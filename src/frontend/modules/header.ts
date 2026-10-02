// Header behaviour (Theme\Header_Behavior): toggles is-scrolled / is-hidden / is-transparent on the
// header location wrapper and publishes the sticky header height as --uncoder-header-h (anchor offsets).
interface HeaderSettings {
  sticky?: '' | 'always' | 'reveal';
  transparent?: boolean;
  offset?: number;
}

window.UncoderWB.register('header', (el, api) => {
  const s = api.settings<HeaderSettings>(el);
  const root = document.documentElement;
  const sticky = s.sticky === 'always' || s.sticky === 'reveal';
  const offset = Math.max(0, Number(s.offset ?? 10));

  const setHeight = () => {
    if (sticky) root.style.setProperty('--uncoder-header-h', `${el.offsetHeight}px`);
  };
  setHeight();
  const ro = 'ResizeObserver' in window ? new ResizeObserver(setHeight) : null;
  ro?.observe(el);

  // Do not hide the header while its menu, a dropdown or a search panel is open.
  const menuOpen = () => !!el.querySelector('[aria-expanded="true"]');
  // An open mobile menu panel is solid, so the bar above it turns solid too.
  const mobileOpen = () => !!el.querySelector('.uncoder-nav-menu__toggle[aria-expanded="true"]');

  let lastY = window.scrollY;
  let frame = 0;
  const update = () => {
    frame = 0;
    const y = Math.max(0, window.scrollY);
    const scrolled = y > offset;
    el.classList.toggle('is-scrolled', scrolled);
    if (s.transparent) {
      // A fixed transparent header turns solid once the page scrolls; a non-sticky one scrolls away as is.
      el.classList.toggle('is-transparent', !mobileOpen() && (sticky ? !scrolled : true));
    }
    if (s.sticky === 'reveal') {
      const down = y > lastY;
      if (down && y > el.offsetHeight && !menuOpen()) el.classList.add('is-hidden');
      else if (!down || y <= el.offsetHeight) el.classList.remove('is-hidden');
    }
    lastY = y;
  };
  const onScroll = () => {
    if (!frame) frame = requestAnimationFrame(update);
  };
  // Keyboard users: a focused link inside a hidden header must be visible.
  const onFocus = () => el.classList.remove('is-hidden');

  update();
  window.addEventListener('scroll', onScroll, { passive: true });
  el.addEventListener('focusin', onFocus);
  const mo = s.transparent ? new MutationObserver(onScroll) : null;
  mo?.observe(el, { subtree: true, attributes: true, attributeFilter: ['aria-expanded'] });

  return () => {
    window.removeEventListener('scroll', onScroll);
    el.removeEventListener('focusin', onFocus);
    ro?.disconnect();
    mo?.disconnect();
    if (frame) cancelAnimationFrame(frame);
    if (sticky) root.style.removeProperty('--uncoder-header-h');
  };
});
