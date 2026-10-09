import { useEffect, useState } from 'react';
import { Button, Segmented, Toggle } from '@editor/ui/primitives';
import { NAME } from '@shared/brand';
import { api } from '../lib/api';
import { toast } from '../lib/toast';
import { Dialog } from './Dialog';
import { Callout, Field } from './kit';

interface Result {
  matches: number;
  applied: boolean;
  documents: Array<{ id: number; title: string; type: string; count: number; samples: Array<{ where: string; text: string }>; edit: string }>;
}

/** Site-wide find & replace (Site\Find_Replace): preview first, then apply. */
export function FindReplaceDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [find, setFind] = useState('');
  const [replace, setReplace] = useState('');
  const [scope, setScope] = useState('text');
  const [matchCase, setMatchCase] = useState(false);
  const [preview, setPreview] = useState<Result | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (open) {
      setPreview(null);
      setError(null);
    }
  }, [open]);
  // Any change to the query invalidates the preview.
  useEffect(() => setPreview(null), [find, replace, scope, matchCase]);

  const run = async (apply: boolean) => {
    setBusy(true);
    setError(null);
    try {
      const res = await api<Result>('find-replace', { body: { find, replace, scope, match_case: matchCase, apply } });
      if (apply) {
        toast(`Replaced ${res.matches} match${res.matches === 1 ? '' : 'es'} in ${res.documents.length} document${res.documents.length === 1 ? '' : 's'}`);
        onClose();
      } else {
        setPreview(res);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Search failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog
      open={open}
      onClose={onClose}
      width={620}
      title="Find & replace across the site"
      description={`Every ${NAME} page, post and template. Texts, links and colors are matched by setting type, so layout values are never touched. Each changed document keeps a revision.`}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          {preview && preview.matches > 0 ? (
            <Button variant="primary" onClick={() => run(true)} loading={busy}>
              Replace {preview.matches} match{preview.matches === 1 ? '' : 'es'}
            </Button>
          ) : (
            <Button variant="primary" icon="search" onClick={() => run(false)} loading={busy} disabled={!find}>
              Preview
            </Button>
          )}
        </>
      }
    >
      <div className="uncoder-ui-stack">
        {error && <Callout tone="danger">{error}</Callout>}
        <div className="uncoder-ui-formgrid uncoder-ui-formgrid--even">
          <Field label="Find" htmlFor="uncoder-ui-fr-find">
            <input id="uncoder-ui-fr-find" className="uncoder-ui-input" value={find} onChange={(e) => setFind(e.currentTarget.value)} placeholder={scope === 'links' ? 'https://old-domain.com' : scope === 'colors' ? '#1f4d3a' : 'Old product name'} autoFocus />
          </Field>
          <Field label="Replace with" htmlFor="uncoder-ui-fr-replace">
            <input id="uncoder-ui-fr-replace" className="uncoder-ui-input" value={replace} onChange={(e) => setReplace(e.currentTarget.value)} />
          </Field>
        </div>
        <div className="uncoder-ui-fr__opts">
          <Segmented
            value={scope}
            onChange={setScope}
            ariaLabel="Search in"
            options={[
              { value: 'text', label: 'Text' },
              { value: 'links', label: 'Links' },
              { value: 'colors', label: 'Colors' },
              { value: 'all', label: 'All' },
            ]}
          />
          <Toggle checked={matchCase} onChange={setMatchCase} label="Match case" />
          <span>Match case</span>
        </div>
        {preview &&
          (preview.matches === 0 ? (
            <Callout tone="info">No matches.</Callout>
          ) : (
            <ul className="uncoder-ui-importlist uncoder-ui-importlist--preview">
              {preview.documents.map((d) => (
                <li key={d.id} className="uncoder-ui-fr__doc">
                  <span className="uncoder-ui-importlist__title">
                    {d.title}
                    {d.samples.map((s, i) => (
                      <span key={i} className="uncoder-ui-fr__sample">
                        {s.where}: {s.text}
                      </span>
                    ))}
                  </span>
                  <span className="uncoder-ui-importlist__type">
                    {d.count} · {d.type}
                  </span>
                </li>
              ))}
            </ul>
          ))}
      </div>
    </Dialog>
  );
}
