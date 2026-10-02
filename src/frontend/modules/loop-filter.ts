// Loop Filter: filters a Loop Grid / Posts widget without reloading. The widget renders plain links and
// GET forms (Site\Loop_Filters reads uf-{key}-* URL parameters on the server); this module fetches the
// filtered page, swaps the grid and every filter of the same key, and keeps the URL shareable.
import { elementId } from '../../shared/element';

type FilterApi = Window["UncoderWB"];

const PAGE_ARG = 'uncoder_page';
let controller: AbortController | null = null;
let historyReady = false;

function clean(url: URL): URL {
  url.searchParams.delete(PAGE_ARG);
  url.pathname = url.pathname.replace(/\/page\/\d+\/?$/, '/');
  return url;
}

/** URL of a filter form: its fields replace the same parameters of the current URL. */
function formUrl(form: HTMLFormElement): string {
  const url = new URL(window.location.href);
  for (const field of Array.from(form.elements) as HTMLInputElement[]) {
    if (field.name) url.searchParams.delete(field.name.replace(/\[\]$/, ''));
  }
  const grouped: Record<string, string[]> = {};
  new FormData(form).forEach((value, name) => {
    const v = String(value).trim();
    if (v) (grouped[name.replace(/\[\]$/, '')] ??= []).push(v);
  });
  for (const [name, values] of Object.entries(grouped)) url.searchParams.set(name, values.join(','));
  return clean(url).href;
}

/** The node with the same element id in a fetched page. */
const twinOf = (doc: Document, node: HTMLElement) => {
  const id = elementId(node);
  return id ? doc.querySelector<HTMLElement>(`.uncoder-${id}`) : null;
};

function adoptFilterStyles(doc: Document): void {
  doc.querySelectorAll<HTMLElement>('link[rel="stylesheet"][id^="uncoder-"], style[id^="uncoder-"]').forEach((node) => {
    if (node.id && !document.getElementById(node.id)) document.head.appendChild(document.importNode(node, true));
  });
}

async function go(api: FilterApi, key: string, href: string, push = true): Promise<void> {
  const target = new URL(href, window.location.href);
  if (target.origin !== window.location.origin) {
    if (target.protocol === 'https:' || target.protocol === 'http:') window.location.href = target.href;
    return;
  }
  const grids = Array.from(document.querySelectorAll<HTMLElement>(`[data-uncoder-filter-key="${CSS.escape(key)}"]`));
  const filters = Array.from(document.querySelectorAll<HTMLElement>(`[data-uncoder-filter="${CSS.escape(key)}"]`));
  if (!grids.length) {
    window.location.href = target.href;
    return;
  }
  controller?.abort();
  controller = new AbortController();
  grids.forEach((g) => (g.classList.add('is-filtering'), g.setAttribute('aria-busy', 'true')));

  // Remember what had focus to put it back on the same control after the swap.
  const active = document.activeElement as HTMLElement | null;
  const owner = active?.closest<HTMLElement>('[data-uncoder-filter]');
  const focusKey = active && owner ? { id: elementId(owner), name: (active as HTMLInputElement).name, text: active.textContent?.trim() } : null;

  try {
    const response = await fetch(target.href, { credentials: 'same-origin', signal: controller.signal });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
    adoptFilterStyles(doc);
    for (const node of [...grids, ...filters]) {
      const next = twinOf(doc, node);
      if (!next) continue;
      const fresh = document.importNode(next, true);
      api.destroy(node);
      node.replaceWith(fresh);
      api.init(fresh);
    }
    if (push) {
      if (!historyReady) {
        history.replaceState({ uncFilter: key }, '', window.location.href);
        historyReady = true;
      }
      history.pushState({ uncFilter: key }, '', target.href);
    }
    if (focusKey?.id) {
      const box = document.querySelector<HTMLElement>(`.uncoder-${focusKey.id}`);
      const candidates = Array.from(box?.querySelectorAll<HTMLElement>('a, input, select, button') ?? []);
      const match = candidates.find((c) => (focusKey.name && (c as HTMLInputElement).name === focusKey.name) || (!focusKey.name && c.textContent?.trim() === focusKey.text));
      match?.focus({ preventScroll: true });
    }
  } catch (error) {
    if ((error as Error).name === 'AbortError') return;
    window.location.href = target.href; // Fall back to a normal page load.
  } finally {
    document.querySelectorAll<HTMLElement>(`[data-uncoder-filter-key="${CSS.escape(key)}"]`).forEach((g) => (g.classList.remove('is-filtering'), g.removeAttribute('aria-busy')));
  }
}

window.UncoderWB.register('loop-filter', (el, api) => {
  if (api.editor) return;
  const key = el.getAttribute('data-uncoder-filter') || 'loop';
  let timer = 0;

  const onClick = (event: MouseEvent) => {
    const link = (event.target as HTMLElement).closest<HTMLAnchorElement>('a.uncoder-loop-filter__pill');
    if (!link || event.metaKey || event.ctrlKey || event.shiftKey || event.button !== 0) return;
    event.preventDefault();
    void go(api, key, link.href);
  };
  const onChange = (event: Event) => {
    const form = (event.target as HTMLElement).closest('form');
    if (form && !(event.target as HTMLElement).matches('input[type="search"]')) void go(api, key, formUrl(form));
  };
  const onSubmit = (event: SubmitEvent) => {
    event.preventDefault();
    void go(api, key, formUrl(event.target as HTMLFormElement));
  };
  // Live search after a short pause.
  const onInput = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (input.type !== 'search' || !input.form) return;
    window.clearTimeout(timer);
    timer = window.setTimeout(() => void go(api, key, formUrl(input.form!)), 450);
  };

  el.addEventListener('click', onClick);
  el.addEventListener('change', onChange);
  el.addEventListener('submit', onSubmit);
  el.addEventListener('input', onInput);
  return () => {
    window.clearTimeout(timer);
    el.removeEventListener('click', onClick);
    el.removeEventListener('change', onChange);
    el.removeEventListener('submit', onSubmit);
    el.removeEventListener('input', onInput);
  };
});

window.addEventListener('popstate', (event) => {
  const key = (event.state as { uncFilter?: string } | null)?.uncFilter;
  if (key) void go(window.UncoderWB, key, window.location.href, false);
});
