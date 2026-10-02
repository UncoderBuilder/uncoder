// Shared carousel engine (no dependencies) for the carousel, image-carousel and testimonial-carousel
// widgets. Native scroll-snap does the swiping; this adds arrows, pagination (dots / fraction /
// progress), keyboard, mouse drag, eased transitions, autoplay (pause on hover / focus / hidden tab,
// never with reduced motion) and a polite live region.
//
// Markup: [data-settings] > .uncoder-carousel > .uncoder-carousel__viewport > .uncoder-carousel__track > .uncoder-carousel__slide
// Slides per view / gap / slides to scroll come from CSS custom properties, so they are responsive.

interface CarouselSettings {
  autoplay?: boolean;
  delay?: number;
  pauseOnHover?: boolean;
  loop?: boolean;
  drag?: boolean;
  speed?: number;
  i18n?: { goto?: string; status?: string; pause?: string; play?: string };
  ticker?: number; // continuous motion: speed in px per second
}

/**
 * Continuous motion: CSS moves the track by half its width forever (the server prints every slide twice).
 * When one set of slides is narrower than the carousel, more copies are added so the row never runs out, and the
 * loop duration follows the set width so the speed stays the same on every screen.
 */
function ticker(root: HTMLElement, viewport: HTMLElement, track: HTMLElement, slides: HTMLElement[], speed: number) {
  // "Still list" on this device (a per-device setting, so it arrives as a custom property).
  const stacked = () => getComputedStyle(root).getPropertyValue('--uncoder-carousel-stack').trim() === '1';
  if (!root.classList.contains('uncoder-carousel--ticker-run')) return undefined; // editor: a still row
  const originals = slides.filter((sl) => !sl.classList.contains('uncoder-carousel__slide--clone'));
  const added: HTMLElement[] = [];
  let sets = 2;
  // The copies are clipped by the viewport: lazy images there would load late and leave gaps.
  const eager = () => track.querySelectorAll<HTMLImageElement>('img[loading="lazy"]').forEach((img) => (img.loading = 'eager'));
  const measure = () => {
    if (stacked()) return;
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    const set = originals.reduce((w, sl) => w + sl.getBoundingClientRect().width + gap, 0);
    if (!set) return;
    const need = Math.max(1, Math.ceil(viewport.clientWidth / set)) * 2;
    while (sets < need) {
      for (const sl of originals) {
        const copy = sl.cloneNode(true) as HTMLElement;
        copy.classList.add('uncoder-carousel__slide--clone');
        copy.setAttribute('aria-hidden', 'true');
        copy.setAttribute('inert', '');
        copy.removeAttribute('role');
        copy.removeAttribute('aria-roledescription');
        copy.removeAttribute('aria-label');
        copy.querySelectorAll('[id]').forEach((n) => n.removeAttribute('id'));
        track.appendChild(copy);
        added.push(copy);
      }
      sets++;
    }
    root.style.setProperty('--uncoder-carousel-ticker-duration', `${((set * sets) / 2 / speed).toFixed(2)}s`);
    eager();
  };
  let frame = 0;
  const onResize = () => {
    if (!frame) {
      frame = requestAnimationFrame(() => {
        frame = 0;
        measure();
      });
    }
  };
  const ro = 'ResizeObserver' in window ? new ResizeObserver(onResize) : null;
  if (ro) ro.observe(viewport);
  else window.addEventListener('resize', onResize);
  measure();
  return () => {
    ro?.disconnect();
    window.removeEventListener('resize', onResize);
    if (frame) cancelAnimationFrame(frame);
    added.forEach((n) => n.remove());
    root.style.removeProperty('--uncoder-carousel-ticker-duration');
  };
}

interface Page {
  x: number; // scroll offset from the inline start
  slide: number; // first slide of the page
}

const format = (tpl: string, ...args: Array<string | number>) => {
  let n = 0;
  return tpl.replace(/%(\d+)\$s|%s/g, (_m, pos) => String(args[pos ? Number(pos) - 1 : n++] ?? ''));
};

window.UncoderWB.register(
  'carousel',
  (el, api) => {
    const root = (el.matches('.uncoder-carousel') ? (el as HTMLElement) : el.querySelector<HTMLElement>(':scope > .uncoder-carousel'));
    const viewport = root?.querySelector<HTMLElement>(':scope > .uncoder-carousel__viewport');
    const track = viewport?.querySelector<HTMLElement>(':scope > .uncoder-carousel__track');
    if (!root || !viewport || !track) return;
    const slides = Array.from(track.querySelectorAll<HTMLElement>(':scope > .uncoder-carousel__slide'));
    if (!slides.length) return;

    const s = api.settings<CarouselSettings>(el);
    if (root.classList.contains('uncoder-carousel--ticker')) return ticker(root, viewport, track, slides, Number(s.ticker) || 40);
    const t = {
      goto: 'Go to slide %s',
      status: 'Slide %1$s of %2$s',
      pause: 'Pause autoplay',
      play: 'Start autoplay',
      ...(s.i18n || {}),
    };
    const reduced = api.reducedMotion();
    const prev = viewport.querySelector<HTMLButtonElement>(':scope > .uncoder-carousel__arrow--prev');
    const next = viewport.querySelector<HTMLButtonElement>(':scope > .uncoder-carousel__arrow--next');
    const pagination = root.querySelector<HTMLElement>(':scope > .uncoder-carousel__pagination');
    const dotsWrap = pagination?.querySelector<HTMLElement>(':scope > .uncoder-carousel__dots') ?? null;
    const current = pagination?.querySelector<HTMLElement>(':scope > .uncoder-carousel__fraction > .uncoder-carousel__current') ?? null;
    const total = pagination?.querySelector<HTMLElement>(':scope > .uncoder-carousel__fraction > .uncoder-carousel__total') ?? null;
    const toggle = pagination?.querySelector<HTMLButtonElement>(':scope > .uncoder-carousel__toggle') ?? null;
    const status = root.querySelector<HTMLElement>(':scope > .uncoder-carousel__status');

    const cleanups: Array<() => void> = [];
    const listen = (target: EventTarget, type: string, fn: (e: any) => void, opts?: AddEventListenerOptions | boolean) => {
      target.addEventListener(type, fn, opts);
      cleanups.push(() => target.removeEventListener(type, fn, opts));
    };

    let pages: Page[] = [{ x: 0, slide: 0 }];
    let dots: HTMLButtonElement[] = [];
    let index = -1;
    let target: number | null = null; // page an animation is heading to
    let anim = 0;
    let frame = 0;

    const isRtl = () => getComputedStyle(track).direction === 'rtl';
    const position = () => Math.abs(track.scrollLeft);
    const maxScroll = () => Math.max(0, track.scrollWidth - track.clientWidth);

    /* -------------------------------------------------------------- Geometry */

    const measure = () => {
      const max = maxScroll();
      const list: Page[] = [];
      if (max > 1) {
        const rtl = isRtl();
        const box = track.getBoundingClientRect();
        const scrolled = position();
        const step = Math.max(1, Math.round(parseFloat(getComputedStyle(root).getPropertyValue('--uncoder-carousel-sts')) || 1));
        const offsets = slides.map((slide) => {
          const r = slide.getBoundingClientRect();
          return Math.round((rtl ? box.right - r.right : r.left - box.left) + scrolled);
        });
        for (let i = 0; i < slides.length; i += step) {
          const x = Math.min(Math.max(0, offsets[i]), max);
          if (!list.length || x - list[list.length - 1].x > 2) list.push({ x, slide: i });
          if (x >= max) break;
        }
        if (list[list.length - 1].x < max - 2) {
          const last = offsets.findIndex((o) => o >= max - 2);
          list.push({ x: max, slide: last >= 0 ? last : slides.length - 1 });
        }
      }
      pages = list.length ? list : [{ x: 0, slide: 0 }];
      buildDots();
      update(false);
    };

    const nearest = () => {
      const pos = position();
      let best = 0;
      for (let i = 1; i < pages.length; i++) {
        if (Math.abs(pages[i].x - pos) < Math.abs(pages[best].x - pos)) best = i;
      }
      return best;
    };

    /* -------------------------------------------------------------- State → DOM */

    const setDisabled = (button: HTMLButtonElement | null, disabled: boolean) => {
      if (!button) return;
      button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
      button.classList.toggle('is-disabled', disabled);
    };

    const buildDots = () => {
      if (!dotsWrap) return;
      const same = dots.length === pages.length && dots.every((d, i) => d.dataset.slide === String(pages[i].slide));
      if (same) return;
      dotsWrap.textContent = '';
      dots = pages.map((page, i) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.className = 'uncoder-carousel__dot';
        dot.dataset.slide = String(page.slide);
        dot.setAttribute('aria-label', format(t.goto, page.slide + 1));
        dot.addEventListener('click', () => go(i, true));
        dotsWrap.appendChild(dot);
        return dot;
      });
      index = -1;
    };

    const update = (announce: boolean) => {
      frame = 0;
      const i = nearest();
      const last = pages.length - 1;
      root.classList.toggle('is-static', last === 0);
      root.classList.toggle('is-start', i === 0);
      root.classList.toggle('is-end', i === last);
      setDisabled(prev, !s.loop && i === 0);
      setDisabled(next, !s.loop && i === last);
      const width = track.scrollWidth || 1;
      root.style.setProperty('--uncoder-carousel-progress-size', String(Math.min(1, track.clientWidth / width)));
      root.style.setProperty('--uncoder-carousel-progress-start', String(Math.min(1, position() / width)));
      if (i === index) return;
      const changed = index !== -1;
      index = i;
      dots.forEach((dot, d) => {
        dot.classList.toggle('is-active', d === i);
        if (d === i) dot.setAttribute('aria-current', 'true');
        else dot.removeAttribute('aria-current');
      });
      if (current) current.textContent = String(i + 1);
      if (total) total.textContent = String(pages.length);
      if (announce && changed && status && !playing()) {
        status.textContent = format(t.status, pages[i].slide + 1, slides.length);
      }
    };

    const onScroll = () => {
      if (!frame) frame = requestAnimationFrame(() => update(true));
    };

    /* -------------------------------------------------------------- Movement */

    const stopAnim = () => {
      if (anim) cancelAnimationFrame(anim);
      anim = 0;
      target = null;
      root.classList.remove('is-animating');
    };

    const scrollToX = (x: number) => {
      stopAnim();
      const to = isRtl() ? -x : x;
      const from = track.scrollLeft;
      const ms = reduced ? 0 : Math.max(0, Number(s.speed ?? 500) || 0);
      if (!ms || Math.abs(to - from) < 1) {
        track.scrollLeft = to;
        return;
      }
      const start = performance.now();
      root.classList.add('is-animating');
      const tick = (now: number) => {
        const p = Math.min(1, (now - start) / ms);
        const eased = 1 - Math.pow(1 - p, 3);
        track.scrollLeft = from + (to - from) * eased;
        if (p < 1) {
          anim = requestAnimationFrame(tick);
        } else {
          anim = 0;
          target = null;
          root.classList.remove('is-animating');
        }
      };
      anim = requestAnimationFrame(tick);
    };

    const go = (page: number, user = false) => {
      const last = pages.length - 1;
      if (page > last) page = s.loop || !user ? 0 : last;
      if (page < 0) page = s.loop ? last : 0;
      scrollToX(pages[page].x);
      if (anim) target = page;
      if (user) schedule();
    };

    const step = (dir: 1 | -1, user = true) => go((target ?? nearest()) + dir, user);

    const isDisabled = (b: HTMLButtonElement) => b.getAttribute('aria-disabled') === 'true';
    if (prev) listen(prev, 'click', () => !isDisabled(prev) && step(-1));
    if (next) listen(next, 'click', () => !isDisabled(next) && step(1));

    // Arrow keys / Home / End on the carousel's own controls (or the track itself when the browser
    // makes it focusable); keys inside slide content are left alone.
    listen(root, 'keydown', (e: KeyboardEvent) => {
      const el2 = e.target as HTMLElement;
      if (e.altKey || e.ctrlKey || e.metaKey || el2.closest('.uncoder-carousel') !== root) return;
      const control = el2 === track || el2.parentElement === viewport || el2.closest('.uncoder-carousel__pagination') !== null;
      if (!control) return;
      const rtl = isRtl();
      let page: number;
      if (e.key === 'ArrowRight') page = (target ?? nearest()) + (rtl ? -1 : 1);
      else if (e.key === 'ArrowLeft') page = (target ?? nearest()) + (rtl ? 1 : -1);
      else if (e.key === 'Home') page = 0;
      else if (e.key === 'End') page = pages.length - 1;
      else return;
      e.preventDefault();
      if (!s.loop) page = Math.max(0, Math.min(pages.length - 1, page));
      go(page, true);
      const dot = dotsWrap && dotsWrap.contains(el2) ? dots[(page + pages.length) % pages.length] : null;
      dot?.focus();
    });

    // A user scroll/swipe cancels a running transition.
    const interrupt = () => {
      if (anim) stopAnim();
    };
    listen(track, 'wheel', interrupt, { passive: true });
    listen(track, 'touchstart', interrupt, { passive: true });
    listen(track, 'scroll', onScroll, { passive: true });

    /* -------------------------------------------------------------- Mouse drag */

    let drag: { x: number; scroll: number; moved: boolean; id: number } | null = null;
    let suppressClick = false;
    if (s.drag && !api.editor) {
      listen(track, 'pointerdown', (e: PointerEvent) => {
        if (e.pointerType !== 'mouse' || e.button !== 0 || root.classList.contains('is-static')) return;
        if ((e.target as Element).closest('input, textarea, select, button, [contenteditable]')) return;
        stopAnim();
        drag = { x: e.clientX, scroll: track.scrollLeft, moved: false, id: e.pointerId };
      });
      listen(track, 'pointermove', (e: PointerEvent) => {
        if (!drag || e.pointerId !== drag.id) return;
        const dx = e.clientX - drag.x;
        if (!drag.moved && Math.abs(dx) < 5) return;
        if (!drag.moved) {
          drag.moved = true;
          root.classList.add('is-dragging');
          try {
            track.setPointerCapture(e.pointerId);
          } catch {
            /* pointer already released */
          }
          schedule();
        }
        track.scrollLeft = drag.scroll - dx;
      });
      const end = (e: PointerEvent) => {
        if (!drag || e.pointerId !== drag.id) return;
        const { moved, x, scroll } = drag;
        drag = null;
        if (!moved) return;
        root.classList.remove('is-dragging');
        suppressClick = true;
        setTimeout(() => (suppressClick = false), 0);
        const dx = e.clientX - x;
        const start = Math.abs(scroll);
        const forward = isRtl() ? dx > 0 : dx < 0;
        let page = nearest();
        if (Math.abs(dx) > 40) {
          if (forward) {
            const i = pages.findIndex((p) => p.x > start + 2);
            page = i >= 0 ? i : pages.length - 1;
          } else {
            let i = -1;
            pages.forEach((p, k) => {
              if (p.x < start - 2) i = k;
            });
            page = i >= 0 ? i : 0;
          }
        }
        go(page, true);
      };
      listen(track, 'pointerup', end);
      listen(track, 'pointercancel', end);
      listen(track, 'click', (e: MouseEvent) => {
        if (suppressClick) {
          e.preventDefault();
          e.stopPropagation();
          suppressClick = false;
        }
      }, true);
      listen(track, 'dragstart', (e: DragEvent) => e.preventDefault());
    }

    /* -------------------------------------------------------------- Autoplay */

    let timer = 0;
    let stopped = !s.autoplay || reduced || api.editor;
    let hovered = false;
    let focused = false;
    let visible = true;

    function playing() {
      return !!s.autoplay && !stopped && !hovered && !focused && visible && !document.hidden && !drag && pages.length > 1;
    }

    function schedule() {
      clearTimeout(timer);
      timer = 0;
      if (!playing()) return;
      timer = window.setTimeout(() => {
        step(1, false);
        schedule();
      }, Math.max(1000, Number(s.delay ?? 5000) || 5000));
    }

    const syncToggle = () => {
      if (toggle) {
        toggle.classList.toggle('is-paused', stopped);
        toggle.setAttribute('aria-label', stopped ? t.play : t.pause);
      }
      root.classList.toggle('is-playing', !!s.autoplay && !stopped);
    };

    if (s.autoplay) {
      if (toggle) {
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
      // Rotation pauses while keyboard focus is inside (the pause button itself excepted).
      listen(root, 'focusin', (e: FocusEvent) => {
        focused = e.target !== toggle;
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
      syncToggle();
    }

    /* -------------------------------------------------------------- Editor */

    listen(el, 'uncoder:nested-select', (e: CustomEvent<{ index?: number }>) => {
      if (e.target !== el || typeof e.detail?.index !== 'number') return;
      const slide = e.detail.index;
      let page = 0;
      pages.forEach((p, i) => {
        if (p.slide <= slide) page = i;
      });
      go(page);
    });

    /* -------------------------------------------------------------- Init */

    let resizeFrame = 0;
    const onResize = () => {
      if (!resizeFrame) {
        resizeFrame = requestAnimationFrame(() => {
          resizeFrame = 0;
          measure();
        });
      }
    };
    if ('ResizeObserver' in window) {
      const ro = new ResizeObserver(onResize);
      ro.observe(track);
      cleanups.push(() => ro.disconnect());
    } else {
      listen(window, 'resize', onResize);
    }

    root.classList.add('is-ready');
    measure();
    schedule();

    return () => {
      cleanups.forEach((fn) => fn());
      stopAnim();
      clearTimeout(timer);
      if (frame) cancelAnimationFrame(frame);
      if (resizeFrame) cancelAnimationFrame(resizeFrame);
      if (dotsWrap) dotsWrap.textContent = '';
      root.classList.remove('is-ready', 'is-static', 'is-start', 'is-end', 'is-playing', 'is-dragging', 'is-animating');
      root.style.removeProperty('--uncoder-carousel-progress-size');
      root.style.removeProperty('--uncoder-carousel-progress-start');
    };
  },
  { lazy: true },
);
