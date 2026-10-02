// Sticky elements: CSS position:sticky does the work; this adds .uncoder-sticky--stuck while stuck
// (for shrinking headers, shadows…) and exposes the header height as --uncoder-sticky-h.
window.UncoderWB.register('sticky', (el) => {
  const sentinel = document.createElement('div');
  sentinel.setAttribute('aria-hidden', 'true');
  sentinel.style.cssText = 'position:relative;height:1px;margin-bottom:-1px;pointer-events:none;';
  el.parentNode?.insertBefore(sentinel, el);
  // Off on this device ("Sticky on"): the element scrolls away, so it takes no room at the top.
  const setHeight = () => document.documentElement.style.setProperty('--uncoder-sticky-h', getComputedStyle(el).position === 'sticky' ? `${el.offsetHeight}px` : '0px');
  setHeight();
  const ro = 'ResizeObserver' in window ? new ResizeObserver(setHeight) : null;
  ro?.observe(el);
  let raf = 0;
  const onResize = () => (raf ||= requestAnimationFrame(() => ((raf = 0), setHeight())));
  window.addEventListener('resize', onResize);
  const io = new IntersectionObserver(([entry]) => el.classList.toggle('uncoder-sticky--stuck', !entry.isIntersecting), { threshold: 0 });
  io.observe(sentinel);
  return () => {
    io.disconnect();
    ro?.disconnect();
    window.removeEventListener('resize', onResize);
    cancelAnimationFrame(raf);
    sentinel.remove();
  };
});
