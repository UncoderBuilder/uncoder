import { config } from './config';

export class ApiError extends Error {
  constructor(message: string, public status: number, public data?: any) {
    super(message);
  }
}

/*
 * Some hosts run a web application firewall (ModSecurity with the OWASP rule set, Imunify360…) that refuses requests
 * whose JSON holds ordinary page content, an inline style or a sentence that looks like SQL, before WordPress sees
 * them: the editor then showed "Request failed (406)". Such a request is sent again wrapped in base64
 * ({"uncoder_body": "…"}, header X-Uncoder-Wrapped), which Rest::unwrap_body() opens before WordPress routes it.
 * After one refusal every request body is wrapped, and the site is remembered in this browser.
 */
const WRAP_KEY = 'uncoder-wrap-body';
let wrapBodies = (() => {
  try {
    return localStorage.getItem(WRAP_KEY) === '1';
  } catch {
    return false;
  }
})();

const toBase64 = (text: string): string => {
  const bytes = new TextEncoder().encode(text);
  let bin = '';
  for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
  return btoa(bin);
};

/** WordPress answers REST errors as JSON with a code; a firewall answers with its own page. */
const isWpError = (data: any) => !!data && typeof data === 'object' && 'code' in data;
const refusedByHost = (res: Response, data: any) => [403, 406, 412, 415, 501].includes(res.status) && !isWpError(data);

async function readBody(res: Response): Promise<any> {
  const text = await res.text();
  try {
    return text ? JSON.parse(text) : null;
  } catch {
    return text;
  }
}

export async function api<T = any>(path: string, options: { method?: string; body?: any; signal?: AbortSignal; wp?: boolean } = {}): Promise<T> {
  const base = options.wp ? config.rest.wp : config.rest.root;
  const json = options.body ? JSON.stringify(options.body) : undefined;
  const send = (wrapped: boolean) =>
    fetch(base + path.replace(/^\//, ''), {
      method: options.method ?? (options.body ? 'POST' : 'GET'),
      credentials: 'same-origin',
      headers: {
        'X-WP-Nonce': config.rest.nonce,
        ...(json !== undefined ? { 'Content-Type': 'application/json' } : {}),
        ...(json !== undefined && wrapped ? { 'X-Uncoder-Wrapped': '1' } : {}),
      },
      body: json === undefined ? undefined : wrapped ? JSON.stringify({ uncoder_body: toBase64(json) }) : json,
      signal: options.signal,
    });
  const wrapped = json !== undefined && wrapBodies;
  let res = await send(wrapped);
  let data = await readBody(res);
  // Refused before WordPress ran, so nothing was applied: safe to send again, wrapped.
  if (json !== undefined && !wrapped && refusedByHost(res, data)) {
    res = await send(true);
    data = await readBody(res);
    if (res.ok || isWpError(data)) {
      wrapBodies = true;
      try {
        localStorage.setItem(WRAP_KEY, '1');
      } catch {
        /* private mode */
      }
    }
  }
  if (!res.ok) {
    // A PHP fatal error returns an HTML page: never show raw markup to the user.
    const message = (data && typeof data === 'object' && data.message) || (res.status >= 500 ? `Server error (${res.status}). Check the PHP error log.` : `Request failed (${res.status})`);
    throw new ApiError(message, res.status, data);
  }
  return data as T;
}
