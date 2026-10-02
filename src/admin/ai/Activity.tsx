import { useEffect, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button, IconButton, Toggle } from '@editor/ui/primitives';
import { mcpApi, type LogEntry } from '../lib/api';
import { absoluteTime, cx, formatMs, relativeTime } from '../lib/format';
import { useResource } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { confirmDialog } from '../ui/Dialog';
import { Badge, Card, EmptyState, ErrorState, Pager, SearchInput, SkeletonRows } from '../ui/kit';

const PAGE = 25;

const STATUS_TONE: Record<string, 'success' | 'danger' | 'warning' | 'neutral'> = { ok: 'success', error: 'danger', invalid: 'warning', limited: 'warning', denied: 'danger', revoked: 'neutral' };
const STATUS_LABEL: Record<string, string> = { ok: 'OK', error: 'Error', invalid: 'Invalid', limited: 'Rate limited', denied: 'Denied', revoked: 'Revoked' };

export function ActivityPanel({ toolNames }: { toolNames: string[] }) {
  const [page, setPage] = useState(0);
  const [tool, setTool] = useState('');
  const [status, setStatus] = useState('');
  const [query, setQuery] = useState('');
  const [live, setLive] = useState(false);
  const [undoing, setUndoing] = useState<number | null>(null);
  const log = useResource((signal) => mcpApi.log({ limit: PAGE + 1, offset: page * PAGE, tool, status }, signal), [page, tool, status]);

  useEffect(() => {
    if (!live || page !== 0) return;
    const t = window.setInterval(() => log.reload(), 8000);
    return () => window.clearInterval(t);
  }, [live, page, log.reload]);

  const rows = (log.data ?? []).slice(0, PAGE);
  const hasNext = (log.data?.length ?? 0) > PAGE;
  const q = query.trim().toLowerCase();
  const visible = q ? rows.filter((r) => [r.client, r.tool, r.method, r.summary ?? '', r.user].some((v) => v.toLowerCase().includes(q))) : rows;

  const undo = async (r: LogEntry) => {
    const ok = await confirmDialog({
      title: 'Undo this change?',
      body: (
        <>
          Restores the state saved just before <strong>{r.tool || r.method}</strong> ran {relativeTime(r.time)}
          {r.summary ? <> (“{r.summary}”)</> : null}. Changes made to the same item since then are replaced too; the current state is saved first so this can be undone again.
        </>
      ),
      confirmLabel: 'Undo change',
    });
    if (!ok) return;
    setUndoing(r.id);
    try {
      await mcpApi.undo(r.snapshot);
      toast('Change undone');
      setPage(0);
      log.reload();
    } catch (e) {
      toastError(e);
    } finally {
      setUndoing(null);
    }
  };

  const clear = async () => {
    const ok = await confirmDialog({ title: 'Clear the activity log?', body: 'Every entry is deleted. Undo snapshots stay attached to their pages but are no longer listed here.', confirmLabel: 'Clear log', danger: true });
    if (!ok) return;
    try {
      await mcpApi.clearLog();
      setPage(0);
      log.reload();
      toast('Activity log cleared');
    } catch (e) {
      toastError(e);
    }
  };

  return (
    <Card
      flush
      title="Activity"
      description="Every MCP call: who, which tool, the outcome and a short summary (arguments are never stored)."
      actions={
        <>
          <label className="uncoder-ui-inline uncoder-ui-muted" title="Refresh every 8 seconds">
            <Toggle checked={live} onChange={setLive} label="Live updates" />
            Live
          </label>
          <IconButton icon="refresh-cw" label="Refresh" onClick={() => log.reload()} className={cx(log.loading && 'is-spinning')} />
          <Button size="sm" variant="ghost" className="uncoder-ui-btn--danger-ghost" onClick={clear}>
            Clear log
          </Button>
        </>
      }
    >
      <div className="uncoder-ui-filters">
        <select
          className="uncoder-ui-select uncoder-ui-select--sm"
          value={tool}
          aria-label="Filter by tool"
          onChange={(e) => {
            setTool(e.currentTarget.value);
            setPage(0);
          }}
        >
          <option value="">All tools</option>
          {[...toolNames].sort().map((t) => (
            <option key={t} value={t}>
              {t}
            </option>
          ))}
        </select>
        <select
          className="uncoder-ui-select uncoder-ui-select--sm"
          value={status}
          aria-label="Filter by status"
          onChange={(e) => {
            setStatus(e.currentTarget.value);
            setPage(0);
          }}
        >
          <option value="">Any status</option>
          {Object.entries(STATUS_LABEL).map(([v, l]) => (
            <option key={v} value={v}>
              {l}
            </option>
          ))}
        </select>
        <SearchInput value={query} onChange={setQuery} placeholder="Filter this page by client or summary…" width={280} />
      </div>
      {log.error && !log.data ? (
        <ErrorState error={log.error} onRetry={log.reload} />
      ) : !log.data ? (
        <SkeletonRows rows={6} cols={6} />
      ) : visible.length === 0 ? (
        <EmptyState icon="activity" title={tool || status || q ? 'No matching activity' : 'No activity yet'}>
          {tool || status || q ? 'Try other filters.' : 'Calls from connected AI clients appear here as they happen.'}
        </EmptyState>
      ) : (
        <div className="uncoder-ui-tablewrap">
          <table className="uncoder-ui-table uncoder-ui-table--log">
            <thead>
              <tr>
                <th scope="col" className="uncoder-ui-col-date">
                  Time
                </th>
                <th scope="col">Client</th>
                <th scope="col">Tool</th>
                <th scope="col" className="uncoder-ui-col-status">
                  Status
                </th>
                <th scope="col">Summary</th>
                <th scope="col" className="uncoder-ui-col-num uncoder-ui-col-hide-md">
                  Duration
                </th>
                <th scope="col" className="uncoder-ui-col-actions">
                  <span className="uncoder-ui-sr-only">Undo</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {visible.map((r) => (
                <tr key={r.id}>
                  <td className="uncoder-ui-col-date">
                    <time dateTime={r.time} title={absoluteTime(r.time)}>
                      {relativeTime(r.time)}
                    </time>
                  </td>
                  <td>
                    <div className="uncoder-ui-logclient">
                      <span className="uncoder-ui-truncate" title={r.client}>
                        {r.client || '—'}
                      </span>
                      {r.user && <span className="uncoder-ui-namecell__meta">{r.user}</span>}
                    </div>
                  </td>
                  <td>
                    <code className="uncoder-ui-tool">{r.tool || r.method}</code>
                  </td>
                  <td className="uncoder-ui-col-status">
                    <Badge tone={STATUS_TONE[r.status] ?? 'neutral'} dot>
                      {STATUS_LABEL[r.status] ?? r.status}
                    </Badge>
                  </td>
                  <td>
                    <span className="uncoder-ui-logsummary" title={r.summary ?? ''}>
                      {r.summary || <span className="uncoder-ui-cell-muted">—</span>}
                    </span>
                  </td>
                  <td className="uncoder-ui-col-num uncoder-ui-col-hide-md">{r.duration ? formatMs(r.duration) : <span className="uncoder-ui-cell-muted">—</span>}</td>
                  <td className="uncoder-ui-col-actions">
                    {r.snapshot && r.status === 'ok' ? (
                      <Button size="sm" variant="secondary" icon="undo-2" onClick={() => undo(r)} loading={undoing === r.id} title="Restore the state before this change">
                        Undo
                      </Button>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <Pager
        page={page}
        hasPrev={page > 0}
        hasNext={hasNext}
        onPage={setPage}
        summary={
          rows.length ? (
            <>
              <Icon name="list" size={13} />
              Entries {page * PAGE + 1}–{page * PAGE + rows.length}
            </>
          ) : null
        }
      />
    </Card>
  );
}
