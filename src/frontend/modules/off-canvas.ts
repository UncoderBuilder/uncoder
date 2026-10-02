// Off-canvas panel: modal <dialog> with focus trap, Escape / overlay / close button to close,
// scroll lock, and anchor links (#uncoder-offcanvas-{id} or a custom anchor) that open it.
window.UncoderWB.register('off-canvas', (el, api) => {
  const dialog = el.querySelector<HTMLDialogElement>('dialog.uncoder-offcanvas__dialog');
  if (!dialog || api.editor || typeof dialog.showModal !== 'function') return;
  const root = (el.matches('.uncoder-offcanvas') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-offcanvas')) ?? el;
  const s = api.settings<{ lock?: boolean; overlay?: boolean; anchor?: string }>(el);
  const html = document.documentElement;
  const hashes = [`#${dialog.id}`];
  if (s.anchor) hashes.push(`#${s.anchor}`);
  const triggers = Array.from(el.querySelectorAll<HTMLElement>('.uncoder-offcanvas__trigger'));
  let opener: HTMLElement | null = null;
  let timer = 0;
  let locked = false;

  const duration = (): number => {
    if (api.reducedMotion()) return 0;
    const raw = getComputedStyle(root).getPropertyValue('--uncoder-oc-duration').trim();
    const value = parseFloat(raw);
    if (Number.isNaN(value)) return 350;
    return raw.endsWith('ms') ? value : value * 1000;
  };

  const lockScroll = (lock: boolean) => {
    if (lock === locked) return;
    locked = lock;
    const count = Number(html.dataset.uncoderLocks || '0') + (lock ? 1 : -1);
    html.dataset.uncoderLocks = String(Math.max(0, count));
    if (lock && count === 1) {
      html.style.setProperty('--uncoder-scrollbar-w', `${window.innerWidth - html.clientWidth}px`);
      html.classList.add('uncoder-scroll-lock');
    } else if (count <= 0) {
      html.classList.remove('uncoder-scroll-lock');
      html.style.removeProperty('--uncoder-scrollbar-w');
    }
  };

  const setExpanded = (open: boolean) => triggers.forEach((t) => t.setAttribute('aria-expanded', String(open)));

  const focusables = (): HTMLElement[] =>
    Array.from(
      dialog.querySelectorAll<HTMLElement>(
        'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), iframe, summary, [tabindex]:not([tabindex="-1"]), [contenteditable="true"]',
      ),
    ).filter((node) => node.getClientRects().length > 0);

  const open = (from?: HTMLElement | null) => {
    window.clearTimeout(timer);
    opener = from ?? (document.activeElement as HTMLElement | null);
    if (!dialog.open) dialog.showModal();
    if (s.lock !== false) lockScroll(true);
    setExpanded(true);
    const first = focusables().find((node) => !node.classList.contains('uncoder-offcanvas__close')) ?? focusables()[0];
    first?.focus({ preventScroll: true });
    requestAnimationFrame(() => requestAnimationFrame(() => dialog.classList.add('is-open')));
  };

  const close = (restoreFocus = true) => {
    if (!dialog.open) return;
    dialog.classList.remove('is-open');
    setExpanded(false);
    lockScroll(false);
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
      if (dialog.open) dialog.close();
      if (restoreFocus && opener && document.contains(opener)) opener.focus({ preventScroll: true });
    }, duration());
  };

  const onTrigger = (e: Event) => {
    e.preventDefault();
    open(e.currentTarget as HTMLElement);
  };

  const onDialogClick = (e: MouseEvent) => {
    const target = e.target as Element;
    if (target.closest('[data-uncoder-oc-close]')) {
      close();
      return;
    }
    // In-page links inside the panel close it so the target section is visible.
    const link = target.closest<HTMLAnchorElement>('a[href*="#"]');
    if (link && link.pathname === window.location.pathname && link.hash && !hashes.includes(link.hash)) close(false);
  };

  const onKeydown = (e: KeyboardEvent) => {
    if (e.key !== 'Tab') return;
    const items = focusables();
    if (!items.length) {
      e.preventDefault();
      return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  };

  const onCancel = (e: Event) => {
    e.preventDefault();
    close();
  };

  const onDocumentClick = (e: MouseEvent) => {
    const link = (e.target as Element | null)?.closest?.('a[href*="#"]') as HTMLAnchorElement | null;
    if (!link || dialog.contains(link) || !hashes.includes(link.hash)) return;
    if (link.pathname !== window.location.pathname && link.getAttribute('href')?.charAt(0) !== '#') return;
    e.preventDefault();
    open(link);
  };

  triggers.forEach((t) => t.addEventListener('click', onTrigger));
  dialog.addEventListener('click', onDialogClick);
  dialog.addEventListener('keydown', onKeydown);
  dialog.addEventListener('cancel', onCancel);
  document.addEventListener('click', onDocumentClick);

  return () => {
    triggers.forEach((t) => t.removeEventListener('click', onTrigger));
    dialog.removeEventListener('click', onDialogClick);
    dialog.removeEventListener('keydown', onKeydown);
    dialog.removeEventListener('cancel', onCancel);
    document.removeEventListener('click', onDocumentClick);
    window.clearTimeout(timer);
    lockScroll(false);
    setExpanded(false);
    dialog.classList.remove('is-open');
    if (dialog.open) dialog.close();
  };
});
