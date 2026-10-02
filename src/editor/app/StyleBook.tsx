import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { config } from '../lib/config';
import { useKit } from '../store/kit';
import { useUi } from '../store/ui';
import { Icon } from '../ui/Icon';
import { Button } from '../ui/primitives';

/**
 * Style book: every widget, the colors, text styles and buttons on one page (Site\Style_Book), in the site's
 * theme. The Design System being edited is posted along, so unsaved changes show before they are saved.
 */
export function StyleBook() {
  const open = useUi((s) => s.styleBook);
  const kit = useKit((s) => s.kit);
  const dirty = useKit((s) => s.dirty);
  const form = useRef<HTMLFormElement>(null);
  const input = useRef<HTMLInputElement>(null);
  const url = config.urls.styleBook;

  // (Re)load with the current Design System; quick successive edits send one request.
  useEffect(() => {
    if (!open || !form.current || !input.current) return;
    const timer = window.setTimeout(() => {
      const { breakpoints: _bp, ...payload } = kit as any;
      input.current!.value = JSON.stringify(payload);
      form.current!.submit();
    }, 250);
    return () => window.clearTimeout(timer);
  }, [open, kit]);

  if (!open || !url) return null;
  const close = () => useUi.setState({ styleBook: false });

  return createPortal(
    <div className="uncoder-ui-scrim uncoder-ui-sbook" onPointerDown={(e) => e.target === e.currentTarget && close()}>
      <div className="uncoder-ui-sbook__panel" role="dialog" aria-modal="true" aria-labelledby="uncoder-ui-sbook-title" onKeyDown={(e) => e.key === 'Escape' && close()}>
        <div className="uncoder-ui-sbook__head">
          <Icon name="book-open" size={16} />
          <h2 id="uncoder-ui-sbook-title">Style book</h2>
          <span className="uncoder-ui-sbook__note">{dirty ? 'Showing your unsaved Design System changes' : 'Every widget with the Design System'}</span>
          <Button size="sm" variant="ghost" icon="external-link" onClick={() => window.open(url, '_blank', 'noopener')}>
            Open in new tab
          </Button>
          <Button size="sm" icon="x" onClick={close} autoFocus>
            Close
          </Button>
        </div>
        <iframe className="uncoder-ui-sbook__frame" name="uncoder-ui-sbook-frame" title="Style book" />
        <form ref={form} method="post" action={url} target="uncoder-ui-sbook-frame" hidden>
          <input ref={input} type="hidden" name="kit" />
          <input type="hidden" name="_wpnonce" value={config.styleBookNonce ?? ''} />
        </form>
      </div>
    </div>,
    document.querySelector('.uncoder-ui-portal') ?? document.body,
  );
}
