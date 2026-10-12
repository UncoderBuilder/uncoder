// Entrance animations: elements with .uncoder-anim get .uncoder-anim--in when they scroll into view.
// The wrapper names this module (data-uncoder-js), so elements added later — loop grid "load more",
// filtered results, the editor canvas — are picked up by UncoderWB.init like any other module.
(() => {
  const api = window.UncoderWB;
  const reveal = (el: Element) => el.classList.add('uncoder-anim--in');
  const instant = api.reducedMotion() || api.editor || !('IntersectionObserver' in window);
  // What starts each element: itself coming into view, or, for "Drop in", the box it falls into (an element that
  // lands on the bottom edge of the first screen would otherwise wait below the fold, invisible).
  const waiting = new Map<Element, Set<Element>>();
  const io = instant
    ? null
    : new IntersectionObserver(
        (entries) => {
          for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            waiting.get(entry.target)?.forEach(reveal);
            waiting.delete(entry.target);
            io!.unobserve(entry.target);
          }
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.05 },
      );
  const triggerOf = (el: Element) => (el.getAttribute('data-uncoder-anim') === 'drop-in' && el.parentElement ? el.parentElement : el);
  const watch = (el: Element) => {
    if (el.classList.contains('uncoder-anim--in')) return;
    if (!io) return reveal(el);
    const trigger = triggerOf(el);
    const set = waiting.get(trigger) ?? new Set<Element>();
    set.add(el);
    waiting.set(trigger, set);
    io.observe(trigger);
  };
  const unwatch = (el: Element) => {
    const trigger = triggerOf(el);
    const set = waiting.get(trigger);
    set?.delete(el);
    if (set && !set.size) {
      waiting.delete(trigger);
      io?.unobserve(trigger);
    }
  };
  // Markup cached before the wrapper named the module.
  const setup = () => document.querySelectorAll('.uncoder-anim:not(.uncoder-anim--in)').forEach(watch);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setup);
  else setup();
  api.register('animations', (el) => {
    watch(el);
    return () => unwatch(el);
  });
})();
