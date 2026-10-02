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

/** A connection link just created: shown once, like a key. */
export interface FreshLink {
  id: number;
  name: string;
  url: string;
}

interface Props {
  status: McpStatus | undefined;
  freshKey: FreshKey | null;
  /** Connection links created on this screen, one per client (each shown once). */
  freshLinks: Partial<Record<ClientId, FreshLink>>;
  /** Creates a connection link for a client (resolves when it is shown). */
  onCreateLink: (client: ClientId, name: string) => Promise<void>;
  onCreateKey: (suggestedName: string) => void;
}

export function ConnectPanel({ status, freshKey, freshLinks, onCreateLink, onCreateKey }: Props) {
  const [linking, setLinking] = useState(false);
  const [clientId, setClientId] = useState<ClientId>('claude');
  const [methodByClient, setMethodByClient] = useState<Record<string, string>>({});
  const url = status?.endpoint ?? cfg.urls.mcp;
  const local = /^https?:\/\/(localhost|127\.|10\.|192\.168\.|\[::1\])/i.test(url) || /\.(test|local|localhost)(:\d+)?\//i.test(url);

  const list = useMemo(
    () =>
      clients({
        url,
        key: freshKey?.secret ?? KEY_PLACEHOLDER,
        links: Object.fromEntries(Object.entries(freshLinks).map(([id, l]) => [id, l?.url ?? ''])),
        site: cfg.site.name || 'WordPress',
        metadata: status?.metadata ?? url.replace(/mcp$/, 'oauth/protected-resource'),
      }),
    [url, freshKey, freshLinks, status],
  );
  const client = list.find((c) => c.id === clientId) ?? list[0];
  const method = client.methods.find((m) => m.id === methodByClient[client.id]) ?? client.methods[0];
  // Only web connectors connect from the vendor's servers; desktop apps and editors reach the site from this computer.
  const needsHttps = (!!method.cloud && (!status?.https || local)) || (method.auth === 'link' && !status?.https && !local);
  const linksOff = method.auth === 'link' && status?.settings.links === false;
  const freshLink = freshLinks[client.id] ?? null;

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
            <Icon name={method.auth === 'oauth' ? 'shield-check' : method.auth === 'link' ? 'link' : 'key-round'} size={13} />
            {method.auth === 'oauth' ? 'Sign in with OAuth' : method.auth === 'link' ? 'Connection link' : 'API key'}
          </span>
        </div>
        <p className="uncoder-ui-connect__intro">{method.intro}</p>

        {needsHttps && (
          <Callout tone="warning" title={local ? 'This site is not reachable from the internet' : 'HTTPS required'}>
            {method.cloud
              ? `${client.name} connects from its own servers, so your site must be public and use HTTPS. ${local ? `For a site on your computer, use one of the other ${client.name} methods or a tunnel (for example ngrok or Cloudflare Tunnel).` : 'Enable SSL on this site first.'}`
              : 'Connection links carry their key in the URL, so they work only over HTTPS. Enable SSL on this site first, or sign in instead.'}
          </Callout>
        )}
        {linksOff && (
          <Callout tone="warning" title="Connection links are turned off">
            Turn them on under Server settings → Connection links, or use another method.
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
                {s.link &&
                  (freshLink ? (
                    <>
                      <CopyField value={freshLink.url} label="Connection link" what="Connection link copied" secret />
                      <p className="uncoder-ui-connect__hint">“{freshLink.name}” · copy it now: it is shown only once. Revoke it any time under API keys.</p>
                    </>
                  ) : (
                    <Button
                      variant="primary"
                      icon="link"
                      loading={linking}
                      disabled={!cfg.user.caps.use_mcp || linksOff || needsHttps}
                      onClick={async () => {
                        setLinking(true);
                        try {
                          await onCreateLink(client.id, `${client.name} link`);
                        } finally {
                          setLinking(false);
                        }
                      }}
                    >
                      Create connection link
                    </Button>
                  ))}
                {s.open &&
                  (freshLink ? (
                    <a className="uncoder-ui-btn uncoder-ui-btn--primary uncoder-ui-btn--md" href={s.open.href} target={s.open.href.startsWith('http') ? '_blank' : undefined} rel="noopener noreferrer">
                      <Icon name={s.open.icon ?? 'external-link'} size={14} />
                      {s.open.label}
                    </a>
                  ) : (
                    <p className="uncoder-ui-connect__hint">“{s.open.label}” appears here once the link exists.</p>
                  ))}
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
