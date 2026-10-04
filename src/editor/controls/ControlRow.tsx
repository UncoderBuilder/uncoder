import { memo, useMemo, useRef, useState } from 'react';
import type { ControlDef, DynamicDef, Settings } from '@shared/types';
import { config } from '../lib/config';
import { readValue, visible, writeKey } from '../lib/schema';
import { setDynamic, updateSettings, updateSettingsMany, useDoc } from '../store/doc';
import { useInspectorTargets } from '../app/inspectorTargets';
import { breakpoints, useUi } from '../store/ui';
import { Icon } from '../ui/Icon';
import { Popover } from '../ui/Popover';
import { IconButton } from '../ui/primitives';
import { controlComponent, GROUP_TYPES, STACKED, STACKED_UI } from './registry';
import { ControlForm } from './ControlForm';
import { AiTextButton, aiWritable } from './AiAssist';
import { stateValues, withStateValue } from '../lib/states';
import { varGroup, varId, VarPicker } from '../ui/VarPicker';


export interface ControlProps<T = any> {
  control: ControlDef;
  value: T;
  onChange: (v: T | undefined) => void;
  placeholder?: T;
  device: string;
  id: string;
  keyName: string;
  settings: Settings;
}

export const ControlRow = memo(function ControlRow({ id, keyName, control, controls, settings, stateKey = 'normal' }: { id: string; keyName: string; control: ControlDef; controls: Record<string, ControlDef>; settings: Settings; stateKey?: string }) {
  const device = useUi((s) => s.device);
  const raw = useDoc((s) => s.doc.nodes[id]?.settings);
  const targets = useInspectorTargets();
  const many = targets.length > 1 ? targets : null;
  // Several elements whose values for this control differ.
  const mixed = useDoc((s) => {
    if (!many || !control) return false;
    const valueOf = (settings: Settings | undefined) => {
      const src = stateKey !== 'normal' ? stateValues(settings, stateKey) : settings ?? {};
      return JSON.stringify(readValue(src, keyName, control, device).value ?? null);
    };
    const first = valueOf(s.doc.nodes[many[0]]?.settings);
    return many.some((i) => valueOf(s.doc.nodes[i]?.settings) !== first);
  });
  const dynamic = useDoc((s) => s.doc.nodes[id]?.dynamic?.[keyName]);
  if (!control || !raw) return null;
  if (!visible(control, settings, controls, device)) return null;

  // Shown by the inspector itself (ClassesBar at the top of the Style tab).
  if (control.ui === 'classes') return null;
  if (control.type === 'heading') return <div className="uncoder-ui-ctl-heading">{control.label}</div>;
  if (control.type === 'divider') return <hr className="uncoder-ui-ctl-divider" />;
  if (control.type === 'notice')
    return (
      <div className="uncoder-ui-notice">
        <Icon name="info" size={14} />
        <span>{control.label ?? control.description}</span>
      </div>
    );

  const Comp = controlComponent(control);
  if (!Comp) return <div className="uncoder-ui-ctl__unknown">Unsupported control “{control.type}”.</div>;

  const inState = stateKey !== 'normal';
  const normal = readValue(raw, keyName, control, device);
  // In a state: its own value, else the Normal value as the faded placeholder.
  const own = inState ? readValue(stateValues(raw, stateKey), keyName, control, device) : normal;
  const read = inState && own.from === null ? { value: normal.value, own: false, from: normal.from } : own;
  const wk = writeKey(keyName, control, device);
  const isGroup = GROUP_TYPES.has(control.type);
  const k = isGroup ? keyName : wk;
  const write = (v: any, mergeKey?: string) =>
    many
      ? updateSettingsMany(many, (settings) => (inState ? { _states: withStateValue(settings, stateKey, k, v) } : { [k]: v }), { mergeKey: mergeKey && `${mergeKey}@${many.length}:${stateKey}` })
      : inState
        ? updateSettings(id, { _states: withStateValue(raw, stateKey, k, v) }, { mergeKey: mergeKey && `${mergeKey}@${stateKey}` })
        : updateSettings(id, { [k]: v }, { mergeKey });
  const onChange = (v: any) => write(v, `${id}:${isGroup ? keyName : wk}`);
  const stacked = STACKED.has(control.type) || STACKED_UI.has(control.ui ?? '') || (control.type === 'textarea' && (control.rows ?? 3) > 1) || control.label === undefined;
  const tools = !dynamic && (aiWritable(control, keyName) || !!control.dynamic);
  const layout = rowLayout(control, stacked || (tools && control.type === 'text'));
  // Content text shows its default as editable text (not as a faded placeholder).
  const textDefault = !read.own && read.from === null && control.tab === 'content' && ['text', 'textarea', 'wysiwyg'].includes(control.type);

  return (
    <div className={`uncoder-ui-ctl uncoder-ui-ctl--t-${control.type} uncoder-ui-ctl--${layout}${isGroup ? ' uncoder-ui-ctl--group' : ''}${read.own ? ' is-set' : ''}`} data-control={keyName}>
      {control.label !== undefined && (
        <div className="uncoder-ui-ctl__label">
          <ValueDot own={read.own} from={read.from} device={device} onReset={() => write(undefined)} />
          <span className="uncoder-ui-ctl__text" title={control.label}>
            {control.label}
          </span>
          {control.description && <HelpTip text={control.description} />}
          {mixed && (
            <span className="uncoder-ui-ctl__mixed" data-tip="The selected elements have different values">
              Mixed
            </span>
          )}
          {/* Helpers (AI, dynamic data, design variables) show on hover or focus of the row, or while in use. */}
          <span className="uncoder-ui-ctl__tools">
            {!dynamic && aiWritable(control, keyName) && <AiTextButton control={control} value={typeof read.value === 'string' ? read.value : ''} onApply={(v) => updateSettings(id, { [wk]: v })} />}
            {control.dynamic && !dynamic && <DynamicButton id={id} keyName={keyName} control={control} />}
            {(control.type === 'slider' || control.type === 'dimensions') && (
              <VarPicker
                group={varGroup(control, keyName)}
                current={control.type === 'slider' ? varId(read.own ? read.value?.size : null) : varId(read.own ? read.value?.top : null)}
                onPick={(ref) =>
                  onChange(
                    control.type === 'slider'
                      ? { size: ref, unit: 'custom' }
                      : { top: ref, right: ref, bottom: ref, left: ref, unit: (read.own ? read.value?.unit : undefined) ?? control.size_units?.[0] ?? 'px', linked: true },
                  )
                }
              />
            )}
          </span>
        </div>
      )}
      <div className="uncoder-ui-ctl__input">
        {dynamic ? (
          <DynamicChip id={id} keyName={keyName} def={dynamic} />
        ) : (
          <Comp
            control={control}
            value={read.own || isGroup || textDefault ? read.value : undefined}
            placeholder={read.own || textDefault ? undefined : read.value}
            onChange={onChange}
            device={device}
            id={id}
            keyName={keyName}
            settings={settings}
          />
        )}
      </div>
      {control.description && control.label === undefined && <p className="uncoder-ui-ctl__desc">{control.description}</p>}
    </div>
  );
});

/**
 * How a row lays out its label and input:
 * inline  — label column + input (selects, choices, colours, numbers…)
 * stacked — label above a full-width input (rich inputs, and labels too long for the label column)
 * slider  — label and value on one line, the slider underneath
 * switch  — label on the left (it may wrap), the switch at the right
 */
export function rowLayout(control: ControlDef, stacked: boolean): 'inline' | 'stacked' | 'slider' | 'switch' {
  if (control.type === 'switch') return 'switch';
  if (control.type === 'slider' || (control.type === 'number' && control.min !== undefined && control.max !== undefined && control.ui !== 'input')) return 'slider';
  if (stacked || (control.label?.length ?? 0) > 15) return 'stacked';
  return 'inline';
}

/** The control's description, as a tooltip on a small help icon next to its label. */
export function HelpTip({ text }: { text: string }) {
  return (
    <span className="uncoder-ui-ctl__help" tabIndex={0} role="img" aria-label={text} data-tip={text} data-tip-force="">
      <Icon name="circle-help" size={12} />
    </span>
  );
}

/** ● value set on this device (click to reset) · ○ inherited from a larger device · faint ○ default. */
function ValueDot({ own, from, device, onReset }: { own: boolean; from: string | null; device: string; onReset: () => void }) {
  const name = (d: string) => breakpoints.find((b) => b.id === d)?.label ?? d;
  if (own) {
    return <button type="button" className="uncoder-ui-ctl__dot is-set" aria-label={`Set on ${name(device)}. Reset`} data-tip={`Set on ${name(device)} · click to reset`} onClick={onReset} />;
  }
  if (from) return <span className="uncoder-ui-ctl__dot is-inherited" data-tip={`Inherited from ${name(from)}`} />;
  return <span className="uncoder-ui-ctl__dot is-default" aria-hidden />;
}

function categoriesFor(control: ControlDef): string[] {
  switch (control.type) {
    case 'url':
    case 'link':
      return ['url'];
    case 'media':
      return ['image'];
    case 'number':
      return ['number', 'text'];
    case 'color':
      return ['color'];
    case 'wysiwyg':
      return ['text', 'html'];
    default:
      return ['text'];
  }
}

function DynamicButton({ id, keyName, control }: { id: string; keyName: string; control: ControlDef }) {
  const ref = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const cats = categoriesFor(control);
  const groups = useMemo(() => {
    const out: Record<string, Array<{ name: string; title: string }>> = {};
    for (const tag of Object.values(config.schema.dynamicTags)) {
      if (!tag.categories.some((c) => cats.includes(c))) continue;
      if (q && !tag.title.toLowerCase().includes(q.toLowerCase())) continue;
      (out[tag.group] ??= []).push({ name: tag.name, title: tag.title });
    }
    return out;
  }, [q, cats]);
  return (
    <>
      <button ref={ref} type="button" className="uncoder-ui-dynbtn" aria-label="Dynamic data" aria-expanded={open} data-tip="Dynamic data" onClick={() => setOpen((o) => !o)}>
        <Icon name="database" size={12} />
      </button>
      <Popover anchor={ref} open={open} onClose={() => setOpen(false)} width={250} placement="bottom-end" label="Dynamic data">
        <div className="uncoder-ui-dynpick">
          <input className="uncoder-ui-input" placeholder="Search dynamic tags" value={q} onChange={(e) => setQ(e.currentTarget.value)} data-autofocus aria-label="Search dynamic tags" />
          <div className="uncoder-ui-dynpick__list">
            {Object.entries(groups).map(([group, tags]) => (
              <div key={group}>
                <div className="uncoder-ui-dynpick__group">{config.schema.tagGroups[group] ?? group}</div>
                {tags.map((t) => (
                  <button
                    key={t.name}
                    type="button"
                    className="uncoder-ui-dynpick__item"
                    onClick={() => {
                      setDynamic(id, keyName, { tag: t.name, options: {} });
                      setOpen(false);
                    }}
                  >
                    {t.title}
                  </button>
                ))}
              </div>
            ))}
            {!Object.keys(groups).length && <div className="uncoder-ui-dynpick__none">No tags for this field.</div>}
          </div>
        </div>
      </Popover>
    </>
  );
}

function DynamicChip({ id, keyName, def }: { id: string; keyName: string; def: DynamicDef }) {
  const tag = config.schema.dynamicTags[def.tag];
  const ref = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const hasOptions = !!tag && (Object.keys(tag.controls ?? {}).length > 0 || true);
  return (
    <div className="uncoder-ui-dynchip">
      <button ref={ref} type="button" className="uncoder-ui-dynchip__main" onClick={() => hasOptions && setOpen((o) => !o)}>
        <Icon name="database" size={12} />
        <span>{tag?.title ?? def.tag}</span>
        <Icon name="settings-2" size={12} />
      </button>
      <IconButton icon="x" label="Remove dynamic data" size={12} onClick={() => setDynamic(id, keyName, null)} />
      <Popover anchor={ref} open={open} onClose={() => setOpen(false)} width={260} label="Dynamic tag settings">
        <div className="uncoder-ui-dynset">
          <ControlForm
            controls={{
              ...(tag?.controls ?? {}),
              before: { type: 'text', label: 'Before' },
              after: { type: 'text', label: 'After' },
              fallback: { type: 'text', label: 'Fallback' },
            }}
            values={{ ...(def.options ?? {}), before: def.before ?? '', after: def.after ?? '', fallback: def.fallback ?? '' }}
            onChange={(k, v) => {
              const next: DynamicDef = { ...def, options: { ...(def.options ?? {}) } };
              if (k === 'before' || k === 'after' || k === 'fallback') (next as any)[k] = v || undefined;
              else if (v === undefined) delete next.options![k];
              else next.options![k] = v;
              setDynamic(id, keyName, next);
            }}
          />
        </div>
      </Popover>
    </div>
  );
}
