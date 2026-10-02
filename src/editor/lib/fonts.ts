// Google Fonts catalog for pickers + on-demand <link> injection in the canvas.
import { registerFontCategories } from '@shared/css';
import { googleFamilyParam, sortWeights } from '@shared/fonts-url';
import { config } from './config';

export interface FontInfo {
  family: string;
  category: string;
  weights: string[];
  /** Uploaded under Custom Fonts: served by the site's own @font-face rules, never from Google. */
  custom?: boolean;
}

let catalog: Record<string, { c: string; w: string[]; o?: [number, number]; i?: number; custom?: boolean }> | null = null;
let loading: Promise<void> | null = null;
let canvasDoc: Document | null = null;
const loaded = new Set<string>();

async function load(): Promise<void> {
  if (catalog) return;
  if (!loading) {
    loading = fetch(config.urls.fonts, { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((data) => {
        // Custom fonts first: they win over a Google family with the same name.
        catalog = { ...(config.customFonts ?? {}), ...data, ...(config.customFonts ?? {}) };
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
  },
  /** Loads the given families (with weights) into the canvas document. */
  ensure(used: Map<string, Set<string>>) {
    if (!canvasDoc || !catalog || config.kit.settings?.font_delivery === 'none') return;
    const missing: string[] = [];
    for (const [family, weights] of used) {
      const info = catalog[family];
      if (!info || info.custom) continue;
      const w = sortWeights([...new Set([...weights, '400'])].filter((x) => info.w.includes(x)));
      const key = family + ':' + w.join(',');
      if (loaded.has(key)) continue;
      loaded.add(key);
      const list = w.length ? w : [info.w[0]];
      // Twin of Fonts::family_param(): optical-size axis and italics when the family has them.
      missing.push(googleFamilyParam(family, list, info));
    }
    if (!missing.length) return;
    const link = canvasDoc.createElement('link');
    link.rel = 'stylesheet';
    link.href = `https://fonts.googleapis.com/css2?${missing.join('&')}&display=swap`;
    canvasDoc.head.appendChild(link);
  },
};
