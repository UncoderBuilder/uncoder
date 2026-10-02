import { useEffect, useMemo, useState } from 'react';
import type { ElementNode } from '@shared/types';
import { api } from '../lib/api';
import { STRUCTURES, structureTree } from '../canvas/CanvasApp';
import { insertElements, useDoc } from '../store/doc';
import { toast, useUi } from '../store/ui';
import { refreshLookup, useLookup } from '../controls/lookup';
import { revealAdded } from '../app/smart';
import { Icon } from '../ui/Icon';
import { PatternThumb, type PatternShape } from './PatternThumb';
import { config } from '../lib/config';
import { isTemplate } from '../lib/docInfo';

interface PatternItem {
  id: string;
  title: string;
  category: string;
  description: string;
  keywords: string[];
  shape: PatternShape[];
}

interface PatternCategory {
  id: string;
  label: string;
  count: number;
}

interface PatternList {
  patterns: PatternItem[];
  categories: PatternCategory[];
}

// The library is static for the editor session: fetch it once.
let patternCache: Promise<PatternList> | null = null;
const loadPatterns = (): Promise<PatternList> => {
  if (!patternCache) {
    patternCache = api<PatternList>('patterns').catch((e) => {
      patternCache = null;
      throw e;
    });
  }
  return patternCache;
};

function usePatterns(): { data: PatternList | null; error: string | null } {
  const [data, setData] = useState<PatternList | null>(null);
  const [error, setError] = useState<string | null>(null);
  useEffect(() => {
    let live = true;
    loadPatterns()
      .then((res) => live && setData(res))
      .catch((e: any) => live && setError(e?.message ?? 'Could not load patterns.'));
    return () => {
      live = false;
    };
  }, []);
  return { data, error };
}

/** Pattern category that fits the open template: a header starts on Headers, a footer on Footers… */
const TEMPLATE_CATEGORY: Record<string, string> = {
  header: 'header',
  footer: 'footer',
  popup: 'cta',
  archive: 'blog',
  'search-results': 'blog',
};
const suggestedCategory = (): string => (isTemplate ? (TEMPLATE_CATEGORY[config.post.docType] ?? '') : '');

/** Index right after the top-level section that contains the selection, else the end of the page. */
function insertionIndex(): number {
  const { doc } = useDoc.getState();
  let id: string | null = useUi.getState().selected[0] ?? null;
  while (id && doc.nodes[id]?.parent) id = doc.nodes[id].parent;
  const at = id ? doc.root.indexOf(id) : -1;
  return at >= 0 ? at + 1 : doc.root.length;
}

export function LibraryPanel() {
  const [q, setQ] = useState('');
  const sections = useLookup('templates', q, '&type=section');
  const [busy, setBusy] = useState<string | null>(null);
  const [pq, setPq] = useState('');
  // null until a chip is clicked: the category that fits the document, when the library has it.
  const [picked, setCat] = useState<string | null>(null);
  const { data: patterns, error: patternError } = usePatterns();
  const suggested = suggestedCategory();
  const cat = picked ?? (patterns?.categories.some((c) => c.id === suggested) ? suggested : '');

  // Layouts select their first empty column (and open Insert); content sections select themselves. Both scroll into view.
  const insert = (nodes: ElementNode[], label: string, index?: number, fill = false) => {
    const doc = useDoc.getState().doc;
    const ids = insertElements(null, index ?? doc.root.length, nodes, label);
    revealAdded(ids, { fill });
    return ids;
  };

  const trashSection = async (id: string, label: string) => {
    setBusy(id);
    try {
      await api(`templates/${id}`, { method: 'DELETE' });
      refreshLookup('templates');
      toast(`Moved “${label}” to the trash`, 'info', {
        label: 'Undo',
        run: async () => {
          await api(`templates/${id}/restore`, { method: 'POST', body: {} });
          refreshLookup('templates');
        },
      });
    } catch (e: any) {
      toast(`Could not delete: ${e.message}`, 'error');
    } finally {
      setBusy(null);
    }
  };

  const visible = useMemo(() => {
    if (!patterns) return [];
    const words = pq.toLowerCase().split(/\s+/).filter(Boolean);
    return patterns.patterns.filter((p) => {
      if (cat && p.category !== cat) return false;
      if (!words.length) return true;
      const hay = [p.title, p.description, p.category, ...p.keywords].join(' ').toLowerCase();
      return words.every((w) => hay.includes(w));
    });
  }, [patterns, pq, cat]);

  const insertPattern = async (p: PatternItem) => {
    setBusy(p.id);
    try {
      const res = await api<{ elements: ElementNode[] }>(`patterns/${encodeURIComponent(p.id)}`);
      insert(res.elements, `Insert “${p.title}”`, insertionIndex());
    } catch (e: any) {
      toast(`Could not insert: ${e.message}`, 'error');
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="uncoder-ui-library">
      <div className="uncoder-ui-kit__label">Layouts</div>
      <div className="uncoder-ui-library__structs">
        {STRUCTURES.map((s) => (
          <button key={s.id} type="button" className="uncoder-ui-addsec__struct" title={s.label} aria-label={s.label} onClick={() => insert([structureTree(s.cols)], 'Add section', insertionIndex(), true)}>
            {s.cols.map((c, i) => (
              <span key={i} style={{ flexGrow: c }} />
            ))}
          </button>
        ))}
      </div>

      <div className="uncoder-ui-kit__label">Patterns</div>
      <label className="uncoder-ui-search">
        <Icon name="search" size={14} />
        <input type="search" placeholder="Search patterns" value={pq} onChange={(e) => setPq(e.currentTarget.value)} aria-label="Search patterns" />
      </label>
      {patterns && (
        <div className="uncoder-ui-chips uncoder-ui-library__cats" role="group" aria-label="Pattern categories">
          <button type="button" className="uncoder-ui-chip" aria-pressed={cat === ''} onClick={() => setCat('')}>
            All
          </button>
          {patterns.categories.map((c) => (
            <button key={c.id} type="button" className="uncoder-ui-chip" aria-pressed={cat === c.id} onClick={() => setCat(cat === c.id ? '' : c.id)}>
              {c.label}
            </button>
          ))}
        </div>
      )}
      <div className="uncoder-ui-library__patterns">
        {visible.map((p) => (
          <button
            key={p.id}
            type="button"
            className="uncoder-ui-pattern"
            title={p.description}
            aria-label={`Insert pattern: ${p.title}`}
            aria-busy={busy === p.id}
            disabled={busy === p.id}
            onClick={() => insertPattern(p)}
          >
            <span className="uncoder-ui-pattern__thumb">
              <PatternThumb shape={p.shape} />
              <span className="uncoder-ui-pattern__add" aria-hidden="true">
                <Icon name={busy === p.id ? 'loader' : 'plus'} size={13} />
              </span>
            </span>
            <span className="uncoder-ui-pattern__title">{p.title}</span>
          </button>
        ))}
      </div>
      {!patterns && !patternError && <p className="uncoder-ui-note">Loading patterns…</p>}
      {patternError && <p className="uncoder-ui-note">Could not load patterns: {patternError}</p>}
      {patterns && !visible.length && <p className="uncoder-ui-note">No pattern matches “{pq}”.</p>}
      {patterns && <p className="uncoder-ui-note">Inserted after the selected section, or at the end of the page.</p>}

      <div className="uncoder-ui-kit__label">Saved sections</div>
      <label className="uncoder-ui-search">
        <Icon name="search" size={14} />
        <input type="search" placeholder="Search saved sections" value={q} onChange={(e) => setQ(e.currentTarget.value)} aria-label="Search saved sections" />
      </label>
      <div className="uncoder-ui-library__list">
        {(sections ?? []).map((s) => (
          <div key={s.value} className="uncoder-ui-library__item" aria-busy={busy === s.value}>
            <Icon name="layout-template" size={14} />
            <span className="uncoder-ui-library__name">{s.label}</span>
            <button
              type="button"
              className="uncoder-ui-library__act"
              aria-label={`Insert “${s.label}”`}
              data-tip="Insert"
              disabled={busy === s.value}
              onClick={async () => {
                setBusy(s.value);
                try {
                  const doc = await api<{ elements: ElementNode[] }>(`documents/${s.value}`);
                  insert(doc.elements, `Insert “${s.label}”`, insertionIndex());
                } catch (e: any) {
                  toast(`Could not insert: ${e.message}`, 'error');
                } finally {
                  setBusy(null);
                }
              }}
            >
              <Icon name={busy === s.value ? 'loader' : 'plus'} size={14} />
            </button>
            <button type="button" className="uncoder-ui-library__act is-danger" aria-label={`Move “${s.label}” to the trash`} data-tip="Move to trash" disabled={busy === s.value} onClick={() => trashSection(s.value, s.label)}>
              <Icon name="trash-2" size={14} />
            </button>
          </div>
        ))}
        {sections && !sections.length && <p className="uncoder-ui-note">No saved sections yet. Right-click any element and choose “Save as template”, or ask your AI client to create one.</p>}
      </div>
    </div>
  );
}
