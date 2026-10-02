import { useEffect, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button } from '@editor/ui/primitives';
import { cfg } from '../lib/config';
import { templatesApi, type Template, type TemplatesMeta } from '../lib/api';
import { cx } from '../lib/format';
import { Dialog } from '../ui/Dialog';
import { Callout } from '../ui/kit';
import { describeConditions, TYPE_INFO } from './common';

interface Props {
  open: boolean;
  onClose: () => void;
  meta: TemplatesMeta | null;
  /** Types offered in the picker (one type = no picker). */
  types: string[];
  initialType?: string;
  /** Called when the template exists; by default the editor opens. */
  onCreated?: (t: Template) => void;
}

const PLACEHOLDER: Record<string, string> = {
  header: 'Main header',
  footer: 'Main footer',
  'single-post': 'Blog post',
  'single-page': 'Standard page',
  single: 'Case study',
  archive: 'Blog archive',
  'search-results': 'Search results',
  'error-404': 'Not found',
  'loop-item': 'Post card',
  'mega-menu': 'Products menu',
  section: 'Call to action',
  popup: 'Newsletter signup',
};

export function NewTemplateDialog({ open, onClose, meta, types, initialType, onCreated }: Props) {
  const [type, setType] = useState(initialType ?? types[0]);
  const [title, setTitle] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const single = types.length === 1;

  useEffect(() => {
    if (!open) return;
    setType(initialType && types.includes(initialType) ? initialType : types[0]);
    setTitle('');
    setError(null);
    setBusy(false);
  }, [open, initialType, types]);

  const label = meta?.types[type] ?? cfg.templateTypes[type] ?? type;
  const conditional = meta?.conditional.includes(type) ?? false;
  const defaults = meta?.defaults[type] ?? [];

  const submit = async (e?: React.FormEvent) => {
    e?.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const t = await templatesApi.create({ type, title: title.trim() || `${label}` });
      if (onCreated) {
        onCreated(t);
        onClose();
      } else {
        window.location.href = t.editUrl;
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not create the template.');
      setBusy(false);
    }
  };

  return (
    <Dialog
      open={open}
      onClose={onClose}
      dismissable={!title}
      width={single ? 480 : 720}
      title={single ? `New ${label.toLowerCase()}` : 'New template'}
      description={single ? TYPE_INFO[type]?.description : 'Pick what you want to design. It opens in the builder as a draft.'}
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button variant="primary" iconRight="arrow-right" onClick={() => submit()} loading={busy}>
            Create and open editor
          </Button>
        </>
      }
    >
      <form className="uncoder-ui-form-stack" onSubmit={submit}>
        {error && <Callout tone="danger">{error}</Callout>}
        {!single && (
          <fieldset className="uncoder-ui-typegrid">
            <legend className="uncoder-ui-fld__label">Type</legend>
            {types.map((t) => {
              const info = TYPE_INFO[t];
              const checked = t === type;
              return (
                <label key={t} className={cx('uncoder-ui-typecard', checked && 'is-checked')}>
                  <input type="radio" name="uncoder-ui-new-type" value={t} checked={checked} onChange={() => setType(t)} className="uncoder-ui-sr-only" data-autofocus={checked ? true : undefined} />
                  <span className="uncoder-ui-typecard__icon">
                    <Icon name={info?.icon ?? 'file'} size={16} />
                  </span>
                  <span className="uncoder-ui-typecard__text">
                    <span className="uncoder-ui-typecard__label">{meta?.types[t] ?? cfg.templateTypes[t] ?? t}</span>
                    <span className="uncoder-ui-typecard__desc">{info?.description}</span>
                  </span>
                </label>
              );
            })}
          </fieldset>
        )}
        <div className="uncoder-ui-fld">
          <label className="uncoder-ui-fld__label" htmlFor="uncoder-ui-new-title">
            Name
          </label>
          <input
            id="uncoder-ui-new-title"
            className="uncoder-ui-input"
            value={title}
            placeholder={PLACEHOLDER[type] ?? label}
            onChange={(e) => setTitle(e.currentTarget.value)}
            maxLength={120}
            data-autofocus={single ? true : undefined}
            autoComplete="off"
          />
        </div>
        {conditional && (
          <div className="uncoder-ui-newtpl__where">
            <Icon name="map-pin" size={14} />
            <span>
              <strong>Appears on:</strong> {defaults.length ? describeConditions(defaults, meta) : 'nowhere yet — set display conditions after creating it'}
              <span className="uncoder-ui-muted"> · editable any time</span>
            </span>
          </div>
        )}
        <button type="submit" hidden aria-hidden tabIndex={-1} />
      </form>
    </Dialog>
  );
}
