// Loop Grid: "load more" button and infinite scroll (fetch the next page, append its cards) plus JS masonry
// (row spans on a fine grid, so cards keep their left-to-right reading order).
import { elementId } from '../../shared/element';

type Api = Window['UncoderWB'];

interface LoopSettings {
  mode?: 'load_more' | 'infinite';
  loading?: string;
  done?: string;
  error?: string;
}

const MASONRY_ROW = 4; // px; must match grid-auto-rows in loop-grid.css

const isItem = (node: Element): node is HTMLElement => node instanceof HTMLElement && node.classList.contains('uncoder-loop-grid__item');

function masonry(grid: HTMLElement, inOrder: boolean): () => void {
  let frame = 0;
  const items = () => Array.from(grid.children).filter(isItem);
  const layout = () => {
    frame = 0;
    grid.classList.add('is-masonry');
    // Read every height first, then write, to avoid layout thrashing.
    const spans = items().map((item) => {
      const height = item.getBoundingClientRect().height + (parseFloat(getComputedStyle(item).marginBottom) || 0);
      return [item, Math.max(1, Math.ceil(height / MASONRY_ROW))] as const;
    });
    if (!inOrder) {
      // Each card moves up into the shortest column (grid auto-placement).
      for (const [item, span] of spans) item.style.gridRowEnd = `span ${span}`;
      return;
    }
    // "Column by column": card n goes to column n mod columns, under the previous card of that column, so a
    // short / tall pattern (e.g. an alternate card every second place) forms a checkerboard.
    const columns = Math.max(1, getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length);
    const bottoms = new Array<number>(columns).fill(1);
    spans.forEach(([item, span], i) => {
      const col = i % columns;
      item.style.gridColumn = `${col + 1}`;
      item.style.gridRow = `${bottoms[col]} / span ${span}`;
      bottoms[col] += span;
    });
  };
  const schedule = () => {
    if (!frame) frame = requestAnimationFrame(layout);
  };

  const ro = 'ResizeObserver' in window ? new ResizeObserver(schedule) : null;
  const watch = () => items().forEach((item) => ro?.observe(item));
  const mo = new MutationObserver(() => {
    watch();
    schedule();
  });
  ro?.observe(grid);
  watch();
  mo.observe(grid, { childList: true });
  grid.addEventListener('load', schedule, true); // images inside cards
  if (!ro) window.addEventListener('resize', schedule);
  schedule();

  return () => {
    cancelAnimationFrame(frame);
    ro?.disconnect();
    mo.disconnect();
    grid.removeEventListener('load', schedule, true);
    window.removeEventListener('resize', schedule);
    grid.classList.remove('is-masonry');
    items().forEach((item) => ['grid-row-end', 'grid-row', 'grid-column'].forEach((p) => item.style.removeProperty(p)));
  };
}

/** The same widget in a fetched page: same element id inside the same document, same occurrence. */
function twin(doc: Document, el: HTMLElement): HTMLElement | null {
  const id = elementId(el);
  if (!id) return null;
  const selector = `.uncoder-loop-grid.uncoder-${id}`;
  const docOf = (node: Element) => node.parentElement?.closest('[data-uncoder-doc]')?.getAttribute('data-uncoder-doc') ?? '';
  const owner = docOf(el);
  const matches = (scope: ParentNode) => Array.from(scope.querySelectorAll<HTMLElement>(selector)).filter((node) => docOf(node) === owner);
  const index = Math.max(0, matches(document).indexOf(el));
  return matches(doc)[index] ?? null;
}

/** Stylesheets the next page enqueued that this page does not have yet (late template CSS). */
function adoptStyles(doc: Document): void {
  doc.querySelectorAll<HTMLElement>('link[rel="stylesheet"][id^="uncoder-"], style[id^="uncoder-"]').forEach((node) => {
    if (node.id && !document.getElementById(node.id)) document.head.appendChild(document.importNode(node, true));
  });
}

function loader(el: HTMLElement, root: HTMLElement, grid: HTMLElement, s: LoopSettings, api: Api): () => void {
  const wrap = root.querySelector<HTMLElement>(':scope > .uncoder-loop-grid__load');
  const button = wrap?.querySelector<HTMLButtonElement>('.uncoder-loop-grid__more');
  const status = root.querySelector<HTMLElement>(':scope > .uncoder-loop-grid__status');
  if (!wrap || !button) return () => {};

  const text = button.querySelector<HTMLElement>('.uncoder-loop-grid__more-text');
  const label = text?.textContent ?? '';
  const infinite = s.mode === 'infinite' && 'IntersectionObserver' in window;
  const ctrl = new AbortController();
  let busy = false;
  let paused = false;
  let timer = 0;
  let io: IntersectionObserver | null = null;

  const announce = (message: string) => {
    if (!status || !message) return;
    // Clear first so screen readers announce the same sentence again on the next page.
    status.textContent = '';
    window.clearTimeout(timer);
    timer = window.setTimeout(() => (status.textContent = message), 120);
  };

  const setBusy = (on: boolean) => {
    busy = on;
    button.setAttribute('aria-busy', on ? 'true' : 'false');
    grid.setAttribute('aria-busy', on ? 'true' : 'false');
    if (text) text.textContent = on && s.loading ? s.loading : label;
  };

  const rearm = () => {
    // Re-observing reports the current state again, so a sentinel still in view loads the next page.
    if (!io || paused) return;
    io.unobserve(wrap);
    io.observe(wrap);
  };

  const load = async () => {
    const url = button.dataset.next;
    if (busy || !url) return;
    let target: URL;
    try {
      target = new URL(url, window.location.href);
    } catch {
      return;
    }
    if (target.origin !== window.location.origin) return;

    setBusy(true);
    button.classList.remove('is-retry');
    try {
      const response = await fetch(target.href, { credentials: 'same-origin', signal: ctrl.signal });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      const next = twin(doc, el);
      const nextRoot = next?.matches('.uncoder-loop-grid') ? next : next?.querySelector<HTMLElement>(':scope > .uncoder-loop-grid');
      const nextGrid = nextRoot?.querySelector<HTMLElement>(':scope > .uncoder-loop-grid__grid');
      if (!nextRoot || !nextGrid) throw new Error('Loop grid not found on the next page');

      adoptStyles(doc);
      const motion = !api.reducedMotion();
      const fresh = Array.from(nextGrid.children)
        .filter(isItem)
        .map((node) => document.importNode(node, true));
      for (const node of fresh) {
        if (motion) node.classList.add('is-new');
        grid.appendChild(node);
      }
      fresh.forEach((node) => api.init(node));

      const nextUrl = nextRoot.querySelector<HTMLButtonElement>(':scope > .uncoder-loop-grid__load .uncoder-loop-grid__more')?.dataset.next ?? '';
      const loaded = nextGrid.getAttribute('data-loaded') ?? '';
      setBusy(false);

      if (nextUrl && fresh.length) {
        button.dataset.next = nextUrl;
        wrap.querySelector<HTMLAnchorElement>('.uncoder-loop-grid__next-link')?.setAttribute('href', nextUrl);
        announce(loaded);
        rearm();
        return;
      }
      // Last page: remove the button, keeping keyboard focus in the list.
      const hadFocus = document.activeElement === button;
      io?.disconnect();
      wrap.remove();
      announce([loaded, s.done ?? ''].filter(Boolean).join(' '));
      if (hadFocus && fresh[0]) {
        fresh[0].setAttribute('tabindex', '-1');
        fresh[0].focus();
      }
    } catch (error) {
      if ((error as Error).name === 'AbortError') return;
      setBusy(false);
      // Stop auto-loading after a failure; the button becomes visible for a manual retry.
      paused = true;
      button.classList.add('is-retry');
      announce(s.error ?? '');
    }
  };

  const onClick = () => {
    paused = false;
    void load();
  };
  button.addEventListener('click', onClick);

  if (infinite) {
    io = new IntersectionObserver(
      (entries) => {
        if (!paused && entries.some((entry) => entry.isIntersecting)) void load();
      },
      { rootMargin: '0px 0px 600px 0px' },
    );
    io.observe(wrap);
  }

  return () => {
    ctrl.abort();
    io?.disconnect();
    window.clearTimeout(timer);
    button.removeEventListener('click', onClick);
  };
}

window.UncoderWB.register('loop-grid', (el, api) => {
  const root = (el.matches('.uncoder-loop-grid') ? (el as HTMLElement) : el.querySelector<HTMLElement>(':scope > .uncoder-loop-grid'));
  const grid = root?.querySelector<HTMLElement>(':scope > .uncoder-loop-grid__grid');
  if (!root || !grid) return;

  const cleanups: Array<() => void> = [];
  if (root.classList.contains('uncoder-loop-grid--masonry')) cleanups.push(masonry(grid, root.classList.contains('uncoder-loop-grid--masonry-columns')));

  const s = api.settings<LoopSettings>(el);
  if (!api.editor && (s.mode === 'load_more' || s.mode === 'infinite')) cleanups.push(loader(el, root, grid, s, api));

  return () => cleanups.forEach((fn) => fn());
});
