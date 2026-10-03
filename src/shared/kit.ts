// TypeScript twin of Kit::css() — live preview of Design System edits in the editor canvas.
import { cssUrl, fontStack, PRESET_VARS, Rules, sanitizeColor, sanitizeCustomCss, settingsCss, cssValue, suffix, mediaQuery } from './css';
import type { Breakpoint, ControlDef, ElementSchema, Kit, Settings } from './types';
import BUTTON_FX from '../../assets/data/button-fx.json';

export const KIT_SCHEMAS: Record<string, Record<string, ControlDef>> = {
  layout: {
    container_width: { type: 'slider', responsive: true, selectors: { ':root': '--uncoder-container: {{VALUE}}' } },
    gutter: { type: 'slider', responsive: true, selectors: { ':root': '--uncoder-gutter: {{VALUE}}' } },
    gap: { type: 'slider', responsive: true, selectors: { ':root': '--uncoder-kit-gap: {{VALUE}}' } },
    section_space: { type: 'slider', responsive: true, selectors: { ':root': '--uncoder-section-space: {{VALUE}}' } },
    scroll_offset: { type: 'slider', selectors: { ':root': '--uncoder-scroll-offset: {{VALUE}}' } },
  },
  buttons: {
    typography: { type: 'typography', selector: '.uncoder-btn' },
    color: { type: 'color', selectors: { '.uncoder-btn': 'color: {{VALUE}}' } },
    background: { type: 'color', selectors: { '.uncoder-btn': 'background-color: {{VALUE}}' } },
    hover_color: { type: 'color', selectors: { '.uncoder-btn:is(:hover, :focus-visible)': 'color: {{VALUE}}' } },
    hover_background: { type: 'color', selectors: { '.uncoder-btn:is(:hover, :focus-visible)': 'background-color: {{VALUE}}' } },
    padding: { type: 'dimensions', responsive: true, selectors: { '.uncoder-btn': 'padding: {{VALUE}}' } },
    radius: { type: 'dimensions', selectors: { '.uncoder-btn': 'border-radius: {{VALUE}}' } },
    border: { type: 'border', selector: '.uncoder-btn' },
    hover_border_color: { type: 'color', selectors: { '.uncoder-btn:hover': 'border-color: {{VALUE}}' } },
    shadow: { type: 'box_shadow', selector: '.uncoder-btn' },
    hover_effect: {
      type: 'select',
      options: { '': 'None', lift: 'Lift', grow: 'Grow', shrink: 'Shrink', pulse: 'Pulse', shine: 'Shine', arrow: 'Nudge icon', fill: 'Fill from left', 'fill-up': 'Fill from bottom', underline: 'Underline', flip: 'Text roll' },
      description: 'Used by every button that has no hover effect of its own. Off for visitors who prefer reduced motion.',
    },
    hover_fill: { type: 'color', selectors: { '.uncoder-btn': '--uncoder-btn-fill: {{VALUE}}' }, description: 'The color that sweeps in with the Fill hover effects.' },
  },
  forms: {
    label_color: { type: 'color', selectors: { '.uncoder .uncoder-form__label': 'color: {{VALUE}}' } },
    label_typography: { type: 'typography', selector: '.uncoder .uncoder-form__label' },
    field_typography: { type: 'typography', selector: '.uncoder .uncoder-form__field' },
    field_color: { type: 'color', selectors: { '.uncoder .uncoder-form__field': 'color: {{VALUE}}' } },
    field_background: { type: 'color', selectors: { '.uncoder .uncoder-form__field': 'background-color: {{VALUE}}' } },
    field_border_color: { type: 'color', selectors: { '.uncoder .uncoder-form__field': 'border-color: {{VALUE}}' } },
    focus_border_color: { type: 'color', selectors: { '.uncoder .uncoder-form__field:focus': 'border-color: {{VALUE}}; outline-color: {{VALUE}}' } },
    field_radius: { type: 'dimensions', selectors: { '.uncoder .uncoder-form__field': 'border-radius: {{VALUE}}' } },
    field_padding: { type: 'dimensions', selectors: { '.uncoder .uncoder-form__field': 'padding: {{VALUE}}' } },
  },
};

// Fields of a text style with a value on at least one device (see Kit::preset_fields()). A family set to a
// Design System font without a family means "the theme's font", so it is not a value.
function presetFields(value: Settings, devices: string[], unsetFonts: string[] = []): Set<string> {
  const out = new Set<string>();
  for (const field of Object.keys(PRESET_VARS)) {
    for (const device of devices) {
      const raw = value[field + suffix(device)];
      if (field === 'family' && unsetFonts.includes(raw)) continue;
      if (raw !== undefined && raw !== null && raw !== '' && (typeof raw !== 'object' || (raw.size !== '' && raw.size !== undefined))) {
        out.add(field);
        break;
      }
    }
  }
  return out;
}

const presetDecls = (id: string, fields: Set<string>) =>
  Object.entries(PRESET_VARS)
    .filter(([field]) => fields.has(field))
    .map(([, [prop, v]]) => `${prop}:var(--uncoder-t-${id}-${v})`);

function presetVars(id: string, value: Settings, rules: Rules, devices: string[]) {
  for (const device of devices) {
    const decls: string[] = [];
    for (const [field, [, v]] of Object.entries(PRESET_VARS)) {
      const raw = value[field + suffix(device)];
      if (raw === undefined || raw === null || raw === '') continue;
      let css: string;
      if (typeof raw === 'object') {
        if (raw.size === '' || raw.size === undefined) continue;
        css = raw.unit === 'custom' ? cssValue(raw.size) : `${raw.size}${raw.unit ?? ''}`;
      } else if (field === 'family') css = fontStack(String(raw));
      else css = cssValue(raw);
      decls.push(`--uncoder-t-${id}-${v}:${css}`);
    }
    if (decls.length) rules.add(':root', decls, device);
  }
}

/** Twin of Kit::dark_css(): dark color values while <html data-uncoder-scheme="dark">. */
export function darkCss(kit: Kit): string {
  if ((kit.settings?.color_scheme ?? 'light') === 'light') return '';
  const decls: string[] = [];
  for (const c of kit.colors ?? []) {
    const v = sanitizeColor(c.dark);
    if (v) decls.push(`--uncoder-c-${c.id}:${v}`);
  }
  decls.push('color-scheme:dark');
  return `html[data-uncoder-scheme=dark]{${decls.join(';')}}`;
}

/** Twin of Kit::button_fx_css(): the site-wide hover effect for buttons without their own. */
export function buttonFxCss(kit: Kit): string {
  const rules = (BUTTON_FX as Record<string, string[][]>)[String(kit.buttons?.hover_effect ?? '')];
  if (!rules?.length) return '';
  const css = rules.map(([sel, decls]) => `${sel.replace(/&/g, '.uncoder-btn:not([class*="uncoder-hover-"],.uncoder-btn--link)')}{${decls}}`).join('');
  return `@media (prefers-reduced-motion:no-preference){${css}}`;
}

/** Controls a global class can hold (twin of Kit::class_controls()): everything that becomes CSS. */
export function classControls(schema: ElementSchema): Record<string, ControlDef> {
  const out: Record<string, ControlDef> = {};
  const groups = new Set(['typography', 'background', 'border', 'box_shadow', 'text_shadow', 'css_filters', 'transform']);
  for (const [key, c] of Object.entries(schema.controls)) {
    if (c.type === 'repeater' || key === '_custom_css') continue;
    if ((c.selectors && Object.keys(c.selectors).length) || (groups.has(c.type) && c.selector)) out[key] = c;
  }
  return out;
}

export function kitCss(kit: Kit, breakpoints: Breakpoint[], schemas?: Record<string, ElementSchema>): string {
  const rules = new Rules();
  const devices = breakpoints.map((b) => b.id);
  const root: string[] = [];
  for (const c of kit.colors ?? []) {
    const v = sanitizeColor(c.value);
    if (v) root.push(`--uncoder-c-${c.id}:${v}`);
  }
  const unsetFonts: string[] = [];
  for (const f of kit.fonts ?? []) {
    root.push(`--uncoder-f-${f.id}:${f.family ? fontStack(f.family) : 'inherit'}`);
    if (!f.family) unsetFonts.push(`var(--uncoder-f-${f.id})`);
  }
  for (const v of kit.variables ?? []) {
    const value = cssValue(v.value);
    if (value) root.push(`--uncoder-v-${v.id}:${value}`);
  }
  rules.add(':root', root);

  // Only fields a text style defines are used: var() of an undefined variable would unset the property.
  const defined = new Map<string, Set<string>>();
  for (const p of kit.typography ?? []) {
    defined.set(p.id, presetFields(p.value ?? {}, devices, unsetFonts));
    presetVars(p.id, p.value ?? {}, rules, devices);
    rules.add(`.uncoder-t-${p.id}`, presetDecls(p.id, defined.get(p.id)!));
  }
  for (const section of ['layout', 'buttons', 'forms'] as const) {
    settingsCss((kit as any)[section] ?? {}, KIT_SCHEMAS[section], '', rules, devices);
  }
  // Global classes (see Kit::css()).
  for (const c of kit.classes ?? []) {
    const schema = schemas?.[c.type];
    if (schema && c.settings && typeof c.settings === 'object') settingsCss(c.settings, schema.controls, `.uncoder .uncoder-gc-${c.id}`, rules, devices);
  }

  const theme = kit.theme ?? {};
  if (theme.enabled) {
    const body: string[] = defined.has('body') ? presetDecls('body', defined.get('body')!) : [];
    const bc = sanitizeColor(theme.body_color);
    if (bc) body.push(`color:${bc}`);
    const bg = sanitizeColor(theme.background);
    if (bg) body.push(`background-color:${bg}`);
    // Twin of Kit::css(): the page texture, tiled at its own size or covering the page.
    const image = theme.background_image && typeof theme.background_image === 'object' ? cssUrl(theme.background_image.url) : '';
    if (image) {
      body.push(`background-image:url("${image}")`);
      body.push(theme.background_size === 'cover' ? 'background-size:cover;background-position:center top' : 'background-repeat:repeat');
    }
    if (theme.optical_sizing === 'none') body.push('font-optical-sizing:none');
    rules.add('body.uncoder-kit', body);
    const hc = sanitizeColor(theme.heading_color);
    for (const h of ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']) {
      const decls: string[] = defined.has(h) ? presetDecls(h, defined.get(h)!) : [];
      if (hc) decls.push(`color:var(--uncoder-heading-color,${hc})`);
      rules.add(`.uncoder-kit ${h}`, decls);
    }
    const link = sanitizeColor(theme.link_color);
    if (link) rules.add('.uncoder :where(a:not(.uncoder-btn))', `color:${link}`); // (0,1,0): widget link styles win.
    const hover = sanitizeColor(theme.link_hover_color);
    if (hover) rules.add('.uncoder :where(a:not(.uncoder-btn):hover)', `color:${hover}`);
  }
  let css = rules.render(breakpoints) + darkCss(kit) + buttonFxCss(kit);
  if (kit.custom_css) css += '\n' + sanitizeCustomCss(kit.custom_css);
  return css;
}

export { mediaQuery };
