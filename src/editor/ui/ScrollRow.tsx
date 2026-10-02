import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Icon } from './Icon';

/**
 * One row that scrolls sideways instead of wrapping: no scrollbar, a faded edge and an arrow button on each
 * side that has more to show; the mouse wheel scrolls it too.
 */
export function ScrollRow({ children, className, role, label, activeKey }: { children: ReactNode; className?: string; role?: string; label?: string; activeKey?: string }) {
  const track = useRef<HTMLDivElement>(null);
  const [edges, setEdges] = useState({ start: false, end: false });

  useEffect(() => {
    const el = track.current;
    if (!el) return;
    const update = () => {
      const start = el.scrollLeft > 2;
      const end = el.scrollLeft + el.clientWidth < el.scrollWidth - 2;
      setEdges((e) => (e.start === start && e.end === end ? e : { start, end }));
    };
    update();
    const onWheel = (e: WheelEvent) => {
      if (Math.abs(e.deltaY) <= Math.abs(e.deltaX) || el.scrollWidth <= el.clientWidth) return;
      el.scrollLeft += e.deltaY;
      e.preventDefault();
    };
    el.addEventListener('scroll', update, { passive: true });
    el.addEventListener('wheel', onWheel, { passive: false });
    // Content arrives late (e.g. the list of icon libraries): watch the row and what is in it.
    const ro = new ResizeObserver(update);
    ro.observe(el);
    const mo = new MutationObserver(() => {
      Array.from(el.children).forEach((c) => ro.observe(c));
      update();
    });
    mo.observe(el, { childList: true });
    Array.from(el.children).forEach((c) => ro.observe(c));
    return () => {
      el.removeEventListener('scroll', update);
      el.removeEventListener('wheel', onWheel);
      ro.disconnect();
      mo.disconnect();
    };
  }, []);

  // Keep the active item in view.
  useEffect(() => {
    const el = track.current;
    const active = el?.querySelector<HTMLElement>('.is-active');
    if (!el || !active) return;
    const a = active.getBoundingClientRect();
    const r = el.getBoundingClientRect();
    if (a.left < r.left + 32) el.scrollBy({ left: a.left - r.left - 40, behavior: 'smooth' });
    else if (a.right > r.right - 32) el.scrollBy({ left: a.right - r.right + 40, behavior: 'smooth' });
  }, [activeKey]);

  const by = (dir: 1 | -1) => track.current?.scrollBy({ left: dir * track.current.clientWidth * 0.7, behavior: 'smooth' });

  return (
    <div className={`uncoder-ui-scrollrow${className ? ` ${className}` : ''}`}>
      <div className={`uncoder-ui-scrollrow__inner${edges.start ? ' has-start' : ''}${edges.end ? ' has-end' : ''}`}>
        {edges.start && (
          <button type="button" className="uncoder-ui-scrollrow__arrow is-start" aria-label="Scroll left" tabIndex={-1} onClick={() => by(-1)}>
            <Icon name="chevron-left" size={14} />
          </button>
        )}
        <div ref={track} className="uncoder-ui-scrollrow__track" role={role} aria-label={label}>
          {children}
        </div>
        {edges.end && (
          <button type="button" className="uncoder-ui-scrollrow__arrow is-end" aria-label="Scroll right" tabIndex={-1} onClick={() => by(1)}>
            <Icon name="chevron-right" size={14} />
          </button>
        )}
      </div>
    </div>
  );
}
