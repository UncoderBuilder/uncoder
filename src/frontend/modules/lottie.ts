// Lottie widget: loads the bundled lottie-web "light" player (SVG, no expressions) once, when the
// first animation nears the viewport, then plays by trigger: autoplay while visible, on hover, on
// click (a keyboard-reachable toggle) or following the scroll. Reduced motion shows a still frame.
interface LottieAnim {
  totalFrames: number;
  isPaused: boolean;
  loop: boolean | number;
  play(): void;
  pause(): void;
  stop(): void;
  setSpeed(speed: number): void;
  setDirection(direction: 1 | -1): void;
  goToAndStop(value: number, isFrame?: boolean): void;
  goToAndPlay(value: number, isFrame?: boolean): void;
  addEventListener(name: string, cb: () => void): void;
  destroy(): void;
}

let lottiePlayer: Promise<any> | null = null;
const loadLottie = (src: string): Promise<any> =>
  (lottiePlayer ||= new Promise((resolve, reject) => {
    const w = window as any;
    if (w.lottie) return resolve(w.lottie);
    const script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = () => (w.lottie || w.bodymovin ? resolve(w.lottie || w.bodymovin) : reject(new Error('lottie')));
    script.onerror = () => {
      lottiePlayer = null;
      reject(new Error('lottie'));
    };
    document.head.appendChild(script);
  }));

window.UncoderWB.register(
  'lottie',
  (el, api) => {
    const box = (el.matches('.uncoder-lottie[data-settings]') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-lottie[data-settings]'));
    if (!box) return;
    const s = api.settings<{ src: string; player: string; trigger: string; loop: boolean; speed: number; reverse: boolean; hoverOut: string }>(box);
    const reduced = api.reducedMotion();
    const dir: 1 | -1 = s.reverse ? -1 : 1;
    const cleanups: Array<() => void> = [];
    const on = <K extends keyof HTMLElementEventMap>(target: HTMLElement | Window, type: K, fn: (e: HTMLElementEventMap[K]) => void, opts?: AddEventListenerOptions) => {
      target.addEventListener(type, fn as EventListener, opts);
      cleanups.push(() => target.removeEventListener(type, fn as EventListener, opts));
    };
    let anim: LottieAnim | null = null;
    let destroyed = false;

    const last = () => Math.max(0, (anim?.totalFrames ?? 1) - 1);
    const rest = () => anim?.goToAndStop(dir === -1 ? last() : 0, true);

    const ready = () => {
      if (!anim) return;
      box.classList.add('is-ready');
      anim.setDirection(dir);
      rest();
      const trigger = s.trigger;

      if (trigger === 'scroll') {
        if (reduced) return;
        let raf = 0;
        const update = () => {
          raf = 0;
          const r = box.getBoundingClientRect();
          const vh = window.innerHeight || 1;
          const p = Math.min(1, Math.max(0, (vh - r.top) / (vh + r.height)));
          anim?.goToAndStop(p * last(), true);
        };
        const queue = () => (raf ||= requestAnimationFrame(update));
        on(window, 'scroll', queue, { passive: true });
        on(window, 'resize', queue);
        cleanups.push(() => cancelAnimationFrame(raf));
        update();
        return;
      }

      if (trigger === 'hover') {
        if (reduced) return;
        const host = (box.closest('a') as HTMLElement | null) ?? el;
        const enter = () => {
          if (!anim) return;
          anim.loop = s.loop;
          anim.setDirection(dir);
          anim.play();
        };
        const leave = () => {
          if (!anim) return;
          if (s.hoverOut === 'reverse') {
            anim.loop = false;
            anim.setDirection(dir === 1 ? -1 : 1);
            anim.play();
          } else if (s.hoverOut === 'pause') anim.pause();
          else if (s.hoverOut === 'stop') {
            anim.stop();
            rest();
          }
        };
        on(host, 'mouseenter', enter);
        on(host, 'mouseleave', leave);
        on(host, 'focusin', enter);
        on(host, 'focusout', leave);
        return;
      }

      if (trigger === 'click') {
        // Explicit, visitor-started motion stays available with reduced motion.
        const toggle = () => {
          if (!anim) return;
          if (anim.isPaused) {
            if (!s.loop && box.dataset.done) {
              delete box.dataset.done;
              rest();
            }
            anim.play();
          } else anim.pause();
          box.setAttribute('aria-pressed', String(!anim.isPaused));
        };
        anim.addEventListener('complete', () => {
          box.dataset.done = '1';
          box.setAttribute('aria-pressed', 'false');
        });
        on(box, 'click', (e) => {
          if (!box.closest('a')) e.preventDefault();
          toggle();
        });
        on(box, 'keydown', (e) => {
          if (e.key !== 'Enter' && e.key !== ' ') return;
          e.preventDefault();
          toggle();
        });
        return;
      }

      // Autoplay: only while on screen, to spare the CPU and battery.
      if (reduced) return;
      const io = new IntersectionObserver(([entry]) => (entry.isIntersecting ? anim?.play() : anim?.pause()));
      io.observe(box);
      cleanups.push(() => io.disconnect());
    };

    loadLottie(s.player)
      .then((lottie) => {
        if (destroyed) return;
        anim = lottie.loadAnimation({
          container: box,
          renderer: 'svg',
          loop: s.trigger !== 'scroll' && s.loop,
          autoplay: false,
          path: s.src,
          rendererSettings: { preserveAspectRatio: 'xMidYMid meet', progressiveLoad: true },
        }) as LottieAnim;
        anim.setSpeed(s.speed || 1);
        anim.addEventListener('DOMLoaded', ready);
        anim.addEventListener('data_failed', () => box.classList.add('is-failed'));
      })
      .catch(() => box.classList.add('is-failed'));

    return () => {
      destroyed = true;
      cleanups.forEach((fn) => fn());
      anim?.destroy();
      anim = null;
    };
  },
  { lazy: true },
);
