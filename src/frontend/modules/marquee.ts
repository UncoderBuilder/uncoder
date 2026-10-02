// Marquee (and the logo grid's marquee display): the moving strip clips its images, so the browser's lazy
// loading would fetch each one only as it slides into view and leave gaps. Once the marquee comes near the
// screen (lazy module), load them all, sized to how they are shown.
window.UncoderWB.register(
  'marquee',
  (el) => {
    if (!el.querySelector('.uncoder-marquee__track')) return; // The logo grid's plain grid lazy-loads fine.
    el.querySelectorAll<HTMLImageElement>('img[loading="lazy"]').forEach((img) => {
      const width = img.getBoundingClientRect().width;
      if (!width) return; // Hidden (reduced-motion copies): leave it lazy.
      // "sizes: auto" only works for lazy images; give the shown width instead.
      if (img.srcset) img.sizes = `${Math.ceil(width)}px`;
      img.loading = 'eager';
    });
  },
  { lazy: true },
);
