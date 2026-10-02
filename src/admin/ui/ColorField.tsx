import { useEffect, useState } from 'react';
import { formatColor, parseColor, toHex } from '@editor/ui/color';

/** Swatch (native picker, opaque) + text input (any CSS color, including rgba). */
export function ColorField({ value, onChange, label }: { value: string; onChange: (v: string) => void; label: string }) {
  const [text, setText] = useState(value);
  useEffect(() => setText(value), [value]);
  const parsed = parseColor(value);
  const hex = parsed ? toHex({ ...parsed, a: 1 }).slice(0, 7) : '#000000';

  const commit = (raw: string) => {
    const t = raw.trim();
    if (t === value) return;
    if (parseColor(t)) onChange(t);
    else setText(value);
  };

  return (
    <div className="uncoder-ui-colorfield">
      <label className="uncoder-ui-colorfield__swatch" style={{ ['--uncoder-ui-swatch' as string]: value }} title="Pick a color">
        <input
          type="color"
          value={hex}
          aria-label={`${label}: pick a color`}
          onChange={(e) => {
            const picked = parseColor(e.currentTarget.value);
            if (!picked) return;
            // Keep the current transparency when picking a new hue.
            const next = parsed && parsed.a < 1 ? formatColor({ ...picked, a: parsed.a }) : e.currentTarget.value;
            onChange(next);
          }}
        />
      </label>
      <input
        className="uncoder-ui-input uncoder-ui-input--mono"
        value={text}
        aria-label={label}
        spellCheck={false}
        onChange={(e) => setText(e.currentTarget.value)}
        onBlur={(e) => commit(e.currentTarget.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter') commit((e.target as HTMLInputElement).value);
        }}
      />
    </div>
  );
}
