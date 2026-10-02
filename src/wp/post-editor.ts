// WordPress's post editors (block editor; the classic editor gets the same panel from PHP). Adds an
// "Edit with Uncoder" button to the header, and for posts built with Uncoder covers the content area
// with a panel that sends you to Uncoder: the blocks only hold a plain copy of the design (kept for
// search engines, feeds and deactivation), so editing them would change nothing visitors see.
import { APP_ICON, U_LEFT, U_RIGHT } from '../shared/brand';
import './post-editor.css';

interface Config {
  editUrl: string;
  switchUrl: string;
  builder: boolean;
  isNew?: boolean;
  i18n: { edit: string; title: string; text: string; switch: string; confirm: string };
}

(() => {
  const cfg = (window as unknown as { UncoderPostEditor?: Config }).UncoderPostEditor;
  if (!cfg) return;

  const mark = (size: number) =>
    `<svg class="uncoder-pe-mark" width="${Math.round((size * 92) / 96)}" height="${size}" viewBox="0 0 92 96" fill="currentColor" aria-hidden="true" focusable="false"><path d="${U_LEFT}"/><path d="${U_RIGHT}"/></svg>`;

  // The round app icon (Brand::app_icon() in PHP).
  const appIcon = (size: number) =>
    `<svg class="uncoder-app-icon" width="${size}" height="${size}" viewBox="0 0 256 256" aria-hidden="true" focusable="false"><circle cx="128" cy="128" r="128" fill="#083241"/><g transform="translate(${APP_ICON.x} ${APP_ICON.y}) scale(${APP_ICON.scale})" fill="#fff"><path d="${U_LEFT}"/><path d="${U_RIGHT}"/></g></svg>`;

  /**
   * Block editor: a post that was never saved, or has unsaved edits, is saved first so Uncoder opens what you
   * see here (and Polylang links a new translation to its original, which it does on save).
   */
  const saveThenGo = async (e: MouseEvent) => {
    const wp = (window as unknown as { wp?: any }).wp;
    const editor = wp?.data?.select?.('core/editor');
    if (!editor || !(cfg.isNew || editor.isEditedPostDirty?.())) return;
    e.preventDefault();
    const link = e.currentTarget as HTMLAnchorElement;
    link.setAttribute('aria-busy', 'true');
    try {
      await wp.data.dispatch('core/editor').savePost();
    } finally {
      window.location.href = cfg.editUrl;
    }
  };

  const editButton = (large: boolean) => {
    const a = document.createElement('a');
    a.href = cfg.editUrl;
    a.className = large ? 'uncoder-pe-btn uncoder-pe-btn--lg' : 'uncoder-pe-btn';
    a.innerHTML = `${mark(large ? 18 : 14)}<span></span>`;
    a.querySelector('span')!.textContent = cfg.i18n.edit;
    a.addEventListener('click', saveThenGo);
    return a;
  };

  const panel = () => {
    const root = document.createElement('div');
    root.className = 'uncoder-pe-takeover';
    root.innerHTML = `<div class="uncoder-pe-card" role="region"><span class="uncoder-pe-tile">${appIcon(64)}</span><h2></h2><p></p><div class="uncoder-pe-actions"></div><a class="uncoder-pe-switch" href="#"></a></div>`;
    root.querySelector('.uncoder-pe-card')!.setAttribute('aria-label', cfg.i18n.title);
    root.querySelector('h2')!.textContent = cfg.i18n.title;
    root.querySelector('p')!.textContent = cfg.i18n.text;
    root.querySelector('.uncoder-pe-actions')!.appendChild(editButton(true));
    const sw = root.querySelector<HTMLAnchorElement>('.uncoder-pe-switch')!;
    sw.textContent = cfg.i18n.switch;
    sw.href = cfg.switchUrl;
    sw.addEventListener('click', (e) => {
      if (!window.confirm(cfg.i18n.confirm)) e.preventDefault();
    });
    return root;
  };

  // The editor re-renders its header and content (mode switches, panels), so re-attach as needed.
  const mount = () => {
    const bar = document.querySelector('.editor-header__toolbar, .edit-post-header-toolbar');
    if (bar && !document.getElementById('uncoder-edit-btn')) {
      const btn = editButton(false);
      btn.id = 'uncoder-edit-btn';
      bar.appendChild(btn);
    }
    if (!cfg.builder) return;
    document.body.classList.add('uncoder-pe-built');
    for (const area of document.querySelectorAll<HTMLElement>('.editor-visual-editor, .edit-post-visual-editor, .editor-text-editor, .edit-post-text-editor')) {
      if (!area.querySelector(':scope > .uncoder-pe-takeover')) area.appendChild(panel());
    }
  };

  let queued = false;
  const schedule = () => {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => {
      queued = false;
      mount();
    });
  };
  const start = () => {
    mount();
    new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
