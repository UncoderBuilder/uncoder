// Image "hover reveal": moving the pointer over the image paints a second image in under it with a soft round
// brush; each stroke fades out after `fade` ms. A 2D canvas over the <img> draws the second image through a mask the
// pointer paints (no WebGL). The first image stays the real image: the canvas is decoration (aria-hidden), takes no
// pointer events and stops drawing as soon as every stroke has faded.
window.UncoderWB.register('image-reveal', (el, api) => {
  const figure = (el.matches('figure') ? el : el.querySelector('figure')) as HTMLElement | null;
  const img = figure?.querySelector<HTMLImageElement>('img');
  const s = api.settings<{ src?: string; size?: number; fade?: number }>(el);
  if (!figure || !img || !s.src) return;

  const size = Math.max(20, Number(s.size) || 110);
  const fade = Math.max(200, Number(s.fade) || 1400);
  const canvas = document.createElement('canvas');
  canvas.className = 'uncoder-image__reveal';
  canvas.setAttribute('aria-hidden', 'true');
  Object.assign(canvas.style, { position: 'absolute', pointerEvents: 'none', zIndex: '1', left: '0', top: '0' });
  const ctx = canvas.getContext('2d');
  const mask = document.createElement('canvas');
  const mctx = mask.getContext('2d');
  if (!ctx || !mctx) return;
  if (getComputedStyle(figure).position === 'static') figure.style.position = 'relative';
  figure.appendChild(canvas);

  const reveal = new Image();
  reveal.decoding = 'async';
  let ready = false;
  reveal.onload = () => {
    ready = true;
  };

  let dpr = 1;
  let w = 0;
  let h = 0;
  const fit = () => {
    // the canvas lies exactly over the <img> (which may be smaller than the figure)
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    w = img.offsetWidth;
    h = img.offsetHeight;
    Object.assign(canvas.style, { left: `${img.offsetLeft}px`, top: `${img.offsetTop}px`, width: `${w}px`, height: `${h}px`, borderRadius: getComputedStyle(img).borderRadius });
    for (const c of [canvas, mask]) {
      c.width = Math.max(1, Math.round(w * dpr));
      c.height = Math.max(1, Math.round(h * dpr));
    }
  };

  /** The second image drawn the way the first is laid out (object-fit cover / contain / fill, object-position). */
  const drawReveal = () => {
    const cs = getComputedStyle(img);
    const iw = reveal.naturalWidth;
    const ih = reveal.naturalHeight;
    const W = canvas.width;
    const H = canvas.height;
    let dw = W;
    let dh = H;
    if (cs.objectFit === 'cover' || cs.objectFit === 'contain') {
      const k = cs.objectFit === 'cover' ? Math.max(W / iw, H / ih) : Math.min(W / iw, H / ih);
      dw = iw * k;
      dh = ih * k;
    }
    const [px, py] = cs.objectPosition.split(' ').map((v) => (v.endsWith('%') ? parseFloat(v) / 100 : 0.5));
    ctx.drawImage(reveal, (W - dw) * (px ?? 0.5), (H - dh) * (py ?? 0.5), dw, dh);
  };

  let raf = 0;
  let last: { x: number; y: number } | null = null;
  let lastMove = 0;
  const points: Array<{ x: number; y: number }> = [];

  // a solid round brush with a short soft rim; each dab a little larger or smaller, for a less mechanical edge
  const dab = (x: number, y: number) => {
    const r = (size / 2) * dpr * (0.85 + Math.random() * 0.3);
    const g = mctx.createRadialGradient(x, y, 0, x, y, r);
    g.addColorStop(0, 'rgba(0,0,0,1)');
    g.addColorStop(0.7, 'rgba(0,0,0,1)');
    g.addColorStop(1, 'rgba(0,0,0,0)');
    mctx.fillStyle = g;
    mctx.beginPath();
    mctx.arc(x, y, r, 0, Math.PI * 2);
    mctx.fill();
  };

  let prev = 0;
  const frame = (now: number) => {
    raf = 0;
    const dt = prev ? Math.min(64, now - prev) : 16;
    prev = now;
    // fade what is painted: a stroke is gone `fade` ms after the pointer leaves it
    mctx.globalCompositeOperation = 'destination-out';
    mctx.fillStyle = `rgba(0,0,0,${Math.min(1, dt / fade)})`;
    mctx.fillRect(0, 0, mask.width, mask.height);
    mctx.globalCompositeOperation = 'source-over';
    // paint the new pointer positions, filling the gaps of a fast stroke
    for (const p of points.splice(0)) {
      if (last) {
        const dx = p.x - last.x;
        const dy = p.y - last.y;
        const steps = Math.max(1, Math.ceil(Math.hypot(dx, dy) / ((size * dpr) / 6)));
        for (let i = 1; i <= steps; i++) dab(last.x + (dx * i) / steps, last.y + (dy * i) / steps);
      } else {
        dab(p.x, p.y);
      }
      last = p;
    }
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    if (ready) {
      ctx.globalCompositeOperation = 'source-over';
      ctx.drawImage(mask, 0, 0);
      ctx.globalCompositeOperation = 'source-in';
      drawReveal();
      ctx.globalCompositeOperation = 'source-over';
    }
    if (now - lastMove < fade * 1.2) raf = requestAnimationFrame(frame);
    else {
      mctx.clearRect(0, 0, mask.width, mask.height);
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      prev = 0;
    }
  };

  const onMove = (e: PointerEvent) => {
    if (!w) fit();
    if (!reveal.src) reveal.src = s.src as string;
    const r = canvas.getBoundingClientRect();
    points.push({ x: (e.clientX - r.left) * dpr, y: (e.clientY - r.top) * dpr });
    lastMove = performance.now();
    if (!raf) raf = requestAnimationFrame(frame);
  };
  const onLeave = () => {
    last = null;
  };

  const ro = 'ResizeObserver' in window ? new ResizeObserver(() => fit()) : null;
  ro?.observe(img);
  fit();
  // load the second image once the first is near the screen
  const stop = api.onVisible(figure, () => {
    if (!reveal.src) reveal.src = s.src as string;
  }, '200px 0px');
  figure.addEventListener('pointermove', onMove, { passive: true });
  figure.addEventListener('pointerleave', onLeave);

  return () => {
    stop();
    ro?.disconnect();
    if (raf) cancelAnimationFrame(raf);
    figure.removeEventListener('pointermove', onMove);
    figure.removeEventListener('pointerleave', onLeave);
    canvas.remove();
  };
});
