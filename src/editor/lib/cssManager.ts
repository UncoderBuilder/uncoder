// Keeps the canvas <style> tags in sync with the document and the Design System.
import { customCss, elementCss, normalizeSettings, Rules } from '@shared/css';
import { kitCss } from '@shared/kit';
import type { Settings } from '@shared/types';
import { config, schemas } from './config';
import { useDoc } from '../store/doc';
import { useKit } from '../store/kit';
import { useUi } from '../store/ui';
import type { NodeRec } from './tree';
import { fonts } from './fonts';

const cache = new WeakMap<object, { dyn: unknown; css: string }>();
let styleEl: HTMLStyleElement | null = null;
let kitEl: HTMLStyleElement | null = null;
let pageEl: HTMLStyleElement | null = null;
let frame = 0;
/** One <style> per top-level section, so an edit makes the browser re-parse only that section's rules. */
let chunks = new Map<string, { el: HTMLStyleElement; css: string }>();

/** The state being edited on the selected element (hover / focus / active), previewed on the canvas. */
let force: { id: string; state: string } | null = null;

function currentForce(): { id: string; state: string } | null {
  const ui = useUi.getState();
  const id = ui.selected.length === 1 ? ui.selected[0] : null;
  return id && ui.inspectorTab !== 'content' && ['hover', 'focus', 'active'].includes(ui.inspectorState) ? { id, state: ui.inspectorState } : null;
}

/** Marks the forced element (an attribute React does not manage, so re-renders keep it). */
function applyForce(doc: Document, next: typeof force) {
  if (force && (!next || next.id !== force.id)) doc.querySelector(`[data-id="${force.id}"]`)?.removeAttribute('data-uncoder-ui-force');
  if (next) doc.querySelector(`[data-id="${next.id}"]`)?.setAttribute('data-uncoder-ui-force', '');
  force = next;
}

function nodeCss(node: NodeRec, used: Map<string, Set<string>>): string {
  if (force && force.id === node.id) {
    return elementCss({ id: node.id, type: node.type, settings: normalizeSettings(node.settings) }, { docId: config.post.id, breakpoints: config.breakpoints, schemas }, used, force.state);
  }
  const hit = cache.get(node.settings);
  if (hit && hit.dyn === node.id) {
    collectFonts(node.settings, used);
    return hit.css;
  }
  const css = elementCss(
    { id: node.id, type: node.type, settings: normalizeSettings(node.settings) },
    { docId: config.post.id, breakpoints: config.breakpoints, schemas },
    used,
  );
  cache.set(node.settings, { dyn: node.id, css });
  return css;
}

function collectFonts(settings: Settings, used: Map<string, Set<string>>) {
  for (const v of Object.values(settings)) {
    if (v && typeof v === 'object' && !Array.isArray(v) && typeof v.family === 'string' && v.family && !v.family.startsWith('var(')) {
      const set = used.get(v.family) ?? new Set<string>();
      set.add(String(v.weight || '400'));
      used.set(v.family, set);
    }
  }
}

function renderDocCss() {
  frame = 0;
  if (!styleEl) return;
  const { doc, pageSettings } = useDoc.getState();
  const used = new Map<string, Set<string>>();
  const live = new Set<string>();
  for (const rootId of doc.root) {
    let css = '';
    const stack = [rootId];
    while (stack.length) {
      const node = doc.nodes[stack.pop()!];
      if (!node) continue;
      css += nodeCss(node, used);
      for (let i = node.children.length - 1; i >= 0; i--) stack.push(node.children[i]);
    }
    live.add(rootId);
    let chunk = chunks.get(rootId);
    if (!chunk) {
      const el = styleEl.ownerDocument.createElement('style');
      el.setAttribute('data-uncoder-ui-section', rootId);
      (pageEl ?? styleEl).before(el);
      chunk = { el, css: '' };
      chunks.set(rootId, chunk);
    }
    if (chunk.css !== css) {
      chunk.el.textContent = css;
      chunk.css = css;
    }
  }
  for (const [rootId, chunk] of chunks) {
    if (live.has(rootId)) continue;
    chunk.el.remove();
    chunks.delete(rootId);
  }
  if (pageEl) {
    let page = '';
    const scope = `.uncoder-${config.post.id}`;
    if (pageSettings.background) page += `${scope}{background-color:${String(pageSettings.background).replace(/[;{}<>]/g, '')}}`;
    const rules = new Rules();
    customCss(pageSettings, 'custom_css', scope, rules, config.breakpoints.map((b) => b.id));
    page += rules.render(config.breakpoints);
    if (pageEl.textContent !== page) pageEl.textContent = page;
  }
  fonts.ensure(used);
}

function renderKitCss() {
  if (!kitEl) return;
  const kit = useKit.getState().kit;
  kitEl.textContent = kitCss(kit, config.breakpoints, config.schema.elements);
  const used = new Map<string, Set<string>>();
  for (const f of kit.fonts ?? []) if (f.family) used.set(f.family, new Set(['400', '500', '600', '700']));
  for (const p of kit.typography ?? []) {
    const fam = p.value?.family;
    if (typeof fam === 'string' && fam && !fam.startsWith('var(')) used.set(fam, new Set([String(p.value.weight || '400')]));
  }
  fonts.ensure(used);
}

const schedule = () => {
  if (!frame) frame = requestAnimationFrame(renderDocCss);
};

let unsubs: Array<() => void> = [];

/** Attaches style tags to the canvas document (called on every iframe load). */
export function attachCss(doc: Document): void {
  detachCss();
  styleEl = doc.createElement('style');
  styleEl.id = 'uncoder-ui-doc-css';
  kitEl = doc.createElement('style');
  kitEl.id = 'uncoder-ui-kit-css';
  pageEl = doc.createElement('style');
  pageEl.id = 'uncoder-ui-page-css';
  // Live kit CSS sits where the site's kit stylesheet is (#uncoder-kit-css or its inline twin), so widget
  // stylesheets still follow it as on the front end — rules of equal weight (a menu link's
  // `color: inherit`) then win the same way. Element CSS comes last.
  const kitTag = doc.getElementById('uncoder-kit-inline-css') ?? doc.getElementById('uncoder-kit-css');
  if (kitTag) kitTag.after(kitEl);
  else doc.head.appendChild(kitEl);
  doc.head.appendChild(styleEl);
  doc.head.appendChild(pageEl);
  renderKitCss();
  renderDocCss();
  unsubs.push(useDoc.subscribe((s, p) => (s.doc !== p.doc || s.pageSettings !== p.pageSettings) && schedule()));
  unsubs.push(useKit.subscribe((s, p) => s.kit !== p.kit && renderKitCss()));
  unsubs.push(
    useUi.subscribe((s, p) => {
      if (s.selected === p.selected && s.inspectorState === p.inspectorState && s.inspectorTab === p.inspectorTab) return;
      const next = currentForce();
      if (next?.id === force?.id && next?.state === force?.state) return;
      applyForce(doc, next);
      // The previously forced element gets its normal (cached) CSS back.
      chunks.forEach((c) => (c.css = ''));
      schedule();
    }),
  );
  // Newly rendered elements (after a re-render or a canvas reload) get the marker again.
  unsubs.push(useDoc.subscribe(() => force && requestAnimationFrame(() => force && doc.querySelector(`[data-id="${force.id}"]`)?.setAttribute('data-uncoder-ui-force', ''))));
}

export function detachCss(): void {
  unsubs.forEach((u) => u());
  unsubs = [];
  chunks = new Map();
  styleEl = kitEl = pageEl = null;
}
