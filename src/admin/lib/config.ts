import type { CloudAccess } from '@shared/cloud';
import type { Kit } from '@shared/types';

export interface AdminConfig {
  page: string;
  version: string;
  /** Licensing is switched on (Licence::enabled()): Settings shows the Licence section. */
  licensing?: boolean;
  /** The paid plans are on (Licence::enabled()): starter sites come from the library screen, not the built-in dialog. */
  library?: boolean;
  /** The licence covers branded page reports (Site\Page_Checks::allowed()) and this user manages it. */
  reports?: boolean;
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
  /** Set when the site was handed over and this user edits content only (Site\Handoff::notice()). */
  handoff?: { by: string; contact: string } | null;
  /** The private cloud library (Site\Cloud_Library::client_config()): null when not offered to this user. */
  cloud?: CloudAccess;
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
