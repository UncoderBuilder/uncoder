// Nav menu: disclosure submenus (hover, click, keyboard), mobile dropdown / panel under the header /
// off-canvas / full-screen panel.
window.UncoderWB.register('nav-menu', (el, api) => {
  const root = (el.matches('.uncoder-nav-menu') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-nav-menu'));
  if (!root) return;
  const s = api.settings<{ trigger?: 'hover' | 'click'; stretch?: boolean; row?: boolean; attach?: string; fx?: string; magnet?: number; letters?: boolean }>(el);
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

  /* ---------------------------------------------------------------- Hover effects (main menu) */

  const topLinks = main ? Array.from(main.querySelectorAll<HTMLElement>(':scope > .uncoder-menu__item > .uncoder-menu__link')) : [];

  // Letter roll: each letter becomes its own rolling piece; screen readers keep the plain label.
  const split: Array<[HTMLElement, string]> = [];
  if (s.letters && !api.reducedMotion()) {
    topLinks.forEach((link) => {
      const text = link.querySelector<HTMLElement>('.uncoder-menu__text');
      const label = text?.textContent ?? '';
      if (!text || text.children.length || !label.trim()) return; // Labels with icons or badges keep the plain roll-less text.
      const sr = document.createElement('span');
      sr.className = 'uncoder-sr-only';
      sr.textContent = label;
      const letters = document.createElement('span');
      letters.setAttribute('aria-hidden', 'true');
      Array.from(label).forEach((ch, i) => {
        if (/\s/.test(ch)) {
          letters.append(ch);
          return;
        }
        const piece = document.createElement('span');
        piece.className = 'uncoder-menu__ch';
        piece.style.setProperty('--i', String(i));
        piece.textContent = ch;
        letters.append(piece);
      });
      text.replaceChildren(sr, letters);
      split.push([text, label]);
    });
  }

  // Magnetic hover: the item leans towards the pointer by a share of its distance from the item's centre.
  const magnet = s.magnet && !api.reducedMotion() ? Number(s.magnet) : 0;
  const onMagnetMove = (e: PointerEvent) => {
    if (e.pointerType !== 'mouse') return;
    const li = (e.currentTarget as HTMLElement).parentElement as HTMLElement;
    const r = li.getBoundingClientRect(); // The item itself does not move, so the centre stays put.
    li.style.setProperty('--uncoder-nav-mx', `${((e.clientX - r.left - r.width / 2) * magnet).toFixed(1)}px`);
    li.style.setProperty('--uncoder-nav-my', `${((e.clientY - r.top - r.height / 2) * magnet).toFixed(1)}px`);
    li.classList.add('is-magnet');
  };
  const onMagnetLeave = (e: PointerEvent) => {
    const li = (e.currentTarget as HTMLElement).parentElement as HTMLElement;
    li.classList.remove('is-magnet');
    li.style.removeProperty('--uncoder-nav-mx');
    li.style.removeProperty('--uncoder-nav-my');
  };
  if (magnet) {
    topLinks.forEach((link) => {
      link.addEventListener('pointermove', onMagnetMove);
      link.addEventListener('pointerleave', onMagnetLeave);
    });
  }

  // Sliding highlight: one pill, the list's first item (hidden from assistive tech), glides to the item under
  // the pointer or keyboard focus and rests on the current page (scrollspy can change it while scrolling).
  const glide = s.fx === 'highlight' && main ? document.createElement('li') : null;
  let glideTarget: HTMLElement | null = null;
  const currentLink = () => main?.querySelector<HTMLElement>(':scope > :is(.uncoder-menu__item--current, .uncoder-menu__item--ancestor) > .uncoder-menu__link') ?? null;
  const moveGlide = (link: HTMLElement | null) => {
    if (!glide || !main) return;
    glideTarget = link;
    if (!link || !link.offsetWidth) {
      glide.classList.remove('is-on');
      return;
    }
    const box = main.getBoundingClientRect();
    const r = link.getBoundingClientRect();
    // Appearing from nothing: jump into place, then fade in (no slide from the last spot).
    const jump = !glide.classList.contains('is-on');
    glide.classList.toggle('is-jump', jump);
    glide.style.setProperty('--uncoder-glide-x', `${(r.left - box.left - main.clientLeft).toFixed(1)}px`);
    glide.style.setProperty('--uncoder-glide-y', `${(r.top - box.top - main.clientTop).toFixed(1)}px`);
    glide.style.setProperty('--uncoder-glide-w', `${r.width.toFixed(1)}px`);
    glide.style.setProperty('--uncoder-glide-h', `${r.height.toFixed(1)}px`);
    if (jump) void glide.offsetWidth;
    glide.classList.add('is-on');
    if (jump) requestAnimationFrame(() => glide.classList.remove('is-jump'));
  };
  const rest = () => moveGlide(currentLink());
  const onGlideEnter = (e: Event) => moveGlide(e.currentTarget as HTMLElement);
  const onGlideFocus = (e: FocusEvent) => {
    const link = (e.target as HTMLElement).closest<HTMLElement>('.uncoder-menu__link');
    if (link && topLinks.includes(link)) moveGlide(link);
  };
  const busy = () => !!main && (main.matches(':hover') || !!main.querySelector(':focus-visible'));
  const onGlideLeave = () => {
    if (!main?.querySelector(':focus-visible')) rest();
  };
  const onGlideBlur = (e: FocusEvent) => {
    if (!main?.contains(e.relatedTarget as Node | null) && !main?.matches(':hover')) rest();
  };
  const glideResize = glide ? new ResizeObserver(() => moveGlide(glideTarget)) : null;
  // The current page can change without a reload (scrollspy): follow it while nobody is on the menu.
  const glideWatch = glide
    ? new MutationObserver((records) => {
        if (records.some((r) => r.target !== glide && (r.target as Element).parentElement === main) && !busy()) rest();
      })
    : null;
  if (glide && main) {
    glide.className = 'uncoder-menu__glide';
    glide.setAttribute('aria-hidden', 'true');
    glide.setAttribute('role', 'presentation');
    main.prepend(glide);
    topLinks.forEach((link) => link.addEventListener('pointerenter', onGlideEnter));
    main.addEventListener('pointerleave', onGlideLeave);
    main.addEventListener('focusin', onGlideFocus);
    main.addEventListener('focusout', onGlideBlur);
    glideResize?.observe(main);
    glideWatch?.observe(main, { subtree: true, attributes: true, attributeFilter: ['class'] });
    rest();
    document.fonts?.ready.then(() => moveGlide(glideTarget));
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

  // "Panel under the header" hangs from the bottom edge of the header template (not from the toggle).
  const header = s.attach === 'header' ? root.closest<HTMLElement>('.uncoder-location--header, header') : null;

  const position = () => {
    if (!panel || dialog) return;
    const rect = root.getBoundingClientRect();
    if (s.stretch) {
      root.style.setProperty('--uncoder-nav-panel-x', `${-rect.left}px`);
      root.style.setProperty('--uncoder-nav-panel-w', `${html.clientWidth}px`);
    } else if (s.row) {
      // "Match the header row": the dropdown lines up under the container the menu sits in (a floating bar).
      const row = root.parentElement?.closest<HTMLElement>('.uncoder-container');
      if (row) {
        const r = row.getBoundingClientRect();
        root.style.setProperty('--uncoder-nav-panel-x', `${r.left - rect.left}px`);
        root.style.setProperty('--uncoder-nav-panel-w', `${r.width}px`);
        root.style.setProperty('--uncoder-nav-panel-top', `${r.bottom - rect.top}px`);
      }
    }
    if (header) {
      const bottom = header.getBoundingClientRect().bottom;
      root.style.setProperty('--uncoder-nav-panel-top', `${bottom - rect.top}px`);
      root.style.setProperty('--uncoder-nav-panel-vtop', `${Math.max(0, bottom)}px`);
    }
  };

  let scrollRaf = 0;
  const onScroll = () => {
    if (!header || !isOpen()) return;
    scrollRaf ||= requestAnimationFrame(() => {
      scrollRaf = 0;
      position();
    });
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
  if (header) window.addEventListener('scroll', onScroll, { passive: true });

  return () => {
    root.removeEventListener('click', onClick);
    root.removeEventListener('keydown', onKeydown);
    main?.removeEventListener('focusout', onFocusOut);
    document.removeEventListener('pointerdown', onDocumentPointer);
    dialog?.removeEventListener('cancel', onCancel);
    window.removeEventListener('resize', onResize);
    window.removeEventListener('scroll', onScroll);
    cancelAnimationFrame(scrollRaf);
    topLinks.forEach((link) => {
      link.removeEventListener('pointermove', onMagnetMove);
      link.removeEventListener('pointerleave', onMagnetLeave);
      link.removeEventListener('pointerenter', onGlideEnter);
    });
    main?.removeEventListener('pointerleave', onGlideLeave);
    main?.removeEventListener('focusin', onGlideFocus);
    main?.removeEventListener('focusout', onGlideBlur);
    glideResize?.disconnect();
    glideWatch?.disconnect();
    glide?.remove();
    split.forEach(([text, label]) => (text.textContent = label));
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
