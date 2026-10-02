// Reading Progress widget: fills the bar (a scaleX transform) with how far the visitor has scrolled
// through the page, the post content or an element chosen by CSS ID.
window.UncoderWB.register('reading-progress', (el, api) => {
  const box = (el.matches('.uncoder-reading-progress[data-settings]') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-reading-progress[data-settings]'));
  const bar = box?.querySelector<HTMLElement>('.uncoder-reading-progress__bar');
  if (!box || !bar || api.editor) return;
  const s = api.settings<{ track?: string; target?: string }>(box);

  const target = (): Element | null => {
    if (s.track === 'element' && s.target) return document.getElementById(s.target);
    if (s.track === 'content') return document.querySelector('.uncoder-post-content, .entry-content, main article');
    return null;
  };
  let raf = 0;
  const update = () => {
    raf = 0;
    const vh = window.innerHeight;
    const node = target();
    let p: number;
    if (node) {
      // 0 when the top reaches the top of the screen, 1 when the bottom reaches the bottom.
      const r = node.getBoundingClientRect();
      const span = r.height - vh;
      p = span > 0 ? -r.top / span : r.top < 0 ? 1 : 0;
    } else {
      const max = document.documentElement.scrollHeight - vh;
      p = max > 0 ? window.scrollY / max : 0;
    }
    bar.style.transform = `scaleX(${Math.min(1, Math.max(0, p)).toFixed(4)})`;
  };
  const queue = () => (raf ||= requestAnimationFrame(update));
  window.addEventListener('scroll', queue, { passive: true });
  window.addEventListener('resize', queue);
  update();
  return () => {
    cancelAnimationFrame(raf);
    window.removeEventListener('scroll', queue);
    window.removeEventListener('resize', queue);
  };
});
