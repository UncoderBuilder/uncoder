// Endless scroll (Common_Controls "_scroll_loop" on containers): moves the container's elements into a track with a
// second, inert copy, so the CSS animation can travel by exactly one copy and start over seamlessly. The gap of the
// container goes between the elements and after each copy. Backgrounds and shape dividers stay where they are.
window.UncoderWB.register('scroll-loop', (el, api) => {
  if (api.editor) return; // Editing a moving container is no fun: it stands still in the editor.
  const host = el.querySelector<HTMLElement>(':scope > .uncoder-container__inner') ?? el;
  if (host.querySelector(':scope > .uncoder-scroll-loop__track')) return;
  const items = Array.from(host.children).filter(
    (c) => !/\buncoder-(container__|shape)/.test((c as HTMLElement).className || ''),
  ) as HTMLElement[];
  if (!items.length) return;
  const style = getComputedStyle(host);
  const vertical = el.classList.contains('uncoder-scroll-loop--up') || el.classList.contains('uncoder-scroll-loop--down');
  const gap = vertical ? style.rowGap : style.columnGap;
  el.style.setProperty('--uncoder-scroll-loop-gap', gap && gap !== 'normal' ? gap : '0px');

  const track = document.createElement('div');
  track.className = 'uncoder-scroll-loop__track';
  const group = document.createElement('div');
  group.className = 'uncoder-scroll-loop__group';
  group.append(...items);
  const copy = group.cloneNode(true) as HTMLElement;
  copy.setAttribute('aria-hidden', 'true');
  copy.inert = true;
  copy.querySelectorAll('[id]').forEach((n) => n.removeAttribute('id'));
  track.append(group, copy);
  host.append(track);

  return () => {
    host.append(...Array.from(group.children));
    track.remove();
    el.style.removeProperty('--uncoder-scroll-loop-gap');
  };
});
