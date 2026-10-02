import { useMemo, useRef, useState } from 'react';
import type { ControlDef, Settings } from '@shared/types';
import { useKit } from '../store/kit';
import { Icon } from '../ui/Icon';
import { Popover } from '../ui/Popover';
import { Segmented } from '../ui/primitives';
import { ControlForm } from './ControlForm';
import type { ControlProps } from './ControlRow';

const isEmptyObj = (o: Settings | undefined) => !o || Object.keys(o).length === 0;

function patch(value: Settings | undefined, key: string, v: any): Settings | undefined {
  const next: Settings = { ...(value ?? {}) };
  if (v === undefined || v === '') delete next[key];
  else next[key] = v;
  return Object.keys(next).length ? next : undefined;
}

function GroupPopover({ control, value, onChange, summary, children, width = 280 }: { control: ControlDef; value?: Settings; onChange: (v: Settings | undefined) => void; summary: string; children: React.ReactNode; width?: number }) {
  const ref = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  return (
    <div className="uncoder-ui-group">
      <button ref={ref} type="button" className={`uncoder-ui-selectbtn${!isEmptyObj(value) ? ' is-set' : ''}`} onClick={() => setOpen((o) => !o)} aria-expanded={open} aria-label={`${control.label}: ${summary}`}>
        <span className="uncoder-ui-selectbtn__label">{summary}</span>
        <Icon name="pencil" size={12} />
      </button>
      <Popover anchor={ref} open={open} onClose={() => setOpen(false)} width={width} placement="left-start" label={control.label}>
        <div className="uncoder-ui-group__pop">
          <div className="uncoder-ui-group__title">{control.label}</div>
          {children}
        </div>
      </Popover>
    </div>
  );
}

/* ---------------------------------------------------------------- Typography */

export function TypographyControl({ control, value, onChange }: ControlProps<Settings>) {
  const presets = useKit((s) => s.kit.typography);
  const fields = control.fields ?? {};
  const preset = presets.find((p) => p.id === value?.preset);
  const size = value?.size ? `${value.size.size}${value.size.unit === 'custom' ? '' : value.size.unit}` : '';
  const fam = typeof value?.family === 'string' ? value.family.replace(/^var\(--uncoder-f-(.+)\)$/, '$1') : '';
  const summary = [preset?.name, fam, size, value?.weight].filter(Boolean).join(' · ') || 'Default';
  const withPresetOptions: Record<string, ControlDef> = useMemo(
    () => ({
      ...fields,
      preset: { ...(fields.preset ?? { type: 'select', label: 'Text style' }), type: 'select', options: { '': '— None —', ...Object.fromEntries(presets.map((p) => [p.id, p.name])) }, options_dynamic: false },
    }),
    [fields, presets],
  );
  return (
    <GroupPopover control={control} value={value} onChange={onChange} summary={summary} width={290}>
      <div className="uncoder-ui-typo__presets">
        {presets.slice(0, 12).map((p) => (
          <button key={p.id} type="button" className={`uncoder-ui-typo__preset${value?.preset === p.id ? ' is-active' : ''}`} onClick={() => onChange(patch(value, 'preset', value?.preset === p.id ? undefined : p.id))} data-tip={p.name}>
            <span className="uncoder-ui-typo__ag" style={{ fontWeight: Number(p.value?.weight) || 400 }}>
              Ag
            </span>
            <span>{p.name}</span>
          </button>
        ))}
      </div>
      <ControlForm controls={withPresetOptions} values={value ?? {}} onChange={(k, v) => onChange(patch(value, k, v))} only={Object.keys(fields).filter((k) => k !== 'preset')} />
    </GroupPopover>
  );
}

/* ---------------------------------------------------------------- Background */

const BG_FIELDS: Record<string, string[]> = {
  classic: ['color', 'image', 'position', 'size', 'repeat', 'attachment'],
  gradient: ['color', 'color_stop', 'color_b', 'color_b_stop', 'gradient_type', 'gradient_angle', 'gradient_position'],
  video: ['video_url', 'video_fallback', 'color'],
  slideshow: ['slides', 'slide_duration', 'slide_transition', 'slide_speed', 'ken_burns', 'slide_size', 'slide_position', 'color'],
};

export function BackgroundControl({ control, value, onChange }: ControlProps<Settings>) {
  const fields = control.fields ?? {};
  const typeOpts = Object.entries((fields.type?.options ?? {}) as Record<string, any>).map(([v, l]) => ({
    value: v,
    label: typeof l === 'string' ? l : l.label,
    icon: v === '' ? 'ban' : v === 'classic' ? 'paintbrush' : v === 'gradient' ? 'blend' : v === 'video' ? 'clapperboard' : v === 'slideshow' ? 'images' : 'square',
  }));
  const type = String(value?.type ?? '');
  let only = BG_FIELDS[type] ?? [];
  if (type === 'classic' && !value?.image?.url && !Object.keys(value ?? {}).some((k) => k.startsWith('image_'))) only = ['color', 'image'];
  if (type === 'gradient') only = only.filter((k) => (value?.gradient_type === 'radial' ? k !== 'gradient_angle' : k !== 'gradient_position'));
  return (
    <div className="uncoder-ui-bg">
      <Segmented options={typeOpts} value={type} onChange={(t) => onChange(t ? { ...(value ?? {}), type: t } : undefined)} ariaLabel={`${control.label} type`} />
      {type && <ControlForm controls={fields} values={value ?? {}} onChange={(k, v) => onChange(patch(value, k, v) ?? { type })} only={only} />}
    </div>
  );
}

/* ---------------------------------------------------------------- Border */

export function BorderControl({ control, value, onChange }: ControlProps<Settings>) {
  const fields = control.fields ?? {};
  const style = value?.style;
  const w = value?.width;
  const summary = style || w ? `${style || 'solid'}${w?.top !== undefined && w.top !== '' ? ` · ${w.top}${w.unit ?? 'px'}` : ''}` : 'None';
  return (
    <GroupPopover control={control} value={value} onChange={onChange} summary={summary}>
      <ControlForm controls={fields} values={value ?? {}} onChange={(k, v) => onChange(patch(value, k, v))} />
    </GroupPopover>
  );
}

/* ---------------------------------------------------------------- Shadows, filters, transform */

export function ShadowControl({ control, value, onChange }: ControlProps<Settings>) {
  const fields = control.fields ?? {};
  const summary = value?.color ? `${value.x ?? 0} ${value.y ?? 0} ${value.blur ?? 0}${value.spread !== undefined ? ' ' + value.spread : ''}` : 'None';
  const presets = control.type === 'box_shadow'
    ? [
        { label: 'Soft', v: { x: 0, y: 8, blur: 24, spread: -8, color: 'rgba(15, 23, 42, 0.18)' } },
        { label: 'Medium', v: { x: 0, y: 14, blur: 40, spread: -12, color: 'rgba(15, 23, 42, 0.28)' } },
        { label: 'Crisp', v: { x: 0, y: 1, blur: 2, spread: 0, color: 'rgba(15, 23, 42, 0.12)' } },
        { label: 'Glow', v: { x: 0, y: 0, blur: 32, spread: 0, color: 'rgba(110, 86, 255, 0.35)' } },
      ]
    : [];
  return (
    <GroupPopover control={control} value={value} onChange={onChange} summary={summary}>
      {presets.length > 0 && (
        <div className="uncoder-ui-chips">
          {presets.map((p) => (
            <button key={p.label} type="button" className="uncoder-ui-chip" onClick={() => onChange({ ...p.v })}>
              {p.label}
            </button>
          ))}
        </div>
      )}
      <ControlForm controls={fields} values={value ?? {}} onChange={(k, v) => onChange(patch(value, k, v))} />
    </GroupPopover>
  );
}

export function FiltersControl({ control, value, onChange }: ControlProps<Settings>) {
  const fields = control.fields ?? {};
  const n = Object.keys(value ?? {}).length;
  return (
    <GroupPopover control={control} value={value} onChange={onChange} summary={n ? `${n} filter${n > 1 ? 's' : ''}` : 'None'}>
      <ControlForm controls={fields} values={value ?? {}} onChange={(k, v) => onChange(patch(value, k, v))} />
    </GroupPopover>
  );
}

export function TransformControl({ control, value, onChange }: ControlProps<Settings>) {
  const fields = control.fields ?? {};
  const n = Object.keys(value ?? {}).length;
  return (
    <GroupPopover control={control} value={value} onChange={onChange} summary={n ? 'Custom' : 'None'}>
      <ControlForm controls={fields} values={value ?? {}} onChange={(k, v) => onChange(patch(value, k, v))} />
    </GroupPopover>
  );
}

export function QueryControl({ control, value, onChange }: ControlProps<Settings>) {
  const fields = control.fields ?? {};
  const source = value?.source ?? 'posts';
  const only = Object.keys(fields).filter((k) => {
    if (source === 'current') return k === 'source';
    if (source === 'manual') return ['source', 'include_ids'].includes(k);
    if (source === 'related') return ['source', 'posts_per_page', 'orderby', 'order'].includes(k);
    return true;
  });
  return (
    <div className="uncoder-ui-query">
      <ControlForm controls={fields} values={value ?? {}} onChange={(k, v) => onChange(patch(value, k, v))} only={only} />
    </div>
  );
}
