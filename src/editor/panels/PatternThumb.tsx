// Schematic thumbnail of a section pattern, drawn from the compact layout "shape" the server sends
// with the pattern list (see Patterns::shape()). Containers split space (row / column / grid);
// widgets become bars, pills and blocks. Cheap, deterministic, no network.
import { memo, type ReactElement } from 'react';

export interface PatternShape {
  /** "c" container, or a widget kind: h heading, e small text, x text, b button, i image, o icon, k card, l list, r row of items, g logo. */
  t: string;
  /** Container direction: r row, c column, g grid. */
  d?: 'r' | 'c' | 'g';
  /** Grid columns. */
  n?: number;
  /** Container width in % (inside a row). */
  w?: number;
  bg?: 'dark' | 'brand' | 'tint';
  al?: 'c';
  rv?: number;
  k?: PatternShape[];
  /** Heading level. */
  lv?: number;
  /** Button variant: o = outline/link. */
  v?: 'o';
  /** Widget type (cards, lists, rows). */
  y?: string;
}

const VW = 240;
const VH = 150;
const GAP = 5;

interface Ctx {
  center: boolean;
  dark: boolean;
}

function widgetHeight(s: PatternShape, w: number): number {
  switch (s.t) {
    case 'h':
      return s.lv === 1 ? 17 : s.lv === 2 ? 12 : 5;
    case 'e':
      return 3;
    case 'x':
      return 8;
    case 'b':
      return 7;
    case 'i':
      return Math.min(w * 0.62, 110);
    case 'o':
      return 8;
    case 'g':
      return 6;
    case 'r':
      return s.y === 'logo-grid' ? 8 : s.y === 'nav-menu' ? 4 : 6;
    case 'k':
      if (s.y === 'counter') return 12;
      if (s.y === 'price-table') return Math.min(w * 1.25, 96);
      if (s.y === 'team-member') return Math.min(w * 1.35, 90);
      if (s.y === 'image-box') return Math.min(w * 0.95, 84);
      if (s.y === 'testimonial') return 28;
      return 26;
    case 'l':
      if (s.y === 'accordion') return 43;
      if (s.y === 'steps') return 20;
      if (s.y === 'timeline') return 46;
      if (s.y === 'posts') return Math.min(w * 0.3, 64);
      if (s.y === 'form') return 44;
      return 30;
    default:
      return 6;
  }
}

function pad(s: PatternShape, depth: number): { x: number; y: number } {
  if (depth === 0) return { x: 14, y: 12 };
  return s.bg ? { x: 7, y: 7 } : { x: 0, y: 0 };
}

/** Widths of the children of a row: explicit % widths, the rest shared equally. */
function rowWidths(kids: PatternShape[], inner: number): number[] {
  const total = inner - GAP * (kids.length - 1);
  const set = kids.map((k) => (k.t === 'c' && k.w ? k.w : 0));
  const used = set.reduce((a, b) => a + b, 0);
  const free = set.filter((v) => !v).length;
  const share = free ? Math.max(12, (100 - used) / free) : 0;
  const weights = set.map((v) => v || share);
  const sum = weights.reduce((a, b) => a + b, 0) || 1;
  return weights.map((v) => (total * v) / sum);
}

function measure(s: PatternShape, w: number, depth: number): number {
  if (s.t !== 'c') return widgetHeight(s, w);
  const p = pad(s, depth);
  const inner = w - p.x * 2;
  const kids = s.k ?? [];
  if (!kids.length) return 10 + p.y * 2;
  let h = 0;
  if (s.d === 'r') {
    if (kids.every((k) => k.t === 'b')) h = 7;
    else {
      const ws = rowWidths(kids, inner);
      h = Math.max(...kids.map((k, i) => measure(k, ws[i], depth + 1)));
    }
  } else if (s.d === 'g') {
    const cols = Math.max(1, Math.min(s.n ?? 3, kids.length));
    const cw = (inner - GAP * (cols - 1)) / cols;
    for (let r = 0; r < kids.length; r += cols) {
      h += Math.max(...kids.slice(r, r + cols).map((k) => measure(k, cw, depth + 1))) + (r ? GAP : 0);
    }
  } else {
    h = kids.reduce((a, k) => a + measure(k, inner, depth + 1), 0) + GAP * (kids.length - 1);
  }
  return h + p.y * 2;
}

function bar(out: ReactElement[], cls: string, x: number, y: number, w: number, h: number, width: number, ctx: Ctx, r = h / 2): void {
  const bx = ctx.center ? x + (width - w) / 2 : x;
  out.push(<rect key={out.length} className={cls} x={bx} y={y} width={Math.max(2, w)} height={h} rx={r} />);
}

function drawWidget(s: PatternShape, x: number, y: number, w: number, h: number, ctx: Ctx, out: ReactElement[]): void {
  const center = ctx.center || s.al === 'c';
  const c: Ctx = { ...ctx, center };
  const tx = ctx.dark ? ' is-dark' : '';
  const lim = Math.min(w, 190);
  switch (s.t) {
    case 'h':
      if (s.lv === 1) {
        bar(out, 'uncoder-ui-pthumb__h' + tx, x, y, lim * 0.9, 7, w, c, 2);
        bar(out, 'uncoder-ui-pthumb__h' + tx, x, y + 10, lim * 0.62, 7, w, c, 2);
      } else if (s.lv === 2) {
        bar(out, 'uncoder-ui-pthumb__h' + tx, x, y, lim * 0.72, 5, w, c, 2);
        bar(out, 'uncoder-ui-pthumb__h' + tx, x, y + 7, lim * 0.46, 5, w, c, 2);
      } else bar(out, 'uncoder-ui-pthumb__h' + tx, x, y, lim * 0.55, 4, w, c, 1.5);
      return;
    case 'e':
      bar(out, 'uncoder-ui-pthumb__e' + tx, x, y, Math.min(w * 0.3, 34), 2.5, w, c);
      return;
    case 'x':
      bar(out, 'uncoder-ui-pthumb__x' + tx, x, y, w * 0.94, 2.2, w, c);
      bar(out, 'uncoder-ui-pthumb__x' + tx, x, y + 5, w * 0.68, 2.2, w, c);
      return;
    case 'b':
      bar(out, 'uncoder-ui-pthumb__b' + (s.v === 'o' ? ' is-outline' : '') + tx, x, y, Math.min(30, w), 7, w, c);
      return;
    case 'i':
      out.push(<rect key={out.length} className="uncoder-ui-pthumb__i" x={x} y={y} width={w} height={h} rx={4} />);
      return;
    case 'o':
      bar(out, 'uncoder-ui-pthumb__o' + tx, x, y, 8, 8, w, c, 2);
      return;
    case 'g':
      bar(out, 'uncoder-ui-pthumb__h' + tx, x, y + 1, Math.min(26, w), 4, w, c, 1.5);
      return;
    case 'r': {
      const n = s.y === 'logo-grid' ? 6 : s.y === 'nav-menu' ? 4 : 3;
      const item = s.y === 'logo-grid' ? Math.min((w - 5 * 6) / 6, 22) : s.y === 'social-icons' ? 6 : Math.min(14, w / 6);
      const total = n * item + (n - 1) * 6;
      let ix = center || s.y !== 'social-icons' ? x + (w - total) / 2 : x;
      for (let i = 0; i < n; i++) {
        out.push(<rect key={out.length} className={(s.y === 'logo-grid' ? 'uncoder-ui-pthumb__i' : 'uncoder-ui-pthumb__x') + tx} x={ix} y={y + (h - (s.y === 'logo-grid' ? 6 : 3)) / 2} width={item} height={s.y === 'logo-grid' ? 6 : s.y === 'social-icons' ? 6 : 3} rx={s.y === 'social-icons' ? 3 : 1.5} />);
        ix += item + 6;
      }
      return;
    }
    case 'k': {
      if (s.y === 'counter') {
        bar(out, 'uncoder-ui-pthumb__h' + tx, x, y + 1, Math.min(26, w * 0.55), 6, w, c, 2);
        bar(out, 'uncoder-ui-pthumb__x' + tx, x, y + 9, Math.min(34, w * 0.7), 2, w, c);
        return;
      }
      out.push(<rect key={out.length} className={'uncoder-ui-pthumb__k' + tx} x={x} y={y} width={w} height={h} rx={4} />);
      if (s.y === 'image-box' || s.y === 'team-member') {
        out.push(<rect key={out.length} className="uncoder-ui-pthumb__i" x={x + 3} y={y + 3} width={w - 6} height={h * 0.62} rx={3} />);
        bar(out, 'uncoder-ui-pthumb__h' + tx, x + 5, y + h * 0.62 + 7, w * 0.5, 3, w - 10, { ...c, center: s.y === 'team-member' }, 1.5);
        return;
      }
      const inner = w - 10;
      let iy = y + 5;
      if (s.y === 'icon-box') {
        out.push(<rect key={out.length} className="uncoder-ui-pthumb__o" x={x + 5} y={iy} width={6} height={6} rx={1.5} />);
        iy += 9;
      }
      if (s.y === 'price-table') {
        bar(out, 'uncoder-ui-pthumb__h' + tx, x + 5, iy, inner * 0.4, 3, inner, c, 1.5);
        bar(out, 'uncoder-ui-pthumb__h' + tx, x + 5, iy + 7, inner * 0.35, 7, inner, c, 2);
        iy += 20;
        for (let i = 0; i < 5 && iy < y + h - 16; i++, iy += 6) bar(out, 'uncoder-ui-pthumb__x' + tx, x + 5, iy, inner * 0.7, 2, inner, c);
        bar(out, 'uncoder-ui-pthumb__b' + tx, x + 5, y + h - 11, inner, 6, inner, c);
        return;
      }
      bar(out, 'uncoder-ui-pthumb__h' + tx, x + 5, iy, inner * 0.5, 3, inner, c, 1.5);
      bar(out, 'uncoder-ui-pthumb__x' + tx, x + 5, iy + 6, inner * 0.9, 2, inner, c);
      bar(out, 'uncoder-ui-pthumb__x' + tx, x + 5, iy + 10, inner * 0.6, 2, inner, c);
      return;
    }
    case 'l': {
      if (s.y === 'accordion') {
        for (let i = 0; i < 5; i++) {
          out.push(<rect key={out.length} className={'uncoder-ui-pthumb__k' + tx} x={x} y={y + i * 9} width={w} height={7} rx={2} />);
          bar(out, 'uncoder-ui-pthumb__h' + tx, x + 4, y + i * 9 + 2.5, w * 0.45, 2, w - 8, ctx, 1);
        }
        return;
      }
      if (s.y === 'steps') {
        for (let i = 0; i < 4; i++) {
          const cx = x + (w / 4) * i + w / 8;
          out.push(<circle key={out.length} className="uncoder-ui-pthumb__b" cx={cx} cy={y + 4} r={4} />);
          out.push(<rect key={out.length} className={'uncoder-ui-pthumb__h' + tx} x={cx - 9} y={y + 11} width={18} height={3} rx={1.5} />);
          out.push(<rect key={out.length} className={'uncoder-ui-pthumb__x' + tx} x={cx - 12} y={y + 16} width={24} height={2} rx={1} />);
        }
        return;
      }
      if (s.y === 'timeline') {
        out.push(<rect key={out.length} className={'uncoder-ui-pthumb__x' + tx} x={x + w / 2 - 0.5} y={y} width={1} height={h} />);
        for (let i = 0; i < 4; i++) {
          const left = i % 2 === 1;
          out.push(<rect key={out.length} className="uncoder-ui-pthumb__k is-tint" x={left ? x + w * 0.06 : x + w / 2 + 6} y={y + i * 11.5} width={w * 0.38} height={9} rx={2} />);
          out.push(<circle key={out.length} className="uncoder-ui-pthumb__b" cx={x + w / 2} cy={y + i * 11.5 + 4.5} r={2} />);
        }
        return;
      }
      if (s.y === 'posts') {
        const cw = (w - 2 * GAP) / 3;
        for (let i = 0; i < 3; i++) {
          const px = x + i * (cw + GAP);
          out.push(<rect key={out.length} className={'uncoder-ui-pthumb__k' + tx} x={px} y={y} width={cw} height={h} rx={3} />);
          out.push(<rect key={out.length} className="uncoder-ui-pthumb__i" x={px} y={y} width={cw} height={h * 0.55} rx={3} />);
          out.push(<rect key={out.length} className={'uncoder-ui-pthumb__h' + tx} x={px + 3} y={y + h * 0.55 + 4} width={cw * 0.7} height={3} rx={1.5} />);
        }
        return;
      }
      // Form and anything else: field rows and a button.
      let iy = y;
      for (let i = 0; i < 3; i++, iy += 11) out.push(<rect key={out.length} className={'uncoder-ui-pthumb__k' + tx} x={x} y={iy} width={w} height={i === 2 ? 14 : 7} rx={2} />);
      bar(out, 'uncoder-ui-pthumb__b', x, y + h - 7, 26, 7, w, { ...ctx, center: false });
      return;
    }
    default:
      bar(out, 'uncoder-ui-pthumb__x' + tx, x, y, w * 0.8, 2.2, w, c);
  }
}

function draw(s: PatternShape, x: number, y: number, w: number, depth: number, ctx: Ctx, out: ReactElement[]): number {
  const h = measure(s, w, depth);
  if (s.t !== 'c') {
    drawWidget(s, x, y, w, h, ctx, out);
    return h;
  }
  const dark = s.bg === 'dark' || s.bg === 'brand' ? true : s.bg === 'tint' ? false : ctx.dark;
  if (s.bg) out.push(<rect key={out.length} className={'uncoder-ui-pthumb__bg is-' + s.bg} x={x} y={y} width={w} height={h} rx={depth ? 4 : 0} />);
  const c: Ctx = { dark, center: s.al === 'c' || (ctx.center && s.d !== 'r') };
  const p = pad(s, depth);
  const ix = x + p.x;
  const inner = w - p.x * 2;
  let iy = y + p.y;
  const kids = s.rv ? [...(s.k ?? [])].reverse() : s.k ?? [];
  if (s.d === 'r') {
    if (kids.length && kids.every((k) => k.t === 'b')) {
      const total = kids.length * 30 + (kids.length - 1) * 4;
      let bx = c.center ? ix + (inner - total) / 2 : ix;
      for (const k of kids) {
        drawWidget(k, bx, iy, 30, 7, { ...c, center: false }, out);
        bx += 34;
      }
      return h;
    }
    const ws = rowWidths(kids, inner);
    const rowH = h - p.y * 2;
    let cx = ix;
    kids.forEach((k, i) => {
      const kh = measure(k, ws[i], depth + 1);
      draw(k, cx, iy + (rowH - kh) / 2, ws[i], depth + 1, { ...c, center: false }, out);
      cx += ws[i] + GAP;
    });
  } else if (s.d === 'g') {
    const cols = Math.max(1, Math.min(s.n ?? 3, kids.length));
    const cw = (inner - GAP * (cols - 1)) / cols;
    for (let r = 0; r < kids.length; r += cols) {
      const row = kids.slice(r, r + cols);
      const rh = Math.max(...row.map((k) => measure(k, cw, depth + 1)));
      row.forEach((k, i) => draw(k, ix + i * (cw + GAP), iy, cw, depth + 1, c, out));
      iy += rh + GAP;
    }
  } else {
    for (const k of kids) iy += draw(k, ix, iy, inner, depth + 1, c, out) + GAP;
  }
  return h;
}

export const PatternThumb = memo(function PatternThumb({ shape }: { shape: PatternShape[] }) {
  const out: ReactElement[] = [];
  let y = 0;
  for (const s of shape) y += draw(s, 0, y, VW, 0, { center: false, dark: false }, out);
  const vh = Math.max(y, VH);
  const offset = y < VH ? (VH - y) / 2 : 0;
  return (
    <svg className="uncoder-ui-pthumb" viewBox={`0 0 ${VW} ${VH}`} preserveAspectRatio="xMidYMin slice" aria-hidden="true" focusable="false">
      <rect className="uncoder-ui-pthumb__page" x={0} y={0} width={VW} height={vh} />
      <g transform={offset ? `translate(0 ${offset})` : undefined}>{out}</g>
    </svg>
  );
});
