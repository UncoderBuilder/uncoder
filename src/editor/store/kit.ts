// Design System (global design system) state with live preview and save.
import { create } from 'zustand';
import type { Kit, Settings } from '@shared/types';
import { api } from '../lib/api';
import { config } from '../lib/config';

interface KitState {
  kit: Kit;
  dirty: boolean;
  saving: boolean;
}

export const useKit = create<KitState>(() => ({
  kit: config.kit,
  dirty: false,
  saving: false,
}));

export function updateKit(recipe: (kit: Kit) => Kit): void {
  const next = recipe(useKit.getState().kit);
  useKit.setState({ kit: next, dirty: true });
}

export function setKitSection(section: 'layout' | 'buttons' | 'forms' | 'theme' | 'settings', values: Settings): void {
  updateKit((k) => {
    const cur = { ...((k as any)[section] ?? {}) };
    for (const [key, v] of Object.entries(values)) {
      if (v === undefined) delete cur[key];
      else cur[key] = v;
    }
    return { ...k, [section]: cur };
  });
}

export async function saveKit(): Promise<void> {
  const { kit, dirty } = useKit.getState();
  if (!dirty) return;
  useKit.setState({ saving: true });
  try {
    const { breakpoints: _bp, ...payload } = kit as any;
    const res = await api<{ kit: Kit; warnings: string[] }>('kit', { body: { kit: payload, replace: true } });
    useKit.setState({ kit: res.kit, dirty: false });
  } finally {
    useKit.setState({ saving: false });
  }
}

export const colorVar = (id: string) => `var(--uncoder-c-${id})`;
export const globalColorId = (value: unknown): string | null => {
  if (typeof value !== 'string') return null;
  const m = value.match(/^var\(--uncoder-c-([a-z0-9_\-]+)\)$/);
  return m ? m[1] : null;
};
