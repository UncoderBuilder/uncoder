import { useEffect, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button, Toggle } from '@editor/ui/primitives';
import { mcpApi, type ApiKey, type Grant } from '../lib/api';
import { cfg } from '../lib/config';
import { absoluteTime, cx, relativeTime, shortDate } from '../lib/format';
import type { Resource } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { confirmDialog, Dialog } from '../ui/Dialog';
import { Badge, Callout, Card, Checkbox, CopyField, EmptyState, ErrorState, SkeletonRows } from '../ui/kit';
import type { FreshKey } from './Connect';

const SCOPE_LABEL: Record<string, string> = { read: 'Read', content: 'Content', design: 'Design', site: 'Site', admin: 'Admin' };

function Scopes({ scopes }: { scopes: string[] }) {
  return (
    <span className="uncoder-ui-scopes">
      {scopes.map((s) => (
        <span key={s} className={cx('uncoder-ui-scope', `uncoder-ui-scope--${s}`)}>
          {SCOPE_LABEL[s] ?? s}
        </span>
      ))}
    </span>
  );
}

function When({ value, empty = 'Never' }: { value: string | null | undefined; empty?: string }) {
  if (!value) return <span className="uncoder-ui-cell-muted">{empty}</span>;
  return (
    <time dateTime={value} title={absoluteTime(value)}>
      {relativeTime(value)}
    </time>
  );
}

/* ------------------------------------------------------------------ API keys */

export function KeysPanel({ keys, onCreate, freshKey }: { keys: Resource<ApiKey[]>; onCreate: () => void; freshKey: FreshKey | null }) {
  const [showInactive, setShowInactive] = useState(false);
  const all = keys.data ?? [];
  const inactive = all.filter((k) => k.revoked || k.expired);
  const shown = showInactive ? all : all.filter((k) => !k.revoked && !k.expired);

  const revoke = async (k: ApiKey) => {
    const ok = await confirmDialog({
      title: `Revoke “${k.name}”?`,
      body: (
        <>
          Every client using the key ending in <code>…{k.hint}</code> loses access immediately. This cannot be undone.
        </>
      ),
      confirmLabel: 'Revoke key',
      danger: true,
    });
    if (!ok) return;
    try {
      await mcpApi.revokeKey(k.id);
      keys.setData((prev) => (prev ?? []).map((x) => (x.id === k.id ? { ...x, revoked: true } : x)));
      toast(`“${k.name}” revoked`);
    } catch (e) {
      toastError(e);
    }
  };

  return (
    <Card
      flush
      title="API keys"
      description="For Claude Code, Claude Desktop, Cursor, VS Code, Windsurf and scripts. Each key acts as the user who created it."
      actions={
        <>
          {inactive.length > 0 && (
            <label className="uncoder-ui-inline uncoder-ui-muted">
              <Toggle checked={showInactive} onChange={setShowInactive} label="Show revoked and expired keys" />
              Show revoked ({inactive.length})
            </label>
          )}
          <Button variant="primary" icon="plus" onClick={onCreate} disabled={!cfg.user.caps.use_mcp}>
            Create key
          </Button>
        </>
      }
    >
      {keys.error && !keys.data ? (
        <ErrorState error={keys.error} onRetry={keys.reload} />
      ) : !keys.data ? (
        <SkeletonRows rows={3} cols={5} />
      ) : shown.length === 0 ? (
        <EmptyState
          icon="key-round"
          title={all.length ? 'No active keys' : 'No API keys yet'}
          action={
            <Button variant="secondary" icon="plus" onClick={onCreate} disabled={!cfg.user.caps.use_mcp}>
              Create key
            </Button>
          }
        >
          Keys are shown once when created and stored only as a hash.
        </EmptyState>
      ) : (
        <div className="uncoder-ui-tablewrap">
          <table className="uncoder-ui-table">
            <thead>
              <tr>
                <th scope="col">Name</th>
                <th scope="col">Permissions</th>
                <th scope="col" className="uncoder-ui-col-hide-md">
                  Created
                </th>
                <th scope="col">Last used</th>
                <th scope="col" className="uncoder-ui-col-hide-md">
                  Expires
                </th>
                <th scope="col" className="uncoder-ui-col-actions">
                  <span className="uncoder-ui-sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {shown.map((k) => (
                <tr key={k.id} className={cx((k.revoked || k.expired) && 'is-inactive')}>
                  <td>
                    <div className="uncoder-ui-namecell">
                      <span className="uncoder-ui-namecell__icon" aria-hidden>
                        <Icon name="key-round" size={15} />
                      </span>
                      <div className="uncoder-ui-namecell__text">
                        <span className="uncoder-ui-namecell__title">
                          {k.name}
                          {freshKey?.id === k.id && (
                            <Badge tone="accent" dot>
                              New
                            </Badge>
                          )}
                          {k.revoked && <Badge tone="danger">Revoked</Badge>}
                          {!k.revoked && k.expired && <Badge tone="warning">Expired</Badge>}
                        </span>
                        <span className="uncoder-ui-namecell__meta">
                          <code className="uncoder-ui-keyhint">uncoder_key_…{k.hint}</code>
                          {k.user && <> · {k.user}</>}
                        </span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <Scopes scopes={k.scopes} />
                  </td>
                  <td className="uncoder-ui-col-hide-md">
                    <When value={k.created_at} />
                  </td>
                  <td>
                    <When value={k.last_used} />
                  </td>
                  <td className="uncoder-ui-col-hide-md">{k.expires_at ? <span title={absoluteTime(k.expires_at)}>{shortDate(k.expires_at)}</span> : <span className="uncoder-ui-cell-muted">Never</span>}</td>
                  <td className="uncoder-ui-col-actions">
                    {!k.revoked && (
                      <Button size="sm" variant="ghost" className="uncoder-ui-btn--danger-ghost" onClick={() => revoke(k)}>
                        Revoke
                      </Button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}

const EXPIRY = [
  { value: '30', label: '30 days' },
  { value: '90', label: '90 days' },
  { value: '180', label: '180 days' },
  { value: '365', label: '1 year' },
  { value: '0', label: 'Never expires' },
];

export function CreateKeyDialog({ open, suggestedName, scopes, onClose, onCreated }: { open: boolean; suggestedName: string; scopes: Record<string, string>; onClose: () => void; onCreated: (k: FreshKey) => void }) {
  const [name, setName] = useState('');
  const [picked, setPicked] = useState<string[]>(['read', 'content', 'design']);
  const [expiry, setExpiry] = useState('90');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    setName(suggestedName);
    setPicked(['read', 'content', 'design']);
    setExpiry('90');
    setError(null);
    setBusy(false);
  }, [open, suggestedName]);

  const submit = async (e?: React.FormEvent) => {
    e?.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const res = await mcpApi.createKey({ name: name.trim() || 'API key', scopes: picked, expires_days: Number(expiry) });
      onCreated({ id: res.id, name: name.trim() || 'API key', secret: res.secret });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not create the key.');
      setBusy(false);
    }
  };

  const siteAllowed = cfg.user.caps.manage_options;

  return (
    <Dialog
      open={open}
      onClose={onClose}
      dismissable={false}
      width={500}
      title="Create API key"
      description="The key acts as you, limited to the permissions you pick."
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button variant="primary" onClick={() => submit()} loading={busy}>
            Create key
          </Button>
        </>
      }
    >
      <form className="uncoder-ui-form-stack" onSubmit={submit}>
        {error && <Callout tone="danger">{error}</Callout>}
        <div className="uncoder-ui-fld">
          <label className="uncoder-ui-fld__label" htmlFor="uncoder-ui-key-name">
            Name
          </label>
          <input id="uncoder-ui-key-name" className="uncoder-ui-input" value={name} onChange={(e) => setName(e.currentTarget.value)} placeholder="Cursor on my laptop" maxLength={80} data-autofocus autoComplete="off" />
          <div className="uncoder-ui-fld__help">Helps you recognise it in the activity log.</div>
        </div>
        <fieldset className="uncoder-ui-fld">
          <legend className="uncoder-ui-fld__label">Permissions</legend>
          <div className="uncoder-ui-checklist">
            {Object.entries(scopes).map(([id, description]) => (
              <Checkbox
                key={id}
                label={SCOPE_LABEL[id] ?? id}
                description={description}
                checked={id === 'read' || picked.includes(id)}
                disabled={id === 'read' || (id === 'site' && !siteAllowed)}
                onChange={(v) => setPicked((p) => (v ? [...p, id] : p.filter((x) => x !== id)))}
              />
            ))}
          </div>
        </fieldset>
        <div className="uncoder-ui-fld">
          <label className="uncoder-ui-fld__label" htmlFor="uncoder-ui-key-exp">
            Expiration
          </label>
          <select id="uncoder-ui-key-exp" className="uncoder-ui-select" value={expiry} onChange={(e) => setExpiry(e.currentTarget.value)}>
            {EXPIRY.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </select>
        </div>
        <button type="submit" hidden aria-hidden tabIndex={-1} />
      </form>
    </Dialog>
  );
}

export function SecretDialog({ fresh, onClose, onShowSetup }: { fresh: FreshKey | null; onClose: () => void; onShowSetup: () => void }) {
  return (
    <Dialog
      open={!!fresh}
      onClose={onClose}
      dismissable={false}
      width={540}
      title="Copy your new API key"
      description={fresh ? `“${fresh.name}” was created.` : undefined}
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Done
          </Button>
          <Button variant="primary" iconRight="arrow-right" onClick={onShowSetup}>
            Set up a client
          </Button>
        </>
      }
    >
      {fresh && (
        <div className="uncoder-ui-form-stack">
          <CopyField value={fresh.secret} label="API key" what="API key copied" secret />
          <Callout tone="warning" title="This is the only time the key is shown">
            Store it in your client or a password manager now. Until you leave this page, the setup snippets include it automatically.
          </Callout>
        </div>
      )}
    </Dialog>
  );
}

/* ------------------------------------------------------------------ OAuth grants */

export function GrantsPanel({ grants }: { grants: Resource<Grant[]> }) {
  const revoke = async (g: Grant) => {
    const ok = await confirmDialog({
      title: `Disconnect ${g.client_name}?`,
      body: `${g.client_name} loses access for ${g.user || 'this user'} immediately. They can connect again later by signing in.`,
      confirmLabel: 'Disconnect',
      danger: true,
    });
    if (!ok) return;
    try {
      await mcpApi.revokeGrant(g.client_id, g.user_id);
      grants.setData((prev) => (prev ?? []).filter((x) => !(x.client_id === g.client_id && x.user_id === g.user_id)));
      toast(`${g.client_name} disconnected`);
    } catch (e) {
      toastError(e);
    }
  };

  return (
    <Card flush title="Connected apps" description="Apps that signed in with OAuth, such as Claude and ChatGPT connectors.">
      {grants.error && !grants.data ? (
        <ErrorState error={grants.error} onRetry={grants.reload} />
      ) : !grants.data ? (
        <SkeletonRows rows={2} cols={4} />
      ) : grants.data.length === 0 ? (
        <EmptyState icon="plug" title="No connected apps">
          When you add this site as a connector in Claude or ChatGPT and approve access, it appears here.
        </EmptyState>
      ) : (
        <div className="uncoder-ui-tablewrap">
          <table className="uncoder-ui-table">
            <thead>
              <tr>
                <th scope="col">App</th>
                <th scope="col">User</th>
                <th scope="col">Permissions</th>
                <th scope="col" className="uncoder-ui-col-hide-md">
                  Authorized
                </th>
                <th scope="col">Last used</th>
                <th scope="col" className="uncoder-ui-col-actions">
                  <span className="uncoder-ui-sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {grants.data.map((g) => (
                <tr key={g.client_id + ':' + g.user_id}>
                  <td>
                    <div className="uncoder-ui-namecell">
                      <span className="uncoder-ui-namecell__icon" aria-hidden>
                        <Icon name="plug" size={15} />
                      </span>
                      <div className="uncoder-ui-namecell__text">
                        <span className="uncoder-ui-namecell__title">{g.client_name}</span>
                        <span className="uncoder-ui-namecell__meta uncoder-ui-truncate" title={g.client_id}>
                          {g.client_id}
                        </span>
                      </div>
                    </div>
                  </td>
                  <td>{g.user || '—'}</td>
                  <td>
                    <Scopes scopes={g.scopes} />
                  </td>
                  <td className="uncoder-ui-col-hide-md">
                    <When value={g.created_at} />
                  </td>
                  <td>
                    <When value={g.last_used} />
                  </td>
                  <td className="uncoder-ui-col-actions">
                    <Button size="sm" variant="ghost" className="uncoder-ui-btn--danger-ghost" onClick={() => revoke(g)}>
                      Disconnect
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Card>
  );
}
