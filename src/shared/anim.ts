// Timeline animations (Behaviour → Animations). One format shared by the editor, the front-end engine
// (frontend/modules/animate.ts) and PHP (Core\Animations sanitizes it; the editor's ranges match its PROPS):
//
//   { trigger, target, from: {state}, steps: [{ to: {state}, duration, ease, delay }], stagger, repeat, … }
//
// States are relative to the element's own styles: x / y / rotate / skew add to its transform, scale
// multiplies it, opacity 1 and blur 0 mean "as styled". Each step changes some properties on top of the
// previous state. compile() bakes the steps, their holds and the stagger of every target into one
// keyframe list per target, all sharing one duration, so playing, reversing, looping and scroll scrubbing
// each drive plain Web Animations with no timing code of our own.

export type AnimTrigger = 'load' | 'enter' | 'scroll' | 'hover' | 'click' | 'loop';
export type AnimTarget = 'self' | 'children' | 'words' | 'chars' | 'lines';
export type AnimOrder = 'start' | 'end' | 'center' | 'edges' | 'random';
export type AnimClip = 'none' | 'wipe-up' | 'wipe-down' | 'wipe-left' | 'wipe-right' | 'center-x' | 'center-y' | 'circle';

export interface AnimState {
  opacity?: number;
  x?: number | string;
  y?: number | string;
  scale?: number;
  scaleX?: number;
  scaleY?: number;
  rotate?: number;
  rotateX?: number;
  rotateY?: number;
  skewX?: number;
  skewY?: number;
  blur?: number;
  clip?: AnimClip;
}
export type AnimProp = keyof AnimState;

export interface AnimStep {
  to: AnimState;
  duration: number;
  ease?: string;
  /** Hold before this step starts (ms). */
  delay?: number;
}

export interface AnimDef {
  /** Preset the definition started from (a label for the editor; the steps are what plays). */
  preset?: string;
  trigger: AnimTrigger;
  target?: AnimTarget;
  /** Split text / child items slide out of a clipping box of their own. */
  mask?: boolean;
  from?: AnimState;
  steps: AnimStep[];
  /** Before the whole timeline (ms); not used when scrubbing. */
  delay?: number;
  /** Between targets (ms). */
  stagger?: number;
  order?: AnimOrder;
  /** Extra plays; -1 = forever. */
  repeat?: number;
  yoyo?: boolean;
  /** Enter: play once, every time it comes into view, or play backwards when it leaves. */
  replay?: 'once' | 'every' | 'reverse';
  /** Enter: distance from the bottom of the viewport where it starts (% of the viewport height). */
  offset?: number;
  /** Scroll: range of the element's trip through the viewport (0 = its top meets the bottom edge, 100 = its bottom passes the top edge). */
  start?: number;
  end?: number;
  /** Scroll: catch-up time (0 = locked to the scrollbar, 1 = about a second). */
  smooth?: number;
  /** Click: a second click plays it backwards. */
  toggle?: boolean;
  devices?: string[];
}

/** Each property's value when the element is as styled. */
export const NATURAL: Record<AnimProp, number | string> = {
  opacity: 1,
  x: 0,
  y: 0,
  scale: 1,
  scaleX: 1,
  scaleY: 1,
  rotate: 0,
  rotateX: 0,
  rotateY: 0,
  skewX: 0,
  skewY: 0,
  blur: 0,
  clip: 'none',
};

const INSET: Record<AnimClip, string> = {
  none: 'inset(0% 0% 0% 0%)',
  'wipe-up': 'inset(100% 0% 0% 0%)',
  'wipe-down': 'inset(0% 0% 100% 0%)',
  'wipe-left': 'inset(0% 0% 0% 100%)',
  'wipe-right': 'inset(0% 100% 0% 0%)',
  'center-x': 'inset(0% 50% 0% 50%)',
  'center-y': 'inset(50% 0% 50% 0%)',
  circle: 'inset(50% 50% 50% 50%)',
};

// ------------------------------------------------------------------ Easing

type EaseFn = (t: number) => number;

const bounceOut: EaseFn = (t) => {
  const n = 7.5625;
  const d = 2.75;
  if (t < 1 / d) return n * t * t;
  if (t < 2 / d) return n * (t -= 1.5 / d) * t + 0.75;
  if (t < 2.5 / d) return n * (t -= 2.25 / d) * t + 0.9375;
  return n * (t -= 2.625 / d) * t + 0.984375;
};
const elasticOut: EaseFn = (t) => (t <= 0 || t >= 1 ? t : 2 ** (-10 * t) * Math.sin(((t - 0.075) * 2 * Math.PI) / 0.3) + 1);

/** Each family's "in" curve; out and in-out mirror it. Names follow GSAP (power1 = quad … power4 = quint). */
const FAMILY_IN: Record<string, EaseFn> = {
  power1: (t) => t ** 2,
  power2: (t) => t ** 3,
  power3: (t) => t ** 4,
  power4: (t) => t ** 5,
  sine: (t) => 1 - Math.cos((t * Math.PI) / 2),
  expo: (t) => (t <= 0 ? 0 : 2 ** (10 * t - 10)),
  circ: (t) => 1 - Math.sqrt(1 - t * t),
  back: (t) => 2.70158 * t ** 3 - 1.70158 * t ** 2,
  elastic: (t) => 1 - elasticOut(1 - t),
  bounce: (t) => 1 - bounceOut(1 - t),
};

// Cubic-bezier twins for in / out / in-out. Elastic and bounce are sampled into linear() where the
// browser supports it; these are their fallbacks.
const BEZIER: Record<string, [string, string, string]> = {
  power1: ['.11,0,.5,0', '.5,1,.89,1', '.45,0,.55,1'],
  power2: ['.32,0,.67,0', '.33,1,.68,1', '.65,0,.35,1'],
  power3: ['.5,0,.75,0', '.25,1,.5,1', '.76,0,.24,1'],
  power4: ['.64,0,.78,0', '.22,1,.36,1', '.83,0,.17,1'],
  sine: ['.12,0,.39,0', '.61,1,.88,1', '.37,0,.63,1'],
  expo: ['.7,0,.84,0', '.16,1,.3,1', '.87,0,.13,1'],
  circ: ['.55,0,1,.45', '0,.55,.45,1', '.85,0,.15,1'],
  back: ['.36,0,.66,-.56', '.34,1.56,.64,1', '.68,-.6,.32,1.6'],
  elastic: ['.36,0,.66,-.56', '.34,1.56,.64,1', '.68,-.6,.32,1.6'],
  bounce: ['.32,0,.67,0', '.33,1,.68,1', '.65,0,.35,1'],
};

export const EASE_FAMILIES = ['none', 'power1', 'power2', 'power3', 'power4', 'sine', 'expo', 'circ', 'back', 'elastic', 'bounce'];
export const EASE_DIRS = ['in', 'out', 'inOut'] as const;
export const DEFAULT_EASE = 'power3.out';

const BEZIER_RE = /^cubic-bezier\(\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*\)$/;

function bezierFn(x1: number, y1: number, x2: number, y2: number): EaseFn {
  const at = (a: number, b: number, t: number) => 3 * a * t * (1 - t) ** 2 + 3 * b * t * t * (1 - t) + t ** 3;
  return (x) => {
    let lo = 0;
    let hi = 1;
    for (let i = 0; i < 24; i++) {
      const mid = (lo + hi) / 2;
      if (at(x1, x2, mid) < x) lo = mid;
      else hi = mid;
    }
    return at(y1, y2, (lo + hi) / 2);
  };
}

/** The easing curve as a function of 0 → 1 (for drawing curves and scrubbing). */
export function easeFn(name = DEFAULT_EASE): EaseFn {
  const m = BEZIER_RE.exec(name);
  if (m) return bezierFn(+m[1], +m[2], +m[3], +m[4]);
  const [family, dir = 'out'] = name.split('.');
  const easeIn = FAMILY_IN[family];
  if (!easeIn) return (t) => t;
  if (dir === 'in') return easeIn;
  if (dir === 'inOut') return (t) => (t < 0.5 ? easeIn(t * 2) / 2 : 1 - easeIn((1 - t) * 2) / 2);
  return (t) => 1 - easeIn(1 - t);
}

export function isEase(name: unknown): name is string {
  if (typeof name !== 'string') return false;
  if (name === 'none' || BEZIER_RE.test(name)) return true;
  const [family, dir = 'out'] = name.split('.');
  return family in FAMILY_IN && (EASE_DIRS as readonly string[]).includes(dir);
}

const round = (v: number, d = 4) => Math.round(v * 10 ** d) / 10 ** d;

/** CSS timing function for an ease name. */
export function easeCss(name = DEFAULT_EASE, linear = true): string {
  if (name === 'none' || name === 'linear') return 'linear';
  if (BEZIER_RE.test(name)) return name;
  const [family, dir = 'out'] = name.split('.');
  const bezier = BEZIER[family];
  if (!bezier) return `cubic-bezier(${BEZIER.power3[1]})`;
  if (linear && (family === 'elastic' || family === 'bounce')) {
    const f = easeFn(name);
    const n = 64;
    return `linear(${Array.from({ length: n + 1 }, (_, i) => round(f(i / n))).join(',')})`;
  }
  return `cubic-bezier(${bezier[Math.max(0, EASE_DIRS.indexOf(dir as (typeof EASE_DIRS)[number]))]})`;
}

// ------------------------------------------------------------------ Compile

export interface CompileOptions {
  /** The browser understands linear() easing. */
  linear?: boolean;
  /** Keyframes are composited on top of the element's own styles (composite: "add"). */
  additive?: boolean;
  random?: () => number;
}

export interface Compiled {
  /** One play of the whole group, stagger included (ms). */
  duration: number;
  /** One keyframe list per target. */
  keyframes: Keyframe[][];
  iterations: number;
  direction: PlaybackDirection;
  /** The last frame equals the element's own styles, so the effect can be dropped when done. */
  endsNatural: boolean;
}

const len = (v: number | string | undefined) => (typeof v === 'number' ? `${v}px` : v || '0px');
const num = (v: unknown, fallback: number) => (typeof v === 'number' && Number.isFinite(v) ? v : fallback);

function ranks(n: number, order: AnimOrder | undefined, random: () => number): number[] {
  const idx = Array.from({ length: n }, (_, i) => i);
  const c = (n - 1) / 2;
  switch (order) {
    case 'end':
      return idx.map((i) => n - 1 - i);
    case 'center':
      return idx.map((i) => Math.abs(i - c));
    case 'edges':
      return idx.map((i) => c - Math.abs(i - c));
    case 'random': {
      const shuffled = [...idx].sort(() => random() - 0.5);
      return idx.map((i) => shuffled.indexOf(i));
    }
    default:
      return idx;
  }
}

function toCss(st: AnimState, used: Set<AnimProp>, circle: boolean, additive: boolean): Keyframe {
  const v = <P extends AnimProp>(p: P) => st[p] ?? NATURAL[p];
  const out: Keyframe = {};
  if (used.has('opacity')) out.opacity = String(additive ? round(num(v('opacity'), 1) - 1) : num(v('opacity'), 1));
  const tf: string[] = [];
  if (used.has('rotateX') || used.has('rotateY')) tf.push('perspective(1000px)');
  if (used.has('x') || used.has('y')) tf.push(`translate(${len(v('x'))}, ${len(v('y'))})`);
  if (used.has('rotate')) tf.push(`rotate(${num(v('rotate'), 0)}deg)`);
  if (used.has('rotateX')) tf.push(`rotateX(${num(v('rotateX'), 0)}deg)`);
  if (used.has('rotateY')) tf.push(`rotateY(${num(v('rotateY'), 0)}deg)`);
  if (used.has('skewX') || used.has('skewY')) tf.push(`skew(${num(v('skewX'), 0)}deg, ${num(v('skewY'), 0)}deg)`);
  if (used.has('scale') || used.has('scaleX') || used.has('scaleY')) {
    const s = num(v('scale'), 1);
    tf.push(`scale(${round(s * num(v('scaleX'), 1))}, ${round(s * num(v('scaleY'), 1))})`);
  }
  if (tf.length) out.transform = tf.join(' ');
  if (used.has('blur')) out.filter = `blur(${num(v('blur'), 0)}px)`;
  if (used.has('clip')) {
    const clip = (v('clip') as AnimClip) in INSET ? (v('clip') as AnimClip) : 'none';
    out.clipPath = circle ? (clip === 'none' ? 'circle(75% at 50% 50%)' : 'circle(0% at 50% 50%)') : INSET[clip];
  }
  return out;
}

/** The start state followed by the state after each step. */
export function states(def: Pick<AnimDef, 'from' | 'steps'>): AnimState[] {
  const out: AnimState[] = [{ ...(def.from ?? {}) }];
  for (const step of def.steps ?? []) out.push({ ...out[out.length - 1], ...(step.to ?? {}) });
  return out;
}

export function isNatural(state: AnimState): boolean {
  return (Object.keys(state) as AnimProp[]).every((p) => {
    const v = state[p];
    return v === undefined || v === NATURAL[p] || (typeof v === 'string' && parseFloat(v) === 0 && NATURAL[p] === 0);
  });
}

export function compile(def: AnimDef, count: number, opts: CompileOptions = {}): Compiled {
  const steps = def.steps?.length ? def.steps : [{ to: {}, duration: 600 }];
  const list = states({ from: def.from, steps });
  const used = new Set(list.flatMap((s) => Object.keys(s)).filter((k): k is AnimProp => k in NATURAL));
  const circle = list.some((s) => s.clip === 'circle');
  const css = (st: AnimState) => toCss(st, used, circle, !!opts.additive);

  // One target's segments: state a → state b over ms (a === b is a hold).
  const segments: Array<{ a: number; b: number; ms: number; easing: string }> = [];
  steps.forEach((step, i) => {
    if (num(step.delay, 0) > 0) segments.push({ a: i, b: i, ms: num(step.delay, 0), easing: 'linear' });
    segments.push({ a: i, b: i + 1, ms: Math.max(0, num(step.duration, 0)), easing: easeCss(step.ease, opts.linear) });
  });
  const length = segments.reduce((t, s) => t + s.ms, 0);
  const n = Math.max(1, Math.floor(count));
  const r = ranks(n, def.order, opts.random ?? Math.random);
  const low = Math.min(...r);
  const offsets = r.map((x) => (x - low) * Math.max(0, num(def.stagger, 0)));
  const total = Math.max(1, length + Math.max(...offsets));

  const keyframes = offsets.map((off) => {
    const out: Keyframe[] = [];
    let t = 0;
    const push = (state: AnimState, easing: string) => {
      const kf: Keyframe = { offset: Math.min(1, round(t / total, 6)), easing, ...css(state) };
      const last = out[out.length - 1];
      // A segment's end and the next one's start coincide; keep the start (it carries the next easing).
      if (last && last.offset === kf.offset && JSON.stringify({ ...last, easing: 0 }) === JSON.stringify({ ...kf, easing: 0 })) out[out.length - 1] = kf;
      else out.push(kf);
    };
    if (off > 0) {
      push(list[0], 'linear');
      t = off;
    }
    for (const seg of segments) {
      push(list[seg.a], seg.easing);
      t += seg.ms;
      push(list[seg.b], 'linear');
    }
    if (t < total) {
      t = total;
      push(list[list.length - 1], 'linear');
    }
    if (out.length === 1) out.push({ ...out[0], offset: 1 });
    return out;
  });

  const repeat = num(def.repeat, 0);
  const iterations = def.trigger === 'loop' || repeat < 0 ? Infinity : 1 + Math.max(0, Math.floor(repeat));
  const final = def.yoyo && Number.isFinite(iterations) && iterations % 2 === 0 ? list[0] : list[list.length - 1];
  return { duration: total, keyframes, iterations, direction: def.yoyo ? 'alternate' : 'normal', endsNatural: isNatural(final) };
}
