// Reads a font file's own names and weight in the browser, so an upload of a whole family fills itself in.
// TTF/OTF are plain sfnt tables; WOFF tables are zlib-compressed (DecompressionStream). WOFF2 is Brotli +
// transformed tables, so for it (and anything unreadable) we fall back to the file name.

export interface FontInfo {
  family: string;
  weight: string; // "400", or "100 900" for a variable font
  style: 'normal' | 'italic';
  format: 'woff2' | 'woff' | 'ttf' | 'otf' | '';
  fromFile: boolean; // true when read from the font's tables, false when guessed from the name
}

const WEIGHTS: Array<[RegExp, string]> = [
  [/thin|hairline/, '100'],
  [/extra ?light|ultra ?light/, '200'],
  [/light/, '300'],
  [/medium/, '500'],
  [/semi ?bold|demi ?bold/, '600'],
  [/extra ?bold|ultra ?bold/, '800'],
  [/black|heavy/, '900'],
  [/bold/, '700'],
];

/** "Brand-SemiBoldItalic.woff2" → family "Brand", weight 600, italic. */
export function guessFromName(name: string): Omit<FontInfo, 'format' | 'fromFile'> {
  const base = name.replace(/\.(woff2?|ttf|otf)$/i, '');
  const n = base.toLowerCase();
  const variable = /variable|\bvf\b|\[wght/.test(n);
  const family = base
    .replace(/\[.*\]/g, '')
    .replace(/[-_ ]?(variable|vf|thin|hairline|extra ?light|ultra ?light|light|regular|normal|book|roman|medium|semi ?bold|demi ?bold|extra ?bold|ultra ?bold|bold|black|heavy|italic|oblique)+$/gi, '')
    .replace(/([a-z])([A-Z])/g, '$1 $2')
    .replace(/[-_]+/g, ' ')
    .trim();
  return {
    family: family || base,
    weight: variable ? '100 900' : (WEIGHTS.find(([re]) => re.test(n))?.[1] ?? '400'),
    style: /italic|oblique/.test(n) ? 'italic' : 'normal',
  };
}

function formatOf(view: DataView): FontInfo['format'] {
  const tag = String.fromCharCode(view.getUint8(0), view.getUint8(1), view.getUint8(2), view.getUint8(3));
  if (tag === 'wOF2') return 'woff2';
  if (tag === 'wOFF') return 'woff';
  if (tag === 'OTTO') return 'otf';
  if (view.getUint32(0) === 0x00010000 || tag === 'true') return 'ttf';
  return '';
}

async function inflate(bytes: Uint8Array): Promise<Uint8Array> {
  const stream = new Blob([bytes.slice().buffer as ArrayBuffer]).stream().pipeThrough(new DecompressionStream('deflate'));
  return new Uint8Array(await new Response(stream).arrayBuffer());
}

/** Table bytes by tag, for sfnt (TTF/OTF) and WOFF. */
async function tables(buf: ArrayBuffer, format: FontInfo['format'], wanted: string[]): Promise<Record<string, DataView>> {
  const view = new DataView(buf);
  const out: Record<string, DataView> = {};
  const tag = (o: number) => String.fromCharCode(view.getUint8(o), view.getUint8(o + 1), view.getUint8(o + 2), view.getUint8(o + 3));
  if (format === 'ttf' || format === 'otf') {
    const n = view.getUint16(4);
    for (let i = 0; i < n; i++) {
      const rec = 12 + i * 16;
      const t = tag(rec);
      if (wanted.includes(t)) out[t] = new DataView(buf, view.getUint32(rec + 8), view.getUint32(rec + 12));
    }
  } else if (format === 'woff') {
    const n = view.getUint16(12);
    for (let i = 0; i < n; i++) {
      const rec = 44 + i * 20;
      const t = tag(rec);
      if (!wanted.includes(t)) continue;
      const offset = view.getUint32(rec + 4);
      const comp = view.getUint32(rec + 8);
      const orig = view.getUint32(rec + 12);
      const raw = new Uint8Array(buf, offset, comp);
      const data = comp < orig ? await inflate(raw) : raw.slice();
      out[t] = new DataView(data.buffer, data.byteOffset, data.byteLength);
    }
  }
  return out;
}

/** name table entry: Windows Unicode (UTF-16BE) preferred, Mac Roman otherwise. */
function nameOf(name: DataView, ids: number[]): string {
  const count = name.getUint16(2);
  const strings = name.getUint16(4);
  const found: Record<number, string> = {};
  for (let i = 0; i < count; i++) {
    const r = 6 + i * 12;
    const platform = name.getUint16(r);
    const id = name.getUint16(r + 6);
    if (!ids.includes(id) || found[id]) continue;
    const len = name.getUint16(r + 8);
    const off = strings + name.getUint16(r + 10);
    let s = '';
    if (platform === 3 || platform === 0) {
      for (let k = 0; k + 1 < len; k += 2) s += String.fromCharCode(name.getUint16(off + k));
    } else if (platform === 1) {
      for (let k = 0; k < len; k++) s += String.fromCharCode(name.getUint8(off + k));
    }
    if (s.trim()) found[id] = s.trim();
  }
  for (const id of ids) if (found[id]) return found[id];
  return '';
}

/** Reads family, weight, style and a variable weight range from the file itself when it can. */
export async function readFontInfo(file: File): Promise<FontInfo> {
  const guess = guessFromName(file.name);
  let buf: ArrayBuffer;
  try {
    buf = await file.arrayBuffer();
  } catch {
    return { ...guess, format: '', fromFile: false };
  }
  if (buf.byteLength < 64) return { ...guess, format: '', fromFile: false };
  const format = formatOf(new DataView(buf));
  if (format !== 'ttf' && format !== 'otf' && format !== 'woff') return { ...guess, format, fromFile: false };
  try {
    const t = await tables(buf, format, ['name', 'OS/2', 'fvar']);
    let family = t.name ? nameOf(t.name, [16, 1]) : '';
    let weight = guess.weight;
    let style = guess.style;
    if (t['OS/2'] && t['OS/2'].byteLength >= 64) {
      const w = t['OS/2'].getUint16(4);
      if (w >= 100 && w <= 900) weight = String(Math.round(w / 100) * 100);
      const sel = t['OS/2'].getUint16(62);
      style = sel & 0x0001 || sel & 0x0200 ? 'italic' : 'normal';
    }
    if (t.fvar) {
      const axesAt = t.fvar.getUint16(4);
      const axes = t.fvar.getUint16(8);
      const size = t.fvar.getUint16(10);
      for (let i = 0; i < axes; i++) {
        const a = axesAt + i * size;
        const tag = String.fromCharCode(t.fvar.getUint8(a), t.fvar.getUint8(a + 1), t.fvar.getUint8(a + 2), t.fvar.getUint8(a + 3));
        if (tag !== 'wght') continue;
        const min = Math.max(100, Math.round(t.fvar.getInt32(a + 4) / 65536 / 100) * 100);
        const max = Math.min(900, Math.round(t.fvar.getInt32(a + 12) / 65536 / 100) * 100);
        if (max > min) weight = `${min} ${max}`;
      }
    }
    if (!family) family = guess.family;
    // Family names like "Brand Italic" from older fonts: keep the base family.
    family = family.replace(/\s+(italic|oblique)$/i, '').trim();
    return { family, weight, style, format, fromFile: true };
  } catch {
    return { ...guess, format, fromFile: false };
  }
}
