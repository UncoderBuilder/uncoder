import { useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button, Toggle } from '@editor/ui/primitives';
import { api } from '../lib/api';
import { useResource } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { Dialog } from './Dialog';
import { Callout, ErrorState, SkeletonRows } from './kit';

interface Starter {
  id: string;
  title: string;
  description: string;
  colors: Record<string, string>;
  fonts: Record<string, string>;
  pages: Array<{ title: string; sections: number }>;
}

interface ImportResult {
  created: Array<{ id: number; title: string; kind: string; type: string; edit: string }>;
  kit: boolean;
  kitSnapshot: string;
  warnings: string[];
}

const SWATCHES = ['primary', 'secondary', 'accent', 'surface', 'heading'];

/** Picks a starter site (Design System + header/footer + pages from the section library) and imports it as drafts. */
export function StarterSitesDialog({ open, onClose, onImported }: { open: boolean; onClose: () => void; onImported?: () => void }) {
  const list = useResource((signal) => (open ? api<Starter[]>('starters', { signal }) : Promise.resolve(null)), [open]);
  const [chosen, setChosen] = useState<string | null>(null);
  const [kit, setKit] = useState(true);
  const [templates, setTemplates] = useState(true);
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<ImportResult | null>(null);
  const [undone, setUndone] = useState(false);
  const starter = list.data?.find((s) => s.id === chosen) ?? null;

  const close = () => {
    setChosen(null);
    setResult(null);
    setUndone(false);
    onClose();
  };
  const run = async () => {
    if (!starter) return;
    setBusy(true);
    try {
      setResult(await api<ImportResult>('starters/import', { body: { id: starter.id, kit, templates, conditions: templates } }));
      onImported?.();
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog
      open={open}
      onClose={close}
      width={760}
      title={result ? 'Starter site added' : 'Start from a starter site'}
      description={result ? 'Everything arrived as drafts: review, replace the [placeholders] with your own words and images, then publish.' : 'A complete small site built from the section library: colors and fonts, header, footer and pages with placeholder copy you replace with your own.'}
      footer={
        result ? (
          <Button variant="primary" onClick={close}>
            Done
          </Button>
        ) : (
          <>
            <Button onClick={close}>Cancel</Button>
            <Button variant="primary" icon="download" loading={busy} disabled={!starter} onClick={run}>
              {starter ? `Add “${starter.title}”` : 'Choose a starter'}
            </Button>
          </>
        )
      }
    >
      {result ? (
        <div className="uncoder-ui-stack">
          {result.warnings.length > 0 && <Callout tone="warning">{result.warnings.join(' ')}</Callout>}
          <ul className="uncoder-ui-starter-result">
            {result.created.map((c) => (
              <li key={c.id}>
                <Icon name={c.kind === 'template' ? 'layout-template' : 'file-text'} size={14} />
                <span>{c.title}</span>
                <span className="uncoder-ui-starter-result__type">{c.kind === 'template' ? c.type : 'page · draft'}</span>
                <a className="uncoder-ui-link" href={c.edit}>
                  Edit
                </a>
              </li>
            ))}
          </ul>
          {templates && <p className="uncoder-ui-muted">The header and footer take over the whole site once you publish them (Theme Builder).</p>}
          {result.kit && result.kitSnapshot && (
            <Callout
              tone={undone ? 'success' : 'info'}
              actions={
                !undone && (
                  <Button
                    size="sm"
                    icon="undo-2"
                    onClick={async () => {
                      try {
                        await api('kit/restore', { body: { id: result.kitSnapshot } });
                        setUndone(true);
                        toast('Previous colors and fonts restored');
                      } catch (e) {
                        toastError(e);
                      }
                    }}
                  >
                    Undo colors & fonts
                  </Button>
                )
              }
            >
              {undone ? 'Your previous colors and fonts are back.' : 'The Design System now uses the starter’s colors and fonts.'}
            </Callout>
          )}
        </div>
      ) : list.error ? (
        <ErrorState error={list.error} onRetry={list.reload} />
      ) : !list.data ? (
        <SkeletonRows rows={3} />
      ) : (
        <div className="uncoder-ui-stack">
          <div className="uncoder-ui-starters" role="radiogroup" aria-label="Starter sites">
            {list.data.map((s) => (
              <button key={s.id} type="button" role="radio" aria-checked={chosen === s.id} className={`uncoder-ui-starter${chosen === s.id ? ' is-chosen' : ''}`} onClick={() => setChosen(s.id)}>
                <span className="uncoder-ui-starter__preview" style={{ background: s.colors.surface || '#f5f5f5' }}>
                  <span className="uncoder-ui-starter__title" style={{ fontFamily: `"${s.fonts.heading}", system-ui`, color: s.colors.heading || '#111' }}>
                    Aa
                  </span>
                  <span className="uncoder-ui-starter__swatches">
                    {SWATCHES.filter((k) => s.colors[k]).map((k) => (
                      <span key={k} style={{ background: s.colors[k] }} title={k} />
                    ))}
                  </span>
                </span>
                <strong>{s.title}</strong>
                <span className="uncoder-ui-starter__desc">{s.description}</span>
                <span className="uncoder-ui-starter__meta">
                  {s.fonts.heading} / {s.fonts.body} · {s.pages.map((p) => p.title).join(', ')}
                </span>
              </button>
            ))}
          </div>
          {starter && (
            <div className="uncoder-ui-setlist">
              <label className="uncoder-ui-starter-opt">
                <Toggle checked={kit} onChange={setKit} label="Use its colors and fonts" />
                <span>
                  <strong>Use its colors and fonts</strong>
                  <span className="uncoder-ui-muted"> — updates the Design System (a restore point is kept)</span>
                </span>
              </label>
              <label className="uncoder-ui-starter-opt">
                <Toggle checked={templates} onChange={setTemplates} label="Add its header and footer" />
                <span>
                  <strong>Add its header and footer</strong>
                  <span className="uncoder-ui-muted"> — as drafts for the whole site</span>
                </span>
              </label>
            </div>
          )}
        </div>
      )}
    </Dialog>
  );
}
