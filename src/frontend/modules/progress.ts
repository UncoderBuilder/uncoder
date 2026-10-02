// Progress Bar widget: the bar renders at its final width (no-JS, SEO). With animation enabled the
// module collapses it, then fills it and counts the percentage up once it scrolls into view.
window.UncoderWB.register(
  'progress',
  (el, api) => {
    const box = (el.matches('.uncoder-progress[data-animate]') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-progress[data-animate]'));
    if (!box || api.editor || api.reducedMotion()) return;

    const value = Math.max(0, Math.min(100, parseInt(box.dataset.value || '0', 10) || 0));
    const duration = Math.max(0, parseInt(box.dataset.duration || '1200', 10) || 0);
    const labels = Array.from(box.querySelectorAll<HTMLElement>('.uncoder-progress__percent'));
    const finals = labels.map((label) => label.textContent || '');
    // Keeps the site's number formatting: only the digits of the rendered label are replaced.
    const setLabels = (n: number) => labels.forEach((label, i) => (label.textContent = finals[i].replace(/\d+/, String(n))));

    let raf = 0;
    let timer = 0;
    let stopped = false;
    box.classList.add('uncoder-progress--pending');
    setLabels(0);

    const finish = () => {
      stopped = true;
      cancelAnimationFrame(raf);
      window.clearTimeout(timer);
      box.classList.remove('uncoder-progress--pending');
      labels.forEach((label, i) => (label.textContent = finals[i]));
    };

    api.onVisible(box, () => {
      if (stopped) return;
      void box.offsetWidth; // Commit the collapsed state so the width transition runs.
      box.classList.remove('uncoder-progress--pending');
      if (!labels.length || duration === 0) {
        finish();
        return;
      }
      const start = performance.now();
      const step = (now: number) => {
        if (stopped) return;
        const t = Math.min(1, (now - start) / duration);
        if (t < 1) {
          setLabels(Math.round(value * (1 - Math.pow(1 - t, 3))));
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
