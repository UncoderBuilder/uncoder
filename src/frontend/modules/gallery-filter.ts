// Filterable Image Gallery: group buttons show one group at a time (fade out, then reflow). Hidden
// images also leave the lightbox sequence. Without JavaScript the buttons stay hidden and every image shows.
window.UncoderWB.register('gallery-filter', (el, api) => {
  const bar = el.querySelector<HTMLElement>('.uncoder-image-gallery__filters');
  if (!bar) return;
  const items = Array.from(el.querySelectorAll<HTMLElement>('.uncoder-image-gallery__item[data-groups]'));
  const buttons = Array.from(bar.querySelectorAll<HTMLButtonElement>('.uncoder-image-gallery__filter'));
  const timers = new Map<HTMLElement, number>();
  bar.hidden = false;

  const show = (group: string) => {
    const motion = !api.reducedMotion();
    buttons.forEach((b) => {
      const on = (b.dataset.group ?? '') === group;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-pressed', String(on));
    });
    for (const item of items) {
      const match = !group || (item.dataset.groups ?? '').split(' ').includes(group);
      const link = item.querySelector<HTMLElement>('[data-uncoder-lightbox]');
      window.clearTimeout(timers.get(item));
      if (link) {
        // Keep the lightbox set in step with what is visible.
        if (match && link.dataset.uncoderGalleryOff) link.setAttribute('data-uncoder-gallery', link.dataset.uncoderGalleryOff);
        if (!match && link.hasAttribute('data-uncoder-gallery')) {
          link.dataset.uncoderGalleryOff = link.getAttribute('data-uncoder-gallery')!;
          link.removeAttribute('data-uncoder-gallery');
        }
      }
      if (match) {
        item.hidden = false;
        requestAnimationFrame(() => item.classList.remove('is-out'));
      } else if (!item.hidden) {
        item.classList.add('is-out');
        timers.set(item, window.setTimeout(() => (item.hidden = true), motion ? 220 : 0));
      }
    }
  };

  const onClick = (event: MouseEvent) => {
    const button = (event.target as HTMLElement).closest<HTMLButtonElement>('.uncoder-image-gallery__filter');
    if (button) show(button.dataset.group ?? '');
  };
  bar.addEventListener('click', onClick);
  return () => {
    bar.removeEventListener('click', onClick);
    timers.forEach((t) => window.clearTimeout(t));
  };
});
