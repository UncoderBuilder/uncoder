import { useEffect, useState } from 'react';
import { Button, Toggle } from '@editor/ui/primitives';
import { NAME } from '@shared/brand';
import { api, type PluginSettings } from '../lib/api';
import { cfg, screenUrl } from '../lib/config';
import { formatMs } from '../lib/format';
import { useHashState, useResource, useUnsavedGuard } from '../lib/hooks';
import { LicenceCard } from './Licence';
import { WhiteLabelCard } from './WhiteLabel';
import { HandoffCard } from './Handoff';
import { ElementManager } from './Elements';
import { SupportToolsCards } from './SupportTools';
import { toast, toastError } from '../lib/toast';
import { Callout, Card, Checkbox, Embedded, ErrorState, PageHeader, SaveBar, SectionNav, SettingRow, SkeletonRows, Workspace, useSubCrumb, type NavGroup } from '../ui/kit';
import { CodeScreen } from './Code';
import { LookupSelect, type Picked } from '../ui/LookupSelect';
import { downloadExport } from '../lib/transfer';
import { ImportDialog } from '../templates/ImportDialog';
import { FindReplaceDialog } from '../ui/FindReplaceDialog';

type Draft = Pick<PluginSettings, 'postTypes' | 'fontDelivery' | 'applyKit' | 'removeData' | 'formRetentionDays' | 'maintenance' | 'roleAccess' | 'consent' | 'performance' | 'business'> & {
  captcha: { provider: string; site_key: string; secret: string; min_score: number };
  /** Write-only API keys: empty keeps the saved one, "-" removes it. */
  integrations: Record<IntegrationService, { key: string; url?: string }>;
  /** Widgets taken out of the editor's Insert panel (Element manager). */
  disabledWidgets: string[];
};

type IntegrationService = 'mailchimp' | 'mailerlite' | 'brevo' | 'activecampaign';
const INTEGRATIONS: Array<{ id: IntegrationService; label: string; help: string }> = [
  { id: 'mailchimp', label: 'Mailchimp', help: 'Account → Extras → API keys. The key ends in your data center (e.g. -us21).' },
  { id: 'mailerlite', label: 'MailerLite', help: 'Integrations → API → Generate new token.' },
  { id: 'brevo', label: 'Brevo', help: 'SMTP & API → API keys → Generate a new API key (v3).' },
  { id: 'activecampaign', label: 'ActiveCampaign', help: 'Settings → Developer: the API key, plus the API URL below.' },
];

const pick = (s: PluginSettings): Draft => ({
  postTypes: [...s.postTypes].sort(),
  fontDelivery: s.fontDelivery,
  applyKit: s.applyKit,
  removeData: s.removeData,
  formRetentionDays: s.formRetentionDays ?? 0,
  maintenance: { ...s.maintenance, roles: [...s.maintenance.roles].sort() },
  roleAccess: Object.fromEntries(Object.entries(s.roleAccess ?? {}).sort(([a], [b]) => a.localeCompare(b))) as Draft['roleAccess'],
  // The secret is write-only: empty keeps the stored one.
  captcha: { provider: s.captcha?.provider ?? '', site_key: s.captcha?.site_key ?? '', secret: '', min_score: s.captcha?.min_score ?? 0.5 },
  integrations: { mailchimp: { key: '' }, mailerlite: { key: '' }, brevo: { key: '' }, activecampaign: { key: '', url: s.integrations?.activecampaign?.url ?? '' } },
  disabledWidgets: [...(s.disabledWidgets ?? [])].sort(),
  consent: { ...s.consent },
  performance: { lcp: s.performance?.lcp ?? true, inline_css: s.performance?.inline_css ?? false, lazy_bg: s.performance?.lazy_bg ?? true },
  business: { ...s.business, hours: [...(s.business?.hours ?? [])], same_as: [...(s.business?.same_as ?? [])] },
});

const BUSINESS_FIELDS: Array<{ key: 'name' | 'phone' | 'email' | 'street' | 'city' | 'region' | 'postal' | 'country' | 'area' | 'price_range'; label: string; placeholder?: string; local?: boolean }> = [
  { key: 'name', label: 'Business name', placeholder: 'Site title' },
  { key: 'phone', label: 'Phone', placeholder: '+1 512 555 0100' },
  { key: 'email', label: 'Email' },
  { key: 'street', label: 'Street address' },
  { key: 'city', label: 'City' },
  { key: 'region', label: 'State / region' },
  { key: 'postal', label: 'Postal code' },
  { key: 'country', label: 'Country code', placeholder: 'US' },
  { key: 'area', label: 'Area served', placeholder: 'Central Texas' },
  { key: 'price_range', label: 'Price range', placeholder: '$$', local: true },
];

const ACCESS: Array<{ value: 'full' | 'content' | 'none'; label: string }> = [
  { value: 'full', label: 'Full access' },
  { value: 'content', label: 'Content only' },
  { value: 'none', label: 'No access' },
];

const MODES: Array<{ value: Draft['maintenance']['mode']; label: string; help: string }> = [
  { value: '', label: 'Off', help: 'Everyone sees the site.' },
  { value: 'coming_soon', label: 'Coming soon', help: 'Visitors see a landing page (HTTP 200, can be indexed) while you build.' },
  { value: 'maintenance', label: 'Maintenance', help: 'Visitors see a notice with HTTP 503, so search engines keep your pages and check back later.' },
];

const SECTIONS = ['general', 'elements', 'performance', 'access', 'privacy', 'forms', 'seo', 'code', 'tools', 'advanced', 'licence', 'white-label', 'handoff', 'transfer'] as const;
type Section = (typeof SECTIONS)[number];
const SECTION_LABEL: Record<Section, string> = {
  general: 'General',
  licence: 'Licence',
  'white-label': 'White-label',
  handoff: 'Client handoff',
  performance: 'Performance & fonts',
  access: 'Site access',
  privacy: 'Cookie consent',
  forms: 'Forms',
  seo: 'Business & SEO',
  elements: 'Elements',
  transfer: 'Import & export',
  code: 'Custom code',
  tools: 'Tools',
  advanced: 'Advanced',
};
/** Which section each draft field lives in (for the “unsaved” dot in the nav). */
const FIELD_SECTION: Record<keyof Draft, Section> = {
  postTypes: 'general',
  fontDelivery: 'performance',
  applyKit: 'performance',
  performance: 'performance',
  maintenance: 'access',
  roleAccess: 'general',
  consent: 'privacy',
  captcha: 'forms',
  integrations: 'forms',
  formRetentionDays: 'forms',
  disabledWidgets: 'elements',
  business: 'seo',
  removeData: 'advanced',
};

export function SettingsScreen() {
  const [section, setSection] = useHashState(SECTIONS, 'general');
  useEffect(() => {
    // Import & export moved to the Library: old links (Settings → Import & export) land there.
    if (section === 'transfer') window.location.replace(screenUrl('uncoder-library', 'import'));
  }, [section]);
  useSubCrumb(section === 'general' ? null : SECTION_LABEL[section]);
  const settings = useResource((signal) => api<PluginSettings>('settings', { signal }), []);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [saving, setSaving] = useState(false);
  const [regen, setRegen] = useState<{ busy: boolean; result?: string }>({ busy: false });
  const [labels, setLabels] = useState<Record<number, string>>({});
  const [importing, setImporting] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [finding, setFinding] = useState(false);
  const s = settings.data;

  useEffect(() => {
    if (s) setDraft(pick(s));
  }, [s]);

  const dirty = !!s && !!draft && JSON.stringify({ ...draft, postTypes: [...draft.postTypes].sort() }) !== JSON.stringify(pick(s));
  useUnsavedGuard(dirty);

  const save = async () => {
    if (!draft) return;
    setSaving(true);
    try {
      const res = await api<PluginSettings>('settings', { body: draft });
      settings.setData(res);
      toast('Settings saved');
    } catch (e) {
      toastError(e);
    } finally {
      setSaving(false);
    }
  };

  const regenerate = async () => {
    setRegen({ busy: true });
    try {
      const res = await api<{ documents: number; kit: boolean; ms: number }>('settings/regenerate-css', { method: 'POST' });
      const msg = `Regenerated ${res.documents} stylesheet${res.documents === 1 ? '' : 's'}${res.kit ? ' and the Design System' : ''} in ${formatMs(res.ms)}.`;
      setRegen({ busy: false, result: msg });
      toast(msg);
    } catch (e) {
      setRegen({ busy: false });
      toastError(e);
    }
  };

  if (settings.error && !s) {
    return (
      <>
        <PageHeader title="Settings" />
        <ErrorState error={settings.error} onRetry={settings.reload} />
      </>
    );
  }

  const saved = s ? pick(s) : null;
  const changed = new Set<Section>(
    draft && saved ? (Object.keys(FIELD_SECTION) as Array<keyof Draft>).filter((k) => JSON.stringify(k === 'postTypes' ? [...draft.postTypes].sort() : draft[k]) !== JSON.stringify(saved[k])).map((k) => FIELD_SECTION[k]) : [],
  );
  const item = (id: Section, icon: string) => ({
    id,
    icon,
    label: SECTION_LABEL[id],
    flag: changed.has(id) ? ('dirty' as const) : id === 'access' && s?.maintenance.mode ? ('warning' as const) : null,
    flagLabel: changed.has(id) ? 'Unsaved changes' : id === 'access' && s?.maintenance.mode ? 'The site is closed to visitors' : undefined,
  });
  const nav: Array<NavGroup<Section>> = [
    { label: 'Builder', items: [item('general', 'sliders-horizontal'), item('elements', 'blocks'), item('performance', 'gauge')] },
    { label: 'Visitors', items: [item('access', 'lock-keyhole'), item('privacy', 'cookie'), item('forms', 'shield-check'), item('seo', 'building-2')] },
    { label: 'Developer', items: [item('code', 'code-xml'), item('tools', 'wrench'), item('advanced', 'settings-2')] },
    ...(cfg.licensing ? [{ label: 'Account', items: [item('licence', 'key-round'), item('white-label', 'tag'), item('handoff', 'hand-helping')] }] : []),
  ];

  const set = <K extends keyof Draft>(key: K, value: Draft[K]) => setDraft((d) => (d ? { ...d, [key]: value } : d));
  const setAccess = (patch: Partial<Draft['maintenance']>) => setDraft((d) => (d ? { ...d, maintenance: { ...d.maintenance, ...patch } } : d));

  return (
    <>
      <PageHeader title="Settings" description={`How ${NAME} works on this site.`} />
      <Workspace nav={<SectionNav<Section> label="Settings sections" groups={nav} value={section} onChange={setSection} />}>
        {section === 'licence' && cfg.licensing ? (
          <LicenceCard />
        ) : section === 'white-label' && cfg.licensing ? (
          <WhiteLabelCard />
        ) : section === 'handoff' && cfg.licensing ? (
          <HandoffCard />
        ) : section === 'code' ? (
          <Embedded>
            <CodeScreen />
          </Embedded>
        ) : !s || !draft || section === 'transfer' ? (
          <Card>
            <SkeletonRows rows={5} cols={2} />
          </Card>
        ) : (
          <div className="uncoder-ui-stack">
            {section === 'general' && (
              <>
                <Card title="Builder" description={`Content types that can be edited with ${NAME}. Theme templates are always enabled.`}>
                  <div className="uncoder-ui-setlist">
                    <SettingRow title="Post types" description={`Adds “Edit with ${NAME}” to these types. Disabling a type keeps existing designs; they just can’t be opened in the builder.`}>
                      <div className="uncoder-ui-checklist">
                        {s.availablePostTypes.map((pt) => (
                          <Checkbox
                            key={pt.name}
                            label={pt.label}
                            description={pt.name}
                            checked={draft.postTypes.includes(pt.name)}
                            onChange={(v) => set('postTypes', v ? [...draft.postTypes, pt.name] : draft.postTypes.filter((x) => x !== pt.name))}
                          />
                        ))}
                      </div>
                    </SettingRow>
                  </div>
                </Card>
                <Card id="roles" title="Roles" description={`Who can use ${NAME}. “Content only” lets people change texts, images and links of existing designs — not add, move, delete or restyle anything. Administrators always have full access.`}>
                  <div className="uncoder-ui-setlist">
                    {s.roles
                      .filter((r) => r.value !== 'administrator' && r.edits)
                      .map((r) => {
                        const level = draft.roleAccess[r.value] ?? 'full';
                        return (
                          <SettingRow key={r.value} title={r.label} htmlFor={`uncoder-ui-role-${r.value}`} danger={level === 'none'}>
                            <select
                              id={`uncoder-ui-role-${r.value}`}
                              className="uncoder-ui-select"
                              value={level}
                              onChange={(e) => {
                                const v = e.currentTarget.value as 'full' | 'content' | 'none';
                                const next = { ...draft.roleAccess };
                                if (v === 'full') delete next[r.value];
                                else next[r.value] = v;
                                set('roleAccess', Object.fromEntries(Object.entries(next).sort(([a], [b]) => a.localeCompare(b))) as Draft['roleAccess']);
                              }}
                            >
                              {ACCESS.map((a) => (
                                <option key={a.value} value={a.value}>
                                  {a.label}
                                </option>
                              ))}
                            </select>
                          </SettingRow>
                        );
                      })}
                  </div>
                </Card>
              </>
            )}
            {section === 'performance' && (
              <>
                <Card title="Fonts & styles">
                  <div className="uncoder-ui-setlist">
                    <SettingRow title="Font delivery" description="Where Design System and element fonts load from. Choose “Do not load fonts” if your theme or a font plugin already provides them (this also stops requests to Google Fonts)." htmlFor="uncoder-ui-set-fonts">
                      <select id="uncoder-ui-set-fonts" className="uncoder-ui-select" value={draft.fontDelivery} onChange={(e) => set('fontDelivery', e.currentTarget.value)}>
                        {s.fontDeliveryOptions.map((o) => (
                          <option key={o.value} value={o.value}>
                            {o.label}
                          </option>
                        ))}
                      </select>
                    </SettingRow>
                    <SettingRow title={`Load ${NAME} styles on every page`} description={`Loads the base CSS and your Design System (colors, fonts, theme styles for text, links and buttons) on all front-end pages, not only on pages built with ${NAME}.`}>
                      <Toggle checked={draft.applyKit} onChange={(v) => set('applyKit', v)} label={`Load ${NAME} styles on every page`} />
                    </SettingRow>
                  </div>
                </Card>
                <Card id="performance" title="Performance" description="Loading speed for visitors. Also keep images imported (not hotlinked) and use few font families.">
                  <div className="uncoder-ui-setlist">
                    <SettingRow title="Load the hero image first" description="The first image of each page’s first section (usually what visitors see first) loads right away with high priority instead of lazily; background images are preloaded. Improves Largest Contentful Paint.">
                      <Toggle checked={draft.performance.lcp} onChange={(v) => set('performance', { ...draft.performance, lcp: v })} label="Load the hero image first" />
                    </SettingRow>
                    <SettingRow title="Inline small stylesheets" description="Prints the Design System and page CSS into the page when they are under 16 KB, saving render-blocking requests. Larger files stay separate so browsers can cache them.">
                      <Toggle checked={draft.performance.inline_css} onChange={(v) => set('performance', { ...draft.performance, inline_css: v })} label="Inline small stylesheets" />
                    </SettingRow>
                    <SettingRow title="Lazy-load background images" description="Background images of sections further down the page load only when visitors scroll near them. The first section always loads right away.">
                      <Toggle checked={draft.performance.lazy_bg} onChange={(v) => set('performance', { ...draft.performance, lazy_bg: v })} label="Lazy-load background images" />
                    </SettingRow>
                  </div>
                </Card>
              </>
            )}
            {section === 'access' && (
              <>
                <Card id="access" title="Site access" description="Close the site to visitors while you build or fix it. Administrators always see the real site; wp-admin and the login page keep working.">
                  <div className="uncoder-ui-setlist">
                    <SettingRow title="Mode" description={MODES.find((m) => m.value === draft.maintenance.mode)?.help} danger={draft.maintenance.mode !== ''}>
                      <select className="uncoder-ui-select" value={draft.maintenance.mode} aria-label="Site access mode" onChange={(e) => setAccess({ mode: e.currentTarget.value as Draft['maintenance']['mode'] })}>
                        {MODES.map((m) => (
                          <option key={m.value} value={m.value}>
                            {m.label}
                          </option>
                        ))}
                      </select>
                    </SettingRow>
                    {draft.maintenance.mode !== '' && (
                      <>
                        <SettingRow title="Page to show" description={`Any page built with ${NAME} (a draft keeps it out of menus and sitemaps). Leave empty for a simple built-in notice.`}>
                          <div className="uncoder-ui-stack uncoder-ui-stack--tight">
                            <LookupSelect
                              source="posts"
                              filter="page"
                              label="Page to show"
                              placeholder="Search pages…"
                              value={pagePick(draft.maintenance.page, s, labels)}
                              onChange={(v: Picked[]) => {
                                const last = v[v.length - 1];
                                if (last) setLabels((l) => ({ ...l, [last.id]: last.label }));
                                setAccess({ page: last ? last.id : 0 });
                              }}
                            />
                            {s.maintenancePage && s.maintenancePage.id === draft.maintenance.page && (
                              <a className="uncoder-ui-link" href={s.maintenancePage.edit}>
                                Edit “{s.maintenancePage.title}” with {NAME}
                              </a>
                            )}
                          </div>
                        </SettingRow>
                        <SettingRow title="Who can see the site" description="Logged-in users with access browse the real site as usual.">
                          <div className="uncoder-ui-stack uncoder-ui-stack--tight">
                            <select className="uncoder-ui-select" value={draft.maintenance.access} aria-label="Who can see the site" onChange={(e) => setAccess({ access: e.currentTarget.value as Draft['maintenance']['access'] })}>
                              <option value="logged_in">Any logged-in user</option>
                              <option value="roles">Only these roles</option>
                            </select>
                            {draft.maintenance.access === 'roles' && (
                              <div className="uncoder-ui-checklist">
                                {s.roles
                                  .filter((r) => r.value !== 'administrator')
                                  .map((r) => (
                                    <Checkbox
                                      key={r.value}
                                      label={r.label}
                                      checked={draft.maintenance.roles.includes(r.value)}
                                      onChange={(v) => setAccess({ roles: (v ? [...draft.maintenance.roles, r.value] : draft.maintenance.roles.filter((x) => x !== r.value)).sort() })}
                                    />
                                  ))}
                              </div>
                            )}
                          </div>
                        </SettingRow>
                      </>
                    )}
                  </div>
                </Card>
              </>
            )}
            {section === 'privacy' && (
              <>
                <Card id="consent" title="Cookie consent" description="A banner that asks visitors before analytics and marketing code runs. Mark those snippets in Custom Code with their category; they wait until the visitor agrees. Rejecting is as easy as accepting.">
                  <div className="uncoder-ui-setlist">
                    <SettingRow title="Show the banner" description="Visitors see it until they choose; the choice is remembered for 180 days.">
                      <Toggle checked={draft.consent.enabled} onChange={(v) => set('consent', { ...draft.consent, enabled: v })} label="Show the cookie banner" />
                    </SettingRow>
                    {draft.consent.enabled && (
                      <>
                        <SettingRow title="Position" htmlFor="uncoder-ui-consent-pos">
                          <select id="uncoder-ui-consent-pos" className="uncoder-ui-select" value={draft.consent.position} onChange={(e) => set('consent', { ...draft.consent, position: e.currentTarget.value as Draft['consent']['position'] })}>
                            <option value="bar">Bar along the bottom</option>
                            <option value="box-left">Box, bottom left</option>
                            <option value="box-right">Box, bottom right</option>
                          </select>
                        </SettingRow>
                        <SettingRow title="Title and message" description="Leave empty for the default text. Links are allowed in the message.">
                          <div className="uncoder-ui-stack uncoder-ui-stack--tight">
                            <input className="uncoder-ui-input" placeholder="Cookies & privacy" value={draft.consent.title} aria-label="Banner title" onChange={(e) => set('consent', { ...draft.consent, title: e.currentTarget.value })} />
                            <textarea className="uncoder-ui-textarea" rows={3} placeholder="We use cookies to understand how the site is used…" value={draft.consent.message} aria-label="Banner message" onChange={(e) => set('consent', { ...draft.consent, message: e.currentTarget.value })} />
                          </div>
                        </SettingRow>
                        <SettingRow title="Button labels">
                          <div className="uncoder-ui-formgrid uncoder-ui-formgrid--three">
                            <input className="uncoder-ui-input" placeholder="Accept all" value={draft.consent.accept_label} aria-label="Accept button" onChange={(e) => set('consent', { ...draft.consent, accept_label: e.currentTarget.value })} />
                            <input className="uncoder-ui-input" placeholder="Reject all" value={draft.consent.reject_label} aria-label="Reject button" onChange={(e) => set('consent', { ...draft.consent, reject_label: e.currentTarget.value })} />
                            <input className="uncoder-ui-input" placeholder="Preferences" value={draft.consent.prefs_label} aria-label="Preferences button" onChange={(e) => set('consent', { ...draft.consent, prefs_label: e.currentTarget.value })} />
                          </div>
                        </SettingRow>
                        <SettingRow title="Privacy policy link" htmlFor="uncoder-ui-consent-privacy" description="Empty: the page set in Settings → Privacy.">
                          <input id="uncoder-ui-consent-privacy" className="uncoder-ui-input" placeholder="https://" value={draft.consent.privacy_url} onChange={(e) => set('consent', { ...draft.consent, privacy_url: e.currentTarget.value })} />
                        </SettingRow>
                        <SettingRow title="Google Consent Mode v2" description="Tells Google tags (Analytics, Ads, Tag Manager) that storage is denied until the visitor agrees. Keep those tags in the “Necessary” category when this is on.">
                          <Toggle checked={draft.consent.consent_mode} onChange={(v) => set('consent', { ...draft.consent, consent_mode: v })} label="Google Consent Mode v2" />
                        </SettingRow>
                        <SettingRow title="Cookie settings button" description="A small round button to change the choice later. Any link to #uncoder-consent opens the preferences too.">
                          <Toggle checked={draft.consent.reopen} onChange={(v) => set('consent', { ...draft.consent, reopen: v })} label="Show a cookie settings button" />
                        </SettingRow>
                        <SettingRow title="Ask everyone again" description={`Shows the banner to every visitor again, e.g. after adding a new tracking tool. Current version: ${s.consent.version}.${dirty ? ' Save your changes first.' : ''}`}>
                          <Button
                            icon="refresh-cw"
                            disabled={dirty}
                            onClick={async () => {
                              try {
                                const res = await api<PluginSettings>('settings', { body: { consent: { ...draft.consent, renew: true } } });
                                settings.setData(res);
                                toast('Visitors will be asked again');
                              } catch (e) {
                                toastError(e);
                              }
                            }}
                          >
                            Ask again
                          </Button>
                        </SettingRow>
                      </>
                    )}
                  </div>
                </Card>
              </>
            )}
            {section === 'forms' && (
              <>
                <Card id="captcha" title="Form CAPTCHA" description="Optional extra spam check for forms that turn on “CAPTCHA”, on top of the built-in honeypot, timing and rate limit. The provider’s script loads only on pages with such a form.">
                  <div className="uncoder-ui-setlist">
                    <SettingRow title="Provider" htmlFor="uncoder-ui-cap-provider" description="Cloudflare Turnstile is free and usually invisible to visitors. reCAPTCHA v3 is always invisible: it scores each visit instead of asking a question.">
                      <select id="uncoder-ui-cap-provider" className="uncoder-ui-select" value={draft.captcha.provider} onChange={(e) => set('captcha', { ...draft.captcha, provider: e.currentTarget.value })}>
                        <option value="">Off</option>
                        <option value="turnstile">Cloudflare Turnstile</option>
                        <option value="hcaptcha">hCaptcha</option>
                        <option value="recaptcha_v2">Google reCAPTCHA v2 (checkbox)</option>
                        <option value="recaptcha">Google reCAPTCHA v3 (invisible)</option>
                      </select>
                    </SettingRow>
                    {draft.captcha.provider && (
                      <>
                        <SettingRow title="Site key" htmlFor="uncoder-ui-cap-key">
                          <input id="uncoder-ui-cap-key" className="uncoder-ui-input" value={draft.captcha.site_key} autoComplete="off" spellCheck={false} onChange={(e) => set('captcha', { ...draft.captcha, site_key: e.currentTarget.value.trim() })} />
                        </SettingRow>
                        <SettingRow title="Secret key" htmlFor="uncoder-ui-cap-secret" description={s.captcha?.has_secret ? 'A secret is saved. Leave empty to keep it.' : 'Stays on the server; never shown again after saving.'}>
                          <input id="uncoder-ui-cap-secret" className="uncoder-ui-input" type="password" value={draft.captcha.secret} autoComplete="new-password" placeholder={s.captcha?.has_secret ? '•••••••• saved' : ''} onChange={(e) => set('captcha', { ...draft.captcha, secret: e.currentTarget.value.trim() })} />
                        </SettingRow>
                        {draft.captcha.provider === 'recaptcha' && (
                          <SettingRow title="Minimum score" htmlFor="uncoder-ui-cap-score" description="Visits scoring below this are rejected (0.1 lets almost everyone through, 0.9 is strict). Google suggests 0.5.">
                            <input id="uncoder-ui-cap-score" className="uncoder-ui-input uncoder-ui-input--narrow" type="number" min={0.1} max={0.9} step={0.1} value={draft.captcha.min_score} onChange={(e) => set('captcha', { ...draft.captcha, min_score: Number(e.currentTarget.value) || 0.5 })} />
                          </SettingRow>
                        )}
                      </>
                    )}
                  </div>
                </Card>
                <Card id="retention" title="Stored submissions" description={`Form submissions are kept in WordPress (${NAME} → Submissions) and covered by the personal data export and erase tools under Tools. Keep them only as long as you need them.`}>
                  <div className="uncoder-ui-setlist">
                    <SettingRow title="Delete submissions after" htmlFor="uncoder-ui-retention" description={draft.formRetentionDays > 0 ? `Submissions (and their uploaded files) older than ${draft.formRetentionDays} days are deleted automatically once a day. Spam is always deleted after 30 days.` : 'Keep submissions until you delete them. Spam is always deleted after 30 days.'}>
                      <div className="uncoder-ui-inline">
                        <input id="uncoder-ui-retention" className="uncoder-ui-input uncoder-ui-input--narrow" type="number" min={0} max={3650} step={1} value={draft.formRetentionDays} onChange={(e) => set('formRetentionDays', Math.max(0, Math.min(3650, Math.floor(Number(e.currentTarget.value) || 0))))} />
                        <span>days (0 = never)</span>
                      </div>
                    </SettingRow>
                  </div>
                </Card>
                <Card id="integrations" title="Newsletter services" description="Connect a mailing list service, then turn on “Newsletter” in a form’s settings to add people who submit it. Keys stay on the server: they are never shown again, sent to the editor or included in exports.">
                  <div className="uncoder-ui-setlist">
                    {INTEGRATIONS.map((svc) => {
                      const connected = !!s.integrations?.[svc.id]?.connected;
                      const value = draft.integrations[svc.id].key;
                      const removing = value === '-';
                      return (
                        <SettingRow key={svc.id} title={svc.label} htmlFor={`uncoder-ui-int-${svc.id}`} description={removing ? 'The saved key is removed when you save.' : connected ? `Connected. Paste a new key to replace it. ${svc.help}` : svc.help}>
                          <div className="uncoder-ui-inline">
                            <input
                              id={`uncoder-ui-int-${svc.id}`}
                              className="uncoder-ui-input"
                              type="password"
                              value={removing ? '' : value}
                              disabled={removing}
                              autoComplete="new-password"
                              spellCheck={false}
                              placeholder={connected ? '•••••••• saved' : 'API key'}
                              onChange={(e) => set('integrations', { ...draft.integrations, [svc.id]: { ...draft.integrations[svc.id], key: e.currentTarget.value.trim() } })}
                            />
                            {connected && (
                              <Button variant="ghost" onClick={() => set('integrations', { ...draft.integrations, [svc.id]: { ...draft.integrations[svc.id], key: removing ? '' : '-' } })}>
                                {removing ? 'Keep' : 'Disconnect'}
                              </Button>
                            )}
                          </div>
                        </SettingRow>
                      );
                    })}
                    <SettingRow title="ActiveCampaign API URL" htmlFor="uncoder-ui-int-ac-url" description="Settings → Developer → URL, e.g. https://youraccount.api-us1.com">
                      <input id="uncoder-ui-int-ac-url" className="uncoder-ui-input uncoder-ui-input--mono" value={draft.integrations.activecampaign.url ?? ''} placeholder="https://youraccount.api-us1.com" onChange={(e) => set('integrations', { ...draft.integrations, activecampaign: { ...draft.integrations.activecampaign, url: e.currentTarget.value.trim() } })} />
                    </SettingRow>
                  </div>
                </Card>
              </>
            )}
            {section === 'elements' && (
              <ElementManager widgets={s.widgets ?? []} categories={s.widgetCategories ?? {}} disabled={draft.disabledWidgets} onChange={(next) => set('disabledWidgets', [...new Set(next)].sort())} />
            )}
            {section === 'seo' && (
              <>
                <Card id="business" title="Business details (structured data)" description="Tells search engines who is behind the site: name, contact details, address and opening hours as schema.org data on the home page, which can show up in search results and maps. Blog posts get article data automatically.">
                  <div className="uncoder-ui-setlist">
                    <SettingRow title="Add business data" description={s.seoPlugin ? `${s.seoPlugin} already describes your organization; local business types are added next to it.` : 'Printed on the home page only.'}>
                      <Toggle checked={draft.business.enabled} onChange={(v) => set('business', { ...draft.business, enabled: v })} label="Add business data" />
                    </SettingRow>
                    {draft.business.enabled && (
                      <>
                        <SettingRow title="Type" htmlFor="uncoder-ui-biz-type" description="The closest match; “Local business” types add address and opening hours.">
                          <select id="uncoder-ui-biz-type" className="uncoder-ui-select" value={draft.business.type} onChange={(e) => set('business', { ...draft.business, type: e.currentTarget.value })}>
                            {Object.entries(s.businessTypes ?? {}).map(([value, label]) => (
                              <option key={value} value={value}>
                                {label}
                              </option>
                            ))}
                          </select>
                        </SettingRow>
                        <SettingRow title="Description">
                          <textarea className="uncoder-ui-textarea" rows={2} aria-label="Business description" value={draft.business.description} onChange={(e) => set('business', { ...draft.business, description: e.currentTarget.value })} />
                        </SettingRow>
                        <SettingRow title="Contact & address" description="Only real details: search engines compare them with other listings.">
                          <div className="uncoder-ui-formgrid uncoder-ui-formgrid--even">
                            {BUSINESS_FIELDS.filter((f) => !f.local || draft.business.type !== 'Organization').map((f) => (
                              <input key={f.key} className="uncoder-ui-input" aria-label={f.label} placeholder={f.placeholder ?? f.label} title={f.label} value={draft.business[f.key]} onChange={(e) => set('business', { ...draft.business, [f.key]: e.currentTarget.value })} />
                            ))}
                          </div>
                        </SettingRow>
                        {draft.business.type !== 'Organization' && (
                          <SettingRow title="Opening hours" description="One line per range, like “Mo-Fr 08:00-17:00” or “Sa 09:00-13:00”.">
                            <textarea className="uncoder-ui-textarea uncoder-ui-input--mono" rows={3} aria-label="Opening hours" value={draft.business.hours.join('\n')} onChange={(e) => set('business', { ...draft.business, hours: e.currentTarget.value.split('\n') })} />
                          </SettingRow>
                        )}
                        <SettingRow title="Social profiles" description="One URL per line (LinkedIn, Facebook, Instagram, Google Business…).">
                          <textarea className="uncoder-ui-textarea uncoder-ui-input--mono" rows={3} aria-label="Social profile URLs" value={draft.business.same_as.join('\n')} onChange={(e) => set('business', { ...draft.business, same_as: e.currentTarget.value.split('\n') })} />
                        </SettingRow>
                      </>
                    )}
                  </div>
                </Card>
              </>
            )}
            {section === 'tools' && (
              <>
                <Card title="Tools">
                  <div className="uncoder-ui-setlist">
                    <SettingRow title="Regenerate CSS" description={regen.result ?? `Rebuilds the stylesheet of every ${NAME} page and template, and the Design System. Use it after migrating the site or if styles look outdated.`}>
                      <Button icon="refresh-cw" onClick={regenerate} loading={regen.busy}>
                        Regenerate CSS
                      </Button>
                    </SettingRow>
                    <SettingRow title="Find & replace" description="Change a phone number, a product name, an old domain or a brand color in every page and template at once — with a preview first.">
                      <Button icon="replace-all" onClick={() => setFinding(true)}>
                        Find & replace…
                      </Button>
                    </SettingRow>
                    <SettingRow title="Export design (JSON)" description="The Design System and every template as one small file, without pages, media or menus. For the whole site, use Library → Import & export.">
                      <Button
                        icon="download"
                        loading={exporting}
                        onClick={async () => {
                          setExporting(true);
                          await downloadExport({ all_templates: true, kit: true }, 'site-design');
                          setExporting(false);
                        }}
                      >
                        Export
                      </Button>
                    </SettingRow>
                    <SettingRow title="Import design" description={`Adds templates, pages and a Design System from a file exported with ${NAME}. Everything arrives as a draft; the Design System is only replaced if you choose so.`}>
                      <Button icon="upload" onClick={() => setImporting(true)}>
                        Import…
                      </Button>
                    </SettingRow>
                    {!s.cssWritable && (
                      <Callout tone="warning">The uploads folder is not writable, so styles are printed inline in each page. Fix the folder permissions for faster, cacheable CSS files.</Callout>
                    )}
                  </div>
                </Card>
                <SupportToolsCards />
              </>
            )}
            {section === 'advanced' && (
              <>
                <Card title="Uninstall">
                  <div className="uncoder-ui-setlist">
                    <SettingRow
                      title="Remove all data on uninstall"
                      description="When the plugin is deleted, also delete templates, Design System, settings, API keys, the activity log and form submissions. Pages keep their last saved HTML."
                      danger={draft.removeData}
                    >
                      <Toggle checked={draft.removeData} onChange={(v) => set('removeData', v)} label="Remove all data on uninstall" />
                    </SettingRow>
                  </div>
                </Card>
              </>
            )}
          </div>
        )}
      </Workspace>
      {s && draft && <SaveBar dirty={dirty} saving={saving} onSave={save} onDiscard={() => setDraft(pick(s))} />}
      <ImportDialog open={importing} onClose={() => setImporting(false)} />
      <FindReplaceDialog open={finding} onClose={() => setFinding(false)} />
    </>
  );
}

/** The chosen page as a LookupSelect value (its title comes from the server payload). */
function pagePick(id: number, s: PluginSettings, labels: Record<number, string>): Picked[] {
  if (!id) return [];
  return [{ id, label: labels[id] ?? (s.maintenancePage?.id === id ? s.maintenancePage.title : `Page #${id}`) }];
}
