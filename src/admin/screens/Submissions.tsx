import { useEffect, useMemo, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button } from '@editor/ui/primitives';
import { NAME } from '@shared/brand';
import { api, type Submission, type SubmissionList } from '../lib/api';
import { can } from '../lib/config';
import { absoluteTime, cx, relativeTime } from '../lib/format';
import { useDebounced, useResource } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { confirmDialog, Drawer } from '../ui/Dialog';
import { Badge, Card, EmptyState, ErrorState, PageHeader, Pager, SearchInput, SkeletonRows, Tabs } from '../ui/kit';

type StatusFilter = 'all' | 'unread' | 'read' | 'spam';
const PER_PAGE = 25;

interface FieldRow {
  label: string;
  value: string;
  /** Admin download link of an uploaded file (only for users who may download form files). */
  url?: string;
}

const KNOWN: Record<string, string> = { ip: 'IP address', user_agent: 'User agent', page_url: 'Page URL', page_id: 'Page ID', user_id: 'User ID', referrer: 'Referrer', referer: 'Referrer', url: 'URL' };

const humanize = (key: string) => {
  if (KNOWN[key]) return KNOWN[key];
  const s = key.replace(/[_-]+/g, ' ').trim();
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : key;
};

function stringify(v: unknown): string {
  if (v === null || v === undefined) return '';
  if (typeof v === 'boolean') return v ? 'Yes' : 'No';
  if (Array.isArray(v)) return v.map(stringify).filter(Boolean).join(', ');
  if (typeof v === 'object') {
    const o = v as Record<string, unknown>;
    if ('value' in o) return stringify(o.value);
    if ('url' in o) return stringify(o.url);
    return JSON.stringify(v);
  }
  return String(v);
}

/** Meta values: nested objects become "key: value" pairs (e.g. actions → "email: sent"). */
function metaValue(v: unknown): string {
  if (v && typeof v === 'object' && !Array.isArray(v)) {
    return Object.entries(v as Record<string, unknown>)
      .map(([k, x]) => {
        const o = x as Record<string, unknown> | null;
        const inner = o && typeof o === 'object' && !Array.isArray(o) && 'status' in o ? stringify(o.status) : stringify(x);
        return `${humanize(k)}: ${inner}`;
      })
      .join(' · ');
  }
  return stringify(v);
}

/** Submission data comes as { key: value }, { key: { label, value } } or [{ label, value }]; normalize to rows. */
export function fieldRows(data: Submission['data']): FieldRow[] {
  const out: FieldRow[] = [];
  if (Array.isArray(data)) {
    data.forEach((item, i) => {
      if (item && typeof item === 'object' && !Array.isArray(item)) {
        const o = item as Record<string, unknown>;
        out.push({ label: String(o.label ?? o.name ?? o.id ?? `Field ${i + 1}`), value: stringify('value' in o ? o.value : o), url: typeof o.fileUrl === 'string' ? o.fileUrl : undefined });
      } else out.push({ label: `Field ${i + 1}`, value: stringify(item) });
    });
    return out;
  }
  for (const [key, v] of Object.entries(data ?? {})) {
    if (key.startsWith('_')) continue;
    if (v && typeof v === 'object' && !Array.isArray(v) && 'value' in (v as object)) {
      const o = v as Record<string, unknown>;
      out.push({ label: String(o.label || humanize(key)), value: stringify(o.value) });
    } else out.push({ label: humanize(key), value: stringify(v) });
  }
  return out;
}

const preview = (s: Submission) =>
  fieldRows(s.data)
    .map((f) => f.value)
    .filter(Boolean)
    .slice(0, 3)
    .join(' · ') || 'Empty submission';

const isEmail = (v: string) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
const isUrl = (v: string) => /^https?:\/\/\S+$/i.test(v);

/* ------------------------------------------------------------------ CSV */

const csvCell = (v: string) => {
  // Neutralize spreadsheet formulas (CSV injection), then quote.
  const safe = /^[=+\-@\t\r]/.test(v) ? `'${v}` : v;
  return `"${safe.replace(/"/g, '""')}"`;
};

function toCsv(items: Submission[]): string {
  const fieldCols: string[] = [];
  const metaCols: string[] = [];
  const rows = items.map((s) => {
    const f = fieldRows(s.data);
    // Uploaded files get a second column with the admin download link (the file itself stays protected).
    const cells = f.flatMap((r) => (r.url ? [[r.label, r.value], [`${r.label} (download link)`, r.url]] : [[r.label, r.value]]));
    for (const [label] of cells) if (!fieldCols.includes(label)) fieldCols.push(label);
    for (const k of Object.keys(s.meta ?? {})) if (!metaCols.includes(k)) metaCols.push(k);
    return { s, f: new Map(cells as Array<[string, string]>) };
  });
  const head = ['ID', 'Date (UTC)', 'Status', 'Form', 'Page', 'Page URL', ...fieldCols, ...metaCols.map((k) => `meta: ${k}`)];
  const lines = [head.map(csvCell).join(',')];
  for (const { s, f } of rows) {
    lines.push(
      [String(s.id), s.createdAt, s.status, s.form, s.postTitle, s.postUrl, ...fieldCols.map((c) => f.get(c) ?? ''), ...metaCols.map((k) => metaValue(s.meta?.[k]))].map(csvCell).join(','),
    );
  }
  return '\ufeff' + lines.join('\r\n');
}

/* ------------------------------------------------------------------ Screen */

export function SubmissionsScreen() {
  const [status, setStatus] = useState<StatusFilter>('all');
  const [form, setForm] = useState('');
  const [postId, setPostId] = useState(0);
  const [query, setQuery] = useState('');
  const search = useDebounced(query, 300);
  const [page, setPage] = useState(1);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [open, setOpen] = useState<Submission | null>(null);
  const [exporting, setExporting] = useState(false);

  const params = useMemo(() => {
    const p = new URLSearchParams({ page: String(page), per_page: String(PER_PAGE) });
    if (status !== 'all') p.set('status', status);
    if (form) p.set('form', form);
    if (postId) p.set('post_id', String(postId));
    if (search.trim()) p.set('search', search.trim());
    return p;
  }, [status, form, postId, search, page]);

  const list = useResource((signal) => api<SubmissionList>(`submissions?${params.toString()}`, { signal }), [params.toString()]);
  const d = list.data;
  const admin = can('manage_options');

  useEffect(() => setSelected(new Set()), [params.toString()]);
  useEffect(() => setPage(1), [search]);
  const filterBy = <T,>(set: (v: T) => void) => (v: T) => {
    set(v);
    setPage(1);
  };

  const formNames = useMemo(() => Array.from(new Set((d?.forms ?? []).map((f) => f.form))).filter(Boolean), [d?.forms]);
  const pages = useMemo(() => {
    const m = new Map<number, string>();
    for (const f of d?.forms ?? []) if (f.postId) m.set(f.postId, f.postTitle || `#${f.postId}`);
    return Array.from(m.entries());
  }, [d?.forms]);

  const patchItem = (s: Submission) => list.setData((prev) => (prev ? { ...prev, items: prev.items.map((x) => (x.id === s.id ? s : x)) } : prev));

  const setItemStatus = async (s: Submission, next: Submission['status'], quiet = false) => {
    try {
      const saved = await api<Submission>(`submissions/${s.id}`, { body: { status: next } });
      patchItem(saved);
      setOpen((cur) => (cur && cur.id === saved.id ? saved : cur));
      if (!quiet) toast(next === 'spam' ? 'Marked as spam' : next === 'read' ? 'Marked as read' : 'Marked as unread');
      list.reload();
    } catch (e) {
      toastError(e);
    }
  };

  const remove = async (ids: number[]) => {
    const ok = await confirmDialog({
      title: ids.length === 1 ? 'Delete this submission?' : `Delete ${ids.length} submissions?`,
      body: 'The data is permanently removed from the database. Export it first if you need a copy.',
      confirmLabel: 'Delete permanently',
      danger: true,
    });
    if (!ok) return;
    try {
      if (ids.length === 1) await api(`submissions/${ids[0]}`, { method: 'DELETE' });
      else await api('submissions/bulk', { body: { ids, action: 'delete' } });
      toast(ids.length === 1 ? 'Submission deleted' : `${ids.length} submissions deleted`);
      setSelected(new Set());
      if (open && ids.includes(open.id)) setOpen(null);
      list.reload();
    } catch (e) {
      toastError(e);
    }
  };

  const bulk = async (action: 'read' | 'unread' | 'spam') => {
    const ids = Array.from(selected);
    try {
      const res = await api<{ count: number }>('submissions/bulk', { body: { ids, action } });
      toast(`${res.count} submission${res.count === 1 ? '' : 's'} updated`);
      setSelected(new Set());
      list.reload();
    } catch (e) {
      toastError(e);
    }
  };

  const openDetail = (s: Submission) => {
    setOpen(s);
    if (s.status === 'unread') setItemStatus(s, 'read', true);
  };

  const exportCsv = async () => {
    setExporting(true);
    try {
      const p = new URLSearchParams(params);
      p.delete('page');
      p.delete('per_page');
      const res = await api<{ items: Submission[]; truncated: boolean }>(`submissions/export?${p.toString()}`);
      if (!res.items.length) {
        toast('Nothing to export with these filters', 'info');
        return;
      }
      const blob = new Blob([toCsv(res.items)], { type: 'text/csv;charset=utf-8' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = `submissions-${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(a);
      a.click();
      a.remove();
      window.setTimeout(() => URL.revokeObjectURL(a.href), 1000);
      toast(res.truncated ? `Exported the latest ${res.items.length} submissions` : `Exported ${res.items.length} submission${res.items.length === 1 ? '' : 's'}`);
    } catch (e) {
      toastError(e);
    } finally {
      setExporting(false);
    }
  };

  const items = d?.items ?? [];
  const allOnPage = items.length > 0 && items.every((i) => selected.has(i.id));
  const filtered = !!(form || postId || search);

  return (
    <>
      <PageHeader
        title="Form submissions"
        description={`Messages sent through ${NAME} forms on your site.`}
        actions={
          admin ? (
            <Button icon="download" onClick={exportCsv} loading={exporting} disabled={!d || d.total === 0}>
              Export CSV
            </Button>
          ) : undefined
        }
      />
      <Card flush>
        <div className="uncoder-ui-subhead">
          <Tabs<StatusFilter>
            idBase="uncoder-ui-subs"
            label="Filter by status"
            value={status}
            onChange={filterBy(setStatus)}
            tabs={[
              { id: 'all', label: 'All', count: d?.counts.all ?? null },
              { id: 'unread', label: 'Unread', count: d?.counts.unread ?? null },
              { id: 'read', label: 'Read', count: d?.counts.read ?? null },
              { id: 'spam', label: 'Spam', count: d?.counts.spam ?? null },
            ]}
          />
        </div>
        <div className="uncoder-ui-filters" id="uncoder-ui-subs-panel" role="tabpanel" aria-labelledby={`uncoder-ui-subs-tab-${status}`}>
          {selected.size > 0 ? (
            <div className="uncoder-ui-bulkbar">
              <span className="uncoder-ui-bulkbar__count">{selected.size} selected</span>
              <Button size="sm" onClick={() => bulk('read')}>
                Mark read
              </Button>
              <Button size="sm" onClick={() => bulk('unread')}>
                Mark unread
              </Button>
              {status !== 'spam' ? (
                <Button size="sm" onClick={() => bulk('spam')}>
                  Spam
                </Button>
              ) : (
                <Button size="sm" onClick={() => bulk('read')}>
                  Not spam
                </Button>
              )}
              {admin && (
                <Button size="sm" variant="ghost" className="uncoder-ui-btn--danger-ghost" icon="trash-2" onClick={() => remove(Array.from(selected))}>
                  Delete
                </Button>
              )}
              <Button size="sm" variant="ghost" onClick={() => setSelected(new Set())}>
                Clear selection
              </Button>
            </div>
          ) : (
            <>
              <select className="uncoder-ui-select uncoder-ui-select--sm" value={form} aria-label="Filter by form" onChange={(e) => filterBy(setForm)(e.currentTarget.value)}>
                <option value="">All forms</option>
                {formNames.map((f) => (
                  <option key={f} value={f}>
                    {f}
                  </option>
                ))}
              </select>
              <select className="uncoder-ui-select uncoder-ui-select--sm" value={postId} aria-label="Filter by page" onChange={(e) => filterBy(setPostId)(Number(e.currentTarget.value))}>
                <option value={0}>All pages</option>
                {pages.map(([id, title]) => (
                  <option key={id} value={id}>
                    {title}
                  </option>
                ))}
              </select>
              <SearchInput value={query} onChange={setQuery} placeholder="Search submissions…" width={260} />
            </>
          )}
        </div>
        {list.error && !d ? (
          <ErrorState error={list.error} onRetry={list.reload} />
        ) : !d ? (
          <SkeletonRows rows={6} cols={4} />
        ) : items.length === 0 ? (
          filtered || status !== 'all' ? (
            <EmptyState icon="inbox" title="No submissions match">
              {status === 'spam' ? 'Nothing in spam.' : 'Try other filters.'}
            </EmptyState>
          ) : (
            <EmptyState icon="inbox" title="No submissions yet">
              Add a Form widget to a page in the builder: every message your visitors send lands here.
            </EmptyState>
          )
        ) : (
          <div className="uncoder-ui-tablewrap">
            <table className="uncoder-ui-table uncoder-ui-table--subs">
              <thead>
                <tr>
                  <th scope="col" className="uncoder-ui-col-check">
                    <input
                      type="checkbox"
                      className="uncoder-ui-check"
                      aria-label="Select all on this page"
                      checked={allOnPage}
                      onChange={(e) => setSelected(e.currentTarget.checked ? new Set(items.map((i) => i.id)) : new Set())}
                    />
                  </th>
                  <th scope="col">Submission</th>
                  <th scope="col" className="uncoder-ui-col-hide-md">
                    Form
                  </th>
                  <th scope="col" className="uncoder-ui-col-hide-md">
                    Page
                  </th>
                  <th scope="col" className="uncoder-ui-col-date">
                    Received
                  </th>
                </tr>
              </thead>
              <tbody>
                {items.map((s) => (
                  <tr key={s.id} className={cx(s.status === 'unread' && 'is-unread', selected.has(s.id) && 'is-selected')}>
                    <td className="uncoder-ui-col-check">
                      <input
                        type="checkbox"
                        className="uncoder-ui-check"
                        aria-label={`Select submission ${s.id}`}
                        checked={selected.has(s.id)}
                        onChange={(e) => {
                          const next = new Set(selected);
                          if (e.currentTarget.checked) next.add(s.id);
                          else next.delete(s.id);
                          setSelected(next);
                        }}
                      />
                    </td>
                    <td>
                      <button type="button" className="uncoder-ui-subrow" onClick={() => openDetail(s)}>
                        <span className={cx('uncoder-ui-unreaddot', s.status === 'unread' && 'is-on')} aria-hidden />
                        <span className="uncoder-ui-subrow__text">{preview(s)}</span>
                        {s.status === 'unread' && <span className="uncoder-ui-sr-only">(unread)</span>}
                        {s.status === 'spam' && <Badge tone="danger">Spam</Badge>}
                      </button>
                    </td>
                    <td className="uncoder-ui-col-hide-md uncoder-ui-cell-muted">{s.form || '—'}</td>
                    <td className="uncoder-ui-col-hide-md uncoder-ui-cell-muted">
                      <span className="uncoder-ui-truncate">{s.postTitle || '—'}</span>
                    </td>
                    <td className="uncoder-ui-col-date">
                      <time dateTime={s.createdAt} title={absoluteTime(s.createdAt)}>
                        {relativeTime(s.createdAt)}
                      </time>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {d && d.pages > 1 && (
          <Pager
            page={page}
            hasPrev={page > 1}
            hasNext={page < d.pages}
            onPage={setPage}
            summary={`Page ${page} of ${d.pages} · ${d.total.toLocaleString()} submissions`}
          />
        )}
      </Card>

      <Drawer
        open={!!open}
        onClose={() => setOpen(null)}
        width={520}
        title={open?.form || 'Form submission'}
        subtitle={open ? `Received ${absoluteTime(open.createdAt)}` : undefined}
        footer={
          open && (
            <>
              {admin && (
                <Button variant="ghost" className="uncoder-ui-btn--danger-ghost uncoder-ui-modal__foot-start" icon="trash-2" onClick={() => remove([open.id])}>
                  Delete
                </Button>
              )}
              {open.status === 'spam' ? (
                <Button onClick={() => setItemStatus(open, 'read')}>Not spam</Button>
              ) : (
                <Button icon="ban" onClick={() => setItemStatus(open, 'spam')}>
                  Spam
                </Button>
              )}
              <Button variant="primary" icon={open.status === 'unread' ? 'mail-open' : 'mail'} onClick={() => setItemStatus(open, open.status === 'unread' ? 'read' : 'unread')}>
                {open.status === 'unread' ? 'Mark read' : 'Mark unread'}
              </Button>
            </>
          )
        }
      >
        {open && <SubmissionDetail s={open} />}
      </Drawer>
    </>
  );
}

function SubmissionDetail({ s }: { s: Submission }) {
  const rows = fieldRows(s.data);
  const meta = Object.entries(s.meta ?? {}).filter(([, v]) => metaValue(v) !== '');
  return (
    <div className="uncoder-ui-stack">
      <dl className="uncoder-ui-kv">
        {rows.length === 0 && <p className="uncoder-ui-muted">This submission has no fields.</p>}
        {rows.map((r, i) => (
          <div className="uncoder-ui-kv__row" key={i}>
            <dt>{r.label}</dt>
            <dd>
              {r.url && r.value ? (
                <a href={r.url} download>
                  <Icon name="download" size={12} /> {r.value}
                </a>
              ) : isEmail(r.value) ? (
                <a href={`mailto:${r.value}`}>{r.value}</a>
              ) : isUrl(r.value) ? (
                <a href={r.value} target="_blank" rel="noreferrer nofollow">
                  {r.value}
                </a>
              ) : r.value ? (
                <span className="uncoder-ui-prewrap">{r.value}</span>
              ) : (
                <span className="uncoder-ui-cell-muted">—</span>
              )}
            </dd>
          </div>
        ))}
      </dl>
      <section className="uncoder-ui-dgroup">
        <h3 className="uncoder-ui-dgroup__title">Details</h3>
        <dl className="uncoder-ui-kv uncoder-ui-kv--compact">
          <div className="uncoder-ui-kv__row">
            <dt>Status</dt>
            <dd>
              <Badge tone={s.status === 'unread' ? 'accent' : s.status === 'spam' ? 'danger' : 'neutral'} dot>
                {s.status === 'unread' ? 'Unread' : s.status === 'spam' ? 'Spam' : 'Read'}
              </Badge>
            </dd>
          </div>
          <div className="uncoder-ui-kv__row">
            <dt>Page</dt>
            <dd>
              {s.postUrl ? (
                <a href={s.postUrl} target="_blank" rel="noreferrer">
                  {s.postTitle || s.postUrl}
                  <Icon name="arrow-up-right" size={12} />
                </a>
              ) : (
                s.postTitle || '—'
              )}
            </dd>
          </div>
          <div className="uncoder-ui-kv__row">
            <dt>Submission ID</dt>
            <dd className="uncoder-ui-mono">#{s.id}</dd>
          </div>
          {meta.map(([k, v]) => (
            <div className="uncoder-ui-kv__row" key={k}>
              <dt>{humanize(k)}</dt>
              <dd className="uncoder-ui-break">{metaValue(v)}</dd>
            </div>
          ))}
        </dl>
      </section>
    </div>
  );
}
