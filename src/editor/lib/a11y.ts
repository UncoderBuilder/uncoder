// Accessibility check of the rendered canvas (WCAG 2.2 basics): names of links, buttons, images and
// form fields, heading order, text contrast (from computed colors), frames, duplicate ids, tab order
// and autoplaying sound. Issues point at the element (data-id) so the panel can select it.
// The server-side twin for MCP clients is Mcp\Tools\Html_A11y, run by audit_page (same rules, minus contrast).

export type A11ySeverity = 'error' | 'warning' | 'info';

export interface A11yIssue {
  severity: A11ySeverity;
  rule: string;
  message: string;
  fix: string;
  id: string | null;
}

const VAGUE = /^(click here|here|click|read more|learn more|more|more info|link|this|go|details|continue|see more)[.!…]*$/i;

/** Accessible name, simplified (aria-label → aria-labelledby → content incl. alt → title). */
export function accessibleName(el: Element): string {
  const label = el.getAttribute('aria-label');
  if (label && label.trim()) return label.trim();
  const by = el.getAttribute('aria-labelledby');
  if (by) {
    const text = by
      .split(/\s+/)
      .map((id) => el.ownerDocument.getElementById(id)?.textContent ?? '')
      .join(' ')
      .trim();
    if (text) return text;
  }
  // Canvas nodes live in the iframe's realm, so no instanceof checks against this window's classes.
  if (el.tagName === 'INPUT' && ['submit', 'button', 'reset'].includes((el as HTMLInputElement).type)) return (el as HTMLInputElement).value.trim();
  let out = '';
  const walk = (node: Node) => {
    for (const child of Array.from(node.childNodes)) {
      if (child.nodeType === Node.TEXT_NODE) out += child.nodeValue;
      else if (child.nodeType === Node.ELEMENT_NODE) {
        const c = child as Element;
        if (c.getAttribute('aria-hidden') === 'true') continue;
        if (c.tagName === 'IMG' || c.getAttribute('role') === 'img') out += ' ' + (c.getAttribute('alt') ?? c.getAttribute('aria-label') ?? '');
        else if (c.tagName.toLowerCase() === 'svg') out += ' ' + (c.querySelector('title')?.textContent ?? c.getAttribute('aria-label') ?? '');
        else walk(c);
      }
    }
  };
  walk(el);
  out = out.replace(/\s+/g, ' ').trim();
  return out || (el.getAttribute('title') ?? '').trim();
}

type Rgba = [number, number, number, number];

function parseColor(value: string): Rgba | null {
  let m = value.match(/^rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:\s*[,/]\s*([\d.]+%?))?\s*\)$/);
  if (m) return [+m[1], +m[2], +m[3], m[4] === undefined ? 1 : m[4].endsWith('%') ? parseFloat(m[4]) / 100 : +m[4]];
  m = value.match(/^color\(srgb\s+([\d.e-]+)\s+([\d.e-]+)\s+([\d.e-]+)(?:\s*\/\s*([\d.]+%?))?\)$/);
  if (m) return [+m[1] * 255, +m[2] * 255, +m[3] * 255, m[4] === undefined ? 1 : m[4].endsWith('%') ? parseFloat(m[4]) / 100 : +m[4]];
  return null;
}

const blend = (top: Rgba, base: Rgba): Rgba => {
  const a = top[3];
  return [top[0] * a + base[0] * (1 - a), top[1] * a + base[1] * (1 - a), top[2] * a + base[2] * (1 - a), 1];
};

function luminance([r, g, b]: Rgba): number {
  const f = (x: number) => {
    const c = x / 255;
    return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
  };
  return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
}

export function contrastRatio(a: Rgba, b: Rgba): number {
  const [l1, l2] = [luminance(a), luminance(b)].sort((x, y) => y - x);
  return (l1 + 0.05) / (l2 + 0.05);
}

/** The solid color behind an element, or null when an image / gradient / video is involved. */
function backgroundOf(el: Element, win: Window): Rgba | null {
  const layers: Rgba[] = [];
  for (let node: Element | null = el; node; node = node.parentElement) {
    const s = win.getComputedStyle(node);
    if (s.backgroundImage && s.backgroundImage !== 'none') return null;
    if (node.querySelector(':scope > .uncoder-bg-video, :scope > .uncoder-bg-slideshow')) return null;
    const c = parseColor(s.backgroundColor);
    if (c && c[3] > 0) {
      layers.push(c);
      if (c[3] >= 1) break;
    }
  }
  let base: Rgba = [255, 255, 255, 1];
  for (const layer of layers.reverse()) base = blend(layer, base);
  return base;
}

function shown(el: Element, win: Window): boolean {
  const s = win.getComputedStyle(el);
  if (s.display === 'none' || s.visibility === 'hidden') return false;
  const r = el.getBoundingClientRect();
  return r.width > 0 && r.height > 0;
}

export function checkAccessibility(root: Element, opts: { needsH1?: boolean } = {}): A11yIssue[] {
  const doc = root.ownerDocument;
  const win = doc.defaultView as Window;
  const issues: A11yIssue[] = [];
  const seen = new Set<string>();
  const add = (el: Element | null, severity: A11ySeverity, rule: string, message: string, fix: string) => {
    const id = el?.closest('[data-id]')?.getAttribute('data-id') ?? null;
    const key = `${id}|${rule}|${message}`;
    if (seen.has(key)) return;
    seen.add(key);
    issues.push({ severity, rule, message, fix, id });
  };
  const all = <T extends Element>(sel: string) => Array.from(root.querySelectorAll<T>(sel)).filter((el) => shown(el, win) || el.tagName === 'IMG');

  // Images.
  for (const img of all<HTMLImageElement>('img')) {
    if (img.closest('[aria-hidden="true"]') || img.getAttribute('role') === 'presentation') continue;
    const alt = img.getAttribute('alt');
    if (alt === null) add(img, 'error', 'image-alt', 'Image without an alt attribute.', 'Describe the image in its alt text, or mark it decorative (empty alt).');
    else if (/\.(jpe?g|png|webp|gif|avif|svg)$/i.test(alt.trim()) || /^(img|dsc|image|photo)[-_ ]?\d+/i.test(alt.trim())) add(img, 'warning', 'image-alt', `Alt text looks like a file name ("${alt}").`, 'Write what the image shows, in a short sentence.');
  }

  // Links and buttons need a name; vague link text says nothing out of context.
  for (const a of all<HTMLAnchorElement>('a[href]')) {
    const name = accessibleName(a);
    if (!name) add(a, 'error', 'link-name', 'Link without text (only an icon or image).', 'Add an accessible label (e.g. the "Accessible label" field) or alt text on the image inside.');
    else if (VAGUE.test(name)) add(a, 'warning', 'link-text', `Link text "${name}" does not say where it goes.`, 'Use descriptive text ("View our services"), or add an accessible label with context.');
  }
  for (const b of all<HTMLElement>('button, [role="button"], input[type="submit"], input[type="button"]')) {
    if (!accessibleName(b)) add(b, 'error', 'button-name', 'Button without a label.', 'Give the button text or an accessible label.');
  }

  // Form fields need labels (a placeholder is not a label).
  for (const field of all<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>('input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="image"]), select, textarea')) {
    if (field.closest('[aria-hidden="true"]') || field.tabIndex < 0) continue;
    const labelled = (field.labels && field.labels.length > 0 && Array.from(field.labels).some((l) => l.textContent?.trim())) || field.getAttribute('aria-label')?.trim() || field.getAttribute('aria-labelledby') || field.getAttribute('title')?.trim();
    if (!labelled) add(field, 'error', 'form-label', 'Form field without a label.', 'Show the field label (or keep it for screen readers only) instead of relying on the placeholder.');
  }

  // Headings: present, not empty, one h1, no skipped levels.
  const headings = all<HTMLElement>('h1, h2, h3, h4, h5, h6');
  let prev = 0;
  let h1 = 0;
  for (const h of headings) {
    const level = Number(h.tagName[1]);
    if (!accessibleName(h)) add(h, 'error', 'heading-empty', 'Empty heading.', 'Write the heading or delete it.');
    if (level === 1 && ++h1 > 1) add(h, 'error', 'heading-h1', 'More than one h1 on the page.', 'Keep one h1 (the main title); use h2 for sections.');
    if (prev && level > prev + 1) add(h, 'warning', 'heading-order', `Heading level jumps from h${prev} to h${level}.`, `Use h${prev + 1}, and style it with a text style if it should look smaller.`);
    prev = level;
  }
  if (opts.needsH1 && headings.length && !h1) add(headings[0], 'error', 'heading-h1', 'The page has no h1.', 'Make the main title an h1.');

  // Text contrast (WCAG AA: 4.5:1, 3:1 for large text), from the real computed colors.
  const textEls = new Set<Element>();
  const walker = doc.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  while (walker.nextNode() && textEls.size < 600) {
    const t = walker.currentNode;
    if (t.nodeValue?.trim() && t.parentElement) textEls.add(t.parentElement);
  }
  for (const el of textEls) {
    if (!shown(el, win) || el.closest('[aria-hidden="true"], .uncoder-sr-only, svg')) continue;
    const s = win.getComputedStyle(el);
    const fg = parseColor(s.color);
    const bg = backgroundOf(el, win);
    if (!fg || !bg || parseFloat(s.opacity) === 0) continue;
    const ratio = contrastRatio(blend(fg, bg), bg);
    const size = parseFloat(s.fontSize);
    const large = size >= 24 || (size >= 18.66 && Number(s.fontWeight) >= 700);
    const need = large ? 3 : 4.5;
    if (ratio < need) {
      const text = (el.textContent ?? '').trim().slice(0, 40);
      add(el, ratio < need - 1 ? 'error' : 'warning', 'contrast', `Low text contrast ${ratio.toFixed(2)}:1 (needs ${need}:1) on "${text}".`, 'Darken the text or lighten the background (or the other way round).');
    }
  }

  // Frames, ids, tab order, sound.
  for (const f of all<HTMLIFrameElement>('iframe')) {
    if (!f.getAttribute('title')?.trim()) add(f, 'warning', 'frame-title', 'Embedded frame without a title.', 'Give the map / video / embed a title that says what it is.');
  }
  const ids = new Map<string, Element>();
  for (const el of Array.from(root.querySelectorAll('[id]'))) {
    const id = el.id;
    if (!id) continue;
    if (ids.has(id)) add(el, 'warning', 'duplicate-id', `The id "${id}" is used more than once.`, 'Give each element (CSS ID) a unique value; labels and anchors break otherwise.');
    else ids.set(id, el);
  }
  for (const el of Array.from(root.querySelectorAll<HTMLElement>('[tabindex]'))) {
    if (el.tabIndex > 0) add(el, 'warning', 'tabindex', 'Positive tabindex changes the keyboard order.', 'Remove the tabindex attribute or use 0.');
  }
  for (const v of Array.from(root.querySelectorAll<HTMLVideoElement>('video[autoplay]'))) {
    if (!v.muted && !v.hasAttribute('muted')) add(v, 'warning', 'autoplay-sound', 'Video plays automatically with sound.', 'Mute autoplaying videos or let visitors start them.');
  }

  const order: Record<A11ySeverity, number> = { error: 0, warning: 1, info: 2 };
  return issues.sort((a, b) => order[a.severity] - order[b.severity]);
}
