// Where the inspector sits (store/ui inspectorAt): docked next to the build panel (default), docked on the
// right, or floating anywhere. The grip in its header floats it (click) or moves it (drag); dragging it to the
// left or right edge shows where it will dock and docks it on release. The dock menu offers all three.
import type { KeyboardEvent as ReactKeyboardEvent, PointerEvent as ReactPointerEvent } from 'react';
import { placeInspector, useUi, type InspectorAt, type InspectorFloat } from '../store/ui';
import { Icon } from '../ui/Icon';
import { Menu, usePopover } from '../ui/Popover';
import { IconButton } from '../ui/primitives';

const AT_ICON: Record<InspectorAt, string> = { left: 'panel-left', right: 'panel-right', float: 'picture-in-picture-2' };
const AT_LABEL: Record<InspectorAt, string> = { left: 'Docked left', right: 'Docked right', float: 'Floating' };
/** How close to an edge (px) a dragged panel docks there. */
const SNAP = 56;
/** Floating panels keep this much room to the window edges. */
const EDGE = 8;

const appEl = () => document.querySelector<HTMLElement>('.uncoder-ui-app');
const rectOf = (selector: string) => document.querySelector<HTMLElement>(selector)?.getBoundingClientRect() ?? null;

/** Keeps a floating panel fully on screen, below the command bar. */
export function clampFloat(f: InspectorFloat, width: number): InspectorFloat {
  const top = (rectOf('.uncoder-ui-topbar')?.bottom ?? 0) + EDGE;
  const h = Math.max(320, Math.min(f.h, window.innerHeight - top - EDGE));
  const x = Math.min(Math.max(EDGE, f.x), Math.max(EDGE, window.innerWidth - width - EDGE));
  const y = Math.min(Math.max(top, f.y), Math.max(top, window.innerHeight - h - EDGE));
  return { x: Math.round(x), y: Math.round(y), h: Math.round(h) };
}

/** Lifted from where the docked panel is: a little in, a little shorter, so it reads as floating. */
function lifted(panel: HTMLElement): InspectorFloat {
  const r = panel.getBoundingClientRect();
  const inward = useUi.getState().inspectorAt === 'right' ? -16 : 16;
  return clampFloat({ x: r.left + inward, y: r.top + 16, h: r.height - 48 }, r.width);
}

/** The column a panel would dock into on each side (for the drop preview). */
function dockColumn(side: 'left' | 'right', width: number): DOMRect | null {
  const body = rectOf('.uncoder-ui-body');
  if (!body) return null;
  const app = appEl();
  const gutter = app ? parseFloat(getComputedStyle(app).getPropertyValue('--uncoder-ui-gutter')) || 0 : 0;
  const docked = app?.classList.contains('is-docked');
  const build = rectOf('.uncoder-ui-panel');
  const left = side === 'left' ? (build ? build.right + (docked ? 0 : gutter) : body.left) : body.right - width;
  return new DOMRect(left, body.top, width, body.height);
}

/** Which side a pointer at x would dock to, if any. */
function zoneAt(x: number): 'left' | 'right' | null {
  const build = rectOf('.uncoder-ui-panel');
  if (x <= (build ? build.right : 0) + SNAP) return 'left';
  if (x >= window.innerWidth - SNAP) return 'right';
  return null;
}

/**
 * Drags the inspector from its grip or header. A docked panel tears off once the pointer has moved a few
 * pixels; a click without moving floats it in place (docked) and does nothing more (floating).
 */
export function startPanelDrag(e: ReactPointerEvent<HTMLElement>, clickFloats: boolean) {
  if (e.button !== 0) return;
  const panel = (e.currentTarget as HTMLElement).closest<HTMLElement>('.uncoder-ui-inspector');
  if (!panel) return;
  e.preventDefault();
  const start = { x: e.clientX, y: e.clientY };
  const startRect = panel.getBoundingClientRect();
  const off = { x: start.x - startRect.left, y: start.y - startRect.top };
  const before = useUi.getState().inspectorAt;
  let moved = false;
  let pos: InspectorFloat = clampFloat({ x: startRect.left, y: startRect.top, h: before === 'float' ? startRect.height : startRect.height - 48 }, startRect.width);
  let zone: 'left' | 'right' | null = null;
  let ghost: HTMLDivElement | null = null;

  const showGhost = (side: 'left' | 'right' | null) => {
    if (!side) {
      ghost?.classList.remove('is-on');
      return;
    }
    const col = dockColumn(side, startRect.width);
    if (!col) return;
    ghost ??= appEl()?.appendChild(Object.assign(document.createElement('div'), { className: 'uncoder-ui-dockghost' })) ?? null;
    if (!ghost) return;
    Object.assign(ghost.style, { left: `${col.left}px`, top: `${col.top}px`, width: `${col.width}px`, height: `${col.height}px` });
    ghost.dataset.side = side;
    ghost.classList.add('is-on');
  };

  const onMove = (ev: PointerEvent) => {
    if (!moved) {
      if (Math.hypot(ev.clientX - start.x, ev.clientY - start.y) < 4) return;
      moved = true;
      document.documentElement.classList.add('uncoder-ui-moving-panel');
      if (useUi.getState().inspectorAt !== 'float') placeInspector('float', pos);
    }
    pos = clampFloat({ x: ev.clientX - off.x, y: ev.clientY - off.y, h: pos.h }, startRect.width);
    // Follows the pointer directly; the store is updated once, on release.
    const live = document.querySelector<HTMLElement>('.uncoder-ui-inspector');
    if (live) Object.assign(live.style, { left: `${pos.x}px`, top: `${pos.y}px` });
    live?.classList.add('is-moving');
    zone = zoneAt(ev.clientX);
    showGhost(zone);
  };

  const finish = (cancel: boolean) => {
    window.removeEventListener('pointermove', onMove);
    window.removeEventListener('pointerup', onUp);
    window.removeEventListener('pointercancel', onCancel);
    window.removeEventListener('keydown', onKey, true);
    document.documentElement.classList.remove('uncoder-ui-moving-panel');
    document.querySelector('.uncoder-ui-inspector')?.classList.remove('is-moving');
    ghost?.remove();
    if (!moved) {
      if (!cancel && clickFloats && before !== 'float') placeInspector('float', lifted(panel));
      return;
    }
    if (cancel) {
      const back = clampFloat({ x: startRect.left, y: startRect.top, h: startRect.height }, startRect.width);
      // The drag moved the panel directly; React would not rewrite a position it thinks is unchanged.
      if (before === 'float') Object.assign(panel.style, { left: `${back.x}px`, top: `${back.y}px` });
      placeInspector(before, before === 'float' ? back : undefined);
    } else if (zone) placeInspector(zone);
    else placeInspector('float', pos);
  };
  const onUp = () => finish(false);
  const onCancel = () => finish(true);
  const onKey = (ev: KeyboardEvent) => {
    if (ev.key === 'Escape') {
      ev.preventDefault();
      ev.stopPropagation();
      finish(true);
    }
  };
  window.addEventListener('pointermove', onMove);
  window.addEventListener('pointerup', onUp);
  window.addEventListener('pointercancel', onCancel);
  window.addEventListener('keydown', onKey, true);
}

/** The grip: click floats the panel, drag moves it; arrow keys move a floating panel (Shift: farther). */
export function InspectorGrip() {
  const at = useUi((s) => s.inspectorAt);
  const floating = at === 'float';
  const onKeyDown = (e: ReactKeyboardEvent<HTMLButtonElement>) => {
    const step = e.shiftKey ? 64 : 16;
    const d = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
    if (!d || !floating) return;
    e.preventDefault();
    const panel = e.currentTarget.closest<HTMLElement>('.uncoder-ui-inspector');
    const r = panel?.getBoundingClientRect();
    if (!r) return;
    placeInspector('float', clampFloat({ x: r.left + d[0], y: r.top + d[1], h: r.height }, r.width));
  };
  return (
    <button
      type="button"
      className="uncoder-ui-insp__grip"
      aria-label={floating ? 'Move the settings panel (arrow keys)' : 'Float the settings panel'}
      data-tip={floating ? 'Drag to move · drop on an edge to dock' : 'Click to float · drag to move anywhere'}
      onPointerDown={(e) => startPanelDrag(e, true)}
      onClick={(e) => {
        // Keyboard activation (pointer clicks are handled on pointer down/up).
        if (e.detail === 0 && !floating) {
          const panel = e.currentTarget.closest<HTMLElement>('.uncoder-ui-inspector');
          if (panel) placeInspector('float', lifted(panel));
        }
      }}
      onKeyDown={onKeyDown}
    >
      <Icon name="grip-vertical" size={14} />
    </button>
  );
}

/** Dock left / Dock right / Float. */
export function InspectorDockMenu() {
  const at = useUi((s) => s.inspectorAt);
  const menu = usePopover();
  const float = () => {
    const panel = menu.anchorRef.current?.closest<HTMLElement>('.uncoder-ui-inspector');
    const saved = useUi.getState().inspectorFloat;
    placeInspector('float', saved ? clampFloat(saved, panel?.offsetWidth ?? 300) : panel ? lifted(panel) : undefined);
  };
  return (
    <>
      <IconButton ref={menu.anchorRef} icon={AT_ICON[at]} label={`Panel position: ${AT_LABEL[at]}`} size={15} onClick={menu.toggle} aria-haspopup="menu" aria-expanded={menu.open} />
      <Menu
        anchor={menu.anchorRef}
        open={menu.open}
        onClose={menu.close}
        placement="bottom-end"
        width={200}
        items={[
          { label: 'Dock left', icon: 'panel-left', checked: at === 'left', onSelect: () => placeInspector('left') },
          { label: 'Dock right', icon: 'panel-right', checked: at === 'right', onSelect: () => placeInspector('right') },
          { label: 'Float', icon: 'picture-in-picture-2', checked: at === 'float', onSelect: float },
        ]}
      />
    </>
  );
}

/** Double-clicking the header of a floating panel docks it back on its last side. */
export function onHeadDoubleClick(e: React.MouseEvent<HTMLElement>) {
  const s = useUi.getState();
  if (s.inspectorAt !== 'float' || (e.target as Element).closest('button, input, select, a')) return;
  placeInspector(s.inspectorSide);
}

/** Pointer down on the header of a floating panel (outside its buttons) moves it too. */
export function onHeadPointerDown(e: ReactPointerEvent<HTMLElement>) {
  if (useUi.getState().inspectorAt !== 'float' || (e.target as Element).closest('button, input, select, a, [role="tab"]')) return;
  startPanelDrag(e, false);
}
