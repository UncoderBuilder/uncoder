// Back-to-top button (Design System setting): appears after scrolling down, scrolls up smoothly unless the
// visitor prefers reduced motion, then moves focus to the top of the page for keyboard users.
window.UncoderWB.register('back-to-top', (el, api) => {
  let frame = 0;
  const update = () => {
    frame = 0;
    el.classList.toggle('is-visible', window.scrollY > Math.max(400, window.innerHeight * 0.6));
  };
  const onScroll = () => {
    if (!frame) frame = requestAnimationFrame(update);
  };
  const onClick = (event: MouseEvent) => {
    event.preventDefault();
    window.scrollTo({ top: 0, behavior: api.reducedMotion() ? 'auto' : 'smooth' });
    const first = document.querySelector<HTMLElement>('.uncoder-location--header a[href], a[href], button');
    first?.focus({ preventScroll: true });
  };
  update();
  window.addEventListener('scroll', onScroll, { passive: true });
  el.addEventListener('click', onClick);
  return () => {
    window.removeEventListener('scroll', onScroll);
    el.removeEventListener('click', onClick);
    if (frame) cancelAnimationFrame(frame);
  };
});
