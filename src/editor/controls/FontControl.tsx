import { useEffect, useMemo, useRef, useState } from 'react';
import { config } from '../lib/config';
import { fonts } from '../lib/fonts';
import { useKit } from '../store/kit';
import { Icon } from '../ui/Icon';
import { Popover } from '../ui/Popover';
import type { ControlProps } from './ControlRow';

export function FontPicker({ value, placeholder, onChange, label }: { value?: string; placeholder?: string; onChange: (v: string | undefined) => void; label?: string }) {
  const ref = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const [ready, setReady] = useState(false);
  const kitFonts = useKit((s) => s.kit.fonts);
  useEffect(() => {
    if (open && !ready) fonts.load().then(() => setReady(true));
  }, [open, ready]);
  const shown = value ?? placeholder ?? '';
  const kitMatch = shown.match(/^var\(--uncoder-f-([a-z0-9_\-]+)\)$/);
  const kitFont = kitMatch ? kitFonts.find((f) => f.id === kitMatch[1]) : null;
  const display = kitFont ? `${kitFont.name}${kitFont.family ? ` · ${kitFont.family}` : ''}` : shown || 'Default';

  const customFonts = useMemo(() => Object.entries(config.customFonts ?? {}).map(([family, d]) => ({ family, category: d.c })), []);
  const list = useMemo(() => {
    if (!ready) return [];
    const needle = q.trim().toLowerCase();
    const all = fonts.list();
    return (needle ? all.filter((f) => f.family.toLowerCase().includes(needle)) : all.filter((f) => !f.custom)).slice(0, 80);
  }, [q, ready]);

  return (
    <>
      <button ref={ref} type="button" className={`uncoder-ui-selectbtn${value === undefined && placeholder ? ' is-inherited' : ''}`} onClick={() => setOpen((o) => !o)} aria-label={label ? `${label}: ${display}` : 'Font'}>
        <span className="uncoder-ui-selectbtn__label">{display}</span>
        {kitFont && <Icon name="globe" size={12} className="uncoder-ui-color__global" />}
        <Icon name="chevron-down" size={12} />
      </button>
      <Popover anchor={ref} open={open} onClose={() => setOpen(false)} width={260} placement="left-start" label="Font family">
        <div className="uncoder-ui-fontpick">
          <input className="uncoder-ui-input" placeholder={customFonts.length ? 'Search fonts' : 'Search Google Fonts'} value={q} onChange={(e) => setQ(e.currentTarget.value)} data-autofocus aria-label="Search fonts" />
          <div className="uncoder-ui-fontpick__list">
            {!q && (
              <>
                <div className="uncoder-ui-fontpick__group">Site fonts</div>
                {kitFonts.map((f) => (
                  <button key={f.id} type="button" className="uncoder-ui-fontpick__item" onClick={() => (onChange(`var(--uncoder-f-${f.id})`), setOpen(false))}>
                    <Icon name="globe" size={12} className="uncoder-ui-color__global" /> {f.name}
                    <span className="uncoder-ui-fontpick__cat">{f.family || 'theme'}</span>
                  </button>
                ))}
                <button type="button" className="uncoder-ui-fontpick__item" onClick={() => (onChange(undefined), setOpen(false))}>
                  Default (inherit)
                </button>
                {customFonts.length > 0 && (
                  <>
                    <div className="uncoder-ui-fontpick__group">Custom fonts</div>
                    {customFonts.map((f) => (
                      <button key={f.family} type="button" className={`uncoder-ui-fontpick__item${f.family === shown ? ' is-active' : ''}`} onClick={() => (onChange(f.family), setOpen(false))}>
                        <span style={{ fontFamily: `"${f.family}"` }}>{f.family}</span>
                        <span className="uncoder-ui-fontpick__cat">uploaded</span>
                      </button>
                    ))}
                  </>
                )}
                <div className="uncoder-ui-fontpick__group">Google Fonts</div>
              </>
            )}
            {!ready && <div className="uncoder-ui-fontpick__none">Loading fonts…</div>}
            {list.map((f) => (
              <button key={f.family} type="button" className={`uncoder-ui-fontpick__item${f.family === shown ? ' is-active' : ''}`} onClick={() => (onChange(f.family), setOpen(false))}>
                {f.family}
                <span className="uncoder-ui-fontpick__cat">{f.category}</span>
              </button>
            ))}
            {ready && q && !list.length && (
              <button type="button" className="uncoder-ui-fontpick__item" onClick={() => (onChange(q.trim()), setOpen(false))}>
                Use “{q.trim()}” (custom font)
              </button>
            )}
          </div>
        </div>
      </Popover>
    </>
  );
}

export function FontControl({ control, value, placeholder, onChange }: ControlProps<string>) {
  return <FontPicker value={value} placeholder={placeholder as string | undefined} onChange={onChange} label={control.label} />;
}
