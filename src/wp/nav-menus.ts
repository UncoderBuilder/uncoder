// Appearance → Menus: the Icon field of each menu item (Menus\Menu_Item_Extras). A button opens a small
// picker: a library switch (Lucide, the bundled sets, custom sets), a search box and a grid; administrators
// can upload SVG files, which become icons of the "Menu icons" custom set (Site\Custom_Icons rebuilds every
// shape, so the files are never used as-is). The choice goes into a hidden input saved with the menu.
// Also offers to turn on WordPress's Description field when Screen Options hides it.
import './nav-menus.css';

interface Library {
  id: string;
  title: string;
  url: string;
  tags?: string;
}

interface Data {
  libraries: Library[];
  upload: { url: string; nonce: string; set: string; title: string } | null;
  i18n: Record<'choose' | 'search' | 'library' | 'remove' | 'none' | 'loading' | 'upload' | 'uploading' | 'uploadHint' | 'failed' | 'description', string>;
}

/** A loaded library: icon bodies with an optional own viewBox, and search terms. */
interface IconSet {
  viewBox: string;
  mode: 'fill' | 'stroke';
  sw?: number;
  icons: Record<string, string | [string, string]>;
  tags: Record<string, string[]>;
}

declare global {
  interface Window {
    UncoderNavMenus?: Data;
  }
}

const data = window.UncoderNavMenus;

/** Shown in Lucide before anything is typed: icons that suit menu items. */
const COMMON = [
  'house', 'users', 'user', 'briefcase', 'building-2', 'store', 'book-open', 'newspaper', 'library', 'graduation-cap',
  'rocket', 'sparkles', 'zap', 'lightbulb', 'target', 'compass', 'globe', 'map-pin', 'layers', 'blocks',
  'layout-grid', 'box', 'shopping-bag', 'credit-card', 'tag', 'gift', 'award', 'star', 'heart', 'shield-check',
  'lock', 'settings', 'wrench', 'code', 'cpu', 'cloud', 'palette', 'pen-tool', 'camera', 'image',
  'video', 'presentation', 'megaphone', 'calendar', 'clock', 'chart-column', 'trending-up', 'file-text', 'download', 'folder',
  'mail', 'phone', 'message-circle', 'headphones', 'life-buoy', 'circle-help', 'info', 'handshake', 'leaf', 'bell',
];
const LIMIT = 120;

/* ---------------------------------------------------------------- Libraries */

const sets = new Map<string, IconSet>();
const loading = new Map<string, Promise<IconSet | null>>();
const getJson = (url: string) => fetch(url, { credentials: 'same-origin' }).then((r) => (r.ok ? r.json() : null));

function loadSet(id: string): Promise<IconSet | null> {
  if (sets.has(id)) return Promise.resolve(sets.get(id)!);
  const lib = data!.libraries.find((l) => l.id === id);
  if (!lib) return Promise.resolve(null);
  if (!loading.has(id)) {
    const job =
      id === 'lucide'
        ? Promise.all([getJson(lib.url), lib.tags ? getJson(lib.tags).catch(() => ({})) : {}]).then(([icons, tags]) => (icons ? { viewBox: '0 0 24 24', mode: 'stroke' as const, sw: 2, icons, tags: tags ?? {} } : null))
        : getJson(lib.url).then((s) => (s ? { viewBox: s.viewBox ?? '0 0 24 24', mode: s.mode ?? 'fill', sw: s.sw, icons: s.icons ?? {}, tags: s.tags ?? {} } : null));
    loading.set(
      id,
      job
        .then((set: IconSet | null) => {
          if (set) sets.set(id, set);
          loading.delete(id);
          return set;
        })
        .catch(() => {
          loading.delete(id);
          return null;
        }),
    );
  }
  return loading.get(id)!;
}

/** "users" → [lucide, users]; "phosphor:house" → [phosphor, house]. */
const split = (value: string): [string, string] => {
  const at = value.indexOf(':');
  return at > 0 ? [value.slice(0, at), value.slice(at + 1)] : ['lucide', value];
};
const join = (library: string, name: string) => (library === 'lucide' ? name : `${library}:${name}`);

/** SVG markup of a stored value, when its library is loaded ('' otherwise). */
function svg(value: string, size = 18): string {
  const [library, name] = split(value);
  const set = sets.get(library);
  const entry = set?.icons[name];
  if (!set || !entry) return '';
  const [body, box] = Array.isArray(entry) ? entry : [entry, set.viewBox];
  const paint = set.mode === 'stroke' ? `fill="none" stroke="currentColor" stroke-width="${set.sw ?? 2}" stroke-linecap="round" stroke-linejoin="round"` : 'fill="currentColor"';
  return `<svg viewBox="${box}" width="${size}" height="${size}" ${paint} aria-hidden="true" focusable="false">${body}</svg>`;
}

function matches(library: string, query: string): string[] {
  const set = sets.get(library);
  if (!set) return [];
  const names = Object.keys(set.icons);
  const q = query.trim().toLowerCase();
  if (!q) return library === 'lucide' ? COMMON.filter((n) => set.icons[n]) : names.slice(0, LIMIT);
  const named: string[] = [];
  const tagged: string[] = [];
  for (const name of names) {
    if (name.includes(q)) named.push(name);
    else if (set.tags[name]?.some((t) => t.includes(q))) tagged.push(name);
    if (named.length >= LIMIT) break;
  }
  return [...named, ...tagged].slice(0, LIMIT);
}

/* ---------------------------------------------------------------- One picker at a time */

let open: { field: HTMLElement; pop: HTMLElement; onDoc: (e: Event) => void } | null = null;
/** The library the picker shows next (the last one used). */
let lastLibrary = 'lucide';

function close(focus = false) {
  if (!open) return;
  const { field, pop, onDoc } = open;
  open = null;
  pop.remove();
  document.removeEventListener('pointerdown', onDoc, true);
  const pick = field.querySelector<HTMLButtonElement>('.uncoder-mi__pick');
  pick?.setAttribute('aria-expanded', 'false');
  if (focus) pick?.focus();
}

function setValue(field: HTMLElement, value: string) {
  const input = field.querySelector<HTMLInputElement>('input[type="hidden"]')!;
  input.value = value;
  // Lets WordPress know the menu has unsaved changes.
  input.dispatchEvent(new Event('change', { bubbles: true }));
  field.querySelector('.uncoder-mi__preview')!.innerHTML = value ? svg(value) : '';
  field.querySelector('.uncoder-mi__name')!.textContent = value ? split(value)[1] : data!.i18n.choose;
  field.querySelector<HTMLElement>('.uncoder-mi__clear')!.hidden = !value;
}

const el = <K extends keyof HTMLElementTagNameMap>(tag: K, className = '', text = ''): HTMLElementTagNameMap[K] => {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text) node.textContent = text;
  return node;
};

async function toggle(field: HTMLElement) {
  if (open?.field === field) return close(true);
  close();
  const { i18n } = data!;
  const pick = field.querySelector<HTMLButtonElement>('.uncoder-mi__pick')!;
  const current = field.querySelector<HTMLInputElement>('input[type="hidden"]')!.value;
  let library = current ? split(current)[0] : lastLibrary;
  if (!data!.libraries.some((l) => l.id === library)) library = 'lucide';

  const pop = el('div', 'uncoder-mi__pop');
  pop.setAttribute('role', 'dialog');
  pop.setAttribute('aria-label', i18n.choose);
  const bar = el('div', 'uncoder-mi__bar');
  const select = el('select', 'uncoder-mi__lib');
  select.setAttribute('aria-label', i18n.library);
  const fill = () => {
    select.replaceChildren(...data!.libraries.map((l) => Object.assign(el('option', '', l.title), { value: l.id })));
    select.value = library;
  };
  fill();
  const search = el('input', 'uncoder-mi__search');
  search.type = 'search';
  search.placeholder = i18n.search;
  search.setAttribute('aria-label', i18n.search);
  bar.append(select, search);
  const grid = el('div', 'uncoder-mi__grid', i18n.loading);
  grid.setAttribute('role', 'listbox');
  grid.setAttribute('aria-label', i18n.choose);
  pop.append(bar, grid);

  // Upload (administrators): SVG files → the "Menu icons" set; one file is picked straight away.
  const status = el('p', 'uncoder-mi__status');
  status.setAttribute('aria-live', 'polite');
  if (data!.upload) {
    const foot = el('div', 'uncoder-mi__foot');
    const file = el('input');
    file.type = 'file';
    file.accept = '.svg,image/svg+xml';
    file.multiple = true;
    file.hidden = true;
    const up = el('button', 'button button-small uncoder-mi__upload', i18n.upload);
    up.type = 'button';
    up.addEventListener('click', () => file.click());
    file.addEventListener('change', async () => {
      if (!file.files?.length) return;
      const body = new FormData();
      for (const f of Array.from(file.files)) body.append('file[]', f, f.name);
      const { upload } = data!;
      if (data!.libraries.some((l) => l.id === upload!.set)) body.append('append', upload!.set);
      else body.append('title', upload!.title);
      up.disabled = true;
      status.textContent = i18n.uploading;
      try {
        const res = await fetch(upload!.url, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': upload!.nonce }, body });
        const json = await res.json();
        if (!res.ok || !json?.id) throw new Error(json?.message || i18n.failed);
        const entry: Library = { id: json.id, title: json.title ?? upload!.title, url: json.url };
        const at = data!.libraries.findIndex((l) => l.id === entry.id);
        if (at >= 0) data!.libraries[at] = entry;
        else data!.libraries.push(entry);
        sets.delete(entry.id);
        library = lastLibrary = entry.id;
        fill();
        await loadSet(entry.id);
        const added: string[] = Array.isArray(json.added) ? json.added : [];
        if (added.length === 1) {
          setValue(field, join(entry.id, added[0]));
          close(true);
          return;
        }
        status.textContent = '';
        render();
      } catch (err) {
        status.textContent = err instanceof Error ? err.message : i18n.failed;
      } finally {
        up.disabled = false;
        file.value = '';
      }
    });
    foot.append(up, el('span', 'uncoder-mi__foothint', i18n.uploadHint), file);
    pop.append(foot);
  }
  pop.append(status);
  field.append(pop);
  pick.setAttribute('aria-expanded', 'true');

  const onDoc = (e: Event) => {
    if (!field.contains(e.target as Node)) close();
  };
  document.addEventListener('pointerdown', onDoc, true);
  open = { field, pop, onDoc };
  search.focus();

  const render = () => {
    const names = matches(library, search.value);
    grid.replaceChildren();
    if (!names.length) {
      grid.textContent = sets.has(library) ? i18n.none : i18n.loading;
      return;
    }
    for (const name of names) {
      const value = join(library, name);
      const b = el('button', 'uncoder-mi__cell' + (value === current ? ' is-active' : ''));
      b.type = 'button';
      b.setAttribute('role', 'option');
      b.setAttribute('aria-selected', String(value === current));
      b.setAttribute('aria-label', name);
      b.title = name;
      b.dataset.value = value;
      b.innerHTML = svg(value, 20);
      grid.append(b);
    }
  };
  const show = async () => {
    grid.textContent = i18n.loading;
    await loadSet(library);
    if (open?.pop === pop) render();
  };

  select.addEventListener('change', () => {
    library = lastLibrary = select.value;
    void show();
  });
  search.addEventListener('input', render);
  grid.addEventListener('click', (e) => {
    const cell = (e.target as Element).closest<HTMLButtonElement>('.uncoder-mi__cell');
    if (!cell) return;
    lastLibrary = library;
    setValue(field, cell.dataset.value!);
    close(true);
  });
  pop.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
      close(true);
      return;
    }
    const cells = Array.from(grid.querySelectorAll<HTMLButtonElement>('.uncoder-mi__cell'));
    const inGrid = document.activeElement instanceof HTMLButtonElement && cells.includes(document.activeElement);
    // WordPress cancels Enter inside the menu form (no accidental submit), so pick here: the focused icon,
    // or the first result from the search box.
    if ((e.key === 'Enter' && (inGrid || document.activeElement === search)) || (e.key === ' ' && inGrid)) {
      e.preventDefault();
      e.stopPropagation();
      const cell = inGrid ? (document.activeElement as HTMLButtonElement) : cells[0];
      if (cell) {
        lastLibrary = library;
        setValue(field, cell.dataset.value!);
        close(true);
      }
      return;
    }
    // Arrow keys move through the grid; Down from the search box enters it.
    if (!cells.length || (!inGrid && document.activeElement !== search)) return;
    const cols = Math.max(1, Math.round(grid.clientWidth / (cells[0].offsetWidth || 34)));
    const step = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: cols, ArrowUp: -cols }[e.key];
    if (!step) return;
    if (!inGrid) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        cells[0].focus();
      }
      return;
    }
    e.preventDefault();
    const next = cells.indexOf(document.activeElement as HTMLButtonElement) + step;
    if (next < 0) search.focus();
    else cells[Math.min(cells.length - 1, next)].focus();
  });

  await show();
}

/* ---------------------------------------------------------------- Description field */

/** WordPress's Description field is off in Screen Options: offer to turn it on (once for all items). */
function offerDescriptions() {
  const box = document.getElementById('description-hide') as HTMLInputElement | null;
  document.querySelectorAll('.uncoder-mi__desc-on').forEach((b) => b.remove());
  if (!box || box.checked) return;
  document.querySelectorAll<HTMLElement>('.field-uncoder-icon').forEach((field) => {
    const b = el('button', 'button-link uncoder-mi__desc-on', data!.i18n.description);
    b.type = 'button';
    field.append(b);
  });
}

function init() {
  if (!data) return;
  document.addEventListener('click', (e) => {
    const target = e.target as Element;
    const field = target.closest<HTMLElement>('[data-uncoder-icon-field]');
    if (field && target.closest('.uncoder-mi__pick')) {
      e.preventDefault();
      void toggle(field);
    } else if (field && target.closest('.uncoder-mi__clear')) {
      e.preventDefault();
      close();
      setValue(field, '');
      field.querySelector<HTMLButtonElement>('.uncoder-mi__pick')?.focus();
    } else if (target.closest('.uncoder-mi__desc-on')) {
      e.preventDefault();
      (document.getElementById('description-hide') as HTMLInputElement | null)?.click();
    } else if (target.closest('#description-hide')) {
      window.setTimeout(offerDescriptions);
    }
  });
  offerDescriptions();
  // Items added with "Add to Menu" arrive later with their own fields.
  const list = document.getElementById('menu-to-edit');
  if (list) new MutationObserver(() => offerDescriptions()).observe(list, { childList: true });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
