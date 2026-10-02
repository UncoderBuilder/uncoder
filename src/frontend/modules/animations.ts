// Entrance animations: elements with .uncoder-anim get .uncoder-anim--in when they scroll into view.
// The wrapper names this module (data-uncoder-js), so elements added later — loop grid "load more",
// filtered results, the editor canvas — are picked up by UncoderWB.init like any other module.
(() => {
  const api = window.UncoderWB;
  const reveal = (el: Element) => el.classList.add('uncoder-anim--in');
  const instant = api.reducedMotion() || api.editor || !('IntersectionObserver' in window);
  const io = instant
    ? null
    : new IntersectionObserver(
        (entries) => {
          for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            reveal(entry.target);
            io!.unobserve(entry.target);
          }
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.05 },
      );
  const watch = (el: Element) => {
    if (el.classList.contains('uncoder-anim--in')) return;
    if (io) io.observe(el);
    else reveal(el);
  };
  // Markup cached before the wrapper named the module.
  const setup = () => document.querySelectorAll('.uncoder-anim:not(.uncoder-anim--in)').forEach(watch);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup);
  else setup();
  api.register('animations', (el) => {
    watch(el);
    return () => io?.unobserve(el);
  });
})();
