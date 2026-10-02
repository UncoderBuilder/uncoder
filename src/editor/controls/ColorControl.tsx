import { useRef, useState } from 'react';
import { globalColorId, useKit } from '../store/kit';
import { ColorPicker } from '../ui/ColorPicker';
import { Icon } from '../ui/Icon';
import { Popover } from '../ui/Popover';
import type { ControlProps } from './ControlRow';

export function ColorSwatchButton({ value, placeholder, onChange, label, allowGlobal = true }: { value?: string; placeholder?: string; onChange: (v: string | undefined) => void; label?: string; allowGlobal?: boolean }) {
  const ref = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const colors = useKit((s) => s.kit.colors);
  const shown = value ?? placeholder ?? '';
  const gid = globalColorId(shown);
  const global = gid ? colors.find((c) => c.id === gid) : null;
  const css = global ? global.value : shown;
  return (
    <div className={`uncoder-ui-color${value === undefined && placeholder ? ' is-inherited' : ''}`}>
      <button ref={ref} type="button" className="uncoder-ui-color__btn" onClick={() => setOpen((o) => !o)} aria-label={label ? `${label}: ${shown || 'not set'}` : 'Color'}>
        <span className={`uncoder-ui-color__swatch${!css ? ' is-empty' : ''}`} style={{ ['--c' as any]: css || 'transparent' }} />
        <span className="uncoder-ui-color__name">{global ? global.name : shown ? shown : 'Default'}</span>
        {global && <Icon name="globe" size={12} className="uncoder-ui-color__global" />}
      </button>
      {value !== undefined && (
        <button type="button" className="uncoder-ui-color__clear" aria-label="Clear color" onClick={() => onChange(undefined)}>
          <Icon name="x" size={12} />
        </button>
      )}
      <Popover anchor={ref} open={open} onClose={() => setOpen(false)} width={248} placement="left-start" label="Color picker">
        <ColorPicker value={value ?? placeholder ?? ''} onChange={(v) => onChange(v)} allowGlobal={allowGlobal} />
      </Popover>
    </div>
  );
}

export function ColorControl({ control, value, placeholder, onChange }: ControlProps<string>) {
  return <ColorSwatchButton value={value} placeholder={placeholder as string | undefined} onChange={onChange} label={control.label} />;
}
