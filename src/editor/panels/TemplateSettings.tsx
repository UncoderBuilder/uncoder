import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import type { ControlDef, Settings } from '@shared/types';
import type { PopupSettings } from '../../admin/lib/api';
import { ConditionRow, newRow, toCondition, toRow, type Row } from '../../admin/templates/conditionRows';
import { ControlForm } from '../controls/ControlForm';
import { config } from '../lib/config';
import { docNoun, settingsTitle } from '../lib/docInfo';
import { useDoc } from '../store/doc';
import { loadTemplate, saveConditions, setPopup, useTemplate } from '../store/template';
import { Icon } from '../ui/Icon';
import { Button, IconButton, Spinner } from '../ui/primitives';

const noun = () => docNoun().toLowerCase();

function Section({ icon, title, aside, children }: { icon: string; title: string; aside?: React.ReactNode; children: React.ReactNode }) {
  return (
    <section className="uncoder-ui-tplsec">
      <div className="uncoder-ui-tplsec__head">
        <Icon name={icon} size={14} />
        <h3>{title}</h3>
        {aside}
      </div>
      {children}
    </section>
  );
}

// ------------------------------------------------------------------ Display conditions

/** Where the template shows, with a button to change it (settings panel of conditional templates). */
export function ConditionsSection() {
  const loaded = useTemplate((s) => s.loaded);
  const template = useTemplate((s) => s.template);
  const status = useDoc((s) => s.status);
  useEffect(() => {
    loadTemplate();
  }, []);
  const conds = template?.conditions ?? [];
  return (
    <Section icon="map-pin" title="Display conditions">
      {!loaded ? (
        <Spinner />
      ) : conds.length === 0 ? (
        <p className="uncoder-ui-tplsec__warn">
          <Icon name="circle-alert" size={14} /> Not shown anywhere yet. Choose where this {noun()} appears.
        </p>
      ) : (
        <div className="uncoder-ui-condscope">
          <span className="uncoder-ui-condchips">
            {conds.map((c, i) => (
              <span key={i} className={`uncoder-ui-condchip${c.type === 'exclude' ? ' is-exclude' : ''}`} title={c.label}>
                {c.type === 'exclude' ? 'Not: ' : ''}
                {c.label ?? c.rule}
              </span>
            ))}
          </span>
        </div>
      )}
      {loaded && conds.length > 0 && status !== 'publish' && <p className="uncoder-ui-tplsec__note">Takes effect once the {noun()} is published.</p>}
      <Button size="sm" icon={conds.length ? 'pencil' : 'plus'} onClick={() => useTemplate.setState({ dialog: 'edit' })} disabled={!loaded}>
        {conds.length ? 'Edit conditions' : 'Add conditions'}
      </Button>
    </Section>
  );
}

/** The conditions editor; also opens by itself after publishing a template that has none. */
export function TemplateConditionsDialog() {
  const dialog = useTemplate((s) => s.dialog);
  const loaded = useTemplate((s) => s.loaded);
  useEffect(() => {
    if (dialog) loadTemplate();
  }, [dialog]);
  if (!dialog || !loaded) return null;
  return <ConditionsDialog mode={dialog} />;
}

function ConditionsDialog({ mode }: { mode: 'edit' | 'publish' }) {
  const template = useTemplate((s) => s.template);
  const meta = useTemplate((s) => s.meta);
  const title = useDoc((s) => s.title);
  const [rows, setRows] = useState<Row[]>(() => {
    const list = template?.conditions ?? [];
    if (list.length) return list.map(toRow);
    const defaults = meta?.defaults?.[config.post.docType] ?? [];
    return defaults.length ? defaults.map(toRow) : [newRow(true)];
  });
  const [saving, setSaving] = useState(false);
  const close = () => useTemplate.setState({ dialog: false });
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && close();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);
  if (!meta) return null;
  const includes = rows.filter((r) => r.type === 'include').length;
  const save = async () => {
    setSaving(true);
    const ok = await saveConditions(rows.map((r) => toCondition(r, meta)));
    setSaving(false);
    if (ok) close();
  };
  const update = (key: number, patch: Partial<Row>) => setRows((list) => list.map((r) => (r.key === key ? { ...r, ...patch } : r)));

  return createPortal(
    <div className="uncoder-ui-scrim" onPointerDown={(e) => e.target === e.currentTarget && close()}>
      <div className="uncoder-ui-dialog uncoder-ui-conddlg uncoder-ui-condscope" role="dialog" aria-modal="true" aria-labelledby="uncoder-ui-conddlg-title">
        <div className="uncoder-ui-conddlg__head">
          <span className="uncoder-ui-conddlg__icon" aria-hidden>
            <Icon name="map-pin" size={18} />
          </span>
          <div>
            <h2 id="uncoder-ui-conddlg-title">{mode === 'publish' ? `Where should this ${noun()} appear?` : 'Display conditions'}</h2>
            <p>
              {mode === 'publish'
                ? `“${title}” is published. Choose the pages it shows on; you can change this any time in ${settingsTitle()}.`
                : `Where “${title}” appears. The most specific include wins; any matching exclude hides it.`}
            </p>
          </div>
          <IconButton icon="x" label="Close" onClick={close} />
        </div>
        <div className="uncoder-ui-conddlg__body">
          {rows.length > 0 && (
            <ol className="uncoder-ui-conds" aria-label="Conditions">
              {rows.map((r, i) => (
                <ConditionRow key={r.key} row={r} index={i} meta={meta} onChange={(p) => update(r.key, p)} onRemove={() => setRows((list) => list.filter((x) => x.key !== r.key))} />
              ))}
            </ol>
          )}
          <Button icon="plus" className="uncoder-ui-conddlg__add" onClick={() => setRows((list) => [...list, newRow(list.length === 0)])}>
            Add condition
          </Button>
          {rows.length === 0 && <p className="uncoder-ui-tplsec__warn">Without conditions the {noun()} is not shown anywhere.</p>}
          {rows.length > 0 && includes === 0 && <p className="uncoder-ui-tplsec__warn">Only exclude conditions: add at least one include, otherwise it never shows.</p>}
        </div>
        <div className="uncoder-ui-conddlg__foot">
          <Button variant="ghost" onClick={close}>
            {mode === 'publish' ? 'Later' : 'Cancel'}
          </Button>
          <Button variant="primary" loading={saving} onClick={save}>
            Save conditions
          </Button>
        </div>
      </div>
    </div>,
    document.querySelector('.uncoder-ui-portal') ?? document.body,
  );
}

// ------------------------------------------------------------------ Popup settings

/** Flat control keys → paths in the popup settings object. */
const PATHS: Record<string, string[]> = {
  t_load: ['triggers', 'load', 'enabled'],
  t_load_delay: ['triggers', 'load', 'delay'],
  t_scroll: ['triggers', 'scroll', 'enabled'],
  t_scroll_percent: ['triggers', 'scroll', 'percent'],
  t_scroll_to: ['triggers', 'scroll_to', 'enabled'],
  t_scroll_to_selector: ['triggers', 'scroll_to', 'selector'],
  t_click: ['triggers', 'click', 'enabled'],
  t_click_selector: ['triggers', 'click', 'selector'],
  t_exit: ['triggers', 'exit_intent', 'enabled'],
  t_idle: ['triggers', 'inactivity', 'enabled'],
  t_idle_seconds: ['triggers', 'inactivity', 'seconds'],
  t_views: ['triggers', 'page_views', 'enabled'],
  t_views_count: ['triggers', 'page_views', 'count'],
  freq_times: ['frequency', 'times'],
  freq_period: ['frequency', 'period'],
};

const read = (obj: any, path: string[]) => path.reduce((o, k) => (o == null ? undefined : o[k]), obj);

function flatten(p: PopupSettings): Settings {
  const out: Settings = {};
  for (const k of Object.keys(POPUP_CONTROLS)) {
    if (POPUP_CONTROLS[k].type === 'heading') continue;
    out[k] = read(p, PATHS[k] ?? [k]);
  }
  return out;
}

function assign(p: PopupSettings, key: string, value: any): PopupSettings {
  const next: any = structuredClone(p);
  const path = PATHS[key] ?? [key];
  let o = next;
  for (const k of path.slice(0, -1)) o = o[k] ??= {};
  o[path[path.length - 1]] = value;
  return next;
}

const on = (key: string) => ({ [key]: 'yes' });

const POPUP_CONTROLS: Record<string, ControlDef> = {
  _h_look: { type: 'heading', label: 'Appearance' },
  layout: { type: 'select', label: 'Layout', options: { modal: 'Modal', slide_in: 'Slide-in', bar: 'Bar', fullscreen: 'Full screen' } },
  position: {
    type: 'select',
    label: 'Position',
    options: { center: 'Center', top: 'Top', bottom: 'Bottom', left: 'Left', right: 'Right', 'top-left': 'Top left', 'top-right': 'Top right', 'bottom-left': 'Bottom left', 'bottom-right': 'Bottom right' },
    condition: { 'layout!': 'fullscreen' },
  },
  width: { type: 'slider', label: 'Width', size_units: ['px', '%', 'vw', 'rem'], range: { px: { min: 200, max: 1400 }, '%': { min: 10, max: 100 }, vw: { min: 10, max: 100 }, rem: { min: 10, max: 90 } }, condition: { 'layout!': 'fullscreen' } },
  background: { type: 'color', label: 'Background' },
  radius: { type: 'number', label: 'Corner radius (px)', min: 0, max: 80 },
  padding: { type: 'number', label: 'Padding (px)', min: 0, max: 120 },
  animation: { type: 'select', label: 'Animation', options: { none: 'None', fade: 'Fade', zoom: 'Zoom', 'slide-up': 'Slide up', 'slide-down': 'Slide down', 'slide-left': 'Slide from the right', 'slide-right': 'Slide from the left' } },
  overlay: { type: 'switch', label: 'Overlay', description: 'Dims the page behind the popup.' },
  overlay_color: { type: 'color', label: 'Overlay color', condition: on('overlay') },
  _h_close: { type: 'heading', label: 'Closing' },
  close_button: { type: 'switch', label: 'Close button' },
  close_on_overlay: { type: 'switch', label: 'Close on overlay click', condition: on('overlay') },
  close_on_esc: { type: 'switch', label: 'Close with Esc key' },
  _h_open: { type: 'heading', label: 'Opens when' },
  t_load: { type: 'switch', label: 'On page load' },
  t_load_delay: { type: 'number', label: 'Delay (seconds)', min: 0, max: 600, condition: on('t_load') },
  t_scroll: { type: 'switch', label: 'On scroll' },
  t_scroll_percent: { type: 'number', label: 'Scroll depth (%)', min: 1, max: 100, condition: on('t_scroll') },
  t_scroll_to: { type: 'switch', label: 'On reaching an element' },
  t_scroll_to_selector: { type: 'text', label: 'Element selector', placeholder: '#pricing', condition: on('t_scroll_to') },
  t_click: { type: 'switch', label: 'On click', description: `Links to #uncoder-popup:open:${config.post.id} always open it.` },
  t_click_selector: { type: 'text', label: 'Click selector', placeholder: '.open-newsletter', condition: on('t_click') },
  t_exit: { type: 'switch', label: 'On exit intent' },
  t_idle: { type: 'switch', label: 'After inactivity' },
  t_idle_seconds: { type: 'number', label: 'Idle time (seconds)', min: 3, max: 3600, condition: on('t_idle') },
  t_views: { type: 'switch', label: 'After page views' },
  t_views_count: { type: 'number', label: 'Page views', min: 1, max: 100, condition: on('t_views') },
  _h_rules: { type: 'heading', label: 'Show it' },
  freq_times: { type: 'number', label: 'Times', min: 0, max: 100, description: '0 = every time the triggers fire.' },
  freq_period: { type: 'select', label: 'Per', options: { session: 'Visit', day: 'Day', week: 'Week', month: 'Month', forever: 'Ever' } },
  visitors: { type: 'select', label: 'To', options: { all: 'Everyone', logged_in: 'Logged-in visitors', logged_out: 'Logged-out visitors' } },
  devices: { type: 'multiselect', label: 'On devices', options: { desktop: 'Desktop', tablet: 'Tablet', mobile: 'Mobile' } },
  avoid_multiple: { type: 'switch', label: 'Not while another popup is open' },
};

/** The controls with the popup defaults, so only changed settings show as set (with a reset button). */
function withDefaults(defaults: PopupSettings | undefined): Record<string, ControlDef> {
  if (!defaults) return POPUP_CONTROLS;
  const flat = flatten(defaults);
  return Object.fromEntries(Object.entries(POPUP_CONTROLS).map(([k, c]) => [k, flat[k] === undefined ? c : { ...c, default: flat[k] }]));
}

function changed(values: Settings, defaults: PopupSettings | undefined): Settings {
  if (!defaults) return values;
  const flat = flatten(defaults);
  return Object.fromEntries(Object.entries(values).filter(([k, v]) => JSON.stringify(v) !== JSON.stringify(flat[k])));
}

/** Popup behaviour: layout, closing, triggers and frequency (saved as you change them). */
export function PopupSection() {
  const loaded = useTemplate((s) => s.loaded);
  const popup = useTemplate((s) => s.template?.popup);
  const meta = useTemplate((s) => s.meta);
  const saving = useTemplate((s) => s.popupSaving);
  useEffect(() => {
    loadTemplate();
  }, []);
  return (
    <Section icon="app-window" title="Popup" aside={loaded && popup ? <span className="uncoder-ui-tplsec__saved">{saving ? 'Saving…' : 'Saved'}</span> : null}>
      {!loaded || !popup ? (
        <Spinner />
      ) : (
        <ControlForm
          controls={withDefaults(meta?.popupDefaults)}
          values={changed(flatten(popup), meta?.popupDefaults)}
          onChange={(k, v) => {
            const fallback = meta?.popupDefaults ? read(meta.popupDefaults, PATHS[k] ?? [k]) : undefined;
            setPopup(assign(popup, k, v === undefined ? fallback : v));
          }}
        />
      )}
    </Section>
  );
}
