// Interactions (Behaviour → Interactions): "when this happens, do that to this" rows from data-uncoder-ix,
// sanitized on the server (Core\Interactions). Runs on the live page only, never in the editor canvas.
type Row = {
  trigger: 'click' | 'mouseenter' | 'mouseleave' | 'enter' | 'leave' | 'load' | 'scroll';
  action: string;
  target?: 'self' | 'element' | 'selector';
  element?: string;
  selector?: string;
  value?: string;
  offset?: number;
  delay?: number;
};

const HIDDEN = 'uncoder-ix-hidden';
const FOCUSABLE = 'a[href], button, input, select, textarea, summary, [tabindex]';

/** The opposite action, run when a scroll trigger is crossed back (or a view trigger leaves). */
const INVERSE: Record<string, string> = { add_class: 'remove_class', remove_class: 'add_class', show: 'hide', hide: 'show', set_attr: 'remove_attr' };

function targets(el: HTMLElement, row: Row): HTMLElement[] {
  if (row.target === 'element' && row.element) return Array.from(document.querySelectorAll<HTMLElement>(`.uncoder-${row.element}`));
  if (row.target === 'selector' && row.selector) {
    try {
      return Array.from(document.querySelectorAll<HTMLElement>(row.selector));
    } catch {
      return [];
    }
  }
  return [el];
}

function run(el: HTMLElement, row: Row, action = row.action): void {
  const value = row.value ?? '';
  if (action === 'open_popup' || action === 'close_popup') {
    document.dispatchEvent(new CustomEvent('uncoder:popup', { detail: { action: action === 'open_popup' ? 'open' : 'close', id: Number(value) || undefined, trigger: el } }));
    return;
  }
  const list = targets(el, row);
  for (const t of list) {
    switch (action) {
      case 'add_class':
        t.classList.add(...value.split(/\s+/).filter(Boolean));
        break;
      case 'remove_class':
        t.classList.remove(...value.split(/\s+/).filter(Boolean));
        break;
      case 'toggle_class':
        value.split(/\s+/).filter(Boolean).forEach((c) => t.classList.toggle(c));
        break;
      case 'show':
        t.classList.remove(HIDDEN);
        break;
      case 'hide':
        t.classList.add(HIDDEN);
        break;
      case 'toggle':
        t.classList.toggle(HIDDEN);
        break;
      case 'set_attr': {
        // Sanitized on the server (Core\Interactions); checked again here: no handlers, no script URLs.
        const [name, ...rest] = value.split('=');
        const val = rest.join('=');
        if (!name || /^(on|style$|srcdoc$)/i.test(name)) break;
        if (/^(href|src|action|formaction|xlink:href)$/i.test(name) && /^\s*(javascript|vbscript|data):/i.test(val)) break;
        try {
          t.setAttribute(name, val);
        } catch {
          /* invalid attribute name */
        }
        break;
      }
      case 'remove_attr':
        if (value) t.removeAttribute(value.split('=')[0]);
        break;
      case 'scroll_to': {
        const offset = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--uncoder-sticky-h')) || 0;
        const top = t.getBoundingClientRect().top + window.scrollY - offset - 16;
        window.scrollTo({ top: Math.max(0, top), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        break;
      }
    }
  }
  // Show / hide from a click: the trigger says whether what it controls is open.
  if (row.trigger === 'click' && ['show', 'hide', 'toggle'].includes(action) && list.length) el.setAttribute('aria-expanded', String(!list[0].classList.contains(HIDDEN)));
}

window.UncoderWB.register('interactions', (el) => {
  if (document.documentElement.classList.contains('uncoder-editing')) return;
  let rows: Row[] = [];
  try {
    rows = JSON.parse(el.getAttribute('data-uncoder-ix') || '[]');
  } catch {
    return;
  }
  const cleanups: Array<() => void> = [];
  const later = (row: Row, fn: () => void) => {
    if (!row.delay) return fn();
    const t = window.setTimeout(fn, row.delay);
    cleanups.push(() => clearTimeout(t));
  };
  const on = (target: EventTarget, type: string, fn: (e: Event) => void, opts?: AddEventListenerOptions) => {
    target.addEventListener(type, fn, opts);
    cleanups.push(() => target.removeEventListener(type, fn, opts));
  };

  for (const row of rows) {
    switch (row.trigger) {
      case 'click': {
        // A clickable box that is not a control itself (and holds none) becomes one for the keyboard too.
        if (!el.matches(FOCUSABLE) && !el.querySelector(FOCUSABLE)) {
          el.setAttribute('tabindex', '0');
          el.setAttribute('role', 'button');
          on(el, 'keydown', (e) => {
            const k = (e as KeyboardEvent).key;
            if (k === 'Enter' || k === ' ') {
              e.preventDefault();
              later(row, () => run(el, row));
            }
          });
        }
        if (['show', 'hide', 'toggle'].includes(row.action)) {
          const first = targets(el, row)[0];
          if (first) el.setAttribute('aria-expanded', String(!first.classList.contains(HIDDEN)));
        }
        on(el, 'click', (e) => {
          const link = (e.target as Element).closest('a');
          if (link && (link.getAttribute('href') === '#' || link.getAttribute('href') === '')) e.preventDefault();
          later(row, () => run(el, row));
        });
        break;
      }
      case 'mouseenter':
      case 'mouseleave':
        on(el, row.trigger, () => later(row, () => run(el, row)));
        break;
      case 'load':
        later(row, () => run(el, row));
        break;
      case 'enter':
      case 'leave': {
        let seen = false;
        const io = new IntersectionObserver(([entry]) => {
          if (row.trigger === 'enter' && entry.isIntersecting) later(row, () => run(el, row));
          if (row.trigger === 'leave' && !entry.isIntersecting && seen) later(row, () => run(el, row));
          if (entry.isIntersecting) seen = true;
        }, { threshold: 0.15 });
        io.observe(el);
        cleanups.push(() => io.disconnect());
        break;
      }
      case 'scroll': {
        // Crossing the offset runs the action; crossing back runs its opposite (e.g. a header class).
        let past = false;
        let raf = 0;
        const check = () => {
          raf = 0;
          const now = window.scrollY >= (row.offset ?? 100);
          if (now === past) return;
          past = now;
          const inverse = INVERSE[row.action] ?? (row.action.startsWith('toggle') ? row.action : '');
          if (now) run(el, row);
          else if (inverse) run(el, row, inverse);
        };
        on(window, 'scroll', () => (raf ||= requestAnimationFrame(check)), { passive: true });
        check();
        cleanups.push(() => cancelAnimationFrame(raf));
        break;
      }
    }
  }
  return () => cleanups.forEach((fn) => fn());
});

export {};
