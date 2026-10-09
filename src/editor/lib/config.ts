import type { CloudAccess } from '@shared/cloud';
import type { Breakpoint, DynamicTagSchema, ElementNode, ElementSchema, Kit, Settings } from '@shared/types';

export interface EditorConfig {
  /** Fonts uploaded under Uncoder → Design System → Custom fonts (Custom_Fonts::catalog()). */
  customFonts?: Record<string, { c: string; w: string[]; custom?: boolean }>;
  /** Icon sets uploaded under Design System → Custom icons (same shape as icons/index.json, plus url). */
  iconSets?: Array<{ id: string; title: string; group: string; count: number; viewBox: string; mode: 'fill' | 'stroke'; url: string }>;
  version: string;
  post: {
    id: number;
    title: string;
    status: string;
    type: string;
    typeLabel: string;
    docType: string;
    permalink: string;
    previewUrl: string;
    /** Nonce for opening the page with the current autosave (preview in a new tab, Draft_Preview). */
    draftNonce: string;
    exitUrl: string;
    modified: number;
    pageTemplate: string;
    rev: string;
  };
  kitVersion: string;
  elements: ElementNode[];
  pageSettings: Settings;
  schema: {
    elements: Record<string, ElementSchema>;
    categories: Record<string, string>;
    dynamicTags: Record<string, DynamicTagSchema>;
    tagGroups: Record<string, string>;
    templateTypes: Record<string, string>;
    /** Widgets turned off in Settings → Elements. */
    disabled?: string[];
  };
  /** The user's editor preferences (store/prefs.ts). */
  prefs?: { favorites?: string[]; autoPanels?: boolean; handles?: boolean; hints?: boolean };
  kit: Kit;
  breakpoints: Breakpoint[];
  rest: { root: string; wp: string; nonce: string };
  urls: { admin: string; assets: string; icons: string; fonts: string; fontshare?: string; site: string; mcp: string; styleBook?: string };
  styleBookNonce?: string;
  /** Settings → Tools → Safe mode is running for this browser (only Uncoder active, default theme). */
  safeMode?: boolean;
  /** Set when the site was handed over and this user edits content only (Site\Handoff::notice()). */
  handoff?: { by: string; contact: string } | null;
  /** The private cloud library (Site\Cloud_Library::client_config()): null when not offered to this user. */
  cloud?: CloudAccess;
  /** Paid plans launched (Licence::enabled()): premium sections in Insert → Sections. */
  licensing?: boolean;
  user: {
    id: number;
    name: string;
    avatar: string;
    caps: { unfiltered_html: boolean; publish: boolean; manage_options: boolean; upload_files: boolean; edit_theme: boolean };
    /** Role manager "Content only": texts, images and links of existing elements, nothing structural. */
    contentOnly?: boolean;
  };
  site: { name: string; lang: string };
}

declare global {
  interface Window {
    UncoderEditor: EditorConfig;
    wp?: any;
  }
}

export const config: EditorConfig = window.UncoderEditor;

export const schemas = config.schema.elements;

export function schemaOf(type: string): ElementSchema | undefined {
  return schemas[type];
}

export const isContainerType = (type: string) => !!schemas[type]?.container;
export const isNestedType = (type: string) => !!schemas[type]?.nested;

/** Role manager: this user may only edit the content of existing elements (enforced by the server too). */
export const contentOnly = (): boolean => !!config.user?.contentOnly;
