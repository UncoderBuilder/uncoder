// The elements the inspector edits: the selection (several when more than one is selected).
import { createContext, useContext } from 'react';
import type { ElementSchema } from '@shared/types';
import { schemaOf } from '../lib/config';

export const InspectorTargets = createContext<string[]>([]);
export const useInspectorTargets = () => useContext(InspectorTargets);

/**
 * The controls several element types have in common (same key, same control type): spacing, background,
 * border, motion… Sections keep their order and drop what is not shared; native state tabs are left out.
 */
export function sharedSchema(types: string[]): ElementSchema | undefined {
  const list = types.map((t) => schemaOf(t));
  if (list.some((s) => !s)) return undefined;
  const [first, ...rest] = list as ElementSchema[];
  const keys = Object.keys(first.controls).filter((k) => rest.every((s) => s.controls[k]?.type === first.controls[k].type));
  const controls = Object.fromEntries(keys.map((k) => [k, first.controls[k]]));
  const sections = first.sections
    .map((sec) => ({ ...sec, controls: sec.controls.filter((k) => !k.startsWith('@tabs:') && keys.includes(k)), tab_controls: undefined }))
    .filter((sec) => sec.controls.length);
  return { ...first, name: '__shared', title: `${types.length} element types`, description: '', sections, controls, ui_tabs: {} };
}
