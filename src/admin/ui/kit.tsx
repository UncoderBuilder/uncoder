import { createContext, useContext, useEffect, useId, useRef, type ReactNode, type SelectHTMLAttributes } from 'react';
import { create } from 'zustand';
import { Icon } from '@editor/ui/Icon';
import { Button, IconButton, Spinner } from '@editor/ui/primitives';
import { cx } from '../lib/format';
import { copyText } from '../lib/hooks';
import { dismissToast, useToasts } from '../lib/toast';

/* ------------------------------------------------------------------ Page structure */

/** The sub-view a screen shows (“Popups” in Theme Builder), for the breadcrumb in the app bar. */
export const useCrumb = create<{ sub: string | null }>(() => ({ sub: null }));
export function useSubCrumb(label: string | null) {
  useEffect(() => {
    useCrumb.setState({ sub: label });
  }, [label]);
}

/** Inside another screen (Custom fonts in the Design System, Custom code in Settings), a screen's page header becomes a section header. */
const EmbeddedContext = createContext(false);
export function Embedded({ children }: { children: ReactNode }) {
  return <EmbeddedContext.Provider value>{children}</EmbeddedContext.Provider>;
}

export function PageHeader({ title, description, actions, eyebrow }: { title: ReactNode; description?: ReactNode; actions?: ReactNode; eyebrow?: ReactNode }) {
  const embedded = useContext(EmbeddedContext);
  if (embedded) {
    return (
      <div className="uncoder-ui-sechead">
        <div className="uncoder-ui-sechead__text">
          <h2 className="uncoder-ui-sechead__title">{title}</h2>
          {description && <p className="uncoder-ui-sechead__desc">{description}</p>}
        </div>
        {actions && <div className="uncoder-ui-sechead__actions">{actions}</div>}
      </div>
    );
  }
  return (
    <div className="uncoder-ui-pagehead">
      <div className="uncoder-ui-pagehead__text">
        {eyebrow && <div className="uncoder-ui-pagehead__eyebrow">{eyebrow}</div>}
        <h1 className="uncoder-ui-pagehead__title">{title}</h1>
        {description && <p className="uncoder-ui-pagehead__desc">{description}</p>}
      </div>
      {actions && <div className="uncoder-ui-pagehead__actions">{actions}</div>}
    </div>
  );
}

export function Card({ title, description, actions, children, className, flush, id }: { title?: ReactNode; description?: ReactNode; actions?: ReactNode; children?: ReactNode; className?: string; flush?: boolean; id?: string }) {
  return (
    <section className={cx('uncoder-ui-card', flush && 'uncoder-ui-card--flush', className)} id={id}>
      {(title || actions) && (
        <header className="uncoder-ui-card__head">
          <div className="uncoder-ui-card__titles">
            {title && <h2 className="uncoder-ui-card__title">{title}</h2>}
            {description && <p className="uncoder-ui-card__desc">{description}</p>}
          </div>
          {actions && <div className="uncoder-ui-card__actions">{actions}</div>}
        </header>
      )}
      {children}
    </section>
  );
}

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'accent' | 'info';

export function Badge({ tone = 'neutral', dot, children, title }: { tone?: Tone; dot?: boolean; children: ReactNode; title?: string }) {
  return (
    <span className={`uncoder-ui-badge uncoder-ui-badge--${tone}`} title={title}>
      {dot && <span className="uncoder-ui-badge__dot" aria-hidden />}
      {children}
    </span>
  );
}

const STATUS: Record<string, [string, Tone]> = {
  publish: ['Published', 'success'],
  draft: ['Draft', 'neutral'],
  pending: ['Pending', 'warning'],
  private: ['Private', 'info'],
  future: ['Scheduled', 'info'],
  trash: ['Trash', 'danger'],
};

export function PostStatus({ status }: { status: string }) {
  const [label, tone] = STATUS[status] ?? [status, 'neutral'];
  return (
    <Badge tone={tone} dot>
      {label}
    </Badge>
  );
}

export function Callout({ tone = 'info', icon, title, children, actions }: { tone?: Tone; icon?: string; title?: ReactNode; children?: ReactNode; actions?: ReactNode }) {
  const fallback = tone === 'warning' ? 'triangle-alert' : tone === 'danger' ? 'circle-alert' : tone === 'success' ? 'circle-check' : 'info';
  return (
    <div className={`uncoder-ui-callout uncoder-ui-callout--${tone}`} role={tone === 'danger' ? 'alert' : undefined}>
      <Icon name={icon ?? fallback} size={16} className="uncoder-ui-callout__icon" />
      <div className="uncoder-ui-callout__text">
        {title && <strong className="uncoder-ui-callout__title">{title}</strong>}
        {children && <div>{children}</div>}
      </div>
      {actions && <div className="uncoder-ui-callout__actions">{actions}</div>}
    </div>
  );
}

/* ------------------------------------------------------------------ States */

export function SkeletonRows({ rows = 4, cols = 4 }: { rows?: number; cols?: number }) {
  return (
    <div className="uncoder-ui-skel" aria-busy="true" aria-label="Loading">
      {Array.from({ length: rows }, (_, r) => (
        <div className="uncoder-ui-skel__row" key={r}>
          {Array.from({ length: cols }, (_, c) => (
            <span className="uncoder-ui-skel__bar" key={c} style={{ width: c === 0 ? '32%' : `${12 + ((r * 7 + c * 11) % 14)}%` }} />
          ))}
        </div>
      ))}
    </div>
  );
}

export function ErrorState({ error, onRetry }: { error: Error; onRetry?: () => void }) {
  return (
    <div className="uncoder-ui-state uncoder-ui-state--error" role="alert">
      <span className="uncoder-ui-state__icon">
        <Icon name="circle-alert" size={20} />
      </span>
      <strong>Could not load this data</strong>
      <p>{error.message}</p>
      {onRetry && (
        <Button icon="refresh-cw" size="sm" onClick={onRetry}>
          Try again
        </Button>
      )}
    </div>
  );
}

export function EmptyState({ icon, title, children, action }: { icon: string; title: string; children?: ReactNode; action?: ReactNode }) {
  return (
    <div className="uncoder-ui-state">
      <span className="uncoder-ui-state__icon">
        <Icon name={icon} size={20} />
      </span>
      <strong>{title}</strong>
      {children && <p>{children}</p>}
      {action && <div className="uncoder-ui-state__action">{action}</div>}
    </div>
  );
}

/* ------------------------------------------------------------------ Copy */

export function CopyButton({ text, label = 'Copy', what, size = 'sm', variant = 'secondary' }: { text: string; label?: string; what?: string; size?: 'sm' | 'md'; variant?: 'secondary' | 'ghost' | 'primary' }) {
  return (
    <Button size={size} variant={variant} icon="copy" onClick={() => copyText(text, what)}>
      {label}
    </Button>
  );
}

export function CopyField({ value, label, mono = true, what, secret }: { value: string; label?: string; mono?: boolean; what?: string; secret?: boolean }) {
  const ref = useRef<HTMLInputElement>(null);
  return (
    <div className={cx('uncoder-ui-copyfield', secret && 'is-secret')}>
      <input
        ref={ref}
        className={cx('uncoder-ui-copyfield__input', mono && 'is-mono')}
        value={value}
        readOnly
        aria-label={label ?? 'Value'}
        onFocus={(e) => e.currentTarget.select()}
        spellCheck={false}
      />
      <Button size="sm" variant={secret ? 'primary' : 'secondary'} icon="copy" onClick={() => copyText(value, what)}>
        Copy
      </Button>
    </div>
  );
}

export function CodeBlock({ code, label, file, lang }: { code: string; label?: string; file?: string; lang?: string }) {
  return (
    <figure className="uncoder-ui-code">
      <figcaption className="uncoder-ui-code__bar">
        <span className="uncoder-ui-code__file">
          <Icon name={lang === 'shell' ? 'terminal' : 'file-text'} size={13} />
          {file ?? label ?? lang}
        </span>
        <IconButton icon="copy" label={`Copy ${file ?? label ?? 'snippet'}`} size={14} onClick={() => copyText(code, 'Snippet copied')} />
      </figcaption>
      <pre className="uncoder-ui-code__pre" tabIndex={0}>
        <code>{code}</code>
      </pre>
    </figure>
  );
}

/* ------------------------------------------------------------------ Form fields */

export function Field({ label, help, children, htmlFor, inline, error }: { label: ReactNode; help?: ReactNode; children: ReactNode; htmlFor?: string; inline?: boolean; error?: string }) {
  return (
    <div className={cx('uncoder-ui-fld', inline && 'uncoder-ui-fld--inline')}>
      <div className="uncoder-ui-fld__text">
        <label className="uncoder-ui-fld__label" htmlFor={htmlFor}>
          {label}
        </label>
        {help && <div className="uncoder-ui-fld__help">{help}</div>}
      </div>
      <div className="uncoder-ui-fld__control">
        {children}
        {error && <div className="uncoder-ui-fld__error">{error}</div>}
      </div>
    </div>
  );
}

/** A settings row: label + description on the left, control on the right. */
export function SettingRow({ title, description, children, htmlFor, danger }: { title: ReactNode; description?: ReactNode; children: ReactNode; htmlFor?: string; danger?: boolean }) {
  return (
    <div className={cx('uncoder-ui-setrow', danger && 'is-danger')}>
      <div className="uncoder-ui-setrow__text">
        <label className="uncoder-ui-setrow__title" htmlFor={htmlFor}>
          {title}
        </label>
        {description && <div className="uncoder-ui-setrow__desc">{description}</div>}
      </div>
      <div className="uncoder-ui-setrow__control">{children}</div>
    </div>
  );
}

export function Checkbox({ checked, onChange, label, description, disabled, id }: { checked: boolean; onChange: (v: boolean) => void; label: ReactNode; description?: ReactNode; disabled?: boolean; id?: string }) {
  const auto = useId();
  const cid = id ?? auto;
  return (
    <label className={cx('uncoder-ui-checkrow', disabled && 'is-disabled')} htmlFor={cid}>
      <input id={cid} type="checkbox" className="uncoder-ui-check" checked={checked} disabled={disabled} onChange={(e) => onChange(e.currentTarget.checked)} />
      <span className="uncoder-ui-checkrow__text">
        <span className="uncoder-ui-checkrow__label">{label}</span>
        {description && <span className="uncoder-ui-checkrow__desc">{description}</span>}
      </span>
    </label>
  );
}

export function Select({ options, className, ...rest }: SelectHTMLAttributes<HTMLSelectElement> & { options: Array<{ value: string; label: string; disabled?: boolean }> }) {
  return (
    <select className={cx('uncoder-ui-select', className)} {...rest}>
      {options.map((o) => (
        <option key={o.value} value={o.value} disabled={o.disabled}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

export function SearchInput({ value, onChange, placeholder = 'Search…', label = 'Search', width }: { value: string; onChange: (v: string) => void; placeholder?: string; label?: string; width?: number }) {
  return (
    <div className="uncoder-ui-searchbox" style={width ? { width } : undefined}>
      <Icon name="search" size={14} />
      <input type="search" value={value} placeholder={placeholder} aria-label={label} onChange={(e) => onChange(e.currentTarget.value)} />
      {value && <IconButton icon="x" label="Clear search" size={13} className="uncoder-ui-searchbox__clear" onClick={() => onChange('')} />}
    </div>
  );
}

/* ------------------------------------------------------------------ Tabs */

export interface TabDef<T extends string> {
  id: T;
  label: ReactNode;
  icon?: string;
  count?: number | null;
}

/** Underline tabs with roving keyboard focus (arrow keys). Panels are rendered by the caller. */
export function Tabs<T extends string>({ tabs, value, onChange, label, idBase }: { tabs: Array<TabDef<T>>; value: T; onChange: (v: T) => void; label: string; idBase: string }) {
  const onKeyDown = (e: React.KeyboardEvent) => {
    const i = tabs.findIndex((t) => t.id === value);
    let next = -1;
    if (e.key === 'ArrowRight') next = (i + 1) % tabs.length;
    else if (e.key === 'ArrowLeft') next = (i - 1 + tabs.length) % tabs.length;
    else if (e.key === 'Home') next = 0;
    else if (e.key === 'End') next = tabs.length - 1;
    if (next < 0) return;
    e.preventDefault();
    onChange(tabs[next].id);
    requestAnimationFrame(() => document.getElementById(`${idBase}-tab-${tabs[next].id}`)?.focus());
  };
  return (
    <div className="uncoder-ui-tabbar" role="tablist" aria-label={label} onKeyDown={onKeyDown}>
      {tabs.map((t) => {
        const active = t.id === value;
        return (
          <button
            key={t.id}
            id={`${idBase}-tab-${t.id}`}
            type="button"
            role="tab"
            aria-selected={active}
            aria-controls={`${idBase}-panel`}
            tabIndex={active ? 0 : -1}
            className={cx('uncoder-ui-tabbar__tab', active && 'is-active')}
            onClick={() => onChange(t.id)}
          >
            {t.icon && <Icon name={t.icon} size={14} />}
            <span>{t.label}</span>
            {t.count !== undefined && t.count !== null && <span className="uncoder-ui-tabbar__count">{t.count}</span>}
          </button>
        );
      })}
    </div>
  );
}

export function TabPanel({ idBase, active, children }: { idBase: string; active: string; children: ReactNode }) {
  return (
    <div id={`${idBase}-panel`} role="tabpanel" aria-labelledby={`${idBase}-tab-${active}`} className="uncoder-ui-tabpanel">
      {children}
    </div>
  );
}

/* ------------------------------------------------------------------ Section nav (screens with several parts) */

export interface NavItem<T extends string> {
  id: T;
  label: ReactNode;
  icon: string;
  count?: number | string | null;
  /** Small marker after the label: unsaved changes, or something that needs attention. */
  flag?: 'dirty' | 'warning' | null;
  flagLabel?: string;
}
export interface NavGroup<T extends string> {
  label?: string;
  items: Array<NavItem<T>>;
}

/**
 * Left navigation of a multi-part screen (Theme Builder types, Settings sections). Links, so the
 * section is in the URL hash and survives a reload; the page chooses what to render for it.
 */
export function SectionNav<T extends string>({ groups, value, onChange, label }: { groups: Array<NavGroup<T>>; value: T; onChange: (v: T) => void; label: string }) {
  const ref = useRef<HTMLElement>(null);
  // Narrow screens turn the nav into a horizontal strip: keep the current section in view.
  useEffect(() => {
    const nav = ref.current;
    const item = nav?.querySelector<HTMLElement>('.is-active');
    if (!nav || !item || nav.scrollWidth <= nav.clientWidth) return;
    const left = item.offsetLeft - nav.offsetLeft;
    if (left < nav.scrollLeft || left + item.offsetWidth > nav.scrollLeft + nav.clientWidth) nav.scrollLeft = Math.max(0, left - 24);
  }, [value]);
  return (
    <nav ref={ref} className="uncoder-ui-secnav" aria-label={label}>
      {groups.map((g, gi) =>
        g.items.length ? (
          <div className="uncoder-ui-secnav__group" key={g.label ?? gi} role={g.label ? 'group' : undefined} aria-label={g.label}>
            {g.label && <div className="uncoder-ui-secnav__heading">{g.label}</div>}
            {g.items.map((item) => {
              const active = item.id === value;
              return (
                <a
                  key={item.id}
                  href={`#${item.id}`}
                  className={cx('uncoder-ui-secnav__item', active && 'is-active')}
                  aria-current={active ? 'page' : undefined}
                  onClick={(e) => {
                    e.preventDefault();
                    onChange(item.id);
                  }}
                >
                  <Icon name={item.icon} size={15} />
                  <span className="uncoder-ui-secnav__label">{item.label}</span>
                  {item.flag && <span className={cx('uncoder-ui-secnav__flag', `is-${item.flag}`)} title={item.flagLabel} aria-label={item.flagLabel} />}
                  {item.count !== undefined && item.count !== null && item.count !== '' && <span className="uncoder-ui-secnav__count">{item.count}</span>}
                </a>
              );
            })}
          </div>
        ) : null,
      )}
    </nav>
  );
}

/** Section nav + content, side by side (stacked on narrow screens). */
export function Workspace({ nav, children }: { nav: ReactNode; children: ReactNode }) {
  return (
    <div className="uncoder-ui-workspace">
      {nav}
      <div className="uncoder-ui-workspace__main">{children}</div>
    </div>
  );
}

/* ------------------------------------------------------------------ Pagination & save bar */

export function Pager({ page, hasPrev, hasNext, onPage, summary }: { page: number; hasPrev: boolean; hasNext: boolean; onPage: (p: number) => void; summary?: ReactNode }) {
  if (!hasPrev && !hasNext && !summary) return null;
  return (
    <div className="uncoder-ui-pager">
      <span className="uncoder-ui-pager__summary">{summary}</span>
      <div className="uncoder-ui-pager__btns">
        <Button size="sm" variant="secondary" icon="chevron-left" disabled={!hasPrev} onClick={() => onPage(page - 1)}>
          Previous
        </Button>
        <Button size="sm" variant="secondary" iconRight="chevron-right" disabled={!hasNext} onClick={() => onPage(page + 1)}>
          Next
        </Button>
      </div>
    </div>
  );
}

export function SaveBar({ dirty, saving, onSave, onDiscard, message = 'You have unsaved changes' }: { dirty: boolean; saving: boolean; onSave: () => void; onDiscard: () => void; message?: string }) {
  return (
    <div className={cx('uncoder-ui-savebar', dirty && 'is-visible')} aria-hidden={!dirty}>
      <div className="uncoder-ui-savebar__inner">
        <span className="uncoder-ui-savebar__msg">
          <span className="uncoder-ui-savebar__dot" aria-hidden />
          {message}
        </span>
        <Button variant="ghost" onClick={onDiscard} disabled={!dirty || saving} tabIndex={dirty ? 0 : -1}>
          Discard
        </Button>
        <Button variant="primary" onClick={onSave} loading={saving} disabled={!dirty} tabIndex={dirty ? 0 : -1}>
          Save changes
        </Button>
      </div>
    </div>
  );
}

export function InlineSpinner({ label }: { label?: string }) {
  return (
    <span className="uncoder-ui-inline-spin">
      <Spinner size={13} />
      {label}
    </span>
  );
}

/* ------------------------------------------------------------------ Toasts */

const TOAST_ICON = { info: 'info', success: 'circle-check', error: 'circle-alert', warning: 'triangle-alert' } as const;

export function Toasts() {
  const toasts = useToasts((s) => s.toasts);
  return (
    <div className="uncoder-ui-toasts" role="status" aria-live="polite">
      {toasts.map((t) => (
        <div key={t.id} className={`uncoder-ui-toast uncoder-ui-toast--${t.kind}`}>
          <Icon name={TOAST_ICON[t.kind]} size={15} />
          <span className="uncoder-ui-toast__msg">{t.message}</span>
          {t.action && (
            <button
              type="button"
              className="uncoder-ui-toast__action"
              onClick={() => {
                t.action!.run();
                dismissToast(t.id);
              }}
            >
              {t.action.label}
            </button>
          )}
          <button type="button" className="uncoder-ui-toast__close" aria-label="Dismiss notification" onClick={() => dismissToast(t.id)}>
            <Icon name="x" size={13} />
          </button>
        </div>
      ))}
    </div>
  );
}
