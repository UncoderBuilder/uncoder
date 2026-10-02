// Video widget: replaces the facade (poster + play button) with the real YouTube / Vimeo player
// on click, so no third-party request happens before the visitor asks for the video. With
// autoplay enabled the player is injected (muted) when the video scrolls into view instead.
const EMBED_HOSTS = new Set(['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'youtube-nocookie.com', 'player.vimeo.com']);

/** Only https players of the supported providers may be embedded (the attribute is not trusted). */
function safeEmbed(raw: string): string {
  try {
    const url = new URL(raw);
    return url.protocol === 'https:' && EMBED_HOSTS.has(url.hostname) ? url.href : '';
  } catch {
    return '';
  }
}

window.UncoderWB.register('video', (el, api) => {
  const button = el.querySelector<HTMLButtonElement>('.uncoder-video__facade');
  const src = safeEmbed(button?.getAttribute('data-uncoder-embed') || '');
  if (!button || !src || api.editor) return;

  let iframe: HTMLIFrameElement | null = null;
  let cancelled = false;

  const load = (focus: boolean) => {
    if (iframe || cancelled) return;
    iframe = document.createElement('iframe');
    iframe.className = 'uncoder-video__iframe';
    iframe.src = src;
    iframe.title = button.getAttribute('data-title') || '';
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen';
    iframe.allowFullscreen = true;
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    button.insertAdjacentElement('afterend', iframe);
    button.hidden = true;
    if (focus) iframe.focus();
  };

  const onClick = (event: MouseEvent) => {
    event.preventDefault();
    load(true);
  };
  button.addEventListener('click', onClick);

  if (button.getAttribute('data-autoplay') === 'true' && !api.reducedMotion()) {
    api.onVisible(el, () => load(false), '0px');
  }

  return () => {
    cancelled = true;
    button.removeEventListener('click', onClick);
    if (iframe) {
      iframe.remove();
      iframe = null;
      button.hidden = false;
    }
  };
});
