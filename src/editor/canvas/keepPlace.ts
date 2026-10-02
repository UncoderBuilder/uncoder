// Keeps your place when the canvas changes width (device, custom width, zoom): the page reflows at the new
// width, so a plain scroll offset would land somewhere else. The selected element is brought to the middle
// of the canvas; with nothing selected, the element at the top of the canvas stays at the top.
import { useUi } from '../store/ui';
import { elementFor, frame, invalidateGeometry } from './frame';

type Anchor = { id: string; offset: number; selected: boolean };

/** How long the frame keeps resizing after a switch (its width transition) plus late reflow. */
const SETTLE_MS = 700;

function captureAnchor(): Anchor | null {
  const win = frame.win;
  const doc = frame.doc;
  if (!win || !doc) return null;
  const selected = useUi.getState().selected[0];
  const sel = selected ? elementFor(selected) : null;
  if (sel && sel.getBoundingClientRect().height > 0) return { id: selected!, offset: sel.getBoundingClientRect().top, selected: true };
  // The first element at the top of the visible canvas.
  const probe = doc.elementFromPoint(win.innerWidth / 2, 8) as HTMLElement | null;
  const el = probe?.closest<HTMLElement>('[data-id]');
  return el ? { id: el.dataset.id!, offset: el.getBoundingClientRect().top, selected: false } : null;
}

function restore(anchor: Anchor): void {
  const win = frame.win;
  const el = elementFor(anchor.id);
  if (!win || !el) return;
  const r = el.getBoundingClientRect();
  if (!r.height && !r.width) return; // Hidden at this width: leave the scroll where it is.
  let top: number;
  if (anchor.selected) {
    // Centre it; a tall element starts a little below the top edge instead.
    const room = win.innerHeight;
    top = win.scrollY + r.top - (r.height < room - 120 ? (room - r.height) / 2 : 60);
  } else {
    top = win.scrollY + r.top - anchor.offset;
  }
  win.scrollTo({ top: Math.max(0, top), behavior: 'instant' as ScrollBehavior });
  invalidateGeometry();
}

/** Starts watching the canvas width; returns a function that stops it. */
export function keepPlaceOnResize(): () => void {
  let timers: number[] = [];
  let onResize: (() => void) | null = null;
  const stop = () => {
    timers.forEach(clearTimeout);
    timers = [];
    if (onResize) frame.win?.removeEventListener('resize', onResize);
    onResize = null;
  };
  const unsubscribe = useUi.subscribe((s, prev) => {
    if (s.device === prev.device && s.customWidth === prev.customWidth && s.zoom === prev.zoom) return;
    // The store changed but React has not re-rendered the frame yet: measure the old layout now.
    const anchor = captureAnchor();
    stop();
    if (!anchor) return;
    let raf = 0;
    onResize = () => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => restore(anchor));
    };
    frame.win?.addEventListener('resize', onResize);
    // Zoom does not resize the iframe's window; late images and fonts can still move things.
    for (const ms of [60, 260, SETTLE_MS]) timers.push(window.setTimeout(() => restore(anchor), ms));
    timers.push(window.setTimeout(stop, SETTLE_MS + 20));
  });
  return () => {
    stop();
    unsubscribe();
  };
}
