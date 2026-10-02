import { useRef, useState } from 'react';
import type { LinkValue } from '@shared/types';
import { Icon } from '../ui/Icon';
import { Popover } from '../ui/Popover';
import { IconButton, Toggle } from '../ui/primitives';
import { TextInput } from '../ui/inputs';
import { useLookup } from './lookup';
import type { ControlProps } from './ControlRow';

export function UrlControl({ control, value, placeholder, onChange }: ControlProps<LinkValue>) {
  const v: LinkValue = value ?? (placeholder as LinkValue) ?? { url: '' };
  const [search, setSearch] = useState('');
  const [suggest, setSuggest] = useState(false);
  const opts = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const results = useLookup(suggest && search.length > 1 ? 'posts' : null, search);

  const set = (patch: Partial<LinkValue>) => {
    const next = { ...v, ...patch };
    onChange(next.url || next.external || next.nofollow || next.attributes ? next : undefined);
  };

  return (
    <div className="uncoder-ui-url">
      <div className="uncoder-ui-url__row">
        <TextInput
          className="uncoder-ui-input--mono"
          value={value?.url ?? ''}
          placeholder={(placeholder as LinkValue)?.url || 'https://, /page, #anchor or search'}
          onChange={(t) => {
            setSearch(t);
            setSuggest(!/^(https?:|\/|#|mailto:|tel:)/.test(t));
          }}
          onCommit={(url) => {
            if (!suggest || !results?.length) set({ url: url.trim() });
          }}
          aria-label={control.label}
        />
        <IconButton ref={opts} icon="settings-2" label="Link options" size={13} active={!!(v.external || v.nofollow || v.attributes)} onClick={() => setOpen((o) => !o)} />
      </div>
      {suggest && results && results.length > 0 && (
        <div className="uncoder-ui-url__suggest" role="listbox">
          {results.slice(0, 6).map((r: any) => (
            <button
              key={r.value}
              type="button"
              role="option"
              className="uncoder-ui-url__option"
              onClick={() => {
                setSuggest(false);
                set({ url: r.url || `/?p=${r.value}` });
              }}
            >
              <Icon name="file-text" size={12} /> {r.label}
            </button>
          ))}
        </div>
      )}
      <Popover anchor={opts} open={open} onClose={() => setOpen(false)} width={260} placement="bottom-end" label="Link options">
        <div className="uncoder-ui-form uncoder-ui-url__opts">
          <label className="uncoder-ui-ctl">
            <span className="uncoder-ui-ctl__label">Open in new tab</span>
            <Toggle checked={!!v.external} onChange={(b) => set({ external: b || undefined })} />
          </label>
          <label className="uncoder-ui-ctl">
            <span className="uncoder-ui-ctl__label">Add nofollow</span>
            <Toggle checked={!!v.nofollow} onChange={(b) => set({ nofollow: b || undefined })} />
          </label>
          <label className="uncoder-ui-ctl uncoder-ui-ctl--stacked">
            <span className="uncoder-ui-ctl__label">Custom attributes</span>
            <textarea className="uncoder-ui-textarea uncoder-ui-textarea--code" rows={2} placeholder="key|value" defaultValue={v.attributes ?? ''} onBlur={(e) => set({ attributes: e.currentTarget.value || undefined })} />
          </label>
        </div>
      </Popover>
    </div>
  );
}
