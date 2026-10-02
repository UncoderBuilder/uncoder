import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from './toast';

export interface Resource<T> {
  data: T | undefined;
  error: Error | null;
  loading: boolean;
  reload: () => Promise<void>;
  setData: (fn: T | undefined | ((prev: T | undefined) => T | undefined)) => void;
}

/** Loads data once (and again when deps change). Keeps the previous data while reloading, so lists never flash. */
export function useResource<T>(loader: (signal: AbortSignal) => Promise<T>, deps: unknown[] = []): Resource<T> {
  const [data, setDataState] = useState<T | undefined>(undefined);
  const [error, setError] = useState<Error | null>(null);
  const [loading, setLoading] = useState(true);
  const ctrl = useRef<AbortController | null>(null);
  const loaderRef = useRef(loader);
  loaderRef.current = loader;

  const reload = useCallback(async () => {
    ctrl.current?.abort();
    const c = new AbortController();
    ctrl.current = c;
    setLoading(true);
    try {
      const result = await loaderRef.current(c.signal);
      if (c.signal.aborted) return;
      setDataState(result);
      setError(null);
    } catch (e) {
      if (c.signal.aborted || (e as Error)?.name === 'AbortError') return;
      setError(e instanceof Error ? e : new Error(String(e)));
    } finally {
      if (!c.signal.aborted) setLoading(false);
    }
  }, []);

  useEffect(() => {
    reload();
    return () => ctrl.current?.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);

  const setData = useCallback((fn: T | undefined | ((prev: T | undefined) => T | undefined)) => {
    setDataState((prev) => (typeof fn === 'function' ? (fn as (p: T | undefined) => T | undefined)(prev) : fn));
  }, []);

  return { data, error, loading, reload, setData };
}

export function useDebounced<T>(value: T, ms = 250): T {
  const [v, setV] = useState(value);
  useEffect(() => {
    const t = window.setTimeout(() => setV(value), ms);
    return () => window.clearTimeout(t);
  }, [value, ms]);
  return v;
}

/** A tab id mirrored in location.hash (so reloads and links keep the tab). */
export function useHashState<T extends string>(allowed: readonly T[], fallback: T): [T, (v: T) => void] {
  const read = () => {
    const h = decodeURIComponent(window.location.hash.replace(/^#/, '')) as T;
    return allowed.includes(h) ? h : fallback;
  };
  const [value, setValue] = useState<T>(read);
  useEffect(() => {
    const on = () => setValue(read());
    window.addEventListener('hashchange', on);
    return () => window.removeEventListener('hashchange', on);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  const set = useCallback(
    (v: T) => {
      setValue(v);
      const url = new URL(window.location.href);
      url.hash = v === fallback ? '' : v;
      window.history.replaceState(null, '', url.toString());
    },
    [fallback],
  );
  return [value, set];
}

/** Copies text and confirms with a toast; returns whether it worked. */
export async function copyText(text: string, what = 'Copied to clipboard'): Promise<boolean> {
  try {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
    } else {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      const ok = document.execCommand('copy');
      ta.remove();
      if (!ok) throw new Error('copy failed');
    }
    toast(what, 'success', undefined, 2200);
    return true;
  } catch {
    toast('Could not copy. Select the text and copy it manually.', 'error');
    return false;
  }
}

/** Warns before leaving the page with unsaved changes. */
export function useUnsavedGuard(dirty: boolean) {
  useEffect(() => {
    if (!dirty) return;
    const on = (e: BeforeUnloadEvent) => {
      e.preventDefault();
      e.returnValue = '';
    };
    window.addEventListener('beforeunload', on);
    return () => window.removeEventListener('beforeunload', on);
  }, [dirty]);
}
