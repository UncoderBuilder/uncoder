import { useEffect, useMemo, useState } from 'react';
import type { ControlDef, KitColor, KitTypography, KitVariable } from '@shared/types';
import { TextInput } from '../ui/inputs';
import { KIT_SCHEMAS } from '@shared/kit';
import { config, schemas } from '../lib/config';
import { saveKit, setKitSection, updateKit, useKit } from '../store/kit';
import { toast } from '../store/ui';
import { ColorSwatchButton } from '../controls/ColorControl';
import { frame } from '../canvas/frame';
import { ControlForm } from '../controls/ControlForm';
import { FontPicker } from '../controls/FontControl';
import { Icon } from '../ui/Icon';
import { Button, IconButton, Segmented } from '../ui/primitives';
import { ScrollRow } from '../ui/ScrollRow';

type Tab = 'colors' | 'type' | 'variables' | 'buttons' | 'layout' | 'theme';

const LABELS: Record<string, string> = {
  container_width: 'Container width',
  gutter: 'Side gutter',
  gap: 'Default gap',
  section_space: 'Section spacing',
  scroll_offset: 'Anchor offset',
  typography: 'Typography',
  color: 'Text color',
  background: 'Background',
  hover_color: 'Hover text color',
  hover_background: 'Hover background',
  padding: 'Padding',
  radius: 'Border radius',
  border: 'Border',
  hover_border_color: 'Hover border color',
  shadow: 'Shadow',
  hover_effect: 'Hover effect',
  hover_fill: 'Hover fill color',
  enabled: 'Apply to the whole site',
  body_color: 'Body text color',
  heading_color: 'Heading color',
  link_color: 'Link color',
  link_hover_color: 'Link hover color',
};

function typographyFields(): Record<string, ControlDef> {
  for (const s of Object.values(schemas)) {
    for (const c of Object.values(s.controls)) if (c.type === 'typography' && c.fields) return c.fields;
  }
  return {};
}

function labelled(schema: Record<string, ControlDef>): Record<string, ControlDef> {
  const typo = typographyFields();
  return Object.fromEntries(
    Object.entries(schema).map(([k, c]) => [k, { ...c, label: LABELS[k] ?? k, ...(c.type === 'typography' ? { fields: typo } : {}), ...(c.type === 'border' ? { fields: BORDER_FIELDS } : {}), ...(c.type === 'box_shadow' ? { fields: SHADOW_FIELDS } : {}) }]),
  );
}

const BORDER_FIELDS: Record<string, ControlDef> = {
  style: { type: 'select', label: 'Style', options: { '': 'Default', none: 'None', solid: 'Solid', dashed: 'Dashed', dotted: 'Dotted' } },
  width: { type: 'dimensions', label: 'Width', responsive: true, size_units: ['px'] },
  color: { type: 'color', label: 'Color' },
};
const SHADOW_FIELDS: Record<string, ControlDef> = {
  x: { type: 'number', label: 'Horizontal' },
  y: { type: 'number', label: 'Vertical' },
  blur: { type: 'number', label: 'Blur', min: 0 },
  spread: { type: 'number', label: 'Spread' },
  color: { type: 'color', label: 'Color' },
  inset: { type: 'switch', label: 'Inset' },
};

export function KitPanel() {
  const [tab, setTab] = useState<Tab>('colors');
  // "Manage variables…" in a field's variable menu opens this tab.
  useEffect(() => {
    const open = (e: Event) => setTab((e as CustomEvent<Tab>).detail);
    window.addEventListener('uncoder-ui:kit-tab', open);
    return () => window.removeEventListener('uncoder-ui:kit-tab', open);
  }, []);
  const dirty = useKit((s) => s.dirty);
  const saving = useKit((s) => s.saving);
  const canEdit = config.user.caps.edit_theme;
  return (
    <div className="uncoder-ui-kit">
      <ScrollRow className="uncoder-ui-kit__tabs" role="tablist" label="Design System" activeKey={tab}>
        {(
          [
            ['colors', 'Colors'],
            ['type', 'Type'],
            ['variables', 'Sizes'],
            ['buttons', 'Buttons'],
            ['layout', 'Layout'],
            ['theme', 'Theme'],
          ] as Array<[Tab, string]>
        ).map(([value, label]) => (
          <button key={value} type="button" role="tab" aria-selected={tab === value} className={`uncoder-ui-kit__tab${tab === value ? ' is-active' : ''}`} onClick={() => setTab(value)}>
            {label}
          </button>
        ))}
      </ScrollRow>
      <div className="uncoder-ui-kit__body">
        {!canEdit && (
          <div className="uncoder-ui-notice">
            <Icon name="lock" size={14} />
            <span>View only. Ask an administrator to change the Design System.</span>
          </div>
        )}
        {/* Saving needs edit_theme_options (POST /kit): without it the controls are shown but inert. */}
        <div className="uncoder-ui-kit__fields" inert={!canEdit}>
          {tab === 'colors' && <Colors />}
          {tab === 'type' && <Typography />}
          {tab === 'variables' && <Variables />}
          {tab === 'buttons' && <Section section="buttons" />}
          {tab === 'layout' && <Section section="layout" />}
          {tab === 'theme' && <Section section="theme" />}
        </div>
      </div>
      {canEdit && (
      <div className="uncoder-ui-kit__foot">
        <span className="uncoder-ui-kit__note">{dirty ? 'Changes apply to every page when saved.' : 'Global styles are up to date.'}</span>
        <Button
          variant="primary"
          size="sm"
          disabled={!dirty}
          loading={saving}
          onClick={async () => {
            try {
              await saveKit();
              toast('Design System saved', 'success', undefined, 2200);
            } catch (e: any) {
              toast(`Could not save Design System: ${e.message}`, 'error');
            }
          }}
        >
          Save Design System
        </Button>
      </div>
      )}
    </div>
  );
}

function Colors() {
  const colors = useKit((s) => s.kit.colors);
  const fonts = useKit((s) => s.kit.fonts);
  const scheme = useKit((s) => s.kit.settings?.color_scheme ?? 'light');
  const [preview, setPreview] = useState(() => frame.doc?.documentElement.getAttribute('data-uncoder-scheme') === 'dark');
  const setColor = (i: number, patch: Partial<KitColor>) => updateKit((k) => ({ ...k, colors: k.colors.map((c, j) => (j === i ? { ...c, ...patch } : c)) }));
  const dark = scheme !== 'light';
  const togglePreview = () => {
    const next = !preview;
    frame.doc?.documentElement.setAttribute('data-uncoder-scheme', next ? 'dark' : 'light');
    setPreview(next);
  };
  return (
    <>
      <div className="uncoder-ui-kit__label">Dark mode</div>
      <select className="uncoder-ui-select" value={scheme} aria-label="Dark mode" onChange={(e) => setKitSection('settings', { color_scheme: e.currentTarget.value })}>
        <option value="light">Off — light only</option>
        <option value="toggle">On, with a switch (starts light)</option>
        <option value="auto">Follow the visitor’s device (with a switch)</option>
      </select>
      {dark && (
        <div className="uncoder-ui-kit__darkbar">
          <span>Give each color a dark value (right swatch). Add the Color Scheme Switch widget to your header.</span>
          <Button size="sm" icon={preview ? 'sun' : 'moon'} onClick={togglePreview}>
            {preview ? 'Preview light' : 'Preview dark'}
          </Button>
        </div>
      )}
      <div className="uncoder-ui-kit__label">Colors</div>
      <div className={`uncoder-ui-kit__colors${dark ? ' has-dark' : ''}`}>
        {colors.map((c, i) => (
          <div key={c.id} className="uncoder-ui-kitcolor">
            <ColorSwatchButton value={c.value} onChange={(v) => v && setColor(i, { value: v })} allowGlobal={false} label={c.name} />
            {dark && <ColorSwatchButton value={c.dark ?? ''} onChange={(v) => setColor(i, { dark: v || undefined })} allowGlobal={false} label={`${c.name} (dark)`} />}
            <input className="uncoder-ui-input uncoder-ui-kitcolor__name" value={c.name} aria-label="Color name" onChange={(e) => setColor(i, { name: e.currentTarget.value })} />
            <span className="uncoder-ui-kitcolor__id uncoder-ui-mono" data-tip={`var(--uncoder-c-${c.id})`}>
              {c.id}
            </span>
            <IconButton icon="trash-2" label={`Delete ${c.name}`} size={12} tone="danger" onClick={() => updateKit((k) => ({ ...k, colors: k.colors.filter((_, j) => j !== i) }))} />
          </div>
        ))}
      </div>
      <Button
        size="sm"
        icon="plus"
        onClick={() =>
          updateKit((k) => {
            let n = k.colors.length + 1;
            while (k.colors.some((c) => c.id === `color-${n}`)) n++;
            return { ...k, colors: [...k.colors, { id: `color-${n}`, name: `Color ${n}`, value: '#6e56ff' }] };
          })
        }
      >
        Add color
      </Button>
      <div className="uncoder-ui-kit__label">Fonts</div>
      {fonts.map((f, i) => (
        <div key={f.id} className="uncoder-ui-ctl">
          <div className="uncoder-ui-ctl__label">
            <span className="uncoder-ui-ctl__text">{f.name}</span>
          </div>
          <div className="uncoder-ui-ctl__input">
            <FontPicker
              value={f.family || undefined}
              onChange={(v) => updateKit((k) => ({ ...k, fonts: k.fonts.map((x, j) => (j === i ? { ...x, family: v && !v.startsWith('var(') ? v : '' } : x)) }))}
              label={f.name}
            />
          </div>
        </div>
      ))}
    </>
  );
}

function Typography() {
  const presets = useKit((s) => s.kit.typography);
  const [open, setOpen] = useState<string | null>(null);
  const fields = useMemo(() => {
    const f = { ...typographyFields() };
    delete f.preset;
    return f;
  }, []);
  const setPreset = (i: number, patch: Partial<KitTypography>) => updateKit((k) => ({ ...k, typography: k.typography.map((p, j) => (j === i ? { ...p, ...patch } : p)) }));
  return (
    <div className="uncoder-ui-kit__type">
      {presets.map((p, i) => {
        const size = p.value?.size ? `${p.value.size.size}${p.value.size.unit}` : '';
        const isOpen = open === p.id;
        return (
          <div key={p.id} className={`uncoder-ui-kittype${isOpen ? ' is-open' : ''}`}>
            <button type="button" className="uncoder-ui-kittype__head" onClick={() => setOpen(isOpen ? null : p.id)} aria-expanded={isOpen}>
              <span className="uncoder-ui-kittype__ag" style={{ fontWeight: Number(p.value?.weight) || 400 }}>
                Ag
              </span>
              <span className="uncoder-ui-kittype__name">{p.name}</span>
              <span className="uncoder-ui-kittype__meta uncoder-ui-mono">
                {size} {p.value?.weight ?? ''}
              </span>
              <Icon name={isOpen ? 'chevron-down' : 'chevron-right'} size={12} />
            </button>
            {isOpen && (
              <div className="uncoder-ui-kittype__body">
                <input className="uncoder-ui-input" value={p.name} aria-label="Style name" onChange={(e) => setPreset(i, { name: e.currentTarget.value })} />
                <ControlForm
                  controls={fields}
                  values={p.value ?? {}}
                  onChange={(k, v) => {
                    const next = { ...(p.value ?? {}) };
                    if (v === undefined || v === '') delete next[k];
                    else next[k] = v;
                    setPreset(i, { value: next });
                  }}
                />
              </div>
            )}
          </div>
        );
      })}
      <Button
        size="sm"
        icon="plus"
        onClick={() =>
          updateKit((k) => {
            let n = 1;
            while (k.typography.some((t) => t.id === `style-${n}`)) n++;
            return { ...k, typography: [...k.typography, { id: `style-${n}`, name: `Text style ${n}`, value: { size: { size: 18, unit: 'px' } } }] };
          })
        }
      >
        Add text style
      </Button>
    </div>
  );
}

function Section({ section }: { section: 'buttons' | 'layout' | 'theme' }) {
  const values = useKit((s) => (s.kit as any)[section] ?? {});
  const controls = useMemo(() => {
    if (section === 'theme') {
      return {
        enabled: { type: 'switch', label: LABELS.enabled, description: 'Uses the Design System text styles for the body and h1–h6 across the site.' },
        body_color: { type: 'color', label: LABELS.body_color },
        heading_color: { type: 'color', label: LABELS.heading_color },
        link_color: { type: 'color', label: LABELS.link_color },
        link_hover_color: { type: 'color', label: LABELS.link_hover_color },
        background: { type: 'color', label: 'Page background' },
        background_image: { type: 'media', label: 'Background image', description: 'A texture or pattern behind every section without its own background.' },
        background_size: { type: 'select', label: 'Image size', options: { auto: 'Tile', cover: 'Cover' }, condition: { 'background_image.url!': '' } },
        optical_sizing: { type: 'select', label: 'Optical sizing', options: { '': 'Auto', none: 'Off' }, description: 'Off: fonts like Inter or DM Sans keep their standard text shapes and widths at every size (the static font files most designs use).' },
      } as Record<string, ControlDef>;
    }
    return labelled(KIT_SCHEMAS[section]);
  }, [section]);
  const form = <ControlForm controls={controls} values={values} onChange={(k, v) => setKitSection(section, { [k]: v })} />;
  if (section !== 'theme') return form;
  return (
    <>
      {form}
      <SiteBehaviour />
    </>
  );
}

const BEHAVIOUR: Record<string, ControlDef> = {
  _behaviour: { type: 'heading', label: 'Site behaviour' },
  smooth_scroll: { type: 'switch', label: 'Smooth scrolling', default: true, description: 'Anchor links glide to their section and stop below a sticky header.' },
  back_to_top: { type: 'switch', label: 'Back-to-top button' },
  back_to_top_position: { type: 'select', label: 'Button side', options: { right: 'Right', left: 'Left' }, default: 'right', condition: { back_to_top: true } },
  page_transition: { type: 'select', label: 'Page transitions', options: { '': 'None', fade: 'Cross-fade', slide: 'Slide up' }, description: 'Animates between pages in browsers that support View Transitions; others navigate as usual.' },
  preloader: { type: 'select', label: 'Preloader', options: { '': 'None', spinner: 'Spinner', bar: 'Loading bar', logo: 'Pulsing logo' }, description: 'Covers the page until it has loaded (at most 4 s). Makes a site feel slower, so use it sparingly.' },
  preloader_once: { type: 'switch', label: 'Only on the first page of a visit', default: true, condition: { 'preloader!': '' } },
  _lightbox: { type: 'heading', label: 'Lightbox' },
  lightbox_auto: { type: 'switch', label: 'Open image links in the lightbox', default: true, description: 'Every link to an image file (also in blog posts and WordPress galleries) opens in the lightbox. Add data-uncoder-no-lightbox to a link to skip it.' },
  lightbox_bg: { type: 'color', label: 'Background' },
  lightbox_ui: { type: 'color', label: 'Buttons & text' },
  lightbox_caption: { type: 'switch', label: 'Show captions', default: true },
  lightbox_counter: { type: 'switch', label: 'Show “3 / 10” counter', default: true },
  lightbox_download: { type: 'switch', label: 'Download button' },
};

function SiteBehaviour() {
  const values = useKit((s) => s.kit.settings ?? {});
  return <ControlForm controls={BEHAVIOUR} values={values as Record<string, unknown>} onChange={(k, v) => setKitSection('settings', { [k]: v })} />;
}

/* ---------------------------------------------------------------- Variables (sizes) */

const VAR_GROUPS: Array<{ id: KitVariable['group']; label: string; hint: string; scale?: Array<[string, string]> }> = [
  { id: 'spacing', label: 'Spacing', hint: 'Padding, margin and gaps', scale: [['xs', '4px'], ['sm', '8px'], ['md', '16px'], ['lg', '24px'], ['xl', '40px'], ['2xl', '64px']] },
  { id: 'size', label: 'Size', hint: 'Widths, heights, icon and font sizes' },
  { id: 'radius', label: 'Radius', hint: 'Rounded corners', scale: [['sm', '4px'], ['md', '8px'], ['lg', '16px'], ['full', '9999px']] },
  { id: 'other', label: 'Other', hint: 'Anything else' },
];
const slug = (s: string) => s.toLowerCase().replace(/[^a-z0-9-]+/g, '-').replace(/^-+|-+$/g, '');

/** Named values (--uncoder-v-{id}) that size and spacing fields can use through their "{ }" menu. */
function Variables() {
  const vars = useKit((s) => s.kit.variables ?? []);
  const set = (next: KitVariable[]) => updateKit((k) => ({ ...k, variables: next }));
  const unique = (base: string) => {
    let id = slug(base) || 'var';
    for (let n = 2; vars.some((v) => v.id === id); n++) id = `${slug(base) || 'var'}-${n}`;
    return id;
  };
  const add = (group: KitVariable['group'], name: string, value: string) => [...vars, { id: unique(group === 'other' ? name : `${group === 'spacing' ? 'space' : group}-${name}`), name, group, value }];
  return (
    <div className="uncoder-ui-vars">
      <p className="uncoder-ui-note">Name a size once, use it everywhere: every size and spacing field has a “{'{ }'}” menu. Change the value here and every place that uses it follows. Values can be responsive, e.g. <code>clamp(1rem, 3vw, 2rem)</code>.</p>
      {VAR_GROUPS.map((g) => {
        const list = vars.filter((v) => v.group === g.id);
        return (
          <section key={g.id} className="uncoder-ui-vars__group">
            <div className="uncoder-ui-kit__label">
              {g.label} <span className="uncoder-ui-vars__hint">{g.hint}</span>
            </div>
            {list.map((v) => (
              <div key={v.id} className="uncoder-ui-vars__row">
                <TextInput value={v.name} aria-label="Name" onCommit={(name) => set(vars.map((x) => (x.id === v.id ? { ...x, name: name || x.name } : x)))} />
                <TextInput className="uncoder-ui-input--mono" value={v.value} aria-label={`${v.name} value`} placeholder="24px" onCommit={(value) => value.trim() && set(vars.map((x) => (x.id === v.id ? { ...x, value: value.trim() } : x)))} />
                <IconButton icon="trash-2" label={`Delete ${v.name}`} size={13} onClick={() => set(vars.filter((x) => x.id !== v.id))} />
                <code className="uncoder-ui-vars__ref" data-tip="CSS variable (usable in custom CSS too)">--uncoder-v-{v.id}</code>
              </div>
            ))}
            <div className="uncoder-ui-vars__actions">
              <button type="button" className="uncoder-ui-conds__add" onClick={() => set(add(g.id, list.length ? `${list.length + 1}` : 'md', g.id === 'radius' ? '8px' : '16px'))}>
                <Icon name="plus" size={12} /> Add
              </button>
              {g.scale && !list.length && (
                <button
                  type="button"
                  className="uncoder-ui-conds__add"
                  onClick={() => {
                    let next = vars;
                    for (const [name, value] of g.scale!) next = [...next, { id: `${g.id === 'spacing' ? 'space' : g.id}-${name}`, name: `${g.label} ${name}`, group: g.id, value }];
                    set(next.filter((v, i, a) => a.findIndex((x) => x.id === v.id) === i));
                  }}
                >
                  <Icon name="sparkles" size={12} /> Add a {g.label.toLowerCase()} scale
                </button>
              )}
            </div>
          </section>
        );
      })}
    </div>
  );
}
