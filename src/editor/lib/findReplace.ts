// Find & replace in the open document. Twin of Site\Find_Replace (PHP): matches by control type, so
// "text" only touches text / textarea / rich text settings, "links" URL fields and "colors" color
// settings (also inside background, border and shadow groups) — never layout or style values.
import type { ControlDef, Settings } from '@shared/types';

export type Scope = 'text' | 'links' | 'colors' | 'all';

export interface FindOptions {
  find: string;
  replace: string;
  scope: Scope;
  matchCase: boolean;
}

export interface FindHit {
  where: string;
  text: string;
  count: number;
}

const DEVICE = /_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/;
const ADVANCED_GROUPS = new Set(['_background', '_border', '_shadow']);
const escape = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

function swap(value: string, o: FindOptions, color: boolean, hits: FindHit[], where: string): string {
  const re = new RegExp(escape(o.find), o.matchCase && !color ? 'g' : 'gi');
  let count = 0;
  const next = value.replace(re, () => (count++, o.replace));
  if (count) {
    const plain = value.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    hits.push({ where, text: (plain || value).slice(0, 120), count });
  }
  return next;
}

/**
 * Returns the settings with every match replaced (unchanged when nothing matched) and records hits.
 */
export function replaceInSettings(settings: Settings, controls: Record<string, ControlDef>, o: FindOptions, hits: FindHit[], where: string): Settings {
  const scopes = o.scope === 'all' ? ['text', 'links', 'colors'] : [o.scope];
  let out: Settings | null = null;
  const set = (k: string, v: any) => {
    out ??= { ...settings };
    out[k] = v;
  };
  for (const [key, value] of Object.entries(settings)) {
    if (key.startsWith('_') && !ADVANCED_GROUPS.has(key)) continue;
    const control = controls[key] ?? controls[key.replace(DEVICE, '')];
    if (!control) continue;
    const label = `${where} · ${control.label ?? key}`;
    const t = control.type;
    if (['text', 'textarea', 'wysiwyg'].includes(t) && typeof value === 'string' && scopes.includes('text')) {
      const n = swap(value, o, false, hits, label);
      if (n !== value) set(key, n);
    } else if ((t === 'url' || t === 'link') && value && typeof value.url === 'string' && scopes.includes('links')) {
      const n = swap(value.url, o, false, hits, label);
      if (n !== value.url) set(key, { ...value, url: n });
    } else if (t === 'color' && typeof value === 'string' && scopes.includes('colors')) {
      const n = swap(value, o, true, hits, label);
      if (n !== value) set(key, n);
    } else if (t === 'overrides' && value && typeof value === 'object') {
      // Component overrides: texts and link URLs of this copy.
      let changed = false;
      const next: Settings = { ...value };
      for (const [k, v] of Object.entries(value as Settings)) {
        if (typeof v === 'string' && scopes.includes('text')) next[k] = swap(v, o, false, hits, label);
        else if (v && typeof v === 'object' && typeof v.url === 'string' && v.id === undefined && scopes.includes('links')) next[k] = { ...v, url: swap(v.url, o, false, hits, label) };
        if (next[k] !== v) changed = true;
      }
      if (changed) set(key, next);
    } else if (t === 'repeater' && Array.isArray(value)) {
      let changed = false;
      const rows = value.map((row: any) => {
        if (!row || typeof row !== 'object') return row;
        const r = replaceInSettings(row, control.fields ?? {}, o, hits, label);
        if (r !== row) changed = true;
        return r;
      });
      if (changed) set(key, rows);
    } else if (value && typeof value === 'object' && !Array.isArray(value) && control.fields) {
      const g = replaceInSettings(value, control.fields, o, hits, label);
      if (g !== value) set(key, g);
    }
  }
  return out ?? settings;
}
