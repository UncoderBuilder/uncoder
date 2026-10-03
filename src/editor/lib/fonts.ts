// Google Fonts + Fontshare catalog for pickers + on-demand <link> injection in the canvas.
import { registerFontCategories } from '@shared/css';
import { fontshareFamilyParam, googleFamilyParam, sortWeights } from '@shared/fonts-url';
import { config } from './config';
import { useKit } from '../store/kit';

export interface FontInfo {
  family: string;
  category: string;
  weights: string[];
  /** Uploaded under Custom Fonts: served by the site's own @font-face rules, never from Google. */
  custom?: boolean;
}

let catalog: Record<string, { c: string; w: string[]; o?: [number, number]; i?: number; s?: string; custom?: boolean }> | null = null;
let loading: Promise<void> | null = null;
let canvasDoc: Document | null = null;
const loaded = new Set<string>();
const links: HTMLLinkElement[] = [];
/** Whether the canvas fonts were loaded with their optical-size axis (Design System › Theme › Optical sizing). */
let opticalMode = true;

async function load(): Promise<void> {
  if (catalog) return;
  if (!loading) {
    const fontshare = config.urls.fontshare ? fetch(config.urls.fontshare, { credentials: 'same-origin' }).then((r) => r.json()).catch(() => ({})) : Promise.resolve({});
    loading = Promise.all([fetch(config.urls.fonts, { credentials: 'same-origin' }).then((r) => r.json()), fontshare])
      .then(([data, extra]) => {
        // Custom fonts win over a Google or Fontshare family with the same name; Google wins over Fontshare.
        catalog = { ...extra, ...data, ...(config.customFonts ?? {}) };
        registerFontCategories(catalog!);
      })
      .catch(() => {
        catalog = { ...(config.customFonts ?? {}) };
        registerFontCategories(catalog);
      });
  }
  return loading;
}

export const fonts = {
  load,
  list(): FontInfo[] {
    if (!catalog) return [];
    return Object.entries(catalog).map(([family, d]) => ({ family, category: d.c, weights: d.w, custom: !!d.custom }));
  },
  info(family: string): FontInfo | null {
    const d = catalog?.[family];
    return d ? { family, category: d.c, weights: d.w, custom: !!d.custom } : null;
  },
  attach(doc: Document) {
    canvasDoc = doc;
    loaded.clear();
    links.length = 0;
  },
  /** Loads the given families (with weights) into the canvas document. */
  ensure(used: Map<string, Set<string>>) {
    if (!canvasDoc || !catalog || config.kit.settings?.font_delivery === 'none') return;
    const theme = (useKit.getState().kit.theme ?? {}) as { enabled?: boolean; optical_sizing?: string };
    const optical = !(theme.enabled && theme.optical_sizing === 'none');
    if (optical !== opticalMode) {
      // The other cut's stylesheets would keep winning over the new ones (same family names), so they go.
      for (const link of links.splice(0)) link.remove();
      loaded.clear();
      opticalMode = optical;
    }
    const missing: string[] = [];
    const fontshare: string[] = [];
    for (const [family, weights] of used) {
      const info = catalog[family];
      if (!info || info.custom) continue;
      const w = sortWeights([...new Set([...weights, '400'])].filter((x) => info.w.includes(x)));
      const key = family + ':' + w.join(',');
      if (loaded.has(key)) continue;
      loaded.add(key);
      const list = w.length ? w : [info.w[0]];
      // Twins of Fonts::fontshare_param() / Fonts::family_param(): italics (and Google's optical-size axis).
      if (info.s) fontshare.push(fontshareFamilyParam(info.s, list, !!info.i));
      else missing.push(googleFamilyParam(family, list, info, optical));
    }
    const add = (href: string) => {
      const link = canvasDoc!.createElement('link');
      link.rel = 'stylesheet';
      link.href = href;
      canvasDoc!.head.appendChild(link);
      links.push(link);
    };
    if (missing.length) add(`https://fonts.googleapis.com/css2?${missing.join('&')}&display=swap`);
    if (fontshare.length) add(`https://api.fontshare.com/v2/css?${fontshare.join('&')}&display=swap`);
  },
};
