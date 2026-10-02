// Sticky elements: CSS position:sticky does the work; this adds .uncoder-sticky--stuck while stuck
// (for shrinking headers, shadows…) and, for a header, exposes its height as --uncoder-sticky-h.
// No marker element is inserted: an extra child would take part in its row's layout (a "space between" row
// would push the sticky element off its place, and the row gap would be added once more).
window.UncoderWB.register('sticky', (el) => {
  const bottom = el.classList.contains('uncoder-sticky--bottom');
  // Only a header's height offsets anchor scrolling; a sticky sidebar or label must not overwrite it.
  const header = !!el.closest('.uncoder--header, .uncoder-location--header');
  const sticking = () => getComputedStyle(el).position === 'sticky';
  // Off on this device ("Sticky on"): the element scrolls away, so it takes no room at the top.
  const setHeight = () => header && document.documentElement.style.setProperty('--uncoder-sticky-h', sticking() ? `${el.offsetHeight}px` : '0px');

  // Stuck = the element sits at its offset with its edge just past the observed area (rootMargin 1px inside it).
  let io: IntersectionObserver | null = null;
  const observe = () => {
    io?.disconnect();
    const cs = getComputedStyle(el);
    const offset = Math.max(0, parseFloat(bottom ? cs.bottom : cs.top) || 0) + 1;
    io = new IntersectionObserver(
      ([entry]) => {
        const r = entry.boundingClientRect;
        const stuck = sticking() && entry.intersectionRatio < 1 && (bottom ? r.bottom >= window.innerHeight - offset : r.top <= offset);
        el.classList.toggle('uncoder-sticky--stuck', stuck);
      },
      { threshold: [1], rootMargin: bottom ? `0px 0px -${offset}px 0px` : `-${offset}px 0px 0px 0px` }
    );
    io.observe(el);
  };

  setHeight();
  observe();
  const ro = 'ResizeObserver' in window ? new ResizeObserver(setHeight) : null;
  ro?.observe(el);
  let raf = 0;
  const onResize = () => (raf ||= requestAnimationFrame(() => ((raf = 0), setHeight(), observe())));
  window.addEventListener('resize', onResize);
  return () => {
    io?.disconnect();
    ro?.disconnect();
    window.removeEventListener('resize', onResize);
    cancelAnimationFrame(raf);
  };
});
