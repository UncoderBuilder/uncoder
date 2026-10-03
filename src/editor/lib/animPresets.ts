// Editor-side metadata for timeline animations: the preset library (assets/data/animations.json, shared
// with PHP), property ranges and labels, trigger / target wording and a one-line summary of a definition.
import DATA from '../../../assets/data/animations.json';
import type { AnimClip, AnimDef, AnimProp, AnimTarget, AnimTrigger } from '@shared/anim';

export interface AnimPreset {
  id: string;
  label: string;
  group: string;
  def: AnimDef;
}

export const PRESET_GROUPS: Array<{ id: string; label: string }> = Object.entries(DATA.groups as Record<string, string>).map(([id, label]) => ({ id, label }));

export const PRESETS: AnimPreset[] = Object.entries(DATA.presets as unknown as Record<string, Omit<AnimPreset, 'id'>>).map(([id, p]) => ({ id, ...p }));

export const presetById = (id: string | undefined) => PRESETS.find((p) => p.id === id);

/** A fresh copy of a preset's definition, tagged with its id. */
export const fromPreset = (p: AnimPreset): AnimDef => ({ preset: p.id, ...structuredClone(p.def) });

/** Ranges match Core\Animations::PROPS. */
export const PROP_META: Record<Exclude<AnimProp, 'clip'>, { label: string; unit?: string; min: number; max: number; step: number; nudge: number }> = {
  opacity: { label: 'Opacity', min: 0, max: 1, step: 0.05, nudge: 0 },
  x: { label: 'Move X', unit: 'px', min: -3000, max: 3000, step: 1, nudge: 40 },
  y: { label: 'Move Y', unit: 'px', min: -3000, max: 3000, step: 1, nudge: 40 },
  scale: { label: 'Scale', min: 0, max: 100, step: 0.05, nudge: 0.8 },
  scaleX: { label: 'Scale X', min: -10, max: 10, step: 0.05, nudge: 0.8 },
  scaleY: { label: 'Scale Y', min: -10, max: 10, step: 0.05, nudge: 0.8 },
  rotate: { label: 'Rotate', unit: '°', min: -3600, max: 3600, step: 1, nudge: 15 },
  rotateX: { label: 'Rotate X (3D)', unit: '°', min: -3600, max: 3600, step: 1, nudge: 45 },
  rotateY: { label: 'Rotate Y (3D)', unit: '°', min: -3600, max: 3600, step: 1, nudge: 45 },
  skewX: { label: 'Skew X', unit: '°', min: -80, max: 80, step: 1, nudge: 10 },
  skewY: { label: 'Skew Y', unit: '°', min: -80, max: 80, step: 1, nudge: 10 },
  blur: { label: 'Blur', unit: 'px', min: 0, max: 100, step: 1, nudge: 10 },
};

export const PROP_ORDER: AnimProp[] = ['opacity', 'x', 'y', 'scale', 'scaleX', 'scaleY', 'rotate', 'rotateX', 'rotateY', 'skewX', 'skewY', 'blur', 'clip'];

export const CLIP_LABELS: Record<AnimClip, string> = {
  none: 'Fully shown',
  'wipe-up': 'Hidden, reveals upward',
  'wipe-down': 'Hidden, reveals downward',
  'wipe-left': 'Hidden, reveals to the left',
  'wipe-right': 'Hidden, reveals to the right',
  'center-x': 'Hidden, opens from the middle',
  'center-y': 'Hidden, opens from the horizon',
  circle: 'Hidden, circle from the center',
};

export const TRIGGERS: Array<{ value: AnimTrigger; label: string; icon: string; hint: string }> = [
  { value: 'enter', label: 'Scroll into view', icon: 'scan-eye', hint: 'Plays when the element comes into view.' },
  { value: 'load', label: 'Page load', icon: 'zap', hint: 'Plays as soon as the page opens.' },
  { value: 'scroll', label: 'Scroll scrub', icon: 'arrow-down-up', hint: 'Follows the scrollbar while the element crosses the screen.' },
  { value: 'hover', label: 'Hover', icon: 'mouse-pointer-2', hint: 'Plays on hover or keyboard focus, and back when the pointer leaves.' },
  { value: 'click', label: 'Click', icon: 'mouse-pointer-click', hint: 'Plays on click.' },
  { value: 'loop', label: 'Loop', icon: 'repeat', hint: 'Plays forever (resting while off screen).' },
];

export const TARGET_LABELS: Record<AnimTarget, string> = {
  self: 'The element',
  children: 'Child items, one by one',
  words: 'Words, one by one',
  chars: 'Letters, one by one',
  lines: 'Lines, one by one',
};

export const SPLIT_TARGETS: AnimTarget[] = ['words', 'chars', 'lines'];

const seconds = (ms: number) => `${Math.round(ms / 100) / 10}s`;

/** "Scroll into view · 0.9s · 60ms stagger" */
export function describe(def: AnimDef): string {
  const trigger = TRIGGERS.find((t) => t.value === def.trigger)?.label ?? def.trigger;
  const length = def.steps.reduce((t, s) => t + (s.delay ?? 0) + (s.duration ?? 0), 0);
  const bits = [trigger];
  if (def.trigger === 'scroll') bits.push(`${def.start ?? 0}–${def.end ?? 100}%`);
  else bits.push(seconds(length));
  if (def.target && def.target !== 'self') bits.push(def.target === 'chars' ? 'letters' : def.target);
  if (def.delay && def.trigger !== 'scroll') bits.push(`+${seconds(def.delay)}`);
  return bits.join(' · ');
}

export function titleOf(def: AnimDef): string {
  return presetById(def.preset)?.label ?? 'Custom animation';
}
