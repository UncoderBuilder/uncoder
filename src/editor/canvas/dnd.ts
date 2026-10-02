// Pointer-driven drag & drop that spans the editor window and the canvas iframe.
// While dragging, the iframe ignores pointer events; hit-testing uses iframeDocument.elementFromPoint.
import type { ElementNode } from '@shared/types';
import { contentOnly, schemaOf } from '../lib/config';
import { isDescendant } from '../lib/tree';
import { commit, createElement, lockedBy, unlockedOnly, useDoc } from '../store/doc';
import { useUi, viewZoom, type DropTarget } from '../store/ui';
import { frame, toCanvas, toParent } from './frame';
import { insertTree, moveNode, reId } from '../lib/tree';
import { iconSvg } from '../lib/icons';
import { revealAdded } from '../app/smart';

export type DragPayload = { kind: 'new'; type: string; tree?: ElementNode[] } | { kind: 'move'; ids: string[] };

const EDGE = 10;
let active: {
  payload: DragPayload;
  ghost: HTMLElement;
  startX: number;
  startY: number;
  started: boolean;
  scrollRaf: number;
  lastPoint: { x: number; y: number } | null;
} | null = null;

type Rect = { x: number; y: number; w: number; h: number };

function docRect(el: Element): Rect {
  const r = el.getBoundingClientRect();
  const win = frame.win!;
  return { x: r.left + win.scrollX, y: r.top + win.scrollY, w: r.width, h: r.height };
}

/** The element that lays out a container's children (the inner div when boxed). */
function layoutEl(containerEl: HTMLElement): HTMLElement {
  const inner = containerEl.querySelector(':scope > .uncoder-container__inner') as HTMLElement | null;
  return inner ?? containerEl;
}

function childEls(layout: HTMLElement): HTMLElement[] {
  return Array.from(layout.children).filter((c) => (c as HTMLElement).dataset?.id) as HTMLElement[];
}

function axisOf(layout: HTMLElement): 'x' | 'y' | 'grid' {
  const cs = frame.win!.getComputedStyle(layout);
  if (cs.display.includes('grid')) return 'grid';
  if (cs.display.includes('flex') && cs.flexDirection.startsWith('row')) return 'x';
  return 'y';
}

function isDragged(id: string, payload: DragPayload): boolean {
  if (payload.kind !== 'move') return false;
  const doc = useDoc.getState().doc;
  return payload.ids.some((d) => d === id || isDescendant(doc, id, d));
}

function lineBetween(axis: 'x' | 'y' | 'grid', layout: HTMLElement, kids: HTMLElement[], index: number): Rect {
  const box = docRect(layout);
  const vertical = axis === 'x' || axis === 'grid';
  if (!kids.length) return { x: box.x + 4, y: box.y + box.h / 2 - 1, w: box.w - 8, h: 2 };
  if (vertical) {
    const ref = kids[Math.min(index, kids.length - 1)];
    const r = docRect(ref);
    const x = index >= kids.length ? r.x + r.w + 2 : r.x - 3;
    return { x: x - 1, y: r.y, w: 3, h: r.h };
  }
  const before = kids[index - 1] ? docRect(kids[index - 1]) : null;
  const after = kids[index] ? docRect(kids[index]) : null;
  const y = before && after ? (before.y + before.h + after.y) / 2 : after ? after.y - 2 : before!.y + before!.h + 2;
  const ref = after ?? before!;
  return { x: ref.x, y: y - 1, w: ref.w, h: 3 };
}

function insertionIndex(axis: 'x' | 'y' | 'grid', kids: HTMLElement[], x: number, y: number): number {
  if (axis === 'grid') {
    let best = 0;
    let bestD = Infinity;
    kids.forEach((k, i) => {
      const r = k.getBoundingClientRect();
      const d = Math.hypot(x - (r.left + r.width / 2), y - (r.top + r.height / 2));
      if (d < bestD) {
        bestD = d;
        best = x > r.left + r.width / 2 ? i + 1 : i;
      }
    });
    return best;
  }
  for (let i = 0; i < kids.length; i++) {
    const r = kids[i].getBoundingClientRect();
    if (axis === 'x' ? x < r.left + r.width / 2 : y < r.top + r.height / 2) return i;
  }
  return kids.length;
}

/** Computes where a drop at canvas point (x, y) would land. */
export function computeDrop(x: number, y: number, payload: DragPayload): DropTarget | null {
  const idoc = frame.doc;
  if (!idoc || !frame.mount) return null;
  const doc = useDoc.getState().doc;
  const draggingContainer =
    payload.kind === 'new' ? !!schemaOf(payload.type)?.container : payload.ids.every((id) => schemaOf(doc.nodes[id]?.type)?.container);

  let hit = idoc.elementFromPoint(x, y) as HTMLElement | null;
  let el = hit?.closest?.('[data-id]') as HTMLElement | null;
  while (el && (isDragged(el.dataset.id!, payload) || !frame.mount.contains(el))) {
    el = el.parentElement?.closest('[data-id]') as HTMLElement | null;
  }

  // Outside every element: append to the page root (or before the first element when above it).
  if (!el) {
    const roots = childEls(frame.mount);
    const index = insertionIndex('y', roots, x, y);
    return { parent: null, index, line: lineBetween('y', frame.mount, roots, index) };
  }

  const id = el.dataset.id!;
  const node = doc.nodes[id];
  if (!node) return null;
  const parentNode = node.parent ? doc.nodes[node.parent] : null;
  const parentIsNested = parentNode ? !!schemaOf(parentNode.type)?.nested : false;

  if (schemaOf(node.type)?.container) {
    const r = el.getBoundingClientRect();
    const parentLayout = el.parentElement ? (el.parentElement.closest('[data-id], #uncoder-canvas-root') as HTMLElement | null) : null;
    const parentAxis = parentLayout ? axisOf(parentLayout.id === 'uncoder-canvas-root' ? parentLayout : layoutEl(parentLayout)) : 'y';
    // Near the container's own edge: drop beside it (escape nesting) — not for nested-widget slots.
    if (!parentIsNested) {
      const nearStart = parentAxis === 'x' ? x - r.left < EDGE : y - r.top < EDGE;
      const nearEnd = parentAxis === 'x' ? r.right - x < EDGE : r.bottom - y < EDGE;
      if (nearStart || nearEnd) {
        const siblings = node.parent ? doc.nodes[node.parent].children : doc.root;
        const index = siblings.indexOf(id) + (nearEnd ? 1 : 0);
        const parentEl = node.parent ? layoutEl(parentLayout!) : frame.mount;
        const kids = childEls(parentEl);
        return validate({ parent: node.parent, index, line: lineBetween(parentAxis, parentEl, kids, index) }, payload, draggingContainer);
      }
    }
    const layout = layoutEl(el);
    const kids = childEls(layout).filter((k) => !isDragged(k.dataset.id!, payload));
    const axis = axisOf(layout);
    const index = insertionIndex(axis, kids, x, y);
    const realIndex = index < kids.length ? node.children.indexOf(kids[index].dataset.id!) : node.children.length;
    return validate(
      {
        parent: id,
        index: realIndex < 0 ? node.children.length : realIndex,
        line: kids.length ? lineBetween(axis, layout, kids, index) : undefined,
        box: docRect(el),
      },
      payload,
      draggingContainer,
    );
  }

  // A widget: drop before/after it inside its parent container.
  const parentEl = el.parentElement?.closest('[data-id]') as HTMLElement | null;
  if (!node.parent || !parentEl) return null;
  const layout = layoutEl(parentEl);
  const axis = axisOf(layout);
  const r = el.getBoundingClientRect();
  const after = axis === 'x' || axis === 'grid' ? x > r.left + r.width / 2 : y > r.top + r.height / 2;
  const siblings = doc.nodes[node.parent].children;
  const index = siblings.indexOf(id) + (after ? 1 : 0);
  const kids = childEls(layout);
  const domIndex = kids.indexOf(el) + (after ? 1 : 0);
  return validate({ parent: node.parent, index, line: lineBetween(axis, layout, kids, domIndex), box: docRect(parentEl) }, payload, draggingContainer);
}

function validate(target: DropTarget, payload: DragPayload, draggingContainer: boolean): DropTarget | null {
  const doc = useDoc.getState().doc;
  if (target.parent) {
    const parent = doc.nodes[target.parent];
    if (!parent) return null;
    // Nested widgets only own their item containers; locked elements take nothing new.
    if (schemaOf(parent.type)?.nested || lockedBy(parent.id, doc)) return null;
    if (payload.kind === 'move' && payload.ids.some((id) => id === target.parent || isDescendant(doc, target.parent!, id))) return null;
  }
  void draggingContainer;
  return target;
}

/* ---------------------------------------------------------------- Drag lifecycle */

export function beginDrag(e: { clientX: number; clientY: number; pointerId?: number }, payload: DragPayload, label: string, icon: string): void {
  if (contentOnly()) return;
  if (payload.kind === 'move' && unlockedOnly(payload.ids).length < payload.ids.length) return;
  if (active) cancel();
  const ghost = document.createElement('div');
  ghost.className = 'uncoder-ui-drag-ghost';
  ghost.innerHTML = `${iconSvg(icon, 14)}<span></span>`;
  ghost.querySelector('span')!.textContent = label;
  (document.querySelector('.uncoder-ui-portal') ?? document.body).appendChild(ghost);
  active = { payload, ghost, startX: e.clientX, startY: e.clientY, started: false, scrollRaf: 0, lastPoint: null };
  // From the first moment: the canvas stops taking pointer events (a drag that starts on the element
  // toolbar inside the iframe would otherwise only be seen once the pointer left the canvas) and the
  // grabbing cursor shows at once.
  if (frame.iframe) frame.iframe.style.pointerEvents = 'none';
  document.body.classList.add('uncoder-ui-is-dragging');
  frame.doc?.documentElement.classList.add('uncoder-ui-is-dragging');
  window.addEventListener('pointermove', onMove, true);
  window.addEventListener('pointerup', onUp, true);
  window.addEventListener('pointercancel', cancel, true);
  window.addEventListener('keydown', onKey, true);
  window.addEventListener('blur', cancel);
  // A drag that starts inside the canvas (element toolbar, section handle, an element itself) keeps getting
  // its pointer events in the canvas frame even with the frame's pointer events off: forward them.
  frame.doc?.addEventListener('pointermove', onFrameMove, true);
  frame.doc?.addEventListener('pointerup', onFrameUp, true);
  frame.doc?.addEventListener('pointercancel', cancel, true);
}

/** A canvas pointer event in editor-window coordinates. */
function fromFrame(e: PointerEvent): PointerEvent {
  const p = toParent(e.clientX, e.clientY, viewZoom());
  return { clientX: p.x, clientY: p.y, target: e.target, preventDefault: () => e.preventDefault() } as unknown as PointerEvent;
}
function onFrameMove(e: PointerEvent) {
  onMove(fromFrame(e));
}
function onFrameUp(e: PointerEvent) {
  onUp(fromFrame(e));
}

function start() {
  if (!active) return;
  active.started = true;
  const p = active.payload;
  useUi.setState({ dragging: { kind: p.kind, label: active.ghost.textContent || '', icon: '', ids: p.kind === 'move' ? p.ids : undefined, type: p.kind === 'new' ? p.type : undefined } });
  autoScroll();
}

function onMove(e: PointerEvent) {
  if (!active) return;
  if (!active.started) {
    if (Math.hypot(e.clientX - active.startX, e.clientY - active.startY) < 5) return;
    start();
  }
  e.preventDefault();
  active.ghost.style.transform = `translate(${e.clientX + 14}px, ${e.clientY + 12}px)`;
  active.ghost.classList.add('is-visible');
  const zoom = viewZoom();
  const p = toCanvas(e.clientX, e.clientY, zoom);
  active.lastPoint = p;
  if (!p) {
    useUi.setState({ drop: null });
    active.ghost.classList.add('is-invalid');
    return;
  }
  const drop = computeDrop(p.x, p.y, active.payload);
  active.ghost.classList.toggle('is-invalid', !drop);
  useUi.setState({ drop });
}

function autoScroll() {
  if (!active || !active.started) return;
  const p = active.lastPoint;
  const win = frame.win;
  if (p && win) {
    const h = win.innerHeight;
    const zone = 70;
    if (p.y < zone) win.scrollBy(0, -Math.ceil((zone - p.y) / 4));
    else if (p.y > h - zone) win.scrollBy(0, Math.ceil((p.y - (h - zone)) / 4));
  }
  active.scrollRaf = requestAnimationFrame(autoScroll);
}

function onUp(e: PointerEvent) {
  if (!active) return;
  const { started, payload } = active;
  const drop = useUi.getState().drop;
  cleanup();
  if (!started) {
    // A click on a panel tile without dragging: insert after the selection.
    if (payload.kind === 'new' && (e.target as Element)?.closest?.('[data-uncoder-ui-tile]')) insertNearSelection(payload);
    return;
  }
  if (drop) performDrop(drop, payload);
}

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    e.preventDefault();
    cancel();
  }
}

export function cancel() {
  cleanup();
}

function cleanup() {
  if (!active) return;
  cancelAnimationFrame(active.scrollRaf);
  active.ghost.remove();
  active = null;
  window.removeEventListener('pointermove', onMove, true);
  window.removeEventListener('pointerup', onUp, true);
  window.removeEventListener('pointercancel', cancel, true);
  window.removeEventListener('keydown', onKey, true);
  window.removeEventListener('blur', cancel);
  frame.doc?.removeEventListener('pointermove', onFrameMove, true);
  frame.doc?.removeEventListener('pointerup', onFrameUp, true);
  frame.doc?.removeEventListener('pointercancel', cancel, true);
  if (frame.iframe) frame.iframe.style.pointerEvents = '';
  document.body.classList.remove('uncoder-ui-is-dragging');
  frame.doc?.documentElement.classList.remove('uncoder-ui-is-dragging');
  useUi.setState({ dragging: null, drop: null });
}

/* ---------------------------------------------------------------- Drop */

function wrapIfRoot(parent: string | null, nodes: ElementNode[]): ElementNode[] {
  if (parent !== null) return nodes;
  return nodes.map((n) => (schemaOf(n.type)?.container ? n : { id: '', type: 'container', settings: {}, children: [n] }));
}

export function performDrop(drop: DropTarget, payload: DragPayload): void {
  if (payload.kind === 'new') {
    if (!payload.tree) useUi.setState((s) => ({ recentWidgets: [payload.type, ...s.recentWidgets.filter((n) => n !== payload.type)].slice(0, 6) }));
    const base = payload.tree ?? [createElement(payload.type)];
    const nodes = wrapIfRoot(drop.parent, base);
    const doc = useDoc.getState().doc;
    const fresh = reId(nodes, doc.nodes);
    let ids: string[] = [];
    commit(`Add ${schemaOf(payload.type)?.title ?? 'element'}`, (d) => {
      ids = insertTree(d, drop.parent, drop.index, fresh);
    });
    // Select the dropped widget itself, not the auto-created wrapper.
    const first = fresh[0];
    const target = drop.parent === null && first.type === 'container' && payload.type !== 'container' && first.children?.[0] ? first.children[0].id : ids[0];
    if (target) revealAdded([target], { fill: !payload.tree });
    return;
  }
  const doc = useDoc.getState().doc;
  const ids = payload.ids.filter((id) => doc.nodes[id]);
  if (!ids.length) return;
  commit(ids.length > 1 ? 'Move elements' : 'Move element', (d) => {
    let index = drop.index;
    let parent = drop.parent;
    if (parent === null && ids.some((id) => !schemaOf(d.nodes[id].type)?.container)) {
      const [wrapper] = insertTree(d, null, index, [{ id: '', type: 'container', settings: {}, children: [] }]);
      parent = wrapper;
      index = 0;
    }
    for (const id of ids) {
      const before = d.nodes[id].parent === parent ? (parent ? d.nodes[parent].children : d.root).indexOf(id) : -1;
      moveNode(d, id, parent, index);
      if (before === -1 || before >= index) index++;
    }
  });
}

/**
 * Where a click in Insert adds an element of `type`: inside a selected container (at the end), after a
 * selected widget, or a new section at the end of the page.
 */
export function insertTarget(type: string): { parent: string | null; index: number } {
  const doc = useDoc.getState().doc;
  const sel = useUi.getState().selected[0];
  const node = sel ? doc.nodes[sel] : null;
  let parent: string | null = null;
  let index = doc.root.length;
  if (node) {
    if (schemaOf(node.type)?.container && !schemaOf(type)?.container) {
      parent = node.id;
      index = node.children.length;
    } else {
      parent = node.parent;
      const list = parent ? doc.nodes[parent].children : doc.root;
      index = list.indexOf(node.id) + 1;
    }
    if (parent && schemaOf(doc.nodes[parent].type)?.nested) {
      const p = doc.nodes[parent];
      parent = p.parent;
      index = (parent ? doc.nodes[parent].children : doc.root).indexOf(p.id) + 1;
    }
  }
  return { parent, index };
}

/** Inserts a new element where insertTarget() says (after the selection, inside it, or at the page end). */
export function insertNearSelection(payload: Extract<DragPayload, { kind: 'new' }>): void {
  performDrop(insertTarget(payload.type), payload);
}
