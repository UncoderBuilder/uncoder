import { useCallback, useEffect, useLayoutEffect, useRef, useState, type CSSProperties, type ReactNode, type RefObject } from 'react';
import { createPortal } from 'react-dom';
import { Icon } from './Icon';

const portalRoot = () => (document.querySelector('.uncoder-ui-portal') as HTMLElement) ?? document.body;

type Placement = 'bottom-start' | 'bottom-end' | 'top-start' | 'right-start' | 'left-start' | 'bottom';

interface PopoverProps {
  anchor: RefObject<HTMLElement | null> | { x: number; y: number };
  open: boolean;
  onClose: () => void;
  children: ReactNode;
  placement?: Placement;
  width?: number;
  className?: string;
  offset?: number;
  label?: string;
}

export function Popover({ anchor, open, onClose, children, placement = 'bottom-start', width, className, offset = 6, label }: PopoverProps) {
  const ref = useRef<HTMLDivElement>(null);
  const [style, setStyle] = useState<CSSProperties>({ visibility: 'hidden' });

  const position = useCallback(() => {
    const el = ref.current;
    if (!el) return;
    let r: { left: number; top: number; right: number; bottom: number; width: number; height: number };
    if ('current' in anchor) {
      const a = anchor.current;
      if (!a) return;
      r = a.getBoundingClientRect();
    } else {
      r = { left: anchor.x, top: anchor.y, right: anchor.x, bottom: anchor.y, width: 0, height: 0 };
    }
    const pw = el.offsetWidth;
    const ph = el.offsetHeight;
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    let side = placement;
    // Side popovers of the settings panel open next to the panel, towards the canvas (it can sit on either side).
    let edge = { left: r.left, right: r.right };
    if ((side === 'left-start' || side === 'right-start') && 'current' in anchor) {
      const panel = anchor.current?.closest('.uncoder-ui-inspector')?.getBoundingClientRect();
      if (panel) {
        edge = { left: panel.left, right: panel.right };
        side = panel.left + panel.width / 2 < vw / 2 ? 'right-start' : 'left-start';
      }
    }
    // No room on that side: the other side.
    if (side === 'left-start' && edge.left - pw - offset < 8 && edge.right + offset + pw <= vw - 8) side = 'right-start';
    else if (side === 'right-start' && edge.right + offset + pw > vw - 8 && edge.left - pw - offset >= 8) side = 'left-start';
    let left = r.left;
    let top = r.bottom + offset;
    if (side === 'bottom-end') left = r.right - pw;
    if (side === 'bottom') left = r.left + r.width / 2 - pw / 2;
    if (side === 'top-start') top = r.top - ph - offset;
    if (side === 'right-start') {
      left = edge.right + offset;
      top = r.top;
    }
    if (side === 'left-start') {
      left = edge.left - pw - offset;
      top = r.top;
    }
    // No room below: a menu opened at a point (right-click) slides up just enough to fit, as system menus do; one
    // attached to a button flips above it.
    const atPoint = !('current' in anchor);
    if (top + ph > vh - 8) top = Math.max(8, atPoint || side.startsWith('right') || side.startsWith('left') ? vh - ph - 8 : r.top - ph - offset);
    if (top < 8) top = 8;
    if (left + pw > vw - 8) left = vw - pw - 8;
    if (left < 8) left = 8;
    setStyle({ left, top, width });
  }, [anchor, placement, width, offset]);

  useLayoutEffect(() => {
    if (open) position();
  }, [open, position]);

  // Inputs marked data-autofocus get focus once the popover is positioned and visible.
  useEffect(() => {
    if (!open) return;
    const raf = requestAnimationFrame(() => ref.current?.querySelector<HTMLElement>('[data-autofocus]')?.focus());
    return () => cancelAnimationFrame(raf);
  }, [open]);

  useEffect(() => {
    if (!open) return;
    const onDown = (e: PointerEvent) => {
      const t = e.target as Node;
      if (ref.current?.contains(t)) return;
      if ('current' in anchor && anchor.current?.contains(t)) return;
      // Clicks inside another open popover (nested pickers) do not close this one.
      if ((t as Element).closest?.('.uncoder-ui-pop')) return;
      onClose();
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        onClose();
      }
    };
    const ro = new ResizeObserver(position);
    if (ref.current) ro.observe(ref.current);
    window.addEventListener('pointerdown', onDown, true);
    window.addEventListener('keydown', onKey, true);
    window.addEventListener('resize', position);
    return () => {
      ro.disconnect();
      window.removeEventListener('pointerdown', onDown, true);
      window.removeEventListener('keydown', onKey, true);
      window.removeEventListener('resize', position);
    };
  }, [open, onClose, anchor, position]);

  if (!open) return null;
  return createPortal(
    <div ref={ref} className={'uncoder-ui-pop' + (className ? ' ' + className : '')} style={style} role="dialog" aria-label={label}>
      {children}
    </div>,
    portalRoot(),
  );
}

export function usePopover() {
  const [open, setOpen] = useState(false);
  const anchorRef = useRef<HTMLButtonElement>(null);
  const close = useCallback(() => setOpen(false), []);
  const toggle = useCallback(() => setOpen((o) => !o), []);
  return { open, setOpen, close, toggle, anchorRef };
}

export type MenuItem =
  | 'separator'
  | {
      label: string;
      icon?: string;
      shortcut?: string;
      onSelect: () => void;
      danger?: boolean;
      disabled?: boolean;
      checked?: boolean;
    };

export function MenuList({ items, onClose, autoFocus = true }: { items: MenuItem[]; onClose: () => void; autoFocus?: boolean }) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (autoFocus) ref.current?.querySelector<HTMLButtonElement>('button:not(:disabled)')?.focus();
  }, [autoFocus]);
  const onKeyDown = (e: React.KeyboardEvent) => {
    const buttons = Array.from(ref.current?.querySelectorAll<HTMLButtonElement>('button:not(:disabled)') ?? []);
    const i = buttons.indexOf(document.activeElement as HTMLButtonElement);
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      buttons[(i + 1) % buttons.length]?.focus();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      buttons[(i - 1 + buttons.length) % buttons.length]?.focus();
    }
  };
  return (
    <div className="uncoder-ui-menu" role="menu" ref={ref} onKeyDown={onKeyDown}>
      {items.map((item, i) =>
        item === 'separator' ? (
          <div key={i} className="uncoder-ui-menu__sep" role="separator" />
        ) : (
          <button
            key={i}
            type="button"
            role="menuitem"
            className={`uncoder-ui-menu__item${item.danger ? ' is-danger' : ''}`}
            disabled={item.disabled}
            onClick={() => {
              onClose();
              item.onSelect();
            }}
          >
            <span className="uncoder-ui-menu__icon">{item.checked ? <Icon name="check" size={14} /> : item.icon ? <Icon name={item.icon} size={14} /> : null}</span>
            <span className="uncoder-ui-menu__label">{item.label}</span>
            {item.shortcut && <span className="uncoder-ui-menu__shortcut">{item.shortcut}</span>}
          </button>
        ),
      )}
    </div>
  );
}

export function Menu({ anchor, open, onClose, items, placement = 'bottom-start', width = 220 }: { anchor: PopoverProps['anchor']; open: boolean; onClose: () => void; items: MenuItem[]; placement?: Placement; width?: number }) {
  return (
    <Popover anchor={anchor} open={open} onClose={onClose} placement={placement} width={width} className="uncoder-ui-pop--menu">
      <MenuList items={items} onClose={onClose} />
    </Popover>
  );
}
