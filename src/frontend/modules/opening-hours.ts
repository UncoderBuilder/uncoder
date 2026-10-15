// Opening Hours: works out "open now" in the site's timezone in the visitor's browser (a cached page keeps the
// server's answer from when it was made) and again every minute; also marks today's row in the list.
interface HoursRow {
  days: number[];
  open: number;
  close: number;
}
interface HoursSettings {
  tz?: string;
  rows?: HoursRow[];
  open?: string;
  closed?: string;
  format?: '12' | '24';
  today?: string;
  tomorrow?: string;
  days?: string[];
}

const HOURS_WEEKDAY: Record<string, number> = { Mon: 0, Tue: 1, Wed: 2, Thu: 3, Fri: 4, Sat: 5, Sun: 6 };

/** Day (0 = Monday) and minutes after midnight now, in the given IANA timezone or a "+05:30" offset. */
function hoursNow(tz: string): { day: number; min: number } {
  const date = new Date();
  const offset = /^[+-]\d{2}:\d{2}$/.exec(tz);
  if (offset) {
    const sign = tz[0] === '-' ? -1 : 1;
    const shift = sign * (parseInt(tz.slice(1, 3), 10) * 60 + parseInt(tz.slice(4, 6), 10));
    const t = new Date(date.getTime() + shift * 60000);
    return { day: (t.getUTCDay() + 6) % 7, min: t.getUTCHours() * 60 + t.getUTCMinutes() };
  }
  try {
    const parts = new Intl.DateTimeFormat('en-US', { timeZone: tz || undefined, weekday: 'short', hour: 'numeric', minute: 'numeric', hourCycle: 'h23' }).formatToParts(date);
    const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '';
    return { day: HOURS_WEEKDAY[get('weekday')] ?? 0, min: (parseInt(get('hour'), 10) % 24) * 60 + parseInt(get('minute'), 10) };
  } catch {
    return { day: (date.getDay() + 6) % 7, min: date.getHours() * 60 + date.getMinutes() };
  }
}

function hoursFormat(minutes: number, f: '12' | '24'): string {
  const m = ((minutes % 1440) + 1440) % 1440;
  const h = Math.floor(m / 60);
  const mm = m % 60;
  if (f === '24') return `${String(h).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
  return `${h % 12 === 0 ? 12 : h % 12}${mm ? `:${String(mm).padStart(2, '0')}` : ''}${h < 12 ? 'am' : 'pm'}`;
}

/** [open, closing minutes, next opening as [days ahead, minutes]] — the same rules as Opening_Hours::state(). */
function hoursState(rows: HoursRow[], day: number, min: number): [boolean, number | null, [number, number] | null] {
  for (const r of rows) {
    const overnight = r.close <= r.open;
    if (r.days.includes(day) && min >= r.open && (overnight || min < r.close)) return [true, r.close, null];
    if (overnight && r.days.includes((day + 6) % 7) && min < r.close) return [true, r.close, null];
  }
  for (let offset = 0; offset <= 7; offset++) {
    const d = (day + offset) % 7;
    const opens = rows.filter((r) => r.days.includes(d) && (offset > 0 || r.open > min)).map((r) => r.open);
    if (opens.length) return [false, null, [offset, Math.min(...opens)]];
  }
  return [false, null, null];
}

// The widget's single root merges into the wrapper, so el is the .uncoder-opening-hours box.
window.UncoderWB.register('opening-hours', (el, api) => {
  const s = api.settings<HoursSettings>(el);
  const rows = Array.isArray(s.rows) ? s.rows : [];
  const f = s.format === '24' ? '24' : '12';
  const status = el.querySelector<HTMLElement>('.uncoder-opening-hours__status');
  const text = status?.querySelector<HTMLElement>('.uncoder-opening-hours__text') ?? null;

  const update = () => {
    const { day, min } = hoursNow(s.tz || '');
    if (status && text) {
      const [open, close, next] = hoursState(rows, day, min);
      let out: string;
      if (open) out = (s.open || '').replace('{close}', hoursFormat(close ?? 0, f));
      else if (next) {
        const name = next[0] === 0 ? s.today || 'today' : next[0] === 1 ? s.tomorrow || 'tomorrow' : s.days?.[(day + next[0]) % 7] || '';
        out = (s.closed || '').replace('{day}', name).replace('{open}', hoursFormat(next[1], f));
      } else out = (s.closed || '').replace(/\s*·.*$/u, '');
      text.textContent = out.replace(/\s+/g, ' ').trim();
      status.classList.toggle('is-open', open);
      status.classList.toggle('is-closed', !open);
    }
    el.querySelectorAll<HTMLElement>('.uncoder-opening-hours__row').forEach((row) => {
      row.classList.toggle('is-today', (row.dataset.days || '').split(',').map(Number).includes(day));
    });
  };
  update();
  const timer = window.setInterval(update, 60000);
  return () => window.clearInterval(timer);
});
