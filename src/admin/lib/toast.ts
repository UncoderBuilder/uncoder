import { create } from 'zustand';

export interface ToastItem {
  id: number;
  kind: 'info' | 'success' | 'error' | 'warning';
  message: string;
  action?: { label: string; run: () => void };
}

export const useToasts = create<{ toasts: ToastItem[] }>(() => ({ toasts: [] }));

let seq = 0;
const timers = new Map<number, number>();

export function dismissToast(id: number) {
  window.clearTimeout(timers.get(id));
  timers.delete(id);
  useToasts.setState((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) }));
}

export function toast(message: string, kind: ToastItem['kind'] = 'success', action?: ToastItem['action'], timeout?: number): number {
  const id = ++seq;
  useToasts.setState((s) => ({ toasts: [...s.toasts.slice(-3), { id, kind, message, action }] }));
  const ms = timeout ?? (kind === 'error' ? 7000 : action ? 6500 : 3800);
  timers.set(id, window.setTimeout(() => dismissToast(id), ms));
  return id;
}

export function toastError(e: unknown, fallback = 'Something went wrong.') {
  const code = (e as { data?: { code?: string } } | null)?.data?.code;
  if (code === 'rest_cookie_invalid_nonce') {
    // The login session changed (e.g. signed in again in another tab): the page's nonce is stale.
    toast('Your session changed. Reload the page and try again.', 'error', { label: 'Reload', run: () => window.location.reload() }, 12000);
    return;
  }
  toast(e instanceof Error && e.message ? e.message : fallback, 'error');
}
