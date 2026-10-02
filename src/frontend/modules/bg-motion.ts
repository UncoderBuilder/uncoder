// Moving container backgrounds (Renderer::background_layer): parallax, zoom on scroll or following
// the pointer. Moves the oversized inner layer; the container's own background stays underneath as
// the fallback, so nothing changes without JS or for visitors who prefer reduced motion.
window.UncoderWB.register('bg-motion', (el, api) => {
  const layer = el.firstElementChild as HTMLElement | null;
  const box = el.parentElement;
  if (!layer || !box || api.editor || api.reducedMotion()) return;
  const s = api.settings<{ motion?: string; speed?: number }>(el);
  const speed = Math.min(10, Math.max(1, Number(s.speed) || 4));
  const round = (v: number) => Math.round(v * 100) / 100;
  let frame = 0;

  if (s.motion === 'mouse') {
    if (!(window.matchMedia?.('(hover: hover) and (pointer: fine)').matches ?? false)) return;
    const reach = speed * 5; // Matches --uncoder-bgm-extra minus a small safety margin.
    let target: [number, number] = [0, 0];
    let current: [number, number] = [0, 0];
    const step = () => {
      frame = 0;
      current = [current[0] + (target[0] - current[0]) * 0.08, current[1] + (target[1] - current[1]) * 0.08];
      layer.style.translate = `${round(current[0])}px ${round(current[1])}px`;
      if (Math.abs(target[0] - current[0]) > 0.05 || Math.abs(target[1] - current[1]) > 0.05) frame = requestAnimationFrame(step);
    };
    const onMove = (e: PointerEvent) => {
      // The background drifts against the pointer, which reads as depth.
      target = [-((e.clientX / window.innerWidth) * 2 - 1) * reach, -((e.clientY / window.innerHeight) * 2 - 1) * reach];
      if (!frame) frame = requestAnimationFrame(step);
    };
    window.addEventListener('pointermove', onMove, { passive: true });
    return () => {
      window.removeEventListener('pointermove', onMove);
      if (frame) cancelAnimationFrame(frame);
      layer.style.removeProperty('translate');
    };
  }

  const update = () => {
    frame = 0;
    const rect = box.getBoundingClientRect();
    const vh = window.innerHeight;
    // 0 when the section's top reaches the bottom of the viewport, 1 when its bottom passes the top.
    const p = Math.min(1, Math.max(0, (vh - rect.top) / (vh + rect.height)));
    if (s.motion === 'parallax') {
      // The layer is taller than the box by speed × 3% at each end; it drifts down through that slack.
      layer.style.translate = `0 ${round((p - 0.5) * 2 * rect.height * speed * 0.03)}px`;
    } else if (s.motion === 'zoom-in' || s.motion === 'zoom-out') {
      const t = s.motion === 'zoom-in' ? p : 1 - p;
      layer.style.scale = String(Math.round((1 + t * speed * 0.03) * 10000) / 10000);
    }
  };
  const onScroll = () => {
    if (!frame) frame = requestAnimationFrame(update);
  };

  // Listen only while the section is near the viewport.
  let active = false;
  const toggle = (on: boolean) => {
    if (on === active) return;
    active = on;
    if (on) {
      window.addEventListener('scroll', onScroll, { passive: true });
      window.addEventListener('resize', onScroll, { passive: true });
      onScroll();
    } else {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onScroll);
    }
  };
  const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => toggle(entries.some((e) => e.isIntersecting)), { rootMargin: '25% 0px' }) : null;
  if (io) io.observe(box);
  else toggle(true);
  update();

  return () => {
    io?.disconnect();
    toggle(false);
    if (frame) cancelAnimationFrame(frame);
    layer.style.removeProperty('translate');
    layer.style.removeProperty('scale');
  };
});
