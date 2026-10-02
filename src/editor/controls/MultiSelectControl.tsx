import { useEffect, useState } from 'react';
import { Icon } from '../ui/Icon';
import { lookupLabels, useLookup, type LookupItem } from './lookup';
import type { ControlProps } from './ControlRow';

export function MultiSelectControl({ control, value, onChange }: ControlProps<string[]>) {
  const selected = value ?? [];
  const [q, setQ] = useState('');
  const [labels, setLabels] = useState<Record<string, string>>({});
  const source = control.source ?? null;
  const remote = useLookup(source && q.length > 0 ? source : null, q);
  const staticOpts: LookupItem[] = Object.entries(control.options ?? {}).map(([v, l]) => ({ value: v, label: typeof l === 'string' ? l : l.label }));

  useEffect(() => {
    if (!source) return;
    const missing = selected.filter((v) => !(v in labels));
    if (!missing.length || source === 'terms') return;
    lookupLabels(source, missing).then((items) => setLabels((l) => ({ ...l, ...Object.fromEntries(items.map((i) => [i.value, i.label])) })));
  }, [selected.join(','), source]);

  const options = (source ? remote ?? [] : staticOpts.filter((o) => !q || o.label.toLowerCase().includes(q.toLowerCase()))).filter((o) => !selected.includes(o.value));
  const label = (v: string) => labels[v] ?? staticOpts.find((o) => o.value === v)?.label ?? v;

  return (
    <div className="uncoder-ui-multi">
      {selected.length > 0 && (
        <div className="uncoder-ui-multi__tokens">
          {selected.map((v) => (
            <span key={v} className="uncoder-ui-token">
              {label(v)}
              <button type="button" aria-label={`Remove ${label(v)}`} onClick={() => onChange(selected.filter((x) => x !== v))}>
                <Icon name="x" size={11} />
              </button>
            </span>
          ))}
        </div>
      )}
      <input className="uncoder-ui-input" placeholder={source ? 'Search…' : 'Filter…'} value={q} onChange={(e) => setQ(e.currentTarget.value)} aria-label={control.label} />
      {(q || !source) && options.length > 0 && (
        <div className="uncoder-ui-multi__options">
          {options.slice(0, 12).map((o) => (
            <button
              key={o.value}
              type="button"
              className="uncoder-ui-multi__option"
              onClick={() => {
                setLabels((l) => ({ ...l, [o.value]: o.label }));
                onChange([...selected, o.value]);
                setQ('');
              }}
            >
              {o.label}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
