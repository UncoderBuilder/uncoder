// The shared UI primitives (Icon, Button, Popover…) and the REST helper come from the editor and read
// `window.UncoderEditor` when their modules load. The admin provides the small subset they need, built
// from `window.UncoderAdmin`. This module must be imported before anything from '@editor/*'.
import type { EditorConfig } from '@editor/lib/config';

const a = window.UncoderAdmin;
if (a && !window.UncoderEditor) {
  window.UncoderEditor = {
    version: a.version,
    rest: { root: a.rest.root, wp: a.rest.wp, nonce: a.rest.nonce },
    urls: {
      admin: a.urls.admin,
      assets: a.urls.assets,
      icons: a.urls.assets + 'data/lucide.json',
      fonts: a.urls.assets + 'data/google-fonts.json',
      site: a.urls.site,
      mcp: a.urls.mcp,
    },
    schema: { elements: {}, categories: {}, dynamicTags: {}, tagGroups: {}, templateTypes: a.templateTypes },
    kit: a.kit,
    customFonts: a.customFonts,
    breakpoints: [],
    user: { id: a.user.id, name: a.user.name, avatar: '', caps: { unfiltered_html: a.user.caps.unfiltered_html, publish: true, manage_options: a.user.caps.manage_options, upload_files: false, edit_theme: a.user.caps.edit_theme_options } },
    site: { name: a.site.name, lang: document.documentElement.lang || 'en' },
  } as unknown as EditorConfig;
}

export {};
