// Timeline entrance: events below the fold are hidden when the module starts and revealed as they
// scroll into view. Events already on screen are never hidden, so there is no flash, and nothing
// is hidden at all without JavaScript, in the editor or with reduced motion.
window.UncoderWB.register('timeline', (el, api) => {
  const list = el.querySelector<HTMLElement>('.uncoder-timeline--reveal');
  if (!list || api.editor || api.reducedMotion() || !('IntersectionObserver' in window)) return;

  const HIDDEN = 'uncoder-timeline__item--hidden';
  const fold = window.innerHeight * 0.92;
  const pending = Array.from(list.querySelectorAll<HTMLElement>('.uncoder-timeline__item')).filter(
    (item) => item.getBoundingClientRect().top > fold,
  );
  if (!pending.length) return;

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
  };
});
