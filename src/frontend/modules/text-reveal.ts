// Text reveal (Advanced → Motion & effects on text widgets): splits the widget's text into words,
// letters or lines and lets them fade / slide / blur in one after another when scrolled into view.
// With the "scroll" trigger the pieces follow the scroll instead: they start faint and light up as the
// text moves up the screen (and dim again when scrolling back).
// Markup and links stay intact (only text nodes are wrapped). Letter mode keeps a screen-reader copy
// of each text so the word is not spelled out; reduced motion and the editor show the text as is.
window.UncoderWB.register('text-reveal', (el, api) => {
  const [mode, effect, trigger] = (el.dataset.uncoderReveal || 'words fade-up').split(' ');
  const scroll = trigger === 'scroll';
  const ready = () => el.classList.add('uncoder-reveal--ready');
  if (api.editor || api.reducedMotion() || !('IntersectionObserver' in window)) {
    ready();
    el.classList.add('uncoder-reveal--in');
    return;
  }
  el.style.setProperty('--uncoder-reveal-stagger-default', mode === 'letters' ? '25ms' : mode === 'lines' ? '140ms' : '60ms');

  const words: HTMLElement[] = [];
  const items: HTMLElement[] = [];
  const skip = 'script,style,svg,noscript,textarea,.uncoder-sr-only';
  const texts: Text[] = [];
  const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, {
    acceptNode: (node) => (node.nodeValue?.trim() && !node.parentElement?.closest(skip) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT),
  });
  while (walker.nextNode()) texts.push(walker.currentNode as Text);

  for (const text of texts) {
    const frag = document.createDocumentFragment();
    let host: Node = frag;
    if (mode === 'letters') {
      const copy = document.createElement('span');
      copy.className = 'uncoder-sr-only';
      copy.textContent = text.nodeValue;
      frag.append(copy);
      const hidden = document.createElement('span');
      hidden.setAttribute('aria-hidden', 'true');
      frag.append(hidden);
      host = hidden;
    }
    for (const part of (text.nodeValue || '').split(/(\s+)/)) {
      if (!part) continue;
      if (/^\s+$/.test(part)) {
        host.appendChild(document.createTextNode(part));
        continue;
      }
      const word = document.createElement('span');
      word.className = 'uncoder-rw';
      if (mode === 'letters') {
        for (const ch of Array.from(part)) {
          const letter = document.createElement('span');
          letter.className = 'uncoder-r';
          letter.textContent = ch;
          word.append(letter);
          items.push(letter);
        }
      } else {
        const inner = document.createElement('span');
        inner.className = 'uncoder-r';
        inner.textContent = part;
        word.append(inner);
        items.push(inner);
      }
      words.push(word);
      host.appendChild(word);
    }
    text.replaceWith(frag);
  }

  const index = () => {
    if (mode === 'lines') {
      // Words on the same line share a line number (grouped by their top position).
      let line = -1;
      let top = -Infinity;
      for (const word of words) {
        const t = word.getBoundingClientRect().top;
        if (Math.abs(t - top) > 2) {
          line++;
          top = t;
        }
        (word.firstElementChild as HTMLElement | null)?.style.setProperty('--i', String(line));
      }
    } else {
      items.forEach((item, i) => item.style.setProperty('--i', String(i)));
    }
  };
  index();
  el.classList.add('uncoder-reveal', `uncoder-reveal--${effect || 'fade-up'}`);
  if (scroll) el.classList.add('uncoder-reveal--scroll');
  ready();

  if (scroll) {
    // Order of each piece (its line in line mode) → lit once the scroll progress passes order / count.
    let order: number[] = [];
    let count = 1;
    const measure = () => {
      if (mode === 'lines') index();
      order = items.map((item, i) => (mode === 'lines' ? Number(item.style.getPropertyValue('--i')) || 0 : i));
      count = Math.max(1, ...order.map((o) => o + 1));
    };
    let raf = 0;
    const update = () => {
      raf = 0;
      const r = el.getBoundingClientRect();
      const vh = window.innerHeight;
      // 0 while the top is below 86% of the screen; 1 once the middle of the text is a little above the centre.
      const p = Math.min(1, Math.max(0, (vh * 0.86 - r.top) / (vh * 0.38 + r.height * 0.5)));
      items.forEach((item, i) => item.classList.toggle('is-lit', order[i] / count < p));
    };
    const queue = () => (raf ||= requestAnimationFrame(update));
    const resize = () => {
      measure();
      queue();
    };
    let listening = false;
    const listen = (on: boolean) => {
      if (on === listening) return;
      listening = on;
      if (on) window.addEventListener('scroll', queue, { passive: true });
      else window.removeEventListener('scroll', queue);
    };
    // Only follow the scroll while the text is near the screen.
    const near = new IntersectionObserver((entries) => {
      listen(entries.some((e) => e.isIntersecting));
      queue();
    }, { rootMargin: '25% 0px 25% 0px' });
    measure();
    update();
    near.observe(el);
    window.addEventListener('resize', resize);
    document.fonts?.ready.then(resize);
    return () => {
      near.disconnect();
      listen(false);
      cancelAnimationFrame(raf);
      window.removeEventListener('resize', resize);
    };
  }

  const io = new IntersectionObserver(
    (entries) => {
      if (!entries.some((e) => e.isIntersecting)) return;
      if (mode === 'lines') index(); // Fonts may have loaded since, which changes the line breaks.
      requestAnimationFrame(() => el.classList.add('uncoder-reveal--in'));
      io.disconnect();
    },
    { rootMargin: '0px 0px -8% 0px', threshold: 0.05 },
  );
  io.observe(el);
  return () => io.disconnect();
});
