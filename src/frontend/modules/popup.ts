// Popups: triggers, frequency capping, device rules and an accessible modal dialog.
interface PopupSettings {
  id: number;
  triggers: {
    load?: { enabled: boolean; delay: number };
    scroll?: { enabled: boolean; percent: number };
    scroll_to?: { enabled: boolean; selector: string };
    click?: { enabled: boolean; selector: string };
    exit_intent?: { enabled: boolean };
    inactivity?: { enabled: boolean; seconds: number };
    page_views?: { enabled: boolean; count: number };
  };
  frequency: { times: number; period: 'session' | 'day' | 'week' | 'month' | 'forever' };
  devices: string[];
  overlay: boolean;
  closeOverlay: boolean;
  closeEsc: boolean;
  avoid: boolean;
  rules?: {
    referrer: '' | 'search' | 'external' | 'internal' | 'direct' | 'contains';
    referrerValue: string;
    param: string;
    sessions: number;
    schedule: null | { local: boolean; from: number | string | null; until: number | string | null };
    browsers: string[];
    host: string;
  };
}

(() => {
  const api = window.UncoderWB;
  const PERIOD: Record<string, number> = { day: 864e5, week: 6048e5, month: 2592e6, forever: Infinity };
  let openCount = 0;

  const store = {
    get(key: string, session = false): any {
      try {
        return JSON.parse((session ? sessionStorage : localStorage).getItem(key) || 'null');
      } catch {
        return null;
      }
    },
    set(key: string, value: unknown, session = false) {
      try {
        (session ? sessionStorage : localStorage).setItem(key, JSON.stringify(value));
      } catch {
        /* storage blocked */
      }
    },
  };

  // Page views (for the page_views trigger), counted once per page load.
  const views = (store.get('uncoder-pv', true) || 0) + 1;
  store.set('uncoder-pv', views, true);

  // Visits (sessions) and how this visit started: the first page's referrer and URL.
  if (!store.get('uncoder-visit', true)) {
    store.set('uncoder-visit', 1, true);
    store.set('uncoder-visits', (store.get('uncoder-visits') || 0) + 1);
    store.set('uncoder-landing', { ref: document.referrer, url: location.href }, true);
  }
  const visits: number = store.get('uncoder-visits') || 1;
  const landing: { ref: string; url: string } = store.get('uncoder-landing', true) || { ref: document.referrer, url: location.href };

  const SEARCH = /(^|\.)(google|bing|yahoo|duckduckgo|yandex|baidu|ecosia|qwant|startpage|naver|seznam)\.|search\.brave\.com/i;
  const hostOf = (url: string) => {
    try {
      return new URL(url).hostname;
    } catch {
      return '';
    }
  };

  function browser(): string {
    const ua = navigator.userAgent;
    const list: Array<[string, RegExp]> = [
      ['edge', /Edg(e|A|iOS)?\//],
      ['opera', /OPR\/|Opera/],
      ['samsung', /SamsungBrowser/],
      ['firefox', /Firefox|FxiOS/],
      ['chrome', /Chrome|CriOS/],
      ['safari', /Safari/],
    ];
    return list.find(([, re]) => re.test(ua))?.[0] ?? '';
  }

  /** Popup settings → "Who sees it" rules (links that open the popup skip them). */
  function allowedByRules(s: PopupSettings): boolean {
    const r = s.rules;
    if (!r) return true;
    if (r.sessions > 1 && visits < r.sessions) return false;
    if (r.browsers?.length && !r.browsers.includes(browser())) return false;
    if (r.referrer) {
      const ref = r.referrer === 'internal' ? document.referrer : landing.ref;
      const host = hostOf(ref);
      const own = host !== '' && (host === r.host || host === location.hostname);
      if (r.referrer === 'direct' && ref !== '') return false;
      if (r.referrer === 'internal' && !own) return false;
      if (r.referrer === 'external' && (host === '' || own)) return false;
      if (r.referrer === 'search' && !SEARCH.test(host)) return false;
      if (r.referrer === 'contains' && (!r.referrerValue || !ref.toLowerCase().includes(r.referrerValue.toLowerCase()))) return false;
    }
    if (r.param) {
      const [name, value] = r.param.split('=');
      const has = (url: string) => {
        try {
          const p = new URL(url).searchParams;
          return p.has(name) && (value === undefined || p.get(name) === value);
        } catch {
          return false;
        }
      };
      if (!has(location.href) && !has(landing.url)) return false;
    }
    const sc = r.schedule;
    if (sc) {
      const now = Date.now();
      const at = (v: number | string | null) => (v === null || v === '' ? null : sc.local ? new Date(String(v)).getTime() : Number(v));
      const from = at(sc.from);
      const until = at(sc.until);
      if ((from !== null && now < from) || (until !== null && now > until)) return false;
    }
    return true;
  }

  function device(): string {
    const w = window.innerWidth;
    const bps = (api.config.breakpoints || []) as Array<{ id: string; value: number | null }>;
    const mobile = bps.find((b) => b.id === 'mobile')?.value ?? 767;
    const tablet = bps.find((b) => b.id === 'tablet')?.value ?? 1024;
    return w <= mobile ? 'mobile' : w <= tablet ? 'tablet' : 'desktop';
  }

  function allowedByFrequency(s: PopupSettings): boolean {
    if (!s.frequency || !s.frequency.times) return true;
    const session = s.frequency.period === 'session';
    const key = `uncoder-popup-${s.id}`;
    const log: number[] = store.get(key, session) || [];
    const window_ = PERIOD[s.frequency.period] ?? Infinity;
    const recent = session ? log : log.filter((t) => Date.now() - t < window_);
    return recent.length < s.frequency.times;
  }

  function remember(s: PopupSettings) {
    const session = s.frequency?.period === 'session';
    const key = `uncoder-popup-${s.id}`;
    const log: number[] = store.get(key, session) || [];
    log.push(Date.now());
    store.set(key, log.slice(-50), session);
  }

  api.register('popup', (el, a) => {
    const s = a.settings<PopupSettings>(el);
    if (a.editor) return;
    if (s.devices && s.devices.length && !s.devices.includes(device())) return;
    const dialog = el.querySelector<HTMLElement>('.uncoder-popup__dialog')!;
    let lastFocus: HTMLElement | null = null;
    let shown = false;
    const cleanups: Array<() => void> = [];

    const focusables = () =>
      Array.from(dialog.querySelectorAll<HTMLElement>('a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])')).filter((n) => n.offsetParent !== null);

    function open(force = false) {
      if (shown && !force) return;
      if (!force && (!allowedByFrequency(s) || !allowedByRules(s) || (s.avoid && openCount > 0))) return;
      shown = true;
      openCount++;
      remember(s);
      lastFocus = document.activeElement as HTMLElement;
      el.hidden = false;
      requestAnimationFrame(() => el.classList.add('is-open'));
      if (s.overlay) document.documentElement.classList.add('uncoder-popup-lock');
      setTimeout(() => (focusables()[0] ?? dialog).focus(), 50);
      el.dispatchEvent(new CustomEvent('uncoder:popup-open', { bubbles: true, detail: { id: s.id } }));
    }

    function close() {
      if (el.hidden) return;
      el.classList.remove('is-open');
      openCount = Math.max(0, openCount - 1);
      const done = () => {
        el.hidden = true;
        if (!document.querySelector('.uncoder-popup.is-open')) document.documentElement.classList.remove('uncoder-popup-lock');
      };
      if (a.reducedMotion()) done();
      else setTimeout(done, 220);
      lastFocus?.focus?.();
    }

    const onKey = (e: KeyboardEvent) => {
      if (el.hidden) return;
      if (e.key === 'Escape' && s.closeEsc) close();
      if (e.key === 'Tab' && s.overlay) {
        const list = focusables();
        if (!list.length) return;
        const first = list[0];
        const last = list[list.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    };
    document.addEventListener('keydown', onKey);
    cleanups.push(() => document.removeEventListener('keydown', onKey));

    el.addEventListener('click', (e) => {
      const t = e.target as Element;
      if (t.closest('[data-uncoder-popup-close]') || (s.closeOverlay && t.hasAttribute('data-uncoder-popup-overlay'))) close();
    });

    // Programmatic control: links #uncoder-popup:open:ID (runtime dispatches uncoder:popup).
    const onAction = (e: Event) => {
      const d = (e as CustomEvent).detail || {};
      // "#uncoder-popup:close" without an id closes the popup the link is in.
      if (d.action === 'close' && !d.id && d.trigger instanceof Element && el.contains(d.trigger)) {
        close();
        return;
      }
      if (Number(d.id) !== s.id) return;
      if (d.action === 'close') close();
      else if (d.action === 'toggle') el.hidden ? open(true) : close();
      else open(true);
    };
    document.addEventListener('uncoder:popup', onAction);
    cleanups.push(() => document.removeEventListener('uncoder:popup', onAction));

    const t = s.triggers || {};
    if (t.page_views?.enabled && views < t.page_views.count) return () => cleanups.forEach((c) => c());
    // "After page views" limits the other triggers; on its own it opens the popup once the count is reached.
    const others = [t.load, t.scroll, t.scroll_to, t.click, t.exit_intent, t.inactivity].some((x) => x?.enabled);
    if (t.page_views?.enabled && !others) {
      const timer = setTimeout(() => open(), 1000);
      cleanups.push(() => clearTimeout(timer));
    }

    if (t.load?.enabled) {
      const timer = setTimeout(() => open(), Math.max(0, t.load.delay) * 1000);
      cleanups.push(() => clearTimeout(timer));
    }
    if (t.scroll?.enabled) {
      const onScroll = () => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        if (max > 0 && (window.scrollY / max) * 100 >= t.scroll!.percent) {
          window.removeEventListener('scroll', onScroll);
          open();
        }
      };
      window.addEventListener('scroll', onScroll, { passive: true });
      cleanups.push(() => window.removeEventListener('scroll', onScroll));
    }
    if (t.scroll_to?.enabled && t.scroll_to.selector) {
      const target = document.querySelector(t.scroll_to.selector);
      if (target) a.onVisible(target, () => open(), '0px');
    }
    if (t.click?.enabled && t.click.selector) {
      const onClick = (e: Event) => {
        const hit = (e.target as Element)?.closest?.(t.click!.selector);
        if (hit) {
          e.preventDefault();
          open(true);
        }
      };
      document.addEventListener('click', onClick);
      cleanups.push(() => document.removeEventListener('click', onClick));
    }
    if (t.exit_intent?.enabled) {
      const onOut = (e: MouseEvent) => {
        if (!e.relatedTarget && e.clientY <= 0) {
          document.removeEventListener('mouseout', onOut);
          open();
        }
      };
      document.addEventListener('mouseout', onOut);
      cleanups.push(() => document.removeEventListener('mouseout', onOut));
    }
    if (t.inactivity?.enabled) {
      let timer = 0;
      const reset = () => {
        clearTimeout(timer);
        timer = window.setTimeout(() => open(), t.inactivity!.seconds * 1000);
      };
      const events = ['mousemove', 'keydown', 'scroll', 'touchstart'];
      events.forEach((ev) => window.addEventListener(ev, reset, { passive: true }));
      reset();
      cleanups.push(() => {
        clearTimeout(timer);
        events.forEach((ev) => window.removeEventListener(ev, reset));
      });
    }
    return () => cleanups.forEach((c) => c());
  });
})();
