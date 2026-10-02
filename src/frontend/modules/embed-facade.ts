// Click-to-load facade for third-party iframes (SoundCloud, Facebook embed, Google Maps widgets): the widget
// draws a local preview button carrying the player URL in data-uncoder-embed; nothing is requested
// from the provider until the visitor clicks it. Optional data-fit-width / data-fit-height ("min-max",
// either side may be empty) rewrite the width / height query parameters to the size of the box at
// that moment, for plugins that render at the size they are given (Facebook).
(() => {
  /** Allowed providers (https only) and the iframe features they get. */
  const PROVIDERS: Record<string, { allow: string; fullscreen?: boolean; noScroll?: boolean }> = {
    'w.soundcloud.com': { allow: 'autoplay; encrypted-media' },
    'www.facebook.com': { allow: 'autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share', fullscreen: true, noScroll: true },
    'maps.google.com': { allow: 'fullscreen', fullscreen: true },
  };

  const fit = (url: URL, param: string, size: number, range: string | undefined) => {
    if (!range || !(size > 0)) return;
    const [min, max] = range.split('-').map((n) => parseInt(n, 10));
    let value = Math.round(size);
    if (min > 0) value = Math.max(min, value);
    if (max > 0) value = Math.min(max, value);
    url.searchParams.set(param, String(value));
  };

  window.UncoderWB.register('embed-facade', (el, api) => {
    const button = el.querySelector<HTMLButtonElement>('[data-uncoder-embed]');
    if (!button || api.editor) return;
    const url = (() => {
      try {
        return new URL(button.getAttribute('data-uncoder-embed') || '');
      } catch {
        return null;
      }
    })();
    // The attribute is not trusted: only https players of the known providers are embedded.
    const provider = url?.protocol === 'https:' ? PROVIDERS[url.hostname] : undefined;
    if (!url || !provider) return;

    let iframe: HTMLIFrameElement | null = null;

    const load = () => {
      if (iframe) return;
      const box = button.getBoundingClientRect();
      fit(url, 'width', box.width, button.dataset.fitWidth);
      fit(url, 'height', box.height, button.dataset.fitHeight);
      iframe = document.createElement('iframe');
      // uncoder-{widget}__facade → uncoder-{widget}__iframe
      iframe.className = (button.classList[0] || '').replace(/__facade$/, '__iframe');
      iframe.src = url.href;
      iframe.title = button.dataset.title || '';
      iframe.allow = provider.allow;
      iframe.referrerPolicy = 'strict-origin-when-cross-origin';
      if (provider.fullscreen) iframe.allowFullscreen = true;
      if (provider.noScroll) iframe.setAttribute('scrolling', 'no');
      button.insertAdjacentElement('afterend', iframe);
      button.hidden = true;
      el.classList.add('is-loaded');
      iframe.focus();
    };

    const onClick = (event: MouseEvent) => {
      event.preventDefault();
      load();
    };
    button.addEventListener('click', onClick);

    return () => {
      button.removeEventListener('click', onClick);
      if (iframe) {
        iframe.remove();
        iframe = null;
        button.hidden = false;
        el.classList.remove('is-loaded');
      }
    };
  });
})();
