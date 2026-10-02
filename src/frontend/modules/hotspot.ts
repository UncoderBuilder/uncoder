// Hotspot: disclosure buttons (aria-expanded) that open tooltip cards. Click trigger toggles and
// closes on outside click; hover trigger opens on hover / focus through CSS and this module keeps
// aria-expanded in sync. Escape closes (and dismisses a hovered tooltip, WCAG 1.4.13). Tooltips are
// nudged back inside the viewport.
window.UncoderWB.register('hotspot', (el, api) => {
  const root = (el.matches('.uncoder-hotspot') ? (el as HTMLElement) : el.querySelector<HTMLElement>(':scope > .uncoder-hotspot'));
  if (!root) return;
  const hover = api.settings<{ trigger?: string }>(el).trigger === 'hover';

  interface Spot {
    item: HTMLElement;
    button: HTMLButtonElement;
    tip: HTMLElement;
  }
  const spots: Spot[] = [];
  root.querySelectorAll<HTMLElement>(':scope > .uncoder-hotspot__item').forEach((item) => {
    const button = item.querySelector<HTMLButtonElement>(':scope > button.uncoder-hotspot__marker');
    const tip = item.querySelector<HTMLElement>(':scope > .uncoder-hotspot__tooltip');
    if (button && tip) spots.push({ item, button, tip });
  });
  if (!spots.length) return;

  const fit = (spot: Spot) => {
    const { tip, item } = spot;
    tip.style.setProperty('--uncoder-hs-shift', '0px');
    if (!item.classList.contains('uncoder-hotspot__item--top') && !item.classList.contains('uncoder-hotspot__item--bottom')) return;
    const r = tip.getBoundingClientRect();
    const margin = 8;
    const vw = document.documentElement.clientWidth;
    let shift = 0;
    if (r.left < margin) shift = margin - r.left;
    else if (r.right > vw - margin) shift = vw - margin - r.right;
    if (shift) tip.style.setProperty('--uncoder-hs-shift', `${Math.round(shift)}px`);
  };

  const setOpen = (spot: Spot, open: boolean) => {
    spot.button.setAttribute('aria-expanded', open ? 'true' : 'false');
    spot.item.classList.toggle('is-open', open);
    if (open) {
      spot.item.classList.remove('is-dismissed');
      fit(spot);
    }
  };

  const closeAll = (except?: Spot) => {
    for (const spot of spots) {
      if (spot !== except && spot.button.getAttribute('aria-expanded') === 'true') setOpen(spot, false);
    }
  };

  const find = (target: EventTarget | null) => {
    const item = (target as Element | null)?.closest?.('.uncoder-hotspot__item');
    return spots.find((spot) => spot.item === item);
  };

  const onClick = (e: MouseEvent) => {
    const spot = find(e.target);
    if (!spot || !spot.button.contains(e.target as Node)) return;
    const open = spot.button.getAttribute('aria-expanded') !== 'true';
    closeAll(spot);
    setOpen(spot, open);
    if (hover && !open) spot.item.classList.add('is-dismissed');
  };

  const onDocClick = (e: MouseEvent) => {
    if (!root.contains(e.target as Node) || !find(e.target)) closeAll();
  };

  const onKey = (e: KeyboardEvent) => {
    if (e.key !== 'Escape') return;
    const spot = spots.find((sp) => sp.button.getAttribute('aria-expanded') === 'true');
    if (!spot) return;
    setOpen(spot, false);
    spot.item.classList.add('is-dismissed');
    if (spot.item.contains(document.activeElement) && document.activeElement !== spot.button) spot.button.focus();
  };

  // Hover trigger: CSS shows the tooltip on hover / focus; mirror the state for assistive technologies.
  const onEnter = (e: PointerEvent) => {
    const spot = find(e.target);
    if (spot && e.pointerType !== 'touch' && (e.target as Element) === spot.item) {
      closeAll(spot);
      setOpen(spot, true);
    }
  };
  const onLeave = (e: PointerEvent) => {
    const spot = find(e.target);
    if (spot && e.pointerType !== 'touch' && (e.target as Element) === spot.item && !spot.item.contains(document.activeElement)) {
      setOpen(spot, false);
      spot.item.classList.remove('is-dismissed');
    }
  };
  const onFocusIn = (e: FocusEvent) => {
    const spot = find(e.target);
    if (spot && !spot.item.contains(e.relatedTarget as Node | null)) {
      closeAll(spot);
      setOpen(spot, true);
    }
  };
  const onFocusOut = (e: FocusEvent) => {
    const spot = find(e.target);
    if (spot && !spot.item.contains(e.relatedTarget as Node | null) && !spot.item.matches(':hover')) {
      setOpen(spot, false);
      spot.item.classList.remove('is-dismissed');
    }
  };

  root.addEventListener('click', onClick);
  document.addEventListener('keydown', onKey);
  document.addEventListener('click', onDocClick);
  if (hover) {
    root.addEventListener('pointerenter', onEnter, true);
    root.addEventListener('pointerleave', onLeave, true);
    root.addEventListener('focusin', onFocusIn);
    root.addEventListener('focusout', onFocusOut);
  }

  return () => {
    root.removeEventListener('click', onClick);
    document.removeEventListener('keydown', onKey);
    root.removeEventListener('pointerenter', onEnter, true);
    root.removeEventListener('pointerleave', onLeave, true);
    root.removeEventListener('focusin', onFocusIn);
    root.removeEventListener('focusout', onFocusOut);
    document.removeEventListener('click', onDocClick);
    closeAll();
  };
});
