// Library → Import & export: the whole site as one zip (Site\Site_Kit) — export here, import on another site.
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button } from '@editor/ui/primitives';
import { NAME } from '@shared/brand';
import { api, ApiError } from '../lib/api';
import { cfg } from '../lib/config';
import { toast, toastError } from '../lib/toast';
import { Badge, Callout, Card, Checkbox } from '../ui/kit';

export type Part = 'design' | 'templates' | 'content' | 'posts' | 'menus' | 'media' | 'fonts' | 'settings' | 'snippets';
export const PART_LABEL: Record<Part, { label: string; help: string }> = {
  design: { label: 'Design System', help: 'Colors, fonts, text styles, sizes, buttons, classes' },
  templates: { label: 'Theme Builder', help: 'Headers, footers, singles, archives, popups, saved sections, loop items' },
  content: { label: 'Pages & posts', help: `Everything built with ${NAME}` },
  posts: { label: 'Blog posts', help: 'Posts written in the WordPress editor, with dates, categories, tags and featured images' },
  menus: { label: 'Menus', help: 'With mega menus and menu locations' },
  media: { label: 'Images & files', help: 'Every file those use, inside the zip' },
  fonts: { label: 'Custom fonts', help: 'Uploaded font files' },
  settings: { label: 'Settings', help: `${NAME} settings (never passwords or API keys)` },
  snippets: { label: 'Custom code', help: 'Head / body / footer snippets (imported switched off)' },
};
export const ALL_PARTS: Part[] = ['design', 'templates', 'content', 'posts', 'menus', 'media', 'fonts', 'settings', 'snippets'];

interface Summary {
  templates: number;
  content: number;
  posts: number;
  menus: number;
  fonts: number;
  snippets: number;
  maxUpload: number;
}
export interface Preview {
  token: string;
  manifest: { site: { name: string; url: string }; exported: string; plugin: string; parts: Part[] };
  design: boolean;
  settings: boolean;
  snippets: number;
  fonts: number;
  media: number;
  templates: Array<{ title: string; type: string; exists: boolean }>;
  content: Array<{ title: string; type: string; exists: boolean }>;
  /** Missing in kits from before blog posts could be exported. */
  posts?: Array<{ title: string; type: string; exists: boolean }>;
  menus: Array<{ name: string; items: number; exists: boolean }>;
  homepage: boolean;
}
export interface Report {
  /** Every template, page and post written (new or replaced). */
  created: Array<{ id: number; title: string; kind: string; type: string; edit: string; replaced?: boolean }>;
  /** New items (templates, pages, posts, menus, snippets); replaced ones are counted in `replaced` only. */
  added?: number;
  replaced: number;
  skipped: number;
  media: number;
  reused?: number;
  languages?: number;
  warnings: string[];
  homepage?: boolean;
}

/** How much of a part a kit holds (design and settings: 1 or 0). */
const inKit = (p: Part, k: Preview): number => {
  switch (p) {
    case 'design':
      return k.design ? 1 : 0;
    case 'settings':
      return k.settings ? 1 : 0;
    case 'snippets':
    case 'fonts':
    case 'media':
      return k[p];
    case 'posts':
      return (k.posts ?? []).length;
    default:
      return k[p].length;
  }
};

const mb = (n: number) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);

export function SiteKitCard() {
  const [summary, setSummary] = useState<Summary | null>(null);
  const [parts, setParts] = useState<Record<Part, boolean>>(() => Object.fromEntries(ALL_PARTS.map((p) => [p, true])) as Record<Part, boolean>);
  const [exporting, setExporting] = useState<string | null>(null);

  useEffect(() => {
    api<Summary>('site-kit/summary').then(setSummary).catch(() => {});
  }, []);

  const count: Partial<Record<Part, number>> = summary ? { templates: summary.templates, content: summary.content, posts: summary.posts, menus: summary.menus, fonts: summary.fonts, snippets: summary.snippets } : {};
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
      <Card title="Export this site" description={`Everything you built with ${NAME} in one zip file: a backup, or the start of another site. Import it under Library → Import & export on the other site.`}>
        <div className="uncoder-ui-kitparts">
          {ALL_PARTS.map((p) => (
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
          <Button variant="primary" icon="download" loading={!!exporting} disabled={!ALL_PARTS.some((p) => parts[p])} onClick={run}>
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
      setPreview(data as Preview);
    } catch (e) {
      toastError(e);
    } finally {
      setUploading(false);
      if (input.current) input.current.value = '';
    }
  };

  return (
    <Card title="Import a site kit" description={`A zip exported from ${NAME} on this or another site. You choose what to bring in before anything changes.${maxUpload ? ` Largest file this server accepts: ${mb(maxUpload)}.` : ''}`}>
      {!preview && !report && (
        <label className={`uncoder-ui-kitdrop${uploading ? ' is-busy' : ''}`}>
          <input ref={input} type="file" accept=".zip,application/zip" disabled={uploading} onChange={(e) => e.currentTarget.files?.[0] && upload(e.currentTarget.files[0])} />
          <Icon name={uploading ? 'loader' : 'file-archive'} size={22} />
          <strong>{uploading ? 'Reading the kit…' : 'Choose a site kit (.zip)'}</strong>
          <span>or drop it here</span>
        </label>
      )}
      {preview && (
        <KitImport
          preview={preview}
          onCancel={() => setPreview(null)}
          onDone={(r) => {
            setReport(r);
            setPreview(null);
          }}
        />
      )}
      {report && <KitReport report={report} again="Import another kit" onAgain={() => setReport(null)} />}
    </Card>
  );
}

/**
 * A staged site kit (Site_Kit::stage_zip()'s preview): what it holds, what to bring in, what to do with items that
 * already exist; Import runs the media steps, then the rest. Also used by the starter-site library.
 */
export function KitImport({ preview, onCancel, onDone, from, onBusy }: { preview: Preview; onCancel: () => void; onDone: (r: Report) => void; from?: ReactNode; onBusy?: (busy: boolean) => void }) {
  const [parts, setParts] = useState<Record<Part, boolean>>(() => Object.fromEntries(ALL_PARTS.map((part) => [part, inKit(part, preview) > 0])) as Record<Part, boolean>);
  const [conflicts, setConflicts] = useState<'skip' | 'replace' | 'keep'>('skip');
  const [homepage, setHomepage] = useState(true);
  const [activate, setActivate] = useState(true);
  const [progress, setProgress] = useState<string | null>(null);
  const busy = progress !== null;

  // While it runs: the host locks its dialog, and leaving or reloading the page asks first (a half import is messy).
  useEffect(() => {
    onBusy?.(busy);
    if (!busy) return;
    const stay = (e: BeforeUnloadEvent) => {
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', stay);
    return () => window.removeEventListener('beforeunload', stay);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [busy]);

  const start = async () => {
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
      setProgress('Creating templates, pages, posts and menus…');
      const res = await api<Report>('site-kit/import', { body: { token: preview.token, step: 'finish', parts, conflicts, homepage, activate } });
      toast(`Imported ${res.created.length} item${res.created.length === 1 ? '' : 's'}`);
      onDone(res);
    } catch (e) {
      toastError(e);
    } finally {
      setProgress(null);
    }
  };

  const existing = [...preview.templates, ...preview.content, ...(preview.posts ?? [])].filter((x) => x.exists).length + preview.menus.filter((m) => m.exists).length;
  const available = (p: Part) => inKit(p, preview) > 0;
  const kitCount = (p: Part) => (p === 'design' || p === 'settings' ? undefined : inKit(p, preview));

  return (
    <div className="uncoder-ui-kitpreview">
      <div className="uncoder-ui-kitpreview__from">
        <Icon name="package" size={16} />
        <span>
          {from ?? (
            <>
              From <strong>{preview.manifest.site.name || preview.manifest.site.url}</strong> · exported {new Date(preview.manifest.exported).toLocaleString()} · {NAME} {preview.manifest.plugin}
            </>
          )}
        </span>
      </div>
      <div className="uncoder-ui-kitparts">
        {ALL_PARTS.filter(available).map((p) => (
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
              ['replace', 'Replace mine', 'Existing templates, pages, posts and menus are overwritten with the kit’s.'],
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
        <Button variant="primary" icon="upload" loading={!!progress} disabled={!ALL_PARTS.some((p) => parts[p])} onClick={start}>
          Import
        </Button>
        <Button disabled={!!progress} onClick={onCancel}>
          Cancel
        </Button>
        {progress && <span className="uncoder-ui-muted">{progress}</span>}
      </div>
      {progress && (
        <Callout tone="info" title="Keep this window open">
          The import is running. Don’t close or reload the page until it finishes.
        </Callout>
      )}
    </div>
  );
}

/** What an import did: counts, warnings and links to edit what arrived. */
export function KitReport({ report, again, onAgain, extra }: { report: Report; again?: string; onAgain?: () => void; extra?: ReactNode }) {
  return (
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
            <Icon name={c.kind === 'template' ? 'layout-template' : c.kind === 'post' ? 'newspaper' : 'file-text'} size={14} />
            <span>{c.title}</span>
            <span className="uncoder-ui-muted">{c.type}</span>
            <a href={c.edit}>Edit</a>
          </li>
        ))}
      </ul>
      {(onAgain || extra) && (
        <div className="uncoder-ui-kitreport__actions">
          {extra}
          {onAgain && <Button onClick={onAgain}>{again ?? 'Done'}</Button>}
        </div>
      )}
    </div>
  );
}
