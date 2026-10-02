import { conditionsMet, suffix } from '@shared/css';
import type { ControlDef, ElementSchema, Settings } from '@shared/types';
import { config } from './config';

const deviceIds = config.breakpoints.map((b) => b.id);

/** Devices whose values a device inherits, nearest first (desktop-first cascade). */
export function inheritanceChain(device: string): string[] {
  const idx = deviceIds.indexOf(device);
  if (idx < 0) return ['desktop'];
  const chain = deviceIds.slice(0, idx + 1).reverse();
  return device === 'widescreen' ? chain : chain.filter((d) => d !== 'widescreen');
}

export interface ReadResult {
  value: any;
  own: boolean;
  from: string | null;
}

export function readValue(settings: Settings, key: string, control: ControlDef, device: string): ReadResult {
  if (!control.responsive || device === 'desktop') {
    if (key in settings) return { value: settings[key], own: true, from: 'desktop' };
    return { value: control.default, own: false, from: null };
  }
  const chain = inheritanceChain(device);
  for (let i = 0; i < chain.length; i++) {
    const k = key + suffix(chain[i]);
    if (k in settings) return { value: settings[k], own: i === 0, from: chain[i] };
  }
  return { value: control.default, own: false, from: null };
}

export function writeKey(key: string, control: ControlDef, device: string): string {
  return control.responsive ? key + suffix(device) : key;
}

export function effectiveSettings(schema: ElementSchema | undefined, settings: Settings): Settings {
  if (!schema) return settings;
  const out: Settings = {};
  for (const [k, c] of Object.entries(schema.controls)) if (c.default !== undefined) out[k] = c.default;
  return Object.assign(out, settings);
}

export function visible(control: ControlDef, settings: Settings, controls: Record<string, ControlDef>): boolean {
  return conditionsMet(control, settings, controls);
}

/** Responsive keys that carry an explicit value for a control (for the "override" dots). */
export function overriddenDevices(settings: Settings, key: string): string[] {
  return deviceIds.filter((d) => d !== 'desktop' && key + suffix(d) in settings);
}

export const isEmptyValue = (v: any): boolean =>
  v === undefined ||
  v === null ||
  v === '' ||
  (Array.isArray(v) && v.length === 0) ||
  (typeof v === 'object' && !Array.isArray(v) && Object.keys(v).length === 0) ||
  (typeof v === 'object' && v !== null && 'size' in v && (v.size === '' || v.size === undefined));
