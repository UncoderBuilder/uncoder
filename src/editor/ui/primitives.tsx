import { forwardRef, useEffect, useLayoutEffect, useRef, useState, type ButtonHTMLAttributes, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { Icon } from './Icon';
import { usePrefs } from '../store/prefs';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'ai';

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: Variant;
  size?: 'sm' | 'md';
  icon?: string;
  iconRight?: string;
  loading?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
  { variant = 'secondary', size = 'md', icon, iconRight, loading, children, className, ...rest },
  ref,
) {
  return (
    <button ref={ref} type="button" className={`uncoder-ui-btn uncoder-ui-btn--${variant} uncoder-ui-btn--${size}${className ? ' ' + className : ''}`} {...rest} disabled={rest.disabled || loading}>
      {loading ? <span className="uncoder-ui-spinner" aria-hidden /> : icon ? <Icon name={icon} size={size === 'sm' ? 13 : 14} /> : null}
      {children}
      {iconRight && <Icon name={iconRight} size={13} />}
    </button>
  );
});

interface IconButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  icon: string;
  label: string;
  size?: number;
  active?: boolean;
  tone?: 'default' | 'danger' | 'accent';
  shortcut?: string;
}

export const IconButton = forwardRef<HTMLButtonElement, IconButtonProps>(function IconButton(
  { icon, label, size = 15, active, tone = 'default', shortcut, className, ...rest },
  ref,
) {
  return (
    <button
      ref={ref}
      type="button"
      aria-label={label}
      data-tip={shortcut ? `${label}  ${shortcut}` : label}
      className={`uncoder-ui-iconbtn${active ? ' is-active' : ''} uncoder-ui-iconbtn--${tone}${className ? ' ' + className : ''}`}
      aria-pressed={active === undefined ? undefined : active}
      {...rest}
    >
      <Icon name={icon} size={size} />
    </button>
  );
});

export function Toggle({ checked, onChange, label, disabled }: { checked: boolean; onChange: (v: boolean) => void; label?: string; disabled?: boolean }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      disabled={disabled}
      className={`uncoder-ui-toggle${checked ? ' is-on' : ''}`}
      onClick={() => onChange(!checked)}
    >
      <span className="uncoder-ui-toggle__knob" />
    </button>
  );
}

export interface SegOption {
  value: string;
  label: string;
  icon?: string;
}

export function Segmented({ options, value, onChange, size = 'md', ariaLabel, allowEmpty }: { options: SegOption[]; value: string; onChange: (v: string) => void; size?: 'sm' | 'md'; ariaLabel?: string; allowEmpty?: boolean }) {
  return (
    <div className={`uncoder-ui-seg uncoder-ui-seg--${size}`} role="radiogroup" aria-label={ariaLabel}>
      {options.map((o) => {
        const active = o.value === value;
        return (
          <button
            key={o.value}
            type="button"
            role="radio"
            aria-checked={active}
            aria-label={o.label}
            data-tip={o.icon ? o.label : undefined}
            className={`uncoder-ui-seg__item${active ? ' is-active' : ''}`}
            onClick={() => onChange(active && allowEmpty ? '' : o.value)}
          >
            {o.icon ? <Icon name={o.icon} size={14} /> : o.label}
          </button>
        );
      })}
    </div>
  );
}

export function Kbd({ children }: { children: ReactNode }) {
  return <kbd className="uncoder-ui-kbd">{children}</kbd>;
}

export function Spinner({ size = 14 }: { size?: number }) {
  return <span className="uncoder-ui-spinner" style={{ width: size, height: size }} aria-hidden />;
}

export function Empty({ icon, title, children }: { icon: string; title: string; children?: ReactNode }) {
  return (
    <div className="uncoder-ui-empty">
      <span className="uncoder-ui-empty__icon">
        <Icon name={icon} size={20} />
      </span>
      <strong>{title}</strong>
      {children && <p>{children}</p>}
    </div>
  );
}

/** Global tooltip layer: any element with data-tip gets a tooltip on hover/focus, with a caret pointing at it. */
export function TooltipLayer({ root }: { root: HTMLElement | null }) {
  const [tip, setTip] = useState<{ text: string; x: number; y: number; below: boolean; right?: boolean } | null>(null);
  const [pos, setPos] = useState<{ left: number; arrow: number } | null>(null);
  const ref = useRef<HTMLDivElement>(null);
  const timer = useRef<number>(0);
  useEffect(() => {
    if (!root) return;
    const show = (e: Event) => {
      const el = (e.target as Element | null)?.closest?.('[data-tip]') as HTMLElement | null;
      window.clearTimeout(timer.current);
      // Preferences → hints off: no tooltips on hover (keyboard focus and ⓘ icons still show them).
      if (el && e.type === 'pointerover' && !usePrefs.getState().hints && !el.hasAttribute('data-tip-force')) {
        setTip(null);
        return;
      }
      if (!el) {
        setTip(null);
        return;
      }
      timer.current = window.setTimeout(() => {
        // Not over the menu or panel its own button has just opened (a click focuses the button too).
        if (el.getAttribute('aria-expanded') === 'true' || !el.isConnected) return;
        const r = el.getBoundingClientRect();
        setPos(null);
        // data-tip-side="right" (the rail): beside the trigger, caret pointing left.
        if (el.getAttribute('data-tip-side') === 'right') {
          setTip({ text: el.getAttribute('data-tip') || '', x: r.right + 10, y: r.top + r.height / 2, below: false, right: true });
          return;
        }
        // Above the trigger (caret pointing down) unless there is no room, then below (caret up).
        const below = r.top < 52;
        setTip({ text: el.getAttribute('data-tip') || '', x: r.left + r.width / 2, y: below ? r.bottom + 9 : r.top - 9, below });
      }, 380);
    };
    const hide = () => {
      window.clearTimeout(timer.current);
      setTip(null);
    };
    root.addEventListener('pointerover', show);
    root.addEventListener('focusin', show);
    root.addEventListener('pointerdown', hide);
    root.addEventListener('focusout', hide);
    root.addEventListener('pointerleave', hide);
    return () => {
      root.removeEventListener('pointerover', show);
      root.removeEventListener('focusin', show);
      root.removeEventListener('pointerdown', hide);
      root.removeEventListener('focusout', hide);
      root.removeEventListener('pointerleave', hide);
    };
  }, [root]);
  // Keep the bubble inside the window; the caret stays on the trigger.
  useLayoutEffect(() => {
    if (!tip || !ref.current) return;
    if (tip.right) {
      setPos({ left: tip.x, arrow: 0 });
      return;
    }
    const w = ref.current.offsetWidth;
    const left = Math.max(6, Math.min(window.innerWidth - w - 6, tip.x - w / 2));
    setPos({ left, arrow: Math.max(8, Math.min(w - 8, tip.x - left)) });
  }, [tip]);
  if (!tip || !tip.text) return null;
  return createPortal(
    <div
      ref={ref}
      className={`uncoder-ui-tooltip${tip.below ? ' is-below' : ''}${tip.right ? ' is-right' : ''}`}
      style={{ left: pos ? pos.left : tip.x, top: tip.y, visibility: pos ? 'visible' : 'hidden', ['--uncoder-ui-tip-arrow' as string]: pos ? `${pos.arrow}px` : '50%' }}
      role="tooltip"
    >
      {tip.text}
    </div>,
    document.querySelector('.uncoder-ui-portal') ?? document.body,
  );
}
