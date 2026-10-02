// Animated backgrounds (container Style → Background → Animated background): WebGL shaders from
// assets/vendor/animated-bg drawn in a layer behind the content. The CSS styles need no script; this module
// runs the shaders. A shader and its small WebGL helper load only where they are used; the animation pauses
// off screen and in background tabs, and visitors who prefer reduced motion see a still frame.

interface Settings {
  name: string;
  base: string;
  ver?: string;
  speed?: number;
  scale?: number;
  intensity?: number;
  noise?: number;
  angle?: number;
  offsetX?: number;
  offsetY?: number;
  interactive?: boolean;
  static?: boolean;
  frame?: number;
  image?: string;
}

/** What a shader script stores on element.animatedBackground. */
interface Shader {
  renderer?: { setSize(w: number, h: number): void; render(o: { scene: unknown }): void };
  program?: { uniforms: Record<string, { value: unknown }> };
  mesh?: unknown;
  custom?: boolean;
  pause?: () => void;
  resume?: () => void;
  destroy?: () => void;
}

type Rgb = [number, number, number];
type Host = HTMLElement & { animatedBackground?: Shader | null };

const loading = new Map<string, Promise<void>>();
const load = (src: string): Promise<void> => {
  let promise = loading.get(src);
  if (!promise) {
    promise = new Promise<void>((resolve, reject) => {
      const script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.onload = () => resolve();
      script.onerror = () => {
        loading.delete(src);
        reject(new Error(`bg-animated: ${src}`));
      };
      document.head.appendChild(script);
    });
    loading.set(src, promise);
  }
  return promise;
};

// Fluid Gradient keeps the name it had in Uncoder Elements.
const entry = (name: string) => (name === 'fluid-gradient' ? 'uiAnimated_Animation-6' : `uiAnimated_${name.replace(/(?:^|-)([a-z])/g, (_, c: string) => c.toUpperCase())}`);

/** Any CSS colour (hex, rgb(), color(srgb …)) → 0–1 RGB, through the browser's own colour parsing. */
const toRgb = (color: string, fallback: string): Rgb => {
  const probe = document.createElement('i');
  probe.style.color = fallback;
  if (color) probe.style.color = color;
  probe.style.display = 'none';
  document.body.appendChild(probe);
  const computed = getComputedStyle(probe).color;
  probe.remove();
  const nums = (computed.match(/[\d.]+/g) || ['0', '0', '0']).slice(0, 3).map(Number);
  const unit = computed.startsWith('color(') ? 1 : 255;
  return [nums[0] / unit, nums[1] / unit, nums[2] / unit];
};

const toHex = (c: Rgb) => `#${c.map((v) => Math.round(v * 255).toString(16).padStart(2, '0')).join('')}`;

window.UncoderWB.register('bg-animated', (layer, api) => {
  const s = api.settings<Settings>(layer);
  const host = layer.parentElement as Host | null;
  if (!host || !s.name || !s.base) return;
  const reduced = api.reducedMotion();
  const still = !!s.static || reduced;
  const interactive = !reduced && (!!s.interactive || s.name === 'liquid-image');
  const scripts = [`${s.base}${s.name === 'bit-wave' ? 'ogl.js' : 'ogl-lite.js'}?ver=${s.ver ?? ''}`, `${s.base}${s.name}.js?ver=${s.ver ?? ''}`];

  let canvas: HTMLCanvasElement | null = null;
  let shader: Shader | null = null;
  // What the shader script stored on the host (read through a function: the script sets it, not this code).
  const created = (): Shader | null => host.animatedBackground ?? null;
  let raf = 0;
  let near = false;
  let destroyed = false;
  let generation = 0;
  const mouse = { x: 0.5, y: 0.5, vx: 0, vy: 0 };
  const target = { x: 0.5, y: 0.5 };
  let tracking = false;

  const size = () => [host.offsetWidth || 1, host.offsetHeight || 1] as const;

  /** Shader settings; colours come from the container's own choices, else the Design System (see bg-animated.css). */
  const options = (c: HTMLCanvasElement) => {
    const style = getComputedStyle(layer);
    const colors = (['1', '2', '3', '4'] as const).map((n, i) => toRgb(style.getPropertyValue(`--uncoder-abg-${n}`).trim(), ['#2b59ff', '#7c3aed', '#22d3ee', '#0f172a'][i]));
    // Fluid Gradient reads its colours as hex from the canvas.
    colors.forEach((rgb, i) => c.style.setProperty(`--uncoder-fluid-${i + 1}`, toHex(rgb)));
    return {
      colorArray: colors,
      backgroundColor: toRgb(style.getPropertyValue('--uncoder-abg-bg').trim(), '#000'),
      scale: s.scale ?? 10,
      intensity: s.intensity ?? 50,
      speed: still ? 0 : s.speed ?? 20,
      noise: s.noise ?? 20,
      angle: s.angle ?? 0,
      mouseInteractive: interactive,
      static: still,
      progress: s.frame ?? 10,
      offsetX: s.offsetX ?? 0,
      offsetY: s.offsetY ?? 0,
      texture: s.image ? { url: s.image, src: s.image } : null,
    };
  };

  const draw = (t: number) => {
    if (!shader?.program || !shader.renderer) return;
    const u = shader.program.uniforms;
    if (u.uTime) u.uTime.value = t * 0.001;
    // A soft spring towards the pointer (from Uncoder Elements).
    mouse.vx = (mouse.vx + (target.x - mouse.x) * 0.12) * 0.72;
    mouse.vy = (mouse.vy + (target.y - mouse.y) * 0.12) * 0.72;
    mouse.x += mouse.vx;
    mouse.y += mouse.vy;
    if (u.uMouse) u.uMouse.value = [mouse.x, mouse.y];
    shader.renderer.render({ scene: shader.mesh });
  };

  // Still frames only need drawing once (again after a resize); moving ones run while near the screen.
  const running = () => !destroyed && near && !document.hidden && !(still && !interactive);

  const loop = (t: number) => {
    raf = 0;
    draw(t);
    if (running()) raf = requestAnimationFrame(loop);
  };

  const sync = () => {
    if (!shader) return;
    if (shader.custom) {
      if (running()) shader.resume?.();
      else shader.pause?.();
      return;
    }
    if (running() && !raf) raf = requestAnimationFrame(loop);
    if (!running() && raf) {
      cancelAnimationFrame(raf);
      raf = 0;
    }
  };

  const resize = () => {
    if (!shader || shader.custom || !shader.renderer || !shader.program) return;
    const [w, h] = size();
    shader.renderer.setSize(w, h);
    if (shader.program.uniforms.uResolution) shader.program.uniforms.uResolution.value = [w, h, w / h];
    if (!raf) draw(performance.now());
  };

  const onPointer = (e: PointerEvent) => {
    const r = host.getBoundingClientRect();
    const x = (e.clientX - r.left) / r.width;
    const y = (e.clientY - r.top) / r.height;
    if (!tracking) {
      // Start where the pointer is instead of sliding in from the centre.
      mouse.x = target.x = x;
      mouse.y = target.y = y;
      tracking = true;
    }
    target.x = x;
    target.y = y;
  };

  // Each background owns a WebGL context only while it is near the screen: browsers keep about 16 at a time and
  // drop the oldest, so a page (or the editor) with many animated sections would otherwise lose some.
  const start = () => {
    if (canvas || destroyed) return;
    const run = ++generation;
    const c = document.createElement('canvas');
    c.className = 'uncoder-abg__canvas';
    layer.appendChild(c);
    canvas = c;
    load(scripts[0])
      .then(() => load(scripts[1]))
      .then(() => {
        const init = (window as unknown as Record<string, unknown>)[entry(s.name)];
        if (run !== generation || typeof init !== 'function') return;
        host.animatedBackground = null;
        (init as (el: HTMLElement, cv: HTMLCanvasElement, o: unknown) => void)(host, c, options(c));
        shader = created();
        if (!shader) return;
        layer.classList.add('is-ready');
        if (interactive && !shader.custom) host.addEventListener('pointermove', onPointer, { passive: true });
        resize();
        sync();
      })
      .catch(() => {
        /* The container keeps its plain background. */
      });
  };

  const stop = () => {
    generation++;
    if (raf) cancelAnimationFrame(raf);
    raf = 0;
    host.removeEventListener('pointermove', onPointer);
    try {
      shader?.destroy?.();
    } catch {
      /* ignore */
    }
    shader = null;
    host.animatedBackground = null;
    layer.classList.remove('is-ready');
    if (canvas) {
      const gl = (canvas.getContext('webgl2') || canvas.getContext('webgl')) as WebGLRenderingContext | null;
      gl?.getExtension('WEBGL_lose_context')?.loseContext();
      canvas.remove();
      canvas = null;
    }
  };

  const io = new IntersectionObserver(
    (entries) => {
      near = entries.some((e) => e.isIntersecting);
      if (near) start();
      else stop();
      sync();
    },
    { rootMargin: '200px 0px' },
  );
  const ro = new ResizeObserver(resize);
  io.observe(host);
  ro.observe(host);
  document.addEventListener('visibilitychange', sync);

  return () => {
    destroyed = true;
    io.disconnect();
    ro.disconnect();
    document.removeEventListener('visibilitychange', sync);
    stop();
  };
});
