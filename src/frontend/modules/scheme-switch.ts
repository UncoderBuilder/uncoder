// Color scheme switch: flips <html data-uncoder-scheme> between light and dark, remembers the choice and
// keeps every switch on the page in sync. The initial scheme is set before paint by Frontend::js_flag().
window.UncoderWB.register('scheme-switch', (el) => {
  const button = (el.matches('.uncoder-scheme-switch') ? (el as HTMLButtonElement) : el.querySelector<HTMLButtonElement>('.uncoder-scheme-switch'));
  if (!button) return;
  const root = document.documentElement;
  const isDark = () => root.getAttribute('data-uncoder-scheme') === 'dark';
  const sync = () => button.setAttribute('aria-pressed', String(isDark()));

  const onClick = () => {
    const next = isDark() ? 'light' : 'dark';
    root.setAttribute('data-uncoder-scheme', next);
    try {
      localStorage.setItem('uncoder-scheme', next);
    } catch {
      /* private mode: the choice lasts for this page only */
    }
  };
  const observer = new MutationObserver(sync);
  observer.observe(root, { attributes: true, attributeFilter: ['data-uncoder-scheme'] });
  button.addEventListener('click', onClick);
  sync();

  return () => {
    observer.disconnect();
    button.removeEventListener('click', onClick);
  };
});
