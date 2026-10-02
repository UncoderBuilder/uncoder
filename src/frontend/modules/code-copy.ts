// Code Highlight copy button: copies the exact code text (line numbers are CSS pseudo-elements,
// so they are not part of textContent), shows the "copied" label for two seconds and announces it.
window.UncoderWB.register('code-copy', (el) => {
  const button = el.querySelector<HTMLButtonElement>('.uncoder-code__copy');
  const code = el.querySelector<HTMLElement>('.uncoder-code__code');
  if (!button || !code) return;
  const label = button.querySelector<HTMLElement>('.uncoder-code__copy-label');
  const status = el.querySelector<HTMLElement>('.uncoder-code__status');
  const original = label?.textContent || '';
  let timer = 0;

  button.hidden = false;

  const fallbackCopy = (text: string): boolean => {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.cssText = 'position:fixed;top:0;left:0;width:1px;height:1px;opacity:0;pointer-events:none;';
    document.body.appendChild(area);
    area.select();
    let ok = false;
    try {
      ok = document.execCommand('copy');
    } catch {
      ok = false;
    }
    area.remove();
    return ok;
  };

  const onClick = async () => {
    const text = code.textContent || '';
    let ok = false;
    try {
      await navigator.clipboard.writeText(text);
      ok = true;
    } catch {
      ok = fallbackCopy(text);
    }
    if (!ok) return;
    const done = button.dataset.copied || 'Copied!';
    button.classList.add('uncoder-code__copy--done');
    if (label) label.textContent = done;
    if (status) status.textContent = done;
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
      button.classList.remove('uncoder-code__copy--done');
      if (label) label.textContent = original;
      if (status) status.textContent = '';
    }, 2000);
  };

  button.addEventListener('click', onClick);
  return () => {
    button.removeEventListener('click', onClick);
    window.clearTimeout(timer);
    button.classList.remove('uncoder-code__copy--done');
    if (label) label.textContent = original;
  };
});
