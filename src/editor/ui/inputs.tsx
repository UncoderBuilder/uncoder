import { useEffect, useRef, useState, type InputHTMLAttributes, type ReactNode } from 'react';

function evalNumber(text: string): number | null {
  const t = text.trim();
  if (t === '') return null;
  if (/^-?\d*\.?\d+$/.test(t)) return Number(t);
  // Tiny, safe arithmetic: digits, dots, spaces and + - * / ( ).
  if (!/^[\d.\s+\-*/()]+$/.test(t)) return NaN;
  try {
    const tokens = t.match(/\d*\.?\d+|[+\-*/()]/g) ?? [];
    let pos = 0;
    const expr = (): number => {
      let v = term();
      while (tokens[pos] === '+' || tokens[pos] === '-') v = tokens[pos++] === '+' ? v + term() : v - term();
      return v;
    };
    const term = (): number => {
      let v = factor();
      while (tokens[pos] === '*' || tokens[pos] === '/') v = tokens[pos++] === '*' ? v * factor() : v / factor();
      return v;
    };
    const factor = (): number => {
      const tk = tokens[pos++];
      if (tk === '(') {
        const v = expr();
        pos++;
        return v;
      }
      if (tk === '-') return -factor();
      return Number(tk);
    };
    const v = expr();
    return Number.isFinite(v) ? Math.round(v * 10000) / 10000 : NaN;
  } catch {
    return NaN;
  }
}

interface NumberInputProps {
  value: number | '' | null | undefined;
  onChange: (v: number | '') => void;
  min?: number;
  max?: number;
  step?: number;
  placeholder?: string;
  /** Shown inside the drag handle (e.g. a side glyph in spacing fields). */
  prefix?: ReactNode;
  suffix?: ReactNode;
  scrub?: boolean;
  ariaLabel?: string;
  className?: string;
  width?: number;
}

/** When anything last scrolled: a wheel over a number field right after scrolling a panel keeps scrolling. */
let lastScroll = 0;
if (typeof document !== 'undefined') document.addEventListener('scroll', () => (lastScroll = performance.now()), { capture: true, passive: true });

export function NumberInput({ value, onChange, min, max, step = 1, placeholder, prefix, suffix, scrub = true, ariaLabel, className, width }: NumberInputProps) {
  const [text, setText] = useState(value === '' || value === null || value === undefined ? '' : String(value));
  const focused = useRef(false);
  const root = useRef<HTMLDivElement>(null);
  // Values this field sent itself. Anything else arriving while it has focus (undo, a linked side, AI)
  // replaces the text too; only the user's own typing is left alone.
  const emitted = useRef<Array<number | ''>>([]);
  const emit = (v: number | '') => {
    emitted.current = [...emitted.current.slice(-8), v];
    onChange(v);
  };
  const latest = useRef({ value, placeholder, onChange: emit });
  latest.current = { value, placeholder, onChange: emit };
  useEffect(() => {
    const own = emitted.current.includes(value === null || value === undefined ? '' : value);
    if (!focused.current || !own) setText(value === '' || value === null || value === undefined ? '' : String(value));
    emitted.current = [];
  }, [value]);

  // Mouse wheel over the field nudges the value (Shift ×10, Alt ×0.1). Needs a non-passive listener to stop
  // the panel from scrolling; skipped while a panel is mid-scroll so passing over a field does not grab the wheel.
  useEffect(() => {
    const el = root.current;
    if (!el) return;
    let acc = 0;
    const onWheel = (e: WheelEvent) => {
      if (!focused.current && performance.now() - lastScroll < 400) return;
      e.preventDefault();
      acc += e.deltaMode === 1 ? e.deltaY * 16 : e.deltaY;
      if (Math.abs(acc) < 24) return;
      const dir = acc < 0 ? 1 : -1;
      acc = 0;
      const { value: v, placeholder: ph, onChange: change } = latest.current;
      const base = typeof v === 'number' ? v : Number(ph) || 0;
      const mult = e.shiftKey ? 10 : e.altKey ? 0.1 : 1;
      const next = clamp(base + dir * step * mult);
      change(next);
      setText(String(next));
    };
    el.addEventListener('wheel', onWheel, { passive: false });
    return () => el.removeEventListener('wheel', onWheel);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [step, min, max]);

  const clamp = (n: number) => {
    let v = n;
    if (min !== undefined && v < min) v = min;
    if (max !== undefined && v > max) v = max;
    const decimals = String(step).split('.')[1]?.length ?? 0;
    return Number(v.toFixed(Math.max(decimals, 2)));
  };

  const commit = (raw: string) => {
    const n = evalNumber(raw);
    if (n === null) {
      emit('');
      setText('');
    } else if (Number.isNaN(n)) {
      setText(value === '' || value === null || value === undefined ? '' : String(value));
    } else {
      const v = clamp(n);
      emit(v);
      setText(String(v));
    }
  };

  const nudge = (dir: number, e: React.KeyboardEvent) => {
    const base = typeof value === 'number' ? value : Number(placeholder) || 0;
    const mult = e.shiftKey ? 10 : e.altKey ? 0.1 : 1;
    const v = clamp(base + dir * step * mult);
    emit(v);
    setText(String(v));
  };

  const onScrubDown = (e: React.PointerEvent) => {
    if (!scrub || e.button !== 0) return;
    e.preventDefault();
    const startX = e.clientX;
    const start = typeof value === 'number' ? value : Number(placeholder) || 0;
    const target = e.currentTarget as HTMLElement;
    target.setPointerCapture(e.pointerId);
    document.body.classList.add('uncoder-ui-scrubbing');
    const move = (ev: PointerEvent) => {
      const dx = ev.clientX - startX;
      const mult = ev.shiftKey ? 10 : ev.altKey ? 0.1 : 1;
      const v = clamp(start + Math.round(dx / 2) * step * mult);
      emit(v);
      setText(String(v));
    };
    const up = () => {
      target.removeEventListener('pointermove', move);
      target.removeEventListener('pointerup', up);
      document.body.classList.remove('uncoder-ui-scrubbing');
    };
    target.addEventListener('pointermove', move);
    target.addEventListener('pointerup', up);
  };

  return (
    <div ref={root} className={'uncoder-ui-num' + (prefix ? ' has-prefix' : '') + (className ? ' ' + className : '')} style={width ? { width } : undefined}>
      {scrub ? (
        <span className="uncoder-ui-num__scrub" onPointerDown={onScrubDown} aria-hidden title="Drag or scroll to adjust">
          {prefix}
        </span>
      ) : (
        prefix && (
          <span className="uncoder-ui-num__prefix" aria-hidden>
            {prefix}
          </span>
        )
      )}
      <input
        className="uncoder-ui-num__input"
        inputMode="decimal"
        value={text}
        placeholder={placeholder}
        aria-label={ariaLabel}
        onFocus={(e) => {
          focused.current = true;
          e.currentTarget.select();
        }}
        onBlur={(e) => {
          focused.current = false;
          commit(e.currentTarget.value);
        }}
        onChange={(e) => setText(e.currentTarget.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter') commit((e.target as HTMLInputElement).value);
          else if (e.key === 'ArrowUp') {
            e.preventDefault();
            nudge(1, e);
          } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            nudge(-1, e);
          }
        }}
      />
      {typeof suffix === 'string' ? (
        <span className="uncoder-ui-num__suffix" aria-hidden>
          {suffix}
        </span>
      ) : (
        suffix
      )}
    </div>
  );
}

export function UnitSelect({ units, value, onChange }: { units: string[]; value: string; onChange: (u: string) => void }) {
  if (units.length <= 1) return <span className="uncoder-ui-unit uncoder-ui-unit--static">{units[0] === '' ? '—' : units[0]}</span>;
  return (
    <select className="uncoder-ui-unit" value={value} onChange={(e) => onChange(e.currentTarget.value)} aria-label="Unit">
      {units.map((u) => (
        <option key={u} value={u}>
          {u === '' ? '—' : u === 'custom' ? 'fx' : u}
        </option>
      ))}
    </select>
  );
}

export function TextInput({ value, onChange, onCommit, className, ...rest }: Omit<InputHTMLAttributes<HTMLInputElement>, 'onChange' | 'value'> & { value: string; onChange?: (v: string) => void; onCommit?: (v: string) => void }) {
  const [text, setText] = useState(value ?? '');
  const focused = useRef(false);
  useEffect(() => {
    if (!focused.current) setText(value ?? '');
  }, [value]);
  return (
    <input
      className={'uncoder-ui-input' + (className ? ' ' + className : '')}
      value={text}
      onFocus={() => (focused.current = true)}
      onBlur={() => {
        focused.current = false;
        onCommit?.(text);
      }}
      onChange={(e) => {
        setText(e.currentTarget.value);
        onChange?.(e.currentTarget.value);
      }}
      onKeyDown={(e) => {
        if (e.key === 'Enter' && onCommit) onCommit((e.target as HTMLInputElement).value);
      }}
      {...rest}
    />
  );
}

export function RangeSlider({ value, min, max, step, onChange, ariaLabel }: { value: number; min: number; max: number; step: number; onChange: (v: number) => void; ariaLabel?: string }) {
  const pct = max > min ? ((Math.min(max, Math.max(min, value)) - min) / (max - min)) * 100 : 0;
  return (
    <input
      type="range"
      className="uncoder-ui-range"
      min={min}
      max={max}
      step={step}
      value={Number.isFinite(value) ? value : min}
      aria-label={ariaLabel}
      style={{ ['--uncoder-ui-range-pct' as any]: `${pct}%` }}
      onChange={(e) => onChange(Number(e.currentTarget.value))}
    />
  );
}
