// Alert widget: the dismiss button hides the alert; with "remember" the dismissal is stored in
// localStorage under data-settings.key so the alert stays hidden on later visits.
window.UncoderWB.register('alert', (el, api) => {
  const box = (el.matches('.uncoder-alert') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-alert'));
  const button = el.querySelector<HTMLButtonElement>('.uncoder-alert__dismiss');
  if (!box || !button || api.editor) return;

  const { key } = api.settings<{ key?: string }>(el);
  const storageKey = key ? `uncoder-alert:${key}` : '';
  const hide = () => {
    el.hidden = true;
    el.style.display = 'none';
  };

  if (storageKey) {
    try {
      if (window.localStorage.getItem(storageKey) === '1') {
        hide();
        return;
      }
    } catch {
      // Storage blocked (private mode, cookies disabled): the alert simply shows every time.
    }
  }

  let timer = 0;
  const onClick = () => {
    if (storageKey) {
      try {
        window.localStorage.setItem(storageKey, '1');
      } catch {
        // Ignore: dismissal then lasts for this page view only.
      }
    }
    if (api.reducedMotion()) {
      hide();
      return;
    }
    box.classList.add('uncoder-alert--leaving');
    timer = window.setTimeout(hide, 200);
  };

  button.addEventListener('click', onClick);
  return () => {
    button.removeEventListener('click', onClick);
    window.clearTimeout(timer);
  };
});
