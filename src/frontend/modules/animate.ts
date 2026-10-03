// Timeline animations (Behaviour → Animations; format in shared/anim.ts). Each element carries its
// definitions in data-uncoder-animate. A definition becomes one Web Animation per target (the element, its
// child items, or its words / letters / lines), all sharing one duration, so a group plays, reverses,
// loops and scrubs together. Triggers: page load, entering the viewport, scroll position (scrub), hover,
// click and loop. Keyframes are composited on top of the element's own styles ("add"), and an entrance
// that ends where the element started drops its effect when done. Nothing runs in the editor (it
// previews on demand through UncoderWB.animate.preview) or for visitors who prefer reduced motion.
import { compile, type AnimDef, type Compiled } from '../../shared/anim';
import { elementId } from '../../shared/element';

type Units = HTMLElement[][];

interface Targets {
  units: Units;
  /** Line groups measured again (fonts may have changed the line breaks since splitting). */
  relayout?: () => Units;
  restore: () => void;
  split: boolean;
}

interface Group {
  anims: Animation[];
  compiled: Compiled;
  /** Time where the last iteration ends (Infinity for loops). */
  end: number;
}

(() => {
  const api = window.UncoderWB;
  const supportsLinear = typeof CSS !== 'undefined' && !!CSS.supports?.('animation-timing-function', 'linear(0, 1)');
  const additive = typeof KeyframeEffect !== 'undefined' && 'composite' in KeyframeEffect.prototype;
  const SKIP = 'script,style,svg,noscript,textarea,.uncoder-sr-only';
  const clamp = (v: number, min: number, max: number) => Math.min(max, Math.max(min, v));
  const span = (className: string) => {
    const s = document.createElement('span');
    s.className = className;
    return s;
  };

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

  // ---------------------------------------------------------------- Targets

  /** Wraps the words (and letters) of every text node; markup and links stay as they are. */
  function splitText(el: HTMLElement, mode: 'words' | 'chars' | 'lines', mask: boolean): Targets {
    const texts: Text[] = [];
    const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, {
      acceptNode: (node) => (node.nodeValue?.trim() && !node.parentElement?.closest(SKIP) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT),
    });
    while (walker.nextNode()) texts.push(walker.currentNode as Text);

    const words: HTMLElement[] = [];
    const chars: HTMLElement[] = [];
    const replaced: Array<{ text: Text; nodes: Node[] }> = [];
    for (const text of texts) {
      const frag = document.createDocumentFragment();
      let host: Node = frag;
      if (mode === 'chars') {
        // Screen readers get the text once instead of letter by letter.
        const copy = span('uncoder-sr-only');
        copy.textContent = text.nodeValue;
        const hidden = document.createElement('span');
        hidden.setAttribute('aria-hidden', 'true');
        frag.append(copy, hidden);
        host = hidden;
      }
      for (const part of (text.nodeValue || '').split(/(\s+)/)) {
        if (!part) continue;
        if (/^\s+$/.test(part)) {
          host.appendChild(document.createTextNode(part));
          continue;
        }
        const word = span(mask ? 'uncoder-split-w uncoder-split-mask' : 'uncoder-split-w');
        const inner = mask ? word.appendChild(span('uncoder-split-in')) : word;
        if (mode === 'chars') {
          for (const ch of Array.from(part)) {
            const letter = inner.appendChild(span('uncoder-split-c'));
            letter.textContent = ch;
            chars.push(letter);
          }
        } else inner.textContent = part;
        words.push(inner);
        host.appendChild(word);
      }
      replaced.push({ text, nodes: [...frag.childNodes] });
      text.replaceWith(frag);
    }

    // Words on the same line share a unit (grouped by the top of their unmoved box).
    const lines = (): Units => {
      const out: Units = [];
      let top = -Infinity;
      for (const w of words) {
        const box = w.classList.contains('uncoder-split-in') ? w.parentElement! : w;
        const t = box.getBoundingClientRect().top;
        if (Math.abs(t - top) > 2 || !out.length) {
          out.push([]);
          top = t;
        }
        out[out.length - 1].push(w);
      }
      return out;
    };
    return {
      units: mode === 'lines' ? lines() : (mode === 'chars' ? chars : words).map((n) => [n]),
      relayout: mode === 'lines' ? lines : undefined,
      split: true,
      restore: () => {
        for (const { text, nodes } of replaced) {
          const first = nodes.find((n) => n.parentNode);
          if (!first) continue;
          first.parentNode!.insertBefore(text, first);
          nodes.forEach((n) => n.parentNode?.removeChild(n));
        }
      },
    };
  }

  function targetsOf(el: HTMLElement, def: AnimDef): Targets {
    const target = def.target ?? 'self';
    if (target === 'words' || target === 'chars' || target === 'lines') return splitText(el, target, !!def.mask);
    const none = { restore: () => {}, split: false };
    if (target === 'children') {
      // A container's child elements; for a widget (the element is its block), its items (list items, cards…).
      const inner = el.querySelector<HTMLElement>(':scope > .uncoder-container__inner');
      const kids = el.classList.contains('uncoder-container')
        ? [...(inner ?? el).children].filter((c) => elementId(c) !== null)
        : [...el.children].filter((c) => !c.matches('script,style,template'));
      return { units: kids.map((k) => [k as HTMLElement]), ...none };
    }
    return { units: [[el]], ...none };
  }

  // ---------------------------------------------------------------- Groups

  function build(units: Units, def: AnimDef, delay: number): Group {
    const compiled = compile(def, units.length, { linear: supportsLinear, additive });
    const anims: Animation[] = [];
    units.forEach((unit, i) => {
      for (const node of unit) {
        const a = node.animate(compiled.keyframes[i], {
          duration: compiled.duration,
          iterations: compiled.iterations,
          direction: compiled.direction,
          fill: 'both',
          delay,
          composite: additive ? 'add' : 'replace',
        });
        a.pause();
        anims.push(a);
      }
    });
    return { anims, compiled, end: delay + compiled.duration * compiled.iterations };
  }

  /** Plays forward (1) or backward (-1) from wherever the group is; a no-op when already there. */
  function go(g: Group, dir: 1 | -1) {
    for (const a of g.anims) {
      const t = Number(a.currentTime ?? 0);
      if ((dir > 0 && t >= g.end) || (dir < 0 && t <= 0)) continue;
      a.playbackRate = dir;
      a.play();
    }
  }
  function seek(g: Group, p: number) {
    const t = p * g.compiled.duration;
    for (const a of g.anims) a.currentTime = t;
  }
  function rewind(g: Group) {
    for (const a of g.anims) {
      a.pause();
      a.playbackRate = 1;
      a.currentTime = 0;
    }
  }
  const drop = (g: Group) => g.anims.forEach((a) => a.cancel());
  const finished = (g: Group) =>
    Promise.all(g.anims.map((a) => a.finished)).then(
      () => true,
      () => false,
    );
  const fontsReady = () => (document.fonts?.ready ?? Promise.resolve()).then(() => undefined);

  // ---------------------------------------------------------------- Scroll scrubbing

  interface Scrub {
    el: HTMLElement;
    group: () => Group;
    start: number;
    end: number;
    smooth: number;
    top: number;
    height: number;
    current: number;
    near: boolean;
    /** Inside a pinned (sticky) box: progress runs while the box is pinned, through its parent's height. */
    pinned: boolean;
  }
  const scrubs = new Set<Scrub>();
  let frame = 0;
  let last = 0;
  let listening = false;

  // A sticky box (not the site header) around the element: it stays put while pinned, so the scroll is measured
  // on its parent, the track it is pinned in (a tall section with a sticky panel = a scroll scene).
  const pinTrack = (el: HTMLElement): HTMLElement | null => {
    const box = el.closest<HTMLElement>('.uncoder-sticky');
    if (!box || box.closest('.uncoder-location--header, .uncoder--header') || getComputedStyle(box).position !== 'sticky') return null;
    return box.parentElement;
  };
  function measure(s: Scrub) {
    // Layout position (offsetTop chain), never the moved box, so the effect cannot feed its own progress.
    const track = pinTrack(s.el);
    s.pinned = !!track && track.offsetHeight > window.innerHeight;
    const target = s.pinned && track ? track : s.el;
    let top = 0;
    for (let node: HTMLElement | null = target; node; node = node.offsetParent as HTMLElement | null) top += node.offsetTop;
    s.top = top;
    s.height = target.offsetHeight;
  }
  function progress(s: Scrub): number {
    const vh = window.innerHeight;
    // Pinned: 0 when the track's top reaches the top of the screen, 1 when its bottom reaches the bottom.
    const raw = s.pinned ? (window.scrollY - s.top) / Math.max(1, s.height - vh) : (window.scrollY + vh - s.top) / (vh + s.height);
    return clamp((raw * 100 - s.start) / Math.max(1, s.end - s.start), 0, 1);
  }
  function tick(now: number) {
    frame = 0;
    const dt = last ? Math.min(100, now - last) : 16;
    last = now;
    let again = false;
    for (const s of scrubs) {
      if (!s.near) continue;
      const target = progress(s);
      const k = s.smooth > 0 ? 1 - Math.exp(-dt / (s.smooth * 250)) : 1;
      let p = s.current + (target - s.current) * k;
      if (Math.abs(target - p) < 0.0005) p = target;
      else again = true;
      if (p !== s.current) {
        s.current = p;
        seek(s.group(), p);
      }
    }
    if (again) schedule();
    else last = 0;
  }
  function schedule() {
    if (!frame) frame = requestAnimationFrame(tick);
  }
  const remeasure = () => {
    scrubs.forEach(measure);
    schedule();
  };
  const bodyObserver = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(remeasure) : null;
  function listen(on: boolean) {
    if (on === listening) return;
    listening = on;
    const method = on ? 'addEventListener' : 'removeEventListener';
    window[method]('scroll', schedule as EventListener, { passive: true } as AddEventListenerOptions);
    window[method]('resize', remeasure as EventListener, { passive: true } as AddEventListenerOptions);
    window[method]('load', remeasure as EventListener);
    if (on) bodyObserver?.observe(document.body);
    else bodyObserver?.disconnect();
  }

  // ---------------------------------------------------------------- Triggers

  function start(el: HTMLElement, def: AnimDef, keepSplit: boolean): () => void {
    const trigger = def.trigger;
    const scrub = trigger === 'scroll';
    const oneShot = scrub || trigger === 'hover' || trigger === 'click';
    const plays: AnimDef = oneShot ? { ...def, repeat: 0, yoyo: false } : def;
    const targets = targetsOf(el, def);
    if (!targets.units.length) {
      targets.restore();
      return () => {};
    }
    const delay = scrub ? 0 : clamp(def.delay ?? 0, 0, 20000);
    let g = build(targets.units, plays, delay);
    const offs: Array<() => void> = [];
    let gone = false;
    const stop = () => {
      if (gone) return;
      gone = true;
      offs.forEach((off) => off());
      drop(g);
      if (!keepSplit) targets.restore();
    };
    // Fresh line breaks before the first play (the group is still at its start, so nothing jumps).
    const relayout = () => {
      if (!targets.relayout) return;
      drop(g);
      g = build(targets.relayout(), plays, delay);
    };
    // An entrance that ends as styled leaves nothing behind once it has played.
    const settle = () => finished(g).then((ok) => ok && g.compiled.endsNatural && stop());
    const on = (type: string, fn: (e: Event) => void) => {
      el.addEventListener(type, fn);
      offs.push(() => el.removeEventListener(type, fn));
    };

    if (trigger === 'load') {
      fontsReady().then(() => {
        if (gone) return;
        relayout();
        go(g, 1);
        settle();
      });
    } else if (trigger === 'enter') {
      const replay = def.replay ?? 'once';
      let played = false;
      const io = new IntersectionObserver(
        (entries) => {
          const inView = entries[entries.length - 1].isIntersecting;
          if (inView && (!played || replay !== 'once')) {
            if (!played) relayout();
            played = true;
            go(g, 1);
            if (replay === 'once') {
              io.disconnect();
              settle();
            }
          } else if (!inView && played && replay === 'reverse') go(g, -1);
        },
        { rootMargin: `0px 0px -${clamp(def.offset ?? 10, 0, 50)}% 0px` },
      );
      io.observe(el);
      offs.push(() => io.disconnect());
      if (replay === 'every') {
        // Starts over once it is fully out of view.
        const out = new IntersectionObserver((entries) => {
          if (played && !entries[entries.length - 1].isIntersecting) rewind(g);
        });
        out.observe(el);
        offs.push(() => out.disconnect());
      }
    } else if (scrub) {
      const s: Scrub = { el, group: () => g, start: clamp(def.start ?? 0, 0, 100), end: clamp(def.end ?? 100, 0, 100), smooth: clamp(def.smooth ?? 0, 0, 10), top: 0, height: 0, current: 0, near: true, pinned: false };
      if (s.end <= s.start) s.end = Math.min(100, s.start + 1);
      measure(s);
      s.current = progress(s);
      seek(g, s.current);
      scrubs.add(s);
      listen(true);
      const io = new IntersectionObserver(
        (entries) => {
          s.near = entries[entries.length - 1].isIntersecting;
          if (s.near) schedule();
        },
        { rootMargin: '25% 0px' },
      );
      io.observe(el);
      if (targets.relayout)
        fontsReady().then(() => {
          if (gone) return;
          relayout();
          seek(g, s.current);
        });
      offs.push(() => {
        io.disconnect();
        scrubs.delete(s);
        if (!scrubs.size) listen(false);
      });
    } else if (trigger === 'hover') {
      // A hover that ends as styled (a jiggle, a spin) replays on each visit; otherwise leaving plays it back.
      const enter = (e: Event) => {
        if ((e as PointerEvent).pointerType === 'touch') return;
        if (!g.compiled.endsNatural) go(g, 1);
        else if (g.anims.every((a) => a.playState !== 'running')) {
          rewind(g);
          go(g, 1);
        }
      };
      const leave = (e: Event) => {
        if ((e as PointerEvent).pointerType === 'touch') return;
        if (!g.compiled.endsNatural) go(g, -1);
      };
      on('pointerenter', enter);
      on('pointerleave', leave);
      on('focusin', enter);
      on('focusout', leave);
    } else if (trigger === 'click') {
      let forward = false;
      on('click', () => {
        if (def.toggle) {
          forward = !forward;
          go(g, forward ? 1 : -1);
        } else {
          rewind(g);
          go(g, 1);
        }
      });
    } else if (trigger === 'loop') {
      // Loops rest while they are off screen.
      const io = new IntersectionObserver(
        (entries) => {
          const visible = entries[entries.length - 1].isIntersecting;
          for (const a of g.anims) {
            if (visible) a.play();
            else a.pause();
          }
        },
        { rootMargin: '100px' },
      );
      io.observe(el);
      offs.push(() => io.disconnect());
    }
    return stop;
  }

  const read = (el: HTMLElement): AnimDef[] => {
    try {
      const defs = JSON.parse(el.getAttribute('data-uncoder-animate') || '[]');
      return Array.isArray(defs) ? defs.filter((d) => d && Array.isArray(d.steps)) : [];
    } catch {
      return [];
    }
  };

  api.register('animate', (el) => {
    const ready = () => el.classList.add('uncoder-animate--ready');
    if (api.editor || api.reducedMotion() || typeof el.animate !== 'function' || !('IntersectionObserver' in window)) {
      ready();
      return;
    }
    const current = device();
    const defs = read(el).filter((d) => !d.devices || d.devices.includes(current));
    // Two definitions that both split the text share the spans, so neither may undo the split.
    const keepSplit = defs.filter((d) => ['words', 'chars', 'lines'].includes(d.target ?? 'self')).length > 1;
    const stops = defs.map((d) => {
      try {
        return start(el, d, keepSplit);
      } catch {
        return () => {};
      }
    });
    ready();
    return () => stops.forEach((s) => s());
  });

  // ---------------------------------------------------------------- Editor preview

  const previews = new WeakMap<HTMLElement, () => void>();

  /** Plays one definition on an element once, then puts everything back (used by the editor). */
  function preview(el: HTMLElement, def: AnimDef): () => void {
    previews.get(el)?.();
    const targets = targetsOf(el, def);
    if (!targets.units.length || typeof el.animate !== 'function') {
      targets.restore();
      return () => {};
    }
    const scrub = def.trigger === 'scroll';
    const interactive = def.trigger === 'hover' || def.trigger === 'click';
    const endless = def.trigger === 'loop' || (def.repeat ?? 0) < 0;
    const plays: AnimDef = scrub || interactive ? { ...def, repeat: 0, yoyo: false } : endless ? { ...def, trigger: 'enter', repeat: def.yoyo ? 3 : 1 } : def;
    const g = build(targets.units, plays, scrub ? 0 : clamp(def.delay ?? 0, 0, 1500));
    let raf = 0;
    const timers: number[] = [];
    let stopped = false;
    const stop = () => {
      if (stopped) return;
      stopped = true;
      cancelAnimationFrame(raf);
      timers.forEach(clearTimeout);
      drop(g);
      targets.restore();
      previews.delete(el);
    };
    const later = (fn: () => void, ms: number) => timers.push(window.setTimeout(fn, ms));
    previews.set(el, stop);
    later(stop, 15000);

    if (scrub) {
      // Sweeps the scroll range once, as if the page scrolled past.
      const t0 = performance.now();
      const sweep = (now: number) => {
        const k = Math.min(1, (now - t0) / 2000);
        seek(g, (1 - Math.cos(k * Math.PI)) / 2);
        if (k < 1) raf = requestAnimationFrame(sweep);
        else later(stop, 500);
      };
      raf = requestAnimationFrame(sweep);
    } else {
      go(g, 1);
      finished(g).then((ok) => {
        if (!ok || stopped) return;
        if (interactive && !g.compiled.endsNatural) {
          later(() => {
            go(g, -1);
            finished(g).then(() => later(stop, 300));
          }, 600);
        } else later(stop, 450);
      });
    }
    return stop;
  }

  (api as typeof api & { animate?: { preview: typeof preview } }).animate = { preview };
})();
