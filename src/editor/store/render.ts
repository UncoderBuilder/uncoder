// Server-rendered widget markup cache. Only settings that affect markup trigger a request;
// CSS-only settings (render: 'css') are previewed instantly by the CSS engine.
import { create } from 'zustand';
import type { Settings } from '@shared/types';
import { api } from '../lib/api';
import { config, schemaOf } from '../lib/config';
import type { NodeRec } from '../lib/tree';
import { useDoc } from './doc';

/** A widget as the server renders it: one element (Renderer::merge()) split into its tag, attributes and inner markup. */
export interface Rendered {
  tag: string;
  html: string;
  attrs: Record<string, string>;
  hash: string;
}

interface RenderState {
  items: Record<string, Rendered>;
  pending: Record<string, boolean>;
  errors: Record<string, string>;
}

export const useRender = create<RenderState>(() => ({ items: {}, pending: {}, errors: {} }));

const byHash = new Map<string, Rendered>();
let queue = new Map<string, { node: NodeRec; hash: string }>();
/** Newest hash asked for per element: a slower, older response never overwrites a newer one. */
const latest = new Map<string, string>();
let timer: number | null = null;
let inflight = 0;
let booted = false;

/** Keys whose value changes the markup (not handled by CSS alone). */
export function templateSettings(node: NodeRec): Settings {
  const schema = schemaOf(node.type);
  if (!schema) return node.settings;
  const out: Settings = {};
  for (const [key, value] of Object.entries(node.settings)) {
    const base = key.replace(/_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/, '');
    const control = schema.controls[key] ?? schema.controls[base];
    if (control && (control.render === 'css' || control.render === 'none') && key !== '_animation') continue;
    if (key === '_custom_css') continue;
    out[key] = value;
  }
  return out;
}

export function renderHash(node: NodeRec, contextPost: number): string {
  return JSON.stringify([node.type, templateSettings(node), node.dynamic ?? null, schemaOf(node.type)?.render === 'dynamic' ? contextPost : 0]);
}

/** The post dynamic widgets render with: the template's "Preview with" choice, or 0 to let the server pick sample content. */
export function contextPost(): number {
  return Number(useDoc.getState().pageSettings.preview_post) || 0;
}

/** Ensures markup for a widget node is current; schedules a batched request when needed. */
export function requestRender(node: NodeRec): void {
  const hash = renderHash(node, contextPost());
  const current = useRender.getState().items[node.id];
  if (current && current.hash === hash) {
    // Back to what is shown (e.g. undo while a render was on its way): drop the outdated request.
    if (latest.has(node.id)) settle(node.id);
    return;
  }
  const cached = byHash.get(node.type + '|' + hash);
  if (cached && schemaOf(node.type)?.render !== 'dynamic') {
    // Same template settings elsewhere (e.g. a freshly dropped default widget): reuse, but with this id.
    if (latest.has(node.id)) settle(node.id);
    useRender.setState((s) => ({ items: { ...s.items, [node.id]: { ...cached, attrs: retarget(cached.attrs, node.id), hash } } }));
    return;
  }
  queue.set(node.id, { node, hash });
  latest.set(node.id, hash);
  useRender.setState((s) => ({ pending: { ...s.pending, [node.id]: true } }));
  // First load: every widget is queued in the same tick, so send at once; later, wait out typing.
  if (timer === null) timer = window.setTimeout(flush, !booted ? 0 : inflight ? 160 : 90);
}

/** Marks the current markup as matching new settings (used after inline edits already applied in the DOM). */
export function acceptCurrent(node: NodeRec, html?: string): void {
  const hash = renderHash(node, contextPost());
  useRender.setState((s) => {
    const cur = s.items[node.id];
    if (!cur) return s;
    return { items: { ...s.items, [node.id]: { ...cur, html: html ?? cur.html, hash } } };
  });
}

function settle(id: string) {
  latest.delete(id);
  queue.delete(id);
  useRender.setState((s) => {
    const pending = { ...s.pending };
    delete pending[id];
    return { pending };
  });
}

/** The same markup for another element: its own class uncoder-{id} follows the new element id. */
function retarget(attrs: Record<string, string>, id: string): Record<string, string> {
  const out = { ...attrs };
  const old = out['data-id'];
  if (old && old !== id && out.class) out.class = out.class.split(/\s+/).map((c) => (c === `uncoder-${old}` ? `uncoder-${id}` : c)).join(' ');
  if (out['data-id']) out['data-id'] = id;
  return out;
}

/** Splits the finished element into tag / attributes / inner markup (the canvas renders the tag itself). */
function parseOuter(r: { html: string; attrs: Record<string, string>; outer?: string }): Omit<Rendered, 'hash'> {
  if (r.outer) {
    const t = document.createElement('template');
    t.innerHTML = r.outer;
    const root = t.content.firstElementChild;
    if (root) {
      const attrs: Record<string, string> = {};
      for (const a of Array.from(root.attributes)) attrs[a.name] = a.value;
      return { tag: root.localName, attrs, html: root.innerHTML };
    }
  }
  return { tag: 'div', attrs: normalizeAttrs(r.attrs), html: r.html };
}

const RENDER_BATCH = 60;
/** Batches in flight at once: a large page loads in a few parallel round trips instead of a long chain. */
const MAX_INFLIGHT = 4;

function flush() {
  timer = null;
  booted = true;
  // The server renders at most 80 elements per request (Render_Controller::MAX_BATCH): send large
  // pages in chunks, several at a time, and keep the rest queued until a slot frees up.
  while (queue.size && inflight < MAX_INFLIGHT) {
    const all = [...queue.values()];
    queue = new Map(all.slice(RENDER_BATCH).map((entry) => [entry.node.id, entry]));
    void send(all.slice(0, RENDER_BATCH));
  }
}

async function send(batch: Array<{ node: NodeRec; hash: string }>) {
  inflight++;
  try {
    const res = await api<{ items: Record<string, { html: string; attrs: Record<string, string>; outer?: string }> }>('render', {
      body: {
        post_id: config.post.id,
        context_post: contextPost(),
        elements: batch.map(({ node }) => ({ id: node.id, type: node.type, settings: node.settings, dynamic: node.dynamic })),
      },
    });
    useRender.setState((s) => {
      const items = { ...s.items };
      const pending = { ...s.pending };
      const errors = { ...s.errors };
      for (const { node, hash } of batch) {
        const r = res.items?.[node.id];
        const current = latest.get(node.id) === hash;
        if (r) byHash.set(node.type + '|' + hash, { ...parseOuter(r), hash });
        if (!current) continue;
        latest.delete(node.id);
        delete pending[node.id];
        if (!r) {
          // Never leave a widget as a loading placeholder forever.
          errors[node.id] = 'The server did not render this element.';
          continue;
        }
        items[node.id] = byHash.get(node.type + '|' + hash)!;
        delete errors[node.id];
      }
      return { items, pending, errors };
    });
  } catch (e: any) {
    useRender.setState((s) => {
      const pending = { ...s.pending };
      const errors = { ...s.errors };
      for (const { node, hash } of batch) {
        if (latest.get(node.id) !== hash) continue;
        latest.delete(node.id);
        delete pending[node.id];
        errors[node.id] = e?.message ?? 'Render failed';
      }
      return { pending, errors };
    });
  } finally {
    inflight--;
    if (queue.size && timer === null) timer = window.setTimeout(flush, 0);
  }
}

function normalizeAttrs(attrs: any): Record<string, string> {
  const out: Record<string, string> = {};
  if (attrs && typeof attrs === 'object' && !Array.isArray(attrs)) {
    for (const [k, v] of Object.entries(attrs)) {
      if (v === null || v === false || v === undefined) continue;
      out[k] = v === true ? '' : String(v);
    }
  }
  return out;
}

export function forgetRender(id: string): void {
  useRender.setState((s) => {
    if (!(id in s.items)) return s;
    const items = { ...s.items };
    delete items[id];
    return { items };
  });
}
