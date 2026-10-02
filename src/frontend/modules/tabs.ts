// Tabs: WAI-ARIA tabs pattern (automatic activation, roving tabindex, arrow keys, Home/End),
// an accordion mode chosen per breakpoint by CSS (the tab list is hidden, the headers shown),
// optional #hash deep links. Nested tabs are left to their own instance (child selectors only).
window.UncoderWB.register('tabs', (el, api) => {
  const root = (el.matches('.uncoder-tabs') ? (el as HTMLElement) : el.querySelector<HTMLElement>(':scope > .uncoder-tabs'));
  const list = root?.querySelector<HTMLElement>(':scope > .uncoder-tabs__list');
  const wrap = root?.querySelector<HTMLElement>(':scope > .uncoder-tabs__panels');
  if (!root || !list || !wrap) return;

  const tabs = Array.from(list.querySelectorAll<HTMLButtonElement>(':scope > .uncoder-tabs__tab'));
  const accs = Array.from(wrap.querySelectorAll<HTMLButtonElement>(':scope > .uncoder-tabs__acc'));
  const panels = Array.from(wrap.querySelectorAll<HTMLElement>(':scope > .uncoder-tabs__panel'));
  if (!tabs.length || tabs.length !== panels.length) return;

  const s = api.settings<{ deepLink?: boolean }>(el);
  let active = Math.max(0, tabs.findIndex((t) => t.getAttribute('aria-selected') === 'true'));
  let expanded = true; // accordion mode: whether the active panel is open
  let accordion = false;

  const render = (animate: boolean) => {
    tabs.forEach((tab, i) => {
      const on = i === active;
      const shown = on && (expanded || !accordion);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.tabIndex = on ? 0 : -1;
      tab.classList.toggle('is-active', on);
      const acc = accs[i];
      if (acc) {
        acc.setAttribute('aria-expanded', shown ? 'true' : 'false');
        acc.classList.toggle('is-active', shown);
      }
      const panel = panels[i];
      const was = !panel.hidden;
      panel.hidden = !shown;
      panel.classList.toggle('is-active', shown);
      if (shown && !was && animate && !api.reducedMotion()) {
        panel.classList.remove('is-entering');
        void panel.offsetWidth; // restart the entrance animation
        panel.classList.add('is-entering');
      }
    });
  };

  const onAnimationEnd = (e: AnimationEvent) => {
    const panel = e.target as HTMLElement;
    if (panels.includes(panel)) panel.classList.remove('is-entering');
  };

  // Layout comes from CSS (responsive): read it back and keep the ARIA roles in sync.
  const syncMode = () => {
    const style = getComputedStyle(list);
    const isAccordion = style.display === 'none' && accs.length === tabs.length;
    list.setAttribute('aria-orientation', style.flexDirection.indexOf('column') === 0 ? 'vertical' : 'horizontal');
    if (isAccordion === accordion) return;
    accordion = isAccordion;
    panels.forEach((panel, i) => {
      panel.setAttribute('role', accordion ? 'region' : 'tabpanel');
      panel.setAttribute('aria-labelledby', (accordion ? accs[i] : tabs[i]).id);
    });
    if (!accordion) expanded = true;
    render(false);
  };

  const select = (i: number, opts: { focus?: boolean; user?: boolean } = {}) => {
    if (i < 0 || i >= tabs.length) return;
    const changed = i !== active || !expanded;
    active = i;
    expanded = true;
    render(changed);
    if (opts.focus) tabs[i].focus();
    if (opts.user && s.deepLink && changed) {
      history.replaceState(history.state, '', '#' + encodeURIComponent(tabs[i].id));
    }
  };

  const onListClick = (e: MouseEvent) => {
    const i = tabs.indexOf((e.target as Element).closest('.uncoder-tabs__tab') as HTMLButtonElement);
    if (i >= 0) select(i, { user: true });
  };

  const onListKey = (e: KeyboardEvent) => {
    const i = tabs.indexOf(e.target as HTMLButtonElement);
    if (i < 0 || e.altKey || e.ctrlKey || e.metaKey) return;
    syncMode();
    const vertical = list.getAttribute('aria-orientation') === 'vertical';
    const rtl = getComputedStyle(list).direction === 'rtl';
    let next: number;
    switch (e.key) {
      case 'ArrowRight':
        if (vertical) return;
        next = rtl ? i - 1 : i + 1;
        break;
      case 'ArrowLeft':
        if (vertical) return;
        next = rtl ? i + 1 : i - 1;
        break;
      case 'ArrowDown':
        if (!vertical) return;
        next = i + 1;
        break;
      case 'ArrowUp':
        if (!vertical) return;
        next = i - 1;
        break;
      case 'Home':
        next = 0;
        break;
      case 'End':
        next = tabs.length - 1;
        break;
      default:
        return;
    }
    e.preventDefault();
    select((next + tabs.length) % tabs.length, { focus: true, user: true });
  };

  const onAccClick = (e: MouseEvent) => {
    const i = accs.indexOf((e.target as Element).closest('.uncoder-tabs__acc') as HTMLButtonElement);
    if (i < 0) return;
    syncMode();
    if (i === active && expanded) {
      expanded = false;
      render(false);
    } else {
      select(i, { user: true });
    }
  };

  const indexFromHash = (): number => {
    let id = '';
    try {
      id = decodeURIComponent(location.hash.slice(1));
    } catch {
      return -1;
    }
    if (!id) return -1;
    return tabs.findIndex((tab, i) => tab.id === id || panels[i].id === id || accs[i]?.id === id);
  };

  const openFromHash = (scroll: boolean) => {
    const i = indexFromHash();
    if (i < 0) return;
    select(i);
    if (scroll) {
      const target = accordion ? accs[i] : tabs[i];
      target?.scrollIntoView({ block: 'start', behavior: api.reducedMotion() ? 'auto' : 'smooth' });
    }
  };
  const onHashChange = () => openFromHash(true);

  // The editor asks a nested widget to show item N when its child container is selected.
  const onNestedSelect = (e: Event) => {
    if (e.target !== el) return;
    const index = (e as CustomEvent<{ index?: number }>).detail?.index;
    if (typeof index === 'number') select(index);
  };

  let frame = 0;
  const onResize = () => {
    if (!frame) {
      frame = requestAnimationFrame(() => {
        frame = 0;
        syncMode();
      });
    }
  };
  const ro = 'ResizeObserver' in window ? new ResizeObserver(onResize) : null;

  syncMode();
  if (s.deepLink && !api.editor) openFromHash(accordion);

  list.addEventListener('click', onListClick);
  list.addEventListener('keydown', onListKey);
  wrap.addEventListener('click', onAccClick);
  wrap.addEventListener('animationend', onAnimationEnd);
  el.addEventListener('uncoder:nested-select', onNestedSelect);
  window.addEventListener('resize', onResize);
  if (s.deepLink) window.addEventListener('hashchange', onHashChange);
  ro?.observe(root);

  return () => {
    list.removeEventListener('click', onListClick);
    list.removeEventListener('keydown', onListKey);
    wrap.removeEventListener('click', onAccClick);
    wrap.removeEventListener('animationend', onAnimationEnd);
    el.removeEventListener('uncoder:nested-select', onNestedSelect);
    window.removeEventListener('resize', onResize);
    window.removeEventListener('hashchange', onHashChange);
    ro?.disconnect();
    if (frame) cancelAnimationFrame(frame);
  };
});
