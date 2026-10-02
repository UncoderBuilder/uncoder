// Icon libraries beyond Lucide (built by tools/build-icons.mjs). Each set is a JSON file fetched the first time
// it is needed — the picker tab or an icon already used on the page — and cached for the session.
import { useEffect, useState } from 'react';
import { config } from './config';
import { iconNames, loadIconTags } from './icons';

export interface IconLibrary {
  id: string;
  title: string;
  group: string;
  count: number;
  viewBox: string;
  mode: 'fill' | 'stroke';
  sw?: number;
}
interface IconSet {
  viewBox: string;
  mode: 'fill' | 'stroke';
  sw?: number;
  icons: Record<string, string | [string, string]>;
  tags?: Record<string, string[]>;
}

/** URL of a file next to lucide.json (keeps the ?ver= cache buster). */
const dataUrl = (file: string) => config.urls.icons.replace(/lucide\.json/, file);

let indexPromise: Promise<IconLibrary[]> | null = null;
const sets = new Map<string, IconSet>();
const pending = new Map<string, Promise<IconSet | null>>();

export function loadLibraries(): Promise<IconLibrary[]> {
  indexPromise ??= fetch(dataUrl('icons/index.json'), { credentials: 'same-origin' })
    .then((r) => (r.ok ? r.json() : []))
    .catch(() => [])
    .then((list: IconLibrary[]) => [...list, ...(config.iconSets ?? [])]);
  return indexPromise;
}

/** Where a set's JSON lives: the plugin's data folder, or uploads for a custom set. */
const setUrl = (id: string) => config.iconSets?.find((s) => s.id === id)?.url ?? dataUrl(`icons/${id}.json`);

export function loadSet(id: string): Promise<IconSet | null> {
  if (sets.has(id)) return Promise.resolve(sets.get(id)!);
  if (!pending.has(id)) {
    pending.set(
      id,
      fetch(setUrl(id), { credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : null))
        .then((s: IconSet | null) => {
          if (s) sets.set(id, s);
          return s;
        })
        .catch(() => null),
    );
  }
  return pending.get(id)!;
}

/** Names in a loaded library (Lucide included), with its search terms. */
export async function namesOf(library: string): Promise<{ names: string[]; tags: Record<string, string[]> }> {
  if (library === 'lucide') return { names: iconNames(), tags: await loadIconTags() };
  const set = await loadSet(library);
  return { names: set ? Object.keys(set.icons) : [], tags: set?.tags ?? {} };
}

/** Inline SVG for an icon of a loaded library, or null if the set is not loaded (yet) or the icon is unknown. */
export function setIconSvg(library: string, name: string, size = 18): string | null {
  const set = sets.get(library);
  const entry = set?.icons[name];
  if (!set || !entry) return null;
  const [body, viewBox] = Array.isArray(entry) ? entry : [entry, set.viewBox];
  const paint = set.mode === 'stroke' ? `fill="none" stroke="currentColor" stroke-width="${set.sw ?? 2}" stroke-linecap="round" stroke-linejoin="round"` : 'fill="currentColor"';
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="${viewBox}" ${paint} aria-hidden="true">${body}</svg>`;
}

/** Loads a library on demand and re-renders once it arrives. */
export function useIconSet(library: string | undefined): boolean {
  const [, tick] = useState(0);
  const ready = !library || library === 'lucide' || library === 'svg' || library === 'none' || sets.has(library);
  useEffect(() => {
    if (!ready && library) loadSet(library).then(() => tick((n) => n + 1));
  }, [library, ready]);
  return ready;
}
