import { useEffect, useState } from 'react';
import type { ElementNode } from '@shared/types';
import { api } from '../lib/api';
import { config } from '../lib/config';
import { fromTree } from '../lib/tree';
import { commit, redo, undo, useDoc } from '../store/doc';
import { select, toast, useUi } from '../store/ui';
import { doUndo } from '../app/actions';
import { Icon } from '../ui/Icon';

function time(ts: number) {
  return new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

/** "Today 14:05", "Yesterday 09:12", "3 Sep 18:40". */
function when(unix: number) {
  const d = new Date(unix * 1000);
  const t = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  const days = Math.round((new Date().setHours(0, 0, 0, 0) - new Date(d).setHours(0, 0, 0, 0)) / 86400000);
  if (days === 0) return `Today ${t}`;
  if (days === 1) return `Yesterday ${t}`;
  return `${d.toLocaleDateString([], { day: 'numeric', month: 'short', year: d.getFullYear() === new Date().getFullYear() ? undefined : 'numeric' })} ${t}`;
}

interface Revision {
  id: number;
  date: number;
  author: string;
  isAi: boolean;
  count: number;
}

/** History: this session's undo steps, then the versions saved to WordPress (restore or preview any). */
export function HistoryPanel() {
  const past = useDoc((s) => s.past);
  const future = useDoc((s) => s.future);
  return (
    <div className="uncoder-ui-history" role="list">
      <h3 className="uncoder-ui-history__head">This session</h3>
      {!past.length && !future.length && <p className="uncoder-ui-history__note">No changes yet. Every edit appears here and can be undone.</p>}
      {future
        .slice()
        .reverse()
        .map((e, i) => (
          <button
            key={`f${i}`}
            type="button"
            role="listitem"
            className="uncoder-ui-history__item is-future"
            onClick={() => {
              const steps = future.length - i;
              for (let n = 0; n < steps; n++) redo();
            }}
          >
            <Icon name="redo-2" size={13} />
            <span>{e.label}</span>
            <span className="uncoder-ui-mono">{time(e.time)}</span>
          </button>
        ))}
      {past
        .slice()
        .reverse()
        .map((e, i) => (
          <button
            key={`p${i}`}
            type="button"
            role="listitem"
            className={`uncoder-ui-history__item${i === 0 ? ' is-current' : ''}`}
            onClick={() => {
              for (let n = 0; n < i; n++) undo();
            }}
          >
            <Icon name={i === 0 ? 'circle-dot' : 'circle'} size={13} />
            <span>{e.label}</span>
            <span className="uncoder-ui-mono">{time(e.time)}</span>
          </button>
        ))}
      <div className="uncoder-ui-history__item is-origin">
        <Icon name="flag" size={13} />
        <span>Opened editor</span>
      </div>
      <SavedVersions />
    </div>
  );
}

function SavedVersions() {
  const lastSaved = useUi((s) => s.lastSaved);
  const [list, setList] = useState<Revision[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<number | null>(null);

  // Reload after every save: each save adds a version.
  useEffect(() => {
    let live = true;
    api<Revision[]>(`documents/${config.post.id}/revisions`)
      .then((r) => live && (setList(r), setError(null)))
      .catch((e: any) => live && setError(e?.message ?? 'Could not load saved versions.'));
    return () => {
      live = false;
    };
  }, [lastSaved]);

  const restore = async (rev: Revision) => {
    setBusy(rev.id);
    try {
      const res = await api<{ elements: ElementNode[] }>(`documents/${config.post.id}/revisions/${rev.id}`);
      const next = fromTree(res.elements ?? []);
      commit(`Restore version from ${when(rev.date)}`, (d) => {
        d.nodes = next.nodes;
        d.root = next.root;
      });
      select(null);
      toast(`Restored the version from ${when(rev.date)}. Save to keep it.`, 'success', { label: 'Undo', run: doUndo }, 6000);
    } catch (e: any) {
      toast(`Could not restore: ${e?.message ?? 'unknown error'}`, 'error');
    } finally {
      setBusy(null);
    }
  };

  const preview = (rev: Revision) => {
    const base = config.post.permalink + (config.post.permalink.includes('?') ? '&' : '?');
    window.open(`${base}preview=true&uncoder_draft=${encodeURIComponent(config.post.draftNonce)}&uncoder_rev=${rev.id}`, '_blank', 'noopener');
  };

  return (
    <>
      <h3 className="uncoder-ui-history__head">Saved versions</h3>
      {error && <p className="uncoder-ui-history__note">{error}</p>}
      {!list && !error && <p className="uncoder-ui-history__note">Loading…</p>}
      {list && !list.length && <p className="uncoder-ui-history__note">No saved versions yet. Each time you save, WordPress keeps a version you can come back to.</p>}
      {list?.map((rev, i) => (
        <div key={rev.id} className="uncoder-ui-history__rev" role="listitem">
          <Icon name={i === 0 ? 'circle-check' : 'history'} size={13} />
          <span className="uncoder-ui-history__rev-text">
            <strong>{when(rev.date)}</strong>
            <span>
              {rev.author || 'Unknown'}
              {rev.count ? ` · ${rev.count} element${rev.count === 1 ? '' : 's'}` : ''}
              {i === 0 ? ' · latest' : ''}
            </span>
          </span>
          {rev.isAi && <span className="uncoder-ui-history__ai">AI</span>}
          <button type="button" className="uncoder-ui-history__act" aria-label={`Preview the version from ${when(rev.date)} in a new tab`} data-tip="Preview in a new tab" onClick={() => preview(rev)}>
            <Icon name="external-link" size={13} />
          </button>
          <button type="button" className="uncoder-ui-history__act" aria-label={`Restore the version from ${when(rev.date)}`} data-tip="Restore (undoable)" disabled={busy === rev.id} onClick={() => restore(rev)}>
            <Icon name={busy === rev.id ? 'loader' : 'rotate-ccw'} size={13} />
          </button>
        </div>
      ))}
    </>
  );
}
