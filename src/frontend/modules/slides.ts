// Slides (widget "slides"): full-width hero slider. The slides share one grid cell; a change animates
// only the outgoing and the incoming slide with the Web Animations API, so the loop is infinite in both
// directions without cloned slides. Adds arrows, dots / fraction, swipe that follows the finger (slide
// mode), ← → / Home / End on the slider's own controls, autoplay (pause button, hover, keyboard focus,
// hidden tab, off screen; never in the editor or with reduced motion) and a polite status region that
// only speaks for user-initiated changes. Reduced motion: instant changes, no Ken Burns (CSS).
//
// Markup: [data-settings].uncoder-slides > .uncoder-slides__viewport > .uncoder-slides__slide (see includes/Widgets/Slides.php)

interface SlidesSettings {
  transition?: 'slide' | 'fade';
  speed?: number;
  autoplay?: boolean;
  delay?: number;
  pauseOnHover?: boolean;
  loop?: boolean;
  swipe?: boolean;
  i18n?: { status?: string; pause?: string; play?: string };
}

interface Drag {
  id: number;
  x: number;
  y: number;
  t: number;
  dx: number;
  moved: boolean;
  off: number; // applied offset of the current slide (with edge resistance)
  peek: HTMLElement | null; // neighbour shown while dragging
  peekStart: number;
}

const format = (tpl: string, ...args: Array<string | number>) => {
  let n = 0;
  return tpl.replace(/%(\d+)\$s|%s/g, (_m, pos) => String(args[pos ? Number(pos) - 1 : n++] ?? ''));
};

// Editor canvas: the slide on show survives the re-render that follows a settings change.
const remembered = new Map<string, number>();

const EASE_MOVE = 'cubic-bezier(0.65, 0, 0.35, 1)';
const EASE_RELEASE = 'cubic-bezier(0.2, 0.7, 0.3, 1)';

window.UncoderWB.register(
  'slides',
  (el, api) => {
    const root = el.matches('.uncoder-slides') ? el : el.querySelector<HTMLElement>(':scope > .uncoder-slides');
    const viewport = root?.querySelector<HTMLElement>(':scope > .uncoder-slides__viewport');
    if (!root || !viewport) return;
    const slides = Array.from(viewport.querySelectorAll<HTMLElement>(':scope > .uncoder-slides__slide'));
    const total = slides.length;
    if (total < 2) return;

    const s = api.settings<SlidesSettings>(el);
    const t = { status: 'Slide %1$s of %2$s', pause: 'Pause autoplay', play: 'Start autoplay', ...(s.i18n || {}) };
    const reduced = api.reducedMotion();
    const fade = s.transition === 'fade';
    const loop = !!s.loop;
    const speed = reduced ? 0 : Math.min(3000, Math.max(0, Number(s.speed ?? 700) || 0));
    const delay = Math.max(1500, Number(s.delay ?? 6000) || 6000);
    const canAnimate = typeof viewport.animate === 'function';
    const key = el.getAttribute('data-id') || '';

    const prev = root.querySelector<HTMLButtonElement>(':scope > .uncoder-slides__arrow--prev');
    const next = root.querySelector<HTMLButtonElement>(':scope > .uncoder-slides__arrow--next');
    const dots = Array.from(root.querySelectorAll<HTMLButtonElement>(':scope > .uncoder-slides__pagination > .uncoder-slides__dots > .uncoder-slides__dot'));
    const current = root.querySelector<HTMLElement>(':scope > .uncoder-slides__pagination > .uncoder-slides__fraction > .uncoder-slides__current');
    const toggle = root.querySelector<HTMLButtonElement>(':scope > .uncoder-slides__toggle');
    const status = root.querySelector<HTMLElement>(':scope > .uncoder-slides__status');

    const cleanups: Array<() => void> = [];
    const listen = (target: EventTarget, type: string, fn: (e: any) => void, opts?: AddEventListenerOptions | boolean) => {
      target.addEventListener(type, fn, opts);
      cleanups.push(() => target.removeEventListener(type, fn, opts));
    };

    let index = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
    let anims: Animation[] = [];
    let settle: (() => void) | null = null; // jumps the running transition to its end
    let drag: Drag | null = null;

    const isRtl = () => getComputedStyle(root).direction === 'rtl';
    // Logical direction of a horizontal drag: moving left means "next" in LTR, "previous" in RTL.
    const dragDir = (dx: number): 1 | -1 => ((dx < 0) !== isRtl() ? 1 : -1);
    const wrap = (i: number) => ((i % total) + total) % total;
    const inRange = (i: number) => loop || (i >= 0 && i < total);
    const px = (x: number) => `translate3d(${x}px,0,0)`;

    /* -------------------------------------------------------------- State → DOM */

    const setDisabled = (button: HTMLButtonElement | null, disabled: boolean) => {
      if (!button) return;
      button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
      button.classList.toggle('is-disabled', disabled);
    };

    const show = (i: number) => {
      slides.forEach((slide, k) => {
        const on = k === i;
        slide.classList.toggle('is-active', on);
        if (on) {
          slide.removeAttribute('inert');
          slide.removeAttribute('aria-hidden');
        } else {
          slide.setAttribute('inert', '');
          slide.setAttribute('aria-hidden', 'true');
        }
      });
      dots.forEach((dot, k) => {
        dot.classList.toggle('is-active', k === i);
        if (k === i) dot.setAttribute('aria-current', 'true');
        else dot.removeAttribute('aria-current');
      });
      if (current) current.textContent = String(i + 1);
      setDisabled(prev, !loop && i === 0);
      setDisabled(next, !loop && i === total - 1);
    };

    const announce = (i: number) => {
      if (!status) return;
      const heading = slides[i].querySelector('.uncoder-slides__heading')?.textContent?.trim();
      status.textContent = format(t.status, i + 1, total) + (heading ? `: ${heading}` : '');
    };

    const finish = () => settle?.();

    const stop = () => {
      anims.forEach((a) => a.cancel());
      anims = [];
    };

    /* -------------------------------------------------------------- Movement */

    // `dir` 1 = forward, -1 = back; `offset` = px the slides were already dragged (slide mode).
    const go = (to: number, user: boolean, dir?: 1 | -1, offset = 0) => {
      finish();
      if (!inRange(to)) return;
      to = wrap(to);
      if (to === index) return;
      const from = index;
      const out = slides[from];
      const inc = slides[to];
      const d: 1 | -1 = dir ?? (to > from ? 1 : -1);
      index = to;
      root.classList.add('has-moved');
      inc.classList.toggle('is-swiped', offset !== 0);
      inc.classList.remove('is-peek');
      out.classList.add('is-leaving');
      show(to);
      if (api.editor && key) remembered.set(key, to);
      if (user) announce(to);

      const end = () => {
        settle = null;
        out.classList.remove('is-leaving');
        out.style.transform = '';
        inc.style.transform = '';
        stop();
      };
      const w = viewport.clientWidth || 1;
      const ms = offset ? speed * Math.max(0.3, 1 - Math.abs(offset) / w) : speed;
      if (!ms || !canAnimate) {
        end();
        return;
      }
      const opts: KeyframeAnimationOptions = { duration: ms, easing: offset ? EASE_RELEASE : EASE_MOVE, fill: 'both' };
      if (fade) {
        anims = [inc.animate([{ opacity: 0 }, { opacity: 1 }], { ...opts, easing: 'ease' })];
      } else {
        const side = d * (isRtl() ? -1 : 1); // 1: the new slide comes in from the right
        anims = [
          out.animate([{ transform: px(offset) }, { transform: px(-side * w) }], opts),
          inc.animate([{ transform: px(offset + side * w) }, { transform: px(0) }], opts),
        ];
      }
      // Drag positions were inline styles; the animations take over from the same spot.
      out.style.transform = '';
      inc.style.transform = '';
      settle = end;
      anims[0].finished.then(
        () => settle === end && end(),
        () => {},
      );
    };

    // A drag that did not go far enough: back to where it started.
    const snapBack = (d: Drag) => {
      const cur = slides[index];
      const peek = d.peek;
      const end = () => {
        settle = null;
        cur.style.transform = '';
        if (peek) {
          peek.classList.remove('is-peek');
          peek.style.transform = '';
        }
        stop();
      };
      if (!speed || !canAnimate || !d.off) {
        end();
        return;
      }
      const opts: KeyframeAnimationOptions = { duration: Math.max(180, speed * 0.6), easing: EASE_RELEASE, fill: 'both' };
      anims = [cur.animate([{ transform: px(d.off) }, { transform: px(0) }], opts)];
      if (peek) anims.push(peek.animate([{ transform: px(d.peekStart) }, { transform: px(d.peekStart - d.off) }], opts));
      cur.style.transform = '';
      if (peek) peek.style.transform = '';
      settle = end;
      anims[0].finished.then(
        () => settle === end && end(),
        () => {},
      );
    };

    const step = (dir: 1 | -1, user = true) => go(index + dir, user, dir);

    const isDisabled = (b: HTMLButtonElement) => b.getAttribute('aria-disabled') === 'true';
    if (prev) {
      listen(prev, 'click', () => {
        if (isDisabled(prev)) return;
        step(-1);
        schedule();
      });
    }
    if (next) {
      listen(next, 'click', () => {
        if (isDisabled(next)) return;
        step(1);
        schedule();
      });
    }
    dots.forEach((dot, k) =>
      listen(dot, 'click', () => {
        go(k, true);
        schedule();
      }),
    );

    /* -------------------------------------------------------------- Keyboard */

    // ← → / Home / End on the slider's own controls (or the region itself); keys inside slide content
    // are left alone so focus never ends up in a slide that is being hidden.
    if (!api.editor) {
      listen(root, 'keydown', (e: KeyboardEvent) => {
        if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
        const target = e.target as HTMLElement;
        const control = target === root || target.closest('.uncoder-slides__arrow, .uncoder-slides__pagination, .uncoder-slides__toggle') !== null;
        if (!control || target.closest('.uncoder-slides') !== root) return;
        const rtl = isRtl();
        let to: number;
        let dir: 1 | -1;
        if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
          dir = (e.key === 'ArrowRight') !== rtl ? 1 : -1;
          to = index + dir;
        } else if (e.key === 'Home') {
          to = 0;
          dir = -1;
        } else if (e.key === 'End') {
          to = total - 1;
          dir = 1;
        } else return;
        e.preventDefault();
        if (!inRange(to)) return;
        go(to, true, dir);
        schedule();
        if (dots.includes(target as HTMLButtonElement)) dots[index].focus();
      });
    }

    /* -------------------------------------------------------------- Swipe / drag */

    let suppressClick = false;
    if (s.swipe && !api.editor) {
      listen(viewport, 'pointerdown', (e: PointerEvent) => {
        if (drag || (e.pointerType === 'mouse' && e.button !== 0)) return;
        const target = e.target as Element;
        if (target.closest('input, textarea, select, [contenteditable]')) return;
        // Mouse: no text selection while dragging (links and buttons keep their default behaviour).
        if (e.pointerType === 'mouse' && !target.closest('a, button')) e.preventDefault();
        drag = { id: e.pointerId, x: e.clientX, y: e.clientY, t: performance.now(), dx: 0, moved: false, off: 0, peek: null, peekStart: 0 };
      });

      listen(viewport, 'pointermove', (e: PointerEvent) => {
        if (!drag || e.pointerId !== drag.id) return;
        const dx = e.clientX - drag.x;
        const dy = e.clientY - drag.y;
        if (!drag.moved) {
          if (Math.abs(dx) < 8 && Math.abs(dy) < 8) return;
          if (Math.abs(dy) >= Math.abs(dx)) {
            drag = null; // vertical: the page scrolls
            return;
          }
          finish();
          drag.moved = true;
          root.classList.add('is-dragging');
          try {
            viewport.setPointerCapture(e.pointerId);
          } catch {
            /* pointer already released */
          }
          schedule();
        }
        drag.dx = dx;
        if (fade) return;
        const w = viewport.clientWidth || 1;
        const dir = dragDir(dx);
        const has = inRange(index + dir);
        const peek = has ? slides[wrap(index + dir)] : null;
        if (peek !== drag.peek) {
          if (drag.peek) {
            drag.peek.classList.remove('is-peek');
            drag.peek.style.transform = '';
          }
          peek?.classList.add('is-peek');
          drag.peek = peek;
        }
        drag.off = has ? dx : dx * 0.3;
        slides[index].style.transform = px(drag.off);
        if (peek) {
          drag.peekStart = drag.off + (dx < 0 ? w : -w);
          peek.style.transform = px(drag.peekStart);
        }
      });

      const release = (e: PointerEvent) => {
        if (!drag || e.pointerId !== drag.id) return;
        const d = drag;
        drag = null;
        if (!d.moved) return;
        root.classList.remove('is-dragging');
        suppressClick = true;
        setTimeout(() => (suppressClick = false), 0);
        const dx = e.type === 'pointercancel' ? 0 : d.dx;
        const w = viewport.clientWidth || 1;
        const fast = Math.abs(dx) / Math.max(1, performance.now() - d.t) > 0.4 && Math.abs(dx) > 30;
        const dir = dragDir(dx);
        if (dx && inRange(index + dir) && (fast || Math.abs(dx) > Math.min(120, w * 0.2))) {
          go(index + dir, true, dir, fade ? 0 : d.off);
        } else if (!fade) {
          snapBack(d);
        }
        schedule();
      };
      listen(viewport, 'pointerup', release);
      listen(viewport, 'pointercancel', release);
      listen(
        viewport,
        'click',
        (e: MouseEvent) => {
          if (!suppressClick) return;
          e.preventDefault();
          e.stopPropagation();
          suppressClick = false;
        },
        true,
      );
      listen(viewport, 'dragstart', (e: DragEvent) => e.preventDefault());
    }

    /* -------------------------------------------------------------- Autoplay */

    let timer = 0;
    let stopped = !s.autoplay || reduced || api.editor;
    let hovered = false;
    let focused = false;
    let visible = true;

    function playing() {
      return !!s.autoplay && !stopped && !hovered && !focused && visible && !document.hidden && !drag;
    }

    function schedule() {
      clearTimeout(timer);
      timer = 0;
      if (!playing()) return;
      timer = window.setTimeout(() => {
        // Without the loop, autoplay rewinds to the first slide after the last one.
        if (index === total - 1 && !loop) go(0, false, -1);
        else step(1, false);
        schedule();
      }, delay);
    }

    const syncToggle = () => {
      if (toggle) {
        toggle.classList.toggle('is-paused', stopped);
        toggle.setAttribute('aria-label', stopped ? t.play : t.pause);
      }
      root.classList.toggle('is-playing', !!s.autoplay && !stopped);
    };

    if (s.autoplay) {
      if (toggle && !api.editor) {
        listen(toggle, 'click', () => {
          stopped = !stopped;
          syncToggle();
          schedule();
        });
      }
      if (s.pauseOnHover) {
        listen(root, 'pointerenter', (e: PointerEvent) => {
          if (e.pointerType === 'mouse') {
            hovered = true;
            schedule();
          }
        });
        listen(root, 'pointerleave', () => {
          hovered = false;
          schedule();
        });
      }
      // Rotation pauses while keyboard focus is inside (the pause button itself excepted); a mouse click
      // on an arrow or dot leaves focus there without :focus-visible and keeps autoplay running.
      const keyboardFocus = (target: Element) => {
        try {
          return target.matches(':focus-visible');
        } catch {
          return true;
        }
      };
      listen(root, 'focusin', (e: FocusEvent) => {
        focused = e.target !== toggle && keyboardFocus(e.target as Element);
        schedule();
      });
      listen(root, 'focusout', (e: FocusEvent) => {
        if (!root.contains(e.relatedTarget as Node | null)) {
          focused = false;
          schedule();
        }
      });
      listen(document, 'visibilitychange', schedule);
      if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver(([entry]) => {
          visible = entry.isIntersecting;
          schedule();
        });
        io.observe(root);
        cleanups.push(() => io.disconnect());
      }
      // The Ken Burns zoom lasts as long as a slide is on show.
      root.style.setProperty('--uncoder-slides-kb-duration', `${delay + speed * 2}ms`);
      syncToggle();
    }

    /* -------------------------------------------------------------- Init */

    if (api.editor && key && remembered.has(key)) {
      const kept = remembered.get(key)!;
      if (kept < total) index = kept;
    }
    show(index);
    root.classList.add('is-ready');
    schedule();

    return () => {
      cleanups.forEach((fn) => fn());
      clearTimeout(timer);
      stop();
      settle = null;
      slides.forEach((slide) => {
        slide.classList.remove('is-leaving', 'is-peek', 'is-swiped');
        slide.style.transform = '';
      });
      root.classList.remove('is-ready', 'is-playing', 'is-dragging', 'has-moved');
      root.style.removeProperty('--uncoder-slides-kb-duration');
    };
  },
  { lazy: true },
);

// A module (not a global script), so the helpers above never collide with other modules' names.
export {};
