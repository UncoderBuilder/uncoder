// Share buttons: only "copy link" and the native share sheet need JavaScript (the network links
// are plain URLs). Both buttons are rendered hidden and revealed here when supported.
window.UncoderWB.register('share', (el, api) => {
  const status = el.querySelector<HTMLElement>('.uncoder-share__status');
  const canShare = typeof navigator.share === 'function';
  const timers = new Set<number>();

  el.querySelectorAll<HTMLButtonElement>('button[data-uncoder-share]').forEach((btn) => {
    const item = btn.closest<HTMLElement>('.uncoder-share__item');
    if (!item) return;
    item.hidden = btn.dataset.uncoderShare === 'native' ? !canShare && !api.editor : false;
  });

  const copy = async (text: string): Promise<boolean> => {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch {
      /* Clipboard API unavailable (insecure context, permissions): fall back below. */
    }
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

  const later = (ms: number, fn: () => void) => {
    const id = window.setTimeout(() => {
      timers.delete(id);
      fn();
    }, ms);
    timers.add(id);
  };

  const onClick = async (event: Event) => {
    const btn = (event.target as Element | null)?.closest<HTMLButtonElement>('button[data-uncoder-share]');
    if (!btn || !el.contains(btn)) return;
    const url = btn.dataset.url || window.location.href;
    const title = btn.dataset.title || document.title;

    if (btn.dataset.uncoderShare === 'native') {
      if (!canShare) return;
      try {
        await navigator.share({ title, url });
      } catch {
        /* dismissed by the user */
      }
      return;
    }

    if (btn.dataset.uncoderShare === 'copy') {
      if (!(await copy(url))) return;
      const message = btn.dataset.copied || 'Link copied';
      btn.setAttribute('data-uncoder-copied', message);
      if (status) {
        status.textContent = '';
        later(50, () => (status.textContent = message));
      }
      later(2000, () => {
        btn.removeAttribute('data-uncoder-copied');
        if (status) status.textContent = '';
      });
    }
  };

  el.addEventListener('click', onClick);
  return () => {
    el.removeEventListener('click', onClick);
    timers.forEach((id) => window.clearTimeout(id));
    timers.clear();
  };
});
