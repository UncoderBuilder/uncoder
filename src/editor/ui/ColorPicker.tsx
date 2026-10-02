import { useEffect, useMemo, useRef, useState } from 'react';
import { useKit, colorVar, globalColorId } from '../store/kit';
import { formatColor, hsvToRgb, parseColor, rgbToHsv, toHex, type HSVA } from './color';
import { Icon } from './Icon';
import { IconButton } from './primitives';

const RECENT_KEY = 'uncoder-ui-recent-colors';

function recent(): string[] {
  try {
    return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
  } catch {
    return [];
  }
}
function pushRecent(c: string) {
  try {
    const list = [c, ...recent().filter((x) => x !== c)].slice(0, 10);
    localStorage.setItem(RECENT_KEY, JSON.stringify(list));
  } catch {
    /* ignore */
  }
}

/** Resolves a value (possibly a global var) to a concrete color for display. */
export function useResolvedColor(value: string | undefined): string {
  const colors = useKit((s) => s.kit.colors);
  return useMemo(() => {
    const id = globalColorId(value);
    if (id) return colors.find((c) => c.id === id)?.value ?? '';
    return value ?? '';
  }, [value, colors]);
}

function Drag({ className, onMove, children, label }: { className: string; onMove: (x: number, y: number) => void; children?: React.ReactNode; label: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const handle = (e: React.PointerEvent) => {
    const el = ref.current!;
    el.setPointerCapture(e.pointerId);
    const apply = (ev: PointerEvent | React.PointerEvent) => {
      const r = el.getBoundingClientRect();
      onMove(Math.min(1, Math.max(0, (ev.clientX - r.left) / r.width)), Math.min(1, Math.max(0, (ev.clientY - r.top) / r.height)));
    };
    apply(e);
    const move = (ev: PointerEvent) => apply(ev);
    const up = () => {
      el.removeEventListener('pointermove', move);
      el.removeEventListener('pointerup', up);
    };
    el.addEventListener('pointermove', move);
    el.addEventListener('pointerup', up);
  };
  return (
    <div ref={ref} className={className} onPointerDown={handle} role="slider" aria-label={label} tabIndex={0}>
      {children}
    </div>
  );
}

export function ColorPicker({ value, onChange, allowGlobal = true }: { value: string; onChange: (v: string) => void; allowGlobal?: boolean }) {
  const kitColors = useKit((s) => s.kit.colors);
  const gid = globalColorId(value);
  const resolved = gid ? kitColors.find((c) => c.id === gid)?.value ?? '' : value;
  const [hsv, setHsv] = useState<HSVA>(() => rgbToHsv(parseColor(resolved) ?? { r: 110, g: 86, b: 255, a: 1 }));
  const [text, setText] = useState(resolved);
  const last = useRef(value);

  useEffect(() => {
    if (value !== last.current) {
      last.current = value;
      const parsed = parseColor(resolved);
      if (parsed) setHsv(rgbToHsv(parsed));
      setText(resolved);
    }
  }, [value, resolved]);

  const emit = (next: HSVA) => {
    setHsv(next);
    const out = formatColor(hsvToRgb(next));
    last.current = out;
    setText(out);
    onChange(out);
  };

  const rgb = hsvToRgb(hsv);
  const pure = hsvToRgb({ h: hsv.h, s: 1, v: 1, a: 1 });
  const eyedropper = typeof (window as any).EyeDropper === 'function';

  return (
    <div className="uncoder-ui-cp">
      <Drag className="uncoder-ui-cp__sv" label="Saturation and brightness" onMove={(x, y) => emit({ ...hsv, s: x, v: 1 - y })}>
        <div className="uncoder-ui-cp__sv-bg" style={{ background: `rgb(${pure.r},${pure.g},${pure.b})` }} />
        <span className="uncoder-ui-cp__thumb" style={{ left: `${hsv.s * 100}%`, top: `${(1 - hsv.v) * 100}%`, background: toHex(rgb) }} />
      </Drag>
      <div className="uncoder-ui-cp__row">
        <div className="uncoder-ui-cp__sliders">
          <Drag className="uncoder-ui-cp__hue" label="Hue" onMove={(x) => emit({ ...hsv, h: Math.min(359.9, x * 360) })}>
            <span className="uncoder-ui-cp__knob" style={{ left: `${(hsv.h / 360) * 100}%` }} />
          </Drag>
          <Drag className="uncoder-ui-cp__alpha" label="Opacity" onMove={(x) => emit({ ...hsv, a: Math.round(x * 100) / 100 })}>
            <div className="uncoder-ui-cp__alpha-bg" style={{ background: `linear-gradient(90deg, transparent, ${toHex(rgb)})` }} />
            <span className="uncoder-ui-cp__knob" style={{ left: `${hsv.a * 100}%` }} />
          </Drag>
        </div>
        <span className="uncoder-ui-cp__preview" style={{ ['--c' as any]: formatColor(rgb) }} />
      </div>
      <div className="uncoder-ui-cp__row">
        <input
          className="uncoder-ui-input uncoder-ui-input--mono"
          value={text}
          aria-label="Color value"
          onChange={(e) => setText(e.currentTarget.value)}
          onBlur={() => {
            const p = parseColor(text);
            if (p) emit(rgbToHsv(p));
            else setText(resolved);
          }}
          onKeyDown={(e) => {
            if (e.key === 'Enter') (e.target as HTMLInputElement).blur();
          }}
        />
        <span className="uncoder-ui-cp__alpha-val">{Math.round(hsv.a * 100)}%</span>
        {eyedropper && (
          <IconButton
            icon="pipette"
            label="Pick from screen"
            onClick={async () => {
              try {
                const res = await new (window as any).EyeDropper().open();
                const p = parseColor(res.sRGBHex);
                if (p) emit(rgbToHsv(p));
              } catch {
                /* cancelled */
              }
            }}
          />
        )}
      </div>
      {allowGlobal && (
        <div className="uncoder-ui-cp__section">
          <div className="uncoder-ui-cp__label">
            <Icon name="globe" size={12} /> Site colors
          </div>
          <div className="uncoder-ui-cp__swatches">
            {kitColors.map((c) => (
              <button
                key={c.id}
                type="button"
                className={`uncoder-ui-swatch${gid === c.id ? ' is-active' : ''}`}
                style={{ ['--c' as any]: c.value }}
                data-tip={c.name}
                aria-label={`${c.name} (global)`}
                onClick={() => {
                  last.current = colorVar(c.id);
                  onChange(colorVar(c.id));
                  const p = parseColor(c.value);
                  if (p) setHsv(rgbToHsv(p));
                  setText(c.value);
                }}
              />
            ))}
          </div>
        </div>
      )}
      <RecentColors
        onPick={(c) => {
          const p = parseColor(c);
          if (p) emit(rgbToHsv(p));
        }}
      />
      <CommitRecent value={value} />
    </div>
  );
}

function RecentColors({ onPick }: { onPick: (c: string) => void }) {
  const list = recent();
  if (!list.length) return null;
  return (
    <div className="uncoder-ui-cp__section">
      <div className="uncoder-ui-cp__label">Recent</div>
      <div className="uncoder-ui-cp__swatches">
        {list.map((c) => (
          <button key={c} type="button" className="uncoder-ui-swatch" style={{ ['--c' as any]: c }} aria-label={c} data-tip={c} onClick={() => onPick(c)} />
        ))}
      </div>
    </div>
  );
}

/** Stores the final color in "recent" when the picker closes. */
function CommitRecent({ value }: { value: string }) {
  const ref = useRef(value);
  ref.current = value;
  useEffect(
    () => () => {
      if (ref.current && !ref.current.startsWith('var(')) pushRecent(ref.current);
    },
    [],
  );
  return null;
}
