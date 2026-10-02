// One-page menus: marks nav links that point to sections of the current page as current while the
// section is in view (both the desktop bar and the mobile copy of the menu).
window.UncoderWB.register('scrollspy', (el, api) => {
  if (api.settings<{ scrollspy?: boolean }>(el).scrollspy === false || api.editor) return;

  interface Entry {
    target: HTMLElement;
    links: HTMLAnchorElement[];
  }
  const here = window.location.pathname.replace(/\/+$/, '');
  const byId = new Map<string, Entry>();
  el.querySelectorAll<HTMLAnchorElement>('a.uncoder-menu__link[href*="#"]').forEach((a) => {
    let url: URL;
    try {
      url = new URL(a.href, window.location.href);
    } catch {
      return;
    }
    if (url.origin !== window.location.origin || url.pathname.replace(/\/+$/, '') !== here || url.hash.length < 2) return;
    const id = decodeURIComponent(url.hash.slice(1));
    const target = document.getElementById(id);
    if (!target) return;
    const entry = byId.get(id) ?? { target, links: [] };
    entry.links.push(a);
    byId.set(id, entry);
  });
  const entries = [...byId.values()];
  if (!entries.length) return;

  let current: Entry | null = null;
  const mark = (entry: Entry | null) => {
    if (entry === current) return;
    for (const e of [current, entry]) {
      if (!e) continue;
      const on = e === entry;
      for (const link of e.links) {
        link.closest('.uncoder-menu__item')?.classList.toggle('uncoder-menu__item--current', on);
        if (on) link.setAttribute('aria-current', 'location');
        else link.removeAttribute('aria-current');
      }
    }
    current = entry;
  };

  let frame = 0;
  const update = () => {
    frame = 0;
    const header = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--uncoder-header-h')) || 0;
    const line = header + window.innerHeight * 0.3;
    // The section whose top edge is the lowest one still above the reading line (menu order may
    // differ from page order, and the previous section's bottom can still overlap the header).
    let active: Entry | null = null;
    let best = -Infinity;
    for (const e of entries) {
      const rect = e.target.getBoundingClientRect();
      if (rect.top <= line && rect.bottom > header && rect.top > best) {
        best = rect.top;
        active = e;
      }
    }
    // At the very bottom the last section wins even if it is short.
    if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 2) {
      active = entries.reduce((a, b) => (a.target.getBoundingClientRect().top > b.target.getBoundingClientRect().top ? a : b));
    }
    mark(active);
  };
  const onScroll = () => {
    if (!frame) frame = requestAnimationFrame(update);
  };
  update();
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll, { passive: true });
  return () => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);
    if (frame) cancelAnimationFrame(frame);
    mark(null);
  };
});
