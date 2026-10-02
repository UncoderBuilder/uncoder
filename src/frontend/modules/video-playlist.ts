// Video Playlist widget: plays the entry chosen in the list. The first embed is a facade (poster +
// play button), so nothing is requested from YouTube / Vimeo before the visitor plays something;
// choosing another entry starts it at once (that click is the request). With "next" on, the
// following entry starts when a video ends: the native "ended" event for files, the players'
// postMessage events for embeds (Vimeo's player API; YouTube's iframe API with enablejsapi=1).
(() => {
  type Kind = 'youtube' | 'vimeo' | 'file';
  type Media = HTMLIFrameElement | HTMLVideoElement;

  const PROVIDERS: Record<string, Kind> = {
    'www.youtube-nocookie.com': 'youtube',
    'youtube-nocookie.com': 'youtube',
    'www.youtube.com': 'youtube',
    'youtube.com': 'youtube',
    'player.vimeo.com': 'vimeo',
  };
  const ALLOW = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen';

  /** Data attributes are not trusted: embeds must be https players of their provider, files http(s). */
  const safeSrc = (raw: string, kind: string): string => {
    if (!raw) return '';
    try {
      const url = new URL(raw, location.href);
      if (kind === 'file') return url.protocol === 'https:' || url.protocol === 'http:' ? url.href : '';
      return url.protocol === 'https:' && PROVIDERS[url.hostname] === kind ? url.href : '';
    } catch {
      return '';
    }
  };

  const parse = (data: unknown): any => {
    if (typeof data !== 'string') return data;
    try {
      return JSON.parse(data);
    } catch {
      return null;
    }
  };

  window.UncoderWB.register('video-playlist', (el, api) => {
    if (api.editor) return;
    const player = el.querySelector<HTMLElement>('.uncoder-video-playlist__player');
    const list = el.querySelector<HTMLElement>('.uncoder-video-playlist__list');
    const buttons = Array.from(el.querySelectorAll<HTMLButtonElement>('.uncoder-video-playlist__button'));
    if (!player || !list || !buttons.length) return;

    const status = el.querySelector<HTMLElement>('.uncoder-video-playlist__status');
    const position = el.querySelector<HTMLElement>('.uncoder-video-playlist__position');
    const s = api.settings<{ next?: boolean; loop?: boolean; playing?: string }>(el);
    const initial = Array.from(player.childNodes);
    const initialIndex = Math.max(0, buttons.findIndex((b) => b.classList.contains('is-active')));
    let active = initialIndex;
    let media: Media | null = player.querySelector<HTMLVideoElement>(':scope > .uncoder-video-playlist__video');
    let finished = false; // embeds may report the end more than once
    let hello = 0; // YouTube handshake timer

    const setActive = (i: number) => {
      active = i;
      buttons.forEach((b, n) => {
        b.classList.toggle('is-active', n === i);
        if (n === i) b.setAttribute('aria-current', 'true');
        else b.removeAttribute('aria-current');
      });
      if (position) position.textContent = String(i + 1);
      // Keep the entry in view inside the list without scrolling the page.
      const item = buttons[i].parentElement as HTMLElement;
      if (list.scrollHeight <= list.clientHeight) return;
      const top = item.offsetTop;
      const bottom = top + item.offsetHeight;
      const to = top < list.scrollTop ? top : bottom > list.scrollTop + list.clientHeight ? bottom - list.clientHeight : null;
      if (to !== null) list.scrollTo({ top: to, behavior: api.reducedMotion() ? 'auto' : 'smooth' });
    };

    const announce = (i: number) => {
      if (!status) return;
      status.textContent = (s.playing || '%1$s')
        .replace('%1$s', buttons[i].dataset.title || '')
        .replace('%2$d', String(i + 1))
        .replace('%3$d', String(buttons.length));
    };

    const onEnded = () => {
      if (finished || !s.next) return;
      finished = true;
      let next = active + 1;
      if (next >= buttons.length) {
        if (!s.loop) return;
        next = 0;
      }
      if (next === active) return;
      setActive(next);
      mount(next, false);
    };

    /** YouTube only reports player events after a "listening" handshake; repeat it until the player is ready. */
    const greetYouTube = (iframe: HTMLIFrameElement) => {
      const origin = new URL(iframe.src).origin;
      let tries = 0;
      const send = () => {
        if (++tries > 30 || media !== iframe) {
          window.clearInterval(hello);
          return;
        }
        iframe.contentWindow?.postMessage(JSON.stringify({ event: 'listening', id: 1, channel: 'widget' }), origin);
        iframe.contentWindow?.postMessage(JSON.stringify({ event: 'command', func: 'addEventListener', args: ['onStateChange'], id: 1, channel: 'widget' }), origin);
      };
      window.clearInterval(hello);
      hello = window.setInterval(send, 500);
      send();
    };

    const greetVimeo = (iframe: HTMLIFrameElement) => {
      const origin = new URL(iframe.src).origin;
      for (const value of ['ended', 'finish']) iframe.contentWindow?.postMessage({ method: 'addEventListener', value }, origin);
    };

    const onMessage = (event: MessageEvent) => {
      if (!s.next || !(media instanceof HTMLIFrameElement) || event.source !== media.contentWindow) return;
      let host = '';
      try {
        host = new URL(event.origin).hostname;
      } catch {
        return;
      }
      const kind = PROVIDERS[host];
      const data = parse(event.data);
      if (!kind || !data || typeof data !== 'object') return;
      if (kind === 'vimeo') {
        if (data.event === 'ready') greetVimeo(media);
        else if (data.event === 'ended' || data.event === 'finish') onEnded();
        return;
      }
      if (data.event === 'onReady') {
        window.clearInterval(hello);
        media.contentWindow?.postMessage(JSON.stringify({ event: 'command', func: 'addEventListener', args: ['onStateChange'], id: 1, channel: 'widget' }), event.origin);
      } else if ((data.event === 'onStateChange' && data.info === 0) || (data.event === 'infoDelivery' && data.info?.playerState === 0)) {
        onEnded();
      }
    };

    const release = () => {
      window.clearInterval(hello);
      if (media instanceof HTMLVideoElement) media.removeEventListener('ended', onEnded);
    };

    /** Puts entry i in the player and starts it (only ever called after the visitor asked for a video). */
    function mount(i: number, focus: boolean) {
      const b = buttons[i];
      const kind = (b.dataset.kind || '') as Kind;
      const src = safeSrc(b.dataset.src || '', kind);
      if (!src) return;
      const title = b.dataset.title || '';
      const refocus = !!media && document.activeElement === media;
      let node: Media;
      if (kind === 'file') {
        const video = document.createElement('video');
        video.className = 'uncoder-video-playlist__video';
        video.controls = true;
        video.playsInline = true;
        video.preload = 'auto';
        const poster = safeSrc(b.dataset.poster || '', 'file');
        if (poster) video.poster = poster;
        video.setAttribute('aria-label', title);
        video.src = src;
        video.addEventListener('ended', onEnded);
        node = video;
      } else {
        const iframe = document.createElement('iframe');
        const url = new URL(src);
        if (kind === 'youtube' && s.next) url.searchParams.set('origin', location.origin);
        iframe.className = 'uncoder-video-playlist__iframe';
        iframe.src = url.href;
        iframe.title = title;
        iframe.allow = ALLOW;
        iframe.allowFullscreen = true;
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        if (s.next) iframe.addEventListener('load', () => (kind === 'youtube' ? greetYouTube(iframe) : greetVimeo(iframe)), { once: true });
        node = iframe;
      }
      release();
      finished = false;
      player!.replaceChildren(node);
      media = node;
      el.classList.add('is-started');
      if (node instanceof HTMLVideoElement) node.play().catch(() => {});
      if (focus || refocus) node.focus();
      announce(i);
    }

    const onPlayerClick = (event: MouseEvent) => {
      if (!(event.target as Element).closest('.uncoder-video-playlist__facade')) return;
      event.preventDefault();
      mount(active, true);
    };

    const onListClick = (event: MouseEvent) => {
      const i = buttons.indexOf((event.target as Element).closest('.uncoder-video-playlist__button') as HTMLButtonElement);
      if (i < 0 || (i === active && media)) return;
      setActive(i);
      mount(i, false);
    };

    // Arrow keys, Home and End move between entries (Tab still visits each one).
    const onKey = (event: KeyboardEvent) => {
      const i = buttons.indexOf(document.activeElement as HTMLButtonElement);
      if (i < 0) return;
      const last = buttons.length - 1;
      const to = ({ ArrowDown: Math.min(i + 1, last), ArrowUp: Math.max(i - 1, 0), Home: 0, End: last } as Record<string, number>)[event.key];
      if (to === undefined) return;
      event.preventDefault();
      buttons[to].focus();
    };

    if (media) media.addEventListener('ended', onEnded);
    player.addEventListener('click', onPlayerClick);
    list.addEventListener('click', onListClick);
    list.addEventListener('keydown', onKey);
    window.addEventListener('message', onMessage);

    return () => {
      player.removeEventListener('click', onPlayerClick);
      list.removeEventListener('click', onListClick);
      list.removeEventListener('keydown', onKey);
      window.removeEventListener('message', onMessage);
      release();
      player.replaceChildren(...initial);
      media = null;
      el.classList.remove('is-started');
      setActive(initialIndex);
      if (status) status.textContent = '';
    };
  });
})();
