// Pasted HTML → elements (a web page, an AI answer, code): headings, text, images, buttons, quotes, dividers,
// videos, boxes as containers; anything else becomes an HTML widget. Classes become CSS classes and <style>
// blocks are returned as CSS for the section. The result is ordinary tree nodes (sanitized on save).
import type { ElementNode, Settings } from '@shared/types';

const BOX = new Set(['DIV', 'SECTION', 'HEADER', 'FOOTER', 'MAIN', 'ARTICLE', 'ASIDE', 'NAV', 'FIGURE']);
const SEMANTIC: Record<string, string> = { SECTION: 'section', HEADER: 'header', FOOTER: 'footer', MAIN: 'main', ARTICLE: 'article', ASIDE: 'aside', NAV: 'nav' };
const TEXT = new Set(['P', 'UL', 'OL', 'DL', 'PRE', 'ADDRESS', 'TABLE']);
const INLINE = new Set(['A', 'ABBR', 'B', 'BR', 'CITE', 'CODE', 'EM', 'I', 'KBD', 'MARK', 'Q', 'S', 'SMALL', 'SPAN', 'STRONG', 'SUB', 'SUP', 'TIME', 'U', 'DEL', 'INS']);
const DROP = new Set(['SCRIPT', 'NOSCRIPT', 'TEMPLATE', 'LINK', 'META', 'TITLE', 'HEAD']);

const node = (type: string, settings: Settings = {}, children: ElementNode[] = []): ElementNode => ({ id: '', type, settings, children });

/** px (or unitless) → number; anything else (em, %) is left out. */
const px = (v: string): number | null => {
  const m = /^(-?\d+(?:\.\d+)?)(px)?$/.exec(v.trim());
  return m ? Number(m[1]) : null;
};

/** CSS padding shorthand in px → dimensions value. */
function dims(v: string): Settings | null {
  const parts = v.trim().split(/\s+/).map(px);
  if (!parts.length || parts.some((p) => p === null)) return null;
  const [t, r = t, b = t, l = r] = parts as number[];
  return { top: t, right: r, bottom: b, left: l, unit: 'px', linked: t === r && r === b && b === l };
}

function classes(el: Element): Settings {
  const cls = Array.from(el.classList).filter((c) => /^[A-Za-z_-][A-Za-z0-9_-]*$/.test(c)).join(' ');
  return cls ? { _css_classes: cls } : {};
}

/** Inline HTML worth keeping as text (links, emphasis…), without attributes other than href / target. */
function inlineHtml(el: Element): string {
  const clone = el.cloneNode(true) as Element;
  clone.querySelectorAll('*').forEach((n) => {
    for (const a of Array.from(n.attributes)) if (!['href', 'target', 'rel'].includes(a.name)) n.removeAttribute(a.name);
  });
  return clone.innerHTML.trim();
}

const isInlineOnly = (el: Element): boolean => Array.from(el.children).every((c) => INLINE.has(c.tagName));
const looksLikeButton = (a: Element): boolean => /(^|[\s_-])(btn|button|cta)([\s_-]|$)/i.test(a.getAttribute('class') ?? '') || a.getAttribute('role') === 'button';

function video(src: string): ElementNode | null {
  const yt = /youtube(?:-nocookie)?\.com\/embed\/([\w-]{6,})/.exec(src)?.[1];
  if (yt) return node('video', { source: 'youtube', youtube_url: `https://www.youtube.com/watch?v=${yt}` });
  if (/youtube\.com\/watch|youtu\.be\//.test(src)) return node('video', { source: 'youtube', youtube_url: src });
  const vimeo = /vimeo\.com\/(?:video\/)?(\d+)/.exec(src)?.[1];
  if (vimeo) return node('video', { source: 'vimeo', vimeo_url: `https://vimeo.com/${vimeo}` });
  return null;
}

function convert(el: Element, out: ElementNode[], css: string[]): void {
  const tag = el.tagName;
  if (DROP.has(tag)) return;
  if (tag === 'STYLE') {
    css.push(el.textContent ?? '');
    return;
  }
  const style = (el as HTMLElement).style;
  if (/^H[1-6]$/.test(tag)) {
    const s: Settings = { title: inlineHtml(el), tag: tag.toLowerCase(), ...classes(el) };
    if (style?.color) s.color = style.color;
    if (['left', 'center', 'right'].includes(style?.textAlign)) s.align = style.textAlign;
    out.push(node('heading', s));
    return;
  }
  if (tag === 'IMG') {
    const src = el.getAttribute('src') ?? '';
    if (!src || src.startsWith('data:')) return;
    out.push(node('image', { image: { id: 0, url: src, alt: el.getAttribute('alt') ?? '' }, ...classes(el) }));
    return;
  }
  if (tag === 'A') {
    const img = el.querySelector('img');
    if (img && el.textContent?.trim() === '') {
      const src = img.getAttribute('src') ?? '';
      if (src && !src.startsWith('data:')) out.push(node('image', { image: { id: 0, url: src, alt: img.getAttribute('alt') ?? '' }, link_to: 'custom', link: { url: el.getAttribute('href') ?? '' } }));
      return;
    }
    out.push(node('button', { text: (el.textContent ?? '').trim() || 'Button', link: { url: el.getAttribute('href') ?? '', external: el.getAttribute('target') === '_blank' }, ...classes(el) }));
    return;
  }
  if (tag === 'BUTTON') {
    out.push(node('button', { text: (el.textContent ?? '').trim() || 'Button', ...classes(el) }));
    return;
  }
  if (tag === 'HR') {
    out.push(node('divider'));
    return;
  }
  if (tag === 'BLOCKQUOTE') {
    const cite = el.querySelector('cite, footer');
    const quote = (cite ? Array.from(el.childNodes).filter((n) => n !== cite && !(n as Element).contains?.(cite)).map((n) => n.textContent).join(' ') : el.textContent ?? '').trim();
    out.push(node('blockquote', { quote, ...(cite ? { citation: (cite.textContent ?? '').replace(/^[—–-]\s*/, '').trim() } : {}) }));
    return;
  }
  if (tag === 'IFRAME') {
    const v = video(el.getAttribute('src') ?? '');
    out.push(v ?? node('html', { html: el.outerHTML }));
    return;
  }
  if (TEXT.has(tag) || (BOX.has(tag) && isInlineOnly(el) && el.textContent?.trim())) {
    // Paragraphs and lists in a row become one text block.
    const html = TEXT.has(tag) ? el.outerHTML.replace(/\s(class|style|id)="[^"]*"/g, '') : `<p>${inlineHtml(el)}</p>`;
    const last = out[out.length - 1];
    if (last?.type === 'text-editor' && !last.settings._css_classes) last.settings.content = `${last.settings.content}\n${html}`;
    else out.push(node('text-editor', { content: html, ...(TEXT.has(tag) ? {} : classes(el)) }));
    return;
  }
  if (BOX.has(tag)) {
    const children: ElementNode[] = [];
    for (const child of Array.from(el.childNodes)) {
      if (child.nodeType === Node.TEXT_NODE) {
        const text = child.textContent?.trim();
        if (text) children.push(node('text-editor', { content: `<p>${text.replace(/</g, '&lt;')}</p>` }));
      } else if (child.nodeType === Node.ELEMENT_NODE) convert(child as Element, children, css);
    }
    if (tag === 'FIGURE' && children.length <= 2 && children[0]?.type === 'image') {
      const caption = el.querySelector('figcaption')?.textContent?.trim();
      if (caption) children[0].settings = { ...children[0].settings, caption_source: 'custom', caption };
      out.push(children[0]);
      return;
    }
    if (!children.length) return;
    // A wrapper around one thing adds nothing.
    if (children.length === 1 && !el.getAttribute('class') && !el.getAttribute('style') && !SEMANTIC[tag]) {
      out.push(children[0]);
      return;
    }
    const s: Settings = { ...classes(el) };
    if (SEMANTIC[tag]) s.tag = SEMANTIC[tag];
    if (style?.display === 'flex' || style?.display === 'inline-flex') s.direction = style.flexDirection && style.flexDirection !== 'row' ? style.flexDirection : 'row';
    if (style?.display === 'grid') {
      s.layout = 'grid';
      const cols = /repeat\(\s*(\d+)/.exec(style.gridTemplateColumns)?.[1] ?? String(style.gridTemplateColumns.split(/\s+/).filter(Boolean).length || '');
      if (cols && Number(cols) > 0) s.grid_columns = Math.min(12, Number(cols));
    }
    const gap = style?.gap ? px(style.gap.split(/\s+/)[0]) : null;
    if (gap !== null) s.gap = { size: gap, unit: 'px' };
    const pad = style?.padding ? dims(style.padding) : null;
    if (pad) s._padding = pad;
    if (style?.backgroundColor) s.background = { type: 'classic', color: style.backgroundColor };
    out.push(node('container', s, children));
    return;
  }
  // SVG, forms, tables of data, custom elements… stay exactly as they are.
  if (el.outerHTML.trim()) out.push(node('html', { html: el.outerHTML }));
}

/** Whether clipboard text is HTML markup (a tag at the start). */
export const isHtml = (text: string): boolean => /^\s*<(!doctype|html|body|[a-z][a-z0-9-]*[\s>/])/i.test(text);

export function htmlToElements(html: string): { nodes: ElementNode[]; css: string } {
  const doc = new DOMParser().parseFromString(html, 'text/html');
  const out: ElementNode[] = [];
  const css: string[] = [];
  doc.head.querySelectorAll('style').forEach((s) => css.push(s.textContent ?? ''));
  for (const child of Array.from(doc.body.childNodes)) {
    if (child.nodeType === Node.TEXT_NODE) {
      const text = child.textContent?.trim();
      if (text) out.push(node('text-editor', { content: `<p>${text.replace(/</g, '&lt;')}</p>` }));
    } else if (child.nodeType === Node.ELEMENT_NODE) convert(child as Element, out, css);
  }
  return { nodes: out, css: css.join('\n').trim() };
}
