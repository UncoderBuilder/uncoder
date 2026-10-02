// Settings → Import & export: the whole site as one zip (Site\Site_Kit) — export here, import on another site.
import { useEffect, useRef, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button } from '@editor/ui/primitives';
import { api, ApiError } from '../lib/api';
import { cfg } from '../lib/config';
import { toast, toastError } from '../lib/toast';
import { Badge, Callout, Card, Checkbox } from '../ui/kit';

type Part = 'design' | 'templates' | 'content' | 'menus' | 'media' | 'fonts' | 'settings' | 'snippets';
const PART_LABEL: Record<Part, { label: string; help: string }> = {
  design: { label: 'Design System', help: 'Colors, fonts, text styles, sizes, buttons, classes' },
  templates: { label: 'Theme Builder', help: 'Headers, footers, singles, archives, popups, saved sections, loop items' },
  content: { label: 'Pages & posts', help: 'Everything built with Uncoder' },
  menus: { label: 'Menus', help: 'With mega menus and menu locations' },
  media: { label: 'Images & files', help: 'Every file those use, inside the zip' },
  fonts: { label: 'Custom fonts', help: 'Uploaded font files' },
  settings: { label: 'Settings', help: 'Uncoder settings (never passwords or API keys)' },
  snippets: { label: 'Custom code', help: 'Head / body / footer snippets (imported switched off)' },
};
const ALL: Part[] = ['design', 'templates', 'content', 'menus', 'media', 'fonts', 'settings', 'snippets'];

interface Summary {
  templates: number;
  content: number;
  menus: number;
  fonts: number;
  snippets: number;
  maxUpload: number;
}
interface Preview {
  token: string;
  manifest: { site: { name: string; url: string }; exported: string; plugin: string; parts: Part[] };
  design: boolean;
  settings: boolean;
  snippets: number;
  fonts: number;
  media: number;
  templates: Array<{ title: string; type: string; exists: boolean }>;
  content: Array<{ title: string; type: string; exists: boolean }>;
  menus: Array<{ name: string; items: number; exists: boolean }>;
  homepage: boolean;
}
interface Report {
  /** Every template and page written (new or replaced). */
  created: Array<{ id: number; title: string; kind: string; type: string; edit: string; replaced?: boolean }>;
  /** New items (templates, pages, menus, snippets); replaced ones are counted in `replaced` only. */
  added?: number;
  replaced: number;
  skipped: number;
  media: number;
  reused?: number;
  languages?: number;
  warnings: string[];
  homepage?: boolean;
}

const mb = (n: number) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);

export function SiteKitCard() {
  const [summary, setSummary] = useState<Summary | null>(null);
  const [parts, setParts] = useState<Record<Part, boolean>>(() => Object.fromEntries(ALL.map((p) => [p, true])) as Record<Part, boolean>);
  const [exporting, setExporting] = useState<string | null>(null);

  useEffect(() => {
    api<Summary>('site-kit/summary').then(setSummary).catch(() => {});
  }, []);

  const count: Partial<Record<Part, number>> = summary ? { templates: summary.templates, content: summary.content, menus: summary.menus, fonts: summary.fonts, snippets: summary.snippets } : {};
  const run = async () => {
    setExporting('Collecting pages, templates and files…');
    try {
      const res = await api<{ url: string; size: number; counts: Record<string, number> }>('site-kit/export', { body: { parts } });
      setExporting(null);
      toast(`Site kit ready (${mb(res.size)}), downloading…`);
      window.location.href = res.url;
    } catch (e) {
      setExporting(null);
      toastError(e);
    }
  };

  return (
    <>
      <Card title="Export this site" description="Everything you built with Uncoder in one zip file: a backup, or the start of another site. Import it under Settings → Import & export on the other site.">
        <div className="uncoder-ui-kitparts">
          {ALL.map((p) => (
            <Checkbox
              key={p}
              checked={parts[p]}
              onChange={(v) => setParts((s) => ({ ...s, [p]: v }))}
              label={
                <>
                  {PART_LABEL[p].label}
                  {count[p] !== undefined && <span className="uncoder-ui-kitparts__count">{count[p]}</span>}
                </>
              }
              description={PART_LABEL[p].help}
            />
          ))}
        </div>
        <div className="uncoder-ui-kitactions">
          <Button variant="primary" icon="download" loading={!!exporting} disabled={!ALL.some((p) => parts[p])} onClick={run}>
            Export site kit
          </Button>
          {exporting && <span className="uncoder-ui-muted">{exporting}</span>}
        </div>
      </Card>
      <ImportCard maxUpload={summary?.maxUpload ?? 0} />
    </>
  );
}

function ImportCard({ maxUpload }: { maxUpload: number }) {
  const input = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const [preview, setPreview] = useState<Preview | null>(null);
  const [parts, setParts] = useState<Record<Part, boolean>>({} as Record<Part, boolean>);
  const [conflicts, setConflicts] = useState<'skip' | 'replace' | 'keep'>('skip');
  const [homepage, setHomepage] = useState(true);
  const [activate, setActivate] = useState(true);
  const [progress, setProgress] = useState<string | null>(null);
  const [report, setReport] = useState<Report | null>(null);

  const upload = async (file: File) => {
    setUploading(true);
    setReport(null);
    try {
      const form = new FormData();
      form.append('file', file);
      const res = await fetch(`${cfg.rest.root}site-kit/upload`, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.rest.nonce }, body: form });
      const data = await res.json().catch(() => null);
      if (!res.ok) throw new ApiError(data?.message ?? `Upload failed (${res.status})`, res.status, data);
      const p = data as Preview;
      setPreview(p);
      setParts(
        Object.fromEntries(
          ALL.map((part) => [
            part,
            part === 'design' ? p.design : part === 'settings' ? p.settings : part === 'snippets' ? p.snippets > 0 : part === 'fonts' ? p.fonts > 0 : part === 'media' ? p.media > 0 : part === 'templates' ? p.templates.length > 0 : part === 'content' ? p.content.length > 0 : p.menus.length > 0,
          ]),
        ) as Record<Part, boolean>,
      );
    } catch (e) {
      toastError(e);
    } finally {
      setUploading(false);
      if (input.current) input.current.value = '';
    }
  };

  const start = async () => {
    if (!preview) return;
    try {
      if (parts.media) {
        let done = 0;
        let total = preview.media;
        setProgress(`Uploading images and files… 0 / ${total}`);
        while (done < total) {
          const r = await api<{ done: number; total: number }>('site-kit/import', { body: { token: preview.token, step: 'media', parts } });
          if (r.done === done) break;
          done = r.done;
          total = r.total;
          setProgress(`Uploading images and files… ${done} / ${total}`);
        }
      }
      setProgress('Creating templates, pages and menus…');
      const res = await api<Report>('site-kit/import', { body: { token: preview.token, step: 'finish', parts, conflicts, homepage, activate } });
      setReport(res);
      setPreview(null);
      toast(`Imported ${res.created.length} item${res.created.length === 1 ? '' : 's'}`);
    } catch (e) {
      toastError(e);
    } finally {
      setProgress(null);
    }
  };

  const existing = preview ? [...preview.templates, ...preview.content].filter((x) => x.exists).length + preview.menus.filter((m) => m.exists).length : 0;
  const available = (p: Part) =>
    !!preview && (p === 'design' ? preview.design : p === 'settings' ? preview.settings : p === 'snippets' ? preview.snippets > 0 : p === 'fonts' ? preview.fonts > 0 : p === 'media' ? preview.media > 0 : p === 'templates' ? preview.templates.length > 0 : p === 'content' ? preview.content.length > 0 : preview.menus.length > 0);
  const kitCount = (p: Part) => (!preview ? 0 : p === 'templates' ? preview.templates.length : p === 'content' ? preview.content.length : p === 'menus' ? preview.menus.length : p === 'media' ? preview.media : p === 'fonts' ? preview.fonts : p === 'snippets' ? preview.snippets : undefined);

  return (
    <Card title="Import a site kit" description={`A zip exported from Uncoder on this or another site. You choose what to bring in before anything changes.${maxUpload ? ` Largest file this server accepts: ${mb(maxUpload)}.` : ''}`}>
      {!preview && !report && (
        <label className={`uncoder-ui-kitdrop${uploading ? ' is-busy' : ''}`}>
          <input ref={input} type="file" accept=".zip,application/zip" disabled={uploading} onChange={(e) => e.currentTarget.files?.[0] && upload(e.currentTarget.files[0])} />
          <Icon name={uploading ? 'loader' : 'file-archive'} size={22} />
          <strong>{uploading ? 'Reading the kit…' : 'Choose a site kit (.zip)'}</strong>
          <span>or drop it here</span>
        </label>
      )}
      {preview && (
        <div className="uncoder-ui-kitpreview">
          <div className="uncoder-ui-kitpreview__from">
            <Icon name="package" size={16} />
            <span>
              From <strong>{preview.manifest.site.name || preview.manifest.site.url}</strong> · exported {new Date(preview.manifest.exported).toLocaleString()} · Uncoder {preview.manifest.plugin}
            </span>
          </div>
          <div className="uncoder-ui-kitparts">
            {ALL.filter(available).map((p) => (
              <Checkbox
                key={p}
                checked={!!parts[p]}
                onChange={(v) => setParts((s) => ({ ...s, [p]: v }))}
                label={
                  <>
                    {PART_LABEL[p].label}
                    {kitCount(p) !== undefined && <span className="uncoder-ui-kitparts__count">{kitCount(p)}</span>}
                    {p === 'design' && <Badge tone="warning">replaces yours (a restore point is kept)</Badge>}
                  </>
                }
                description={PART_LABEL[p].help}
              />
            ))}
          </div>
          {existing > 0 && (
            <div className="uncoder-ui-kitconflicts">
              <strong>
                {existing} item{existing === 1 ? '' : 's'} already exist here (same name)
              </strong>
              {(
                [
                  ['skip', 'Keep mine', 'Existing items stay; only new ones are added.'],
                  ['replace', 'Replace mine', 'Existing templates, pages and menus are overwritten with the kit’s.'],
                  ['keep', 'Keep both', 'The kit’s copies are added next to yours.'],
                ] as const
              ).map(([v, label, help]) => (
                <label key={v} className="uncoder-ui-radio">
                  <input type="radio" name="uncoder-kit-conflicts" checked={conflicts === v} onChange={() => setConflicts(v)} />
                  <span>
                    <strong>{label}</strong> — {help}
                  </span>
                </label>
              ))}
            </div>
          )}
          {parts.templates && <Checkbox checked={activate} onChange={setActivate} label="Activate headers, footers and other templates" description="Applies their display conditions as on the original site. Off: they arrive as drafts." />}
          {parts.content && preview.homepage && <Checkbox checked={homepage} onChange={setHomepage} label="Use the kit’s home page" description="Sets Settings → Reading → Homepage like the original site." />}
          <div className="uncoder-ui-kitactions">
            <Button variant="primary" icon="upload" loading={!!progress} disabled={!ALL.some((p) => parts[p])} onClick={start}>
              Import
            </Button>
            <Button disabled={!!progress} onClick={() => setPreview(null)}>
              Cancel
            </Button>
            {progress && <span className="uncoder-ui-muted">{progress}</span>}
          </div>
        </div>
      )}
      {report && (
        <div className="uncoder-ui-kitreport">
          <Callout tone="success" title="Import finished">
            {report.added ?? report.created.length} created{report.replaced ? `, ${report.replaced} replaced` : ''}
            {report.skipped ? `, ${report.skipped} kept as they were` : ''}, {report.media} file{report.media === 1 ? '' : 's'} added to the media library{report.reused ? ` (${report.reused} already there from an earlier import, reused)` : ''}{report.languages ? `, ${report.languages} given their language` : ''}{report.homepage ? ', home page set' : ''}.
          </Callout>
          {report.warnings.length > 0 && (
            <Callout tone="warning" title="Worth a look">
              <ul>
                {report.warnings.slice(0, 12).map((w, i) => (
                  <li key={i}>{w}</li>
                ))}
              </ul>
            </Callout>
          )}
          <ul className="uncoder-ui-kitreport__list">
            {report.created.slice(0, 40).map((c) => (
              <li key={c.id}>
                <Icon name={c.kind === 'template' ? 'layout-template' : 'file-text'} size={14} />
                <span>{c.title}</span>
                <span className="uncoder-ui-muted">{c.type}</span>
                <a href={c.edit}>Edit</a>
              </li>
            ))}
          </ul>
          <Button onClick={() => setReport(null)}>Import another kit</Button>
        </div>
      )}
    </Card>
  );
}
