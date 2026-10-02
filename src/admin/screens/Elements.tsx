import { useMemo, useState } from 'react';
import { Button, Toggle } from '@editor/ui/primitives';
import { api } from '../lib/api';
import { useResource } from '../lib/hooks';
import { Card, SkeletonRows } from '../ui/kit';

interface Usage {
  documents: number;
  usage: Record<string, { uses: number; pages: number }>;
}

type Filter = 'all' | 'used' | 'unused' | 'off';

/**
 * Settings → Elements: every widget with how often it is used, and a switch to take it out of the editor's
 * Insert panel. Turning a widget off never breaks a page that already uses it.
 */
export function ElementManager({
  widgets,
  categories,
  disabled,
  onChange,
}: {
  widgets: Array<{ name: string; title: string; category: string; icon: string }>;
  categories: Record<string, string>;
  disabled: string[];
  onChange: (next: string[]) => void;
}) {
  const usage = useResource((signal) => api<Usage>('elements/usage', { signal }), []);
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState<Filter>('all');
  const off = useMemo(() => new Set(disabled), [disabled]);
  const count = (name: string) => usage.data?.usage[name] ?? { uses: 0, pages: 0 };

  const groups = useMemo(() => {
    const q = query.trim().toLowerCase();
    const shown = widgets.filter((w) => {
      if (q && !w.title.toLowerCase().includes(q) && !w.name.includes(q)) return false;
      const uses = usage.data?.usage[w.name]?.uses ?? 0;
      if (filter === 'used') return uses > 0;
      if (filter === 'unused') return !!usage.data && uses === 0;
      if (filter === 'off') return off.has(w.name);
      return true;
    });
    const keys = [...Object.keys(categories), ...new Set(shown.map((w) => w.category).filter((c) => !(c in categories)))];
    return keys.map((cat) => ({ id: cat, label: categories[cat] ?? cat, items: shown.filter((w) => w.category === cat) })).filter((g) => g.items.length);
  }, [widgets, categories, query, filter, usage.data, off]);

  const unused = usage.data ? widgets.filter((w) => (usage.data!.usage[w.name]?.uses ?? 0) === 0 && !off.has(w.name)).map((w) => w.name) : [];
  const set = (name: string, on: boolean) => onChange(on ? disabled.filter((n) => n !== name) : [...disabled, name]);

  return (
    <Card
      id="elements"
      title="Element manager"
      description="Turn off widgets you don’t use to keep the editor’s Insert panel short. Pages that already use a widget keep showing it; it just can’t be added again. Counts cover every page, post and template built with Uncoder."
    >
      <div className="uncoder-ui-elmgr__bar">
        <input className="uncoder-ui-input" type="search" placeholder={`Search ${widgets.length} widgets`} value={query} onChange={(e) => setQuery(e.currentTarget.value)} aria-label="Search widgets" />
        <div className="uncoder-ui-chipset" role="radiogroup" aria-label="Show">
          {(
            [
              ['all', 'All'],
              ['used', 'Used'],
              ['unused', 'Unused'],
              ['off', `Off (${disabled.length})`],
            ] as Array<[Filter, string]>
          ).map(([value, label]) => (
            <button key={value} type="button" role="radio" aria-checked={filter === value} className={`uncoder-ui-togglechip${filter === value ? ' is-on' : ''}`} onClick={() => setFilter(value)}>
              {label}
            </button>
          ))}
        </div>
        {unused.length > 0 && (
          <Button size="sm" variant="ghost" icon="eye-off" onClick={() => onChange([...disabled, ...unused])}>
            Turn off {unused.length} unused
          </Button>
        )}
        {disabled.length > 0 && (
          <Button size="sm" variant="ghost" icon="eye" onClick={() => onChange([])}>
            Turn all on
          </Button>
        )}
      </div>
      {usage.loading && !usage.data ? (
        <SkeletonRows rows={6} cols={3} />
      ) : (
        <div className="uncoder-ui-elmgr">
          {usage.data && <p className="uncoder-ui-muted">Scanned {usage.data.documents} designs.</p>}
          {groups.map((g) => (
            <section key={g.id} className="uncoder-ui-elmgr__group">
              <h3>{g.label}</h3>
              <ul>
                {g.items.map((w) => {
                  const c = count(w.name);
                  const on = !off.has(w.name);
                  return (
                    <li key={w.name} className={on ? '' : 'is-off'}>
                      <span className="uncoder-ui-elmgr__name">
                        <strong>{w.title}</strong>
                        <code>{w.name}</code>
                      </span>
                      <span className="uncoder-ui-elmgr__uses" title={c.uses ? `${c.uses} uses on ${c.pages} designs` : 'Not used'}>
                        {usage.data ? (c.uses ? `${c.uses} × on ${c.pages}` : 'Unused') : '…'}
                      </span>
                      <Toggle checked={on} onChange={(v) => set(w.name, v)} label={`${w.title} available in the editor`} />
                    </li>
                  );
                })}
              </ul>
            </section>
          ))}
          {!groups.length && <p className="uncoder-ui-muted">No widget matches.</p>}
        </div>
      )}
    </Card>
  );
}
