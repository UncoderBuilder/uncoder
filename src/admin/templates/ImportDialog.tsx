import { useEffect, useRef, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button } from '@editor/ui/primitives';
import { NAME } from '@shared/brand';
import { api } from '../lib/api';
import { cfg } from '../lib/config';
import { readExportFile, type ExportFile, type ImportResult } from '../lib/transfer';
import { Dialog } from '../ui/Dialog';
import { Callout, Checkbox } from '../ui/kit';

/** Imports an Uncoder export file (templates, pages, Design System). Everything arrives as drafts. */
export function ImportDialog({ open, onClose, onImported }: { open: boolean; onClose: () => void; onImported?: (r: ImportResult) => void }) {
  const [file, setFile] = useState<ExportFile | null>(null);
  const [name, setName] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [opts, setOpts] = useState({ kit: false, media: true, conditions: false });
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<ImportResult | null>(null);
  const input = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (!open) return;
    setFile(null);
    setName('');
    setError(null);
    setResult(null);
    setOpts({ kit: false, media: true, conditions: false });
  }, [open]);

  const pick = async (f: File | undefined) => {
    if (!f) return;
    setError(null);
    try {
      const data = await readExportFile(f);
      setFile(data);
      setName(f.name);
    } catch (e) {
      setFile(null);
      setError(e instanceof Error ? e.message : 'Could not read the file.');
    }
  };

  const run = async () => {
    if (!file) return;
    setBusy(true);
    setError(null);
    try {
      const r = await api<ImportResult>('transfer/import', { body: { data: file, options: opts } });
      setResult(r);
      onImported?.(r);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Import failed.');
    } finally {
      setBusy(false);
    }
  };

  const templates = file?.items.filter((i) => i.kind === 'template') ?? [];
  const pages = file?.items.filter((i) => i.kind === 'page') ?? [];
  const typeName = (t: string) => cfg.templateTypes[t] ?? t;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      width={560}
      title={result ? 'Import finished' : 'Import templates'}
      description={result ? undefined : `Bring in templates, pages and a Design System exported from any site running ${NAME}. Everything is created as a draft.`}
      footer={
        result ? (
          <Button variant="primary" onClick={onClose}>
            Done
          </Button>
        ) : (
          <>
            <Button variant="ghost" onClick={onClose}>
              Cancel
            </Button>
            <Button variant="primary" icon="download" onClick={run} loading={busy} disabled={!file || (!file.items.length && !(opts.kit && file.kit))}>
              Import
            </Button>
          </>
        )
      }
    >
      {result ? (
        <div className="uncoder-ui-stack">
          <Callout tone="success" icon="circle-check">
            Imported {result.created.length} item{result.created.length === 1 ? '' : 's'}
            {result.kit ? ' and the Design System' : ''}
            {result.images ? `, ${result.images} image${result.images === 1 ? '' : 's'} copied to the media library` : ''}. Review the drafts, then publish them.
          </Callout>
          {result.created.length > 0 && (
            <ul className="uncoder-ui-importlist">
              {result.created.map((c) => (
                <li key={c.id}>
                  <Icon name={c.kind === 'page' ? 'file-text' : 'layout-template'} size={14} />
                  <span className="uncoder-ui-importlist__title">{c.title}</span>
                  <span className="uncoder-ui-importlist__type">{c.kind === 'page' ? c.type : typeName(c.type)}</span>
                  <a className="uncoder-ui-btn uncoder-ui-btn--secondary uncoder-ui-btn--sm" href={c.edit}>
                    Edit
                  </a>
                </li>
              ))}
            </ul>
          )}
          {result.warnings.map((w) => (
            <Callout key={w} tone="warning">
              {w}
            </Callout>
          ))}
        </div>
      ) : (
        <div className="uncoder-ui-stack">
          {error && <Callout tone="danger">{error}</Callout>}
          <div className="uncoder-ui-filepick">
            <input ref={input} type="file" accept=".json,application/json" className="uncoder-ui-sr-only" aria-label="Export file" onChange={(e) => pick(e.currentTarget.files?.[0])} />
            <Button icon="file-up" onClick={() => input.current?.click()}>
              {file ? 'Choose another file' : 'Choose export file'}
            </Button>
            <span className="uncoder-ui-filepick__name">{name || `A .json file from ${NAME} → Export`}</span>
          </div>
          {file && (
            <>
              <p className="uncoder-ui-muted">
                From {file.site.replace(/^https?:\/\//, '').replace(/\/$/, '')} · {new Date(file.exported).toLocaleDateString()} — {templates.length} template{templates.length === 1 ? '' : 's'}
                {pages.length ? `, ${pages.length} page${pages.length === 1 ? '' : 's'}` : ''}
                {file.kit ? ', Design System' : ''}
              </p>
              {file.items.length > 0 && (
                <ul className="uncoder-ui-importlist uncoder-ui-importlist--preview">
                  {file.items.slice(0, 12).map((i) => (
                    <li key={`${i.kind}-${i.id}`}>
                      <Icon name={i.kind === 'page' ? 'file-text' : 'layout-template'} size={14} />
                      <span className="uncoder-ui-importlist__title">{i.title || '(no title)'}</span>
                      <span className="uncoder-ui-importlist__type">{i.kind === 'page' ? i.type : typeName(i.type)}</span>
                    </li>
                  ))}
                  {file.items.length > 12 && <li className="uncoder-ui-muted">+{file.items.length - 12} more</li>}
                </ul>
              )}
              <div className="uncoder-ui-checklist">
                <Checkbox label="Copy images into the media library" description="Otherwise images keep loading from the original site." checked={opts.media} onChange={(v) => setOpts((o) => ({ ...o, media: v }))} />
                {templates.length > 0 && (
                  <Checkbox label="Keep display conditions" description="Where each template appears. They apply once you publish the template." checked={opts.conditions} onChange={(v) => setOpts((o) => ({ ...o, conditions: v }))} />
                )}
                {file.kit && (
                  <Checkbox label="Replace the Design System" description="Colors, fonts, text styles and buttons of this site are overwritten. You can undo it from the Design System history." checked={opts.kit} onChange={(v) => setOpts((o) => ({ ...o, kit: v }))} />
                )}
              </div>
            </>
          )}
        </div>
      )}
    </Dialog>
  );
}
