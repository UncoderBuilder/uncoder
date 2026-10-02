// Container background slideshow (Renderer::background_layer). Only the first image loads with the
// page; each next one is fetched a slide ahead. Pauses while off screen or in a background tab.
window.UncoderWB.register('bg-slideshow', (el, api) => {
  const slides = Array.from(el.querySelectorAll<HTMLElement>('.uncoder-bg-slide'));
  if (slides.length < 2 || api.editor) return;
  const s = api.settings<{ duration?: number; transition?: number }>(el);
  const speed = Math.max(100, Number(s.transition) || 1000);
  const interval = Math.max(speed + 200, Number(s.duration) || 5000);

  const load = (slide: HTMLElement | undefined) => {
    const src = slide?.dataset.bg;
    if (!slide || !src) return;
    slide.style.backgroundImage = `url("${src.replace(/["\\\n]/g, '')}")`;
    delete slide.dataset.bg;
  };

  let index = Math.max(0, slides.findIndex((slide) => slide.classList.contains('is-active')));
  let timer = 0;
  let settle = 0;
  load(slides[(index + 1) % slides.length]);

  const next = () => {
    const prev = slides[index];
    index = (index + 1) % slides.length;
    const cur = slides[index];
    load(cur);
    load(slides[(index + 1) % slides.length]);
    window.clearTimeout(settle);
    slides.forEach((slide) => slide !== prev && slide.classList.remove('is-prev'));
    prev.classList.replace('is-active', 'is-prev');
    cur.classList.add('is-active');
    // Once the new slide covers it, the previous one resets underneath (no visible jump).
    settle = window.setTimeout(() => prev.classList.remove('is-prev'), speed);
  };

  let inView = true;
  const run = () => {
    const on = inView && document.visibilityState === 'visible';
    if (on && !timer) timer = window.setInterval(next, interval);
    if (!on && timer) {
      window.clearInterval(timer);
      timer = 0;
    }
  };
  const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
    inView = entries.some((e) => e.isIntersecting);
    run();
  }) : null;
  io?.observe(el);
  document.addEventListener('visibilitychange', run);
  run();

  return () => {
    io?.disconnect();
    document.removeEventListener('visibilitychange', run);
    window.clearInterval(timer);
    window.clearTimeout(settle);
  };
});
