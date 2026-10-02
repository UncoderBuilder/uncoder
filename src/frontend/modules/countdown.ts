// Countdown: keeps the server-rendered digits ticking. Due-date mode counts to a fixed timestamp;
// evergreen mode stores a per-visitor deadline in localStorage. The visual digits are aria-hidden;
// a visually hidden sentence (minute precision) is kept current for screen readers (aria-live off).
type Unit = 'days' | 'hours' | 'minutes' | 'seconds';

interface CountdownSettings {
  mode?: 'due' | 'evergreen';
  due?: number;
  duration?: number;
  restart?: boolean;
  key?: string;
  units?: Unit[];
  actions?: string[];
  redirect?: string;
  sr?: Partial<Record<Unit, [string, string]>> & { remaining?: string; expired?: string };
}

const ORDER: Unit[] = ['days', 'hours', 'minutes', 'seconds'];
const SIZE: Record<Unit, number> = { days: 86400, hours: 3600, minutes: 60, seconds: 1 };

window.UncoderWB.register('countdown', (el, api) => {
  const root = (el.matches('.uncoder-countdown') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-countdown'));
  if (!root) return;
  const s = api.settings<CountdownSettings>(el);
  const units = ORDER.filter((u) => (s.units || ORDER).includes(u));
  const digits = new Map<Unit, HTMLElement>();
  units.forEach((u) => {
    const d = root.querySelector<HTMLElement>(`[data-unit="${u}"]`);
    if (d) digits.set(u, d);
  });
  const srEl = root.querySelector<HTMLElement>('.uncoder-countdown__sr');
  const unitsEl = root.querySelector<HTMLElement>('.uncoder-countdown__units');
  const messageEl = root.querySelector<HTMLElement>('.uncoder-countdown__message');
  const actions = s.actions || [];
  const duration = Math.max(0, Number(s.duration) || 0) * 1000;

  const read = (): number => {
    try {
      return parseInt(window.localStorage.getItem(s.key || '') || '', 10) || 0;
    } catch {
      return 0;
    }
  };
  const write = (value: number) => {
    try {
      window.localStorage.setItem(s.key || '', String(value));
    } catch {
      /* storage unavailable (private mode): the deadline lasts for this page view */
    }
  };

  let end: number;
  if (s.mode === 'evergreen') {
    // The editor always previews a fresh period and never stores anything.
    end = api.editor ? Date.now() + duration : read();
    if (!api.editor && (!end || (s.restart && end <= Date.now()))) {
      end = Date.now() + duration;
      write(end);
    }
  } else {
    end = (Number(s.due) || 0) * 1000;
  }

  const split = (seconds: number): Partial<Record<Unit, number>> => {
    const out: Partial<Record<Unit, number>> = {};
    for (const u of units) {
      out[u] = Math.floor(seconds / SIZE[u]);
      seconds -= (out[u] as number) * SIZE[u];
    }
    return out;
  };

  const spoken = (parts: Partial<Record<Unit, number>>): string => {
    const list = units.length > 1 ? units.filter((u) => u !== 'seconds') : units;
    const chunks = list.map((u) => {
      const n = parts[u] ?? 0;
      const forms = s.sr?.[u] || ['%d', '%d'];
      return (n === 1 ? forms[0] : forms[1]).replace('%d', String(n));
    });
    return (s.sr?.remaining || '%s').replace('%s', chunks.join(', '));
  };

  const pad = (n: number) => (n < 10 ? '0' + n : String(n));
  let timer = 0;
  let lastSr = srEl?.textContent || '';
  let expired = false;

  const expire = () => {
    if (s.mode === 'evergreen' && s.restart && !api.editor) {
      end = Date.now() + duration;
      write(end);
      return;
    }
    expired = true;
    window.clearInterval(timer);
    root.classList.add('uncoder-countdown--expired');
    if (srEl && s.sr?.expired) srEl.textContent = s.sr.expired;
    if (api.editor) return;
    if (actions.includes('hide') && unitsEl) unitsEl.hidden = true;
    if (actions.includes('message') && messageEl) messageEl.hidden = false;
    if (actions.includes('redirect') && s.redirect) {
      try {
        const url = new URL(s.redirect, window.location.href);
        if ((url.protocol === 'http:' || url.protocol === 'https:') && url.href !== window.location.href) {
          window.location.assign(url.href);
        }
      } catch {
        /* invalid URL: ignore */
      }
    }
  };

  const tick = () => {
    if (expired) return;
    const remaining = Math.max(0, Math.floor((end - Date.now()) / 1000));
    const parts = split(remaining);
    digits.forEach((node, u) => {
      const text = pad(parts[u] ?? 0);
      if (node.textContent !== text) node.textContent = text;
    });
    if (remaining <= 0) {
      expire();
      return;
    }
    const sentence = spoken(parts);
    if (srEl && sentence !== lastSr) {
      srEl.textContent = sentence;
      lastSr = sentence;
    }
  };

  tick();
  if (!expired) timer = window.setInterval(tick, 1000);

  return () => window.clearInterval(timer);
});
