// Front-end admin bar: "Edit with Uncoder" as a menu of every Uncoder document on the page (the page, its
// header and footer, theme templates, popups, loop items, mega menus), each with its type as a badge, then
// tools (Theme Builder, Design System, Clear Cache). Editor\Admin_Bar adds the node and prints
// window.UncoderAdminBar at the end of the page; hovering a document outlines it on the page.
import './admin-bar.css';

interface Doc {
  id: number;
  title: string;
  type: string;
  label: string;
  url: string;
  current: boolean;
  status: string;
  also: boolean;
}

interface Data {
  docs: Doc[];
  links: Array<{ icon: string; label: string; url: string }>;
  clear: { url: string; nonce: string } | null;
  i18n: { edit: string; clear: string; clearing: string; cleared: string; failed: string };
}

declare global {
  interface Window {
    UncoderAdminBar?: Data;
  }
}

// Lucide icons. No <rect>s: the admin bar resets width and height on every element (#wpadminbar *).
const ICONS: Record<string, string> = {
  templates: '<path d="M4 3h16a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z"/><path d="M4 14h7a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-5a1 1 0 0 1 1-1z"/><path d="M17 14h3a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-3a1 1 0 0 1-1-1v-5a1 1 0 0 1 1-1z"/>',
  design: '<path d="M12 22a1 1 0 0 1 0-20 10 9 0 0 1 10 9 5 5 0 0 1-5 5h-2.25a1.75 1.75 0 0 0-1.4 2.8l.3.4a1.75 1.75 0 0 1-1.4 2.8z"/><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>',
  clear: '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
  done: '<path d="M20 6 9 17l-5-5"/>',
  failed: '<circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/>',
};

const icon = (name: string) =>
  `<svg class="uncoder-ab__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${ICONS[name]}</svg>`;

const el = <K extends keyof HTMLElementTagNameMap>(tag: K, className: string, value = ''): HTMLElementTagNameMap[K] => {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (value) node.textContent = value;
  return node;
};

/* ------------------------------------------------------------------ Outline the hovered document on the page */

let outline: HTMLDivElement | null = null;

function highlight(doc: Doc | null) {
  const parts = doc ? Array.from(document.querySelectorAll<HTMLElement>(`[data-uncoder-doc="${doc.id}"]`)) : [];
  const rects = parts.map((p) => p.getBoundingClientRect()).filter((r) => r.width > 0 && r.height > 0);
  if (!doc || !rects.length) {
    outline?.classList.remove('is-on');
    return;
  }
  if (!outline) {
    outline = document.body.appendChild(el('div', 'uncoder-ab-outline'));
    outline.append(el('span', ''));
  }
  const top = Math.min(...rects.map((r) => r.top));
  const left = Math.min(...rects.map((r) => r.left));
  const bottom = Math.max(...rects.map((r) => r.bottom));
  const right = Math.max(...rects.map((r) => r.right));
  Object.assign(outline.style, {
    top: `${top + window.scrollY}px`,
    left: `${left + window.scrollX}px`,
    width: `${right - left}px`,
    height: `${bottom - top}px`,
  });
  (outline.firstElementChild as HTMLElement).textContent = doc.title;
  outline.classList.add('is-on');
}

/* ------------------------------------------------------------------ Menu */

function docRow(doc: Doc, i18n: Data['i18n']): HTMLLIElement {
  const li = el('li', 'uncoder-ab__row');
  const a = el('a', `ab-item uncoder-ab__doc${doc.current ? ' is-current' : ''}`);
  a.href = doc.url;
  a.setAttribute('aria-label', i18n.edit.replace('%s', doc.title));
  a.append(el('span', 'uncoder-ab__name', doc.title), el('span', 'uncoder-ab__badge', doc.status ? `${doc.label} · ${doc.status}` : doc.label));
  a.addEventListener('mouseenter', () => highlight(doc));
  a.addEventListener('focus', () => highlight(doc));
  a.addEventListener('mouseleave', () => highlight(null));
  a.addEventListener('blur', () => highlight(null));
  li.append(a);
  return li;
}

function toolRow(name: string, label: string, href = '#'): [HTMLLIElement, HTMLAnchorElement, HTMLSpanElement] {
  const li = el('li', 'uncoder-ab__row');
  const a = el('a', 'ab-item uncoder-ab__tool');
  a.href = href;
  a.innerHTML = icon(name);
  const text = el('span', 'uncoder-ab__tool-label', label);
  a.append(text);
  li.append(a);
  return [li, a, text];
}

/** Clear Cache: posts to the REST route and shows Clearing… → Cache cleared (or the error) in place. */
function clearRow(data: Data): HTMLLIElement {
  const { i18n } = data;
  const [li, a, text] = toolRow('clear', i18n.clear);
  a.setAttribute('role', 'button');
  text.setAttribute('aria-live', 'polite');
  let busy = false;
  let reset = 0;
  const show = (state: 'idle' | 'busy' | 'done' | 'failed', label: string) => {
    a.dataset.state = state;
    a.setAttribute('aria-disabled', String(state === 'busy'));
    a.querySelector('svg')!.outerHTML = icon(state === 'done' ? 'done' : state === 'failed' ? 'failed' : 'clear');
    text.textContent = label;
  };
  a.addEventListener('click', async (e) => {
    e.preventDefault();
    if (busy || !data.clear) return;
    busy = true;
    window.clearTimeout(reset);
    show('busy', i18n.clearing);
    try {
      const res = await fetch(data.clear.url, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': data.clear.nonce } });
      show(res.ok ? 'done' : 'failed', res.ok ? i18n.cleared : i18n.failed);
    } catch {
      show('failed', i18n.failed);
    }
    busy = false;
    reset = window.setTimeout(() => show('idle', i18n.clear), 3000);
  });
  return li;
}

function init() {
  const data = window.UncoderAdminBar;
  const node = document.getElementById('wp-admin-bar-uncoder-edit');
  const list = node?.querySelector<HTMLUListElement>('.ab-submenu');
  if (!node || !list) return;
  if (!data?.docs.length) {
    node.remove(); // Nothing on this page can be opened in Uncoder.
    return;
  }
  const top = node.querySelector<HTMLAnchorElement>(':scope > .ab-item');
  if (top) top.href = (data.docs.find((d) => d.current) ?? data.docs[0]).url;

  const items: HTMLElement[] = [];
  let also = false;
  for (const doc of data.docs) {
    // Posts listed on the page (search results, archives) come after a divider.
    if (doc.also && !also && items.length) items.push(el('li', 'uncoder-ab__sep'));
    also ||= doc.also;
    items.push(docRow(doc, data.i18n));
  }
  if (data.links.length || data.clear) {
    items.push(el('li', 'uncoder-ab__sep'));
    for (const link of data.links) items.push(toolRow(link.icon, link.label, link.url)[0]);
    if (data.clear) items.push(clearRow(data));
  }

  list.replaceChildren(...items);
  node.classList.add('is-ready');
  node.addEventListener('mouseleave', () => highlight(null));
  window.addEventListener('scroll', () => highlight(null), { passive: true });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
else init();
