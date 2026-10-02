// Before / after comparison: pointer dragging (mouse, touch, pen) on the images, optional
// follow-the-mouse, and a native range input for keyboard and screen reader users.
window.UncoderWB.register('image-compare', (el, api) => {
  const root = (el.matches('.uncoder-image-compare') ? (el as HTMLElement) : el.querySelector<HTMLElement>(':scope > .uncoder-image-compare'));
  const media = root?.querySelector<HTMLElement>(':scope > .uncoder-image-compare__media');
  const range = root?.querySelector<HTMLInputElement>(':scope > .uncoder-image-compare__range');
  if (!root || !media || !range) return;

  const s = api.settings<{ hover?: boolean; before?: string; after?: string }>(el);
  const vertical = root.classList.contains('uncoder-image-compare--vertical');
  let frame = 0;
  let pending = 0;
  let dragging: number | null = null;

  const valueText = (v: number) => {
    const before = (s.before || '').trim();
    const after = (s.after || '').trim();
    return before && after ? `${before} ${v}%, ${after} ${100 - v}%` : `${v}%`;
  };

  const set = (value: number, fromRange = false) => {
    const v = Math.round(Math.min(100, Math.max(0, value)) * 10) / 10;
    pending = v;
    if (!fromRange) range.value = String(Math.round(v));
    range.setAttribute('aria-valuetext', valueText(Math.round(v)));
    if (!frame) {
      frame = requestAnimationFrame(() => {
        frame = 0;
        root.style.setProperty('--uncoder-compare-pos', `${pending}%`);
      });
    }
  };

  const fromPointer = (e: PointerEvent) => {
    const r = media.getBoundingClientRect();
    const v = vertical ? ((e.clientY - r.top) / (r.height || 1)) * 100 : ((e.clientX - r.left) / (r.width || 1)) * 100;
    set(v);
  };

  // Start from the rendered position (the CSS variable), not from possibly stale markup.
  const initial = parseFloat(getComputedStyle(root).getPropertyValue('--uncoder-compare-pos'));
  if (!Number.isNaN(initial)) {
    range.value = String(Math.round(initial));
    range.setAttribute('aria-valuetext', valueText(Math.round(initial)));
  }

  const onDown = (e: PointerEvent) => {
    if (e.button !== 0 && e.pointerType === 'mouse') return;
    dragging = e.pointerId;
    try {
      media.setPointerCapture(e.pointerId);
    } catch {
      /* ignore */
    }
    root.classList.add('is-dragging');
    fromPointer(e);
    range.focus({ preventScroll: true });
  };
  const onMove = (e: PointerEvent) => {
    if (dragging === e.pointerId) {
      if (e.cancelable) e.preventDefault();
      fromPointer(e);
    } else if (s.hover && e.pointerType === 'mouse' && dragging === null) {
      fromPointer(e);
    }
  };
  const onUp = (e: PointerEvent) => {
    if (dragging !== e.pointerId) return;
    dragging = null;
    root.classList.remove('is-dragging');
  };
  const onInput = () => set(Number(range.value), true);
  const onKey = (e: KeyboardEvent) => {
    // Vertical: Up moves the divider up (the native range would increase the value).
    if (!vertical || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
    e.preventDefault();
    const step = e.shiftKey ? 10 : 1;
    set(Number(range.value) + (e.key === 'ArrowDown' ? step : -step));
  };
  const onDragStart = (e: DragEvent) => e.preventDefault();

  media.addEventListener('pointerdown', onDown);
  media.addEventListener('pointermove', onMove);
  media.addEventListener('pointerup', onUp);
  media.addEventListener('pointercancel', onUp);
  media.addEventListener('dragstart', onDragStart);
  range.addEventListener('input', onInput);
  range.addEventListener('keydown', onKey);

  return () => {
    media.removeEventListener('pointerdown', onDown);
    media.removeEventListener('pointermove', onMove);
    media.removeEventListener('pointerup', onUp);
    media.removeEventListener('pointercancel', onUp);
    media.removeEventListener('dragstart', onDragStart);
    range.removeEventListener('input', onInput);
    range.removeEventListener('keydown', onKey);
    if (frame) cancelAnimationFrame(frame);
    root.classList.remove('is-dragging');
  };
});
