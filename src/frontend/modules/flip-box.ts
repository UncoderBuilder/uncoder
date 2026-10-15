// Flip Box: hover and keyboard focus are handled in CSS. On touch/pen devices a tap toggles
// .uncoder-flip-box--flipped (taps on links/buttons inside the card keep working), a tap outside
// or Escape turns the card back. A --button card turns only with its round + / − buttons; the hidden
// face is inert so keyboards and screen readers only meet the face on show.
window.UncoderWB.register('flip-box', (el, api) => {
  const box = (el.matches('.uncoder-flip-box') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-flip-box'));
  if (!box) return;

  const FLIPPED = 'uncoder-flip-box--flipped';
  const canHover = () => window.matchMedia?.('(hover: hover)').matches ?? true;
  let pointer = '';

  if (box.classList.contains('uncoder-flip-box--button')) {
    const front = box.querySelector<HTMLElement>('.uncoder-flip-box__front');
    const back = box.querySelector<HTMLElement>('.uncoder-flip-box__back');
    const openBtn = box.querySelector<HTMLButtonElement>('[data-flip="open"]');
    const closeBtn = box.querySelector<HTMLButtonElement>('[data-flip="close"]');
    const turn = (on: boolean, moveFocus: boolean) => {
      box.classList.toggle(FLIPPED, on);
      openBtn?.setAttribute('aria-expanded', String(on));
      // In the editor both faces stay editable.
      if (front && !api.editor) front.inert = on;
      if (back && !api.editor) back.inert = !on;
      if (moveFocus) (on ? closeBtn : openBtn)?.focus({ preventScroll: true });
    };
    const onToggle = (e: MouseEvent) => {
      const btn = (e.target as Element | null)?.closest<HTMLElement>('[data-flip]');
      if (!btn || !box.contains(btn)) return;
      // Focus follows only a keyboard press (a click's detail is 0 then), as the pressed button disappears.
      turn(btn.dataset.flip === 'open', e.detail === 0);
    };
    const onEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && box.classList.contains(FLIPPED)) turn(false, true);
    };
    turn(false, false);
    box.addEventListener('click', onToggle);
    box.addEventListener('keydown', onEscape);
    return () => {
      box.removeEventListener('click', onToggle);
      box.removeEventListener('keydown', onEscape);
      box.classList.remove(FLIPPED);
      if (front) front.inert = false;
      if (back) back.inert = false;
    };
  }

  const set = (on: boolean) => {
    box.classList.toggle(FLIPPED, on);
    if (on) document.addEventListener('pointerdown', onOutside, true);
    else document.removeEventListener('pointerdown', onOutside, true);
  };

  const onPointerDown = (e: PointerEvent) => {
    pointer = e.pointerType;
  };

  const onClick = (e: MouseEvent) => {
    const target = e.target as Element | null;
    if (target?.closest('a, button, input, select, textarea, summary, [role="button"]')) return;
    // A mouse on a hover-capable device already flips the card with :hover.
    if ((pointer === 'mouse' || pointer === '') && canHover()) return;
    set(!box.classList.contains(FLIPPED));
  };

  const onOutside = (e: Event) => {
    if (!box.contains(e.target as Node)) set(false);
  };

  const onKey = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && box.classList.contains(FLIPPED)) set(false);
  };

  box.addEventListener('pointerdown', onPointerDown);
  box.addEventListener('click', onClick);
  box.addEventListener('keydown', onKey);

  return () => {
    box.removeEventListener('pointerdown', onPointerDown);
    box.removeEventListener('click', onClick);
    box.removeEventListener('keydown', onKey);
    document.removeEventListener('pointerdown', onOutside, true);
    box.classList.remove(FLIPPED);
  };
});
