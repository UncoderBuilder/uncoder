// Header behaviour (Theme\Header_Behavior): toggles is-scrolled / is-hidden / is-transparent on the
// header location wrapper and publishes the sticky header height as --uncoder-header-h (anchor offsets).
// "Sticky from" an element (its "Sticky: Top" option): everything above it scrolls away. The wrapper sticks
// that much higher (--uncoder-header-skip, a negative top); a fixed (transparent) header slides up by up to
// that much as the page scrolls.
interface HeaderSettings {
  sticky?: '' | 'always' | 'reveal';
  transparent?: boolean;
  offset?: number;
  from?: string;
}

window.UncoderWB.register('header', (el, api) => {
  const s = api.settings<HeaderSettings>(el);
  const root = document.documentElement;
  const sticky = s.sticky === 'always' || s.sticky === 'reveal';
  const offset = Math.max(0, Number(s.offset ?? 10));
  const from = sticky && s.from ? el.querySelector<HTMLElement>(`.uncoder-${CSS.escape(s.from)}`) : null;

  // How far the sticky part starts below the header's top: 0 when the element is hidden on this device or its
  // "Sticky on" leaves this device out (then the whole header follows the header's own setting).
  let skip = 0;
  let fixed = false;
  const measure = () => {
    fixed = getComputedStyle(el).position === 'fixed';
    skip = 0;
    if (from && from.offsetParent !== null && getComputedStyle(from).position === 'sticky') {
      skip = Math.max(0, Math.round(from.getBoundingClientRect().top - el.getBoundingClientRect().top));
    }
  };
  const applySkip = () => {
    const shift = fixed ? Math.min(skip, Math.max(0, window.scrollY)) : skip;
    if (skip || el.style.getPropertyValue('--uncoder-header-skip')) el.style.setProperty('--uncoder-header-skip', `${shift}px`);
  };
  const setHeight = () => {
    measure();
    applySkip();
    if (sticky) root.style.setProperty('--uncoder-header-h', `${el.offsetHeight - skip}px`);
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
    if (fixed && skip) applySkip();
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
    el.style.removeProperty('--uncoder-header-skip');
  };
});
