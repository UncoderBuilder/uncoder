// Accordion: progressive enhancement of <details>/<summary> items. Adds a height animation
// (skipped for reduced motion) and the "one item open" behaviour. Without JS the native
// elements (and the `name` attribute grouping) keep everything usable.
window.UncoderWB.register('accordion', (el, api) => {
  const root = (el.matches('.uncoder-accordion') ? (el as HTMLElement) : el.querySelector<HTMLElement>(':scope > .uncoder-accordion'));
  if (!root) return;
  const s = api.settings<{ multiple?: boolean; duration?: number; closedBelow?: number }>(el);
  // "Open on phones too" off: on a phone-sized screen the item rendered open starts closed.
  if (s.closedBelow && window.matchMedia?.(`(max-width: ${s.closedBelow}px)`).matches) {
    root.querySelectorAll<HTMLDetailsElement>(':scope > details.uncoder-accordion__item[open]').forEach((d) => {
      d.open = false;
    });
  }

  interface Item {
    details: HTMLDetailsElement;
    summary: HTMLElement;
    panel: HTMLElement;
    anim: Animation | null;
    open: boolean; // target state
    name: string | null;
  }

  const items: Item[] = [];
  root.querySelectorAll<HTMLDetailsElement>(':scope > details.uncoder-accordion__item').forEach((details) => {
    const summary = details.querySelector<HTMLElement>(':scope > summary');
    const panel = details.querySelector<HTMLElement>(':scope > .uncoder-accordion__panel');
    if (!summary || !panel) return;
    const name = details.getAttribute('name');
    // Exclusivity is handled here so the item being closed can animate.
    details.removeAttribute('name');
    items.push({ details, summary, panel, anim: null, open: details.open, name });
  });
  if (!items.length) return;

  const easing = 'cubic-bezier(0.2, 0.8, 0.2, 1)';
  // Clip the panel while its height animates. "clip" (not "hidden") keeps margins collapsing as they do once the
  // animation ends, so an answer with a negative top margin doesn't make everything below jump at the end.
  const clip = typeof CSS !== 'undefined' && CSS.supports?.('overflow', 'clip') ? 'clip' : 'hidden';
  const duration = () => (api.reducedMotion() ? 0 : Math.max(0, Math.min(2000, Number(s.duration ?? 300) || 0)));

  const finish = (item: Item) => {
    item.anim = null;
    item.panel.style.overflow = '';
    item.details.classList.remove('is-closing');
  };

  const animate = (item: Item, open: boolean) => {
    const { details, panel } = item;
    item.open = open;
    const from = details.open ? panel.getBoundingClientRect().height : 0;
    if (item.anim) {
      item.anim.cancel();
      finish(item);
    }
    const ms = duration();
    if (!ms || typeof panel.animate !== 'function') {
      details.open = open;
      return;
    }
    details.classList.toggle('is-closing', !open);
    details.open = true;
    // A panel pulled up under its title by a negative top margin (collapsing through it) is "closed" at that height:
    // the items below then neither dip at the start of opening nor jump at the end of closing.
    const lift = Math.max(0, item.summary.getBoundingClientRect().bottom - panel.getBoundingClientRect().top);
    const start = from > 0 ? from : lift;
    const to = open ? panel.scrollHeight : lift;
    if (Math.abs(to - start) < 1) {
      details.open = open;
      finish(item);
      return;
    }
    panel.style.overflow = clip;
    const anim = panel.animate(
      [
        { height: `${start}px`, opacity: open && from === 0 ? 0 : 1 },
        { height: `${to}px`, opacity: open ? 1 : 0 },
      ],
      { duration: ms, easing },
    );
    item.anim = anim;
    anim.onfinish = () => {
      if (item.anim !== anim) return;
      if (!open) details.open = false;
      finish(item);
    };
  };

  const toggle = (item: Item, open: boolean) => {
    if (open && !s.multiple) {
      for (const other of items) if (other !== item && other.open) animate(other, false);
    }
    animate(item, open);
  };

  const onClick = (e: MouseEvent) => {
    const summary = (e.target as Element).closest('summary');
    const item = items.find((it) => it.summary === summary);
    if (!item) return;
    e.preventDefault();
    toggle(item, !item.open);
  };

  // Opened or closed natively (find-in-page, scripts): keep the state and exclusivity in sync.
  const onToggle = (e: Event) => {
    const item = items.find((it) => it.details === e.target);
    if (!item || item.anim) return;
    if (item.details.open && !item.open) {
      item.open = true;
      if (!s.multiple) {
        for (const other of items) if (other !== item && other.open) animate(other, false);
      }
    } else if (!item.details.open && item.open) {
      item.open = false;
    }
  };

  const onNestedSelect = (e: Event) => {
    if (e.target !== el) return;
    const index = (e as CustomEvent<{ index?: number }>).detail?.index;
    const item = typeof index === 'number' ? items[index] : undefined;
    if (item && !item.open) toggle(item, true);
  };

  root.addEventListener('click', onClick);
  root.addEventListener('toggle', onToggle, true);
  el.addEventListener('uncoder:nested-select', onNestedSelect);

  return () => {
    root.removeEventListener('click', onClick);
    root.removeEventListener('toggle', onToggle, true);
    el.removeEventListener('uncoder:nested-select', onNestedSelect);
    for (const item of items) {
      item.anim?.cancel();
      finish(item);
      if (item.name) item.details.setAttribute('name', item.name);
    }
  };
});
