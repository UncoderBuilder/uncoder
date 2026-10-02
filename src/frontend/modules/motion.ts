// Scroll & pointer effects (Common_Controls::motion()). One shared loop drives every element.
// Positions come from the layout (offsetTop chain, cached until something resizes), never from the
// moved box, so an effect cannot feed back into its own progress. Effects write the individual
// translate / rotate / scale properties, which add up with the Transform settings.
interface Motion {
  y?: number;
  x?: number;
  rotate?: number;
  scale?: 'in' | 'out' | 'in-out' | 'grow' | 'shrink';
  amount?: number;
  fade?: Ramp;
  blur?: Ramp;
  mouse?: 'track' | 'tilt';
  strength?: number;
  range?: [number, number];
  devices?: string[];
}
type Ramp = 'in' | 'out' | 'in-out';

interface Item {
  el: HTMLElement;
  m: Motion;
  top: number;
  height: number;
  active: boolean;
  last: string;
  // Pointer effects: target and current values (px for track, deg for tilt), eased every frame.
  target: [number, number];
  current: [number, number];
  cleanup?: () => void;
}

(() => {
  const api = window.UncoderWB;
  const items = new Set<Item>();
  const clamp = (v: number, min: number, max: number) => Math.min(max, Math.max(min, v));
  const round = (v: number, d = 2) => Math.round(v * 10 ** d) / 10 ** d;
  const PROPS = ['translate', 'rotate', 'scale', 'opacity', 'filter', 'transform'] as const;
  // The Transform group's chain (css.ts TRANSFORM_DECL), kept after the tilt so both apply.
  const BASE = 'translate(var(--uncoder-tx,0),var(--uncoder-ty,0)) rotate(var(--uncoder-rot,0deg)) scale(var(--uncoder-sc,1)) skew(var(--uncoder-skx,0deg),var(--uncoder-sky,0deg))';
  const finePointer = () => window.matchMedia?.('(hover: hover) and (pointer: fine)').matches ?? false;

  let frame = 0;
  let listening = false;
  let pointer: [number, number] = [0, 0];

  function device(): string {
    const w = window.innerWidth;
    let hit = 'desktop';
    let best = Infinity;
    for (const bp of (api.config.breakpoints ?? []) as Array<{ id: string; value: number | null; direction?: string }>) {
      if (bp.value && bp.direction !== 'min' && w <= bp.value && bp.value < best) {
        best = bp.value;
        hit = bp.id;
      }
    }
    return hit.startsWith('mobile') ? 'mobile' : hit.startsWith('tablet') ? 'tablet' : 'desktop';
  }

  function measure() {
    const current = device();
    for (const it of items) {
      let top = 0;
      for (let node: HTMLElement | null = it.el; node; node = node.offsetParent as HTMLElement | null) top += node.offsetTop;
      it.top = top;
      it.height = it.el.offsetHeight;
      const active = !it.m.devices || it.m.devices.includes(current);
      if (!active && it.active) reset(it);
      it.active = active;
      it.last = '';
    }
  }

  function reset(it: Item) {
    for (const prop of PROPS) it.el.style.removeProperty(prop);
    it.current = [0, 0];
    it.last = '';
  }

  // Entering = the first 30% of the range (rising from the bottom of the screen), leaving = the last 30%.
  const EDGE = 0.3;
  const entering = (p: number) => Math.min(1, p / EDGE);
  const leaving = (p: number) => Math.min(1, (1 - p) / EDGE);

  /** 0 → 1 while entering ("in"), 1 → 0 while leaving ("out"), fully on in between. */
  function ramp(mode: Ramp, p: number): number {
    return mode === 'in' ? entering(p) : mode === 'out' ? leaving(p) : Math.min(entering(p), leaving(p));
  }

  function apply(it: Item) {
    const { m, el } = it;
    const vh = window.innerHeight;
    // 0 when the element's top reaches the bottom of the viewport, 1 when its bottom passes the top.
    const raw = (window.scrollY + vh - it.top) / (vh + it.height);
    const [a, b] = m.range ?? [0, 100];
    const p = clamp((raw * 100 - a) / (b - a), 0, 1);

    let tx = 0;
    let ty = 0;
    if (m.y) ty += (0.5 - p) * m.y * 40;
    if (m.x) tx += (p - 0.5) * m.x * 40;
    if (m.mouse === 'track') {
      tx += it.current[0];
      ty += it.current[1];
    }
    const out: Partial<Record<(typeof PROPS)[number], string>> = {};
    if (m.y || m.x || m.mouse === 'track') out.translate = `${round(tx)}px ${round(ty)}px`;
    if (m.rotate) out.rotate = `${round((p - 0.5) * m.rotate * 9)}deg`;
    if (m.scale) {
      const k = m.amount ?? 0.2;
      const scale = {
        in: 1 - k * (1 - entering(p)),
        out: 1 - k * (1 - leaving(p)),
        'in-out': 1 - k * (1 - Math.min(entering(p), leaving(p))),
        grow: 1 + k * p,
        shrink: 1 + k * (1 - p),
      }[m.scale];
      out.scale = String(round(scale, 4));
    }
    if (m.fade) out.opacity = String(round(ramp(m.fade, p), 3));
    if (m.blur) out.filter = `blur(${round((1 - ramp(m.blur, p)) * 10)}px)`;
    if (m.mouse === 'tilt') {
      const [rx, ry] = it.current;
      out.transform = rx || ry ? `perspective(900px) rotateX(${round(rx)}deg) rotateY(${round(ry)}deg) ${BASE}` : '';
    }

    const key = JSON.stringify(out);
    if (key === it.last) return;
    it.last = key;
    for (const [prop, value] of Object.entries(out)) {
      if (value) el.style.setProperty(prop, value);
      else el.style.removeProperty(prop);
    }
  }

  /** Eases pointer effects toward their target; true while still moving. */
  function ease(it: Item): boolean {
    const strength = it.m.strength ?? 4;
    if (it.m.mouse === 'track') it.target = [pointer[0] * strength * 5, pointer[1] * strength * 5];
    const [cx, cy] = it.current;
    const [tx, ty] = it.target;
    const nx = cx + (tx - cx) * 0.12;
    const ny = cy + (ty - cy) * 0.12;
    const done = Math.abs(tx - nx) < 0.05 && Math.abs(ty - ny) < 0.05;
    it.current = done ? [tx, ty] : [nx, ny];
    return !done;
  }

  function tick() {
    frame = 0;
    const vh = window.innerHeight;
    const y = window.scrollY;
    let again = false;
    for (const it of items) {
      if (!it.active) continue;
      // Only elements near the viewport are written; the rest keep their last (clamped) state.
      const near = y + vh > it.top - vh * 0.25 && y < it.top + it.height + vh * 0.25;
      if (it.m.mouse && ease(it)) again = true;
      if (near) apply(it);
    }
    if (again) schedule();
  }

  function schedule() {
    if (!frame) frame = requestAnimationFrame(tick);
  }

  let resizeFrame = 0;
  const remeasure = () => {
    if (resizeFrame) return;
    resizeFrame = requestAnimationFrame(() => {
      resizeFrame = 0;
      measure();
      schedule();
    });
  };
  const onPointer = (e: PointerEvent) => {
    pointer = [(e.clientX / window.innerWidth) * 2 - 1, (e.clientY / window.innerHeight) * 2 - 1];
    schedule();
  };
  const bodyObserver = 'ResizeObserver' in window ? new ResizeObserver(remeasure) : null;

  function listen(on: boolean) {
    if (on === listening) return;
    listening = on;
    const method = on ? 'addEventListener' : 'removeEventListener';
    window[method]('scroll', schedule as EventListener, { passive: true } as AddEventListenerOptions);
    window[method]('resize', remeasure as EventListener, { passive: true } as AddEventListenerOptions);
    window[method]('load', remeasure as EventListener);
    window[method]('pointermove', onPointer as EventListener, { passive: true } as AddEventListenerOptions);
    if (on) bodyObserver?.observe(document.body);
    else bodyObserver?.disconnect();
  }

  api.register('motion', (el) => {
    if (api.editor || api.reducedMotion()) return;
    let m: Motion;
    try {
      m = JSON.parse(el.getAttribute('data-uncoder-motion') || '{}');
    } catch {
      return;
    }
    if (m.mouse && !finePointer()) delete m.mouse;
    if (!m.y && !m.x && !m.rotate && !m.scale && !m.fade && !m.blur && !m.mouse) return;

    const it: Item = { el, m, top: 0, height: 0, active: true, last: '', target: [0, 0], current: [0, 0] };
    if (m.mouse === 'tilt') {
      // Tilt follows the pointer across the element and settles back when it leaves.
      const strength = () => (m.strength ?? 4) * 1.5;
      const move = (e: PointerEvent) => {
        const r = el.getBoundingClientRect();
        const px = (e.clientX - r.left) / r.width - 0.5;
        const py = (e.clientY - r.top) / r.height - 0.5;
        it.target = [-py * 2 * strength(), px * 2 * strength()];
        schedule();
      };
      const leave = () => {
        it.target = [0, 0];
        schedule();
      };
      el.addEventListener('pointermove', move);
      el.addEventListener('pointerleave', leave);
      it.cleanup = () => {
        el.removeEventListener('pointermove', move);
        el.removeEventListener('pointerleave', leave);
      };
    }
    items.add(it);
    listen(true);
    remeasure(); // One layout read for all elements registered this frame, then the first apply.
    return () => {
      it.cleanup?.();
      items.delete(it);
      reset(it);
      if (!items.size) listen(false);
    };
  });
})();
