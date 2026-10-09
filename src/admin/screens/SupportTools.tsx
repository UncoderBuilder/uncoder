import { useEffect, useState } from 'react';
import { Button, Toggle } from '@editor/ui/primitives';
import { NAME } from '@shared/brand';
import { api } from '../lib/api';
import { copyText, useResource } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { confirmDialog } from '../ui/Dialog';
import { Callout, Card, SettingRow, SkeletonRows } from '../ui/kit';

type Info = Record<string, Record<string, string>>;

/** Session flag (and window event) set by the app bar's "System info": open that card and bring it into view. */
export const OPEN_FLAG = 'uncoder-ui-open';

/** Settings → Tools: system info, safe mode, version rollback (Site\Support_Tools). */
export function SupportToolsCards() {
  return (
    <>
      <SafeModeCard />
      <RollbackCard />
      <SystemInfoCard />
    </>
  );
}

function SystemInfoCard() {
  const [open, setOpen] = useState(false);
  // Arriving from the app bar's "System info" (a new page load or a tab switch): open and scroll to the card.
  useEffect(() => {
    const check = () => {
      let wanted = false;
      try {
        wanted = sessionStorage.getItem(OPEN_FLAG) === 'system-info';
        if (wanted) sessionStorage.removeItem(OPEN_FLAG);
      } catch {
        /* storage blocked */
      }
      if (!wanted) return;
      setOpen(true);
      window.setTimeout(() => document.getElementById('system-info')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 80);
    };
    check();
    window.addEventListener(OPEN_FLAG, check);
    return () => window.removeEventListener(OPEN_FLAG, check);
  }, []);
  const info = useResource((signal) => (open ? api<Info>('system-info', { signal }) : Promise.resolve(null)), [open]);
  const text = (data: Info) =>
    Object.entries(data)
      .map(([section, rows]) => `### ${section}\n` + Object.entries(rows).map(([k, v]) => `${k}: ${v}`).join('\n'))
      .join('\n\n');
  return (
    <Card
      id="system-info"
      title="System info"
      description={`Versions and settings support needs to help: WordPress, server, theme, plugins and ${NAME}. Nothing is sent anywhere; copy it into your message.`}
      actions={
        open && info.data ? (
          <Button size="sm" icon="copy" onClick={() => copyText(text(info.data!), 'System info copied')}>
            Copy for support
          </Button>
        ) : (
          <Button size="sm" icon="activity" onClick={() => setOpen(true)}>
            Show
          </Button>
        )
      }
    >
      {open &&
        (!info.data ? (
          <SkeletonRows rows={6} cols={2} />
        ) : (
          <div className="uncoder-ui-sysinfo">
            {Object.entries(info.data).map(([section, rows]) => (
              <section key={section}>
                <h3>{section}</h3>
                <dl>
                  {Object.entries(rows).map(([k, v]) => (
                    <div key={k}>
                      <dt>{k}</dt>
                      <dd>{v || '—'}</dd>
                    </div>
                  ))}
                </dl>
              </section>
            ))}
          </div>
        ))}
    </Card>
  );
}

interface SafeState {
  enabled: boolean;
  mine: boolean;
  possible: boolean;
}

function SafeModeCard() {
  const state = useResource((signal) => api<SafeState>('safe-mode', { signal }), []);
  const [busy, setBusy] = useState(false);
  const s = state.data;
  const set = async (enabled: boolean) => {
    setBusy(true);
    try {
      state.setData(await api<SafeState>('safe-mode', { body: { enabled } }));
      toast(enabled ? 'Safe mode is on for this browser: open the builder to test.' : 'Safe mode is off');
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(false);
    }
  };
  return (
    <Card title="Safe mode" description={`If the builder does not load or behaves oddly, turn this on and open the builder again. It then runs with only ${NAME} active and a default theme — just for you, in this browser. Visitors and other editors see the site as usual.`}>
      {!s ? (
        <SkeletonRows rows={1} cols={2} />
      ) : (
        <div className="uncoder-ui-setlist">
          <SettingRow title="Safe mode" description={s.enabled ? (s.mine ? 'On in this browser. If the builder works now, another plugin or your theme conflicts with it: turn plugins back on one by one to find it.' : 'On for another browser or user.') : 'Off'}>
            <Toggle checked={s.enabled} disabled={busy || (!s.possible && !s.enabled)} onChange={set} label="Safe mode" />
          </SettingRow>
          {!s.possible && !s.enabled && <Callout tone="warning">wp-content/mu-plugins is not writable, so safe mode cannot be turned on.</Callout>}
        </div>
      )}
    </Card>
  );
}

interface Versions {
  current: string;
  dev: boolean;
  versions: string[];
  message: string;
}

function RollbackCard() {
  const data = useResource((signal) => api<Versions>('rollback', { signal }), []);
  const [version, setVersion] = useState('');
  const [busy, setBusy] = useState(false);
  const d = data.data;
  const run = async () => {
    if (!version) return;
    if (!(await confirmDialog({ title: `Reinstall ${NAME} ${version}?`, body: `${NAME} ${d?.current} is replaced with ${version}. Your designs and settings stay. Update again from the Plugins screen at any time.`, confirmLabel: 'Reinstall', danger: true }))) return;
    setBusy(true);
    try {
      await api('rollback', { body: { version } });
      toast(`${NAME} ${version} is installed. Reloading…`, 'success');
      window.setTimeout(() => window.location.reload(), 1200);
    } catch (e) {
      toastError(e);
      setBusy(false);
    }
  };
  return (
    <Card title="Version rollback" description={`Had a problem after an update? Download an earlier ${NAME} version from GitHub and upload it under Plugins → Add New. Back up the site first.`}>
      {!d ? (
        <SkeletonRows rows={1} cols={2} />
      ) : d.versions.length ? (
        <div className="uncoder-ui-inline">
          <select className="uncoder-ui-select" value={version} onChange={(e) => setVersion(e.currentTarget.value)} aria-label="Version">
            <option value="">Choose a version…</option>
            {d.versions.map((v) => (
              <option key={v} value={v}>
                {v}
              </option>
            ))}
          </select>
          <Button variant="primary" icon="history" disabled={!version} loading={busy} onClick={run}>
            Reinstall
          </Button>
        </div>
      ) : (
        <p className="uncoder-ui-muted">
          Current version {d.current}. {d.message || 'No earlier versions are available.'}
        </p>
      )}
    </Card>
  );
}
