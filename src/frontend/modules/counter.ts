// Counter widget: counts from data-from to data-to once the number scrolls into view.
// The final value is already in the HTML (no-JS, SEO); this only animates it. Skipped in the
// editor and when the visitor prefers reduced motion.
window.UncoderWB.register(
  'counter',
  (el, api) => {
    const num = el.querySelector<HTMLElement>('.uncoder-counter__number');
    if (!num || api.editor || api.reducedMotion()) return;

    const d = num.dataset;
    const from = parseFloat(d.from || '0');
    const to = parseFloat(d.to || '0');
    const duration = Math.max(0, parseInt(d.duration || '2000', 10) || 0);
    if (!isFinite(from) || !isFinite(to) || from === to || duration === 0) return;

    const decimals = Math.min(3, Math.max(0, parseInt(d.decimals || '0', 10) || 0));
    const thousands = d.thousands ?? '';
    const point = d.point || '.';
    const final = num.textContent || '';

    // Mirrors PHP number_format( $n, $decimals, $point, $thousands ).
    const format = (value: number): string => {
      const fixed = Math.abs(value).toFixed(decimals);
      const [int, frac] = fixed.split('.');
      const grouped = thousands ? int.replace(/\B(?=(\d{3})+(?!\d))/g, thousands) : int;
      return (value < 0 && Number(fixed) !== 0 ? '-' : '') + grouped + (frac ? point + frac : '');
    };
    const easeOut = (t: number) => 1 - Math.pow(1 - t, 3);

    let raf = 0;
    let timer = 0;
    let stopped = false;
    num.textContent = format(from);

    const finish = () => {
      stopped = true;
      cancelAnimationFrame(raf);
      window.clearTimeout(timer);
      num.textContent = final;
    };

    api.onVisible(el, () => {
      if (stopped) return;
      const start = performance.now();
      const step = (now: number) => {
        if (stopped) return;
        const progress = Math.min(1, (now - start) / duration);
        if (progress < 1) {
          num.textContent = format(from + (to - from) * easeOut(progress));
          raf = requestAnimationFrame(step);
        } else {
          finish();
        }
      };
      raf = requestAnimationFrame(step);
      // Frames can be throttled (background tab, low-power mode): always land on the exact final text.
      timer = window.setTimeout(finish, duration + 250);
    });

    return finish;
  },
  { lazy: true },
);
