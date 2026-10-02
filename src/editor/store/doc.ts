// Document store: the element tree, page settings and patch-based undo/redo.
import { applyPatches, enablePatches, produceWithPatches, type Patch } from 'immer';
import { create } from 'zustand';
import type { ElementNode, Settings } from '@shared/types';
import { config, contentOnly, schemaOf } from '../lib/config';
import { fromTree, insertTree, moveNode, removeNode, reId, subtree, toTree, type Doc } from '../lib/tree';
import { toast } from './ui';

enablePatches();

interface HistoryEntry {
  label: string;
  patches: Patch[];
  inverse: Patch[];
  time: number;
  mergeKey?: string;
  selection?: string[];
}

interface DocState {
  doc: Doc;
  pageSettings: Settings;
  title: string;
  status: string;
  past: HistoryEntry[];
  future: HistoryEntry[];
  version: number;
  savedVersion: number;
}

export const useDoc = create<DocState>(() => ({
  doc: fromTree(config.elements ?? []),
  pageSettings: (config.pageSettings as Settings) ?? {},
  title: config.post.title,
  status: config.post.status,
  past: [],
  future: [],
  version: 0,
  savedVersion: 0,
}));

const MERGE_WINDOW = 900;
const MAX_HISTORY = 200;

/**
 * Applies an undoable change. `mergeKey` coalesces rapid edits of the same control into one step.
 */
export function commit(label: string, recipe: (doc: Doc) => void, opts: { mergeKey?: string; selection?: string[] } = {}): void {
  const state = useDoc.getState();
  const [next, patches, inverse] = produceWithPatches(state.doc, recipe);
  if (!patches.length) return;
  const now = Date.now();
  const last = state.past[state.past.length - 1];
  let past: HistoryEntry[];
  if (opts.mergeKey && last && last.mergeKey === opts.mergeKey && now - last.time < MERGE_WINDOW) {
    past = [...state.past.slice(0, -1), { ...last, patches: [...last.patches, ...patches], inverse: [...inverse, ...last.inverse], time: now }];
  } else {
    past = [...state.past, { label, patches, inverse, time: now, mergeKey: opts.mergeKey, selection: opts.selection }].slice(-MAX_HISTORY);
  }
  useDoc.setState({ doc: next, past, future: [], version: state.version + 1 });
}

/** Mutation that is not undoable (e.g. live inline typing before blur commits). */
export function mutateSilently(recipe: (doc: Doc) => void): void {
  const state = useDoc.getState();
  const [next, patches] = produceWithPatches(state.doc, recipe);
  if (patches.length) useDoc.setState({ doc: next, version: state.version + 1 });
}

export function undo(): string | null {
  const s = useDoc.getState();
  const entry = s.past[s.past.length - 1];
  if (!entry) return null;
  useDoc.setState({
    doc: applyPatches(s.doc, entry.inverse),
    past: s.past.slice(0, -1),
    future: [entry, ...s.future],
    version: s.version + 1,
  });
  return entry.label;
}

export function redo(): string | null {
  const s = useDoc.getState();
  const entry = s.future[0];
  if (!entry) return null;
  useDoc.setState({
    doc: applyPatches(s.doc, entry.patches),
    past: [...s.past, entry],
    future: s.future.slice(1),
    version: s.version + 1,
  });
  return entry.label;
}

export const isDirty = () => {
  const s = useDoc.getState();
  return s.version !== s.savedVersion;
};

export const getTree = (): ElementNode[] => toTree(useDoc.getState().doc);

/* ---------------------------------------------------------------- High-level actions */

/** Builds a fresh element of a type with its panel preset (and child containers for nested widgets). */
export function createElement(type: string, extra: Settings = {}, depth = 1): ElementNode {
  const schema = schemaOf(type);
  const settings: Settings = { ...structuredClone(schema?.preset ?? {}), ...extra };
  const node: ElementNode = { id: '', type, settings };
  if (schema?.container) {
    node.children = [];
    if (depth > 0 && settings.content_width === undefined) {
      // Nested containers default to full width; top-level ones to boxed (renderer rule).
    }
  }
  if (schema?.nested) {
    const key = schema.nested.items;
    let rows: any[] = settings[key] ?? schema.controls[key]?.default ?? [];
    rows = rows.map((r: any) => ({ ...r, _id: r._id || Math.random().toString(36).slice(2, 9) }));
    settings[key] = rows;
    node.children = rows.map(() => ({ id: '', type: 'container', settings: {}, children: [] }));
  }
  return node;
}

/** Content-only roles cannot change the structure: explains why the action did nothing. */
function structureLocked(): boolean {
  if (!contentOnly()) return false;
  toast('Your role can edit content only: texts, images and links of existing elements.', 'info');
  return true;
}

/** Only Content-tab settings (not Advanced "_" keys) of an element, for content-only roles. */
function contentKeys(type: string, values: Settings): Settings {
  const controls = schemaOf(type)?.controls ?? {};
  const out: Settings = {};
  for (const [k, v] of Object.entries(values)) {
    const base = k.replace(/_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/, '');
    if (!k.startsWith('_') && (controls[base]?.tab ?? 'content') === 'content' && controls[base]) out[k] = v;
  }
  return out;
}

export function insertElements(parent: string | null, index: number, nodes: ElementNode[], label = 'Add element'): string[] {
  if (structureLocked()) return [];
  let ids: string[] = [];
  const doc = useDoc.getState().doc;
  const fresh = reId(nodes, doc.nodes);
  commit(label, (d) => {
    ids = insertTree(d, parent, index, fresh);
  });
  return ids;
}

/** The locked element that keeps `id` from changing: itself or an ancestor (null when free). */
export function lockedBy(id: string, doc = useDoc.getState().doc): string | null {
  for (let n = doc.nodes[id]; n; n = n.parent ? doc.nodes[n.parent] : (undefined as any)) if (n.locked) return n.id;
  return null;
}

/** Drops locked elements (and anything inside one) from `ids`, telling the user once. */
export function unlockedOnly(ids: string[]): string[] {
  const doc = useDoc.getState().doc;
  const free = ids.filter((id) => !lockedBy(id, doc));
  if (free.length < ids.length) toast(ids.length === 1 ? 'This element is locked. Unlock it first (right-click → Unlock).' : 'Locked elements were left as they are.', 'info', undefined, 2600);
  return free;
}

export function toggleLocked(id: string): void {
  if (structureLocked()) return;
  const locked = !!useDoc.getState().doc.nodes[id]?.locked;
  commit(locked ? 'Unlock element' : 'Lock element', (d) => {
    const n = d.nodes[id];
    if (!n) return;
    if (n.locked) delete n.locked;
    else n.locked = true;
  });
}

/** Deletes elements (locked ones stay); returns the ids actually deleted. */
export function removeElements(ids: string[]): string[] {
  if (structureLocked()) return [];
  ids = unlockedOnly(ids);
  if (!ids.length) return [];
  commit(ids.length > 1 ? `Delete ${ids.length} elements` : 'Delete element', (d) => {
    for (const id of ids) removeNode(d, id);
  });
  return ids;
}

export function moveElement(id: string, parent: string | null, index: number): void {
  if (structureLocked() || !unlockedOnly([id]).length) return;
  commit('Move element', (d) => moveNode(d, id, parent, index));
}

export function duplicateElement(id: string): string | null {
  const doc = useDoc.getState().doc;
  const n = doc.nodes[id];
  const tree = subtree(doc, id);
  if (!n || !tree) return null;
  const list = n.parent ? doc.nodes[n.parent].children : doc.root;
  const [newId] = insertElements(n.parent, list.indexOf(id) + 1, [tree], 'Duplicate element');
  return newId ?? null;
}

/**
 * Updates settings of one element. `values` may contain `undefined` to delete keys.
 */
export function updateSettings(id: string, values: Settings, opts: { mergeKey?: string; label?: string } = {}): void {
  if (contentOnly()) {
    const type = useDoc.getState().doc.nodes[id]?.type ?? '';
    const allowed = contentKeys(type, values);
    if (Object.keys(allowed).length < Object.keys(values).length) structureLocked();
    if (!Object.keys(allowed).length) return;
    values = allowed;
  }
  if (!unlockedOnly([id]).length) return;
  commit(
    opts.label ?? 'Edit setting',
    (d) => {
      const n = d.nodes[id];
      if (!n) return;
      for (const [k, v] of Object.entries(values)) {
        if (v === undefined) delete n.settings[k];
        else n.settings[k] = v;
      }
    },
    { mergeKey: opts.mergeKey ?? `${id}:${Object.keys(values).join(',')}` },
  );
}

/**
 * One change applied to several elements (multi-selection in the inspector) as a single undo step.
 * `patch` gets each element's settings and returns the keys to set (undefined removes a key).
 */
export function updateSettingsMany(ids: string[], patch: (settings: Settings) => Settings, opts: { mergeKey?: string; label?: string } = {}): void {
  if (contentOnly()) {
    structureLocked();
    return;
  }
  ids = unlockedOnly(ids);
  if (!ids.length) return;
  commit(
    opts.label ?? `Edit ${ids.length} elements`,
    (d) => {
      for (const id of ids) {
        const n = d.nodes[id];
        if (!n) continue;
        for (const [k, v] of Object.entries(patch(n.settings))) {
          if (v === undefined) delete n.settings[k];
          else n.settings[k] = v;
        }
      }
    },
    { mergeKey: opts.mergeKey },
  );
}

export function setDynamic(id: string, key: string, def: any | null): void {
  commit(def ? 'Insert dynamic tag' : 'Remove dynamic tag', (d) => {
    const n = d.nodes[id];
    if (!n) return;
    if (!def) {
      if (n.dynamic) {
        delete n.dynamic[key];
        if (!Object.keys(n.dynamic).length) delete n.dynamic;
      }
      return;
    }
    n.dynamic = { ...(n.dynamic ?? {}), [key]: def };
  });
}

export function renameElement(id: string, label: string): void {
  commit('Rename element', (d) => {
    const n = d.nodes[id];
    if (!n) return;
    if (label.trim()) n.label = label.trim();
    else delete n.label;
  });
}

export function toggleDisabled(id: string): void {
  if (structureLocked()) return;
  commit('Toggle element', (d) => {
    const n = d.nodes[id];
    if (!n) return;
    if (n.disabled) delete n.disabled;
    else n.disabled = true;
  });
}

/** Wraps elements in a new container placed where the first one was. */
export function wrapInContainer(ids: string[]): string | null {
  if (unlockedOnly(ids).length < ids.length) return null;
  if (structureLocked()) return null;
  const doc = useDoc.getState().doc;
  const first = doc.nodes[ids[0]];
  if (!first) return null;
  const parent = first.parent;
  const list = parent ? doc.nodes[parent].children : doc.root;
  const index = list.indexOf(ids[0]);
  let wrapperId = '';
  commit('Wrap in container', (d) => {
    const [cid] = insertTree(d, parent, index, [{ id: '', type: 'container', settings: parent ? {} : {}, children: [] }]);
    wrapperId = cid;
    ids.forEach((id, i) => moveNode(d, id, cid, i));
  });
  return wrapperId;
}

export type NestedOp =
  | { type: 'add'; index: number }
  | { type: 'remove'; index: number }
  | { type: 'move'; from: number; to: number }
  | { type: 'duplicate'; index: number };

/**
 * Updates a nested widget's item repeater and keeps its child containers in step, as one undo step.
 */
export function changeNestedItems(id: string, key: string, rows: any[], op: NestedOp): void {
  if (structureLocked()) return;
  commit('Edit items', (d) => {
    const n = d.nodes[id];
    if (!n) return;
    n.settings[key] = rows;
    if (op.type === 'add') {
      insertTree(d, id, op.index, [{ id: '', type: 'container', settings: {}, children: [] }]);
    } else if (op.type === 'remove') {
      const child = n.children[op.index];
      if (child) removeNode(d, child);
    } else if (op.type === 'move') {
      const child = n.children[op.from];
      if (child) {
        n.children.splice(op.from, 1);
        n.children.splice(op.to, 0, child);
      }
    } else if (op.type === 'duplicate') {
      const child = n.children[op.index];
      const tree = child ? toTree(d as Doc, [child]) : [{ id: '', type: 'container', settings: {}, children: [] }];
      insertTree(d, id, op.index + 1, reId(tree, d.nodes));
    }
  });
}

export function setPageSettings(values: Settings): void {
  if (structureLocked()) return;
  const s = useDoc.getState();
  useDoc.setState({ pageSettings: { ...s.pageSettings, ...values }, version: s.version + 1 });
}

export function setTitle(title: string): void {
  const s = useDoc.getState();
  useDoc.setState({ title, version: s.version + 1 });
}
