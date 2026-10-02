import { useEffect, useId, useRef, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { create } from 'zustand';
import { Button, IconButton } from '@editor/ui/primitives';
import { cx } from '../lib/format';

const portalRoot = () => (document.querySelector('.uncoder-ui-admin-root .uncoder-ui-portal') as HTMLElement) ?? document.body;

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/** Focus trap + Escape + focus restore, shared by dialogs and drawers. */
function useModal(open: boolean, onClose: () => void, ref: React.RefObject<HTMLDivElement | null>) {
  const closeRef = useRef(onClose);
  closeRef.current = onClose;
  useEffect(() => {
    if (!open) return;
    const previous = document.activeElement as HTMLElement | null;
    const raf = requestAnimationFrame(() => {
      const el = ref.current;
      if (!el) return;
      const body = el.querySelector<HTMLElement>('.uncoder-ui-modal__body');
      const target = el.querySelector<HTMLElement>('[data-autofocus]') ?? body?.querySelector<HTMLElement>(FOCUSABLE) ?? el.querySelector<HTMLElement>('.uncoder-ui-modal__foot ' + 'button:not([disabled])') ?? el;
      target.focus();
    });
    return () => {
      cancelAnimationFrame(raf);
      if (previous && document.contains(previous)) previous.focus();
    };
  }, [open, ref]);

  return (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      // A popover inside the modal closes first.
      if (document.querySelector('.uncoder-ui-admin-root .uncoder-ui-pop')) return;
      e.stopPropagation();
      closeRef.current();
      return;
    }
    if (e.key !== 'Tab') return;
    const el = ref.current;
    if (!el) return;
    const items = Array.from(el.querySelectorAll<HTMLElement>(FOCUSABLE)).filter((n) => n.offsetParent !== null || n === document.activeElement);
    if (!items.length) return;
    const first = items[0];
    const last = items[items.length - 1];
    if (e.shiftKey && (document.activeElement === first || document.activeElement === el)) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  };
}

interface DialogProps {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  description?: ReactNode;
  children?: ReactNode;
  footer?: ReactNode;
  width?: number;
  /** Clicking the backdrop closes (off for forms with typed input). */
  dismissable?: boolean;
  className?: string;
}

export function Dialog({ open, onClose, title, description, children, footer, width = 520, dismissable = true, className }: DialogProps) {
  const ref = useRef<HTMLDivElement>(null);
  const id = useId();
  const onKeyDown = useModal(open, onClose, ref);
  if (!open) return null;
  return createPortal(
    <div
      className="uncoder-ui-modal-scrim"
      onPointerDown={(e) => {
        if (dismissable && e.target === e.currentTarget) onClose();
      }}
    >
      <div
        ref={ref}
        className={cx('uncoder-ui-modal', className)}
        role="dialog"
        aria-modal="true"
        aria-labelledby={id + '-t'}
        aria-describedby={description ? id + '-d' : undefined}
        tabIndex={-1}
        style={{ width }}
        onKeyDown={onKeyDown}
      >
        <header className="uncoder-ui-modal__head">
          <div className="uncoder-ui-modal__titles">
            <h2 className="uncoder-ui-modal__title" id={id + '-t'}>
              {title}
            </h2>
            {description && (
              <p className="uncoder-ui-modal__desc" id={id + '-d'}>
                {description}
              </p>
            )}
          </div>
          <IconButton icon="x" label="Close" onClick={onClose} />
        </header>
        {children !== undefined && <div className="uncoder-ui-modal__body">{children}</div>}
        {footer && <footer className="uncoder-ui-modal__foot">{footer}</footer>}
      </div>
    </div>,
    portalRoot(),
  );
}

interface DrawerProps {
  open: boolean;
  onClose: () => void;
  title: ReactNode;
  subtitle?: ReactNode;
  children: ReactNode;
  footer?: ReactNode;
  width?: number;
  actions?: ReactNode;
}

export function Drawer({ open, onClose, title, subtitle, children, footer, width = 480, actions }: DrawerProps) {
  const ref = useRef<HTMLDivElement>(null);
  const id = useId();
  const onKeyDown = useModal(open, onClose, ref);
  if (!open) return null;
  return createPortal(
    <div
      className="uncoder-ui-drawer-scrim"
      onPointerDown={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}
    >
      <aside ref={ref} className="uncoder-ui-drawer" role="dialog" aria-modal="true" aria-labelledby={id + '-t'} tabIndex={-1} style={{ width }} onKeyDown={onKeyDown}>
        <header className="uncoder-ui-drawer__head">
          <div className="uncoder-ui-modal__titles">
            <h2 className="uncoder-ui-modal__title" id={id + '-t'}>
              {title}
            </h2>
            {subtitle && <p className="uncoder-ui-modal__desc">{subtitle}</p>}
          </div>
          {actions}
          <IconButton icon="x" label="Close" onClick={onClose} />
        </header>
        <div className="uncoder-ui-drawer__body uncoder-ui-modal__body">{children}</div>
        {footer && <footer className="uncoder-ui-drawer__foot">{footer}</footer>}
      </aside>
    </div>,
    portalRoot(),
  );
}

/* ------------------------------------------------------------------ Confirm */

interface ConfirmOptions {
  title: string;
  body?: ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  danger?: boolean;
}

interface ConfirmState {
  request: (ConfirmOptions & { resolve: (ok: boolean) => void }) | null;
}

const useConfirmStore = create<ConfirmState>(() => ({ request: null }));

/** Promise-based confirmation dialog: `if (await confirmDialog({...})) …`. */
export function confirmDialog(options: ConfirmOptions): Promise<boolean> {
  return new Promise((resolve) => {
    useConfirmStore.getState().request?.resolve(false);
    useConfirmStore.setState({ request: { ...options, resolve } });
  });
}

export function ConfirmHost() {
  const request = useConfirmStore((s) => s.request);
  const close = (ok: boolean) => {
    request?.resolve(ok);
    useConfirmStore.setState({ request: null });
  };
  return (
    <Dialog
      open={!!request}
      onClose={() => close(false)}
      title={request?.title ?? ''}
      width={440}
      footer={
        <>
          {/* Destructive confirmations start on Cancel so Enter never deletes by accident. */}
          <Button variant="ghost" onClick={() => close(false)} data-autofocus={request?.danger ? true : undefined}>
            {request?.cancelLabel ?? 'Cancel'}
          </Button>
          <Button variant={request?.danger ? 'danger' : 'primary'} className={request?.danger ? 'uncoder-ui-btn--danger-solid' : undefined} onClick={() => close(true)} data-autofocus={request?.danger ? undefined : true}>
            {request?.confirmLabel ?? 'Confirm'}
          </Button>
        </>
      }
    >
      {request?.body ? <div className="uncoder-ui-confirm__body">{request.body}</div> : undefined}
    </Dialog>
  );
}
