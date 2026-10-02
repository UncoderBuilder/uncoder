import { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import type { IconValue } from '@shared/types';
import { loadLibraries, namesOf, setIconSvg, useIconSet, type IconLibrary } from '../lib/iconSets';
import { Icon } from '../ui/Icon';
import { IconButton } from '../ui/primitives';
import { ScrollRow } from '../ui/ScrollRow';
import { openMedia } from './MediaControl';
import type { ControlProps } from './ControlRow';

const POPULAR = ['check', 'arrow-right', 'arrow-up-right', 'star', 'heart', 'phone', 'mail', 'map-pin', 'clock', 'calendar', 'shield-check', 'zap', 'sparkles', 'rocket', 'users', 'award', 'truck', 'leaf', 'coffee', 'camera', 'globe', 'lock', 'search', 'shopping-bag', 'message-circle', 'play', 'chevron-right', 'plus', 'minus', 'x', 'menu', 'quote', 'thumbs-up', 'badge-check', 'circle-check', 'gift', 'house', 'briefcase', 'graduation-cap', 'wrench'];
const LUCIDE: IconLibrary = { id: 'lucide', title: 'Lucide Icons', group: 'Lucide', count: 0, viewBox: '0 0 24 24', mode: 'stroke', sw: 2 };
const PAGE = 240;

const hasIcon = (v: IconValue | undefined) => !!v && v.library !== 'none' && !!(v.value || v.url);

/** An icon value from any library, drawn at `size` (libraries load on demand). */
export function IconPreview({ value, size = 18 }: { value: IconValue | undefined; size?: number }) {
  useIconSet(value?.library);
  if (!hasIcon(value)) return <Icon name="ban" size={size - 2} />;
  if (value!.library === 'svg') return <img src={value!.url} alt="" width={size} height={size} />;
  if (value!.library === 'lucide' || !value!.library) return <Icon name={value!.value!} size={size} />;
  const svg = setIconSvg(value!.library, value!.value!, size);
  return svg ? <span className="uncoder-ui-libicon" dangerouslySetInnerHTML={{ __html: svg }} /> : <span className="uncoder-ui-libicon is-loading" style={{ width: size, height: size }} />;
}

export function IconControl({ control, value, placeholder, onChange }: ControlProps<IconValue>) {
  const [open, setOpen] = useState(false);
  const v = value ?? (placeholder as IconValue | undefined);
  const label = hasIcon(v) ? (v!.library === 'svg' ? 'Custom SVG' : v!.value) : 'None';
  return (
    <div className="uncoder-ui-iconctl">
      <button type="button" className="uncoder-ui-iconctl__btn" onClick={() => setOpen(true)} aria-label={`${control.label}: ${label}`}>
        <span className="uncoder-ui-iconctl__preview">
          <IconPreview value={v} size={18} />
        </span>
        <span className="uncoder-ui-iconctl__name">{label}</span>
        <Icon name="chevron-down" size={12} />
      </button>
      {value && <IconButton icon="x" label="Remove icon" size={12} onClick={() => onChange({ library: 'none', value: '' })} />}
      {open && (
        <IconLibraryDialog
          current={v}
          onClose={() => setOpen(false)}
          onPick={(icon) => {
            onChange(icon);
            setOpen(false);
          }}
        />
      )}
    </div>
  );
}

/** One icon of a library (the All tab mixes libraries). */
type IconItem = { lib: string; name: string };

/** The icon library: All icons, then every set in tabs; search by name and search terms; SVG upload. */
function IconLibraryDialog({ current, onClose, onPick }: { current: IconValue | undefined; onClose: () => void; onPick: (icon: IconValue) => void }) {
  const [libraries, setLibraries] = useState<IconLibrary[]>([LUCIDE]);
  const [lib, setLib] = useState<string>(current?.library && current.library !== 'svg' && current.library !== 'none' ? current.library : 'lucide');
  const [q, setQ] = useState('');
  const [data, setData] = useState<{ items: IconItem[]; tags: Record<string, string[]> } | null>(null);
  const [shown, setShown] = useState(PAGE);
  const grid = useRef<HTMLDivElement>(null);
  const ready = useIconSet(lib === 'all' ? undefined : lib);

  useEffect(() => {
    loadLibraries().then((list) => setLibraries([LUCIDE, ...list]));
  }, []);
  useEffect(() => {
    let live = true;
    setData(null);
    // All icons: every library (each set loads once and stays cached); tags are keyed "library:name".
    const libs = lib === 'all' ? libraries.map((l) => l.id) : [lib];
    Promise.all(libs.map((id) => namesOf(id).then((d) => ({ id, ...d })))).then((sets) => {
      if (!live) return;
      const items: IconItem[] = [];
      const tags: Record<string, string[]> = {};
      for (const set of sets) {
        for (const name of set.names) {
          items.push({ lib: set.id, name });
          if (set.tags[name]) tags[`${set.id}:${name}`] = set.tags[name];
        }
      }
      setData({ items, tags });
    });
    setShown(PAGE);
    grid.current?.scrollTo(0, 0);
    return () => {
      live = false;
    };
  }, [lib, lib === 'all' ? libraries.length : 0]);
  useEffect(() => {
    setShown(PAGE);
    grid.current?.scrollTo(0, 0);
  }, [q]);
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  const results = useMemo(() => {
    if (!data) return [];
    const needle = q.trim().toLowerCase();
    if (!needle) {
      if (lib !== 'lucide' && lib !== 'all') return data.items;
      // Lucide's everyday icons first.
      const popular = POPULAR.map((name) => data.items.find((i) => i.lib === 'lucide' && i.name === name)).filter(Boolean) as IconItem[];
      return [...popular, ...data.items.filter((i) => !(i.lib === 'lucide' && POPULAR.includes(i.name)))];
    }
    const starts: IconItem[] = [];
    const rest: IconItem[] = [];
    for (const item of data.items) {
      if (item.name.startsWith(needle)) starts.push(item);
      else if (item.name.includes(needle) || data.tags[`${item.lib}:${item.name}`]?.some((t) => t.includes(needle))) rest.push(item);
    }
    return [...starts, ...rest];
  }, [data, q, lib]);

  // Opening on the current icon: render far enough to include it and scroll it into view.
  const revealed = useRef(false);
  useEffect(() => {
    if (revealed.current || !data || !ready || q || !current?.value) return;
    revealed.current = true;
    const at = results.findIndex((i) => i.lib === current.library && i.name === current.value);
    if (at < 0) return;
    if (at >= PAGE) setShown(at + PAGE / 2);
    reveal.current = true;
  }, [data, ready, results, q, current]);
  const reveal = useRef(false);
  useLayoutEffect(() => {
    const cell = reveal.current && grid.current?.querySelector<HTMLElement>('.uncoder-ui-iconlib__cell.is-active');
    if (!cell || !grid.current) return;
    reveal.current = false;
    grid.current.scrollTop = cell.offsetTop - grid.current.clientHeight / 2 + cell.offsetHeight / 2;
  });

  const all = lib === 'all';
  const active = all ? { ...LUCIDE, id: 'all', title: 'all icons' } : libraries.find((l) => l.id === lib) ?? LUCIDE;
  const titleOf = (id: string) => libraries.find((l) => l.id === id)?.title ?? id;
  const total = libraries.reduce((n, l) => n + (l.count || 0), 0);
  const groups = useMemo(() => {
    const out: Array<{ group: string; items: IconLibrary[] }> = [];
    for (const l of libraries) {
      const g = out.find((x) => x.group === l.group);
      if (g) g.items.push(l);
      else out.push({ group: l.group, items: [l] });
    }
    return out;
  }, [libraries]);

  return createPortal(
    <div className="uncoder-ui-scrim" onPointerDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className="uncoder-ui-dialog uncoder-ui-iconlib" role="dialog" aria-modal="true" aria-labelledby="uncoder-ui-iconlib-title">
        <div className="uncoder-ui-iconlib__head">
          <h2 id="uncoder-ui-iconlib-title">Icon library</h2>
          <div className="uncoder-ui-iconlib__search">
            <Icon name="search" size={14} />
            <input autoFocus value={q} onChange={(e) => setQ(e.currentTarget.value)} placeholder={all ? 'Search all icons…' : `Search ${active.title}…`} aria-label="Search icons" />
          </div>
          <IconButton
            icon="upload"
            label="Upload an SVG"
            onClick={async () => {
              const [att] = await openMedia({ title: 'Choose an SVG icon', type: 'image/svg+xml' });
              if (att) onPick({ library: 'svg', id: att.id, url: att.url });
            }}
          />
          <IconButton icon="x" label="Close" onClick={onClose} />
        </div>
        <ScrollRow className="uncoder-ui-iconlib__tabs" role="tablist" label="Icon libraries" activeKey={lib}>
          <button type="button" role="tab" aria-selected={all} className={`uncoder-ui-iconlib__tab${all ? ' is-active' : ''}`} onClick={() => setLib('all')}>
            All icons
          </button>
          {groups.map((g) => {
            const tab = (l: IconLibrary, text: string) => (
              <button key={l.id} type="button" role="tab" aria-selected={l.id === lib} aria-label={l.title} className={`uncoder-ui-iconlib__tab${l.id === lib ? ' is-active' : ''}`} onClick={() => setLib(l.id)}>
                {text}
              </button>
            );
            if (g.items.length === 1) return tab(g.items[0], g.items[0].title);
            // Styles of one family share a pill: "Font Awesome | Solid · Regular · Brands".
            return (
              <div key={g.group} className={`uncoder-ui-iconlib__group${g.items.some((l) => l.id === lib) ? ' is-active' : ''}`} role="presentation">
                <span className="uncoder-ui-iconlib__group-name">{g.group}</span>
                {g.items.map((l) => tab(l, l.title.replace(g.group, '').replace(/^\s*[–-]\s*/, '')))}
              </div>
            );
          })}
        </ScrollRow>
        <div
          ref={grid}
          className="uncoder-ui-iconlib__grid"
          onScroll={(e) => {
            const el = e.currentTarget;
            if (el.scrollTop + el.clientHeight > el.scrollHeight - 200 && shown < results.length) setShown((n) => n + PAGE);
          }}
        >
          {!data || !ready ? (
            <div className="uncoder-ui-iconlib__empty">
              <span className="uncoder-ui-spinner" aria-hidden /> Loading {all ? `all ${total ? total.toLocaleString() + ' ' : ''}icons` : active.title}…
            </div>
          ) : results.length === 0 ? (
            <div className="uncoder-ui-iconlib__empty">No icon matches “{q}” in {active.title}.{all ? '' : ' Try All icons.'}</div>
          ) : (
            results.slice(0, shown).map((item) => {
              const selected = current?.library === item.lib && current.value === item.name;
              const svg = item.lib === 'lucide' ? null : setIconSvg(item.lib, item.name, 22);
              const tip = all ? `${item.name} · ${titleOf(item.lib)}` : item.name;
              return (
                <button key={`${item.lib}:${item.name}`} type="button" className={`uncoder-ui-iconlib__cell${selected ? ' is-active' : ''}`} data-tip={tip} aria-label={tip} onClick={() => onPick({ library: item.lib, value: item.name })}>
                  {svg ? <span dangerouslySetInnerHTML={{ __html: svg }} /> : <Icon name={item.name} size={22} />}
                </button>
              );
            })
          )}
        </div>
        <div className="uncoder-ui-iconlib__foot">
          <span>
            {data ? `${results.length.toLocaleString()} icon${results.length === 1 ? '' : 's'}` : ''}
            {hasIcon(current) && current!.library !== 'svg' && (
              <>
                {' · '}current: <strong>{current!.value}</strong>
              </>
            )}
          </span>
          <button type="button" className="uncoder-ui-iconlib__none" onClick={() => onPick({ library: 'none', value: '' })}>
            <Icon name="ban" size={13} /> No icon
          </button>
        </div>
      </div>
    </div>,
    document.querySelector('.uncoder-ui-portal') ?? document.body,
  );
}
