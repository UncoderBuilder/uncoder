// Nav menu: disclosure submenus (hover, click, keyboard), mobile dropdown / off-canvas / full-screen panel.
window.UncoderWB.register('nav-menu', (el, api) => {
  const root = (el.matches('.uncoder-nav-menu') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-nav-menu'));
  if (!root) return;
  const s = api.settings<{ trigger?: 'hover' | 'click'; stretch?: boolean }>(el);
  const hover = s.trigger !== 'click';
  const main = root.querySelector<HTMLElement>('.uncoder-menu--main');
  const toggle = root.querySelector<HTMLButtonElement>('.uncoder-nav-menu__toggle');
  const panel = root.querySelector<HTMLElement>('.uncoder-nav-menu__panel');
  const dialog = panel instanceof HTMLDialogElement ? panel : null;
  const timers = new Map<HTMLElement, number>();
  const html = document.documentElement;
  const duration = () => (api.reducedMotion() ? 0 : 350);
  let closeTimer = 0;

  /* ---------------------------------------------------------------- Submenus */

  const subOf = (li: HTMLElement) => li.querySelector<HTMLElement>(':scope > .uncoder-menu__sub, :scope > .uncoder-menu__mega');

  const flip = (li: HTMLElement) => {
    const sub = subOf(li);
    if (!sub || !main?.contains(li)) return;
    sub.classList.remove('uncoder-menu__sub--flip');
    const rect = sub.getBoundingClientRect();
    const rtl = getComputedStyle(li).direction === 'rtl';
    if ((!rtl && rect.right > html.clientWidth - 8) || (rtl && rect.left < 8)) sub.classList.add('uncoder-menu__sub--flip');
  };

  const setOpen = (li: HTMLElement, open: boolean) => {
    li.classList.toggle('is-open', open);
    li.querySelector(':scope > .uncoder-menu__toggle')?.setAttribute('aria-expanded', String(open));
    if (open) {
      flip(li);
      li.parentElement?.querySelectorAll<HTMLElement>(':scope > .uncoder-menu__item.is-open').forEach((other) => {
        if (other !== li) setOpen(other, false);
      });
    } else {
      li.querySelectorAll<HTMLElement>('.uncoder-menu__item.is-open').forEach((child) => setOpen(child, false));
    }
  };

  const closeAll = (scope: ParentNode | null) => {
    scope?.querySelectorAll<HTMLElement>(':scope > .uncoder-menu__item.is-open').forEach((li) => setOpen(li, false));
  };

  const onPointerEnter = (e: PointerEvent) => {
    if (e.pointerType !== 'mouse') return;
    const li = e.currentTarget as HTMLElement;
    window.clearTimeout(timers.get(li));
    setOpen(li, true);
  };

  const onPointerLeave = (e: PointerEvent) => {
    if (e.pointerType !== 'mouse') return;
    const li = e.currentTarget as HTMLElement;
    timers.set(
      li,
      window.setTimeout(() => setOpen(li, false), 200),
    );
  };

  const parents = main ? Array.from(main.querySelectorAll<HTMLElement>('.uncoder-menu__item--has-children')) : [];
  if (hover) {
    parents.forEach((li) => {
      li.addEventListener('pointerenter', onPointerEnter);
      li.addEventListener('pointerleave', onPointerLeave);
    });
  }

  /* ---------------------------------------------------------------- Mobile panel */

  const isOpen = () => toggle?.getAttribute('aria-expanded') === 'true';

  const lockScroll = (lock: boolean) => {
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

  const position = () => {
    if (!panel || dialog || !s.stretch) return;
    const rect = root.getBoundingClientRect();
    root.style.setProperty('--uncoder-nav-panel-x', `${-rect.left}px`);
    root.style.setProperty('--uncoder-nav-panel-w', `${html.clientWidth}px`);
  };

  const openPanel = () => {
    if (!panel || !toggle || isOpen()) return;
    window.clearTimeout(closeTimer);
    toggle.setAttribute('aria-expanded', 'true');
    root.classList.add('is-panel-open');
    if (dialog) {
      if (!dialog.open) dialog.showModal();
      lockScroll(true);
    } else {
      panel.hidden = false;
      position();
    }
    requestAnimationFrame(() => requestAnimationFrame(() => panel.classList.add('is-open')));
  };

  const closePanel = (restoreFocus = true) => {
    if (!panel || !toggle || !isOpen()) return;
    toggle.setAttribute('aria-expanded', 'false');
    root.classList.remove('is-panel-open');
    panel.classList.remove('is-open');
    if (dialog) lockScroll(false);
    closeTimer = window.setTimeout(() => {
      if (dialog) {
        if (dialog.open) dialog.close();
        // The page behind a modal dialog is inert: focus the toggle once the dialog is closed.
        if (restoreFocus) toggle.focus();
      } else {
        panel.hidden = true;
      }
    }, duration());
    if (restoreFocus && !dialog) toggle.focus();
  };

  /* ---------------------------------------------------------------- Events */

  const onClick = (e: MouseEvent) => {
    const target = e.target as Element;
    if (toggle && toggle.contains(target)) {
      if (isOpen()) closePanel();
      else openPanel();
      return;
    }
    if (target.closest('[data-uncoder-nav-close]')) {
      closePanel();
      return;
    }
    const button = target.closest<HTMLElement>('.uncoder-menu__toggle');
    if (button && root.contains(button)) {
      const li = button.parentElement as HTMLElement;
      setOpen(li, !li.classList.contains('is-open'));
      return;
    }
    const link = target.closest<HTMLAnchorElement>('a.uncoder-menu__link');
    if (!link || !root.contains(link)) return;
    const li = link.parentElement as HTMLElement;
    const href = link.getAttribute('href');
    // Parent items without a real destination behave like their toggle.
    if ((!href || href === '#') && li.classList.contains('uncoder-menu__item--has-children')) {
      e.preventDefault();
      setOpen(li, !li.classList.contains('is-open'));
      return;
    }
    // In-page anchors close the mobile panel so the target section is visible.
    if (panel?.contains(link) && link.hash && link.pathname === window.location.pathname) closePanel(false);
  };

  const onKeydown = (e: KeyboardEvent) => {
    if (e.key !== 'Escape') return;
    // Only desktop dropdowns close on Escape; inside the mobile panel Escape closes the panel.
    const openLi = main
      ? (e.target as Element).closest<HTMLElement>('.uncoder-menu__item.is-open') ?? main.querySelector<HTMLElement>('.uncoder-menu__item.is-open')
      : null;
    if (openLi && main?.contains(openLi)) {
      e.stopPropagation();
      setOpen(openLi, false);
      openLi.querySelector<HTMLElement>(':scope > .uncoder-menu__toggle')?.focus();
      return;
    }
    if (isOpen() && !dialog) {
      closePanel();
    }
  };

  const onFocusOut = (e: FocusEvent) => {
    const next = e.relatedTarget as Node | null;
    if (!main || !next) return;
    main.querySelectorAll<HTMLElement>('.uncoder-menu__item.is-open').forEach((li) => {
      if (!li.contains(next)) setOpen(li, false);
    });
  };

  const onDocumentPointer = (e: PointerEvent) => {
    const target = e.target as Node;
    if (root.contains(target)) return;
    closeAll(main);
    if (isOpen() && !dialog) closePanel(false);
  };

  const onCancel = (e: Event) => {
    e.preventDefault();
    closePanel();
  };

  const onResize = () => {
    if (!toggle || !isOpen()) return;
    if (getComputedStyle(toggle).display === 'none') closePanel(false);
    else position();
  };

  root.addEventListener('click', onClick);
  root.addEventListener('keydown', onKeydown);
  main?.addEventListener('focusout', onFocusOut);
  document.addEventListener('pointerdown', onDocumentPointer);
  dialog?.addEventListener('cancel', onCancel);
  window.addEventListener('resize', onResize);

  return () => {
    root.removeEventListener('click', onClick);
    root.removeEventListener('keydown', onKeydown);
    main?.removeEventListener('focusout', onFocusOut);
    document.removeEventListener('pointerdown', onDocumentPointer);
    dialog?.removeEventListener('cancel', onCancel);
    window.removeEventListener('resize', onResize);
    parents.forEach((li) => {
      li.removeEventListener('pointerenter', onPointerEnter);
      li.removeEventListener('pointerleave', onPointerLeave);
    });
    timers.forEach((t) => window.clearTimeout(t));
    window.clearTimeout(closeTimer);
    if (isOpen()) {
      toggle?.setAttribute('aria-expanded', 'false');
      panel?.classList.remove('is-open');
      if (dialog) {
        lockScroll(false);
        if (dialog.open) dialog.close();
      } else if (panel) {
        panel.hidden = true;
      }
    }
  };
});
