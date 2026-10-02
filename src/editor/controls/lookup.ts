import { useEffect, useState } from 'react';
import { api } from '../lib/api';

export interface LookupItem {
  value: string;
  label: string;
}

const cache = new Map<string, LookupItem[]>();
const REFRESH = 'uncoder-ui:lookup-refresh';

/** Forgets cached options of `source` and reloads open lists (e.g. after saving a new template). */
export function refreshLookup(source: string): void {
  for (const key of [...cache.keys()]) if (key.startsWith(source + '|')) cache.delete(key);
  window.dispatchEvent(new CustomEvent(REFRESH, { detail: source }));
}

/** Options from /lookup/{source} (menus, post types, templates…). */
export function useLookup(source: string | null, search = '', extra = ''): LookupItem[] | null {
  const key = source ? `${source}|${search}|${extra}` : '';
  const [items, setItems] = useState<LookupItem[] | null>(source ? cache.get(key) ?? null : null);
  const [version, setVersion] = useState(0);
  useEffect(() => {
    if (!source) return;
    const onRefresh = (e: Event) => (e as CustomEvent<string>).detail === source && setVersion((v) => v + 1);
    window.addEventListener(REFRESH, onRefresh);
    return () => window.removeEventListener(REFRESH, onRefresh);
  }, [source]);
  useEffect(() => {
    if (!source) return;
    if (cache.has(key)) {
      setItems(cache.get(key)!);
      return;
    }
    const ctrl = new AbortController();
    const t = setTimeout(() => {
      api<LookupItem[]>(`lookup/${source}?search=${encodeURIComponent(search)}${extra}`, { signal: ctrl.signal })
        .then((res) => {
          cache.set(key, res);
          setItems(res);
        })
        .catch(() => {});
    }, search ? 200 : 0);
    return () => {
      clearTimeout(t);
      ctrl.abort();
    };
  }, [key, source, search, extra, version]);
  return items;
}

export async function lookupLabels(source: string, ids: string[]): Promise<LookupItem[]> {
  if (!ids.length) return [];
  return api<LookupItem[]>(`lookup/${source}?ids=${encodeURIComponent(ids.join(','))}`);
}
