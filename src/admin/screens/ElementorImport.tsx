// Settings → Import & export: convert Elementor pages, templates and Site Settings into Uncoder designs
// (Site\Elementor_Import). The Elementor data is never changed, so every conversion can be compared or undone.
import { useMemo, useRef, useState, type DragEvent } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button, Segmented, Toggle } from '@editor/ui/primitives';
import { NAME } from '@shared/brand';
import { api, ApiError } from '../lib/api';
import { cfg } from '../lib/config';
import { cx, relativeTime } from '../lib/format';
import { useResource } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { Badge, Callout, Card, ErrorState, PostStatus, SkeletonRows } from '../ui/kit';
import '../elementor-import.css';

/* ------------------------------------------------------------------ Server shapes (elementor-import/*) */

interface Copy {
  id: number;
  title: string;
  status: string;
  editUrl: string;
}

interface ScanItem {
  id: number;
  title: string;
  type: string;
  typeLabel: string;
  /** Elementor template type (header, footer, loop-item…) for library items. */
  template: string;
  /** Uncoder template type it becomes. */
  templateAs: string;
  status: string;
  modified: string;
  /** The post already has an Uncoder design (replace would overwrite it). */
  uncoder: boolean;
  replaceable: boolean;
  copyable: boolean;
  elements: number;
  copies: Copy[];
  viewUrl: string;
  editUrl: string;
}

interface Scan {
  items: ScanItem[];
  kit: { exists: boolean; colors: number; fonts: number; imported: boolean; canImport: boolean };
  elementor: boolean;
  canUpload: boolean;
  maxUpload: number;
}

interface Count {
  name: string;
  count: number;
}

interface Report {
  elements: number;
  converted: Count[];
  unmapped: Count[];
  settings: Count[];
  notes: Count[];
  remote: string[];
  errors: string[];
}

interface ConvertItem {
  id: number;
  title: string;
  ok: boolean;
  message?: string;
  mode?: 'copy' | 'replace' | 'template';
  target?: number;
  targetTitle?: string;
  editUrl?: string;
  viewUrl?: string;
  report?: Report;
}

interface KitResult {
  ok: boolean;
  message?: string;
  colors?: number;
  typography?: number;
  fonts?: string[];
  sections?: string[];
  errors?: string[];
}

interface ConvertResponse {
  kit: KitResult | null;
  items: ConvertItem[];
}

interface UploadItem {
  title: string;
  ok: boolean;
  message?: string;
  id?: number;
  type?: string;
  typeLabel?: string;
  editUrl?: string;
  report?: Report;
}

type Mode = 'copy' | 'replace';

const TEMPLATE_LABEL: Record<string, string> = {
  header: 'Header',
  footer: 'Footer',
  'single-post': 'Single post',
  'single-page': 'Single page',
  single: 'Single',
  archive: 'Archive',
  'search-results': 'Search results',
  'error-404': '404 page',
  popup: 'Popup',
  'loop-item': 'Loop item',
  section: 'Section',
  container: 'Container',
  page: 'Page',
  widget: 'Global widget',
  product: 'Product',
  'product-archive': 'Product archive',
};

const mb = (n: number) => (n >= 1048576 ? `${(n / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);
const plural = (n: number, one: string, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;

/** Loop items and popups first, then other templates, then pages: pages that use them are remapped. */
const rank = (item: ScanItem) => (item.type !== 'elementor_library' ? 3 : item.template === 'loop-item' || item.template === 'popup' ? 1 : 2);

export function ElementorImportCard() {
  const scan = useResource<Scan>((signal) => api<Scan>('elementor-import/scan', { signal }));
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [kitChoice, setKitChoice] = useState<boolean | null>(null);
  const [mode, setMode] = useState<Mode>('copy');
  const [progress, setProgress] = useState<{ done: number; total: number; title: string } | null>(null);
  const [results, setResults] = useState<ConvertItem[]>([]);
  const [kitResult, setKitResult] = useState<KitResult | null>(null);

  const data = scan.data;
  const items = data?.items ?? [];
  const kitAvailable = !!data?.kit.exists && !!data?.kit.canImport;
  // Default: import the kit the first time, when it exists and this user may change the Design System.
  const kit = kitChoice ?? (kitAvailable && !data?.kit.imported);
  const chosen = useMemo(() => items.filter((i) => selected.has(i.id)), [items, selected]);
  const allSelected = items.length > 0 && chosen.length === items.length;
  const overwrites = mode === 'replace' ? chosen.filter((i) => i.replaceable && i.uncoder).length : 0;

  const toggle = (id: number, on: boolean) =>
    setSelected((s) => {
      const next = new Set(s);
      if (on) next.add(id);
      else next.delete(id);
      return next;
    });

  const run = async () => {
    const queue = [...chosen].sort((a, b) => rank(a) - rank(b));
    setResults([]);
    setKitResult(null);
    let kitPending = kit && kitAvailable;
    const done: ConvertItem[] = [];
    try {
      for (let i = 0; i < queue.length; i++) {
        setProgress({ done: i, total: queue.length, title: queue[i].title });
        // One item per request: long pages never hit the server's time limit, and progress stays visible.
        const res = await api<ConvertResponse>('elementor-import/convert', { body: { ids: [queue[i].id], kit: kitPending, mode } });
        if (kitPending) {
          setKitResult(res.kit);
          kitPending = false;
        }
        done.push(...res.items);
        setResults([...done]);
      }
      if (kit && kitAvailable && !queue.length) {
        const res = await api<ConvertResponse>('elementor-import/convert', { body: { ids: [], kit: true, mode } });
        setKitResult(res.kit);
      }
      const ok = done.filter((d) => d.ok).length;
      toast(queue.length ? `Converted ${plural(ok, 'item')}${ok < done.length ? `, ${done.length - ok} failed` : ''}` : 'Design System updated');
      setSelected(new Set());
      await scan.reload();
    } catch (e) {
      toastError(e);
    } finally {
      setProgress(null);
    }
  };

  return (
    <>
      <Card
        flush
        className="uncoder-ui-elimp"
        title="Import from Elementor"
        description={`Rebuild pages and templates made with Elementor as ${NAME} designs. Elementor’s own data is left exactly as it is, so you can compare both versions or switch back at any time.`}
        actions={
          <Button size="sm" variant="ghost" icon="refresh-cw" onClick={() => scan.reload()} disabled={scan.loading || !!progress}>
            Rescan
          </Button>
        }
      >
        {scan.error && !data ? (
          <div className="uncoder-ui-elimp__pad">
            <ErrorState error={scan.error} onRetry={() => scan.reload()} />
          </div>
        ) : !data ? (
          <SkeletonRows rows={4} cols={4} />
        ) : (
          <>
            {items.length === 0 ? (
              <div className="uncoder-ui-elimp__empty">
                <Icon name="search" size={20} />
                <strong>No Elementor designs on this site</strong>
                <span>Pages, posts and templates edited with Elementor show up here. You can still import exported Elementor templates below.</span>
              </div>
            ) : (
              <div className="uncoder-ui-tablewrap">
                <table className="uncoder-ui-table uncoder-ui-elimp__table">
                  <thead>
                    <tr>
                      <th className="uncoder-ui-elimp__check">
                        <input
                          type="checkbox"
                          className="uncoder-ui-check"
                          aria-label="Select all"
                          checked={allSelected}
                          disabled={!!progress}
                          onChange={(e) => setSelected(e.currentTarget.checked ? new Set(items.map((i) => i.id)) : new Set())}
                        />
                      </th>
                      <th>Title</th>
                      <th>Type</th>
                      <th className="uncoder-ui-col-status">Status</th>
                      <th>In {NAME}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {items.map((item) => (
                      <tr key={item.id} className={cx(selected.has(item.id) && 'is-selected')}>
                        <td className="uncoder-ui-elimp__check">
                          <input type="checkbox" className="uncoder-ui-check" aria-label={`Select ${item.title}`} checked={selected.has(item.id)} disabled={!!progress || !item.copyable} onChange={(e) => toggle(item.id, e.currentTarget.checked)} />
                        </td>
                        <td>
                          <div className="uncoder-ui-elimp__title">
                            {item.viewUrl ? (
                              <a href={item.viewUrl} target="_blank" rel="noreferrer">
                                {item.title}
                              </a>
                            ) : (
                              <span>{item.title}</span>
                            )}
                            <span className="uncoder-ui-muted">
                              {plural(item.elements, 'element')} · edited {relativeTime(item.modified)}
                            </span>
                          </div>
                        </td>
                        <td>
                          <span className="uncoder-ui-elimp__type">
                            {item.typeLabel}
                            {item.template && (
                              <Badge tone="info" title={`Becomes a ${TEMPLATE_LABEL[item.templateAs] ?? item.templateAs} template in ${NAME}`}>
                                {TEMPLATE_LABEL[item.template] ?? item.template}
                              </Badge>
                            )}
                          </span>
                          {!item.copyable && <span className="uncoder-ui-muted uncoder-ui-elimp__why">Enable {NAME} for this post type first</span>}
                        </td>
                        <td className="uncoder-ui-col-status">
                          <PostStatus status={item.status} />
                        </td>
                        <td>
                          <div className="uncoder-ui-elimp__copies">
                            {item.uncoder && (
                              <a className="uncoder-ui-elimp__copy" href={item.editUrl}>
                                <Icon name="replace" size={13} /> Replaced in place
                              </a>
                            )}
                            {item.copies.map((c) => (
                              <a key={c.id} className="uncoder-ui-elimp__copy" href={c.editUrl} title={c.title}>
                                <Icon name={item.template ? 'layout-template' : 'files'} size={13} /> {item.template ? 'Template' : 'Copy'} #{c.id}
                              </a>
                            ))}
                            {!item.uncoder && !item.copies.length && <span className="uncoder-ui-muted">Not converted</span>}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <div className="uncoder-ui-elimp__options">
              <div className="uncoder-ui-elimp__option">
                <Toggle checked={kit && kitAvailable} disabled={!kitAvailable || !!progress} onChange={(v) => setKitChoice(v)} label="Also import the Elementor global colors & fonts" />
                <div className="uncoder-ui-elimp__optiontext">
                  <strong>Also import the Elementor global colors &amp; fonts</strong>
                  <span className="uncoder-ui-muted">
                    {!data.kit.exists
                      ? 'This site has no Elementor Site Settings.'
                      : !data.kit.canImport
                        ? 'Only administrators can change the Design System.'
                        : `${plural(data.kit.colors, 'color')} and ${plural(data.kit.fonts, 'font')}, plus theme colors, buttons, form fields and the container width. Converted elements then use your Design System. A restore point is kept.${data.kit.imported ? ' Already imported once.' : ''}`}
                  </span>
                </div>
              </div>
              <div className="uncoder-ui-elimp__option">
                <Segmented
                  ariaLabel="How to convert"
                  value={mode}
                  onChange={(v) => setMode(v as Mode)}
                  options={[
                    { value: 'copy', label: 'Create copies' },
                    { value: 'replace', label: 'Replace in place' },
                  ]}
                />
                <span className="uncoder-ui-muted">
                  {mode === 'copy'
                    ? `Each page becomes a new draft named “… (${NAME})”. The original stays live.`
                    : `The page itself switches to its ${NAME} design (same address). Its Elementor data is kept to go back.`}{' '}
                  Templates always become new {NAME} templates.
                </span>
              </div>
            </div>

            {overwrites > 0 && (
              <div className="uncoder-ui-elimp__pad">
                <Callout tone="warning" title={`${plural(overwrites, 'page')} already ${overwrites === 1 ? 'has' : 'have'} a design in ${NAME}`}>
                  Replacing converts them again and overwrites that design (revisions keep the previous one).
                </Callout>
              </div>
            )}

            <div className="uncoder-ui-kitactions uncoder-ui-elimp__pad">
              <Button variant="primary" icon="arrow-right" loading={!!progress} disabled={!chosen.length && !(kit && kitAvailable)} onClick={run}>
                {chosen.length ? `Convert ${plural(chosen.length, 'item')}` : 'Import colors & fonts'}
              </Button>
              {progress && (
                <span className="uncoder-ui-elimp__progress" role="status">
                  <span className="uncoder-ui-elimp__bar">
                    <span style={{ width: `${Math.round((progress.done / Math.max(1, progress.total)) * 100)}%` }} />
                  </span>
                  Converting {progress.done + 1} of {progress.total}: {progress.title}
                </span>
              )}
            </div>

            {(kitResult || results.length > 0) && (
              <div className="uncoder-ui-elimp__results uncoder-ui-elimp__pad">
                {kitResult && <KitSummary result={kitResult} />}
                {results.map((r) => (
                  <ResultRow key={`${r.id}-${r.target ?? 0}`} title={r.title} ok={r.ok} message={r.message} report={r.report} kind={r.mode === 'template' ? `${NAME} template` : r.mode === 'replace' ? 'Replaced in place' : 'Draft copy'} editUrl={r.editUrl} viewUrl={r.viewUrl} />
                ))}
              </div>
            )}
          </>
        )}
      </Card>
      {data?.canUpload && <UploadCard maxUpload={data.maxUpload} onDone={() => scan.reload()} />}
    </>
  );
}

function KitSummary({ result }: { result: KitResult }) {
  if (!result.ok) {
    return (
      <Callout tone="warning" title="Colors & fonts were not imported">
        {result.message}
      </Callout>
    );
  }
  return (
    <Callout tone="success" title="Design System updated from Elementor">
      {plural(result.colors ?? 0, 'color')}, {plural(result.typography ?? 0, 'text style')}
      {result.fonts?.length ? `, fonts ${result.fonts.join(' and ')}` : ''}
      {result.sections?.length ? ` (also ${result.sections.join(', ').replace('custom_css', 'site CSS')})` : ''}. Your previous Design System was kept as a restore point (a connected AI assistant can restore it).
      {!!result.errors?.length && (
        <ul>
          {result.errors.slice(0, 6).map((e, i) => (
            <li key={i}>{e}</li>
          ))}
        </ul>
      )}
    </Callout>
  );
}

/** One converted item with what could not be converted. */
function ResultRow({ title, ok, message, report, kind, editUrl, viewUrl }: { title: string; ok: boolean; message?: string; report?: Report; kind: string; editUrl?: string; viewUrl?: string }) {
  const [open, setOpen] = useState(false);
  if (!ok || !report) {
    return (
      <div className="uncoder-ui-elimp__result is-failed">
        <Icon name="circle-alert" size={16} />
        <div className="uncoder-ui-elimp__resultmain">
          <strong>{title}</strong>
          <span className="uncoder-ui-muted">{message ?? 'Not converted.'}</span>
        </div>
      </div>
    );
  }
  const unmapped = report.unmapped.reduce((n, u) => n + u.count, 0);
  const attention = unmapped + report.settings.length + report.errors.length + (report.remote.length ? 1 : 0);
  return (
    <div className={cx('uncoder-ui-elimp__result', attention ? 'is-partial' : 'is-clean')}>
      <Icon name={attention ? 'triangle-alert' : 'circle-check'} size={16} />
      <div className="uncoder-ui-elimp__resultmain">
        <div className="uncoder-ui-elimp__resulthead">
          <strong>{title}</strong>
          <Badge tone={attention ? 'warning' : 'success'}>{kind}</Badge>
          <span className="uncoder-ui-muted">
            {plural(report.elements, 'element')}
            {unmapped ? ` · ${plural(unmapped, 'widget')} not converted` : ''}
            {report.settings.length ? ` · ${plural(report.settings.length, 'setting')} to check` : ''}
          </span>
          <span className="uncoder-ui-elimp__links">
            {editUrl && (
              <a href={editUrl}>
                <Icon name="pencil" size={13} /> Edit
              </a>
            )}
            {viewUrl && (
              <a href={viewUrl} target="_blank" rel="noreferrer">
                <Icon name="external-link" size={13} /> View
              </a>
            )}
            {(attention > 0 || report.notes.length > 0) && (
              <button type="button" className="uncoder-ui-elimp__more" aria-expanded={open} onClick={() => setOpen(!open)}>
                <Icon name={open ? 'chevron-down' : 'chevron-right'} size={13} /> Details
              </button>
            )}
          </span>
        </div>
        {open && <ReportDetails report={report} />}
      </div>
    </div>
  );
}

function ReportDetails({ report }: { report: Report }) {
  const notes = report.notes.filter((n) => !n.name.startsWith('Internal:'));
  return (
    <div className="uncoder-ui-elimp__details">
      {report.unmapped.length > 0 && (
        <section>
          <h4>Widgets that were not converted</h4>
          <p className="uncoder-ui-muted">They became empty HTML widgets named “Elementor: …” so you can find and rebuild them.</p>
          <ul className="uncoder-ui-elimp__chips">
            {report.unmapped.map((u) => (
              <li key={u.name}>
                {u.name}
                {u.count > 1 && <span>×{u.count}</span>}
              </li>
            ))}
          </ul>
        </section>
      )}
      {report.settings.length > 0 && (
        <section>
          <h4>Settings to check</h4>
          <ul className="uncoder-ui-elimp__list">
            {report.settings.map((s) => {
              const [widget, ...rest] = s.name.split(': ');
              return (
                <li key={s.name}>
                  <code>{widget}</code> {rest.join(': ')}
                  {s.count > 1 && <span className="uncoder-ui-muted"> ×{s.count}</span>}
                </li>
              );
            })}
          </ul>
        </section>
      )}
      {notes.length > 0 && (
        <section>
          <h4>Good to know</h4>
          <ul className="uncoder-ui-elimp__list">
            {notes.map((n) => (
              <li key={n.name}>{n.name}</li>
            ))}
          </ul>
        </section>
      )}
      {report.remote.length > 0 && (
        <section>
          <h4>Images still loaded from another site</h4>
          <p className="uncoder-ui-muted">Upload them to your media library and pick them again, or they break when that site goes away.</p>
          <ul className="uncoder-ui-elimp__list uncoder-ui-elimp__urls">
            {report.remote.map((u) => (
              <li key={u}>
                <a href={u} target="_blank" rel="noreferrer">
                  {u}
                </a>
              </li>
            ))}
          </ul>
        </section>
      )}
      {report.errors.length > 0 && (
        <section>
          <h4>Dropped while saving</h4>
          <ul className="uncoder-ui-elimp__list">
            {report.errors.map((e, i) => (
              <li key={i}>{e}</li>
            ))}
          </ul>
        </section>
      )}
    </div>
  );
}

function UploadCard({ maxUpload, onDone }: { maxUpload: number; onDone: () => void }) {
  const input = useRef<HTMLInputElement>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [over, setOver] = useState(false);
  const [items, setItems] = useState<UploadItem[]>([]);

  const upload = async (files: File[]) => {
    const done: UploadItem[] = [];
    try {
      for (const file of files) {
        if (!/\.(json|zip)$/i.test(file.name)) {
          done.push({ title: file.name, ok: false, message: 'Only Elementor template exports (.json) or a .zip of them.' });
          continue;
        }
        setBusy(`Converting ${file.name}…`);
        const form = new FormData();
        form.append('file', file);
        const res = await fetch(`${cfg.rest.root}elementor-import/upload`, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.rest.nonce }, body: form });
        const data = await res.json().catch(() => null);
        if (!res.ok) {
          done.push({ title: file.name, ok: false, message: data?.message ?? new ApiError(`Upload failed (${res.status})`, res.status).message });
          continue;
        }
        done.push(...((data?.items ?? []) as UploadItem[]));
      }
      setItems(done);
      const ok = done.filter((d) => d.ok).length;
      if (ok) toast(`Created ${plural(ok, 'template')}`);
      onDone();
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(null);
      if (input.current) input.current.value = '';
    }
  };

  const drop = (e: DragEvent<HTMLLabelElement>) => {
    e.preventDefault();
    setOver(false);
    if (!busy && e.dataTransfer.files.length) upload([...e.dataTransfer.files]);
  };

  return (
    <Card
      title="Import Elementor templates"
      description={`Template files exported from Elementor (Templates → Saved Templates → Export) on this or another site. Headers, footers, singles, archives, popups and loop items keep their type; everything else becomes a section template. Images from other sites keep their address.${maxUpload ? ` Largest file this server accepts: ${mb(maxUpload)}.` : ''}`}
    >
      <label
        className={cx('uncoder-ui-kitdrop', 'uncoder-ui-elimp__drop', busy && 'is-busy', over && 'is-over')}
        onDragOver={(e) => {
          e.preventDefault();
          setOver(true);
        }}
        onDragLeave={() => setOver(false)}
        onDrop={drop}
      >
        <input ref={input} type="file" accept=".json,.zip,application/json,application/zip" multiple disabled={!!busy} onChange={(e) => e.currentTarget.files?.length && upload([...e.currentTarget.files])} />
        <Icon name={busy ? 'loader' : 'file-json'} size={22} />
        <strong>{busy ?? 'Choose Elementor template files (.json or .zip)'}</strong>
        <span>or drop them here</span>
      </label>
      {items.length > 0 && (
        <div className="uncoder-ui-elimp__results">
          {items.map((item, i) => (
            <ResultRow key={`${item.id ?? 'x'}-${i}`} title={item.title} ok={item.ok} message={item.message} report={item.report} kind={`${item.typeLabel ?? 'Template'} template`} editUrl={item.editUrl} />
          ))}
        </div>
      )}
    </Card>
  );
}
