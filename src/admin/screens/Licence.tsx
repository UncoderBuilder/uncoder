// Settings → Licence (Licence\Licence): enter a key from uncoderbuilder.com, see the plan, sites and end date, check it
// again, or deactivate it on this site. Without a key Uncoder never contacts the licence server.
import { useEffect, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button } from '@editor/ui/primitives';
import { NAME } from '@shared/brand';
import { api, ApiError } from '../lib/api';
import { relativeTime, shortDate } from '../lib/format';
import { toast, toastError } from '../lib/toast';
import { Badge, Callout, Card, Field, SettingRow, SkeletonRows } from '../ui/kit';

type Feature = 'library' | 'sections' | 'ai_rewrite' | 'priority_support' | 'white_label' | 'private_library' | 'handoff' | 'reports';

export interface LicenceState {
  status: 'none' | 'active' | 'expired' | 'invalid' | 'unverified';
  plan: 'free' | 'pro' | 'agency';
  licence: { plan: string; status: string; expires: string | null; renews?: string | null; ends?: string | null; sites: { used: number; limit: number }; email: string } | null;
  hint: string;
  dev: boolean;
  checkedAt: string | null;
  error: { code: string; message: string } | null;
  features: Record<Feature, boolean>;
  plans: Record<Feature, 'pro' | 'agency'>;
  pricing: string;
  /** uncoderbuilder.com/account/ opened on "email me my keys". */
  findKey?: string;
}

const FEATURE_LABEL: Record<Feature, { label: string; help: string }> = {
  library: { label: 'Starter-site library', help: 'Every starter site, imported in one click, and new ones each month' },
  sections: { label: 'Premium section packs', help: 'More ready-made sections in the editor' },
  ai_rewrite: { label: 'Make it yours', help: 'Your AI app rewrites an imported starter for your business (no AI key needed)' },
  priority_support: { label: 'Priority support', help: 'Answers within one working day' },
  white_label: { label: 'White-label', help: 'Your brand in the WordPress admin and the AI connection' },
  private_library: { label: 'Private cloud library', help: 'Save your own sections and kits, reuse them on every site' },
  handoff: { label: 'Client handoff mode', help: 'Lock layouts and give clients a simple content editor' },
  reports: { label: 'Branded page-check reports', help: 'Page checks with your logo, for clients' },
};

const PLAN_LABEL = { free: 'Free', pro: 'Pro', agency: 'Agency' } as const;

export function LicenceCard() {
  const [state, setState] = useState<LicenceState | null>(null);
  const [error, setError] = useState<Error | null>(null);
  const [key, setKey] = useState('');
  const [busy, setBusy] = useState<'activate' | 'check' | 'deactivate' | null>(null);
  const [fieldError, setFieldError] = useState('');
  const [changing, setChanging] = useState(false);

  useEffect(() => {
    api<LicenceState>('licence').then(setState).catch(setError);
  }, []);

  const activate = async () => {
    setBusy('activate');
    setFieldError('');
    try {
      const next = await api<LicenceState>('licence', { body: { key } });
      setState(next);
      setKey('');
      setChanging(false);
      toast(`${PLAN_LABEL[next.plan]} licence activated on this site`);
    } catch (e) {
      if (e instanceof ApiError && e.status < 500) setFieldError(e.message);
      else toastError(e);
    } finally {
      setBusy(null);
    }
  };
  const check = async () => {
    setBusy('check');
    try {
      const next = await api<LicenceState>('licence/refresh', { method: 'POST' });
      setState(next);
      toast(next.status === 'active' ? 'Licence checked: all good' : 'Licence checked');
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(null);
    }
  };
  const deactivate = async () => {
    if (!window.confirm('Deactivate the licence on this site? Its slot is freed for another site. Everything you built here keeps working; the paid features lock.')) return;
    setBusy('deactivate');
    try {
      setState(await api<LicenceState>('licence', { method: 'DELETE' }));
      toast('Licence deactivated on this site');
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(null);
    }
  };

  if (error) return <Callout tone="danger" title="The licence could not be loaded">{error.message}</Callout>;
  if (!state) {
    return (
      <Card>
        <SkeletonRows rows={3} cols={2} />
      </Card>
    );
  }

  const keyForm = (
    <form
      className="uncoder-ui-licence-key"
      onSubmit={(e) => {
        e.preventDefault();
        if (key.trim()) void activate();
      }}
    >
      <Field
        label={changing ? 'New licence key' : 'Licence key'}
        htmlFor="uncoder-ui-licence-key"
        error={fieldError}
        help={
          <>
            You find it in the email you got with your purchase.{' '}
            {state?.findKey && (
              <a href={state.findKey} target="_blank" rel="noopener noreferrer">
                Lost it? Get it by email
              </a>
            )}
          </>
        }
      >
        <div className="uncoder-ui-licence-key__row">
          <input
            id="uncoder-ui-licence-key"
            className="uncoder-ui-input"
            value={key}
            onChange={(e) => setKey(e.currentTarget.value)}
            placeholder="UNC-XXXXX-XXXXX-XXXXX-XXXXX"
            spellCheck={false}
            autoComplete="off"
            aria-invalid={fieldError ? true : undefined}
          />
          <Button type="submit" variant="primary" icon="key-round" loading={busy === 'activate'} disabled={!key.trim()}>
            Activate
          </Button>
          {changing && (
            <Button
              onClick={() => {
                setChanging(false);
                setKey('');
                setFieldError('');
              }}
            >
              Cancel
            </Button>
          )}
        </div>
        {changing && state?.status !== 'none' && <p className="uncoder-ui-muted">The current key stops using a site here once the new one is active.</p>}
      </Field>
    </form>
  );

  const l = state.licence;
  const sites = l ? (l.sites.limit > 0 ? `${l.sites.used} of ${l.sites.limit} live sites` : `${l.sites.used} live ${l.sites.used === 1 ? 'site' : 'sites'} (unlimited)`) : '';
  const problem =
    state.status === 'expired'
      ? { tone: 'warning' as const, title: l?.expires ? `Your licence ended on ${shortDate(l.expires)}` : 'Your licence has ended', text: 'Everything you built keeps working. Renew to import from the library and use the paid features again.' }
      : state.status === 'invalid'
        ? { tone: 'danger' as const, title: 'This licence cannot be used on this site', text: state.error?.message ?? 'Check the key, or activate it again.' }
        : state.status === 'unverified'
          ? { tone: 'warning' as const, title: 'The licence could not be checked for a while', text: state.error?.message ?? `${NAME} could not reach uncoderbuilder.com. Check now, or try again later.` }
          : null;

  return (
    <div className="uncoder-ui-stack">
      <Card
        title={
          <>
            Licence {state.status !== 'none' && <Badge tone={state.status === 'active' ? 'success' : 'warning'}>{state.status === 'active' ? PLAN_LABEL[state.plan] : 'Not active'}</Badge>}
          </>
        }
        description={
          state.status === 'none' ? (
            <>
              {NAME} is free. A Pro or Agency licence adds the starter-site library and, with Agency, tools for client work.{' '}
              <a href={state.pricing} target="_blank" rel="noopener noreferrer">
                See the plans
              </a>
            </>
          ) : undefined
        }
      >
        {state.status === 'none' || changing ? (
          keyForm
        ) : (
          <>
            {problem && (
              <Callout
                tone={problem.tone}
                title={problem.title}
                actions={
                  <>
                    {state.status === 'expired' && (
                      <a className="uncoder-ui-btn uncoder-ui-btn--primary uncoder-ui-btn--sm" href={state.pricing} target="_blank" rel="noopener noreferrer">
                        Renew
                      </a>
                    )}
                    <Button size="sm" onClick={() => setChanging(true)}>
                      Enter another key
                    </Button>
                  </>
                }
              >
                {problem.text}
              </Callout>
            )}
            <SettingRow title="Key">
              <code>UNC-•••••-•••••-•••••-{state.hint}</code>
              <Button size="sm" icon="key-round" onClick={() => setChanging(true)}>
                Change key
              </Button>
            </SettingRow>
            {l?.email && <SettingRow title="Account">{l.email}</SettingRow>}
            {l && <SettingRow title="Plan">{l.plan === 'agency' ? 'Agency' : 'Pro'}</SettingRow>}
            {l && (
              <SettingRow title="Sites" description={state.dev ? 'This site counts as a staging or local copy: it does not use a site of the licence.' : undefined}>
                {sites}
              </SettingRow>
            )}
            {l && <SettingRow title={l.renews ? 'Renews' : l.expires ? 'Ends' : 'Term'}>{l.renews ? shortDate(l.renews) : l.expires ? shortDate(l.ends || l.expires) : 'Lifetime'}</SettingRow>}
            <SettingRow title="Last checked" description={state.error && state.status === 'active' ? state.error.message : undefined}>
              <span className="uncoder-ui-licence-checked">{state.checkedAt ? relativeTime(state.checkedAt) : 'Never'}</span>
              <Button size="sm" icon="refresh-cw" loading={busy === 'check'} onClick={check}>
                Check now
              </Button>
            </SettingRow>
            <SettingRow title="This site" description="Frees this site's slot for another site. What you built here keeps working." danger>
              <Button size="sm" variant="danger" loading={busy === 'deactivate'} onClick={deactivate}>
                Deactivate on this site
              </Button>
            </SettingRow>
          </>
        )}
      </Card>
      <Card title="What a licence unlocks" description="The builder itself is the same in every plan.">
        <ul className="uncoder-ui-licence-features">
          {(Object.keys(FEATURE_LABEL) as Feature[]).map((f) => {
            const on = state.features[f];
            return (
              <li key={f} className={on ? 'is-on' : undefined}>
                <Icon name={on ? 'check' : 'lock'} size={16} />
                <span className="uncoder-ui-licence-features__text">
                  <strong>{FEATURE_LABEL[f].label}</strong>
                  <span className="uncoder-ui-muted">{FEATURE_LABEL[f].help}</span>
                </span>
                {!on && <Badge tone={state.plans[f] === 'agency' ? 'accent' : 'info'}>{state.plans[f] === 'agency' ? 'Agency' : 'Pro'}</Badge>}
              </li>
            );
          })}
        </ul>
      </Card>
    </div>
  );
}
