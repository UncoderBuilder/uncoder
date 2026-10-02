// Search form: full-screen search overlay (modal <dialog>) opened from an icon toggle, and optional live
// results (an ARIA 1.2 combobox: the field owns a listbox of links, arrow keys move the highlight).
import type { UncoderApi } from '../runtime';

interface LiveResult {
  id: number;
  title: string;
  url: string;
  type: string;
  image?: string;
  excerpt?: string;
}

interface LiveOptions {
  n: number;
  image: boolean;
  excerpt: boolean;
  all: boolean;
  type: string;
  i18n: { none: string; all: string; count: string; loading: string };
}

function overlay(el: HTMLElement, api: UncoderApi): (() => void) | undefined {
  const toggle = el.querySelector<HTMLButtonElement>('.uncoder-search__toggle');
  const dialog = el.querySelector<HTMLDialogElement>('.uncoder-search__dialog');
  if (!toggle || !dialog || typeof dialog.showModal !== 'function') return undefined;
  const html = document.documentElement;
  let timer = 0;

  const lockScroll = (lock: boolean) => {
    const count = Number(html.dataset.uncoderLocks || '0') + (lock ? 1 : -1);
    html.dataset.uncoderLocks = String(Math.max(0, count));
    if (lock && count === 1) {
      html.style.setProperty('--uncoder-scrollbar-w', `${window.innerWidth - html.clientWidth}px`);
      html.classList.add('uncoder-scroll-lock');
    } else if (count <= 0) {
      html.classList.remove('uncoder-scroll-lock');
      html.style.removeProperty('--uncoder-scrollbar-w');
    }
  };

  const open = () => {
    if (dialog.open) return;
    window.clearTimeout(timer);
    dialog.showModal();
    lockScroll(true);
    toggle.setAttribute('aria-expanded', 'true');
    dialog.querySelector<HTMLInputElement>('.uncoder-search__input')?.focus();
    requestAnimationFrame(() => requestAnimationFrame(() => dialog.classList.add('is-open')));
  };

  const close = () => {
    if (!dialog.open || toggle.getAttribute('aria-expanded') !== 'true') return;
    toggle.setAttribute('aria-expanded', 'false');
    dialog.classList.remove('is-open');
    lockScroll(false);
    timer = window.setTimeout(() => {
      if (dialog.open) dialog.close();
      toggle.focus();
    }, api.reducedMotion() ? 0 : 250);
  };

  const onToggle = () => open();
  const onClick = (e: MouseEvent) => {
    if ((e.target as Element).closest('[data-uncoder-search-close]')) close();
  };
  const onCancel = (e: Event) => {
    e.preventDefault();
    // Escape first closes open live results (handled by the field), then the overlay.
    if (dialog.querySelector('.uncoder-search__input[aria-expanded="true"]')) return;
    close();
  };

  toggle.addEventListener('click', onToggle);
  dialog.addEventListener('click', onClick);
  dialog.addEventListener('cancel', onCancel);

  return () => {
    toggle.removeEventListener('click', onToggle);
    dialog.removeEventListener('click', onClick);
    dialog.removeEventListener('cancel', onCancel);
    window.clearTimeout(timer);
    if (toggle.getAttribute('aria-expanded') === 'true') {
      toggle.setAttribute('aria-expanded', 'false');
      lockScroll(false);
    }
    if (dialog.open) dialog.close();
    dialog.classList.remove('is-open');
  };
}

function live(form: HTMLFormElement, api: UncoderApi): (() => void) | undefined {
  const input = form.querySelector<HTMLInputElement>('.uncoder-search__input');
  const list = form.querySelector<HTMLElement>('.uncoder-search__results');
  const status = form.querySelector<HTMLElement>('.uncoder-search__status');
  const rest = String(api.config.rest || '');
  let opts: LiveOptions;
  try {
    opts = JSON.parse(form.dataset.live || '{}');
  } catch {
    return undefined;
  }
  if (!input || !list || !rest) return undefined;

  let timer = 0;
  let controller: AbortController | null = null;
  let active = -1;
  let lastTerm = '';
  const cache = new Map<string, { results: LiveResult[]; total: number; all: string }>();

  const items = () => Array.from(list.querySelectorAll<HTMLAnchorElement>('[role="option"]'));

  const setOpen = (open: boolean) => {
    list.hidden = !open;
    input.setAttribute('aria-expanded', open ? 'true' : 'false');
    form.classList.toggle('is-live-open', open);
    if (!open) setActive(-1);
  };

  const setActive = (index: number) => {
    const all = items();
    active = all.length ? Math.max(-1, Math.min(index, all.length - 1)) : -1;
    all.forEach((a, i) => a.setAttribute('aria-selected', i === active ? 'true' : 'false'));
    if (active >= 0) {
      input.setAttribute('aria-activedescendant', all[active].id);
      all[active].scrollIntoView({ block: 'nearest' });
    } else {
      input.removeAttribute('aria-activedescendant');
    }
  };

  /** Title with the typed words marked, built from text nodes only. */
  const highlight = (text: string, term: string) => {
    const frag = document.createDocumentFragment();
    const words = term.split(/\s+/).filter((w) => w.length > 1).map((w) => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    if (!words.length) {
      frag.append(text);
      return frag;
    }
    const re = new RegExp(`(${words.join('|')})`, 'gi');
    text.split(re).forEach((part, i) => {
      if (!part) return;
      if (i % 2) {
        const mark = document.createElement('mark');
        mark.textContent = part;
        frag.append(mark);
      } else {
        frag.append(part);
      }
    });
    return frag;
  };

  const render = (term: string, data: { results: LiveResult[]; total: number; all: string }) => {
    list.replaceChildren();
    const base = list.id || 'uncoder-search-results';
    data.results.forEach((r, i) => {
      const a = document.createElement('a');
      a.className = 'uncoder-search__result';
      a.id = `${base}-${i}`;
      a.href = r.url;
      a.setAttribute('role', 'option');
      a.setAttribute('aria-selected', 'false');
      a.tabIndex = -1;
      if (opts.image) {
        const media = document.createElement('span');
        media.className = 'uncoder-search__result-media';
        if (r.image) {
          const img = document.createElement('img');
          img.src = r.image;
          img.alt = '';
          img.loading = 'lazy';
          img.decoding = 'async';
          media.append(img);
        }
        a.append(media);
      }
      const body = document.createElement('span');
      body.className = 'uncoder-search__result-body';
      const title = document.createElement('span');
      title.className = 'uncoder-search__result-title';
      title.append(highlight(r.title, term));
      body.append(title);
      if (r.type) {
        const type = document.createElement('span');
        type.className = 'uncoder-search__result-type';
        type.textContent = r.type;
        body.append(type);
      }
      if (opts.excerpt && r.excerpt) {
        const ex = document.createElement('span');
        ex.className = 'uncoder-search__result-excerpt';
        ex.textContent = r.excerpt;
        body.append(ex);
      }
      a.append(body);
      list.append(a);
    });
    if (!data.results.length) {
      const empty = document.createElement('p');
      empty.className = 'uncoder-search__empty';
      empty.textContent = opts.i18n.none;
      list.append(empty);
    } else if (opts.all && data.total > data.results.length) {
      const all = document.createElement('a');
      all.className = 'uncoder-search__all';
      all.id = `${base}-all`;
      all.href = data.all;
      all.setAttribute('role', 'option');
      all.setAttribute('aria-selected', 'false');
      all.tabIndex = -1;
      all.textContent = `${opts.i18n.all} (${data.total})`;
      list.append(all);
    }
    setOpen(true);
    setActive(-1);
    if (status) status.textContent = data.results.length ? opts.i18n.count.replace('%d', String(data.results.length)) : opts.i18n.none;
  };

  const run = async (term: string) => {
    lastTerm = term;
    const key = term.toLowerCase();
    const hit = cache.get(key);
    if (hit) {
      render(term, hit);
      return;
    }
    controller?.abort();
    controller = new AbortController();
    form.classList.add('is-searching');
    const params = new URLSearchParams({ s: term, per_page: String(opts.n || 5), image: opts.image ? '1' : '0', excerpt: opts.excerpt ? '1' : '0' });
    if (opts.type) params.set('post_type', opts.type);
    try {
      const res = await fetch(`${rest}live-search?${params}`, { signal: controller.signal, headers: { Accept: 'application/json' } });
      const data = await res.json();
      if (!res.ok || !Array.isArray(data?.results)) throw new Error('bad response');
      cache.set(key, data);
      if (term === lastTerm) render(term, data);
    } catch (e) {
      if ((e as Error).name !== 'AbortError') setOpen(false);
    } finally {
      form.classList.remove('is-searching');
    }
  };

  const onInput = () => {
    window.clearTimeout(timer);
    const term = input.value.trim();
    if (term.length < 2) {
      controller?.abort();
      lastTerm = '';
      setOpen(false);
      if (status) status.textContent = '';
      return;
    }
    timer = window.setTimeout(() => run(term), 220);
  };

  const onKey = (e: KeyboardEvent) => {
    const open = !list.hidden;
    const count = items().length;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      if (!open) {
        if (input.value.trim().length >= 2 && list.childElementCount) setOpen(true);
        return;
      }
      e.preventDefault();
      if (!count) return;
      const next = e.key === 'ArrowDown' ? (active + 1) % count : active <= 0 ? count - 1 : active - 1;
      setActive(next);
    } else if (e.key === 'Enter' && open && active >= 0) {
      e.preventDefault();
      window.location.assign(items()[active].href);
    } else if (e.key === 'Escape') {
      if (open) {
        e.preventDefault();
        e.stopPropagation();
        setOpen(false);
      }
    } else if (e.key === 'Home' || e.key === 'End') {
      if (open && active >= 0) {
        e.preventDefault();
        setActive(e.key === 'Home' ? 0 : count - 1);
      }
    }
  };

  const onFocus = () => {
    if (input.value.trim().length >= 2 && list.childElementCount && input.value.trim() === lastTerm) setOpen(true);
  };
  const onDocPointer = (e: PointerEvent) => {
    if (!form.contains(e.target as Node)) setOpen(false);
  };
  const onFocusOut = (e: FocusEvent) => {
    if (!form.contains(e.relatedTarget as Node | null)) setOpen(false);
  };
  // Keep the field focused while clicking a result (the link still follows).
  const onListDown = (e: PointerEvent) => {
    if ((e.target as Element).closest('a')) e.preventDefault();
  };

  input.addEventListener('input', onInput);
  input.addEventListener('keydown', onKey);
  input.addEventListener('focus', onFocus);
  form.addEventListener('focusout', onFocusOut);
  list.addEventListener('pointerdown', onListDown);
  document.addEventListener('pointerdown', onDocPointer);

  return () => {
    window.clearTimeout(timer);
    controller?.abort();
    input.removeEventListener('input', onInput);
    input.removeEventListener('keydown', onKey);
    input.removeEventListener('focus', onFocus);
    form.removeEventListener('focusout', onFocusOut);
    list.removeEventListener('pointerdown', onListDown);
    document.removeEventListener('pointerdown', onDocPointer);
    setOpen(false);
    list.replaceChildren();
  };
}

window.UncoderWB.register('search-form', (el, api) => {
  const cleanups = [overlay(el, api), ...Array.from(el.querySelectorAll<HTMLFormElement>('.uncoder-search__form[data-live]')).map((f) => live(f, api))].filter(
    (fn): fn is () => void => typeof fn === 'function',
  );
  if (!cleanups.length) return;
  return () => cleanups.forEach((fn) => fn());
});
