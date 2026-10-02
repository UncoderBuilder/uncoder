import { useEffect, useRef, useState } from 'react';
import type { ControlDef, ControlOptions } from '@shared/types';
import { NumberInput, RangeSlider, TextInput, UnitSelect } from '../ui/inputs';
import { IconButton, Segmented, Toggle } from '../ui/primitives';
import { openMedia } from './MediaControl';
import { VarChip, varId } from '../ui/VarPicker';
import type { ControlProps } from './ControlRow';
import { config, schemaOf } from '../lib/config';
import { readValue } from '../lib/schema';
import { useDoc } from '../store/doc';
import { DEVICE_ICON, deviceLabel, deviceSuffix } from '../lib/devices';
import { setDevice } from '../store/ui';
import { Icon } from '../ui/Icon';
import { useLookup } from './lookup';

export function TextControl({ control, value, placeholder, onChange }: ControlProps<string>) {
  const input = (
    <TextInput
      value={value ?? ''}
      placeholder={(placeholder as string) ?? control.placeholder ?? ''}
      onChange={(v) => onChange(v)}
      aria-label={control.label}
    />
  );
  if (!control.media) return input;
  // A link or a file from the media library (e.g. a background video: YouTube link or an uploaded MP4).
  const kind = control.media;
  return (
    <div className="uncoder-ui-mediaurl">
      {input}
      <IconButton
        icon={kind === 'video' ? 'clapperboard' : kind === 'audio' ? 'music' : 'image'}
        label={`Choose ${kind === 'video' ? 'a video' : kind === 'audio' ? 'an audio file' : 'an image'} from the media library`}
        size={14}
        onClick={async () => {
          const [att] = await openMedia({ type: kind, title: `Choose ${kind === 'video' ? 'a video' : kind === 'audio' ? 'an audio file' : 'an image'}` });
          if (att?.url) onChange(att.url);
        }}
      />
    </div>
  );
}

/** Native date + time picker; stores "YYYY-MM-DDTHH:MM" (the server also reads "YYYY-MM-DD HH:MM"). */
export function DateTimeControl({ control, value, onChange }: ControlProps<string>) {
  return (
    <input
      type="datetime-local"
      className="uncoder-ui-input uncoder-ui-input--datetime"
      value={String(value ?? '').replace(' ', 'T').slice(0, 16)}
      onChange={(e) => onChange(e.currentTarget.value || undefined)}
      aria-label={control.label}
    />
  );
}

export function TextareaControl({ control, value, placeholder, onChange }: ControlProps<string>) {
  const [text, setText] = useState(value ?? '');
  const focused = useRef(false);
  useEffect(() => {
    if (!focused.current) setText(value ?? '');
  }, [value]);
  return (
    <textarea
      className="uncoder-ui-textarea"
      rows={control.rows ?? 3}
      value={text}
      placeholder={(placeholder as string) ?? control.placeholder ?? ''}
      aria-label={control.label}
      onFocus={() => (focused.current = true)}
      onBlur={() => (focused.current = false)}
      onChange={(e) => {
        setText(e.currentTarget.value);
        onChange(e.currentTarget.value);
      }}
    />
  );
}

export function CodeControl({ control, value, onChange, device, settings, keyName }: ControlProps<string>) {
  const [text, setText] = useState(value ?? '');
  useEffect(() => setText(value ?? ''), [value]);
  const box = (
    <textarea
      className="uncoder-ui-textarea uncoder-ui-textarea--code"
      rows={8}
      spellCheck={false}
      value={text}
      placeholder={control.language === 'css' ? (control.responsive && device !== 'desktop' ? `/* ${deviceLabel(device)} and smaller */\nselector { }` : 'selector { }') : ''}
      aria-label={control.label}
      onChange={(e) => setText(e.currentTarget.value)}
      onBlur={() => text !== (value ?? '') && onChange(text)}
      onKeyDown={(e) => {
        if (e.key === 'Tab') {
          e.preventDefault();
          const t = e.currentTarget;
          const s = t.selectionStart;
          const next = text.slice(0, s) + '  ' + text.slice(t.selectionEnd);
          setText(next);
          requestAnimationFrame(() => t.setSelectionRange(s + 2, s + 2));
        }
      }}
    />
  );
  if (!control.responsive) return box;
  // One tab per active breakpoint (from the Design System breakpoints); a tab switches the canvas device.
  return (
    <div className="uncoder-ui-code-rsp">
      <div className="uncoder-ui-devtabs" role="tablist" aria-label={`${control.label ?? 'Code'} per device`}>
        {config.breakpoints.map((bp) => {
          const has = typeof settings[keyName + deviceSuffix(bp.id)] === 'string' && String(settings[keyName + deviceSuffix(bp.id)]).trim() !== '';
          return (
            <button key={bp.id} type="button" role="tab" aria-selected={bp.id === device} className={`uncoder-ui-devtab${bp.id === device ? ' is-active' : ''}`} data-tip={deviceLabel(bp.id)} aria-label={deviceLabel(bp.id)} onClick={() => setDevice(bp.id)}>
              <Icon name={DEVICE_ICON[bp.id] ?? 'monitor'} size={13} />
              {has && <span className="uncoder-ui-devtab__dot" />}
            </button>
          );
        })}
      </div>
      {box}
    </div>
  );
}

export function NumberControl({ control, value, placeholder, onChange }: ControlProps<number | ''>) {
  // A bounded number (opacity, durations, offsets…) gets a slider next to the input, like Elementor.
  if (control.min !== undefined && control.max !== undefined && control.ui !== 'input') {
    const step = control.step ?? (control.max <= 5 ? 0.01 : 1);
    const ph = placeholder !== undefined && placeholder !== '' ? Number(placeholder) : NaN;
    const current = typeof value === 'number' ? value : Number.isFinite(ph) ? ph : control.min;
    return (
      <div className="uncoder-ui-slider">
        <RangeSlider value={current} min={control.min} max={control.max} step={step} onChange={(v) => onChange(v)} ariaLabel={control.label} />
        <NumberInput
          className="uncoder-ui-num--compact"
          value={value}
          placeholder={Number.isFinite(ph) ? String(ph) : ''}
          min={control.min}
          max={control.max}
          step={step}
          onChange={(v) => onChange(v === '' ? undefined : v)}
          ariaLabel={`${control.label} value`}
        />
      </div>
    );
  }
  return (
    <NumberInput
      value={value}
      placeholder={placeholder !== undefined && placeholder !== '' ? String(placeholder) : ''}
      min={control.min}
      max={control.max}
      step={control.step ?? (control.max !== undefined && control.max <= 5 ? 0.05 : 1)}
      onChange={(v) => onChange(v === '' ? undefined : v)}
      ariaLabel={control.label}
    />
  );
}

export function SliderControl({ control, value: raw, placeholder, onChange }: ControlProps<{ size: any; unit: string }>) {
  const units = [...(control.size_units ?? ['px'])];
  if (!units.includes('custom')) units.push('custom');
  // A bare number (e.g. stored by an AI client or older settings) reads as a size in the first unit.
  const value = typeof raw === 'number' || (typeof raw === 'string' && raw !== '') ? { size: raw, unit: units[0] } : raw;
  const current = value ?? (placeholder as any);
  const unit = value?.unit ?? current?.unit ?? units[0];
  const base = defaultRange(unit);
  const custom = control.range?.[unit] ?? {};
  const range = { min: custom.min ?? base.min, max: custom.max ?? base.max, step: custom.step ?? base.step };
  const size = value?.size;
  const ph = placeholder && typeof placeholder === 'object' && (placeholder as any).size !== '' ? String((placeholder as any).size) : '';
  const set = (s: number | string, u = unit) => onChange(s === '' ? undefined : { size: s, unit: u });

  const variable = varId(size);
  if (variable) {
    return (
      <div className="uncoder-ui-slider">
        <VarChip id={variable} onClear={() => onChange(undefined)} />
      </div>
    );
  }
  if (unit === 'custom') {
    return (
      <div className="uncoder-ui-slider">
        <TextInput className="uncoder-ui-input--mono" value={String(size ?? '')} placeholder="clamp(1rem, 4vw, 3rem)" onCommit={(v) => set(v.trim(), 'custom')} aria-label={control.label} />
        <UnitSelect units={units} value={unit} onChange={(u) => set(size ?? '', u)} />
      </div>
    );
  }
  const numeric = typeof size === 'number' ? size : typeof size === 'string' && size !== '' ? Number(size) : NaN;
  return (
    <div className="uncoder-ui-slider">
      <RangeSlider
        value={Number.isFinite(numeric) ? numeric : Number(ph) || range.min}
        min={range.min}
        max={range.max}
        step={range.step}
        onChange={(v) => set(v)}
        ariaLabel={control.label}
      />
      <NumberInput
        className="uncoder-ui-num--compact"
        value={Number.isFinite(numeric) ? numeric : ''}
        placeholder={ph}
        step={range.step}
        onChange={(v) => set(v)}
        ariaLabel={`${control.label} value`}
        suffix={<UnitSelect units={units} value={unit} onChange={(u) => (size === undefined || size === '' ? onChange({ size: '', unit: u }) : set(size, u))} />}
      />
    </div>
  );
}

export function defaultRange(unit: string): { min: number; max: number; step: number } {
  switch (unit) {
    case '%':
      return { min: 0, max: 100, step: 1 };
    case 'em':
    case 'rem':
      return { min: 0, max: 10, step: 0.1 };
    case 'vw':
    case 'vh':
    case 'svh':
    case 'dvh':
      return { min: 0, max: 100, step: 1 };
    case 'deg':
      return { min: -360, max: 360, step: 1 };
    case '':
      return { min: 0, max: 3, step: 0.05 };
    case 's':
      return { min: 0, max: 10, step: 0.1 };
    case 'ms':
      return { min: 0, max: 5000, step: 50 };
    default:
      return { min: 0, max: 200, step: 1 };
  }
}

function optionList(options: ControlOptions | undefined): Array<{ value: string; label: string; icon?: string }> {
  return Object.entries(options ?? {}).map(([value, o]) => (typeof o === 'string' ? { value, label: o } : { value, label: o.label, icon: o.icon }));
}

const IMPLICIT_SOURCES: Record<string, string> = { post_type: 'post_types', taxonomy: 'taxonomies', menu: 'menus', popup: 'popups', template_id: 'templates' };

export function SelectControl({ control, value, placeholder, onChange, keyName }: ControlProps<string>) {
  const hasOptions = Object.keys(control.options ?? {}).length > 0;
  const source = control.source ?? (control.options_dynamic && !hasOptions ? IMPLICIT_SOURCES[keyName] : undefined);
  const remote = useLookup(control.options_dynamic && source ? source : null);
  const opts = remote ?? optionList(control.options);
  const current = value ?? (placeholder as string) ?? '';
  const known = opts.some((o) => o.value === current);
  return (
    <select className="uncoder-ui-select" value={current} aria-label={control.label} onChange={(e) => onChange(e.currentTarget.value)}>
      {!known && <option value={current}>{current === '' ? '— Default —' : current}</option>}
      {opts.map((o) => (
        <option key={o.value} value={o.value}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

/**
 * How to turn row-drawn alignment icons for the flex direction in effect on this device: a column turns
 * main-axis icons 90° and cross-axis icons −90°, a reversed row mirrors the main axis.
 */
function useAxisTurn(axis: ControlDef['axis'], id: string, device: string): string {
  const parentId = useDoc((s) => (axis === 'self' ? s.doc.nodes[id]?.parent ?? null : id));
  const target = useDoc((s) => (axis && parentId ? s.doc.nodes[parentId] : undefined));
  if (!axis || !target || target.type !== 'container') return '';
  const controls = schemaOf('container')?.controls ?? {};
  if (controls.layout && readValue(target.settings, 'layout', controls.layout, device).value === 'grid') return '';
  const dir = String((controls.direction ? readValue(target.settings, 'direction', controls.direction, device).value : '') || 'column');
  const main = axis === 'main';
  if (dir === 'row') return '';
  if (dir === 'row-reverse') return main ? 'flip' : '';
  if (dir === 'column') return main ? 'r90' : 'rm90';
  return 'rm90';
}

export function ChooseControl({ control, value, placeholder, onChange, id, device }: ControlProps<string>) {
  const opts = optionList(control.options).filter((o) => o.value !== '');
  const current = value ?? (placeholder as string) ?? '';
  const iconic = opts.every((o) => o.icon);
  const turn = useAxisTurn(control.axis, id, device);
  return (
    <div className={[value === undefined && placeholder ? 'is-inherited' : '', turn ? `uncoder-ui-axis uncoder-ui-axis--${turn}` : ''].filter(Boolean).join(' ') || undefined}>
      <Segmented options={opts.map((o) => ({ value: o.value, label: o.label, icon: iconic ? o.icon : undefined }))} value={current} onChange={(v) => onChange(v === '' ? undefined : v)} allowEmpty ariaLabel={control.label} />
    </div>
  );
}

export function SwitchControl({ control, value, placeholder, onChange }: ControlProps<boolean>) {
  const on = value ?? !!placeholder;
  return <Toggle checked={!!on} onChange={(v) => onChange(v)} label={control.label} />;
}

export { UnitSelect };
