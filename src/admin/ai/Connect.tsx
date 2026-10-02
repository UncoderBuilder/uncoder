import { useMemo, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button, Segmented } from '@editor/ui/primitives';
import type { McpStatus } from '../lib/api';
import { cfg } from '../lib/config';
import { cx } from '../lib/format';
import { Callout, CodeBlock, CopyField } from '../ui/kit';
import { clients, KEY_PLACEHOLDER, type ClientDef, type ClientId } from './snippets';
import { CLIENT_LOGOS } from './clientLogos';

export interface FreshKey {
  id: number;
  name: string;
  secret: string;
}

interface Props {
  status: McpStatus | undefined;
  freshKey: FreshKey | null;
  onCreateKey: (suggestedName: string) => void;
}

export function ConnectPanel({ status, freshKey, onCreateKey }: Props) {
  const [clientId, setClientId] = useState<ClientId>('claude');
  const [methodByClient, setMethodByClient] = useState<Record<string, string>>({});
  const url = status?.endpoint ?? cfg.urls.mcp;
  const local = /^https?:\/\/(localhost|127\.|10\.|192\.168\.|\[::1\])/i.test(url) || /\.(test|local|localhost)(:\d+)?\//i.test(url);

  const list = useMemo(
    () =>
      clients({
        url,
        key: freshKey?.secret ?? KEY_PLACEHOLDER,
        site: cfg.site.name || 'WordPress',
        bridge: status?.bridge ?? `npx -y @uncoder/mcp --url ${url} --key <API_KEY>`,
        metadata: status?.metadata ?? url.replace(/mcp$/, 'oauth/protected-resource'),
      }),
    [url, freshKey, status],
  );
  const client = list.find((c) => c.id === clientId) ?? list[0];
  const method = client.methods.find((m) => m.id === methodByClient[client.id]) ?? client.methods[0];
  const needsHttps = method.auth === 'oauth' && (!status?.https || local);

  return (
    <div className="uncoder-ui-connect">
      <div className="uncoder-ui-clientpick" role="radiogroup" aria-label="AI client">
        {list.map((c) => {
          const active = c.id === client.id;
          return (
            <button
              key={c.id}
              type="button"
              role="radio"
              aria-checked={active}
              className={cx('uncoder-ui-clientpick__item', active && 'is-active')}
              onClick={() => setClientId(c.id)}
              onKeyDown={(e) => {
                const i = list.findIndex((x) => x.id === c.id);
                const next = e.key === 'ArrowRight' || e.key === 'ArrowDown' ? list[(i + 1) % list.length] : e.key === 'ArrowLeft' || e.key === 'ArrowUp' ? list[(i - 1 + list.length) % list.length] : null;
                if (next) {
                  e.preventDefault();
                  setClientId(next.id);
                  (e.currentTarget.parentElement?.children[list.indexOf(next)] as HTMLElement | undefined)?.focus();
                }
              }}
              tabIndex={active ? 0 : -1}
            >
              <ClientMark client={c} />
              <span className="uncoder-ui-clientpick__text">
                <strong>{c.name}</strong>
                <span>{c.blurb}</span>
              </span>
            </button>
          );
        })}
      </div>

      <div className="uncoder-ui-connect__panel">
        <div className="uncoder-ui-connect__head">
          {client.methods.length > 1 ? (
            <Segmented
              size="md"
              ariaLabel={`${client.name} connection method`}
              value={method.id}
              onChange={(v) => setMethodByClient((m) => ({ ...m, [client.id]: v }))}
              options={client.methods.map((m) => ({ value: m.id, label: m.label }))}
            />
          ) : (
            <h3 className="uncoder-ui-connect__title">{method.label}</h3>
          )}
          <span className={cx('uncoder-ui-authtag', method.auth === 'oauth' ? 'is-oauth' : 'is-key')}>
            <Icon name={method.auth === 'oauth' ? 'shield-check' : 'key-round'} size={13} />
            {method.auth === 'oauth' ? 'Sign in with OAuth' : 'API key'}
          </span>
        </div>
        <p className="uncoder-ui-connect__intro">{method.intro}</p>

        {needsHttps && (
          <Callout tone="warning" title={local ? 'This site is not reachable from the internet' : 'HTTPS required'}>
            {client.name} connects from its own servers, so the URL must be public and use HTTPS. {local ? 'For a local site, use a tunnel (for example ngrok or Cloudflare Tunnel) or connect a desktop client with an API key.' : 'Enable SSL on this site first.'}
          </Callout>
        )}

        {method.auth === 'key' &&
          (freshKey ? (
            <Callout tone="success" icon="key-round" title={`Your new key “${freshKey.name}” is filled in below`}>
              Copy the snippet now — the key will not be shown again after you leave this page.
            </Callout>
          ) : (
            <Callout
              tone="info"
              icon="key-round"
              title="This method uses an API key"
              actions={
                <Button size="sm" variant="primary" icon="plus" onClick={() => onCreateKey(client.name)} disabled={!cfg.user.caps.use_mcp}>
                  Create key
                </Button>
              }
            >
              Create one and it is inserted into the snippets below, or replace <code>{KEY_PLACEHOLDER}</code> with an existing key.
            </Callout>
          ))}

        <ol className="uncoder-ui-steps">
          {method.steps.map((s, i) => (
            <li key={i} className="uncoder-ui-steps__item">
              <span className="uncoder-ui-steps__num" aria-hidden>
                {i + 1}
              </span>
              <div className="uncoder-ui-steps__body">
                <p>{s.text}</p>
                {s.url && <CopyField value={url} label="MCP server URL" what="Server URL copied" />}
                {s.snippet && <CodeBlock code={s.snippet.code} lang={s.snippet.lang} file={s.snippet.file} label={s.snippet.label} />}
              </div>
            </li>
          ))}
        </ol>
        {method.note && (
          <p className="uncoder-ui-connect__note">
            <Icon name="info" size={13} />
            {method.note}
          </p>
        )}
      </div>
    </div>
  );
}

/** The app's real logo in its brand color, or a neutral icon where no logo may be used. */
function ClientMark({ client }: { client: ClientDef }) {
  const logo = client.logo ? CLIENT_LOGOS[client.logo] : undefined;
  return (
    <span className={cx('uncoder-ui-clientpick__mark', !logo && 'is-neutral')} aria-hidden>
      {logo ? (
        <svg viewBox={logo.viewBox ?? '0 0 24 24'} width="18" height="18" fill={logo.color}>
          <path d={logo.path} />
        </svg>
      ) : (
        <Icon name={client.icon ?? 'plug'} size={16} />
      )}
    </span>
  );
}
