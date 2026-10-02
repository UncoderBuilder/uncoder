// Positions mega menu panels: "full" spans the viewport, "container" the site container width.
window.UncoderWB.register('mega-menu', (el) => {
  const panel = el.closest<HTMLElement>('.uncoder-menu__mega');
  const item = el.closest<HTMLElement>('.uncoder-menu__item');
  if (!panel || !item || el.classList.contains('uncoder-mega--auto') || item.closest('.uncoder-menu--mobile')) return;

  const place = () => {
    const root = document.documentElement;
    const vw = root.clientWidth;
    let width = vw;
    if (el.classList.contains('uncoder-mega--container')) {
      const styles = getComputedStyle(root);
      const container = parseFloat(styles.getPropertyValue('--uncoder-container')) || 1200;
      const gutter = parseFloat(styles.getPropertyValue('--uncoder-gutter')) || 24;
      width = Math.min(vw - gutter * 2, container);
    }
    const left = (vw - width) / 2 - item.getBoundingClientRect().left;
    panel.style.setProperty('--uncoder-mega-x', `${Math.round(left)}px`);
    panel.style.setProperty('--uncoder-mega-w', `${Math.round(width)}px`);
  };

  place();
  item.addEventListener('pointerenter', place);
  item.addEventListener('focusin', place);
  window.addEventListener('resize', place, { passive: true });
  return () => {
    item.removeEventListener('pointerenter', place);
    item.removeEventListener('focusin', place);
    window.removeEventListener('resize', place);
  };
});
