// Styling states in the inspector (`_states` = { state: settings }; CSS in statesCss(), src/shared/css.ts).
import { cleanState, stateIsSelfOnly, STATE_KEYS } from '@shared/css';
import type { ControlDef, Settings } from '@shared/types';

export { cleanState, STATE_KEYS };

export const STATE_LABEL: Record<string, string> = { normal: 'Normal', hover: 'Hover', focus: 'Focus', active: 'Pressed', before: '::before', after: '::after' };

export const stateLabel = (state: string): string => STATE_LABEL[state] ?? state;

/** Whether a control can take a value in `state` (it makes CSS; for pseudo-elements and elsewhere-selectors, on the element itself). */
export function stateable(control: ControlDef | undefined, state: string): boolean {
  if (!control || state === 'normal') return true;
  const self = stateIsSelfOnly(state);
  // Groups (typography, background, border…) have one `selector`; other controls a `selectors` map.
  if (control.selector) return !self || control.selector.trim() === '{{WRAPPER}}';
  if (!control.selectors) return false;
  return !self || Object.keys(control.selectors).some((s) => s.trim() === '{{WRAPPER}}');
}

/** The values stored for a state (empty object when none). */
export function stateValues(settings: Settings | undefined, state: string): Settings {
  const states = settings?._states;
  const v = states && typeof states === 'object' && !Array.isArray(states) ? (states as Record<string, Settings>)[state] : undefined;
  return v && typeof v === 'object' && !Array.isArray(v) ? v : {};
}

/** `_states` with one key of one state set (undefined removes it; empty states and an empty map go away). */
export function withStateValue(settings: Settings | undefined, state: string, key: string, value: unknown): Settings | undefined {
  const states: Record<string, Settings> = { ...((settings?._states as Record<string, Settings>) ?? {}) };
  const next = { ...(states[state] ?? {}) };
  if (value === undefined) delete next[key];
  else next[key] = value;
  if (Object.keys(next).length) states[state] = next;
  else delete states[state];
  return Object.keys(states).length ? states : undefined;
}

/** States this element already styles (for the state switch). */
export function usedStates(settings: Settings | undefined): string[] {
  const states = settings?._states;
  return states && typeof states === 'object' && !Array.isArray(states) ? Object.keys(states).filter((k) => Object.keys((states as Record<string, Settings>)[k] ?? {}).length) : [];
}
