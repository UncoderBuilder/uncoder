import { dismissToast, useUi } from '../store/ui';
import { Icon } from '../ui/Icon';

const ICON = { info: 'info', success: 'circle-check', error: 'circle-alert', warning: 'triangle-alert' } as const;

export function Toasts() {
  const toasts = useUi((s) => s.toasts);
  return (
    <div className="uncoder-ui-toasts" role="status" aria-live="polite">
      {toasts.map((t) => (
        <div key={t.id} className={`uncoder-ui-toast uncoder-ui-toast--${t.kind}`}>
          <Icon name={ICON[t.kind]} size={15} />
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
          <button type="button" className="uncoder-ui-toast__close" aria-label="Dismiss" onClick={() => dismissToast(t.id)}>
            <Icon name="x" size={13} />
          </button>
        </div>
      ))}
    </div>
  );
}
