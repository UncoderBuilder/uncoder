// Normalized document model: O(1) node access for the store; converted to/from the saved tree.
import type { DynamicDef, ElementNode, Settings } from '@shared/types';
import { schemaOf } from './config';

export interface NodeRec {
  id: string;
  type: string;
  settings: Settings;
  dynamic?: Record<string, DynamicDef>;
  label?: string;
  disabled?: boolean;
  locked?: boolean;
  parent: string | null;
  children: string[];
}

export interface Doc {
  nodes: Record<string, NodeRec>;
  root: string[];
}

const ID_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789';

export function newId(existing?: Record<string, unknown>): string {
  for (;;) {
    const bytes = crypto.getRandomValues(new Uint8Array(7));
    let id = ID_CHARS[bytes[0] % 26];
    for (let i = 1; i < 7; i++) id += ID_CHARS[bytes[i] % 36];
    if (!existing || !(id in existing)) return id;
  }
}

const cleanSettings = (s: any): Settings => (s && typeof s === 'object' && !Array.isArray(s) ? s : {});

export function fromTree(elements: ElementNode[]): Doc {
  const doc: Doc = { nodes: {}, root: [] };
  const add = (node: ElementNode, parent: string | null): string => {
    let id = node.id && /^[a-z][a-z0-9]{2,31}$/.test(node.id) && !doc.nodes[node.id] ? node.id : newId(doc.nodes);
    const rec: NodeRec = { id, type: node.type, settings: cleanSettings(node.settings), parent, children: [] };
    if (node.dynamic && Object.keys(node.dynamic).length) rec.dynamic = node.dynamic;
    if (node.label) rec.label = node.label;
    if (node.disabled) rec.disabled = true;
    if (node.locked) rec.locked = true;
    doc.nodes[id] = rec;
    rec.children = (node.children ?? []).map((c) => add(c, id));
    return id;
  };
  doc.root = elements.map((e) => add(e, null));
  return doc;
}

export function toTree(doc: Doc, ids: string[] = doc.root): ElementNode[] {
  return ids
    .filter((id) => doc.nodes[id])
    .map((id) => {
      const n = doc.nodes[id];
      const out: ElementNode = { id: n.id, type: n.type, settings: n.settings };
      if (n.dynamic && Object.keys(n.dynamic).length) out.dynamic = n.dynamic;
      if (n.label) out.label = n.label;
      if (n.disabled) out.disabled = true;
      if (n.locked) out.locked = true;
      if (schemaOf(n.type)?.container || schemaOf(n.type)?.nested) out.children = toTree(doc, n.children);
      return out;
    });
}

export function subtree(doc: Doc, id: string): ElementNode | null {
  return doc.nodes[id] ? toTree(doc, [id])[0] : null;
}

/** Deep clone of a tree with fresh ids (for paste / duplicate / templates). */
export function reId(nodes: ElementNode[], existing: Record<string, unknown>): ElementNode[] {
  const taken: Record<string, true> = {};
  const walk = (n: ElementNode): ElementNode => {
    let id = newId(existing);
    while (taken[id]) id = newId(existing);
    taken[id] = true;
    const copy: ElementNode = { ...n, id, settings: structuredClone(n.settings ?? {}) };
    delete copy.locked; // A copy starts unlocked.
    if (n.dynamic) copy.dynamic = structuredClone(n.dynamic);
    // Repeater rows get fresh _ids so {{CURRENT_ITEM}} selectors stay unique.
    for (const [k, v] of Object.entries(copy.settings)) {
      if (Array.isArray(v) && v.length && v[0] && typeof v[0] === 'object' && '_id' in v[0]) {
        copy.settings[k] = v.map((row: any) => ({ ...row, _id: newId() }));
      }
    }
    if (n.children) copy.children = n.children.map(walk);
    return copy;
  };
  return nodes.map(walk);
}

export function siblings(doc: Doc, id: string): string[] {
  const n = doc.nodes[id];
  if (!n) return [];
  return n.parent ? doc.nodes[n.parent].children : doc.root;
}

export function indexOf(doc: Doc, id: string): number {
  return siblings(doc, id).indexOf(id);
}

export function ancestors(doc: Doc, id: string): string[] {
  const out: string[] = [];
  let cur = doc.nodes[id]?.parent ?? null;
  while (cur) {
    out.push(cur);
    cur = doc.nodes[cur]?.parent ?? null;
  }
  return out;
}

export function isDescendant(doc: Doc, id: string, of: string): boolean {
  return ancestors(doc, id).includes(of);
}

export function depthOf(doc: Doc, id: string): number {
  return ancestors(doc, id).length;
}

/* ---------------------------------------------------------------- Mutations (run inside immer drafts) */

export function insertTree(doc: Doc, parent: string | null, index: number, nodes: ElementNode[]): string[] {
  const ids: string[] = [];
  const add = (node: ElementNode, p: string | null): string => {
    const id = node.id && !doc.nodes[node.id] ? node.id : newId(doc.nodes);
    const rec: NodeRec = { id, type: node.type, settings: cleanSettings(node.settings), parent: p, children: [] };
    if (node.dynamic) rec.dynamic = node.dynamic;
    if (node.label) rec.label = node.label;
    doc.nodes[id] = rec;
    rec.children = (node.children ?? []).map((c) => add(c, id));
    return id;
  };
  const list = parent ? doc.nodes[parent].children : doc.root;
  const at = Math.max(0, Math.min(index, list.length));
  nodes.forEach((n, i) => {
    const id = add(n, parent);
    list.splice(at + i, 0, id);
    ids.push(id);
  });
  return ids;
}

export function removeNode(doc: Doc, id: string): void {
  const n = doc.nodes[id];
  if (!n) return;
  const list = n.parent ? doc.nodes[n.parent]?.children : doc.root;
  if (list) {
    const i = list.indexOf(id);
    if (i >= 0) list.splice(i, 1);
  }
  const drop = (nid: string) => {
    const rec = doc.nodes[nid];
    if (!rec) return;
    rec.children.forEach(drop);
    delete doc.nodes[nid];
  };
  drop(id);
}

export function moveNode(doc: Doc, id: string, parent: string | null, index: number): void {
  const n = doc.nodes[id];
  if (!n) return;
  if (parent === id || (parent && isDescendant(doc, parent, id))) return;
  const from = n.parent ? doc.nodes[n.parent].children : doc.root;
  const fromIndex = from.indexOf(id);
  const to = parent ? doc.nodes[parent].children : doc.root;
  if (from === to && fromIndex < index) index -= 1;
  from.splice(fromIndex, 1);
  to.splice(Math.max(0, Math.min(index, to.length)), 0, id);
  n.parent = parent;
}
