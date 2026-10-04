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

/** The play / pause pill of a file video: follows the video's state; an autoplaying loop stays still for visitors who
 * prefer reduced motion until they press play. */
function playPause(el: HTMLElement, reduced: boolean): (() => void) | undefined {
  const toggle = el.querySelector<HTMLButtonElement>('.uncoder-video__toggle');
  const media = el.querySelector<HTMLVideoElement>('video.uncoder-video__el');
  if (!toggle || !media) return;
  const label = toggle.querySelector('.uncoder-video__toggle-label');
  const sync = () => {
    const playing = !media.paused;
    toggle.classList.toggle('is-playing', playing);
    const text = playing ? toggle.dataset.pause : toggle.dataset.play;
    if (label && text) label.textContent = text;
    toggle.setAttribute('aria-label', (playing ? toggle.dataset.pauseLabel : toggle.dataset.playLabel) || text || '');
  };
  const onClick = () => {
    if (media.paused) media.play().catch(sync);
    else media.pause();
  };
  toggle.addEventListener('click', onClick);
  media.addEventListener('play', sync);
  media.addEventListener('pause', sync);
  if (media.autoplay && reduced) media.pause();
  sync();
  return () => {
    toggle.removeEventListener('click', onClick);
    media.removeEventListener('play', sync);
    media.removeEventListener('pause', sync);
  };
}

window.UncoderWB.register('video', (el, api) => {
  const stopToggle = api.editor ? undefined : playPause(el, api.reducedMotion());
  const button = el.querySelector<HTMLButtonElement>('.uncoder-video__facade');
  const src = safeEmbed(button?.getAttribute('data-uncoder-embed') || '');
  if (!button || !src || api.editor) return stopToggle;

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
    stopToggle?.();
    cancelled = true;
    button.removeEventListener('click', onClick);
    if (iframe) {
      iframe.remove();
      iframe = null;
      button.hidden = false;
    }
  };
});
