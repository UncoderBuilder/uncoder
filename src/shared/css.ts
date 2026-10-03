// CSS engine — TypeScript twin of includes/Core/Css/Generator.php + control types + groups.
// Keep both implementations in sync; tools/tests/css-parity.test.mjs compares their output.
import type { Breakpoint, ControlDef, ElementNode, ElementSchema, Settings } from './types';

/* ------------------------------------------------------------------ Rules */

export class Rules {
  private rules = new Map<string, Map<string, Map<string, string>>>();
  private raw = new Map<string, string[]>();

  add(selector: string, declarations: string | string[], device = 'desktop') {
    selector = selector.trim();
    if (!selector) return;
    const list = Array.isArray(declarations) ? declarations : [declarations];
    for (const declaration of list) {
      for (let decl of String(declaration).split(';')) {
        decl = decl.trim();
        const idx = decl.indexOf(':');
        if (!decl || idx < 0) continue;
        const prop = decl.slice(0, idx).trim();
        const value = decl.slice(idx + 1).trim();
        if (!prop || !value) continue;
        let dev = this.rules.get(device);
        if (!dev) this.rules.set(device, (dev = new Map()));
        let sel = dev.get(selector);
        if (!sel) dev.set(selector, (sel = new Map()));
        sel.set(prop.toLowerCase(), `${prop}:${value}`);
      }
    }
  }

  addRaw(css: string, device = 'desktop') {
    css = css.trim();
    if (!css) return;
    const list = this.raw.get(device) ?? [];
    list.push(css);
    this.raw.set(device, list);
  }

  render(breakpoints: Breakpoint[]): string {
    let out = '';
    for (const bp of breakpoints) {
      const block = this.renderDevice(bp.id);
      if (!block) continue;
      if (bp.id === 'desktop') out += block;
      else out += `${mediaQuery(bp)}{${block}}`;
    }
    return out;
  }

  private renderDevice(device: string): string {
    let out = '';
    for (const [selector, props] of this.rules.get(device) ?? []) {
      out += `${selector}{${[...props.values()].join(';')}}`;
    }
    for (const raw of this.raw.get(device) ?? []) out += raw;
    return out;
  }
}

export const mediaQuery = (bp: Breakpoint) =>
  bp.direction === 'min' ? `@media (min-width:${bp.value}px)` : `@media (max-width:${bp.value}px)`;

export const suffix = (device: string) => (device === 'desktop' ? '' : `_${device}`);

/* ------------------------------------------------------------------ Value helpers */

/** Twin of Dimensions::is_var(): a design variable as a value, var(--uncoder-v-{id}). */
export const isVar = (v: unknown): v is string => typeof v === 'string' && /^var\(--uncoder-v-[a-z0-9-]+\)$/.test(v);

export function cssValue(value: unknown): string {
  if (typeof value === 'boolean') return value ? '1' : '0';
  if (typeof value !== 'string' && typeof value !== 'number') return '';
  let v = String(value).replace(/<[^>]*>/g, '');
  v = v.replace(/[{};<>\\"\n\r]/g, '');
  if (/(expression\s*\(|javascript:|@import|behavior\s*:|-moz-binding)/i.test(v)) return '';
  return v.trim();
}

export function cssUrl(url: unknown): string {
  if (typeof url !== 'string' || !/^(https?:)?\/\//i.test(url.trim()) && !url.startsWith('/')) return '';
  return url.trim().replace(/"/g, '%22').replace(/'/g, '%27').replace(/\(/g, '%28').replace(/\)/g, '%29').replace(/\\/g, '').replace(/ /g, '%20');
}

export function sanitizeColor(value: unknown): string {
  if (typeof value !== 'string') return '';
  const v = value.trim();
  if (!v) return '';
  if (/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(v)) return v.toLowerCase();
  if (/^(rgba?|hsla?|oklch|oklab|lab|lch|hwb|color-mix)\([a-z0-9#.,%\s/\-()]+\)$/i.test(v)) return cssValue(v);
  if (/^var\(--[a-z0-9\-_]+(\s*,\s*[#a-z0-9.,%\s\-()]+)?\)$/i.test(v)) return v;
  if (/^[a-z]{3,30}$/i.test(v)) return v.toLowerCase();
  return '';
}

export function sizeCss(v: any): string | null {
  if (!v || typeof v !== 'object' || v.size === '' || v.size === undefined || v.size === null) return null;
  const unit = String(v.unit ?? 'px');
  if (unit === 'custom') return cssValue(v.size);
  return `${v.size}${unit}`;
}

const GOOGLE_CATEGORY = new Map<string, string>();
export function registerFontCategories(map: Record<string, { c: string }>) {
  for (const [family, data] of Object.entries(map)) GOOGLE_CATEGORY.set(family, data.c);
}

const SYSTEM_FONTS: Record<string, string> = {
  'system-ui': 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
  'sans-serif': 'ui-sans-serif, system-ui, sans-serif',
  serif: 'ui-serif, Georgia, Cambria, "Times New Roman", serif',
  monospace: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
};

export function fontStack(family: string): string {
  family = family.trim();
  if (family.startsWith('var(')) return cssValue(family);
  if (SYSTEM_FONTS[family]) return SYSTEM_FONTS[family];
  const clean = family.replace(/[^A-Za-z0-9 \-_.]/g, '');
  const c = GOOGLE_CATEGORY.get(clean) ?? '';
  const generic = c === 'serif' ? 'serif' : c === 'monospace' ? 'monospace' : c === 'handwriting' ? 'cursive' : 'sans-serif';
  return `"${clean}", ${generic}`;
}

/* ------------------------------------------------------------------ Placeholders by control type */

type PH = Record<string, string>;

export function placeholders(type: string, value: any): PH | null {
  switch (type) {
    case 'slider': {
      if (!value || typeof value !== 'object' || value.size === '' || value.size === undefined) return null;
      const unit = String(value.unit ?? 'px');
      if (unit === 'custom') {
        const c = cssValue(value.size);
        return { SIZE: c, UNIT: '', VALUE: c };
      }
      return { SIZE: String(value.size), UNIT: unit, VALUE: `${value.size}${unit}` };
    }
    case 'dimensions': {
      if (!value || typeof value !== 'object') return null;
      const unit = String(value.unit ?? 'px');
      const map: PH = { UNIT: unit };
      const vals: string[] = [];
      let any = false;
      for (const side of ['top', 'right', 'bottom', 'left']) {
        const v = value[side] ?? '';
        if (v !== '') any = true;
        map[side.toUpperCase()] = v === '' ? '' : String(v);
        vals.push(v === '' ? '0' : v === 'auto' || isVar(v) ? String(v) : `${v}${Number(v) === 0 ? '' : unit}`);
      }
      if (!any) return null;
      map.VALUE = vals.join(' ');
      return map;
    }
    case 'media': {
      if (!value || typeof value !== 'object' || !value.url) return null;
      const u = cssUrl(value.url);
      return u ? { URL: u, VALUE: u } : null;
    }
    case 'switch':
      return value === true ? { VALUE: '1' } : null;
    case 'number':
      return value === '' || value === null || value === undefined ? null : { VALUE: String(value) };
    case 'font':
      return typeof value === 'string' && value ? { VALUE: fontStack(value) } : null;
    case 'color': {
      const c = sanitizeColor(value);
      return c ? { VALUE: c } : null;
    }
    case 'repeater':
    case 'gallery':
    case 'icon':
    case 'url':
    case 'link':
    case 'multiselect':
    case 'select2':
      return null;
    default:
      if (value === null || value === undefined || value === '' || typeof value === 'object') return null;
      return { VALUE: cssValue(value) };
  }
}

/* ------------------------------------------------------------------ Groups */

const PRESET_VARS: Record<string, [string, string]> = {
  family: ['font-family', 'ff'],
  size: ['font-size', 'fs'],
  weight: ['font-weight', 'fw'],
  transform: ['text-transform', 'tt'],
  style: ['font-style', 'fst'],
  decoration: ['text-decoration', 'td'],
  line_height: ['line-height', 'lh'],
  letter_spacing: ['letter-spacing', 'ls'],
};
export { PRESET_VARS };

const TRANSFORM_VARS: Record<string, string> = {
  translate_x: '--uncoder-tx',
  translate_y: '--uncoder-ty',
  rotate: '--uncoder-rot',
  scale: '--uncoder-sc',
  skew_x: '--uncoder-skx',
  skew_y: '--uncoder-sky',
};
export const TRANSFORM_DECL =
  'transform:translate(var(--uncoder-tx,0),var(--uncoder-ty,0)) rotate(var(--uncoder-rot,0deg)) scale(var(--uncoder-sc,1)) skew(var(--uncoder-skx,0deg),var(--uncoder-sky,0deg))';

const isEmpty = (v: any) => v === null || v === undefined || v === '' || (typeof v === 'object' && 'size' in v && (v.size === '' || v.size === undefined));
const dv = (value: Settings, key: string, device: string) => value[key + suffix(device)];
const anyDevice = (value: Settings, key: string, devices: string[]) => devices.some((d) => !isEmpty(dv(value, key, d)));

function groupDeclarations(type: string, value: Settings, device: string, devices: string[]): string[] {
  const d: string[] = [];
  switch (type) {
    case 'typography': {
      const preset = device === 'desktop' ? String(value.preset ?? '').replace(/[^a-z0-9_\-]/g, '') : '';
      if (preset) {
        for (const [field, [prop, v]] of Object.entries(PRESET_VARS)) {
          if (isEmpty(value[field])) d.push(`${prop}:var(--uncoder-t-${preset}-${v})`);
        }
      }
      const family = dv(value, 'family', device);
      if (typeof family === 'string' && family) d.push(`font-family:${fontStack(family)}`);
      const size = sizeCss(dv(value, 'size', device));
      if (size !== null) d.push(`font-size:${size}`);
      for (const [field, prop] of [['weight', 'font-weight'], ['transform', 'text-transform'], ['style', 'font-style'], ['decoration', 'text-decoration']] as const) {
        const v = dv(value, field, device);
        if (typeof v === 'string' && v) d.push(`${prop}:${cssValue(v)}`);
      }
      for (const [field, prop] of [['line_height', 'line-height'], ['letter_spacing', 'letter-spacing'], ['word_spacing', 'word-spacing']] as const) {
        const v = sizeCss(dv(value, field, device));
        if (v !== null) d.push(`${prop}:${v}`);
      }
      return d;
    }
    case 'background': {
      const t = String(value.type ?? '');
      if (!t) return d;
      if (t === 'gradient') {
        if (device !== 'desktop') return d;
        const a = sanitizeColor(value.color) || 'transparent';
        const b = sanitizeColor(value.color_b) || 'transparent';
        const as = sizeCss(value.color_stop) ?? '0%';
        const bs = sizeCss(value.color_b_stop) ?? '100%';
        d.push('background-color:transparent');
        if ((value.gradient_type ?? 'linear') === 'radial') {
          const pos = cssValue(value.gradient_position ?? 'center center') || 'center center';
          d.push(`background-image:radial-gradient(at ${pos}, ${a} ${as}, ${b} ${bs})`);
        } else {
          const angle = sizeCss(value.gradient_angle) ?? '180deg';
          d.push(`background-image:linear-gradient(${angle}, ${a} ${as}, ${b} ${bs})`);
        }
        return d;
      }
      if (device === 'desktop') {
        const c = sanitizeColor(value.color);
        if (c) d.push(`background-color:${c}`);
        if (value.attachment) d.push(`background-attachment:${cssValue(value.attachment)}`);
      }
      // Videos and slideshows render their own layer; the box keeps the color (and a video's image fallback).
      if (t === 'slideshow' || (t === 'video' && device !== 'desktop')) return d;
      const img = dv(value, 'image', device);
      if (img && typeof img === 'object' && img.url) {
        const u = cssUrl(img.url);
        if (u) d.push(`background-image:url("${u}")`);
      }
      for (const [field, prop] of [['position', 'background-position'], ['repeat', 'background-repeat'], ['size', 'background-size']] as const) {
        const v = dv(value, field, device);
        if (typeof v === 'string' && v) d.push(`${prop}:${cssValue(v)}`);
      }
      return d;
    }
    case 'border': {
      const style = String(value.style ?? '');
      if (device === 'desktop') {
        if (style) d.push(`border-style:${cssValue(style)}`);
        else if (anyDevice(value, 'width', devices)) d.push('border-style:solid');
        const c = sanitizeColor(value.color);
        if (c) d.push(`border-color:${c}`);
      }
      if (style === 'none') return d;
      const w = dv(value, 'width', device);
      if (w && typeof w === 'object') {
        const unit = w.unit ?? 'px';
        for (const side of ['top', 'right', 'bottom', 'left']) {
          const v = w[side];
          if (v !== '' && v !== undefined && v !== null) d.push(`border-${side}-width:${v}${unit}`);
        }
      }
      return d;
    }
    case 'box_shadow':
    case 'text_shadow': {
      if (device !== 'desktop') return d;
      const c = sanitizeColor(value.color);
      if (!c) return d;
      const n = (k: string) => `${Number.isFinite(Number(value[k])) && value[k] !== '' ? Number(value[k]) : 0}px`;
      if (type === 'text_shadow') return [`text-shadow:${n('x')} ${n('y')} ${n('blur')} ${c}`];
      return [`box-shadow:${value.inset ? 'inset ' : ''}${n('x')} ${n('y')} ${n('blur')} ${n('spread')} ${c}`];
    }
    case 'css_filters':
    case 'backdrop_filter': {
      if (device !== 'desktop') return d;
      const map: [string, string, string][] = [
        ['blur', 'blur', 'px'], ['brightness', 'brightness', '%'], ['contrast', 'contrast', '%'],
        ['saturate', 'saturate', '%'], ['grayscale', 'grayscale', '%'], ['hue', 'hue-rotate', 'deg'],
      ];
      const parts = map.filter(([k]) => value[k] !== '' && value[k] !== undefined && Number.isFinite(Number(value[k]))).map(([k, fn, u]) => `${fn}(${Number(value[k])}${u})`);
      if (!parts.length) return d;
      const fns = parts.join(' ');
      return type === 'backdrop_filter' ? [`-webkit-backdrop-filter:${fns}`, `backdrop-filter:${fns}`] : [`filter:${fns}`];
    }
    case 'transform': {
      for (const [field, v] of Object.entries(TRANSFORM_VARS)) {
        const raw = dv(value, field, device);
        if (field === 'scale') {
          if (raw !== '' && raw !== undefined && raw !== null && Number.isFinite(Number(raw))) d.push(`${v}:${Number(raw)}`);
          continue;
        }
        const s = sizeCss(raw);
        if (s !== null) d.push(`${v}:${s}`);
      }
      return d;
    }
  }
  return d;
}

const GROUP_TYPES = new Set(['typography', 'background', 'border', 'box_shadow', 'text_shadow', 'css_filters', 'backdrop_filter', 'transform', 'query']);
export const isGroupType = (t: string) => GROUP_TYPES.has(t);

function groupCss(type: string, value: Settings, selector: string, rules: Rules, devices: string[]) {
  if (type === 'query') return;
  if (type === 'transform') {
    const used = Object.keys(TRANSFORM_VARS).some((k) => anyDevice(value, k, devices));
    if (!used) return;
    for (const device of devices) {
      const decls = groupDeclarations(type, value, device, devices);
      if (device === 'desktop') decls.push(TRANSFORM_DECL);
      if (decls.length) rules.add(selector, decls, device);
    }
    return;
  }
  for (const device of devices) {
    const decls = groupDeclarations(type, value, device, devices);
    if (decls.length) rules.add(selector, decls, device);
  }
}

/* ------------------------------------------------------------------ Conditions */

export function conditionsMet(control: ControlDef, settings: Settings, controls: Record<string, ControlDef>): boolean {
  const cond = control.condition;
  if (!cond || typeof cond !== 'object') return true;
  for (let [key, expected] of Object.entries(cond)) {
    const negate = key.endsWith('!');
    key = key.replace(/!$/, '');
    const [root, sub] = key.split('.', 2);
    let actual: any = root in settings ? settings[root] : controls[root]?.default ?? '';
    if (sub !== undefined) actual = actual && typeof actual === 'object' ? actual[sub] ?? '' : '';
    if (typeof actual === 'boolean' && typeof expected === 'string') actual = actual ? 'yes' : '';
    const match = Array.isArray(expected)
      ? expected.includes(actual)
      : actual === expected || ((typeof actual === 'string' || typeof actual === 'number') && String(actual) === String(expected));
    if (match === negate) return false;
  }
  return true;
}

/* ------------------------------------------------------------------ Generator */

const LONGHAND: Record<string, string[]> = {
  padding: ['padding-top', 'padding-right', 'padding-bottom', 'padding-left'],
  margin: ['margin-top', 'margin-right', 'margin-bottom', 'margin-left'],
  'border-width': ['border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width'],
  'border-radius': ['border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius'],
  inset: ['top', 'right', 'bottom', 'left'],
};

/** Twin of Utils::element_anchor(): the id attribute an element prints, only its anchor id (_css_id). */
export const elementAnchor = (settings?: Settings): string =>
  typeof settings?._css_id === 'string' ? settings._css_id.replace(/%[a-fA-F0-9]{2}/g, '').replace(/[^A-Za-z0-9_-]/g, '') : '';

/** Twin of Utils::element_selector(): the element's own class uncoder-{id} inside the document scope. */
export const elementSelector = (id: string): string => `.uncoder .uncoder-${id}`;

export interface GeneratorOptions {
  docId: number | string;
  breakpoints: Breakpoint[];
  schemas: Record<string, ElementSchema>;
}

export function sanitizeCustomCss(css: string): string {
  return css.replace(/<\/?\s*style[^>]*>/gi, '').replace(/</g, '').replace(/(expression\s*\(|javascript:|behavior\s*:|-moz-binding)/gi, '').trim();
}

export function settingsCss(
  settings: Settings,
  controls: Record<string, ControlDef>,
  wrapper: string,
  rules: Rules,
  devices: string[],
  currentItem = '',
  fonts?: Map<string, Set<string>>,
  /** A state's values are printed alone, but conditions and references read the element's settings too. */
  context?: Settings,
) {
  const sel = (tpl: string) => tpl.split('{{CURRENT_ITEM}}').join(currentItem).split('{{WRAPPER}}').join(wrapper);
  const ctx = context ?? settings;

  for (const [key, control] of Object.entries(controls)) {
    const type = control.type;
    if (['heading', 'divider', 'notice'].includes(type)) continue;
    if (!conditionsMet(control, ctx, controls)) continue;

    if (isGroupType(type)) {
      if (!control.selector) continue;
      const value = settings[key] ?? (control.css_default && !context ? control.default : undefined);
      if (!value || typeof value !== 'object' || Array.isArray(value) || !Object.keys(value).length) continue;
      groupCss(type, value, sel(control.selector), rules, devices);
      if (type === 'typography' && fonts) collectFonts(value, fonts);
      continue;
    }
    if (type === 'repeater') {
      for (const row of (settings[key] ?? []) as Settings[]) {
        if (row && row._id) settingsCss(row, control.fields ?? {}, wrapper, rules, devices, `.uncoder-ri-${String(row._id).replace(/[^A-Za-z0-9_\-]/g, '')}`, fonts);
      }
      continue;
    }
    if (!control.selectors) continue;

    const list = control.responsive ? devices : ['desktop'];
    for (const device of list) {
      const full = key + suffix(device);
      let value: any;
      if (full in settings) value = settings[full];
      else if (device === 'desktop' && control.css_default && control.default !== undefined && !context) value = control.default;
      else continue;

      const ph = placeholders(type, value);
      if (!ph) continue;
      if (control.selectors_dictionary && (typeof value === 'string' || typeof value === 'number') && String(value) in control.selectors_dictionary) {
        ph.VALUE = String(control.selectors_dictionary[String(value)]);
      }
      if (type === 'font' && typeof value === 'string' && fonts && !fonts.has(value)) fonts.set(value, new Set());

      for (const [selTpl, declTpl] of Object.entries(control.selectors)) {
        const decls = fillDeclarations(declTpl, ph, type, ctx, controls, device);
        if (decls.length) rules.add(sel(selTpl), decls, device);
      }
    }
  }
}

function fillDeclarations(tpl: string, ph: PH, type: string, settings: Settings, controls: Record<string, ControlDef>, device: string): string[] {
  const out: string[] = [];
  for (let decl of tpl.split(';')) {
    decl = decl.trim();
    if (!decl) continue;
    if (type === 'dimensions') {
      const m = decl.match(/^([a-z\-]+)\s*:\s*\{\{VALUE\}\}(\s*!important)?$/i);
      if (m && LONGHAND[m[1].toLowerCase()]) {
        const important = m[2] ? ' !important' : '';
        ['top', 'right', 'bottom', 'left'].forEach((side, i) => {
          const v = ph[side.toUpperCase()] ?? '';
          if (v === '') return;
          out.push(`${LONGHAND[m[1].toLowerCase()][i]}:${v === 'auto' || isVar(v) ? v : v + (v === '0' ? '' : ph.UNIT)}${important}`);
        });
        continue;
      }
    }
    let missing = false;
    const filled = decl.replace(/\{\{([A-Za-z0-9_]+\.)?([A-Z]+)\}\}/g, (_m, ref: string | undefined, name: string) => {
      if (ref) {
        const r = reference(ref.slice(0, -1), name, settings, controls, device);
        if (r === null || r === '') missing = true;
        return r ?? '';
      }
      const v = ph[name];
      if (v === undefined || (v === '' && name !== 'UNIT')) {
        missing = true;
        return '';
      }
      return v;
    });
    if (!missing) out.push(filled);
  }
  return out;
}

function reference(key: string, name: string, settings: Settings, controls: Record<string, ControlDef>, device: string): string | null {
  const control = controls[key];
  if (!control) return null;
  const value = settings[key + suffix(device)] ?? settings[key] ?? control.default;
  if (value === undefined || value === null) return null;
  return placeholders(control.type, value)?.[name] ?? null;
}

function collectFonts(t: Settings, fonts: Map<string, Set<string>>) {
  for (const [k, v] of Object.entries(t)) {
    if (k.startsWith('family') && typeof v === 'string' && v && !v.startsWith('var(')) {
      let w = t.weight ? String(t.weight) : '400';
      w = w === 'bold' ? '700' : w === 'normal' ? '400' : w;
      const set = fonts.get(v) ?? new Set<string>();
      set.add(w);
      set.add('400');
      fonts.set(v, set);
    }
  }
}

/* ---------------------------------------------------------------- States (`_states`) */

/** Built-in states of the inspector's state switch; any other key is a custom selector around "&". */
export const STATE_KEYS = ['hover', 'focus', 'active', 'before', 'after'];

/** Twin of Generator::clean_state(): a custom state is a selector containing "&" (the element itself). */
export function cleanState(state: string): string {
  if (STATE_KEYS.includes(state)) return state;
  const v = state.replace(/[{}<;@\\]/g, '').replace(/\s+/g, ' ').trim().slice(0, 120);
  return v.includes('&') ? v : '';
}

/** Twin of Generator::state_selector(). */
export function stateSelector(wrapper: string, state: string): string {
  switch (state) {
    case 'hover':
      return `${wrapper}:hover`;
    case 'focus':
      return `${wrapper}:is(:focus-visible,:has(:focus-visible))`;
    case 'active':
      return `${wrapper}:active`;
    case 'before':
      return `${wrapper}::before`;
    case 'after':
      return `${wrapper}::after`;
  }
  const custom = cleanState(state);
  return custom ? custom.split('&').join(wrapper) : '';
}

/**
 * Pseudo-elements, and custom selectors that point somewhere else (`& > .icon`), style with the controls that
 * target the element itself only; hover / focus / active and same-element selectors (`&.is-open`) use all.
 */
export const stateIsSelfOnly = (state: string): boolean => (state === 'before' || state === 'after' ? true : STATE_KEYS.includes(state) ? false : !/^&[.:[]/.test(cleanState(state)));

/** Twin of Generator::self_controls(): controls reduced to their `{{WRAPPER}}` rules. */
function selfControls(controls: Record<string, ControlDef>): Record<string, ControlDef> {
  const out: Record<string, ControlDef> = {};
  for (const [k, c] of Object.entries(controls)) {
    if (isGroupType(c.type)) {
      if ((c.selector ?? '').trim() === '{{WRAPPER}}') out[k] = c;
      continue;
    }
    if (!c.selectors) continue;
    const own = Object.entries(c.selectors).filter(([s]) => s.trim() === '{{WRAPPER}}');
    if (own.length) out[k] = { ...c, selectors: Object.fromEntries(own) };
  }
  return out;
}

/** Twin of Generator::states_css(): each state's values under its selector (`_states` = { state: settings }). */
export function statesCss(settings: Settings, controls: Record<string, ControlDef>, wrapper: string, rules: Rules, devices: string[], fonts?: Map<string, Set<string>>, force?: string): void {
  const states = settings._states;
  if (!states || typeof states !== 'object' || Array.isArray(states)) return;
  for (const [state, values] of Object.entries(states as Record<string, Settings>)) {
    if (!values || typeof values !== 'object' || Array.isArray(values) || !Object.keys(values).length) continue;
    // The editor previews the state being edited on the selected element (force = that state).
    const selector = force === state && ['hover', 'focus', 'active'].includes(state) ? `${wrapper}[data-uncoder-ui-force]` : stateSelector(wrapper, state);
    if (!selector) continue;
    if (state === 'before' || state === 'after') rules.add(selector, ['content:""'], 'desktop');
    settingsCss(values, stateIsSelfOnly(state) ? selfControls(controls) : controls, selector, rules, devices, '', fonts, { ...settings, ...values });
  }
}

/** CSS for one element (not its children). */
export function elementCss(node: ElementNode, opts: GeneratorOptions, fonts?: Map<string, Set<string>>, force?: string): string {
  const schema = opts.schemas[node.type];
  if (!schema) return '';
  const rules = new Rules();
  const devices = opts.breakpoints.map((b) => b.id);
  const settings = normalizeSettings(node.settings);
  const wrapper = elementSelector(node.id);
  settingsCss(settings, schema.controls, wrapper, rules, devices, '', fonts);
  statesCss(settings, schema.controls, wrapper, rules, devices, fonts, force);
  if (node.type === 'container') stackCss(settings, wrapper, rules, devices);
  customCss(settings, '_custom_css', wrapper, rules, devices);
  return rules.render(opts.breakpoints);
}

/** Twin of Generator::stack_css(): a row that stacks at a breakpoint gives its child containers the full width there. */
export function stackCss(settings: Settings, wrapper: string, rules: Rules, devices: string[]): void {
  if ((settings.layout ?? 'flex') === 'grid' || !['row', 'row-reverse'].includes(settings.direction ?? 'column')) return;
  for (const device of devices) {
    if (device === 'desktop') continue;
    const dir = settings['direction' + suffix(device)];
    if (dir === 'column' || dir === 'column-reverse') rules.add(`${wrapper} > :where(.uncoder-container),${wrapper} > :where(.uncoder-container__inner) > :where(.uncoder-container)`, ['--uncoder-width:var(--uncoder-full,100%)'], device);
  }
}

/** Twin of Generator::custom_css(): per-device custom CSS, "selector" → wrapper. */
export function customCss(settings: Settings, key: string, wrapper: string, rules: Rules, devices: string[]): void {
  for (const device of devices) {
    const custom = settings[key + suffix(device)];
    if (typeof custom === 'string' && custom.trim()) rules.addRaw(sanitizeCustomCss(custom).split('selector').join(wrapper), device);
  }
}

export const normalizeSettings = (s: any): Settings => (s && typeof s === 'object' && !Array.isArray(s) ? s : {});

/** CSS for a whole tree (used by the parity test and the page-level export). */
export function documentCss(elements: ElementNode[], opts: GeneratorOptions): { css: string; fonts: Record<string, string[]> } {
  const fonts = new Map<string, Set<string>>();
  const rules = new Rules();
  const devices = opts.breakpoints.map((b) => b.id);
  const walk = (nodes: ElementNode[]) => {
    for (const node of nodes) {
      const schema = opts.schemas[node.type];
      if (schema && /^[a-z][a-z0-9]{2,31}$/.test(node.id)) {
        const settings = normalizeSettings(node.settings);
        const wrapper = elementSelector(node.id);
        settingsCss(settings, schema.controls, wrapper, rules, devices, '', fonts);
        statesCss(settings, schema.controls, wrapper, rules, devices, fonts);
        if (node.type === 'container') stackCss(settings, wrapper, rules, devices);
        customCss(settings, '_custom_css', wrapper, rules, devices);
      }
      if (node.children?.length) walk(node.children);
    }
  };
  walk(elements);
  const out: Record<string, string[]> = {};
  for (const [family, weights] of fonts) out[family] = [...weights];
  return { css: rules.render(opts.breakpoints), fonts: out };
}
