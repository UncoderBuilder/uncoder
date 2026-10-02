import { api } from '@editor/lib/api';
import type { Kit } from '@shared/types';

export { api, ApiError } from '@editor/lib/api';

/* ------------------------------------------------------------------ Templates */

export interface Condition {
  type: 'include' | 'exclude';
  rule: string;
  post_type?: string;
  taxonomy?: string;
  ids?: number[];
  /** id → readable name (added by the server, ignored when saving). */
  labels?: Record<string, string>;
  /** Readable label of the whole condition (added by the server). */
  label?: string;
}

export interface PopupSettings {
  layout: 'modal' | 'slide_in' | 'bar' | 'fullscreen';
  position: string;
  width: { size: number | string; unit: string };
  overlay: boolean;
  overlay_color: string;
  background: string;
  radius: number;
  padding: number;
  close_button: boolean;
  close_on_overlay: boolean;
  close_on_esc: boolean;
  animation: string;
  triggers: {
    load: { enabled: boolean; delay: number };
    scroll: { enabled: boolean; percent: number };
    scroll_to: { enabled: boolean; selector: string };
    click: { enabled: boolean; selector: string };
    exit_intent: { enabled: boolean };
    inactivity: { enabled: boolean; seconds: number };
    page_views: { enabled: boolean; count: number };
  };
  frequency: { times: number; period: string };
  devices: string[];
  visitors: 'all' | 'logged_in' | 'logged_out';
  avoid_multiple: boolean;
  rules: {
    referrer: '' | 'search' | 'external' | 'internal' | 'direct' | 'contains';
    referrer_value: string;
    url_param: string;
    sessions: number;
    schedule: { enabled: boolean; from: string; until: string; timezone: 'site' | 'visitor' };
    browsers: string[];
  };
}

export interface Template {
  id: number;
  title: string;
  type: string;
  typeLabel: string;
  status: string;
  modified: string;
  author: string;
  editUrl: string;
  /** Live preview on the site (Theme\Template_Preview), drafts included. */
  previewUrl: string;
  canEdit: boolean;
  canDelete: boolean;
  elements: number;
  conditional: boolean;
  shortcode: string;
  conditions?: Condition[];
  summary?: string;
  active?: boolean;
  popup?: PopupSettings;
  openLink?: string;
  /** WPML / Polylang: the template's language and its other language versions. */
  languages?: {
    lang: string;
    translations: Array<{ code: string; name: string; flag: string; id?: number; status?: string; editUrl?: string; translateUrl?: string }>;
  };
}

export interface RuleDef {
  label: string;
  context: string;
  fields: Array<'post_type' | 'taxonomy' | 'ids'>;
}

export interface TemplatesMeta {
  types: Record<string, string>;
  conditional: string[];
  defaults: Record<string, Condition[]>;
  rules: Record<string, RuleDef>;
  postTypes: Array<{ name: string; label: string; singular: string; hierarchical: boolean; hasArchive: boolean }>;
  taxonomies: Array<{ name: string; label: string; singular: string; postTypes: string[] }>;
  popupDefaults: PopupSettings;
  canListUsers: boolean;
}

let metaPromise: Promise<TemplatesMeta> | null = null;
export function templatesMeta(): Promise<TemplatesMeta> {
  if (!metaPromise) metaPromise = api<TemplatesMeta>('templates/meta').catch((e) => {
    metaPromise = null;
    throw e;
  });
  return metaPromise;
}

export const templatesApi = {
  list: (types: string[], signal?: AbortSignal) => api<Template[]>(`templates?type=${encodeURIComponent(types.join(','))}`, { signal }),
  create: (body: { type: string; title: string; conditions?: Condition[]; popup?: Partial<PopupSettings> }) => api<Template>('templates', { body }),
  update: (id: number, body: { title?: string; status?: 'publish' | 'draft'; conditions?: Condition[]; popup?: Partial<PopupSettings> }) => api<Template>(`templates/${id}`, { body }),
  duplicate: (id: number) => api<Template>(`templates/${id}/duplicate`, { method: 'POST' }),
  trash: (id: number) => api<{ trashed: boolean }>(`templates/${id}`, { method: 'DELETE' }),
  restore: (id: number) => api<Template>(`templates/${id}/restore`, { method: 'POST' }),
};

/** Conditions as the server expects them (labels stripped). */
export const cleanConditions = (list: Condition[]): Condition[] =>
  list.map((c) => {
    const out: Condition = { type: c.type, rule: c.rule };
    if (c.post_type) out.post_type = c.post_type;
    if (c.taxonomy) out.taxonomy = c.taxonomy;
    if (c.ids && c.ids.length) out.ids = c.ids;
    return out;
  });

/* ------------------------------------------------------------------ Lookups */

export interface LookupItem {
  value: string;
  label: string;
  type?: string;
  url?: string;
}

export const lookup = (source: string, params: Record<string, string> = {}, signal?: AbortSignal) => {
  const q = new URLSearchParams(params).toString();
  return api<LookupItem[]>(`lookup/${source}${q ? '?' + q : ''}`, { signal });
};

/* ------------------------------------------------------------------ Overview */

export interface Overview {
  pages: number;
  recent: Array<{ id: number; title: string; type: string; typeLabel: string; status: string; modified: string; editUrl: string; viewUrl: string }>;
  templates: number;
  activeTemplates: number;
  activePopups: number;
  sections: number;
  live: { header: boolean; footer: boolean };
  unread: number | null;
  postTypes: string[];
}

/* ------------------------------------------------------------------ MCP */

export interface McpSettings {
  enabled: boolean;
  allowed_roles: string[];
  rate_limit: number;
  allow_registration: boolean;
  allowed_origins: string[];
  snapshots: boolean;
  confirm_destructive: boolean;
  /** Connection links (keys made to be pasted as one URL) are accepted. */
  links: boolean;
  image_search: boolean;
  access_ttl: number;
  refresh_ttl: number;
  log_days: number;
}

export interface McpStatus {
  endpoint: string;
  issuer: string;
  metadata: string;
  protocols: string[];
  tools: number;
  tool_names: string[];
  stats: { calls: number; errors: number; writes: number };
  settings: McpSettings;
  roles: Array<{ slug: string; name: string; allowed: boolean }>;
  scopes: Record<string, string>;
  https: boolean;
  app_passwords: boolean;
  abilities: boolean;
  bridge: string;
}

export interface ApiKey {
  id: number;
  name: string;
  /** A connection link (used as ?token= in the server URL) rather than a header key. */
  link?: boolean;
  hint: string;
  user: string;
  scopes: string[];
  created_at: string;
  expires_at: string | null;
  last_used: string | null;
  revoked: boolean;
  expired: boolean;
}

export interface Grant {
  client_id: string;
  client_name: string;
  user: string;
  user_id: number;
  scopes: string[];
  created_at: string;
  last_used: string | null;
}

export interface LogEntry {
  id: number;
  time: string;
  user: string;
  client: string;
  method: string;
  tool: string;
  status: string;
  duration: number;
  object_id: number;
  snapshot: string;
  summary: string | null;
}

export const mcpApi = {
  status: (signal?: AbortSignal) => api<McpStatus>('mcp-admin/status', { signal }),
  keys: (signal?: AbortSignal) => api<ApiKey[]>('mcp-admin/keys', { signal }),
  createKey: (body: { name: string; scopes: string[]; expires_days: number; link?: boolean }) => api<{ id: number; secret: string; url: string; note: string }>('mcp-admin/keys', { body }),
  revokeKey: (id: number) => api<{ revoked: boolean }>(`mcp-admin/keys/${id}`, { method: 'DELETE' }),
  grants: (signal?: AbortSignal) => api<Grant[]>('mcp-admin/grants', { signal }),
  revokeGrant: (client_id: string, user_id: number) => api<{ revoked: boolean }>('mcp-admin/grants/revoke', { body: { client_id, user_id } }),
  log: (params: { limit: number; offset: number; tool?: string; status?: string }, signal?: AbortSignal) => {
    const q = new URLSearchParams({ limit: String(params.limit), offset: String(params.offset) });
    if (params.tool) q.set('tool', params.tool);
    if (params.status) q.set('status', params.status);
    return api<LogEntry[]>(`mcp-admin/log?${q.toString()}`, { signal });
  },
  clearLog: () => api<{ cleared: boolean }>('mcp-admin/log', { method: 'DELETE' }),
  settings: (signal?: AbortSignal) => api<McpSettings>('mcp-admin/settings', { signal }),
  saveSettings: (body: Partial<McpSettings>) => api<McpSettings>('mcp-admin/settings', { body }),
  undo: (snapshot: string) => api<{ restored: number | string; undo_snapshot?: string }>('mcp-admin/undo', { body: { snapshot } }),
};

/* ------------------------------------------------------------------ Submissions */

export interface Submission {
  id: number;
  postId: number;
  postTitle: string;
  postUrl: string;
  elementId: string;
  form: string;
  data: Record<string, unknown> | unknown[];
  meta: Record<string, unknown>;
  status: 'unread' | 'read' | 'spam';
  createdAt: string;
}

export interface SubmissionList {
  items: Submission[];
  total: number;
  page: number;
  pages: number;
  counts: { all: number; unread: number; read: number; spam: number };
  forms: Array<{ form: string; postId: number; postTitle: string; count: number }>;
  canEdit: boolean;
}

/* ------------------------------------------------------------------ Settings */

export interface PluginSettings {
  postTypes: string[];
  availablePostTypes: Array<{ name: string; label: string }>;
  fontDelivery: string;
  fontDeliveryOptions: Array<{ value: string; label: string }>;
  applyKit: boolean;
  removeData: boolean;
  /** Days to keep form submissions (0 = until deleted by hand). */
  formRetentionDays: number;
  cssWritable: boolean;
  maintenance: MaintenanceSettings;
  /** Role manager: restricted roles only ("content" | "none"); missing roles have full access. */
  roleAccess: Record<string, 'content' | 'none'>;
  captcha: { provider: '' | 'turnstile' | 'hcaptcha' | 'recaptcha' | 'recaptcha_v2'; site_key: string; has_secret: boolean; min_score?: number };
  integrations?: Record<'mailchimp' | 'mailerlite' | 'brevo' | 'activecampaign', { connected: boolean; url?: string }>;
  disabledWidgets?: string[];
  aiImages?: { enabled: boolean; model: string; endpoint: string; has_key: boolean };
  widgets?: Array<{ name: string; title: string; category: string; icon: string }>;
  widgetCategories?: Record<string, string>;
  consent: ConsentSettings;
  performance: { lcp: boolean; inline_css: boolean; lazy_bg: boolean };
  business: BusinessSettings;
  businessTypes: Record<string, string>;
  seoPlugin: string;
  ai: { enabled: boolean; model: string; has_key: boolean };
  aiModels: Record<string, string>;
  maintenancePage: { id: number; title: string; status: string; edit: string } | null;
  roles: Array<{ value: string; label: string; /** Can edit posts (relevant to the role manager). */ edits: boolean }>;
}

export interface MaintenanceSettings {
  mode: '' | 'coming_soon' | 'maintenance';
  page: number;
  access: 'logged_in' | 'roles';
  roles: string[];
}

export type { Kit };

export interface ConsentSettings {
  enabled: boolean;
  position: 'bar' | 'box-left' | 'box-right';
  title: string;
  message: string;
  accept_label: string;
  reject_label: string;
  prefs_label: string;
  privacy_url: string;
  consent_mode: boolean;
  reopen: boolean;
  version: number;
  renew?: boolean;
}

export interface BusinessSettings {
  enabled: boolean;
  type: string;
  name: string;
  description: string;
  phone: string;
  email: string;
  street: string;
  city: string;
  region: string;
  postal: string;
  country: string;
  area: string;
  price_range: string;
  hours: string[];
  same_as: string[];
}
