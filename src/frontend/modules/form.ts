// Form: progressive enhancement of the server-rendered form. Without JS the form posts straight to the
// REST endpoint (303 back to the page / HTML error page). With JS: client-side validation that mirrors
// the server rules with inline accessible errors, a fresh signed token on first interaction, fetch
// submit with a loading state, success/error live regions, redirect and reset on success.
window.UncoderWB.register('form', (el, api) => {
  type Messages = Record<
    | 'required' | 'email' | 'url' | 'tel' | 'number' | 'min' | 'max' | 'date' | 'option' | 'length'
    | 'links' | 'file_type' | 'file_size' | 'file' | 'fix' | 'network',
    string
  >;
  interface Settings {
    minTime?: number;
    noLinks?: boolean;
    maxLen?: number;
    tokenUrl?: string;
    messages?: Partial<Messages>;
  }
  interface Result {
    success?: boolean;
    message?: string;
    redirect?: string;
    errors?: Record<string, string>;
    code?: string;
  }
  type Control = HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

  const form = (el.matches('form.uncoder-form') ? (el as HTMLFormElement) : el.querySelector<HTMLFormElement>('form.uncoder-form'));
  // In the editor the form renders normally but never submits (the canvas also blocks submit events).
  if (!form || api.editor) return;

  const s = api.settings<Settings>(el);
  const msg = {
    required: 'This field is required.',
    fix: 'Please correct the highlighted fields.',
    network: 'The form could not be sent. Check your connection and try again.',
    ...s.messages,
  } as Messages;
  const minTime = Math.max(0, Number(s.minTime) || 0);
  const maxLen = Number(s.maxLen) || 5000;
  const button = form.querySelector<HTMLButtonElement>('.uncoder-form__submit');
  const statusEl = form.querySelector<HTMLElement>('.uncoder-form__message--success');
  const alertEl = form.querySelector<HTMLElement>('.uncoder-form__message--error');
  const FIELD = /^fields\[[a-z0-9_]+\](\[\])?$/;
  const LINK = /(https?:\/\/|\bwww\.|<a\s|\[url[\]=])/i;

  form.noValidate = true; // Native validation stays active when JS is off.

  let tokenAt = 0; // performance.now() when the current token was issued (0 ≈ page render).
  let refreshing: Promise<void> | null = null;
  let busy = false;
  let attempted = false;
  let waitTimer = 0;
  let redirectTimer = 0;

  const hidden = (name: string) => form.querySelector<HTMLInputElement>(`input[type="hidden"][name="${name}"]`);
  // Groups hidden by a condition are neither validated nor sent (their controls are disabled).
  const groups = () => Array.from(form.querySelectorAll<HTMLElement>('.uncoder-form__group[data-field]')).filter((g) => !g.hidden);
  const controlsOf = (g: Element) =>
    Array.from(g.querySelectorAll<Control>('input, select, textarea')).filter((c) => FIELD.test(c.name));
  const fill = (text: string | undefined, value: string | number) => (text || '').replace('%s', String(value));
  const sizeLabel = (bytes: number) => `${Math.round((bytes / 1048576) * 10) / 10} MB`;

  /** Mirrors Forms\Fields::validate() (the server re-checks everything). */
  function check(g: HTMLElement): string {
    const type = g.dataset.type || 'text';
    const required = g.dataset.required === '1';
    const controls = controlsOf(g);
    if (!controls.length) return '';

    if (type === 'radio' || type === 'checkbox' || type === 'acceptance') {
      const any = controls.some((c) => (c as HTMLInputElement).checked);
      return required && !any ? msg.required : '';
    }

    const c = controls[0];
    if (type === 'file') {
      const file = (c as HTMLInputElement).files?.[0];
      if (!file) return required ? msg.required : '';
      const accept = (c.getAttribute('accept') || '')
        .split(',')
        .map((x) => x.trim().replace(/^\./, '').toLowerCase())
        .filter(Boolean);
      const ext = (file.name.split('.').pop() || '').toLowerCase();
      if (accept.length && !accept.includes(ext)) return msg.file_type;
      const max = Number(c.getAttribute('data-max-size')) || 0;
      if (max && file.size > max) return fill(msg.file_size, sizeLabel(max));
      return '';
    }

    const v = c.value.trim();
    if (!v) return required ? msg.required : '';
    if (v.length > maxLen) return fill(msg.length, maxLen.toLocaleString());

    switch (type) {
      case 'email':
        if (!/^[^\s@<>()[\],;:"]+@[^\s@<>()[\],;:"]+\.[^\s@<>()[\],;:"]{2,}$/.test(v)) return msg.email;
        break;
      case 'url': {
        const candidate = /^[a-z][a-z0-9+.-]*:\/\//i.test(v) ? v : `http://${v}`;
        try {
          const u = new URL(candidate);
          if (!/^https?:$/.test(u.protocol) || !u.hostname.includes('.')) return msg.url;
        } catch {
          return msg.url;
        }
        break;
      }
      case 'tel':
        if (!/^\+?[0-9\s().\-/]{3,40}$/.test(v) || (v.match(/[0-9]/g) || []).length < 3) return msg.tel;
        break;
      case 'number': {
        const n = Number(v);
        if (!Number.isFinite(n)) return msg.number;
        const min = c.getAttribute('min');
        const max = c.getAttribute('max');
        if (min !== null && min !== '' && n < Number(min)) return fill(msg.min, min);
        if (max !== null && max !== '' && n > Number(max)) return fill(msg.max, max);
        break;
      }
      case 'date': {
        const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v);
        if (!m) return msg.date;
        const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
        if (d.getUTCFullYear() !== +m[1] || d.getUTCMonth() !== +m[2] - 1 || d.getUTCDate() !== +m[3]) return msg.date;
        break;
      }
    }
    if (s.noLinks && (type === 'text' || type === 'textarea') && LINK.test(v)) return msg.links;
    return '';
  }

  /* ---------------------------------------------------------------- Conditional fields */

  interface Rule {
    field: string;
    op: 'is' | 'is_not' | 'contains' | 'filled' | 'empty';
    value: string;
  }
  const conditional = Array.from(form.querySelectorAll<HTMLElement>('.uncoder-form__group[data-show-if]')).map((g) => {
    let rule: Rule | null = null;
    try {
      rule = JSON.parse(g.dataset.showIf || 'null');
    } catch {
      rule = null;
    }
    return { g, rule };
  });

  /** Current answer of a field (empty when the field itself is hidden). */
  function answer(id: string): string[] {
    const g = form!.querySelector<HTMLElement>(`.uncoder-form__group[data-field="${CSS.escape(id)}"]`);
    if (!g || g.hidden) return [];
    return controlsOf(g)
      .filter((c) => !((c as HTMLInputElement).type === 'checkbox' || (c as HTMLInputElement).type === 'radio') || (c as HTMLInputElement).checked)
      .map((c) => c.value.trim())
      .filter(Boolean);
  }

  /** Twin of Forms\Fields::visible(). */
  function passes(rule: Rule | null): boolean {
    if (!rule) return true;
    const list = answer(rule.field);
    const want = (rule.value || '').toLowerCase();
    const has = list.some((v) => v.toLowerCase() === want);
    switch (rule.op) {
      case 'is_not':
        return !has;
      case 'contains':
        return !!want && list.some((v) => v.toLowerCase().includes(want));
      case 'filled':
        return list.length > 0;
      case 'empty':
        return list.length === 0;
      default:
        return has;
    }
  }

  function applyConditions() {
    for (const { g, rule } of conditional) {
      const on = passes(rule);
      if (g.hidden === !on) continue;
      g.hidden = !on;
      for (const c of Array.from(g.querySelectorAll<Control>('input, select, textarea'))) c.disabled = !on;
      if (!on) setError(g, '');
    }
  }

  /* ---------------------------------------------------------------- Steps */

  const steps = Array.from(form.querySelectorAll<HTMLFieldSetElement>('.uncoder-form__step'));
  const nav = form.querySelector<HTMLElement>('.uncoder-form__nav');
  const prevBtn = nav?.querySelector<HTMLButtonElement>('.uncoder-form__prev') ?? null;
  const nextBtn = nav?.querySelector<HTMLButtonElement>('.uncoder-form__next') ?? null;
  const submitGroup = form.querySelector<HTMLElement>('.uncoder-form__group--submit');
  const captcha = form.querySelector<HTMLElement>('.uncoder-form__captcha');
  const multi = steps.length > 1 && !!nav;
  let current = 0;

  function progress(i: number) {
    const bar = form!.querySelector<HTMLElement>('.uncoder-form__progress--bar');
    if (bar) {
      const total = steps.length;
      const count = bar.querySelector<HTMLElement>('.uncoder-form__progress-count');
      const name = bar.querySelector<HTMLElement>('.uncoder-form__progress-name');
      const track = bar.querySelector<HTMLElement>('.uncoder-form__progress-track');
      if (count) count.textContent = (bar.dataset.label || 'Step %1$d of %2$d').replace('%1$d', String(i + 1)).replace('%2$d', String(total));
      const legend = steps[i].querySelector('legend.uncoder-form__step-title')?.textContent ?? '';
      if (name) name.textContent = legend;
      track?.setAttribute('aria-valuenow', String(i + 1));
      const fillEl = track?.querySelector<HTMLElement>('span');
      if (fillEl) fillEl.style.width = `${((i + 1) / total) * 100}%`;
    }
    form!.querySelectorAll<HTMLElement>('.uncoder-form__progress-step').forEach((li, j) => {
      li.classList.toggle('is-current', j === i);
      li.classList.toggle('is-done', j < i);
      if (j === i) li.setAttribute('aria-current', 'step');
      else li.removeAttribute('aria-current');
    });
  }

  function showStep(i: number, focus = true) {
    if (!multi) return;
    current = Math.max(0, Math.min(steps.length - 1, i));
    const last = current === steps.length - 1;
    steps.forEach((st, j) => (st.hidden = j !== current));
    if (prevBtn) prevBtn.hidden = current === 0;
    if (nextBtn) nextBtn.hidden = last;
    if (submitGroup) submitGroup.hidden = !last;
    if (captcha) captcha.hidden = !last;
    progress(current);
    if (focus) {
      const target = steps[current].querySelector<HTMLElement>('input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled)');
      target?.focus();
    }
  }

  /** Validates the groups of one step; returns the first invalid group. */
  function checkStep(i: number): HTMLElement | null {
    let first: HTMLElement | null = null;
    for (const g of groups()) {
      if (!steps[i].contains(g)) continue;
      const m = check(g);
      setError(g, m);
      if (m && !first) first = g;
    }
    return first;
  }

  const stepOf = (g: HTMLElement) => Math.max(0, steps.findIndex((st) => st.contains(g)));

  function setError(g: HTMLElement, message: string) {
    const slot = g.querySelector<HTMLElement>('.uncoder-form__error');
    if (slot && slot.textContent !== message) slot.textContent = message;
    g.classList.toggle('is-invalid', !!message);
    for (const c of controlsOf(g)) {
      if (message) c.setAttribute('aria-invalid', 'true');
      else c.removeAttribute('aria-invalid');
    }
  }

  function focusGroup(g: HTMLElement) {
    const controls = controlsOf(g);
    const target = controls.find((c) => (c as HTMLInputElement).checked) || controls[0];
    target?.focus();
  }

  function say(kind: 'success' | 'error' | '', text = '') {
    if (statusEl) statusEl.textContent = kind === 'success' ? text : '';
    if (alertEl) alertEl.textContent = kind === 'error' ? text : '';
  }

  function setBusy(on: boolean) {
    busy = on;
    form!.classList.toggle('is-loading', on);
    if (!button) return;
    // aria-disabled (not disabled) keeps focus on the button while sending.
    if (on) {
      button.setAttribute('aria-busy', 'true');
      button.setAttribute('aria-disabled', 'true');
    } else {
      button.removeAttribute('aria-busy');
      button.removeAttribute('aria-disabled');
    }
  }

  /** Fresh signed timestamp so the minimum-fill-time check starts when the visitor starts, even on cached pages. */
  function refresh(): Promise<void> {
    if (refreshing) return refreshing;
    const ts = hidden('ts');
    const token = hidden('token');
    if (!s.tokenUrl || !ts || !token) return Promise.resolve();
    let url: URL;
    try {
      url = new URL(s.tokenUrl, window.location.href);
    } catch {
      return Promise.resolve();
    }
    url.searchParams.set('doc_id', hidden('doc_id')?.value || '');
    url.searchParams.set('post_id', hidden('post_id')?.value || '0');
    url.searchParams.set('element_id', hidden('element_id')?.value || '');
    refreshing = fetch(url.toString(), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
      .then((r) => (r.ok ? r.json() : null))
      .then((d: { ts?: number; token?: string } | null) => {
        if (d && d.ts && d.token) {
          ts.value = String(d.ts);
          token.value = d.token;
          tokenAt = performance.now();
        }
      })
      .catch(() => undefined);
    return refreshing;
  }

  const sleep = (ms: number) =>
    new Promise<void>((resolve) => {
      waitTimer = window.setTimeout(resolve, ms);
    });

  async function send() {
    setBusy(true);
    try {
      await refresh();
      // Never submit faster than the server's minimum fill time (it would be filed as spam).
      const wait = minTime * 1000 - (performance.now() - tokenAt) + 300;
      if (minTime && wait > 0) await sleep(wait);

      await recaptchaToken();
      const body = new FormData(form!);
      body.set('_unc_js', '1');
      let result: Result | null = null;
      try {
        const res = await fetch(form!.getAttribute('action') || '', {
          method: 'POST',
          body,
          credentials: 'same-origin',
          headers: { Accept: 'application/json' },
        });
        result = (await res.json().catch(() => null)) as Result | null;
      } catch {
        result = null;
      }
      if (!result || typeof result !== 'object') {
        say('error', msg.network);
        return;
      }

      resetCaptcha();
      if (result.success) {
        form!.reset();
        groups().forEach((g) => setError(g, ''));
        attempted = false;
        applyConditions();
        showStep(0, false);
        say('success', result.message || '');
        form!.dispatchEvent(new CustomEvent('uncoder:form:success', { bubbles: true, detail: result }));
        const to = result.redirect || '';
        if (/^https?:\/\//i.test(to)) {
          redirectTimer = window.setTimeout(() => window.location.assign(to), 900);
        }
        return;
      }

      const errors = result.errors && typeof result.errors === 'object' ? result.errors : {};
      let first: HTMLElement | null = null;
      for (const g of groups()) {
        const m = errors[g.dataset.field || ''] || '';
        setError(g, m);
        if (m && !first) first = g;
      }
      say('error', result.message || msg.network);
      if (first && multi) showStep(stepOf(first), false);
      if (first) focusGroup(first);
      form!.dispatchEvent(new CustomEvent('uncoder:form:error', { bubbles: true, detail: result }));
    } finally {
      setBusy(false);
    }
  }

  /** reCAPTCHA v3 is invisible: fetch a fresh token (valid two minutes) right before each send. */
  async function recaptchaToken() {
    const box = form!.querySelector<HTMLElement>('[data-recaptcha]');
    const input = box?.querySelector<HTMLInputElement>('input[name="g-recaptcha-response"]');
    const g = (window as any).grecaptcha;
    if (!box || !input || !g?.execute) return;
    try {
      input.value = await new Promise<string>((resolve, reject) => {
        g.ready(() => {
          g.execute(box.dataset.recaptcha, { action: box.dataset.action || 'submit' }).then(resolve, reject);
        });
      });
    } catch {
      input.value = '';
    }
  }

  function resetCaptcha() {
    const w = window as any;
    try {
      w.turnstile?.reset?.();
      w.hcaptcha?.reset?.();
      // reCAPTCHA v2 checkbox (v3 has no widget to reset: it fetches a fresh token per send).
      if (form!.querySelector('.g-recaptcha')) w.grecaptcha?.reset?.();
    } catch {
      /* the provider script may not be loaded */
    }
  }

  const onSubmit = (e: Event) => {
    e.preventDefault();
    if (busy) return;
    // Enter in an earlier step moves on instead of sending.
    if (multi && current < steps.length - 1) {
      goNext();
      return;
    }
    attempted = true;
    say('');
    let first: HTMLElement | null = null;
    for (const g of groups()) {
      const m = check(g);
      setError(g, m);
      if (m && !first) first = g;
    }
    if (first) {
      say('error', msg.fix);
      if (multi) showStep(stepOf(first), false);
      focusGroup(first);
      return;
    }
    void send();
  };

  function goNext() {
    const bad = checkStep(current);
    if (bad) {
      focusGroup(bad);
      return;
    }
    showStep(current + 1);
  }
  const onNext = () => goNext();
  const onPrev = () => showStep(current - 1);

  const groupOf = (t: EventTarget | null) =>
    t instanceof Element ? t.closest<HTMLElement>('.uncoder-form__group[data-field]') : null;

  // While typing, only clear an error once the value is valid; full re-check on change / leaving the field.
  const onInput = (e: Event) => {
    applyConditions();
    const g = groupOf(e.target);
    if (g && g.classList.contains('is-invalid') && !check(g)) setError(g, '');
  };
  const onChange = (e: Event) => {
    applyConditions();
    const g = groupOf(e.target);
    if (g && (attempted || g.classList.contains('is-invalid'))) setError(g, check(g));
  };
  const onFocusIn = () => {
    void refresh();
  };

  applyConditions();
  if (multi) {
    form.classList.add('is-stepped');
    showStep(0, false);
    nextBtn?.addEventListener('click', onNext);
    prevBtn?.addEventListener('click', onPrev);
  }
  form.addEventListener('submit', onSubmit);
  form.addEventListener('input', onInput);
  form.addEventListener('change', onChange);
  form.addEventListener('focusout', onChange);
  form.addEventListener('focusin', onFocusIn, { once: true });

  return () => {
    nextBtn?.removeEventListener('click', onNext);
    prevBtn?.removeEventListener('click', onPrev);
    form.removeEventListener('submit', onSubmit);
    form.removeEventListener('input', onInput);
    form.removeEventListener('change', onChange);
    form.removeEventListener('focusout', onChange);
    form.removeEventListener('focusin', onFocusIn);
    window.clearTimeout(waitTimer);
    window.clearTimeout(redirectTimer);
  };
});
