import { useMemo, useState } from 'react';
import { schemaOf } from '../lib/config';
import { replaceInSettings, type FindHit, type FindOptions, type Scope } from '../lib/findReplace';
import { commit, useDoc } from '../store/doc';
import { select, toast } from '../store/ui';
import { elementFor } from '../canvas/frame';
import { Icon } from '../ui/Icon';
import { Button, Segmented, Toggle } from '../ui/primitives';
import { MOD } from '../app/shortcuts';

interface Result {
  id: string;
  title: string;
  hits: FindHit[];
}

/** Find & replace in this page: texts, links or colors, as one undo step. */
export function FindPanel() {
  const nodes = useDoc((s) => s.doc.nodes);
  const [find, setFind] = useState('');
  const [replace, setReplace] = useState('');
  const [scope, setScope] = useState<Scope>('text');
  const [matchCase, setMatchCase] = useState(false);

  const opts: FindOptions = { find, replace, scope, matchCase };
  const results = useMemo<Result[]>(() => {
    if (!find) return [];
    const out: Result[] = [];
    for (const node of Object.values(nodes)) {
      const schema = schemaOf(node.type);
      if (!schema) continue;
      const hits: FindHit[] = [];
      const title = node.label || schema.title;
      replaceInSettings(node.settings, schema.controls, opts, hits, title);
      if (hits.length) out.push({ id: node.id, title, hits });
    }
    return out;
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [nodes, find, scope, matchCase]);
  const total = results.reduce((n, r) => n + r.hits.reduce((m, h) => m + h.count, 0), 0);

  const replaceAll = () => {
    commit(`Replace “${find}”`, (d) => {
      for (const r of results) {
        const node = d.nodes[r.id];
        const schema = node && schemaOf(node.type);
        if (!node || !schema) continue;
        node.settings = replaceInSettings(node.settings, schema.controls, opts, [], '');
      }
    });
    toast(`Replaced ${total} match${total === 1 ? '' : 'es'} — undo with ${MOD}Z`, 'success');
  };

  return (
    <div className="uncoder-ui-find">
      <label className="uncoder-ui-field">
        <span className="uncoder-ui-field__label">Find</span>
        <input className="uncoder-ui-input" autoFocus value={find} onChange={(e) => setFind(e.currentTarget.value)} placeholder={scope === 'colors' ? '#1f4d3a' : scope === 'links' ? 'https://old-domain.com' : 'Text…'} />
      </label>
      <label className="uncoder-ui-field">
        <span className="uncoder-ui-field__label">Replace with</span>
        <input className="uncoder-ui-input" value={replace} onChange={(e) => setReplace(e.currentTarget.value)} />
      </label>
      <Segmented
        size="sm"
        value={scope}
        onChange={(v) => setScope(v as Scope)}
        ariaLabel="Search in"
        options={[
          { value: 'text', label: 'Text' },
          { value: 'links', label: 'Links' },
          { value: 'colors', label: 'Colors' },
          { value: 'all', label: 'All' },
        ]}
      />
      <div className="uncoder-ui-find__row">
        <Toggle checked={matchCase} onChange={setMatchCase} label="Match case" />
        <span>Match case</span>
        <span className="uncoder-ui-find__count">{find ? `${total} match${total === 1 ? '' : 'es'}` : ''}</span>
      </div>
      <Button variant="primary" icon="replace-all" onClick={replaceAll} disabled={!total}>
        Replace all
      </Button>
      <ul className="uncoder-ui-find__results">
        {results.map((r) => (
          <li key={r.id}>
            <button
              type="button"
              onClick={() => {
                select(r.id);
                elementFor(r.id)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
              }}
            >
              <Icon name={schemaOf(nodes[r.id]?.type)?.icon ?? 'box'} size={12} />
              <span className="uncoder-ui-find__where">{r.hits[0].where}</span>
              {r.hits.map((h, i) => (
                <span key={i} className="uncoder-ui-find__text">
                  {h.text}
                </span>
              ))}
            </button>
          </li>
        ))}
      </ul>
      <p className="uncoder-ui-note">Searches this page. To replace across the whole site, use Uncoder → Settings → Tools.</p>
    </div>
  );
}
