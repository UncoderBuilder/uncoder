// Score Wheel: a check-in. When a visitor picks a score, the row's numbers, that area's wedge, the outline of the lowest
// area, the lowest-area line and the average follow. Nothing is stored or sent. The wheel's geometry (centre, radius)
// is read from the server-drawn SVG, so the two always agree (see Score_Wheel::wedge()).
interface WheelSettings {
  max?: number;
  lowest?: string;
  average?: string;
  label?: string;
  of?: string;
  outline?: boolean;
}

const WHEEL_HUB = 0.14;

function wheelWedge(i: number, n: number, score: number, max: number, cx: number, cy: number, r: number): string {
  const rr = r * WHEEL_HUB + r * (1 - WHEEL_HUB) * Math.max(0, Math.min(1, score / Math.max(1, max)));
  const step = 360 / Math.max(1, n);
  const a0 = ((-90 - step / 2 + i * step) * Math.PI) / 180;
  const a1 = ((-90 + step / 2 + i * step) * Math.PI) / 180;
  const p = (a: number) => `${(cx + rr * Math.cos(a)).toFixed(2)},${(cy + rr * Math.sin(a)).toFixed(2)}`;
  return `M${cx.toFixed(2)},${cy.toFixed(2)} L${p(a0)} A${rr.toFixed(2)},${rr.toFixed(2)} 0 ${step > 180 ? 1 : 0} 1 ${p(a1)} Z`;
}

/** Fills {key} placeholders into a container as text, one key (the area) wrapped in the accent span. */
function wheelFill(el: HTMLElement, template: string, values: Record<string, string>, accent = ''): void {
  el.textContent = '';
  template.split(/(\{[a-z]+\})/).forEach((part) => {
    const key = /^\{([a-z]+)\}$/.exec(part)?.[1];
    if (key && key in values) {
      if (key === accent) {
        const span = document.createElement('span');
        span.className = 'uncoder-score-wheel__accent';
        span.textContent = values[key];
        el.appendChild(span);
      } else el.appendChild(document.createTextNode(values[key]));
    } else el.appendChild(document.createTextNode(part));
  });
}

window.UncoderWB.register('score-wheel', (el, api) => {
  const rows = [...el.querySelectorAll<HTMLFieldSetElement>('.uncoder-score-wheel__row')];
  const svg = el.querySelector<SVGSVGElement>('.uncoder-score-wheel__svg');
  const disc = svg?.querySelector<SVGCircleElement>('.uncoder-score-wheel__disc');
  if (!rows.length || !svg || !disc) return;
  const s = api.settings<WheelSettings>(el);
  const max = s.max || 10;
  const cx = parseFloat(disc.getAttribute('cx') || '0');
  const cy = parseFloat(disc.getAttribute('cy') || '0');
  const r = parseFloat(disc.getAttribute('r') || '0');
  const n = rows.length;
  const names = rows.map((row) => row.querySelector('.uncoder-score-wheel__area')?.textContent?.trim() || '');
  const scores = rows.map((row) => parseInt(row.querySelector<HTMLInputElement>('input:checked')?.value || '0', 10));
  const lowestEl = el.querySelector<HTMLElement>('.uncoder-score-wheel__lowest');
  const averageEl = el.querySelector<HTMLElement>('.uncoder-score-wheel__average');

  const update = () => {
    let low = 0;
    scores.forEach((v, i) => {
      if (v < scores[low]) low = i;
    });
    scores.forEach((v, i) => {
      svg.querySelector(`.uncoder-score-wheel__wedge[data-area="${i}"]`)?.setAttribute('d', wheelWedge(i, n, v, max, cx, cy, r));
      rows[i].querySelectorAll<HTMLLabelElement>('.uncoder-score-wheel__pill').forEach((pill, k) => {
        pill.classList.toggle('is-filled', k + 1 < v);
        pill.classList.toggle('is-score', k + 1 === v);
      });
    });
    const lowPath = svg.querySelector('.uncoder-score-wheel__low');
    lowPath?.setAttribute('d', wheelWedge(low, n, scores[low], max, cx, cy, r));
    if (lowPath) svg.querySelectorAll('.uncoder-score-wheel__name').forEach((t) => t.classList.toggle('is-low', t.getAttribute('data-area') === String(low)));
    if (lowestEl && s.lowest) wheelFill(lowestEl, s.lowest, { area: names[low], score: String(scores[low]), max: String(max) }, 'area');
    if (averageEl && s.average) {
      const avg = scores.reduce((a, b) => a + b, 0) / n;
      wheelFill(averageEl, s.average, { average: avg.toLocaleString(document.documentElement.lang || undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 }), max: String(max) });
    }
    const of = s.of || '%1$s %2$d of %3$d';
    svg.setAttribute('aria-label', `${s.label || ''}: ${names.map((name, i) => of.replace('%1$s', name).replace('%2$d', String(scores[i])).replace('%3$d', String(max))).join(', ')}`);
  };

  const onChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    if (input.type !== 'radio') return;
    const row = input.closest<HTMLFieldSetElement>('.uncoder-score-wheel__row');
    const i = row ? rows.indexOf(row) : -1;
    if (i < 0) return;
    scores[i] = parseInt(input.value, 10) || 0;
    update();
  };
  el.addEventListener('change', onChange);
  return () => el.removeEventListener('change', onChange);
});
