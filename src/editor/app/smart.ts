// The editor following what you do: the build panel switches with the selection, the Insert search is one
// keystroke away, and the keyboard walks the tree (PLAN.md §18).
import { contentOnly, schemaOf } from '../lib/config';
import { useDoc } from '../store/doc';
import { select, useUi, type LeftPanel } from '../store/ui';
import { elementFor, frame } from '../canvas/frame';
import { startInline } from '../canvas/inline';
import { usePrefs } from '../store/prefs';

/** Build-panel views that follow the selection; Styles, Page and the tool views stay where the user put them. */
const FOLLOWING: LeftPanel[] = ['add', 'library', 'layers'];

/** A container (or a tab / accordion / slide slot) with nothing in it yet: the next step there is adding something. */
export function isEmptyContainer(id: string | null | undefined): boolean {
  const node = id ? useDoc.getState().doc.nodes[id] : undefined;
  return !!node && !!schemaOf(node.type)?.container && node.children.length === 0;
}

/**
 * Selects what the user pointed at (canvas, breadcrumbs, section handle, keyboard) and lets the build panel
 * follow: Layers for an element, Insert for an empty container. Selections the editor makes itself (after
 * inserting, pasting, undo) and clicks in the Layers tree use select() and leave the panel alone.
 */
export function pick(id: string | null, additive = false): void {
  select(id, additive);
  if (additive || !id || !usePrefs.getState().autoPanels) return;
  const panel = useUi.getState().panel;
  if (!FOLLOWING.includes(panel)) return;
  if (isEmptyContainer(id) && !contentOnly()) {
    if (panel !== 'add') useUi.setState({ panel: 'add' });
  } else if (panel !== 'layers') {
    useUi.setState({ panel: 'layers' });
  }
}

/** The first empty container in a subtree (depth first), e.g. the first column of a new two-column layout. */
function firstEmptyContainer(id: string): string | null {
  if (isEmptyContainer(id)) return id;
  const node = useDoc.getState().doc.nodes[id];
  if (!node || !schemaOf(node.type)?.container) return null;
  for (const child of node.children) {
    const hit = firstEmptyContainer(child);
    if (hit) return hit;
  }
  return null;
}

/** Scrolls the canvas to an element (once it has rendered) unless it is already in view. */
export function scrollToElement(id: string): void {
  requestAnimationFrame(() =>
    requestAnimationFrame(() => {
      const el = elementFor(id);
      const win = frame.win;
      if (!el || !win) return;
      const r = el.getBoundingClientRect();
      if (r.top >= 48 && r.bottom <= win.innerHeight - 32) return;
      el.scrollIntoView({ block: r.height < win.innerHeight - 120 ? 'center' : 'start', behavior: 'smooth' });
    }),
  );
}

/**
 * After the user adds something (section +, layouts, Insert, saved sections, patterns): select it — for new
 * boxes their first empty container, where the next widget goes — open Insert when that is an empty
 * container, and scroll it into view. `fill: false` selects the added element itself (content sections).
 */
export function revealAdded(ids: string[], opts: { fill?: boolean } = {}): void {
  const doc = useDoc.getState().doc;
  const first = ids.find((id) => doc.nodes[id]);
  if (!first) return;
  const target = (opts.fill !== false && firstEmptyContainer(first)) || first;
  select(target);
  if (isEmptyContainer(target) && !contentOnly() && useUi.getState().panel !== 'add') useUi.setState({ panel: 'add' });
  scrollToElement(target);
}

let pendingSearch: string | null | undefined;

/**
 * Opens Insert → Elements and focuses its search, optionally starting it with `text` (type-to-search).
 * Called for deliberate "I want to add something" moves only, so automatic panel switches never take the
 * keyboard away from the canvas.
 */
export function focusInsertSearch(text?: string): void {
  if (contentOnly()) return;
  pendingSearch = text ?? null;
  if (useUi.getState().panel !== 'add') useUi.setState({ panel: 'add' });
  window.dispatchEvent(new Event('uncoder-ui:insert-search'));
}

/** For the Insert search box: the pending request (undefined when there is none), consumed once. */
export function takeInsertSearch(): string | null | undefined {
  const t = pendingSearch;
  pendingSearch = undefined;
  return t;
}

/* ---------------------------------------------------------------- Keyboard: walking the tree */

/** Focus is on the page or in the canvas (not on a panel control), so Enter / Tab can mean the tree. */
export function canvasHasFocus(e: KeyboardEvent): boolean {
  const t = e.target as Node | null;
  if (!t || t === document.body || t === document.documentElement || t === document) return true;
  return !!frame.doc && (t as Node).ownerDocument === frame.doc;
}

const siblingsOf = (id: string): string[] => {
  const doc = useDoc.getState().doc;
  const parent = doc.nodes[id]?.parent;
  return parent ? doc.nodes[parent]?.children ?? [] : doc.root;
};

/** Enter: into the first child; on a text widget, start editing its text in place. */
export function enterSelection(): boolean {
  const id = useUi.getState().selected[0];
  const node = id ? useDoc.getState().doc.nodes[id] : undefined;
  if (!node) return false;
  if (node.children.length) {
    pick(node.children[0]);
    return true;
  }
  const el = elementFor(node.id);
  const target = el && ((el.matches('[data-uncoder-inline]') ? el : el.querySelector('[data-uncoder-inline]')) as HTMLElement | null);
  return !!target && startInline(node.id, target);
}

/** Tab / Shift+Tab: next / previous sibling, wrapping around. */
export function selectSibling(step: 1 | -1): boolean {
  const id = useUi.getState().selected[0];
  if (!id) {
    const first = useDoc.getState().doc.root[0];
    if (first) pick(first);
    return !!first;
  }
  const list = siblingsOf(id);
  if (list.length < 2) return false;
  const next = list[(list.indexOf(id) + step + list.length) % list.length];
  pick(next);
  elementFor(next)?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  return true;
}
