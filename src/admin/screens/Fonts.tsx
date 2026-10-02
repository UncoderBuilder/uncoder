import { useEffect, useRef, useState } from 'react';
import { Button, IconButton } from '@editor/ui/primitives';
import { Icon } from '@editor/ui/Icon';
import { api, ApiError } from '../lib/api';
import { cfg } from '../lib/config';
import { readFontInfo } from '../lib/fontinfo';
import { useResource } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { confirmDialog, Dialog } from '../ui/Dialog';
import { Callout, Card, ErrorState, PageHeader, SkeletonRows } from '../ui/kit';

interface Face {
  index: number;
  file: string;
  weight: string;
  style: 'normal' | 'italic';
  format: Fmt;
  url: string;
  size: number;
}
interface Family {
  family: string;
  category: string;
  display: string;
  preload: string;
  faces: Face[];
}
type Fmt = 'woff2' | 'woff' | 'truetype' | 'opentype';

const FORMATS: Array<{ id: Fmt; label: string; ext: string; accept: string }> = [
  { id: 'woff2', label: 'WOFF2', ext: '.woff2', accept: '.woff2,font/woff2' },
  { id: 'woff', label: 'WOFF', ext: '.woff', accept: '.woff,font/woff' },
  { id: 'truetype', label: 'TTF', ext: '.ttf', accept: '.ttf,font/ttf' },
  { id: 'opentype', label: 'OTF', ext: '.otf', accept: '.otf,font/otf' },
];
const FORMAT_LABEL: Record<string, string> = { woff2: 'WOFF2', woff: 'WOFF', truetype: 'TTF', opentype: 'OTF' };
const CATEGORIES = [
  { value: 'sans-serif', label: 'Sans serif' },
  { value: 'serif', label: 'Serif' },
  { value: 'display', label: 'Display' },
  { value: 'handwriting', label: 'Handwriting' },
  { value: 'monospace', label: 'Monospace' },
];
const DISPLAYS = [
  { value: 'swap', label: 'Swap (recommended)', help: 'Text shows at once in the fallback font, then switches.' },
  { value: 'fallback', label: 'Fallback', help: 'A short invisible period, then the fallback; switches only if the font arrives quickly.' },
  { value: 'optional', label: 'Optional', help: 'Uses the font only if it is cached or arrives almost instantly. No layout shift.' },
  { value: 'block', label: 'Block', help: 'Hides text up to 3 s while the font loads. For icon fonts only.' },
  { value: 'auto', label: 'Browser default', help: 'Lets the browser decide.' },
];
const WEIGHTS = ['100', '200', '300', '400', '500', '600', '700', '800', '900'];
const WEIGHT_NAMES: Record<string, string> = { '100': 'Thin', '200': 'Extra light', '300': 'Light', '400': 'Regular', '500': 'Medium', '600': 'Semi bold', '700': 'Bold', '800': 'Extra bold', '900': 'Black' };
const ACCEPT = FORMATS.map((f) => f.accept).join(',');
const PREVIEW_DEFAULT = 'The quick brown fox jumps over the lazy dog';

const variantLabel = (weight: string, style: string) =>
  (weight.includes(' ') ? `Variable ${weight.replace(' ', '–')}` : `${WEIGHT_NAMES[weight] ?? weight} ${weight}`) + (style === 'italic' ? ' italic' : '');
const kb = (n: number) => (n > 1024 * 1024 ? `${(n / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(n / 1024))} KB`);
const fallbackFor = (category: string) => (category === 'serif' ? 'serif' : category === 'monospace' ? 'monospace' : category === 'handwriting' ? 'cursive' : 'sans-serif');
const fontFiles = (list: FileList | File[] | null | undefined) => [...(list ?? [])].filter((f) => /\.(woff2?|ttf|otf)$/i.test(f.name));
const toFmt = (f: string): Fmt => (f === 'ttf' ? 'truetype' : f === 'otf' ? 'opentype' : (f as Fmt));

/** Makes uploaded faces usable for previews right away (the page's @font-face rules load on reload). */
function useFaces(families: Family[] | undefined) {
  useEffect(() => {
    if (!families || !('FontFace' in window)) return;
    const seen: Set<string> = ((window as any).__uncoderFaces ??= new Set());
    for (const f of families) {
      for (const face of f.faces) {
        const key = `${f.family}|${face.url}`;
        if (seen.has(key)) continue;
        seen.add(key);
        new FontFace(f.family, `url("${face.url}")`, { weight: face.weight, style: face.style })
          .load()
          .then((loaded) => document.fonts.add(loaded))
          .catch(() => undefined);
      }
    }
  }, [families]);
}

async function upload(data: FormData): Promise<Family[]> {
  const res = await fetch(cfg.rest.root + 'custom-fonts/upload', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.rest.nonce }, body: data });
  const json = await res.json().catch(() => null);
  if (!res.ok) throw new ApiError(json?.message ?? `Upload failed (${res.status})`, res.status, json);
  return json as Family[];
}

/** A family's faces grouped by variation (weight + style). */
function variations(faces: Face[]): Array<{ key: string; weight: string; style: Face['style']; files: Face[] }> {
  const map = new Map<string, { key: string; weight: string; style: Face['style']; files: Face[] }>();
  for (const f of faces) {
    const key = `${f.weight} ${f.style}`;
    if (!map.has(key)) map.set(key, { key, weight: f.weight, style: f.style, files: [] });
    map.get(key)!.files.push(f);
  }
  return [...map.values()].sort((a, b) => parseInt(a.weight, 10) - parseInt(b.weight, 10) || Number(a.style === 'italic') - Number(b.style === 'italic'));
}

/**
 * Custom fonts (Design System → Custom fonts). "Add new font" opens a dialog like other font managers:
 * a name, then variants — a font style plus one file per format (WOFF2, WOFF, TTF, OTF). Names, weights and
 * styles are read from the files when you pick or drop them. Everything uploaded appears under “Custom
 * fonts” in every font picker.
 */
export function FontsScreen() {
  const list = useResource((signal) => api<Family[]>('custom-fonts', { signal }), []);
  const [dialog, setDialog] = useState<{ font: Family | null; files?: File[] } | null>(null);
  const [sample, setSample] = useState(PREVIEW_DEFAULT);
  const [size, setSize] = useState(32);
  const [dragging, setDragging] = useState(false);
  useFaces(list.data);
  const items = list.data;

  return (
    <div
      className={`uncoder-ui-fonts${dragging ? ' is-dragging' : ''}`}
      onDragOver={(e) => {
        if (!dialog && [...e.dataTransfer.types].includes('Files')) {
          e.preventDefault();
          setDragging(true);
        }
      }}
      onDragLeave={(e) => {
        if (!e.currentTarget.contains(e.relatedTarget as Node)) setDragging(false);
      }}
      onDrop={(e) => {
        if (dialog) return;
        e.preventDefault();
        setDragging(false);
        const files = fontFiles(e.dataTransfer.files);
        if (files.length) setDialog({ font: null, files });
      }}
    >
      <PageHeader
        title="Custom fonts"
        description="Your brand fonts, served from this site: no requests to third parties. They appear under “Custom fonts” in every font picker, the Design System and for AI clients."
        actions={
          <Button variant="primary" icon="plus" onClick={() => setDialog({ font: null })}>
            Add new font
          </Button>
        }
      />

      {list.error && !items ? (
        <ErrorState error={list.error} onRetry={list.reload} />
      ) : !items ? (
        <Card>
          <SkeletonRows rows={3} cols={3} />
        </Card>
      ) : items.length === 0 ? (
        <button type="button" className="uncoder-ui-fontdrop" onClick={() => setDialog({ font: null })}>
          <span className="uncoder-ui-fontdrop__icon">
            <Icon name="type" size={22} />
          </span>
          <strong>Add your first font</strong>
          <span>Give it a name, then upload a file per style: WOFF2 (smallest), WOFF, TTF or OTF, up to 10 MB each. You can also drop files here.</span>
          <span className="uncoder-ui-fontdrop__cta">Add new font</span>
        </button>
      ) : (
        <>
          <div className="uncoder-ui-fontbar">
            <Icon name="text-cursor-input" size={15} />
            <input className="uncoder-ui-fontbar__text" value={sample} onChange={(e) => setSample(e.currentTarget.value)} placeholder="Type to preview" aria-label="Preview text" />
            <input type="range" className="uncoder-ui-fontbar__size" min={14} max={72} value={size} onChange={(e) => setSize(Number(e.currentTarget.value))} aria-label="Preview size" />
            <span className="uncoder-ui-fontbar__px">{size}px</span>
          </div>
          <div className="uncoder-ui-stack">
            {items.map((f) => (
              <FamilyCard key={f.family} font={f} sample={sample || PREVIEW_DEFAULT} size={size} onEdit={() => setDialog({ font: f })} onData={list.setData} />
            ))}
          </div>
          <Callout tone="info" icon="info">
            Only upload fonts whose license allows self-hosting on the web.
          </Callout>
        </>
      )}

      <AdobeFontsCard />

      {dragging && (
        <div className="uncoder-ui-fonts__overlay" aria-hidden>
          <Icon name="upload" size={22} />
          Drop to add a new font
        </div>
      )}
      {dialog && (
        <FontDialog
          font={dialog.font}
          files={dialog.files}
          existing={(items ?? []).map((f) => f.family)}
          onClose={() => setDialog(null)}
          onSaved={(data, name) => {
            list.setData(data);
            setDialog(null);
            toast(dialog.font ? `“${name}” saved` : `“${name}” added`);
          }}
        />
      )}
    </div>
  );
}

function FamilyCard({ font, sample, size, onEdit, onData }: { font: Family; sample: string; size: number; onEdit: () => void; onData: (d: Family[]) => void }) {
  const vars = variations(font.faces);
  const stack = `"${font.family}", ${fallbackFor(font.category)}`;
  const remove = async () => {
    const ok = await confirmDialog({
      title: `Delete “${font.family}”?`,
      body: 'Every file of this font is deleted. Text that uses it falls back to the system font of its category.',
      confirmLabel: 'Delete',
      danger: true,
    });
    if (!ok) return;
    try {
      onData(await api<Family[]>('custom-fonts/delete', { body: { family: font.family } }));
      toast(`“${font.family}” deleted`);
    } catch (e) {
      toastError(e);
    }
  };
  const category = CATEGORIES.find((c) => c.value === font.category)?.label ?? font.category;
  return (
    <Card
      className="uncoder-ui-fontfam"
      title={font.family}
      description={`${vars.length} variant${vars.length === 1 ? '' : 's'} · ${font.faces.length} file${font.faces.length === 1 ? '' : 's'} · ${category} fallback · use it as “${font.family}” in any font setting`}
      actions={
        <>
          <Button size="sm" icon="pencil" onClick={onEdit}>
            Edit
          </Button>
          <IconButton icon="trash-2" tone="danger" label={`Delete ${font.family}`} onClick={remove} />
        </>
      }
    >
      <p className="uncoder-ui-fontfam__sample" style={{ fontFamily: stack, fontSize: size }}>
        {sample}
      </p>
      <ul className="uncoder-ui-fontvars">
        {vars.map((v) => (
          <li key={v.key} className="uncoder-ui-fontvars__row">
            <span className="uncoder-ui-fontvars__demo" style={{ fontFamily: stack, fontWeight: v.weight.includes(' ') ? undefined : Number(v.weight), fontStyle: v.style }}>
              {sample}
            </span>
            <span className="uncoder-ui-fontvars__name">{variantLabel(v.weight, v.style)}</span>
            <span className="uncoder-ui-fontvars__files">
              {v.files.map((f) => (
                <span key={f.file} className={`uncoder-ui-fontchip is-${f.format}`}>
                  {FORMAT_LABEL[f.format] ?? f.format}
                  <span className="uncoder-ui-fontchip__size">{kb(f.size)}</span>
                </span>
              ))}
              {!v.files.some((f) => f.format === 'woff2') && (
                <span className="uncoder-ui-fontvars__hint" data-tip="WOFF2 is the smallest format: add one for faster pages.">
                  <Icon name="lightbulb" size={12} />
                  no WOFF2
                </span>
              )}
            </span>
            {font.preload === v.key && (
              <span className="uncoder-ui-fontvars__preload is-on" data-tip="Preloaded: renders in this font on first paint">
                <Icon name="zap" size={14} />
              </span>
            )}
          </li>
        ))}
      </ul>
    </Card>
  );
}

// ------------------------------------------------------------------ Add / edit dialog

interface Slot {
  /** A file already stored for this format. */
  face?: Face;
  /** A new file picked in this dialog (uploaded on save). */
  file?: File;
}
interface Variant {
  key: number;
  weight: string;
  style: 'normal' | 'italic';
  preload: boolean;
  slots: Partial<Record<Fmt, Slot>>;
  /** The style was chosen by hand (files picked later do not change it). */
  touched: boolean;
}

let seq = 0;
const emptyVariant = (weight = '400', style: Variant['style'] = 'normal'): Variant => ({ key: ++seq, weight, style, preload: false, slots: {}, touched: false });
const hasFiles = (v: Variant) => Object.values(v.slots).some((s) => s && (s.face || s.file));

function fromFont(font: Family): Variant[] {
  return variations(font.faces).map((g) => ({
    key: ++seq,
    weight: g.weight,
    style: g.style,
    preload: font.preload === g.key,
    touched: true,
    slots: Object.fromEntries(g.files.map((f) => [f.format, { face: f }])),
  }));
}

/** The next style nobody uses yet: Regular, Bold, Italic, Bold italic, then the rest. */
function nextStyle(list: Variant[]): [string, Variant['style']] {
  const used = new Set(list.map((v) => `${v.weight} ${v.style}`));
  const order: Array<[string, Variant['style']]> = [
    ['400', 'normal'],
    ['700', 'normal'],
    ['400', 'italic'],
    ['700', 'italic'],
    ...WEIGHTS.flatMap((w) => [[w, 'normal'], [w, 'italic']] as Array<[string, Variant['style']]>),
  ];
  return order.find(([w, s]) => !used.has(`${w} ${s}`)) ?? ['400', 'normal'];
}

function FontDialog({ font, files, existing, onClose, onSaved }: { font: Family | null; files?: File[]; existing: string[]; onClose: () => void; onSaved: (data: Family[], name: string) => void }) {
  const [name, setName] = useState(font?.family ?? '');
  const [category, setCategory] = useState(font?.category ?? 'sans-serif');
  const [display, setDisplay] = useState(font?.display ?? 'swap');
  const [variants, setVariants] = useState<Variant[]>(() => (font ? fromFont(font) : [emptyVariant()]));
  const [busy, setBusy] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [dropping, setDropping] = useState(false);
  const picker = useRef<HTMLInputElement>(null);
  const target = useRef<{ key: number; fmt: Fmt } | null>(null);
  const nameRef = useRef(name);
  nameRef.current = name;

  const update = (key: number, patch: Partial<Variant> | ((v: Variant) => Variant)) =>
    setVariants((list) => list.map((v) => (v.key === key ? (typeof patch === 'function' ? patch(v) : { ...v, ...patch }) : v)));

  /** Reads a file and puts it where it belongs: its format's slot, and the variant it describes. */
  const addFiles = async (picked: File[], into?: { key: number; fmt: Fmt }) => {
    for (const file of picked) {
      const info = await readFontInfo(file);
      const fmt = toFmt(info.format);
      if (!nameRef.current.trim() && info.family) setName((n) => n.trim() || info.family);
      setVariants((list) => {
        let next = [...list];
        let v = into ? next.find((x) => x.key === into.key) : undefined;
        if (!v) {
          // Dropped files: the variant with the file's weight and style, an empty untouched one, or a new one.
          v = next.find((x) => x.weight === info.weight && x.style === info.style) ?? next.find((x) => !x.touched && !hasFiles(x));
          if (!v) {
            v = emptyVariant(info.weight, info.style);
            next = [...next, v];
          }
        }
        const base = !v.touched && !hasFiles(v) ? { ...v, weight: info.weight, style: info.style } : v;
        const filled: Variant = { ...base, slots: { ...base.slots, [fmt]: { ...base.slots[fmt], file } } };
        return next.map((x) => (x.key === v!.key ? filled : x));
      });
      if (into && fmt !== into.fmt) toast(`${file.name} is a ${FORMAT_LABEL[fmt]} file: added to the ${FORMAT_LABEL[fmt]} slot`);
    }
  };

  const pick = (key: number, fmt: Fmt) => {
    target.current = { key, fmt };
    if (picker.current) {
      picker.current.accept = FORMATS.find((f) => f.id === fmt)?.accept ?? ACCEPT;
      picker.current.click();
    }
  };

  const clean = name.trim();
  const clash = clean !== '' && clean !== font?.family && existing.includes(clean);
  const dupes = new Set<string>();
  const seen = new Set<string>();
  for (const v of variants) {
    const k = `${v.weight} ${v.style}`;
    if (seen.has(k)) dupes.add(k);
    seen.add(k);
  }
  const problem = !clean
    ? 'Enter a font name.'
    : clash
      ? `A font called “${clean}” already exists: edit it instead.`
      : variants.some((v) => !hasFiles(v))
        ? 'Every variant needs at least one file.'
        : dupes.size
          ? `Two variants use the same style: ${[...dupes].map((k) => variantLabel(k.slice(0, k.lastIndexOf(' ')), k.slice(k.lastIndexOf(' ') + 1))).join(', ')}.`
          : null;

  const save = async () => {
    if (problem) return setError(problem);
    setError(null);
    const family = font?.family ?? clean;
    const uploads = variants.flatMap((v) => FORMATS.filter((f) => v.slots[f.id]?.file).map((f) => ({ v, fmt: f.id, file: v.slots[f.id]!.file! })));
    const stored = new Map<File, string>();
    let data: Family[] | null = null;
    try {
      let known = new Set((font?.faces ?? []).map((f) => f.file));
      for (const [i, u] of uploads.entries()) {
        setBusy(`Uploading ${i + 1} of ${uploads.length}…`);
        const form = new FormData();
        form.append('family', family);
        form.append('category', category);
        form.append('weight', u.v.weight);
        form.append('style', u.v.style);
        form.append('replace', '0');
        form.append('file', u.file);
        data = await upload(form);
        const faces = data.find((f) => f.family === family)?.faces ?? [];
        const added = faces.find((f) => !known.has(f.file));
        if (added) stored.set(u.file, added.file);
        known = new Set(faces.map((f) => f.file));
      }
      setBusy('Saving…');
      const preload = variants.find((v) => v.preload);
      data = await api<Family[]>('custom-fonts/save', {
        body: {
          family,
          name: clean,
          category,
          display,
          preload: preload ? `${preload.weight} ${preload.style}` : '',
          faces: variants.flatMap((v) =>
            FORMATS.flatMap((f) => {
              const slot = v.slots[f.id];
              const file = slot?.file ? stored.get(slot.file) : slot?.face?.file;
              return file ? [{ file, weight: v.weight, style: v.style }] : [];
            }),
          ),
        },
      });
      onSaved(data, clean);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not save the font.');
      setBusy(null);
    }
  };

  useEffect(() => {
    if (files?.length) addFiles(files);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const styleValue = (v: Variant) => (v.weight.includes(' ') ? `var|${v.style}` : `${v.weight}|${v.style}`);

  return (
    <Dialog
      open
      onClose={busy ? () => undefined : onClose}
      width={680}
      className="uncoder-ui-fontdlg"
      title={font ? `Edit “${font.family}”` : 'Add new font'}
      description="Name the font, then add a variant for each style with its files. Picked or dropped files fill in their name, weight and style."
      footer={
        <>
          <label className="uncoder-ui-fontdlg__display">
            <span>Loading</span>
            <select className="uncoder-ui-select uncoder-ui-select--sm" value={display} onChange={(e) => setDisplay(e.currentTarget.value)} title={DISPLAYS.find((d) => d.value === display)?.help}>
              {DISPLAYS.map((d) => (
                <option key={d.value} value={d.value}>
                  {d.label}
                </option>
              ))}
            </select>
          </label>
          <Button variant="ghost" onClick={onClose} disabled={!!busy}>
            Cancel
          </Button>
          <Button variant="primary" onClick={save} loading={!!busy}>
            {busy ?? (font ? 'Save font' : 'Add font')}
          </Button>
        </>
      }
    >
      <div
        className={`uncoder-ui-fontdlg__body${dropping ? ' is-dropping' : ''}`}
        onDragOver={(e) => {
          if ([...e.dataTransfer.types].includes('Files')) {
            e.preventDefault();
            e.stopPropagation();
            setDropping(true);
          }
        }}
        onDragLeave={(e) => {
          if (!e.currentTarget.contains(e.relatedTarget as Node)) setDropping(false);
        }}
        onDrop={(e) => {
          e.preventDefault();
          e.stopPropagation();
          setDropping(false);
          const dropped = fontFiles(e.dataTransfer.files);
          if (dropped.length) addFiles(dropped);
        }}
      >
        <input
          ref={picker}
          type="file"
          className="uncoder-ui-sr-only"
          aria-hidden
          tabIndex={-1}
          onChange={(e) => {
            const picked = fontFiles(e.currentTarget.files);
            e.currentTarget.value = '';
            if (picked.length && target.current) addFiles(picked, target.current);
          }}
        />
        {error && <Callout tone="danger">{error}</Callout>}
        <div className="uncoder-ui-fontdlg__top">
          <label className="uncoder-ui-fontdlg__field">
            <input className="uncoder-ui-input" value={name} onChange={(e) => setName(e.currentTarget.value)} placeholder="e.g. Brand Sans" autoFocus={!font} aria-invalid={clash} />
            <span>Font name</span>
          </label>
          <label className="uncoder-ui-fontdlg__field uncoder-ui-fontdlg__field--fallback">
            <select className="uncoder-ui-select" value={category} onChange={(e) => setCategory(e.currentTarget.value)}>
              {CATEGORIES.map((c) => (
                <option key={c.value} value={c.value}>
                  {c.label}
                </option>
              ))}
            </select>
            <span>Fallback while loading</span>
          </label>
        </div>

        {variants.map((v, i) => (
          <section key={v.key} className="uncoder-ui-fontdlg__variant" aria-label={`Variant ${i + 1}`}>
            <div className="uncoder-ui-fontdlg__vhead">
              <label className="uncoder-ui-fontdlg__field uncoder-ui-fontdlg__field--style">
                <select
                  className="uncoder-ui-select"
                  value={styleValue(v)}
                  onChange={(e) => {
                    const [w, s] = e.currentTarget.value.split('|');
                    update(v.key, { weight: w === 'var' ? (v.weight.includes(' ') ? v.weight : '100 900') : w, style: s as Variant['style'], touched: true });
                  }}
                >
                  <optgroup label="Normal">
                    {WEIGHTS.map((w) => (
                      <option key={w} value={`${w}|normal`}>
                        {variantLabel(w, 'normal')}
                      </option>
                    ))}
                  </optgroup>
                  <optgroup label="Italic">
                    {WEIGHTS.map((w) => (
                      <option key={w} value={`${w}|italic`}>
                        {variantLabel(w, 'italic')}
                      </option>
                    ))}
                  </optgroup>
                  <optgroup label="Variable font">
                    <option value="var|normal">Variable (weight range)</option>
                    <option value="var|italic">Variable italic (weight range)</option>
                  </optgroup>
                </select>
                <span>Font style</span>
              </label>
              {v.weight.includes(' ') && (
                <span className="uncoder-ui-fontdlg__range">
                  <select className="uncoder-ui-select" aria-label="Lightest weight" value={v.weight.split(' ')[0]} onChange={(e) => update(v.key, { weight: `${e.currentTarget.value} ${v.weight.split(' ')[1]}`, touched: true })}>
                    {WEIGHTS.slice(0, -1).map((w) => (
                      <option key={w} value={w}>
                        {w}
                      </option>
                    ))}
                  </select>
                  <span>to</span>
                  <select className="uncoder-ui-select" aria-label="Heaviest weight" value={v.weight.split(' ')[1]} onChange={(e) => update(v.key, { weight: `${v.weight.split(' ')[0]} ${e.currentTarget.value}`, touched: true })}>
                    {WEIGHTS.slice(1).map((w) => (
                      <option key={w} value={w}>
                        {w}
                      </option>
                    ))}
                  </select>
                </span>
              )}
              <button
                type="button"
                className={`uncoder-ui-fontdlg__preload${v.preload ? ' is-on' : ''}`}
                aria-pressed={v.preload}
                data-tip="Preload: renders in this font on first paint. Best for the body text style; one per font."
                onClick={() => setVariants((list) => list.map((x) => ({ ...x, preload: x.key === v.key ? !x.preload : false })))}
              >
                <Icon name="zap" size={13} /> Preload
              </button>
              {variants.length > 1 && (
                <button type="button" className="uncoder-ui-fontdlg__remove" onClick={() => setVariants((list) => list.filter((x) => x.key !== v.key))}>
                  <Icon name="minus" size={13} /> Remove variant
                </button>
              )}
            </div>
            <div className="uncoder-ui-fontdlg__slots">
              {FORMATS.map((f) => {
                const slot = v.slots[f.id];
                const has = !!(slot?.file || slot?.face);
                return (
                  <div key={f.id} className={`uncoder-ui-fontdlg__slot${has ? ' is-set' : ''}`}>
                    <div className="uncoder-ui-fontdlg__file">
                      <span className={`uncoder-ui-fontchip is-${f.id}`}>{f.label}</span>
                      {has ? (
                        <>
                          <span className="uncoder-ui-fontdlg__fname" title={slot!.file?.name ?? slot!.face!.file}>
                            {slot!.file?.name ?? slot!.face!.file}
                          </span>
                          <span className="uncoder-ui-fontdlg__fmeta">{kb(slot!.file?.size ?? slot!.face!.size)}{slot!.file ? ' · new' : ''}</span>
                          <button
                            type="button"
                            className="uncoder-ui-fontdlg__clear"
                            aria-label={`Remove the ${f.label} file`}
                            onClick={() =>
                              update(v.key, (x) => {
                                const slots = { ...x.slots };
                                delete slots[f.id];
                                return { ...x, slots };
                              })
                            }
                          >
                            <Icon name="x" size={12} />
                          </button>
                        </>
                      ) : (
                        <span className="uncoder-ui-fontdlg__none">{f.id === 'woff2' ? 'Recommended: smallest file' : `No ${f.ext} file`}</span>
                      )}
                    </div>
                    <Button size="sm" variant={f.id === 'woff2' && !has ? 'primary' : 'secondary'} icon="upload" onClick={() => pick(v.key, f.id)}>
                      {has ? 'Replace' : `Upload ${f.ext}`}
                    </Button>
                  </div>
                );
              })}
            </div>
          </section>
        ))}

        <button type="button" className="uncoder-ui-fontdlg__add" onClick={() => setVariants((list) => [...list, emptyVariant(...nextStyle(list))])}>
          <Icon name="plus" size={13} /> Add variant
        </button>
        {dropping && (
          <div className="uncoder-ui-fontdlg__drop" aria-hidden>
            <Icon name="upload" size={20} /> Drop to add these files
          </div>
        )}
      </div>
    </Dialog>
  );
}


interface AdobeState {
  project: string;
  families: Record<string, string[]>;
  synced: number;
}

/** Adobe Fonts: connect a Web Project; its families join every font picker (Adobe serves the files). */
function AdobeFontsCard() {
  const state = useResource((signal) => api<AdobeState>('adobe-fonts', { signal }), []);
  const [project, setProject] = useState('');
  const [busy, setBusy] = useState(false);
  const s = state.data;
  const families = s ? Object.entries(s.families) : [];

  const connect = async (id: string) => {
    setBusy(true);
    try {
      const res = await api<AdobeState>('adobe-fonts', { body: { project: id } });
      state.setData(res);
      setProject('');
      toast(`Adobe Fonts connected: ${Object.keys(res.families).length} famil${Object.keys(res.families).length === 1 ? 'y' : 'ies'}. Reload the builder to use them.`, 'success');
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(false);
    }
  };
  const disconnect = async () => {
    if (!(await confirmDialog({ title: 'Disconnect Adobe Fonts?', body: 'Text set in these fonts falls back to the next font in its stack until you connect again.', confirmLabel: 'Disconnect', danger: true }))) return;
    try {
      state.setData(await api<AdobeState>('adobe-fonts', { method: 'DELETE' }));
      toast('Adobe Fonts disconnected');
    } catch (e) {
      toastError(e);
    }
  };

  return (
    <Card
      title="Adobe Fonts"
      description="Use the fonts of an Adobe Fonts web project. In Adobe Fonts open “Web Projects”, copy the project ID (like abc1def) and paste it here. Adobe serves the files, so visitors’ browsers contact Adobe on pages that use them."
    >
      {!s ? (
        <SkeletonRows rows={1} cols={2} />
      ) : s.project ? (
        <div className="uncoder-ui-stack">
          <div className="uncoder-ui-inline">
            <strong>
              Project <code>{s.project}</code>
            </strong>
            <span className="uncoder-ui-muted">
              {families.length} famil{families.length === 1 ? 'y' : 'ies'}
              {s.synced ? ` · synced ${new Date(s.synced * 1000).toLocaleDateString()}` : ''}
            </span>
          </div>
          <ul className="uncoder-ui-adobe">
            {families.map(([family, weights]) => (
              <li key={family}>
                <span style={{ fontFamily: `"${family}"` }}>{family}</span>
                <span className="uncoder-ui-muted">{weights.join(', ')}</span>
              </li>
            ))}
          </ul>
          <div className="uncoder-ui-inline">
            <Button size="sm" icon="refresh-cw" loading={busy} onClick={() => connect('')}>
              Sync again
            </Button>
            <Button size="sm" variant="ghost" onClick={disconnect}>
              Disconnect
            </Button>
          </div>
          <link rel="stylesheet" href={`https://use.typekit.net/${s.project}.css`} />
        </div>
      ) : (
        <form
          className="uncoder-ui-inline"
          onSubmit={(e) => {
            e.preventDefault();
            if (project.trim()) connect(project.trim());
          }}
        >
          <input className="uncoder-ui-input" value={project} onChange={(e) => setProject(e.currentTarget.value)} placeholder="Web Project ID" aria-label="Adobe Fonts Web Project ID" spellCheck={false} />
          <Button type="submit" variant="primary" loading={busy} disabled={!project.trim()}>
            Connect
          </Button>
        </form>
      )}
    </Card>
  );
}
