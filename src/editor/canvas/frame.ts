// Shared references to the canvas iframe (set by <Canvas/>, used by DnD, overlays, inline editing).
export interface Frame {
  iframe: HTMLIFrameElement | null;
  doc: Document | null;
  win: (Window & typeof globalThis & { UncoderWB?: any }) | null;
  mount: HTMLElement | null;
  overlay: HTMLElement | null;
}

export const frame: Frame = { iframe: null, doc: null, win: null, mount: null, overlay: null };

type Listener = () => void;
const listeners = new Set<Listener>();

/** Notifies overlay/geometry consumers that layout may have changed. */
export function invalidateGeometry(): void {
  listeners.forEach((l) => l());
}

export function onGeometry(l: Listener): () => void {
  listeners.add(l);
  return () => listeners.delete(l);
}

/** Converts a point in canvas (iframe viewport) coordinates to parent window coordinates. */
export function toParent(x: number, y: number, zoom: number): { x: number; y: number } {
  const r = frame.iframe?.getBoundingClientRect();
  return { x: (r?.left ?? 0) + x * zoom, y: (r?.top ?? 0) + y * zoom };
}

/** Converts a parent-window point into canvas (iframe viewport) coordinates, or null when outside. */
export function toCanvas(x: number, y: number, zoom: number): { x: number; y: number } | null {
  const r = frame.iframe?.getBoundingClientRect();
  if (!r) return null;
  if (x < r.left || x > r.right || y < r.top || y > r.bottom) return null;
  return { x: (x - r.left) / zoom, y: (y - r.top) / zoom };
}

export function elementFor(id: string): HTMLElement | null {
  return (frame.doc?.querySelector(`[data-id="${CSS.escape(id)}"]`) as HTMLElement) ?? null;
}
