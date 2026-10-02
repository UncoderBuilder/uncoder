import { config } from './config';

export class ApiError extends Error {
  constructor(message: string, public status: number, public data?: any) {
    super(message);
  }
}

export async function api<T = any>(path: string, options: { method?: string; body?: any; signal?: AbortSignal; wp?: boolean } = {}): Promise<T> {
  const base = options.wp ? config.rest.wp : config.rest.root;
  const res = await fetch(base + path.replace(/^\//, ''), {
    method: options.method ?? (options.body ? 'POST' : 'GET'),
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': config.rest.nonce,
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
    },
    body: options.body ? JSON.stringify(options.body) : undefined,
    signal: options.signal,
  });
  let data: any = null;
  const text = await res.text();
  try {
    data = text ? JSON.parse(text) : null;
  } catch {
    data = text;
  }
  if (!res.ok) {
    // A PHP fatal error returns an HTML page: never show raw markup to the user.
    const message = (data && typeof data === 'object' && data.message) || (res.status >= 500 ? `Server error (${res.status}). Check the PHP error log.` : `Request failed (${res.status})`);
    throw new ApiError(message, res.status, data);
  }
  return data as T;
}
