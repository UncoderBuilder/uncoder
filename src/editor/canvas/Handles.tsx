// Direct manipulation on the canvas: drag the selected element's padding, margin, a container's gap and a
// column's width. Values are written for the device being edited; one drag is one undo step.
import { useRef, useState, type ReactElement } from 'react';
import { schemaOf } from '../lib/config';
import { suffix } from '@shared/css';
import { commit, mutateSilently, useDoc } from '../store/doc';
import { useUi } from '../store/ui';
import { elementFor, frame, invalidateGeometry } from './frame';

type Side = 'top' | 'right' | 'bottom' | 'left';
type Kind = { type: 'padding' | 'margin'; side: Side } | { type: 'gap'; axis: 'x' | 'y' } | { type: 'width' };
type Box = { x: number; y: number; w: number; h: number };

const OPPOSITE: Record<Side, Side> = { top: 'bottom', bottom: 'top', left: 'right', right: 'left' };
const SNAP = [25, 33.333, 50, 66.667, 75, 100];
const px = (v: string) => parseFloat(v) || 0;

/** The element that lays out a container's children (the inner box of a boxed container). */
const flexOf = (el: HTMLElement): HTMLElement => (el.querySelector<HTMLElement>(':scope > .uncoder-container__inner') ?? el);
const kids = (el: HTMLElement) => Array.from(flexOf(el).children).filter((c): c is HTMLElement => c instanceof HTMLElement && c.hasAttribute('data-id'));

export function Handles({ id }: { id: string }) {
  const node = useDoc((s) => s.doc.nodes[id]);
  const device = useUi((s) => s.device);
  const [live, setLive] = useState<{ kind: Kind; label: string; x: number; y: number } | null>(null);
  const drag = useRef<{ key: string; before: unknown } | null>(null);
  const el = elementFor(id);
  const win = frame.win;
  if (!node || !el || !win) return null;
  const isContainer = !!schemaOf(node.type)?.container;
  const cs = win.getComputedStyle(el);
  const r = el.getBoundingClientRect();
  if (!r.width || !r.height) return null;
  const sx = win.scrollX;
  const sy = win.scrollY;
  const pad = { top: px(cs.paddingTop), right: px(cs.paddingRight), bottom: px(cs.paddingBottom), left: px(cs.paddingLeft) };
  const mar = { top: px(cs.marginTop), bottom: px(cs.marginBottom) };

  const parentEl = el.parentElement?.closest<HTMLElement>('.uncoder-container');
  const parentFlex = parentEl ? win.getComputedStyle(flexOf(parentEl)) : null;
  const inRow = !!parentFlex && parentFlex.display.includes('flex') && parentFlex.flexDirection.startsWith('row');

  let gapBox: Box | null = null;
  let gapAxis: 'x' | 'y' = 'y';
  if (isContainer) {
    const fl = flexOf(el);
    const fcs = win.getComputedStyle(fl);
    const [a, b] = kids(el);
    if (a && b && fcs.display.includes('flex')) {
      const ra = a.getBoundingClientRect();
      const rb = b.getBoundingClientRect();
      gapAxis = fcs.flexDirection.startsWith('row') ? 'x' : 'y';
      gapBox = gapAxis === 'x' ? { x: (ra.right + rb.left) / 2, y: (Math.max(ra.top, rb.top) + Math.min(ra.bottom, rb.bottom)) / 2, w: 0, h: 0 } : { x: (Math.max(ra.left, rb.left) + Math.min(ra.right, rb.right)) / 2, y: (ra.bottom + rb.top) / 2, w: 0, h: 0 };
    }
  }

  const keyFor = (base: string) => base + suffix(device);

  const start = (e: React.PointerEvent, kind: Kind) => {
    if (e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();
    const target = e.currentTarget as HTMLElement;
    target.setPointerCapture(e.pointerId);
    const x0 = e.clientX;
    const y0 = e.clientY;
    const base = kind.type === 'padding' ? '_padding' : kind.type === 'margin' ? '_margin' : kind.type === 'gap' ? 'gap' : 'width';
    const key = keyFor(base);
    const before = useDoc.getState().doc.nodes[id]?.settings[key];
    drag.current = { key, before };
    // Start from what is on screen (inherited or default values included), in px.
    const startPad = { ...pad };
    const startMar = { top: px(cs.marginTop), right: px(cs.marginRight), bottom: px(cs.marginBottom), left: px(cs.marginLeft) };
    const startGap = isContainer ? px(win.getComputedStyle(flexOf(el))[gapAxis === 'x' ? 'columnGap' : 'rowGap']) : 0;
    const parentW = parentEl ? flexOf(parentEl).getBoundingClientRect().width - px(win.getComputedStyle(flexOf(parentEl)).paddingLeft) - px(win.getComputedStyle(flexOf(parentEl)).paddingRight) : 0;
    const startW = r.width;

    const valueAt = (ev: PointerEvent): { value: unknown; label: string } => {
      const dx = ev.clientX - x0;
      const dy = ev.clientY - y0;
      if (kind.type === 'padding' || kind.type === 'margin') {
        const side = kind.side;
        // Dragging into the box grows padding; dragging away from the box grows margin.
        const inward = side === 'top' ? dy : side === 'bottom' ? -dy : side === 'left' ? dx : -dx;
        const delta = kind.type === 'padding' ? inward : -inward;
        const from = kind.type === 'padding' ? startPad : startMar;
        const next = Math.max(kind.type === 'padding' ? 0 : -500, Math.round(from[side] + delta));
        const out = { top: from.top, right: from.right, bottom: from.bottom, left: from.left } as Record<Side, number>;
        if (ev.shiftKey) (Object.keys(out) as Side[]).forEach((s) => (out[s] = next));
        else {
          out[side] = next;
          if (ev.altKey) out[OPPOSITE[side]] = next;
        }
        const linked = out.top === out.right && out.top === out.bottom && out.top === out.left;
        return { value: { ...out, unit: 'px', linked }, label: `${next}px${ev.shiftKey ? ' all' : ev.altKey ? ' both' : ''}` };
      }
      if (kind.type === 'gap') {
        const next = Math.max(0, Math.round(startGap + (kind.axis === 'x' ? dx : dy)));
        return { value: { size: next, unit: 'px' }, label: `gap ${next}px` };
      }
      let pct = parentW ? ((startW + dx) / parentW) * 100 : 0;
      pct = Math.max(5, Math.min(100, pct));
      // Snap to common fractions unless Alt is held.
      if (!ev.altKey) for (const s of SNAP) if (Math.abs(pct - s) < 1.2) pct = s;
      pct = Math.round(pct * 10) / 10;
      return { value: { size: pct, unit: '%' }, label: `${pct}%` };
    };

    const onMove = (ev: PointerEvent) => {
      const { value, label } = valueAt(ev);
      mutateSilently((d) => {
        const n = d.nodes[id];
        if (n) n.settings[key] = value as any;
      });
      setLive({ kind, label, x: ev.clientX + sx, y: ev.clientY + sy });
      invalidateGeometry();
    };
    const onUp = (ev: PointerEvent) => {
      target.removeEventListener('pointermove', onMove);
      target.removeEventListener('pointerup', onUp);
      target.removeEventListener('pointercancel', onUp);
      setLive(null);
      const moved = Math.hypot(ev.clientX - x0, ev.clientY - y0) > 1;
      const final = useDoc.getState().doc.nodes[id]?.settings[key];
      // Put the original back silently, then commit the final value as one undoable step.
      mutateSilently((d) => {
        const n = d.nodes[id];
        if (!n) return;
        if (before === undefined) delete n.settings[key];
        else n.settings[key] = before as any;
      });
      if (moved && final !== undefined) {
        const label = kind.type === 'width' ? 'Resize column' : kind.type === 'gap' ? 'Change gap' : kind.type === 'padding' ? 'Change padding' : 'Change margin';
        commit(label, (d) => {
          const n = d.nodes[id];
          if (n) n.settings[key] = final as any;
        });
      }
      drag.current = null;
    };
    target.addEventListener('pointermove', onMove);
    target.addEventListener('pointerup', onUp);
    target.addEventListener('pointercancel', onUp);
  };

  const handle = (kind: Kind, x: number, y: number, vertical: boolean, key: string, title: string) => (
    <div
      key={key}
      className={`uncoder-ui-hd uncoder-ui-hd--${kind.type}${vertical ? ' is-v' : ''}${live && JSON.stringify(live.kind) === JSON.stringify(kind) ? ' is-active' : ''}`}
      style={{ translate: `${Math.round(x)}px ${Math.round(y)}px` }}
      title={title}
      onPointerDown={(e) => start(e, kind)}
    />
  );

  const L = r.left + sx;
  const T = r.top + sy;
  const out: ReactElement[] = [];
  // Padding: on the inner edge of each side (drag into the box to grow it).
  // Small elements keep only the handles that fit.
  if (r.height >= 28) {
    out.push(handle({ type: 'padding', side: 'top' }, L + r.width / 2, T + pad.top, false, 'pt', 'Padding top · drag (Shift: all sides, Alt: top and bottom)'));
    out.push(handle({ type: 'padding', side: 'bottom' }, L + r.width / 2, T + r.height - pad.bottom, false, 'pb', 'Padding bottom · drag (Shift: all sides, Alt: top and bottom)'));
  }
  if (r.width >= 48) {
    out.push(handle({ type: 'padding', side: 'left' }, L + pad.left, T + r.height / 2, true, 'pl', 'Padding left · drag (Shift: all sides, Alt: left and right)'));
    out.push(handle({ type: 'padding', side: 'right' }, L + r.width - pad.right, T + r.height / 2, true, 'pr', 'Padding right · drag (Shift: all sides, Alt: left and right)'));
  }
  // Margin: just outside the top and bottom edges, off-centre (the section handle sits at the centre).
  if (r.width >= 120) {
    out.push(handle({ type: 'margin', side: 'top' }, L + r.width * 0.75, T - Math.max(mar.top, 0) - 4, false, 'mt', 'Margin top · drag away from the box'));
    out.push(handle({ type: 'margin', side: 'bottom' }, L + r.width * 0.75, T + r.height + Math.max(mar.bottom, 0) + 4, false, 'mb', 'Margin bottom · drag away from the box'));
  }
  if (gapBox) out.push(handle({ type: 'gap', axis: gapAxis }, gapBox.x + sx, gapBox.y + sy, gapAxis === 'x', 'gap', 'Gap · drag'));
  if (isContainer && inRow) out.push(handle({ type: 'width' }, L + r.width, T + r.height / 2, true, 'w', 'Width · drag (snaps to ¼ ⅓ ½ ⅔ ¾; Alt: no snapping)'));

  return (
    <>
      {out}
      {live && (
        <div className="uncoder-ui-hd__label" style={{ translate: `${Math.round(live.x + 12)}px ${Math.round(live.y - 26)}px` }}>
          {live.label}
        </div>
      )}
    </>
  );
}
