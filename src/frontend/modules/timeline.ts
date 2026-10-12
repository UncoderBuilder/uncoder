// Timeline entrance: events below the fold are hidden when the module starts and revealed as they
// scroll into view. Events already on screen are never hidden, so there is no flash, and nothing
// is hidden at all without JavaScript, in the editor or with reduced motion.
// Scroll progress (--progress): each segment of the line fills as the middle of the screen passes
// it (--uncoder-tl-p on the item) and the events above that point are marked reached.
// The widget's wrapper is usually merged into the list itself, so the list is looked up on the element too.
const find = (el: HTMLElement, selector: string) => (el.matches(selector) ? el : el.querySelector<HTMLElement>(selector));
window.UncoderWB.register('timeline', (el, api) => {
  const cleanups: Array<() => void> = [];
  const track = find(el, '.uncoder-timeline--progress');
  if (track && !api.editor && !api.reducedMotion()) cleanups.push(progress(track));
  const list = find(el, '.uncoder-timeline--reveal');
  if (!list || api.editor || api.reducedMotion() || !('IntersectionObserver' in window)) return () => cleanups.forEach((f) => f());

  const HIDDEN = 'uncoder-timeline__item--hidden';
  const fold = window.innerHeight * 0.92;
  const pending = Array.from(list.querySelectorAll<HTMLElement>('.uncoder-timeline__item')).filter(
    (item) => item.getBoundingClientRect().top > fold,
  );
  if (!pending.length) return () => cleanups.forEach((f) => f());

  pending.forEach((item) => item.classList.add(HIDDEN));
  const io = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        entry.target.classList.remove(HIDDEN);
        io.unobserve(entry.target);
      }
    },
    { rootMargin: '0px 0px -10% 0px', threshold: 0.15 },
  );
  pending.forEach((item) => io.observe(item));

  return () => {
    io.disconnect();
    pending.forEach((item) => item.classList.remove(HIDDEN));
    cleanups.forEach((f) => f());
  };
});

function progress(list: HTMLElement): () => void {
  const items = Array.from(list.querySelectorAll<HTMLElement>(':scope > .uncoder-timeline__item'));
  const markers = items.map((item) => item.querySelector<HTMLElement>('.uncoder-timeline__marker'));
  let raf = 0;
  const update = () => {
    raf = 0;
    const line = window.innerHeight / 2;
    const ys = markers.map((m, i) => {
      const r = (m ?? items[i]).getBoundingClientRect();
      return r.top + r.height / 2;
    });
    items.forEach((item, i) => {
      item.classList.toggle('is-reached', ys[i] <= line);
      const next = ys[i + 1];
      if (next === undefined) return;
      const p = next > ys[i] ? (line - ys[i]) / (next - ys[i]) : 0;
      item.style.setProperty('--uncoder-tl-p', Math.min(1, Math.max(0, p)).toFixed(4));
    });
  };
  const queue = () => (raf ||= requestAnimationFrame(update));
  list.classList.add('is-tracking');
  update();
  window.addEventListener('scroll', queue, { passive: true });
  window.addEventListener('resize', queue);
  return () => {
    cancelAnimationFrame(raf);
    window.removeEventListener('scroll', queue);
    window.removeEventListener('resize', queue);
    list.classList.remove('is-tracking');
    items.forEach((item) => {
      item.classList.remove('is-reached');
      item.style.removeProperty('--uncoder-tl-p');
    });
  };
}
