import { useEffect, useId, useRef, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Spinner } from '@editor/ui/primitives';
import { lookup, type LookupItem } from '../lib/api';
import { useDebounced } from '../lib/hooks';
import { cx } from '../lib/format';

export interface Picked {
  id: number;
  label: string;
}

interface Props {
  /** lookup source: posts | terms | users. */
  source: 'posts' | 'terms' | 'users';
  /** Post type for posts, taxonomy for terms. */
  filter?: string;
  value: Picked[];
  onChange: (v: Picked[]) => void;
  placeholder?: string;
  label: string;
  disabled?: boolean;
}

/** Converts a lookup result to an id (terms come back as "taxonomy:id"). */
function toPicked(item: LookupItem, source: Props['source'], filter?: string): Picked | null {
  if (source === 'terms') {
    const [tax, id] = item.value.split(':');
    if (filter && tax !== filter) return null;
    const n = Number(id);
    return n ? { id: n, label: item.label.replace(/\s\([^)]*\)$/, '') } : null;
  }
  const n = Number(item.value);
  return n ? { id: n, label: item.label } : null;
}

/** Async multi-select combobox (chips + search) backed by /lookup/{source}. */
export function LookupSelect({ source, filter, value, onChange, placeholder = 'Search…', label, disabled }: Props) {
  const [query, setQuery] = useState('');
  const [open, setOpen] = useState(false);
  const [items, setItems] = useState<Picked[]>([]);
  const [loading, setLoading] = useState(false);
  const [active, setActive] = useState(0);
  const debounced = useDebounced(query, 220);
  const inputRef = useRef<HTMLInputElement>(null);
  const wrapRef = useRef<HTMLDivElement>(null);
  const id = useId();

  useEffect(() => {
    if (!open) return;
    const ctrl = new AbortController();
    setLoading(true);
    const params: Record<string, string> = { search: debounced };
    if (source === 'posts' && filter) params.type = filter;
    lookup(source, params, ctrl.signal)
      .then((res) => {
        const list = res.map((r) => toPicked(r, source, filter)).filter((p): p is Picked => !!p);
        setItems(list);
        setActive(0);
      })
      .catch(() => {
        if (!ctrl.signal.aborted) setItems([]);
      })
      .finally(() => {
        if (!ctrl.signal.aborted) setLoading(false);
      });
    return () => ctrl.abort();
  }, [debounced, open, source, filter]);

  useEffect(() => {
    if (!open) return;
    const onDown = (e: PointerEvent) => {
      if (!wrapRef.current?.contains(e.target as Node)) setOpen(false);
    };
    window.addEventListener('pointerdown', onDown, true);
    return () => window.removeEventListener('pointerdown', onDown, true);
  }, [open]);

  const selected = new Set(value.map((v) => v.id));
  const options = items.filter((i) => !selected.has(i.id));

  const pick = (p: Picked) => {
    onChange([...value, p]);
    setQuery('');
    inputRef.current?.focus();
  };

  const onKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setOpen(true);
      setActive((a) => Math.min(a + 1, Math.max(0, options.length - 1)));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActive((a) => Math.max(0, a - 1));
    } else if (e.key === 'Enter') {
      if (open && options[active]) {
        e.preventDefault();
        pick(options[active]);
      }
    } else if (e.key === 'Escape') {
      if (open) {
        e.stopPropagation();
        setOpen(false);
      }
    } else if (e.key === 'Backspace' && query === '' && value.length) {
      onChange(value.slice(0, -1));
    }
  };

  const listId = id + '-list';
  return (
    <div ref={wrapRef} className={cx('uncoder-ui-lookup', disabled && 'is-disabled', open && 'is-open')}>
      <div className="uncoder-ui-lookup__field" onClick={() => inputRef.current?.focus()}>
        {value.map((v) => (
          <span className="uncoder-ui-chip" key={v.id}>
            <span className="uncoder-ui-chip__label" title={v.label}>
              {v.label}
            </span>
            <button
              type="button"
              className="uncoder-ui-chip__x"
              aria-label={`Remove ${v.label}`}
              onClick={(e) => {
                e.stopPropagation();
                onChange(value.filter((x) => x.id !== v.id));
              }}
            >
              <Icon name="x" size={11} />
            </button>
          </span>
        ))}
        <input
          ref={inputRef}
          className="uncoder-ui-lookup__input"
          value={query}
          disabled={disabled}
          placeholder={value.length ? '' : placeholder}
          role="combobox"
          aria-label={label}
          aria-expanded={open}
          aria-controls={listId}
          aria-autocomplete="list"
          aria-activedescendant={open && options[active] ? `${id}-o${options[active].id}` : undefined}
          onFocus={() => setOpen(true)}
          onChange={(e) => {
            setQuery(e.currentTarget.value);
            setOpen(true);
          }}
          onKeyDown={onKeyDown}
        />
        {loading && open && <Spinner size={12} />}
      </div>
      {open && (
        <ul className="uncoder-ui-lookup__list" role="listbox" id={listId} aria-label={label}>
          {options.map((o, i) => (
            <li
              key={o.id}
              id={`${id}-o${o.id}`}
              role="option"
              aria-selected={i === active}
              className={cx('uncoder-ui-lookup__opt', i === active && 'is-active')}
              onPointerDown={(e) => {
                e.preventDefault();
                pick(o);
              }}
              onPointerEnter={() => setActive(i)}
            >
              <span className="uncoder-ui-lookup__optlabel">{o.label}</span>
              <span className="uncoder-ui-lookup__optid">#{o.id}</span>
            </li>
          ))}
          {!loading && options.length === 0 && <li className="uncoder-ui-lookup__none">{query ? 'No matches' : 'Type to search'}</li>}
        </ul>
      )}
    </div>
  );
}
