import { useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button } from '@editor/ui/primitives';
import { NAME } from '@shared/brand';
import { api, mcpApi, type Overview } from '../lib/api';
import { can, cfg, screenUrl } from '../lib/config';
import { absoluteTime, relativeTime } from '../lib/format';
import { useResource } from '../lib/hooks';
import { Dialog } from '../ui/Dialog';
import { StarterSitesDialog } from '../ui/StarterSitesDialog';
import { Badge, Callout, Card, EmptyState, ErrorState, PageHeader, PostStatus, SkeletonRows } from '../ui/kit';

interface AiSummary {
  enabled: boolean;
  connections: number;
  keys: number;
  apps: number;
  last: string | null;
  lastTool: string;
  calls: number;
}

async function loadAi(signal: AbortSignal): Promise<AiSummary> {
  const [status, keys, grants, log] = await Promise.all([mcpApi.status(signal), mcpApi.keys(signal), mcpApi.grants(signal), mcpApi.log({ limit: 1, offset: 0 }, signal)]);
  const activeKeys = keys.filter((k) => !k.revoked && !k.expired).length;
  return {
    enabled: status.settings.enabled,
    connections: activeKeys + grants.length,
    keys: activeKeys,
    apps: grants.length,
    last: log[0]?.time ?? null,
    lastTool: log[0] ? log[0].tool || log[0].method : '',
    calls: status.stats.calls,
  };
}

function greeting(): string {
  const h = new Date().getHours();
  return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening';
}

export function DashboardScreen() {
  const overview = useResource((signal) => api<Overview>('overview', { signal }), []);
  const ai = useResource((signal) => (can('manage_options') ? loadAi(signal) : Promise.resolve(null)), []);
  const [creating, setCreating] = useState(false);
  const [starters, setStarters] = useState(false);
  const first = cfg.user.name.split(' ')[0] || cfg.user.name;
  const o = overview.data;
  // A handed-over site (Site\Handoff): the client changes content; the design screens stay with the builders.
  const handed = cfg.handoff ?? null;

  return (
    <>
      <PageHeader
        eyebrow={cfg.site.name}
        title={`${greeting()}, ${first}`}
        description={handed ? 'Change the texts, images and links of your pages. Open a page below and choose Edit.' : 'Design pages visually, build your theme, or let an AI assistant do it for you.'}
        actions={
          handed ? undefined : (
            <Button variant="primary" icon="plus" onClick={() => setCreating(true)} disabled={!can('edit_pages')}>
              New page
            </Button>
          )
        }
      />

      {handed && (
        <Callout tone="info" icon="lock-keyhole" title={handed.by ? `Designed and looked after by ${handed.by}` : 'The design of this site is locked'}>
          You can change texts, images and links on every page. Layout, styles and the theme stay as they were built.
          {handed.contact ? ` For design changes, contact ${handed.contact}.` : ''}
        </Callout>
      )}

      {!handed && (
        <div className="uncoder-ui-quick">
          <button type="button" className="uncoder-ui-quick__card" onClick={() => setCreating(true)} disabled={!can('edit_pages')}>
            <span className="uncoder-ui-quick__icon uncoder-ui-quick__icon--accent">
              <Icon name="file-text" size={18} />
            </span>
            <span className="uncoder-ui-quick__text">
              <strong>Create a page</strong>
              <span>Start from a blank canvas in the builder</span>
            </span>
            <Icon name="arrow-right" size={15} className="uncoder-ui-quick__arrow" />
          </button>
          {/* With the starter-site library (Licence::enabled()), its screen for those who may open it (hidden for the
              others); before that, the built-in starters. */}
          {(cfg.library ? 'uncoder-starters' in cfg.pages : can('edit_theme_options') && can('edit_pages')) && (
            <button
              type="button"
              className="uncoder-ui-quick__card"
              onClick={() => (cfg.library ? (window.location.href = screenUrl('uncoder-starters')) : setStarters(true))}
            >
              <span className="uncoder-ui-quick__icon">
                <Icon name="layout-dashboard" size={18} />
              </span>
              <span className="uncoder-ui-quick__text">
                <strong>Starter sites</strong>
                <span>Header, footer, pages and styles in one go</span>
              </span>
              <Icon name="arrow-right" size={15} className="uncoder-ui-quick__arrow" />
            </button>
          )}
          {can('edit_theme_options') && (
            <a className="uncoder-ui-quick__card" href={screenUrl('uncoder-templates')}>
              <span className="uncoder-ui-quick__icon">
                <Icon name="layout-template" size={18} />
              </span>
              <span className="uncoder-ui-quick__text">
                <strong>Theme Builder</strong>
                <span>Header, footer, layouts and popups</span>
              </span>
              <Icon name="arrow-right" size={15} className="uncoder-ui-quick__arrow" />
            </a>
          )}
          {can('manage_options') && (
            <a className="uncoder-ui-quick__card" href={screenUrl('uncoder-ai')}>
              <span className="uncoder-ui-quick__icon uncoder-ui-quick__icon--ai">
                <Icon name="sparkles" size={18} />
              </span>
              <span className="uncoder-ui-quick__text">
                <strong>Connect AI</strong>
                <span>Claude, ChatGPT, Cursor and more via MCP</span>
              </span>
              <Icon name="arrow-right" size={15} className="uncoder-ui-quick__arrow" />
            </a>
          )}
        </div>
      )}

      {!handed && <SetupChecklist o={o ?? null} ai={can('manage_options') ? (ai.data ?? null) : undefined} onCreate={() => setCreating(true)} />}

      <div className="uncoder-ui-stats">
        <Stat label={`Pages built with ${NAME}`} value={o?.pages} icon="file-text" href={cfg.urls.pages} linkLabel="All pages" />
        {!handed && (
          <Stat
            label="Active templates"
            value={o?.activeTemplates}
            icon="layout-template"
            href={can('edit_theme_options') ? screenUrl('uncoder-templates') : undefined}
            linkLabel="Theme Builder"
            sub={o ? `${o.templates} total · ${o.activePopups} popup${o.activePopups === 1 ? '' : 's'} live` : undefined}
          />
        )}
        {can('manage_options') && !handed && (
          <Stat
            label="AI connections"
            value={ai.data ? ai.data.connections : ai.error ? '—' : undefined}
            icon="plug"
            href={screenUrl('uncoder-ai')}
            linkLabel="AI & MCP"
            sub={
              ai.data
                ? ai.data.last
                  ? `Last activity ${relativeTime(ai.data.last)}${ai.data.lastTool ? ` · ${ai.data.lastTool}` : ''}`
                  : 'No activity yet'
                : ai.error
                  ? 'Status unavailable'
                  : undefined
            }
            badge={ai.data && !ai.data.enabled ? <Badge tone="warning">Server off</Badge> : undefined}
          />
        )}
        {o && o.unread !== null && (
          <Stat
            label="Unread submissions"
            value={o.unread}
            icon="inbox"
            href={screenUrl('uncoder-submissions')}
            linkLabel="Submissions"
            badge={o.unread > 0 ? <Badge tone="accent">New</Badge> : undefined}
          />
        )}
      </div>

      <Card
        flush
        title="Recently edited"
        description={`Pages and posts built with ${NAME}.`}
        actions={
          <a className="uncoder-ui-btn uncoder-ui-btn--ghost uncoder-ui-btn--sm" href={cfg.urls.pages}>
            All pages
            <Icon name="arrow-up-right" size={13} />
          </a>
        }
      >
        {overview.error && !o ? (
          <ErrorState error={overview.error} onRetry={overview.reload} />
        ) : !o ? (
          <SkeletonRows rows={4} cols={3} />
        ) : o.recent.length === 0 ? (
          <EmptyState
            icon="file-text"
            title={`No pages built with ${NAME} yet`}
            action={
              handed ? undefined : (
                <Button variant="primary" icon="plus" onClick={() => setCreating(true)} disabled={!can('edit_pages')}>
                  Create your first page
                </Button>
              )
            }
          >
            {handed ? 'Pages appear here once they are built.' : <>Create one here, or open any page and choose “Edit with {NAME}”.</>}
          </EmptyState>
        ) : (
          <ul className="uncoder-ui-recent">
            {o.recent.map((p) => (
              <li key={p.id} className="uncoder-ui-recent__row">
                <span className="uncoder-ui-recent__icon" aria-hidden>
                  <Icon name={p.type === 'post' ? 'file-text' : 'file'} size={15} />
                </span>
                <div className="uncoder-ui-recent__main">
                  {p.editUrl ? (
                    <a className="uncoder-ui-recent__title" href={p.editUrl}>
                      {p.title || '(no title)'}
                    </a>
                  ) : (
                    <span className="uncoder-ui-recent__title">{p.title || '(no title)'}</span>
                  )}
                  <span className="uncoder-ui-recent__meta">
                    {p.typeLabel} · <time title={absoluteTime(p.modified)}>edited {relativeTime(p.modified)}</time>
                  </span>
                </div>
                <PostStatus status={p.status} />
                <div className="uncoder-ui-rowactions">
                  {p.viewUrl && (
                    <a className="uncoder-ui-btn uncoder-ui-btn--ghost uncoder-ui-btn--sm" href={p.viewUrl} target="_blank" rel="noreferrer">
                      View
                      <span className="uncoder-ui-sr-only"> {p.title} (opens in a new tab)</span>
                    </a>
                  )}
                  {p.editUrl && (
                    <a className="uncoder-ui-btn uncoder-ui-btn--secondary uncoder-ui-btn--sm" href={p.editUrl}>
                      <Icon name="pencil" size={13} />
                      Edit
                    </a>
                  )}
                </div>
              </li>
            ))}
          </ul>
        )}
      </Card>

      <CreatePageDialog open={creating} onClose={() => setCreating(false)} enabled={!o || o.postTypes.includes('page')} />
      <StarterSitesDialog open={starters} onClose={() => setStarters(false)} onImported={() => void overview.reload()} />
    </>
  );
}

/** First steps, each checked from real site state; hidden once everything is done. */
function SetupChecklist({ o, ai, onCreate }: { o: Overview | null; ai: AiSummary | null | undefined; onCreate: () => void }) {
  if (!o) return null;
  const steps: Array<{ id: string; title: string; text: string; done: boolean; action: React.ReactNode }> = [
    {
      id: 'page',
      title: 'Build your first page',
      text: 'Start blank, from a starter site, or let AI draft it.',
      done: o.pages > 0,
      action: can('edit_pages') ? (
        <button type="button" className="uncoder-ui-setup__go" onClick={onCreate}>
          Create page
        </button>
      ) : null,
    },
    ...(can('edit_theme_options')
      ? [
          { id: 'header', title: 'Publish a header', text: 'Replaces your theme’s header on the pages you choose.', done: o.live.header, action: <a className="uncoder-ui-setup__go" href={screenUrl('uncoder-templates', 'header')}>Open</a> },
          { id: 'footer', title: 'Publish a footer', text: 'Contact details, links and legal text on every page.', done: o.live.footer, action: <a className="uncoder-ui-setup__go" href={screenUrl('uncoder-templates', 'footer')}>Open</a> },
        ]
      : []),
    ...(ai !== undefined
      ? [{ id: 'ai', title: 'Connect an AI assistant', text: 'Claude, ChatGPT or Cursor can then build and edit pages.', done: !!ai && ai.connections > 0, action: <a className="uncoder-ui-setup__go" href={screenUrl('uncoder-ai')}>Connect</a> }]
      : []),
  ];
  const done = steps.filter((s) => s.done).length;
  if (!steps.length || done === steps.length) return null;
  return (
    <Card
      className="uncoder-ui-setup"
      title="Get your site ready"
      description={`${done} of ${steps.length} done`}
      actions={
        <span className="uncoder-ui-setup__meter" role="progressbar" aria-label="Setup progress" aria-valuemin={0} aria-valuemax={steps.length} aria-valuenow={done}>
          <span style={{ width: `${(done / steps.length) * 100}%` }} />
        </span>
      }
    >
      <ol className="uncoder-ui-setup__list">
        {steps.map((st) => (
          <li key={st.id} className={st.done ? 'is-done' : undefined}>
            <span className="uncoder-ui-setup__check" aria-hidden>
              {st.done ? <Icon name="check" size={13} /> : null}
            </span>
            <span className="uncoder-ui-setup__text">
              <strong>{st.title}</strong>
              <span>{st.done ? 'Done' : st.text}</span>
            </span>
            {!st.done && st.action}
          </li>
        ))}
      </ol>
    </Card>
  );
}

function Stat({ label, value, icon, href, linkLabel, sub, badge }: { label: string; value: number | string | undefined; icon: string; href?: string; linkLabel?: string; sub?: string; badge?: React.ReactNode }) {
  const body = (
    <>
      <div className="uncoder-ui-stat__top">
        <span className="uncoder-ui-stat__icon" aria-hidden>
          <Icon name={icon} size={15} />
        </span>
        <span className="uncoder-ui-stat__label">{label}</span>
        {badge}
        {href && <Icon name="arrow-up-right" size={14} className="uncoder-ui-stat__go" />}
      </div>
      <div className="uncoder-ui-stat__value">{value === undefined ? <span className="uncoder-ui-skel__bar uncoder-ui-stat__skel" /> : typeof value === 'number' ? value.toLocaleString() : value}</div>
      <div className="uncoder-ui-stat__sub">{sub ?? '\u00a0'}</div>
      {href && linkLabel && <span className="uncoder-ui-sr-only">Open {linkLabel}</span>}
    </>
  );
  return href ? (
    <a className="uncoder-ui-stat uncoder-ui-stat--link" href={href}>
      {body}
    </a>
  ) : (
    <div className="uncoder-ui-stat">{body}</div>
  );
}

function CreatePageDialog({ open, onClose, enabled }: { open: boolean; onClose: () => void; enabled: boolean }) {
  const [title, setTitle] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async (e?: React.FormEvent) => {
    e?.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const res = await api<{ id: number; editUrl: string }>('overview/page', { body: { title: title.trim() } });
      window.location.href = res.editUrl;
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not create the page.');
      setBusy(false);
    }
  };

  return (
    <Dialog
      open={open}
      onClose={() => {
        onClose();
        setTitle('');
        setError(null);
      }}
      dismissable={!title}
      width={460}
      title="New page"
      description="Creates a draft page and opens it in the builder."
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button variant="primary" iconRight="arrow-right" onClick={() => submit()} loading={busy} disabled={!enabled}>
            Create and open editor
          </Button>
        </>
      }
    >
      <form className="uncoder-ui-form-stack" onSubmit={submit}>
        {!enabled && <Callout tone="warning">{NAME} is not enabled for pages. Turn it on in {NAME} → Settings.</Callout>}
        {error && <Callout tone="danger">{error}</Callout>}
        <div className="uncoder-ui-fld">
          <label className="uncoder-ui-fld__label" htmlFor="uncoder-ui-new-page">
            Page title
          </label>
          <input id="uncoder-ui-new-page" className="uncoder-ui-input" value={title} placeholder="About us" maxLength={200} onChange={(e) => setTitle(e.currentTarget.value)} data-autofocus autoComplete="off" />
        </div>
        <button type="submit" hidden aria-hidden tabIndex={-1} />
      </form>
    </Dialog>
  );
}
