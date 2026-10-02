import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Button, Segmented, Toggle } from '@editor/ui/primitives';
import { NumberInput } from '@editor/ui/inputs';
import { templatesApi, type PopupSettings, type Template } from '../lib/api';
import { toast } from '../lib/toast';
import { cx } from '../lib/format';
import { ColorField } from '../ui/ColorField';
import { confirmDialog, Drawer } from '../ui/Dialog';
import { Callout, CopyField } from '../ui/kit';

const POSITIONS = ['top-left', 'top', 'top-right', 'left', 'center', 'right', 'bottom-left', 'bottom', 'bottom-right'];

const clone = <T,>(v: T): T => JSON.parse(JSON.stringify(v));

function Group({ title, children }: { title: string; children: ReactNode }) {
  return (
    <section className="uncoder-ui-dgroup">
      <h3 className="uncoder-ui-dgroup__title">{title}</h3>
      <div className="uncoder-ui-dgroup__body">{children}</div>
    </section>
  );
}

function Row({ label, children, htmlFor, hint }: { label: ReactNode; children: ReactNode; htmlFor?: string; hint?: ReactNode }) {
  return (
    <div className="uncoder-ui-drow">
      <div className="uncoder-ui-drow__text">
        <label className="uncoder-ui-drow__label" htmlFor={htmlFor}>
          {label}
        </label>
        {hint && <div className="uncoder-ui-drow__hint">{hint}</div>}
      </div>
      <div className="uncoder-ui-drow__control">{children}</div>
    </div>
  );
}

type TriggerKey = keyof PopupSettings['triggers'];

const TRIGGERS: Array<{ key: TriggerKey; label: string; hint: string; param?: { field: string; label: string; min: number; max: number; suffix: string } | { field: 'selector'; label: string; placeholder: string } }> = [
  { key: 'load', label: 'On page load', hint: 'After a delay', param: { field: 'delay', label: 'Delay', min: 0, max: 600, suffix: 's' } },
  { key: 'scroll', label: 'On scroll', hint: 'Once the visitor scrolls this far', param: { field: 'percent', label: 'Scroll depth', min: 1, max: 100, suffix: '%' } },
  { key: 'scroll_to', label: 'On reaching an element', hint: 'When it enters the viewport', param: { field: 'selector', label: 'Element selector', placeholder: '#pricing' } },
  { key: 'click', label: 'On click', hint: 'Clicks on matching elements', param: { field: 'selector', label: 'Click selector', placeholder: '.open-newsletter' } },
  { key: 'exit_intent', label: 'On exit intent', hint: 'Pointer leaves toward the browser bar' },
  { key: 'inactivity', label: 'After inactivity', hint: 'No scroll, click or key press', param: { field: 'seconds', label: 'Idle time', min: 3, max: 3600, suffix: 's' } },
  { key: 'page_views', label: 'After page views', hint: 'Pages viewed in this visit', param: { field: 'count', label: 'Page views', min: 1, max: 100, suffix: 'views' } },
];

export function PopupDrawer({ template, onClose, onSaved }: { template: Template | null; onClose: () => void; onSaved: (t: Template) => void }) {
  const [s, setS] = useState<PopupSettings | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setS(template?.popup ? clone(template.popup) : null);
    setError(null);
  }, [template]);

  const initial = useMemo(() => JSON.stringify(template?.popup ?? null), [template]);
  const dirty = !!s && JSON.stringify(s) !== initial;

  const set = <K extends keyof PopupSettings>(key: K, value: PopupSettings[K]) => setS((prev) => (prev ? { ...prev, [key]: value } : prev));
  const setTrigger = (key: TriggerKey, patch: Record<string, unknown>) =>
    setS((prev) => (prev ? { ...prev, triggers: { ...prev.triggers, [key]: { ...prev.triggers[key], ...patch } } } : prev));

  const save = async () => {
    if (!template || !s) return;
    setSaving(true);
    setError(null);
    try {
      const saved = await templatesApi.update(template.id, { popup: s });
      toast('Popup settings saved');
      onSaved(saved);
      onClose();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not save the popup settings.');
    } finally {
      setSaving(false);
    }
  };

  const anyTrigger = s ? Object.values(s.triggers).some((t) => t.enabled) : false;

  const requestClose = async () => {
    if (dirty && !(await confirmDialog({ title: 'Discard unsaved changes?', body: 'Your popup settings changes will be lost.', confirmLabel: 'Discard', danger: true }))) return;
    onClose();
  };

  return (
    <Drawer
      open={!!template}
      onClose={requestClose}
      width={500}
      title="Popup settings"
      subtitle={template?.title}
      footer={
        <>
          <Button variant="ghost" onClick={requestClose}>
            Cancel
          </Button>
          <Button variant="primary" onClick={save} loading={saving} disabled={!dirty}>
            Save changes
          </Button>
        </>
      }
    >
      {error && <Callout tone="danger">{error}</Callout>}
      {s && template && (
        <>
          <Group title="Layout">
            <Row label="Layout">
              <Segmented
                size="md"
                ariaLabel="Popup type"
                value={s.layout}
                onChange={(v) => set('layout', v as PopupSettings['layout'])}
                options={[
                  { value: 'modal', label: 'Modal' },
                  { value: 'slide_in', label: 'Slide-in' },
                  { value: 'bar', label: 'Bar' },
                  { value: 'fullscreen', label: 'Full screen' },
                ]}
              />
            </Row>
            {s.layout !== 'fullscreen' && (
              <Row label="Position" hint={s.layout === 'bar' ? 'Bars use top or bottom.' : undefined}>
                <div className="uncoder-ui-posgrid" role="radiogroup" aria-label="Position">
                  {POSITIONS.map((p) => (
                    <button
                      key={p}
                      type="button"
                      role="radio"
                      aria-checked={s.position === p}
                      aria-label={p.replace('-', ' ')}
                      data-tip={p.replace('-', ' ')}
                      className={cx('uncoder-ui-posgrid__cell', s.position === p && 'is-active')}
                      onClick={() => set('position', p)}
                    />
                  ))}
                </div>
              </Row>
            )}
            <Row label="Width" htmlFor="uncoder-ui-pop-width">
              <div className="uncoder-ui-inline">
                <NumberInput value={Number(s.width.size) || ''} onChange={(v) => set('width', { ...s.width, size: v === '' ? 560 : v })} min={0} max={4000} ariaLabel="Width" width={96} />
                <select className="uncoder-ui-select uncoder-ui-select--sm" value={s.width.unit} aria-label="Width unit" onChange={(e) => set('width', { ...s.width, unit: e.currentTarget.value })}>
                  {['px', '%', 'vw', 'rem'].map((u) => (
                    <option key={u} value={u}>
                      {u}
                    </option>
                  ))}
                </select>
              </div>
            </Row>
            <Row label="Animation" htmlFor="uncoder-ui-pop-anim">
              <select id="uncoder-ui-pop-anim" className="uncoder-ui-select" value={s.animation} onChange={(e) => set('animation', e.currentTarget.value)}>
                {[
                  ['zoom', 'Zoom'],
                  ['fade', 'Fade'],
                  ['slide-up', 'Slide up'],
                  ['slide-down', 'Slide down'],
                  ['slide-left', 'Slide left'],
                  ['slide-right', 'Slide right'],
                  ['none', 'None'],
                ].map(([v, l]) => (
                  <option key={v} value={v}>
                    {l}
                  </option>
                ))}
              </select>
            </Row>
          </Group>

          <Group title="Appearance">
            <Row label="Background">
              <ColorField label="Background color" value={s.background} onChange={(v) => set('background', v)} />
            </Row>
            <Row label="Corner radius">
              <NumberInput value={s.radius} onChange={(v) => set('radius', v === '' ? 0 : v)} min={0} max={200} ariaLabel="Corner radius" suffix={<span className="uncoder-ui-num__suffix">px</span>} width={96} />
            </Row>
            <Row label="Padding">
              <NumberInput value={s.padding} onChange={(v) => set('padding', v === '' ? 0 : v)} min={0} max={200} ariaLabel="Padding" suffix={<span className="uncoder-ui-num__suffix">px</span>} width={96} />
            </Row>
            <Row label="Overlay" hint="Dims the page behind the popup">
              <Toggle checked={s.overlay} onChange={(v) => set('overlay', v)} label="Overlay" />
            </Row>
            {s.overlay && (
              <Row label="Overlay color">
                <ColorField label="Overlay color" value={s.overlay_color} onChange={(v) => set('overlay_color', v)} />
              </Row>
            )}
          </Group>

          <Group title="Closing">
            <Row label="Close button">
              <Toggle checked={s.close_button} onChange={(v) => set('close_button', v)} label="Close button" />
            </Row>
            <Row label="Close on overlay click">
              <Toggle checked={s.close_on_overlay} onChange={(v) => set('close_on_overlay', v)} label="Close on overlay click" />
            </Row>
            <Row label="Close with Esc key">
              <Toggle checked={s.close_on_esc} onChange={(v) => set('close_on_esc', v)} label="Close with Esc key" />
            </Row>
          </Group>

          <Group title="Triggers">
            {!anyTrigger && <Callout tone="info">No trigger: the popup only opens from a link or button pointing to its open link.</Callout>}
            <ul className="uncoder-ui-triggers">
              {TRIGGERS.map((t) => {
                const value = s.triggers[t.key] as Record<string, unknown> & { enabled: boolean };
                return (
                  <li key={t.key} className={cx('uncoder-ui-trigger', value.enabled && 'is-on')}>
                    <Toggle checked={value.enabled} onChange={(v) => setTrigger(t.key, { enabled: v })} label={t.label} />
                    <div className="uncoder-ui-trigger__text">
                      <span className="uncoder-ui-trigger__label">{t.label}</span>
                      <span className="uncoder-ui-trigger__hint">{t.hint}</span>
                    </div>
                    {t.param && value.enabled && (
                      <div className="uncoder-ui-trigger__param">
                        {'placeholder' in t.param ? (
                          <input
                            className="uncoder-ui-input uncoder-ui-input--mono"
                            value={String(value[t.param.field] ?? '')}
                            placeholder={t.param.placeholder}
                            aria-label={t.param.label}
                            onChange={(e) => setTrigger(t.key, { [t.param!.field]: e.currentTarget.value })}
                          />
                        ) : (
                          <NumberInput
                            value={Number(value[t.param.field]) || 0}
                            onChange={(v) => {
                              const p = t.param as { field: string; min: number };
                              setTrigger(t.key, { [p.field]: v === '' ? p.min : v });
                            }}
                            min={t.param.min}
                            max={t.param.max}
                            ariaLabel={t.param.label}
                            suffix={<span className="uncoder-ui-num__suffix">{t.param.suffix}</span>}
                            width={104}
                          />
                        )}
                      </div>
                    )}
                  </li>
                );
              })}
            </ul>
          </Group>

          <Group title="Targeting">
            <Row label="Show at most" hint="0 = every time a trigger fires">
              <div className="uncoder-ui-inline">
                <NumberInput value={s.frequency.times} onChange={(v) => set('frequency', { ...s.frequency, times: v === '' ? 0 : v })} min={0} max={100} ariaLabel="Maximum times" width={72} />
                <span className="uncoder-ui-muted">times per</span>
                <select className="uncoder-ui-select uncoder-ui-select--sm" value={s.frequency.period} aria-label="Frequency period" onChange={(e) => set('frequency', { ...s.frequency, period: e.currentTarget.value })}>
                  {[
                    ['session', 'visit'],
                    ['day', 'day'],
                    ['week', 'week'],
                    ['month', 'month'],
                    ['forever', 'visitor, ever'],
                  ].map(([v, l]) => (
                    <option key={v} value={v}>
                      {l}
                    </option>
                  ))}
                </select>
              </div>
            </Row>
            <Row label="Devices">
              <div className="uncoder-ui-chipset" role="group" aria-label="Devices">
                {[
                  ['desktop', 'Desktop', 'monitor'],
                  ['tablet', 'Tablet', 'tablet'],
                  ['mobile', 'Mobile', 'smartphone'],
                ].map(([d, label]) => {
                  const on = s.devices.includes(d);
                  return (
                    <button
                      key={d}
                      type="button"
                      aria-pressed={on}
                      className={cx('uncoder-ui-togglechip', on && 'is-on')}
                      onClick={() => set('devices', on ? s.devices.filter((x) => x !== d) : [...s.devices, d])}
                    >
                      {label}
                    </button>
                  );
                })}
              </div>
            </Row>
            <Row label="Visitors">
              <Segmented
                size="md"
                ariaLabel="Visitors"
                value={s.visitors}
                onChange={(v) => set('visitors', v as PopupSettings['visitors'])}
                options={[
                  { value: 'all', label: 'Everyone' },
                  { value: 'logged_out', label: 'Logged out' },
                  { value: 'logged_in', label: 'Logged in' },
                ]}
              />
            </Row>
            <Row label="One popup at a time" hint="Skip if another popup is open">
              <Toggle checked={s.avoid_multiple} onChange={(v) => set('avoid_multiple', v)} label="One popup at a time" />
            </Row>
            {s.devices.length === 0 && <Callout tone="warning">No device selected: the popup never shows.</Callout>}
          </Group>

          {s.rules && (
            <Group title="Who sees it">
              <p className="uncoder-ui-muted uncoder-ui-dgroup__intro">Limits the automatic triggers above. A link or button that opens the popup always works.</p>
              <Row label="Arriving from" htmlFor="uncoder-ui-pop-ref" hint="How the visit started">
                <select id="uncoder-ui-pop-ref" className="uncoder-ui-select" value={s.rules.referrer} onChange={(e) => set('rules', { ...s.rules, referrer: e.currentTarget.value as PopupSettings['rules']['referrer'] })}>
                  {[
                    ['', 'Anywhere'],
                    ['search', 'A search engine'],
                    ['external', 'Another website'],
                    ['internal', 'A page of this site'],
                    ['direct', 'Nowhere (typed or bookmarked)'],
                    ['contains', 'A URL containing…'],
                  ].map(([v, l]) => (
                    <option key={v} value={v}>
                      {l}
                    </option>
                  ))}
                </select>
              </Row>
              {s.rules.referrer === 'contains' && (
                <Row label="Referrer contains" htmlFor="uncoder-ui-pop-refv">
                  <input id="uncoder-ui-pop-refv" className="uncoder-ui-input" value={s.rules.referrer_value} placeholder="facebook.com" onChange={(e) => set('rules', { ...s.rules, referrer_value: e.currentTarget.value })} />
                </Row>
              )}
              <Row label="URL parameter" htmlFor="uncoder-ui-pop-param" hint="e.g. utm_campaign=spring (this page or the first page of the visit)">
                <input id="uncoder-ui-pop-param" className="uncoder-ui-input uncoder-ui-input--mono" value={s.rules.url_param} placeholder="utm_campaign=spring" onChange={(e) => set('rules', { ...s.rules, url_param: e.currentTarget.value.trim() })} />
              </Row>
              <Row label="From visit number" hint="0 or 1 = from the first visit">
                <NumberInput value={s.rules.sessions} onChange={(v) => set('rules', { ...s.rules, sessions: v === '' ? 0 : v })} min={0} max={1000} ariaLabel="From visit number" width={88} />
              </Row>
              <Row label="Schedule" hint="Only between these dates">
                <Toggle checked={s.rules.schedule.enabled} onChange={(v) => set('rules', { ...s.rules, schedule: { ...s.rules.schedule, enabled: v } })} label="Schedule" />
              </Row>
              {s.rules.schedule.enabled && (
                <>
                  <Row label="From" htmlFor="uncoder-ui-pop-from">
                    <input id="uncoder-ui-pop-from" type="datetime-local" className="uncoder-ui-input" value={s.rules.schedule.from.length === 10 ? `${s.rules.schedule.from}T00:00` : s.rules.schedule.from} onChange={(e) => set('rules', { ...s.rules, schedule: { ...s.rules.schedule, from: e.currentTarget.value } })} />
                  </Row>
                  <Row label="Until" htmlFor="uncoder-ui-pop-until">
                    <input id="uncoder-ui-pop-until" type="datetime-local" className="uncoder-ui-input" value={s.rules.schedule.until.length === 10 ? `${s.rules.schedule.until}T23:59` : s.rules.schedule.until} onChange={(e) => set('rules', { ...s.rules, schedule: { ...s.rules.schedule, until: e.currentTarget.value } })} />
                  </Row>
                  <Row label="Time zone">
                    <Segmented
                      size="md"
                      ariaLabel="Time zone"
                      value={s.rules.schedule.timezone}
                      onChange={(v) => set('rules', { ...s.rules, schedule: { ...s.rules.schedule, timezone: v as 'site' | 'visitor' } })}
                      options={[
                        { value: 'site', label: 'Site' },
                        { value: 'visitor', label: 'Visitor’s' },
                      ]}
                    />
                  </Row>
                </>
              )}
              <Row label="Browsers" hint="None selected = every browser">
                <div className="uncoder-ui-chipset uncoder-ui-chipset--wrap" role="group" aria-label="Browsers">
                  {[
                    ['chrome', 'Chrome'],
                    ['safari', 'Safari'],
                    ['firefox', 'Firefox'],
                    ['edge', 'Edge'],
                    ['opera', 'Opera'],
                    ['samsung', 'Samsung'],
                  ].map(([b, label]) => {
                    const on = s.rules.browsers.includes(b);
                    return (
                      <button key={b} type="button" aria-pressed={on} className={cx('uncoder-ui-togglechip', on && 'is-on')} onClick={() => set('rules', { ...s.rules, browsers: on ? s.rules.browsers.filter((x) => x !== b) : [...s.rules.browsers, b] })}>
                        {label}
                      </button>
                    );
                  })}
                </div>
              </Row>
            </Group>
          )}

          <Group title="Open from a link">
            <p className="uncoder-ui-muted uncoder-ui-dgroup__intro">Use this as the URL of any button or link to open the popup.</p>
            <CopyField value={template.openLink ?? `#uncoder-popup:open:${template.id}`} label="Popup open link" what="Popup link copied" />
          </Group>
        </>
      )}
    </Drawer>
  );
}
