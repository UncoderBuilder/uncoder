// Animated Headline. Highlight: measures each SVG path and draws it when the headline scrolls into
// view (optionally looping). Rotating: cycles the words with typing / clip / flip / slide / fade.
// Nothing runs with prefers-reduced-motion: the server markup already shows the drawn shape and
// the first word.
interface HeadlineSettings {
  style?: 'highlight' | 'rotating';
  effect?: 'typing' | 'clip' | 'flip' | 'slide' | 'fade';
  hold?: number;
  draw?: number;
  loop?: boolean;
}

window.UncoderWB.register('animated-headline', (el, api) => {
  const root = (el.matches('.uncoder-ah') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-ah'));
  if (!root || api.reducedMotion()) return;
  const s = api.settings<HeadlineSettings>(el);
  const hold = Math.max(500, Number(s.hold) || 2500);
  const loop = s.loop !== false;
  const timers = new Set<number>();
  let alive = true;

  const wait = (ms: number, fn: () => void) => {
    const id = window.setTimeout(() => {
      timers.delete(id);
      if (alive) fn();
    }, ms);
    timers.add(id);
  };

  const cleanups: Array<() => void> = [];
  const added: string[] = [];
  const addClass = (name: string) => {
    root.classList.add(name);
    added.push(name);
  };

  if (s.style !== 'rotating') {
    /* ---------------------------------------------------------------- Highlight */
    const paths = Array.from(root.querySelectorAll<SVGPathElement>('.uncoder-ah__svg path'));
    if (!paths.length) return;
    paths.forEach((p) => {
      let len = 1000;
      try {
        len = Math.ceil(p.getTotalLength()) + 2;
      } catch {
        /* not rendered (display:none): keep the fallback length */
      }
      p.style.setProperty('--uncoder-ah-len', String(len));
    });
    const draw = Math.max(100, Number(s.draw) || 1200);
    const cycle = () => {
      root.classList.add('uncoder-ah--drawn');
      if (!loop) return;
      wait(draw * (paths.length > 1 ? 1.45 : 1) + hold, () => {
        root.classList.add('uncoder-ah--fading');
        wait(450, () => {
          root.classList.remove('uncoder-ah--drawn', 'uncoder-ah--fading');
          wait(60, cycle);
        });
      });
    };
    addClass('uncoder-ah--js');
    api.onVisible(root, () => alive && cycle());
    cleanups.push(() => {
      root.classList.remove('uncoder-ah--drawn', 'uncoder-ah--fading');
      paths.forEach((p) => p.style.removeProperty('--uncoder-ah-len'));
    });
  } else {
    /* ---------------------------------------------------------------- Rotating */
    const box = root.querySelector<HTMLElement>('.uncoder-ah__words');
    const words = Array.from(root.querySelectorAll<HTMLElement>('.uncoder-ah__word'));
    if (!box || words.length < 2) return;
    const effect = s.effect || 'slide';
    const texts = words.map((w) => w.textContent || '');
    let index = Math.max(0, words.findIndex((w) => w.classList.contains('uncoder-ah__word--active')));
    let widths: number[] = [];

    addClass('uncoder-ah--ready');

    const measure = () => {
      if (effect === 'typing') return;
      widths = words.map((w) => w.getBoundingClientRect().width);
      box.style.setProperty('--uncoder-ah-w', `${Math.ceil(widths[index] || 0)}px`);
    };
    const setWidth = (i: number) => box.style.setProperty('--uncoder-ah-w', `${Math.ceil(widths[i] || 0)}px`);
    measure();
    const ro = 'ResizeObserver' in window ? new ResizeObserver(() => measure()) : null;
    ro?.observe(root);
    document.fonts?.ready.then(() => alive && measure());

    const activate = (next: number) => {
      const current = words[index];
      current.classList.remove('uncoder-ah__word--active');
      current.classList.add('uncoder-ah__word--out');
      words[next].classList.remove('uncoder-ah__word--out');
      words[next].classList.add('uncoder-ah__word--active');
      wait(600, () => current.classList.remove('uncoder-ah__word--out'));
      index = next;
    };

    const nextIndex = (): number | null => {
      const next = index + 1;
      if (next < words.length) return next;
      return loop ? 0 : null;
    };

    const typeWord = (i: number, done: () => void) => {
      const word = words[i];
      const text = texts[i];
      let n = 0;
      const step = () => {
        n++;
        word.textContent = text.slice(0, n);
        if (n < text.length) wait(70, step);
        else done();
      };
      word.textContent = '';
      wait(120, step);
    };

    const eraseWord = (i: number, done: () => void) => {
      const word = words[i];
      const step = () => {
        const t = word.textContent || '';
        if (!t.length) {
          done();
          return;
        }
        word.textContent = t.slice(0, -1);
        wait(35, step);
      };
      step();
    };

    const rotate = () => {
      const next = nextIndex();
      if (next === null) return;
      if (effect === 'typing') {
        eraseWord(index, () => {
          words[index].classList.remove('uncoder-ah__word--active');
          words[index].textContent = texts[index];
          words[next].classList.add('uncoder-ah__word--active');
          index = next;
          typeWord(next, () => wait(hold, rotate));
        });
      } else if (effect === 'clip') {
        box.style.setProperty('--uncoder-ah-w', '0px');
        wait(650, () => {
          activate(next);
          setWidth(next);
          wait(650 + hold, rotate);
        });
      } else {
        activate(next);
        setWidth(next);
        wait(hold, rotate);
      }
    };

    api.onVisible(root, () => alive && wait(hold, rotate));
    cleanups.push(() => {
      ro?.disconnect();
      box.style.removeProperty('--uncoder-ah-w');
      words.forEach((w, i) => {
        w.textContent = texts[i];
        w.classList.toggle('uncoder-ah__word--active', i === 0);
        w.classList.remove('uncoder-ah__word--out');
      });
    });
  }

  return () => {
    alive = false;
    timers.forEach((id) => window.clearTimeout(id));
    timers.clear();
    cleanups.forEach((fn) => fn());
    added.forEach((name) => root.classList.remove(name));
  };
});
