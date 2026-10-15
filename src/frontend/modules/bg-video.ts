// Background videos from YouTube / Vimeo (self-hosted files use a plain <video>).
// The embed is created only when the container is near the viewport.
window.UncoderWB.register(
  'bg-video',
  (el) => {
    const url = el.getAttribute('data-src') || '';
    let src = '';
    const yt = url.match(/(?:youtu\.be\/|v=|embed\/|shorts\/)([A-Za-z0-9_-]{6,})/);
    const vimeo = url.match(/vimeo\.com\/(?:video\/)?(\d+)/);
    if (yt) {
      const id = yt[1];
      src = `https://www.youtube-nocookie.com/embed/${id}?autoplay=1&mute=1&loop=1&playlist=${id}&controls=0&playsinline=1&rel=0&modestbranding=1`;
    } else if (vimeo) {
      src = `https://player.vimeo.com/video/${vimeo[1]}?background=1&autoplay=1&loop=1&muted=1&dnt=1`;
    }
    if (!src || window.UncoderWB.reducedMotion()) return;
    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.title = '';
    iframe.setAttribute('aria-hidden', 'true');
    iframe.setAttribute('tabindex', '-1');
    iframe.allow = 'autoplay; fullscreen; picture-in-picture';
    iframe.loading = 'lazy';
    const fit = () => {
      const w = el.clientWidth;
      const h = el.clientHeight;
      const ratio = 16 / 9;
      if (w / h > ratio) {
        iframe.style.width = `${w}px`;
        iframe.style.height = `${w / ratio}px`;
      } else {
        iframe.style.width = `${h * ratio}px`;
        iframe.style.height = `${h}px`;
      }
    };
    el.appendChild(iframe);
    fit();
    const ro = new ResizeObserver(fit);
    ro.observe(el);
    return () => {
      ro.disconnect();
      iframe.remove();
    };
  },
  { lazy: true },
);

// The pause / play button of a self-hosted video background (Background → Pause button): it follows the video's own
// state and label; with reduced motion the video starts paused.
window.UncoderWB.register('bg-video-toggle', (el) => {
  const video = el.parentElement?.querySelector<HTMLVideoElement>(':scope > .uncoder-bg-video video');
  if (!video) {
    el.hidden = true;
    return;
  }
  const sync = () => {
    const paused = video.paused;
    el.classList.toggle('is-paused', paused);
    el.setAttribute('aria-label', (paused ? el.dataset.labelPlay : el.dataset.labelPause) || '');
  };
  if (window.UncoderWB.reducedMotion()) {
    video.removeAttribute('autoplay');
    video.pause();
  }
  const toggle = () => {
    if (video.paused) void video.play().catch(() => sync());
    else video.pause();
  };
  el.addEventListener('click', toggle);
  video.addEventListener('play', sync);
  video.addEventListener('pause', sync);
  sync();
  return () => {
    el.removeEventListener('click', toggle);
    video.removeEventListener('play', sync);
    video.removeEventListener('pause', sync);
  };
});
