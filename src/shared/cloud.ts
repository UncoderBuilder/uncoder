// The private cloud library (Site\Cloud_Library, Agency licence): what the plugin's uncoder/v1/cloud routes return.

export type CloudKind = 'section' | 'page' | 'template' | 'kit';

export interface CloudItem {
  id: number;
  kind: CloudKind;
  title: string;
  size: number;
  /** Facts sent when saving: elements, type, counts of a kit, plugin version… */
  meta: Record<string, string | number | boolean>;
  /** The site it was saved from (host and path). */
  site: string;
  created: string;
  updated: string;
}

export interface CloudList {
  /** The licence covers the library (also when it ended: then read-only). */
  allowed: boolean;
  /** Saving, renaming and deleting are possible. */
  write: boolean;
  items: CloudItem[];
  usage: { items: number; bytes: number };
  limits: { items: number; bytes: number; kit: number; json: number } | null;
  pricing: string;
  licence: string;
}

/** What the admin and the editor configs carry (null: not offered to this user). */
export type CloudAccess = { read: boolean; write: boolean } | null;

export const CLOUD_KIND_LABEL: Record<CloudKind, string> = { section: 'Section', page: 'Page', template: 'Template', kit: 'Site kit' };
export const CLOUD_KIND_ICON: Record<CloudKind, string> = { section: 'rectangle-horizontal', page: 'file-text', template: 'layout-template', kit: 'package' };
