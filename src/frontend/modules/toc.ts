// Table of contents: builds the list from the page headings, adds missing ids, smooth-scrolls,
// highlights the current section and handles the collapsible box.
interface TocSettings {
  headings?: string[];
  container?: string;
  exclude?: string;
  hierarchical?: boolean;
  smooth?: boolean;
  offset?: number;
  highlight?: boolean;
  min?: number;
  sample?: string[];
}

interface TocEntry {
  level: number;
  text: string;
  el: HTMLElement | null;
}

window.UncoderWB.register('toc', (el, api) => {
  const box = (el.matches('.uncoder-toc') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-toc'));
  const list = el.querySelector<HTMLElement>('[data-uncoder-toc-list]');
  if (!box || !list) return;
  const s = api.settings<TocSettings>(el);
  const offset = Math.max(0, Number(s.offset) || 0);
  const levels = (s.headings && s.headings.length ? s.headings : ['h2', 'h3']).filter((h) => /^h[1-6]$/.test(h));
  const listTag = list.tagName.toLowerCase() === 'ol' ? 'ol' : 'ul';
  const toggle = el.querySelector<HTMLButtonElement>('.uncoder-toc__toggle');
  const body = el.querySelector<HTMLElement>('.uncoder-toc__body');

  const addedIds: HTMLElement[] = [];
  const addedTabindex: HTMLElement[] = [];
  const styled: HTMLElement[] = [];
  const links = new Map<HTMLElement, HTMLAnchorElement>();
  let headings: HTMLElement[] = [];
  let io: IntersectionObserver | null = null;
  let mo: MutationObserver | null = null;
  let timer = 0;

  const query = (selector: string, from: ParentNode = document): Element | null => {
    try {
      return from.querySelector(selector);
    } catch {
      return null;
    }
  };

  const scope = (): Element => (s.container ? query(s.container) : null) || el.closest('[data-uncoder-doc]') || document.body;

  // Headings of listing / navigation widgets are not sections of the content.
  const skip = '.uncoder-toc, [hidden], dialog, [aria-hidden="true"], .uncoder-posts, .uncoder-post-nav, .uncoder-author-box, .uncoder-post-comments, .uncoder-login, .uncoder-nav-menu';

  const excluded = (h: HTMLElement): boolean => {
    if (el.contains(h) || h.closest(skip)) return true;
    if (s.exclude) {
      try {
        if (h.closest(s.exclude)) return true;
      } catch {
        /* invalid selector: ignore */
      }
    }
    return false;
  };

  const slug = (text: string): string => {
    const base = text
      .toLowerCase()
      .normalize('NFKD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^\p{L}\p{N}]+/gu, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 60);
    return base || 'section';
  };

  const setActive = (active: HTMLElement | null) => {
    links.forEach((a, h) => {
      const on = h === active;
      a.classList.toggle('is-active', on);
      if (on) a.setAttribute('aria-current', 'location');
      else a.removeAttribute('aria-current');
    });
  };

  const observe = () => {
    io?.disconnect();
    io = null;
    if (s.highlight === false || !headings.length || !('IntersectionObserver' in window)) return;
    const visible = new Set<Element>();
    io = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => (entry.isIntersecting ? visible.add(entry.target) : visible.delete(entry.target)));
        let active: HTMLElement | null = headings.find((h) => visible.has(h)) ?? null;
        if (!active) {
          for (const h of headings) if (h.getBoundingClientRect().top < offset + 1) active = h;
        }
        setActive(active);
      },
      { rootMargin: `-${offset}px 0px -55% 0px` },
    );
    headings.forEach((h) => io!.observe(h));
  };

  const render = (entries: TocEntry[]) => {
    list.textContent = '';
    links.clear();
    const min = Math.min(...entries.map((e) => e.level));
    const stack: Array<{ list: HTMLElement; level: number; last: HTMLLIElement | null }> = [{ list, level: min, last: null }];
    for (const entry of entries) {
      const level = s.hierarchical === false ? min : entry.level;
      let top = stack[stack.length - 1];
      while (level < top.level && stack.length > 1) {
        stack.pop();
        top = stack[stack.length - 1];
      }
      if (level > top.level && top.last) {
        const sub = document.createElement(listTag);
        sub.className = 'uncoder-toc__list uncoder-toc__list--sub';
        top.last.appendChild(sub);
        stack.push({ list: sub, level, last: null });
        top = stack[stack.length - 1];
      }
      const li = document.createElement('li');
      li.className = 'uncoder-toc__item';
      const a = document.createElement('a');
      a.className = 'uncoder-toc__link';
      a.href = entry.el ? `#${encodeURIComponent(entry.el.id)}` : '#';
      a.textContent = entry.text;
      li.appendChild(a);
      top.list.appendChild(li);
      top.last = li;
      if (entry.el) links.set(entry.el, a);
    }
  };

  const build = () => {
    const root = scope();
    headings = levels.length
      ? Array.from(root.querySelectorAll<HTMLElement>(levels.join(','))).filter((h) => !excluded(h) && (h.textContent || '').trim() !== '')
      : [];
    headings.forEach((h) => {
      if (!h.id) {
        const base = slug((h.textContent || '').trim());
        let id = base;
        for (let n = 2; document.getElementById(id); n++) id = `${base}-${n}`;
        h.id = id;
        addedIds.push(h);
      }
      if (offset && !styled.includes(h)) {
        h.style.scrollMarginTop = `${offset}px`;
        styled.push(h);
      }
    });

    let entries: TocEntry[] = headings.map((h) => ({ level: Number(h.tagName.charAt(1)), text: (h.textContent || '').trim(), el: h }));
    if (entries.length < Math.max(1, Number(s.min) || 1)) {
      if (api.editor && s.sample && s.sample.length) {
        const top = levels.length ? Math.min(...levels.map((l) => Number(l.charAt(1)))) : 2;
        entries = s.sample.map((text) => ({ level: text.startsWith('-') ? top + 1 : top, text: text.replace(/^-/, ''), el: null }));
      } else {
        el.classList.add('uncoder-toc-empty');
        list.textContent = '';
        links.clear();
        io?.disconnect();
        return;
      }
    }
    el.classList.remove('uncoder-toc-empty');
    render(entries);
    box.classList.add('is-ready');
    observe();
  };

  const onClick = (e: MouseEvent) => {
    const a = (e.target as Element).closest<HTMLAnchorElement>('a.uncoder-toc__link');
    if (!a || !list.contains(a)) return;
    e.preventDefault();
    const id = decodeURIComponent(a.hash.slice(1));
    const target = id ? document.getElementById(id) : null;
    if (!target) return;
    const top = target.getBoundingClientRect().top + window.scrollY - offset;
    window.scrollTo({ top, behavior: s.smooth === false || api.reducedMotion() ? 'auto' : 'smooth' });
    if (!target.hasAttribute('tabindex')) {
      target.setAttribute('tabindex', '-1');
      addedTabindex.push(target);
    }
    target.focus({ preventScroll: true });
    if (!api.editor) history.replaceState(null, '', `#${encodeURIComponent(id)}`);
  };

  const onToggle = () => {
    if (!toggle || !body) return;
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(open));
    body.hidden = !open;
    box.classList.toggle('is-collapsed', !open);
  };

  build();
  list.addEventListener('click', onClick);
  toggle?.addEventListener('click', onToggle);

  // In the editor the page changes while the widget stays mounted: rebuild after edits elsewhere.
  if (api.editor && 'MutationObserver' in window) {
    mo = new MutationObserver((records) => {
      if (records.every((r) => el.contains(r.target))) return;
      window.clearTimeout(timer);
      timer = window.setTimeout(build, 300);
    });
    mo.observe(scope(), { childList: true, subtree: true, characterData: true });
  }

  return () => {
    list.removeEventListener('click', onClick);
    toggle?.removeEventListener('click', onToggle);
    io?.disconnect();
    mo?.disconnect();
    window.clearTimeout(timer);
    addedIds.forEach((h) => h.removeAttribute('id'));
    addedTabindex.forEach((h) => h.removeAttribute('tabindex'));
    styled.forEach((h) => h.style.removeProperty('scroll-margin-top'));
    list.textContent = '';
    box.classList.remove('is-ready');
    el.classList.remove('uncoder-toc-empty');
  };
});
