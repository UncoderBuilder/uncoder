// Accessible lightbox for links with data-uncoder-lightbox. Links sharing data-uncoder-gallery form a set.
(() => {
  const api = window.UncoderWB;
  let dialog: HTMLDialogElement | null = null;
  let items: HTMLAnchorElement[] = [];
  let index = 0;
  let lastFocus: HTMLElement | null = null;
  const i18n = api.config.i18n || { close: 'Close', next: 'Next', prev: 'Previous' };
  // Design System → Lightbox.
  const opts: { auto?: boolean; bg?: string; ui?: string; caption?: boolean; counter?: boolean; download?: boolean; i18n?: Record<string, string> } = api.config.lightbox || {};
  const IMAGE = /\.(jpe?g|png|gif|webp|avif|svg)(\?.*)?$/i;
  /** Links to image files that open in the lightbox without being set up for it (post content, galleries). */
  const autoLink = (a: HTMLAnchorElement) =>
    !!opts.auto && IMAGE.test(a.pathname + a.search) && !!a.querySelector('img') && !a.hasAttribute('data-uncoder-no-lightbox') && !a.closest('[data-uncoder-no-lightbox]') && a.origin === location.origin;
  const autoGroup = (a: HTMLAnchorElement) => a.closest('.wp-block-gallery, .gallery, .blocks-gallery-grid, .uncoder-image-gallery, .entry-content, .wp-block-post-content, main') || document.body;

  function build() {
    dialog = document.createElement('dialog');
    dialog.className = 'uncoder-lightbox';
    dialog.setAttribute('aria-label', opts.i18n?.viewer || 'Image viewer');
    if (opts.bg) dialog.style.setProperty('--uncoder-lb-bg', opts.bg);
    if (opts.ui) dialog.style.setProperty('--uncoder-lb-ui', opts.ui);
    // Static markup only; translated labels are set as attributes below (never parsed as HTML).
    dialog.innerHTML =
      '<figure class="uncoder-lightbox__figure"><img class="uncoder-lightbox__img" alt=""><figcaption class="uncoder-lightbox__caption"></figcaption></figure>' +
      '<button type="button" class="uncoder-lightbox__btn uncoder-lightbox__close">&times;</button>' +
      '<button type="button" class="uncoder-lightbox__btn uncoder-lightbox__prev">&#8249;</button>' +
      '<button type="button" class="uncoder-lightbox__btn uncoder-lightbox__next">&#8250;</button>' +
      '<span class="uncoder-lightbox__counter" aria-hidden="true"></span>' +
      '<a class="uncoder-lightbox__btn uncoder-lightbox__download" download hidden><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12m0 0-5-5m5 5 5-5M5 21h14"/></svg></a>';
    const labels: Record<string, string> = { close: i18n.close, prev: i18n.prev, next: i18n.next, download: opts.i18n?.download || 'Download' };
    for (const [key, label] of Object.entries(labels)) dialog.querySelector(`.uncoder-lightbox__${key}`)?.setAttribute('aria-label', String(label || ''));
    const style = document.createElement('style');
    style.textContent =
      '.uncoder-lightbox{border:0;padding:0;max-width:100vw;max-height:100vh;width:100vw;height:100vh;background:var(--uncoder-lb-bg,rgb(10 10 12/.92));color:var(--uncoder-lb-ui,#fff)}' +
      '.uncoder-lightbox__counter{position:fixed;top:24px;left:20px;font:600 13px/1 system-ui,sans-serif;opacity:.8;letter-spacing:.04em}.uncoder-lightbox__download{top:16px;right:72px;display:flex;align-items:center;justify-content:center;color:inherit}.uncoder-lightbox__download[hidden],.uncoder-lightbox__counter:empty{display:none}' +
      '.uncoder-lightbox::backdrop{background:transparent}.uncoder-lightbox__figure{margin:0;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;padding:56px 72px}' +
      '.uncoder-lightbox__img{max-width:100%;max-height:calc(100vh - 140px);object-fit:contain;border-radius:6px}.uncoder-lightbox__caption{font:14px/1.4 system-ui,sans-serif;opacity:.85;text-align:center}' +
      '.uncoder-lightbox__btn{position:fixed;width:44px;height:44px;border:0;border-radius:50%;background:color-mix(in srgb,currentColor 14%,transparent);color:inherit;font-size:28px;line-height:1;cursor:pointer}' +
      '.uncoder-lightbox__btn:hover,.uncoder-lightbox__btn:focus-visible{background:color-mix(in srgb,currentColor 26%,transparent);outline:none}.uncoder-lightbox__close{top:16px;right:16px}' +
      '.uncoder-lightbox__prev{left:16px;top:50%;transform:translateY(-50%)}.uncoder-lightbox__next{right:16px;top:50%;transform:translateY(-50%)}' +
      '@media(max-width:600px){.uncoder-lightbox__figure{padding:56px 12px}.uncoder-lightbox__prev,.uncoder-lightbox__next{top:auto;bottom:16px;transform:none}}';
    document.head.appendChild(style);
    document.body.appendChild(dialog);
    dialog.querySelector('.uncoder-lightbox__close')!.addEventListener('click', close);
    dialog.querySelector('.uncoder-lightbox__prev')!.addEventListener('click', () => show(index - 1));
    dialog.querySelector('.uncoder-lightbox__next')!.addEventListener('click', () => show(index + 1));
    dialog.addEventListener('click', (e) => {
      if (e.target === dialog || (e.target as Element).classList.contains('uncoder-lightbox__figure')) close();
    });
    dialog.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight') show(index + 1);
      if (e.key === 'ArrowLeft') show(index - 1);
    });
    dialog.addEventListener('close', () => lastFocus?.focus());
    let startX = 0;
    dialog.addEventListener('touchstart', (e) => (startX = e.touches[0].clientX), { passive: true });
    dialog.addEventListener('touchend', (e) => {
      const dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 50) show(index + (dx < 0 ? 1 : -1));
    });
  }

  function show(i: number) {
    if (!dialog || !items.length) return;
    index = (i + items.length) % items.length;
    const a = items[index];
    const img = dialog.querySelector<HTMLImageElement>('.uncoder-lightbox__img')!;
    const inner = a.querySelector('img');
    img.src = a.href;
    img.alt = inner?.alt || '';
    const caption = a.getAttribute('data-caption') || a.closest('figure')?.querySelector('figcaption')?.textContent?.trim() || '';
    dialog.querySelector('.uncoder-lightbox__caption')!.textContent = opts.caption === false ? '' : caption;
    const multi = items.length > 1;
    dialog.querySelectorAll<HTMLElement>('.uncoder-lightbox__prev,.uncoder-lightbox__next').forEach((b) => (b.hidden = !multi));
    dialog.querySelector('.uncoder-lightbox__counter')!.textContent = multi && opts.counter !== false ? `${index + 1} / ${items.length}` : '';
    const dl = dialog.querySelector<HTMLAnchorElement>('.uncoder-lightbox__download')!;
    dl.hidden = !opts.download;
    dl.href = a.href;
  }

  function open(a: HTMLAnchorElement) {
    if (!dialog) build();
    const group = a.getAttribute('data-uncoder-gallery');
    if (!a.hasAttribute('data-uncoder-lightbox')) {
      // An ordinary image link: its neighbours in the same gallery / content area form the set.
      items = Array.from(autoGroup(a).querySelectorAll<HTMLAnchorElement>('a[href]')).filter((x) => !x.hasAttribute('data-uncoder-lightbox') && autoLink(x));
    } else {
      items = group ? Array.from(document.querySelectorAll<HTMLAnchorElement>(`a[data-uncoder-lightbox][data-uncoder-gallery="${CSS.escape(group)}"]`)) : [a];
    }
    lastFocus = document.activeElement as HTMLElement;
    show(items.indexOf(a));
    dialog!.showModal();
  }

  function close() {
    dialog?.close();
  }

  document.addEventListener('click', (e) => {
    let a = (e.target as Element | null)?.closest?.('a[data-uncoder-lightbox]') as HTMLAnchorElement | null;
    if (!a) {
      const link = (e.target as Element | null)?.closest?.('a[href]') as HTMLAnchorElement | null;
      a = link && autoLink(link) ? link : null;
    }
    if (!a || api.editor || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey) return;
    e.preventDefault();
    open(a);
  });

  api.register('lightbox', () => {});
})();
