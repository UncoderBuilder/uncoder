import { create } from 'zustand';
import { config } from '../lib/config';
import { api } from '../lib/api';

/**
 * Editor preferences of the signed-in user (user meta via /me/prefs, delivered in the editor config), so they
 * follow the user to any browser. Theme and panel layout stay per browser (store/ui.ts).
 */
export interface Prefs {
  /** Widgets pinned to the top of Insert → Elements. */
  favorites: string[];
  /** The build panel switches with the selection (Layers for an element, Insert for an empty container). */
  autoPanels: boolean;
  /** Drag handles for padding, margin and gap on the selected element. */
  handles: boolean;
  /** Tooltips on toolbar buttons and swatches. */
  hints: boolean;
}

const DEFAULTS: Prefs = { favorites: [], autoPanels: true, handles: true, hints: true };

const initial = (): Prefs => {
  const p = config.prefs ?? {};
  return {
    favorites: Array.isArray(p.favorites) ? p.favorites.filter((n) => typeof n === 'string') : [],
    autoPanels: p.autoPanels ?? DEFAULTS.autoPanels,
    handles: p.handles ?? DEFAULTS.handles,
    hints: p.hints ?? DEFAULTS.hints,
  };
};

export const usePrefs = create<Prefs>(initial);

let timer = 0;
/** Changes a preference; saved to the server shortly after (one request for quick successive changes). */
export function setPref<K extends keyof Prefs>(key: K, value: Prefs[K]): void {
  usePrefs.setState({ [key]: value } as Pick<Prefs, K>);
  window.clearTimeout(timer);
  timer = window.setTimeout(() => {
    api('me/prefs', { body: usePrefs.getState() }).catch(() => {
      /* offline: kept for this session */
    });
  }, 600);
}

export function toggleFavorite(name: string): void {
  const list = usePrefs.getState().favorites;
  setPref('favorites', list.includes(name) ? list.filter((n) => n !== name) : [...list, name]);
}

/** Turned off in Settings → Elements (not offered in Insert; existing uses keep working). */
export const disabledWidgets = new Set<string>(config.schema.disabled ?? []);
