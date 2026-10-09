import { useState } from 'react';
import { Button, Toggle } from '@editor/ui/primitives';
import { mcpApi } from '../lib/api';
import { cfg } from '../lib/config';
import { cx } from '../lib/format';
import { useHashState, useResource } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { confirmDialog } from '../ui/Dialog';
import { Callout, CopyField, ErrorState, PageHeader, TabPanel, Tabs, useSubCrumb } from '../ui/kit';
import { ActivityPanel } from '../ai/Activity';
import { CreateKeyDialog, GrantsPanel, KeysPanel, SecretDialog } from '../ai/Access';
import { ConnectPanel, type FreshKey, type FreshLink } from '../ai/Connect';
import type { ClientId } from '../ai/snippets';
import { McpSettingsPanel } from '../ai/McpSettings';

const TABS = ['connect', 'keys', 'apps', 'activity', 'settings'] as const;
const TAB_LABEL: Record<(typeof TABS)[number], string> = { connect: 'Connect a client', keys: 'API keys', apps: 'Connected apps', activity: 'Activity', settings: 'Server settings' };
type Tab = (typeof TABS)[number];

export function AiScreen() {
  const [tab, setTab] = useHashState(TABS, 'connect');
  useSubCrumb(tab === 'connect' ? null : TAB_LABEL[tab]);
  const status = useResource((signal) => mcpApi.status(signal), []);
  const keys = useResource((signal) => mcpApi.keys(signal), []);
  const grants = useResource((signal) => mcpApi.grants(signal), []);
  const [freshKey, setFreshKey] = useState<FreshKey | null>(null);
  const [freshLinks, setFreshLinks] = useState<Partial<Record<ClientId, FreshLink>>>({});
  const [secretOpen, setSecretOpen] = useState(false);
  const [createFor, setCreateFor] = useState<string | null>(null);
  const [enabling, setEnabling] = useState(false);

  const s = status.data;
  const enabled = s?.settings.enabled ?? true;
  const activeKeys = keys.data?.filter((k) => !k.revoked && !k.expired).length;

  const setEnabled = async (on: boolean) => {
    if (!on) {
      const ok = await confirmDialog({
        title: 'Turn off the MCP server?',
        body: 'Every AI client is disconnected until you turn it back on. API keys and connected apps are kept.',
        confirmLabel: 'Turn off',
        danger: true,
      });
      if (!ok) return;
    }
    setEnabling(true);
    try {
      const settings = await mcpApi.saveSettings({ enabled: on });
      status.setData((prev) => (prev ? { ...prev, settings } : prev));
      toast(on ? 'MCP server turned on' : 'MCP server turned off');
    } catch (e) {
      toastError(e);
    } finally {
      setEnabling(false);
    }
  };

  return (
    <>
      <PageHeader
        title="AI & MCP"
        description={
          <>
            Let Claude, ChatGPT, Cursor and other AI clients design and edit this site through the{' '}
            <a href="https://modelcontextprotocol.io/" target="_blank" rel="noreferrer">
              Model Context Protocol
            </a>
            . Every change is checked against the user’s permissions, logged and undoable.
          </>
        }
        actions={
          s ? (
            <label className="uncoder-ui-serverswitch">
              <span>{enabled ? 'Server enabled' : 'Server disabled'}</span>
              <Toggle checked={enabled} onChange={setEnabled} label="MCP server enabled" disabled={enabling} />
            </label>
          ) : undefined
        }
      />

      {status.error && !s ? (
        <ErrorState error={status.error} onRetry={status.reload} />
      ) : (
        <section className={cx('uncoder-ui-server', !enabled && 'is-off')} aria-label="MCP server status">
          <div className="uncoder-ui-server__state">
            <span className={cx('uncoder-ui-pulse', s ? (enabled ? 'is-on' : 'is-off') : 'is-unknown')} aria-hidden />
            <div>
              <div className="uncoder-ui-server__title">{!s ? 'Checking server…' : enabled ? 'MCP server is online' : 'MCP server is turned off'}</div>
              <div className="uncoder-ui-server__meta">
                {s ? (
                  <>
                    {s.tools} tools · protocol {s.protocols[0]}
                    {s.abilities ? ' · Abilities API' : ''}
                  </>
                ) : (
                  '\u00a0'
                )}
              </div>
            </div>
            {s && !enabled && (
              <Button variant="primary" size="sm" onClick={() => setEnabled(true)} loading={enabling} className="uncoder-ui-server__enable">
                Turn on
              </Button>
            )}
          </div>
          <div className="uncoder-ui-server__endpoint">
            <span className="uncoder-ui-server__label" id="uncoder-ui-endpoint-label">
              Server URL
            </span>
            <CopyField value={s?.endpoint ?? cfg.urls.mcp} label="MCP server URL" what="Server URL copied" />
          </div>
          <dl className="uncoder-ui-server__stats">
            <div>
              <dt>Calls · 24h</dt>
              <dd>{s ? s.stats.calls.toLocaleString() : '–'}</dd>
            </div>
            <div>
              <dt>Changes · 24h</dt>
              <dd>{s ? s.stats.writes.toLocaleString() : '–'}</dd>
            </div>
            <div>
              <dt>Errors · 24h</dt>
              <dd className={cx(s && s.stats.errors > 0 && 'is-bad')}>{s ? s.stats.errors.toLocaleString() : '–'}</dd>
            </div>
          </dl>
        </section>
      )}

      {s && !enabled && <Callout tone="warning">AI clients cannot connect while the server is off. Existing keys and connected apps are kept.</Callout>}

      <Tabs<Tab>
        idBase="uncoder-ui-ai"
        label="AI & MCP sections"
        value={tab}
        onChange={setTab}
        tabs={[
          { id: 'connect', label: 'Connect a client', icon: 'cable' },
          { id: 'keys', label: 'API keys', icon: 'key-round', count: activeKeys ?? null },
          { id: 'apps', label: 'Connected apps', icon: 'plug', count: grants.data?.length ?? null },
          { id: 'activity', label: 'Activity', icon: 'activity' },
          { id: 'settings', label: 'Server settings', icon: 'settings' },
        ]}
      />
      <TabPanel idBase="uncoder-ui-ai" active={tab}>
        {tab === 'connect' && (
          <ConnectPanel
            status={s}
            freshKey={freshKey}
            freshLinks={freshLinks}
            onCreateKey={(name) => setCreateFor(name)}
            onCreateLink={async (client, name) => {
              try {
                // Read, Content and Design: enough to build and edit; Site settings stay off for a link.
                const res = await mcpApi.createKey({ name, scopes: ['read', 'content', 'design'], expires_days: 0, link: true });
                setFreshLinks((all) => ({ ...all, [client]: { id: res.id, name, url: res.url } }));
                keys.reload();
                toast('Connection link created');
              } catch (e) {
                toastError(e);
              }
            }}
          />
        )}
        {tab === 'keys' && <KeysPanel keys={keys} freshKey={freshKey} onCreate={() => setCreateFor('')} />}
        {tab === 'apps' && <GrantsPanel grants={grants} />}
        {tab === 'activity' && <ActivityPanel toolNames={s?.tool_names ?? []} />}
        {tab === 'settings' && (
          <McpSettingsPanel
            status={s}
            onSaved={(settings) => {
              status.setData((prev) => (prev ? { ...prev, settings } : prev));
              status.reload();
            }}
          />
        )}
      </TabPanel>

      <CreateKeyDialog
        open={createFor !== null}
        suggestedName={createFor ?? ''}
        scopes={s?.scopes ?? { read: 'Read pages, templates, media, menus and settings', content: 'Create and edit pages, posts, media and their SEO title and description', design: 'Change the Design System, theme templates, popups and custom CSS', site: 'Change site settings and menus' }}
        onClose={() => setCreateFor(null)}
        onCreated={(k) => {
          setCreateFor(null);
          setFreshKey(k);
          setSecretOpen(true);
          keys.reload();
        }}
      />
      <SecretDialog
        fresh={secretOpen ? freshKey : null}
        onClose={() => setSecretOpen(false)}
        onShowSetup={() => {
          setSecretOpen(false);
          setTab('connect');
        }}
      />
    </>
  );
}
