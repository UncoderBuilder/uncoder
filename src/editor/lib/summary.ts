// One-line summaries of a settings group ("M 0 · P 0 24", "Ink #100F0F") so collapsed groups in the
// inspector can be read without opening them. Values are the ones in effect on the current device.
import type { ControlDef, ElementSchema, SectionDef, Settings } from '@shared/types';
import { readValue } from './schema';
import { useKit } from '../store/kit';

const SHORT: Record<string, string> = {
  _margin: 'M',
  _padding: 'P',
  margin: 'M',
  padding: 'P',
  _width: 'W',
  _custom_width: 'W',
  width: 'W',
  max_width: 'max',
  min_height: 'min H',
  gap: 'Gap',
  _z_index: 'Z',
};

const unitless = (u: unknown) => (u === 'px' || !u ? '' : String(u));

function colorName(v: string): string {
  const m = v.match(/^var\(--uncoder-c-([a-z0-9_-]+)\)$/i);
  if (m) return useKit.getState().kit.colors?.find((c) => c.id === m[1])?.name ?? m[1];
  return v.startsWith('#') ? v.toUpperCase() : v;
}

function fmt(value: unknown, control: ControlDef): string {
  if (value === undefined || value === null || value === '') return '';
  switch (control.type) {
    case 'dimensions': {
      const d = value as Record<string, unknown>;
      const u = unitless(d.unit);
      const parts = ['top', 'right', 'bottom', 'left'].map((k) => (d[k] === '' || d[k] === undefined ? '–' : String(d[k])));
      const [t, r, b, l] = parts;
      const s = t === r && r === b && b === l ? t : t === b && r === l ? `${t} ${r}` : parts.join(' ');
      return s + u;
    }
    case 'slider': {
      const v = value as { size?: unknown; unit?: unknown };
      if (v.size === '' || v.size === undefined) return '';
      return v.unit === 'custom' ? String(v.size) : `${v.size}${unitless(v.unit)}`;
    }
    case 'color':
      return colorName(String(value));
    case 'typography': {
      const t = value as Record<string, any>;
      if (t.preset) return useKit.getState().kit.typography?.find((p) => p.id === t.preset)?.name ?? String(t.preset);
      const size = t.size && typeof t.size === 'object' ? `${t.size.size ?? ''}${unitless(t.size.unit)}` : '';
      return [t.family, size, t.weight].filter(Boolean).join(' ');
    }
    case 'background': {
      const g = value as Record<string, any>;
      if (g.type === 'gradient') return 'Gradient';
      if (g.type === 'video') return 'Video';
      if (g.type === 'slideshow') return 'Slideshow';
      if (g.image?.url) return 'Image';
      return g.color ? colorName(String(g.color)) : '';
    }
    case 'border': {
      const g = value as Record<string, any>;
      if (!g.style || g.style === 'none') return g.style === 'none' ? 'None' : '';
      return [g.style, g.color ? colorName(String(g.color)) : ''].filter(Boolean).join(' ');
    }
    case 'box_shadow':
    case 'text_shadow':
      return 'Shadow';
    case 'css_filters':
    case 'transform':
      return 'On';
    case 'switch':
      return value ? 'On' : '';
    case 'select':
    case 'choose': {
      const opt = (control.options as Record<string, any> | undefined)?.[String(value)];
      const label = typeof opt === 'string' ? opt : opt?.label ?? opt?.title;
      return String(label ?? value);
    }
    case 'media':
      return (value as { url?: string }).url ? 'Image' : '';
    case 'number':
      return String(value);
    case 'text':
    case 'textarea':
      return String(value).replace(/<[^>]*>/g, '').slice(0, 24);
    default:
      return '';
  }
}

/** Summary of the values set in a section (up to three), or '' when everything is at its default. */
export function sectionSummary(schema: ElementSchema, section: SectionDef, settings: Settings, device: string): string {
  const keys = section.controls.filter((k) => !k.startsWith('@tabs:')).concat(Object.values(section.tab_controls ?? {}).flatMap((t) => (t.normal ?? Object.values(t)[0] ?? []) as string[]));
  const out: string[] = [];
  for (const key of keys) {
    const control = schema.controls[key];
    if (!control || control.type === 'heading' || control.type === 'notice' || control.type === 'divider') continue;
    const read = readValue(settings, key, control, device);
    if (read.from === null) continue;
    const text = fmt(read.value, control);
    if (!text) continue;
    out.push(SHORT[key] ? `${SHORT[key]} ${text}` : out.length ? text : text);
    if (out.length === 3) break;
  }
  return out.join(' · ');
}
