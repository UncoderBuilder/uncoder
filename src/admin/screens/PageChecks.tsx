// Uncoder → Page checks (Site\Page_Checks): the page audit (accessibility, search, content, mobile, speed) on every
// published page built with Uncoder, and the site's setup. With the Agency licence the results become a branded
// report behind a private link (printable to PDF), under the agency's name, logo and colour.
import { useEffect, useMemo, useRef, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button, IconButton } from '@editor/ui/primitives';
import { api } from '../lib/api';
import { cfg } from '../lib/config';
import { absoluteTime, plural, relativeTime } from '../lib/format';
import { pickImage, type PickedImage } from '../lib/media';
import { toast, toastError } from '../lib/toast';
import { Badge, Callout, Card, Checkbox, EmptyState, ErrorState, PageHeader, SettingRow, SkeletonRows } from '../ui/kit';
import { confirmDialog, Dialog } from '../ui/Dialog';

type Severity = 'error' | 'warning' | 'info';
interface Issue {
  severity: Severity;
  category: string;
  group: string;
  rule: string;
  message: string;
  fix: string;
  count: number;
  elements: string[];
}
interface PageInfo {
  id: number;
  title: string;
  type: string;
  url: string;
  edit: string;
}
interface PageResult extends PageInfo {
  score: number;
  issues: Issue[];
}
interface SiteCheck {
  id: string;
  status: 'pass' | 'warn' | 'fail';
  title: string;
  fix: string;
}
interface Report {
  id: number;
  title: string;
  created: string;
  score: number;
  pages: number;
  issues: number;
  url: string;
}
interface Brand {
  name: string;
  logo: PickedImage;
  accent: string;
  contact: string;
  intro: string;
  fallback: { name: string; logo: string; accent: string };
  allowed: boolean;
}

const BATCH = 8;
const SEVERITY: Record<Severity, { label: string; tone: 'danger' | 'warning' | 'info' }> = {
  error: { label: 'Fix', tone: 'danger' },
  warning: { label: 'Improve', tone: 'warning' },
  info: { label: 'Note', tone: 'info' },
};
const STATUS: Record<SiteCheck['status'], { label: string; tone: 'success' | 'warning' | 'danger' }> = {
  pass: { label: 'OK', tone: 'success' },
  warn: { label: 'Improve', tone: 'warning' },
  fail: { label: 'Fix', tone: 'danger' },
};

const scoreTone = (n: number) => (n >= 90 ? 'success' : n >= 70 ? 'warning' : 'danger');
const count = (issues: Issue[], s: Severity) => issues.filter((i) => i.severity === s).length;

export function PageChecksScreen() {
  const [pages, setPages] = useState<PageInfo[] | null>(null);
  const [site, setSite] = useState<SiteCheck[] | null>(null);
  const [error, setError] = useState<Error | null>(null);
  const [results, setResults] = useState<PageResult[]>([]);
  const [progress, setProgress] = useState<{ done: number; total: number } | null>(null);
  const [open, setOpen] = useState<number | null>(null);
  const [reporting, setReporting] = useState(false);
  const [reportsKey, setReportsKey] = useState(0);
  const stop = useRef(false);
  const agency = !!cfg.licensing; // Reports and branding: the people who manage the licence.
  const canReport = agency && !!cfg.reports; // …and the licence covers reports (otherwise the cards offer the plan).

  useEffect(() => {
    api<{ pages: PageInfo[] }>('checks/pages')
      .then((r) => setPages(r.pages))
      .catch(setError);
    api<{ checks: SiteCheck[] }>('checks/site')
      .then((r) => setSite(r.checks))
      .catch(() => setSite([]));
  }, []);

  const run = async () => {
    if (!pages?.length) return;
    stop.current = false;
    setResults([]);
    setOpen(null);
    setProgress({ done: 0, total: pages.length });
    const all: PageResult[] = [];
    try {
      for (let i = 0; i < pages.length && !stop.current; i += BATCH) {
        const ids = pages.slice(i, i + BATCH).map((p) => p.id);
        const res = await api<{ results: PageResult[] }>('checks/run', { body: { ids } });
        all.push(...res.results);
        setResults([...all]);
        setProgress({ done: Math.min(i + BATCH, pages.length), total: pages.length });
      }
    } catch (e) {
      toastError(e);
    } finally {
      setProgress(null);
    }
  };

  const sorted = useMemo(() => [...results].sort((a, b) => a.score - b.score || a.title.localeCompare(b.title)), [results]);
  const all = useMemo(() => results.flatMap((r) => r.issues), [results]);
  const average = results.length ? Math.round(results.reduce((n, r) => n + r.score, 0) / results.length) : null;

  return (
    <>
      <PageHeader
        title="Page Checks"
        description="Accessibility, search, content, mobile and speed checks for every published page built with this builder, and the site’s setup."
        actions={
          progress ? (
            <Button icon="square" onClick={() => (stop.current = true)}>
              Stop
            </Button>
          ) : (
            <>
              {canReport && results.length > 0 && (
                <Button icon="file-chart-column" onClick={() => setReporting(true)}>
                  Create report
                </Button>
              )}
              <Button variant="primary" icon="play" disabled={!pages?.length} onClick={run}>
                {results.length ? 'Check again' : `Check ${pages ? plural(pages.length, 'page', 'pages') : 'pages'}`}
              </Button>
            </>
          )
        }
      />
      {error ? (
        <ErrorState error={error} />
      ) : (
        <div className="uncoder-ui-stack">
          {progress && (
            <Card>
              <div className="uncoder-ui-checks__progress" role="status">
                <span>
                  Checking {progress.done} of {progress.total} pages…
                </span>
                <progress max={progress.total} value={progress.done} />
              </div>
            </Card>
          )}

          {average !== null && !progress && (
            <div className="uncoder-ui-checks__summary">
              <div className={`uncoder-ui-checks__ring is-${scoreTone(average)}`} style={{ ['--v' as string]: average }} role="img" aria-label={`Average score ${average} out of 100`}>
                <span>{average}</span>
              </div>
              <div className="uncoder-ui-checks__stats">
                <Stat n={results.length} label={results.length === 1 ? 'page checked' : 'pages checked'} />
                <Stat n={count(all, 'error')} label="to fix" />
                <Stat n={count(all, 'warning')} label="to improve" />
                <Stat n={count(all, 'info')} label="notes" />
              </div>
            </div>
          )}

          <Card title="Site setup" description="What visitors and search engines notice before any page.">
            {!site ? (
              <SkeletonRows rows={3} />
            ) : (
              <ul className="uncoder-ui-checks__setup">
                {site.map((c) => (
                  <li key={c.id}>
                    <Badge tone={STATUS[c.status].tone}>{STATUS[c.status].label}</Badge>
                    <span>
                      <strong>{c.title}</strong>
                      {c.status !== 'pass' && <span className="uncoder-ui-muted">{c.fix}</span>}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </Card>

          <Card flush title="Pages" description={results.length ? 'Lowest scores first. Open a page to see what to change.' : 'Published pages and posts built with this builder.'}>
            {!pages ? (
              <SkeletonRows rows={5} cols={3} />
            ) : pages.length === 0 ? (
              <EmptyState icon="file-search" title="No published pages yet">
                Pages appear here once they are built and published.
              </EmptyState>
            ) : results.length === 0 ? (
              <EmptyState icon="list-checks" title={plural(pages.length, 'page to check', 'pages to check')} action={<Button variant="primary" icon="play" onClick={run} disabled={!!progress}>Check now</Button>}>
                Each page is read as it is saved: no visitor sees anything, nothing changes.
              </EmptyState>
            ) : (
              <ul className="uncoder-ui-checks__pages">
                {sorted.map((r) => (
                  <li key={r.id} className={open === r.id ? 'is-open' : undefined}>
                    <button type="button" className="uncoder-ui-checks__row" aria-expanded={open === r.id} onClick={() => setOpen(open === r.id ? null : r.id)}>
                      <Icon name={open === r.id ? 'chevron-down' : 'chevron-right'} size={14} />
                      <span className="uncoder-ui-checks__title">
                        {r.title || '(no title)'}
                        <span className="uncoder-ui-muted"> · {r.type}</span>
                      </span>
                      <span className="uncoder-ui-checks__counts">
                        {count(r.issues, 'error') > 0 && <Badge tone="danger">{count(r.issues, 'error')} to fix</Badge>}
                        {count(r.issues, 'warning') > 0 && <Badge tone="warning">{count(r.issues, 'warning')} to improve</Badge>}
                        {r.issues.length === 0 && <span className="uncoder-ui-muted">No issues</span>}
                      </span>
                      <Badge tone={scoreTone(r.score)}>{r.score}</Badge>
                    </button>
                    {open === r.id && (
                      <div className="uncoder-ui-checks__detail">
                        {r.issues.length === 0 ? (
                          <p className="uncoder-ui-muted">Nothing to change on this page.</p>
                        ) : (
                          <ul>
                            {[...r.issues]
                              .sort((a, b) => ['error', 'warning', 'info'].indexOf(a.severity) - ['error', 'warning', 'info'].indexOf(b.severity))
                              .map((i, n) => (
                                <li key={n}>
                                  <Badge tone={SEVERITY[i.severity].tone}>{SEVERITY[i.severity].label}</Badge>
                                  <span>
                                    {i.message}
                                    <span className="uncoder-ui-muted">
                                      {' '}
                                      · {i.group}
                                      {i.count > 1 ? ` · ${plural(i.count, 'place', 'places')}` : ''}
                                    </span>
                                    {i.fix && <span className="uncoder-ui-checks__fix">{i.fix}</span>}
                                  </span>
                                </li>
                              ))}
                          </ul>
                        )}
                        <div className="uncoder-ui-checks__links">
                          <a className="uncoder-ui-btn uncoder-ui-btn--sm uncoder-ui-btn--secondary" href={r.edit}>
                            <Icon name="pencil" size={13} /> Edit
                          </a>
                          <a className="uncoder-ui-btn uncoder-ui-btn--sm uncoder-ui-btn--ghost" href={r.url} target="_blank" rel="noreferrer">
                            <Icon name="external-link" size={13} /> View
                          </a>
                        </div>
                      </div>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </Card>

          {agency && <ReportsCard key={reportsKey} />}
          {agency && <BrandCard />}
        </div>
      )}
      {canReport && (
        <CreateReportDialog
          open={reporting}
          results={results}
          site={site ?? []}
          onClose={() => setReporting(false)}
          onCreated={() => {
            setReporting(false);
            setReportsKey((k) => k + 1);
          }}
        />
      )}
    </>
  );
}

function Stat({ n, label }: { n: number; label: string }) {
  return (
    <div className="uncoder-ui-checks__stat">
      <strong>{n}</strong>
      <span>{label}</span>
    </div>
  );
}

/* ------------------------------------------------------------------ Reports (Agency) */

function ReportsCard() {
  const [list, setList] = useState<{ reports: Report[]; allowed: boolean; pricing: string } | null>(null);
  const [error, setError] = useState<Error | null>(null);
  const [busy, setBusy] = useState<number | null>(null);
  const load = () => api<{ reports: Report[]; allowed: boolean; pricing: string }>('reports').then(setList).catch(setError);
  useEffect(() => void load(), []);

  const copy = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url);
      toast('Link copied.', 'success');
    } catch {
      window.prompt('Copy the link', url);
    }
  };
  const relink = async (r: Report) => {
    if (!(await confirmDialog({ title: 'Make a new link?', body: 'The current link stops working. Anyone you sent it to needs the new one.', confirmLabel: 'New link' }))) return;
    setBusy(r.id);
    try {
      const res = await api<{ url: string }>(`reports/${r.id}/link`, { method: 'POST', body: {} });
      setList((l) => (l ? { ...l, reports: l.reports.map((x) => (x.id === r.id ? { ...x, url: res.url } : x)) } : l));
      await copy(res.url);
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(null);
    }
  };
  const remove = async (r: Report) => {
    if (!(await confirmDialog({ title: `Delete “${r.title}”?`, body: 'Its link stops working.', confirmLabel: 'Delete', danger: true }))) return;
    setBusy(r.id);
    try {
      await api(`reports/${r.id}`, { method: 'DELETE' });
      setList((l) => (l ? { ...l, reports: l.reports.filter((x) => x.id !== r.id) } : l));
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(null);
    }
  };

  return (
    <Card
      flush
      title={
        <>
          Reports {list && !list.allowed && <Badge tone="accent">Agency</Badge>}
        </>
      }
      description="Branded reports for clients: a page of their own behind a private link, ready to print or save as PDF."
    >
      {error ? (
        <ErrorState error={error} onRetry={load} />
      ) : !list ? (
        <SkeletonRows rows={2} cols={3} />
      ) : list.reports.length === 0 ? (
        <EmptyState
          icon="file-chart-column"
          title={list.allowed ? 'No reports yet' : 'Branded reports come with the Agency licence'}
          action={
            list.allowed ? undefined : (
              <a className="uncoder-ui-btn uncoder-ui-btn--primary" href={list.pricing} target="_blank" rel="noopener noreferrer">
                See the plans
              </a>
            )
          }
        >
          {list.allowed ? 'Check the pages, then choose “Create report”.' : 'Turn the checks into a report under your name, logo and colour, with a private link for your client.'}
        </EmptyState>
      ) : (
        <ul className="uncoder-ui-recent">
          {list.reports.map((r) => (
            <li key={r.id} className="uncoder-ui-recent__row" aria-busy={busy === r.id}>
              <span className="uncoder-ui-recent__icon" aria-hidden>
                <Icon name="file-chart-column" size={15} />
              </span>
              <div className="uncoder-ui-recent__main">
                <span className="uncoder-ui-recent__title">{r.title}</span>
                <span className="uncoder-ui-recent__meta">
                  Score {r.score} · {plural(r.pages, 'page', 'pages')} · {plural(r.issues, 'point', 'points')} · <time title={absoluteTime(r.created)}>{relativeTime(r.created)}</time>
                </span>
              </div>
              <div className="uncoder-ui-rowactions">
                <a className="uncoder-ui-btn uncoder-ui-btn--sm uncoder-ui-btn--secondary" href={r.url} target="_blank" rel="noreferrer">
                  <Icon name="external-link" size={13} /> Open
                </a>
                <Button size="sm" icon="link" onClick={() => void copy(r.url)}>
                  Copy link
                </Button>
                <IconButton icon="refresh-cw" label="Make a new link (the old one stops working)" disabled={busy !== null} onClick={() => void relink(r)} />
                <IconButton icon="trash-2" tone="danger" label={`Delete “${r.title}”`} disabled={busy !== null} onClick={() => void remove(r)} />
              </div>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}

function CreateReportDialog({ open, results, site, onClose, onCreated }: { open: boolean; results: PageResult[]; site: SiteCheck[]; onClose: () => void; onCreated: () => void }) {
  const [title, setTitle] = useState('');
  const [client, setClient] = useState('');
  const [build, setBuild] = useState(false);
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    if (open) {
      setTitle(`Website check · ${cfg.site.name}`);
      setClient(cfg.site.name);
    }
  }, [open]);

  const create = async () => {
    setBusy(true);
    try {
      // Element ids stay here: the report only needs what people read.
      const pages = results.map((r) => ({ id: r.id, issues: r.issues.map(({ elements: _e, ...i }) => i) }));
      const res = await api<{ id: number; url: string }>('reports', { body: { title, client, build, pages, site } });
      toast('The report is ready.', 'success', { label: 'Open', run: () => window.open(res.url, '_blank', 'noreferrer') }, 8000);
      onCreated();
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog
      open={open}
      onClose={() => !busy && onClose()}
      title="Create report"
      description={`From the checks of ${plural(results.length, 'page', 'pages')}, with your report branding below.`}
      width={520}
      footer={
        <>
          <Button onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button variant="primary" icon="file-chart-column" loading={busy} disabled={!title.trim()} onClick={create}>
            Create report
          </Button>
        </>
      }
    >
      <div className="uncoder-ui-stack">
        <label className="uncoder-ui-fld">
          <span className="uncoder-ui-fld__label">Title</span>
          <input className="uncoder-ui-input" value={title} maxLength={160} onChange={(e) => setTitle(e.currentTarget.value)} />
        </label>
        <label className="uncoder-ui-fld">
          <span className="uncoder-ui-fld__label">Prepared for</span>
          <input className="uncoder-ui-input" value={client} maxLength={120} onChange={(e) => setClient(e.currentTarget.value)} />
        </label>
        <Checkbox checked={build} onChange={setBuild} label="Include build-quality notes" description="Nesting depth and empty boxes: useful for your team, usually not for clients." />
      </div>
    </Dialog>
  );
}

function BrandCard() {
  const [saved, setSaved] = useState<Brand | null>(null);
  const [draft, setDraft] = useState<Brand | null>(null);
  const [busy, setBusy] = useState(false);
  useEffect(() => {
    api<Brand>('reports/brand')
      .then((b) => {
        setSaved(b);
        setDraft(b);
      })
      .catch(() => undefined);
  }, []);
  if (!draft || !saved) return null;
  const locked = !draft.allowed;
  const dirty = JSON.stringify(draft) !== JSON.stringify(saved);
  const set = <K extends keyof Brand>(k: K, v: Brand[K]) => setDraft({ ...draft, [k]: v });

  const save = async () => {
    setBusy(true);
    try {
      const res = await api<Brand>('reports/brand', { body: { name: draft.name, logo: draft.logo.id, accent: draft.accent, contact: draft.contact, intro: draft.intro } });
      setSaved(res);
      setDraft(res);
      toast('Report branding saved. New reports use it; earlier reports keep theirs.', 'success');
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Card
      title={
        <>
          Report branding {locked && <Badge tone="accent">Agency</Badge>}
        </>
      }
      description="Your name, logo and colour on every report. Empty fields use your white-label brand."
    >
      {locked && (
        <Callout tone="info" title="Branded reports come with the Agency licence">
          Activate an Agency licence under Settings → Licence to set your report branding.
        </Callout>
      )}
      <SettingRow title="Agency name" htmlFor="uncoder-ui-rb-name">
        <input id="uncoder-ui-rb-name" className="uncoder-ui-input" value={draft.name} maxLength={80} placeholder={draft.fallback.name || 'Your agency'} disabled={locked} onChange={(e) => set('name', e.currentTarget.value)} />
      </SettingRow>
      <SettingRow title="Logo" description="Shown on a white tile at the top of the report.">
        <div className="uncoder-ui-wl-image">
          <span className="uncoder-ui-wl-image__preview is-wide">{draft.logo.url || draft.fallback.logo ? <img src={draft.logo.url || draft.fallback.logo} alt="" /> : <span className="uncoder-ui-muted">None</span>}</span>
          <Button
            size="sm"
            icon="image"
            disabled={locked}
            onClick={async () => {
              const img = await pickImage('Report logo');
              if (img) set('logo', img);
            }}
          >
            {draft.logo.id ? 'Replace' : 'Choose'}
          </Button>
          {draft.logo.id > 0 && (
            <Button size="sm" variant="ghost" disabled={locked} onClick={() => set('logo', { id: 0, url: '' })}>
              Remove
            </Button>
          )}
        </div>
      </SettingRow>
      <SettingRow title="Colour" description="The report’s header and links." htmlFor="uncoder-ui-rb-accent">
        <div className="uncoder-ui-checks__color">
          <input id="uncoder-ui-rb-accent" type="color" value={/^#[0-9a-f]{6}$/i.test(draft.accent) ? draft.accent : '#083241'} disabled={locked} onChange={(e) => set('accent', e.currentTarget.value)} aria-label="Report colour" />
          <code>{draft.accent}</code>
        </div>
      </SettingRow>
      <SettingRow title="Contact line" description="At the bottom of the report." htmlFor="uncoder-ui-rb-contact">
        <input id="uncoder-ui-rb-contact" className="uncoder-ui-input" value={draft.contact} maxLength={160} placeholder="hello@youragency.com · +1 555 0100" disabled={locked} onChange={(e) => set('contact', e.currentTarget.value)} />
      </SettingRow>
      <SettingRow title="Introduction" description="A few words at the top of every report." htmlFor="uncoder-ui-rb-intro">
        <textarea id="uncoder-ui-rb-intro" className="uncoder-ui-input uncoder-ui-checks__intro" rows={3} value={draft.intro} maxLength={1200} disabled={locked} onChange={(e) => set('intro', e.currentTarget.value)} />
      </SettingRow>
      {!locked && (
        <div className="uncoder-ui-wl-actions">
          <Button variant="primary" loading={busy} disabled={!dirty} onClick={save}>
            Save
          </Button>
        </div>
      )}
    </Card>
  );
}

