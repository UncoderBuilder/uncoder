import type { ElementNode, Settings } from '@shared/types';
import { api } from '../lib/api';
import { config, contentOnly, schemaOf } from '../lib/config';
import { subtree } from '../lib/tree';
import {
  commit,
  duplicateElement,
  getTree,
  insertElements,
  isDirty,
  moveElement,
  redo,
  removeElements,
  undo,
  useDoc,
  wrapInContainer,
} from '../store/doc';
import { saveKit, useKit } from '../store/kit';
import { select, toast, useUi } from '../store/ui';
import { setKnownRev, startLiveSync } from './liveSync';
import { afterPublish } from '../store/template';
import { pick, revealAdded, scrollToElement } from './smart';
import { htmlToElements, isHtml } from '../lib/htmlImport';
import { imageFiles, uploadFile } from '../lib/media';
import { insertTarget } from '../canvas/dnd';

const CLIPBOARD_KEY = 'uncoder-ui-clipboard';

export async function save(status?: string): Promise<boolean> {
  const ui = useUi.getState();
  if (ui.saving) return false;
  useUi.setState({ saving: true });
  const docState = useDoc.getState();
  const version = docState.version;
  try {
    // Only users who may edit the Design System save it (POST /kit); a 403 there must not block the page save.
    if (useKit.getState().dirty && config.user.caps.edit_theme) await saveKit();
    const res = await api<{ status: string; title: string; rev?: string; warnings?: string[] }>(`documents/${config.post.id}`, {
      body: {
        elements: getTree(),
        pageSettings: docState.pageSettings,
        title: docState.title,
        ...(status ? { status } : {}),
        ...(docState.pageSettings.template !== undefined ? { pageTemplate: docState.pageSettings.template } : {}),
      },
    });
    setKnownRev(res.rev);
    useDoc.setState({ savedVersion: version, status: res.status });
    useUi.setState({ lastSaved: Date.now() });
    if (res.warnings?.length) toast(`Saved with ${res.warnings.length} adjustment(s): ${res.warnings[0]}`, 'warning', undefined, 7000);
    else toast(({ publish: 'Published', draft: 'Switched to draft', private: 'Made private', pending: 'Submitted for review' } as Record<string, string>)[status ?? ''] ?? 'Saved', 'success', undefined, 2200);
    if (res.status === 'publish') afterPublish();
    return true;
  } catch (e: any) {
    toast(`Save failed: ${e?.message ?? 'unknown error'}`, 'error', { label: 'Retry', run: () => save(status) }, 9000);
    return false;
  } finally {
    useUi.setState({ saving: false });
  }
}

let autosaveTimer: number | null = null;
export function startAutosave(): void {
  if (autosaveTimer) return;
  startLiveSync();
  autosaveTimer = window.setInterval(async () => {
    if (!isDirty()) return;
    try {
      await api(`documents/${config.post.id}/autosave`, { body: { elements: getTree() } });
    } catch {
      /* offline: next tick */
    }
  }, 60000);
  window.setInterval(() => {
    api(`documents/${config.post.id}/lock`, { body: {} }).then((r: any) => {
      if (r?.locked) toast(`${r.user} is also editing this page.`, 'warning', undefined, 8000);
    });
  }, 120000);
  window.addEventListener('beforeunload', (e) => {
    if (isDirty() || useKit.getState().dirty) {
      e.preventDefault();
      e.returnValue = '';
    }
  });
}

/* ---------------------------------------------------------------- Clipboard (works across pages via localStorage) */

export function copySelection(): void {
  const { selected } = useUi.getState();
  const doc = useDoc.getState().doc;
  const trees = selected.map((id) => subtree(doc, id)).filter(Boolean) as ElementNode[];
  if (!trees.length) return;
  try {
    const entry = { v: 1, source: config.post.id, elements: trees, time: Date.now(), sys: false };
    localStorage.setItem(CLIPBOARD_KEY, JSON.stringify(entry));
    // Whether the copy also reached the system clipboard: if not, it beats older HTML found there on paste.
    navigator.clipboard
      ?.writeText(JSON.stringify({ uncoder: 1, elements: trees }))
      .then(() => localStorage.setItem(CLIPBOARD_KEY, JSON.stringify({ ...entry, sys: true })))
      .catch(() => {});
  } catch {
    /* quota */
  }
  toast(trees.length > 1 ? `${trees.length} elements copied` : 'Copied', 'info', undefined, 1600);
}

function readClipboard(): ElementNode[] | null {
  return readClipboardEntry()?.elements ?? null;
}

function readClipboardEntry(): { elements: ElementNode[]; sys?: boolean } | null {
  try {
    const data = JSON.parse(localStorage.getItem(CLIPBOARD_KEY) || 'null');
    return Array.isArray(data?.elements) ? data : null;
  } catch {
    return null;
  }
}

export function pasteAfterSelection(): void {
  const els = readClipboard();
  if (els) pasteElements(els);
}

/** Elements from clipboard text copied in Uncoder on any site ({"uncoder":1,"elements":[…]}), or null. */
function parseClipboardText(text: string): ElementNode[] | null {
  if (!text || text.length > 5_000_000 || !text.trimStart().startsWith('{')) return null;
  try {
    const data = JSON.parse(text);
    if (data?.uncoder !== 1 || !Array.isArray(data.elements)) return null;
    const valid = (n: any): boolean => n && typeof n === 'object' && typeof n.type === 'string' && !!schemaOf(n.type) && (!n.children || (Array.isArray(n.children) && n.children.every(valid)));
    const els = (data.elements as any[]).filter(valid);
    return els.length ? els : null;
  } catch {
    return null;
  }
}

/**
 * Native paste (Ctrl/⌘+V outside text fields): elements copied on another site arrive through the
 * system clipboard; otherwise the same-site clipboard is used.
 */
export function handlePasteEvent(e: ClipboardEvent): void {
  const t = e.target as HTMLElement | null;
  if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable || t.closest?.('[contenteditable="true"], .uncoder-ui-inline-editing'))) return;
  const data = e.clipboardData;
  const text = data?.getData('text/plain') ?? '';
  // 1. Elements copied in Uncoder (this site or another).
  const fromSystem = parseClipboardText(text);
  if (fromSystem) {
    e.preventDefault();
    pasteElements(fromSystem);
    toast(fromSystem.length > 1 ? `${fromSystem.length} elements pasted` : 'Pasted', 'info', undefined, 1600);
    return;
  }
  // 2. Images (a screenshot, a copied picture): upload and insert Image widgets.
  const images = imageFiles(data?.files);
  if (images.length) {
    e.preventDefault();
    insertImages(images);
    return;
  }
  // 3. HTML from a web page, an AI answer or code — unless the last copy was ours and never reached the system clipboard.
  const local = readClipboardEntry();
  const html = data?.getData('text/html') || (isHtml(text) ? text : '');
  if (html && !(local && local.sys === false)) {
    e.preventDefault();
    pasteHtml(html);
    return;
  }
  if (!local) return;
  e.preventDefault();
  pasteElements(local.elements);
}

/** Pasted HTML → elements; its <style> blocks go into the first element's custom CSS. */
export function pasteHtml(html: string): void {
  if (contentOnly()) return;
  const { nodes, css } = htmlToElements(html);
  if (!nodes.length) {
    toast('Nothing in the clipboard could become elements.', 'info');
    return;
  }
  if (css) nodes[0].settings = { ...nodes[0].settings, _custom_css: `${nodes[0].settings._custom_css ?? ''}\n${css}`.trim() };
  const count = (list: ElementNode[]): number => list.reduce((n, x) => n + 1 + count(x.children ?? []), 0);
  pasteElements(nodes, 'Paste HTML');
  toast(`Pasted HTML as ${count(nodes)} element${count(nodes) === 1 ? '' : 's'}`, 'success', { label: 'Undo', run: doUndo }, 4000);
}

/**
 * Uploads images to the media library and adds an Image widget for each: at `at` (a drop position on the
 * canvas) or where Insert would add (after / inside the selection).
 */
export async function insertImages(files: File[], at?: { parent: string | null; index: number }): Promise<void> {
  if (contentOnly() || !files.length) return;
  toast(files.length > 1 ? `Uploading ${files.length} images…` : 'Uploading image…', 'info', undefined, 2500);
  const nodes: ElementNode[] = [];
  for (const file of files) {
    try {
      const up = await uploadFile(file);
      nodes.push({ id: '', type: 'image', settings: { image: { id: up.id, url: up.url, alt: up.alt } }, children: [] });
    } catch (err) {
      toast(`Could not upload ${file.name || 'the image'}: ${err instanceof Error ? err.message : 'error'}`, 'error');
    }
  }
  if (!nodes.length) return;
  const target = at ?? insertTarget('image');
  const wrapped = target.parent === null ? [{ id: '', type: 'container', settings: {}, children: nodes }] : nodes;
  const ids = insertElements(target.parent, target.index, wrapped, nodes.length > 1 ? `Add ${nodes.length} images` : 'Add image');
  revealAdded(ids, { fill: false });
  toast(nodes.length > 1 ? `${nodes.length} images added` : 'Image added', 'success', undefined, 1800);
}

function pasteElements(els: ElementNode[], label = 'Paste'): void {
  const doc = useDoc.getState().doc;
  const sel = useUi.getState().selected[0];
  const node = sel ? doc.nodes[sel] : null;
  let parent: string | null = null;
  let index = doc.root.length;
  if (node) {
    const pastingWidgets = els.some((e) => !schemaOf(e.type)?.container);
    if (schemaOf(node.type)?.container && (pastingWidgets || node.children.length === 0)) {
      parent = node.id;
      index = node.children.length;
    } else {
      parent = node.parent;
      index = (parent ? doc.nodes[parent].children : doc.root).indexOf(node.id) + 1;
    }
  }
  // At page level, loose widgets go into one new section together (containers stay sections of their own).
  const loose = parent === null && els.some((e) => !schemaOf(e.type)?.container);
  const nodes = loose ? [{ id: '', type: 'container', settings: {}, children: els }] : els;
  const ids = insertElements(parent, index, nodes, label);
  select(ids);
  if (ids[0]) scrollToElement(ids[0]);
}

const STYLE_TABS = new Set(['style', 'advanced']);

export function pasteStyle(): void {
  const els = readClipboard();
  if (!els?.length) return;
  const source = els[0];
  const { selected } = useUi.getState();
  commit('Paste style', (d) => {
    for (const id of selected) {
      const n = d.nodes[id];
      if (!n || n.type !== source.type) continue;
      const schema = schemaOf(n.type)!;
      for (const [key, value] of Object.entries(source.settings as Settings)) {
        const base = key.replace(/_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/, '');
        const c = schema.controls[base];
        if (c && STYLE_TABS.has(c.tab ?? '') && !['_css_id'].includes(base)) n.settings[key] = structuredClone(value);
      }
      // State styles (hover, ::before, custom selectors…) travel with the style.
      if (source.settings?._states) n.settings._states = structuredClone(source.settings._states);
    }
  });
  toast('Style pasted', 'info', undefined, 1600);
}

export function resetStyle(): void {
  const { selected } = useUi.getState();
  commit('Reset style', (d) => {
    for (const id of selected) {
      const n = d.nodes[id];
      const schema = n && schemaOf(n.type);
      if (!n || !schema) continue;
      for (const key of Object.keys(n.settings)) {
        const base = key.replace(/_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/, '');
        if (STYLE_TABS.has(schema.controls[base]?.tab ?? '') && schema.controls[base]?.tab === 'style') delete n.settings[key];
      }
      delete n.settings._states;
    }
  });
}

export function deleteSelection(): void {
  const { selected } = useUi.getState();
  const doc = useDoc.getState().doc;
  // Never delete a nested widget's item container directly (edit the widget's items instead).
  const ids = selected.filter((id) => {
    const p = doc.nodes[id]?.parent;
    return !(p && schemaOf(doc.nodes[p].type)?.nested);
  });
  if (!ids.length) return;
  const first = doc.nodes[ids[0]];
  const next = first?.parent ?? null;
  // A refused delete (locked) keeps the selection where it is.
  if (removeElements(ids).length) select(next);
}

export function duplicateSelection(): void {
  const { selected } = useUi.getState();
  const ids = selected.map((id) => duplicateElement(id)).filter(Boolean) as string[];
  if (ids.length) select(ids);
}

/**
 * Moves the selected element one place up or down among its siblings (Ctrl/Cmd + ↑ / ↓). At the start or end
 * of a container it steps out, before or after that container.
 */
export function moveSelection(step: 1 | -1): void {
  const { selected } = useUi.getState();
  if (selected.length !== 1) return;
  const id = selected[0];
  const doc = useDoc.getState().doc;
  const node = doc.nodes[id];
  if (!node) return;
  const list = node.parent ? doc.nodes[node.parent].children : doc.root;
  const at = list.indexOf(id);
  const target = at + step;
  if (target >= 0 && target < list.length) {
    // moveNode() takes the drop position before removal: one further when moving down.
    moveElement(id, node.parent, step > 0 ? at + 2 : at - 1);
  } else if (node.parent && (doc.nodes[node.parent].parent || schemaOf(node.type)?.container)) {
    // Only containers live at the top level of a page.
    const parent = doc.nodes[node.parent];
    const outer = parent.parent ? doc.nodes[parent.parent].children : doc.root;
    const pAt = outer.indexOf(parent.id);
    moveElement(id, parent.parent, step > 0 ? pAt + 1 : pAt);
  } else {
    return;
  }
  scrollToElement(id);
}

export function wrapSelection(): void {
  const { selected } = useUi.getState();
  if (!selected.length) return;
  const id = wrapInContainer(selected);
  if (id) select(id);
}

export function doUndo(): void {
  const label = undo();
  if (label) toast(`Undo: ${label}`, 'info', undefined, 1400);
}

export function doRedo(): void {
  const label = redo();
  if (label) toast(`Redo: ${label}`, 'info', undefined, 1400);
}

export function selectParent(): void {
  const { selected } = useUi.getState();
  const doc = useDoc.getState().doc;
  const p = selected[0] ? doc.nodes[selected[0]]?.parent : null;
  if (p) pick(p);
  else select(null);
}

/**
 * Opens the page in a new tab. With unsaved changes the editor autosaves first and the page renders that
 * autosave for this user only (Draft_Preview), so the tab shows what is on the canvas.
 */
export async function previewPage(): Promise<void> {
  // Open the tab inside the click (popup blockers), then point it at the page once the autosave is stored.
  const tab = window.open('about:blank', '_blank');
  if (tab) tab.opener = null;
  let url = config.post.permalink + (config.post.permalink.includes('?') ? '&' : '?') + 'preview=true';
  if (isDirty()) {
    try {
      await api(`documents/${config.post.id}/autosave`, { body: { elements: getTree() } });
      url += '&uncoder_draft=' + encodeURIComponent(config.post.draftNonce);
    } catch {
      toast('Could not store the unsaved changes; the tab shows the last saved version.', 'warning');
    }
  }
  if (tab) tab.location.href = url;
  else window.open(url, '_blank', 'noopener');
}
