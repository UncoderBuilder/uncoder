// What a row in Layers says about an element: its name (the type, or the name you gave it) and a short summary taken
// from its content, so a page reads as "Heading · Calm, lived-in interiors…" instead of a column of "Heading".
import type { NodeRec as DocNode } from './tree';
import { schemaOf } from './config';

const TEXT_KEYS = ['title', 'text', 'content', 'editor', 'heading', 'label', 'quote', 'question', 'name', 'caption', 'description'];
const MAX = 42;

const clean = (v: unknown): string =>
  typeof v === 'string'
    ? v
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;|&#160;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/\s+/g, ' ')
        .trim()
    : '';
const cut = (s: string): string => (s.length > MAX ? `${s.slice(0, MAX - 1).trimEnd()}…` : s);

/** The first words of an element's own text, if it has any. */
function ownText(n: DocNode): string {
  for (const k of TEXT_KEYS) {
    const t = clean(n.settings?.[k]);
    if (t) return t;
  }
  return '';
}

/** Depth-first: the first heading's text inside a container (a section is known by its heading). */
function firstHeading(nodes: Record<string, DocNode>, id: string, depth = 0): string {
  const n = nodes[id];
  if (!n || depth > 6) return '';
  for (const c of n.children) {
    const child = nodes[c];
    if (!child) continue;
    if (child.type === 'heading') {
      const t = ownText(child);
      if (t) return t;
    }
    const inner = firstHeading(nodes, c, depth + 1);
    if (inner) return inner;
  }
  return '';
}

const fileName = (url: unknown): string => (typeof url === 'string' ? decodeURIComponent(url.split('?')[0].split('/').pop() ?? '') : '');

/** "2 buttons", "3 items": what a container holds. */
function contents(nodes: Record<string, DocNode>, n: DocNode): string {
  const kids = n.children.map((c) => nodes[c]).filter(Boolean) as DocNode[];
  if (!kids.length) return 'empty';
  if (n.settings?.layout === 'grid') {
    const cols = Number(n.settings.grid_columns) || 0;
    return cols ? `Grid ${cols} × ${Math.ceil(kids.length / cols)}` : `Grid · ${kids.length} items`;
  }
  const types = new Set(kids.map((k) => k.type));
  if (types.size === 1 && kids.length > 1) {
    const title = (schemaOf(kids[0].type)?.title ?? kids[0].type).toLowerCase();
    return `${kids.length} ${title.endsWith('s') ? title : `${title}s`}`;
  }
  return kids.length === 1 ? '1 item' : `${kids.length} items`;
}

/**
 * The row's two parts: `name` (the name you gave it, else "Section" for a top-level container, else the type) and
 * `summary` (what it says or holds). `named` is true when the name was given by hand.
 */
export function layerName(nodes: Record<string, DocNode>, id: string): { name: string; summary: string; named: boolean } {
  const n = nodes[id];
  if (!n) return { name: '', summary: '', named: false };
  const schema = schemaOf(n.type);
  const type = schema?.title ?? n.type;
  const container = !!schema?.container;
  const section = container && !n.parent;
  // Named by hand: the summary says what kind of thing it is (a section, what a container holds, the type).
  if (n.label) return { name: n.label, summary: section ? 'Section' : container ? contents(nodes, n) : type, named: true };
  let summary = '';
  if (n.type === 'image') summary = clean(n.settings?.image?.alt) || fileName(n.settings?.image?.url);
  else if (section) summary = firstHeading(nodes, id) || contents(nodes, n);
  else if (container) summary = contents(nodes, n);
  else summary = ownText(n);
  return { name: section ? 'Section' : type, summary: cut(summary), named: false };
}

/** Devices an element is hidden on (Behaviour → Visibility), as a sentence for a tooltip. */
export function hiddenOn(n: DocNode | undefined): string {
  if (!n) return '';
  const list = (['desktop', 'tablet', 'mobile'] as const).filter((d) => n.settings?.[`_hide_${d}`]).map((d) => d[0].toUpperCase() + d.slice(1));
  return list.length ? `Hidden on ${list.join(', ')}` : '';
}
