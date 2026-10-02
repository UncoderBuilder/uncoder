import type { Kit } from '@shared/types';

export interface AdminConfig {
  page: string;
  version: string;
  rest: { root: string; wp: string; nonce: string };
  urls: {
    admin: string;
    site: string;
    editor: string;
    newPage: string;
    mcp: string;
    assets: string;
    pages: string;
    profile: string;
  };
  pages: Record<string, string>;
  user: {
    id: number;
    name: string;
    caps: {
      manage_options: boolean;
      edit_theme_options: boolean;
      edit_pages: boolean;
      unfiltered_html: boolean;
      use_mcp: boolean;
    };
  };
  templateTypes: Record<string, string>;
  site: { name: string; theme: string; block: boolean };
  kit: Kit;
  /** The user's preferences that the admin reads (user meta, Prefs_Controller). */
  prefs?: { newsSeen?: string };
  customFonts: Record<string, { c: string; w: string[]; custom?: boolean }>;
}

declare global {
  interface Window {
    UncoderAdmin: AdminConfig;
  }
}

export const cfg: AdminConfig = window.UncoderAdmin;

export const can = (cap: keyof AdminConfig['user']['caps']) => !!cfg.user.caps[cap];

/** Editor URL for a post id. */
export const editorUrl = (id: number) => cfg.urls.editor + id;

/** URL of another admin screen of the app. */
export const screenUrl = (page: string, hash = '') => `${cfg.urls.admin}admin.php?page=${page}${hash ? '#' + hash : ''}`;
