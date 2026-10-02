// Flip Box: hover and keyboard focus are handled in CSS. On touch/pen devices a tap toggles
// .uncoder-flip-box--flipped (taps on links/buttons inside the card keep working), a tap outside
// or Escape turns the card back.
window.UncoderWB.register('flip-box', (el) => {
  const box = (el.matches('.uncoder-flip-box') ? (el as HTMLElement) : el.querySelector<HTMLElement>('.uncoder-flip-box'));
  if (!box) return;

  const FLIPPED = 'uncoder-flip-box--flipped';
  const canHover = () => window.matchMedia?.('(hover: hover)').matches ?? true;
  let pointer = '';

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
