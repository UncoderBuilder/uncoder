// Settings → Client handoff (Site\Handoff, Agency licence): hand a finished site to the client. Everyone but the
// people chosen here, the client's administrators included, then changes texts, images and links only; layout,
// styles, the theme and the settings stay as built. Turning it on needs the licence; turning it off never does.
import { useEffect, useState } from 'react';
import { Button, Toggle } from '@editor/ui/primitives';
import { api, ApiError } from '../lib/api';
import { shortDate } from '../lib/format';
import { toast, toastError } from '../lib/toast';
import { Badge, Callout, Card, Checkbox, ErrorState, SettingRow, SkeletonRows } from '../ui/kit';

interface HandoffState {
  on: boolean;
  builders: number[];
  contact: string;
  since: string;
  me: number;
  users: { id: number; name: string; email: string; admin: boolean }[];
  allowed: boolean;
  pricing: string;
}

export function HandoffCard() {
  const [saved, setSaved] = useState<HandoffState | null>(null);
  const [draft, setDraft] = useState<HandoffState | null>(null);
  const [error, setError] = useState<Error | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<HandoffState>('handoff')
      .then((s) => {
        setSaved(s);
        setDraft(s);
      })
      .catch(setError);
  }, []);

  if (error) return <ErrorState error={error} />;
  if (!draft || !saved) {
    return (
      <Card title="Client handoff">
        <SkeletonRows rows={4} />
      </Card>
    );
  }

  // Without the licence only "turn it off" is possible (and only while it is on).
  const locked = !draft.allowed;
  const dirty = JSON.stringify(draft) !== JSON.stringify(saved);
  const toggleBuilder = (id: number, v: boolean) => setDraft({ ...draft, builders: v ? [...draft.builders, id] : draft.builders.filter((b) => b !== id) });

  const save = async (next: HandoffState) => {
    setBusy(true);
    try {
      const res = await api<HandoffState>('handoff', { body: { on: next.on, builders: next.builders, contact: next.contact } });
      setSaved(res);
      setDraft(res);
      toast(res.on ? 'The site is handed over.' : 'Handoff is off: everyone has their usual access again.', 'success');
    } catch (e) {
      toastError(e instanceof ApiError ? e : new Error('Client handoff could not be saved.'));
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="uncoder-ui-stack">
      <Card
        title={
          <>
            Client handoff {locked && <Badge tone="accent">Agency</Badge>}
            {saved.on && <Badge tone="success">Handed over</Badge>}
          </>
        }
        description="Hand the finished site to your client. They change texts, images and links on every page; layout, styles, the theme and the settings stay as you built them."
      >
        {locked && (
          <Callout
            tone={saved.on ? 'warning' : 'info'}
            title={saved.on ? 'The site stays handed over' : 'Client handoff comes with the Agency licence'}
            actions={
              <a className="uncoder-ui-btn uncoder-ui-btn--sm" href={draft.pricing} target="_blank" rel="noopener noreferrer">
                See the plans
              </a>
            }
          >
            {saved.on ? 'Changing who has full access needs an active Agency licence. You can turn handoff off at any time.' : 'Activate an Agency licence under Licence to hand sites over.'}
          </Callout>
        )}
        <SettingRow title="Hand this site over" description={saved.on && saved.since ? `Since ${shortDate(saved.since)}.` : 'Everyone without full access below edits content only, administrators included.'}>
          <Toggle checked={draft.on} disabled={locked && !saved.on} onChange={(v) => setDraft({ ...draft, on: v })} label="Hand this site over" />
        </SettingRow>
        <SettingRow title="Full access" description="People who keep designing the site: you, and anyone else on your team. You always keep full access.">
          <div className="uncoder-ui-handoff-users">
            {[...draft.users].sort((a, b) => Number(b.id === draft.me) - Number(a.id === draft.me)).map((u) => (
              <Checkbox
                key={u.id}
                checked={u.id === draft.me || draft.builders.includes(u.id)}
                disabled={u.id === draft.me || locked}
                onChange={(v) => toggleBuilder(u.id, v)}
                label={
                  <>
                    {u.name}
                    {u.id === draft.me && <span className="uncoder-ui-muted"> (you)</span>}
                    {u.admin && (
                      <>
                        {' '}
                        <Badge>Administrator</Badge>
                      </>
                    )}
                  </>
                }
                description={u.email}
              />
            ))}
          </div>
        </SettingRow>
        <SettingRow title="Contact for design changes" description="Shown to your client on the dashboard and in the editor." htmlFor="uncoder-ui-handoff-contact">
          <input
            id="uncoder-ui-handoff-contact"
            className="uncoder-ui-input"
            value={draft.contact}
            maxLength={120}
            placeholder="hello@youragency.com"
            disabled={locked}
            onChange={(e) => setDraft({ ...draft, contact: e.currentTarget.value })}
          />
        </SettingRow>
        <p className="uncoder-ui-muted uncoder-ui-handoff-note">
          Handoff prevents accidental design changes. It is not a lock against administrators: they can still manage plugins and WordPress itself.
        </p>
        <div className="uncoder-ui-wl-actions">
          {locked ? (
            saved.on && (
              <Button loading={busy} onClick={() => void save({ ...saved, on: false })}>
                Turn handoff off
              </Button>
            )
          ) : (
            <Button variant="primary" loading={busy} disabled={!dirty} onClick={() => void save(draft)}>
              Save
            </Button>
          )}
        </div>
      </Card>
    </div>
  );
}
