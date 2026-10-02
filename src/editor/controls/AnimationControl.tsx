import { Fragment, useEffect, useRef, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { compile, easeFn, EASE_FAMILIES, isEase, states, DEFAULT_EASE, type AnimClip, type AnimDef, type AnimProp, type AnimState, type AnimStep, type AnimTarget, type AnimTrigger } from '@shared/anim';
import { elementFor, frame } from '../canvas/frame';
import { CLIP_LABELS, describe, fromPreset, PRESET_GROUPS, PRESETS, PROP_META, PROP_ORDER, SPLIT_TARGETS, TARGET_LABELS, titleOf, TRIGGERS, type AnimPreset } from '../lib/animPresets';
import { Icon } from '../ui/Icon';
import { NumberInput, TextInput } from '../ui/inputs';
import { Menu, Popover } from '../ui/Popover';
import { Button, IconButton, Segmented, Toggle } from '../ui/primitives';
import type { ControlProps } from './ControlRow';

const EASE_NAMES: Record<string, string> = { none: 'Linear', power1: 'Soft', power2: 'Smooth', power3: 'Strong', power4: 'Snappy', sine: 'Sine', expo: 'Expo', circ: 'Circular', back: 'Back', elastic: 'Elastic', bounce: 'Bounce' };
const DIR_NAMES: Record<string, string> = { in: 'in', out: 'out', inOut: 'in-out' };
const DEVICES = [
  { id: 'desktop', label: 'Desktop', icon: 'monitor' },
  { id: 'tablet', label: 'Tablet', icon: 'tablet' },
  { id: 'mobile', label: 'Mobile', icon: 'smartphone' },
];
const BLANK: AnimDef = { trigger: 'enter', from: { opacity: 0, y: 30 }, steps: [{ to: { opacity: 1, y: 0 }, duration: 800, ease: DEFAULT_EASE }] };

/** Plays a definition on the element in the canvas (the front-end engine's preview hook). */
function previewOnCanvas(id: string, def: AnimDef) {
  const el = elementFor(id);
  const engine = frame.win?.UncoderWB?.animate as { preview?: (el: HTMLElement, def: AnimDef) => void } | undefined;
  if (el && engine?.preview) engine.preview(el, def);
}

export function AnimationControl({ control, value, onChange, id }: ControlProps<AnimDef[]>) {
  const list: AnimDef[] = Array.isArray(value) ? value : [];
  const targets: AnimTarget[] = control.targets?.length ? control.targets : ['self', 'children'];
  const [open, setOpen] = useState<number | null>(list.length === 1 ? 0 : null);
  const [gallery, setGallery] = useState(false);

  const commit = (next: AnimDef[]) => onChange(next.length ? next : undefined);
  const update = (i: number, def: AnimDef) => commit(list.map((d, j) => (j === i ? def : d)));
  const remove = (i: number) => {
    commit(list.filter((_, j) => j !== i));
    setOpen(null);
  };
  const duplicate = (i: number) => {
    const next = [...list];
    next.splice(i + 1, 0, structuredClone(list[i]));
    commit(next);
    setOpen(i + 1);
  };

  return (
    <div className="uncoder-ui-anim">
      {list.length === 0 ? (
        <button type="button" className="uncoder-ui-anim__empty" onClick={() => setGallery(true)}>
          <span className="uncoder-ui-anim__empty-art" aria-hidden>
            <i />
            <i />
            <i />
          </span>
          <strong>Add an animation</strong>
          <span>Entrances, text reveals, scroll scrubbing, hover and loops, with 50+ presets to start from.</span>
        </button>
      ) : (
        list.map((def, i) => (
          <AnimCard
            key={i}
            def={def}
            open={open === i}
            targets={targets}
            onToggle={() => setOpen(open === i ? null : i)}
            onChange={(d) => update(i, d)}
            onPreview={() => previewOnCanvas(id, def)}
            onDuplicate={() => duplicate(i)}
            onRemove={() => remove(i)}
          />
        ))
      )}
      {list.length > 0 && (
        <Button size="sm" icon="plus" className="uncoder-ui-anim__add" onClick={() => setGallery(true)}>
          Add animation
        </Button>
      )}
      {gallery && (
        <AnimGallery
          targets={targets}
          onClose={() => setGallery(false)}
          onPick={(def) => {
            commit([...list, def]);
            setOpen(list.length);
            setGallery(false);
            // Once the canvas has the element's new markup, show what was picked.
            window.setTimeout(() => previewOnCanvas(id, def), 300);
          }}
        />
      )}
    </div>
  );
}

// ------------------------------------------------------------------ One animation

function AnimCard({ def, open, targets, onToggle, onChange, onPreview, onDuplicate, onRemove }: { def: AnimDef; open: boolean; targets: AnimTarget[]; onToggle: () => void; onChange: (d: AnimDef) => void; onPreview: () => void; onDuplicate: () => void; onRemove: () => void }) {
  const trigger = TRIGGERS.find((t) => t.value === def.trigger) ?? TRIGGERS[0];
  return (
    <div className={`uncoder-ui-anim__card${open ? ' is-open' : ''}`}>
      <div className="uncoder-ui-anim__head">
        <button type="button" className="uncoder-ui-anim__title" aria-expanded={open} onClick={onToggle}>
          <span className="uncoder-ui-anim__badge" aria-hidden>
            <Icon name={trigger.icon} size={13} />
          </span>
          <span className="uncoder-ui-anim__names">
            <strong>{titleOf(def)}</strong>
            <small>{describe(def)}</small>
          </span>
        </button>
        <IconButton icon="play" label="Preview on the canvas" size={13} tone="accent" onClick={onPreview} />
        <IconButton icon="copy" label="Duplicate animation" size={12} onClick={onDuplicate} />
        <IconButton icon="trash-2" label="Delete animation" size={12} tone="danger" onClick={onRemove} />
      </div>
      {open && <AnimEditor def={def} targets={targets} onChange={onChange} />}
    </div>
  );
}

function Row({ label, children, hint }: { label: string; children: ReactNode; hint?: string }) {
  return (
    <div className="uncoder-ui-anim__row">
      <span className="uncoder-ui-anim__label" data-tip={hint}>
        {label}
      </span>
      <div className="uncoder-ui-anim__field">{children}</div>
    </div>
  );
}

const ms = (v: number | '') => (v === '' ? 0 : Math.max(0, Math.round(v)));

function AnimEditor({ def, targets, onChange }: { def: AnimDef; targets: AnimTarget[]; onChange: (d: AnimDef) => void }) {
  // Editing a preset's values keeps its name only while the steps still match it.
  const set = (patch: Partial<AnimDef>) => {
    const next = { ...def, ...patch };
    for (const k of Object.keys(patch) as Array<keyof AnimDef>) if (patch[k] === undefined) delete next[k];
    onChange(next);
  };
  const trigger = TRIGGERS.find((t) => t.value === def.trigger) ?? TRIGGERS[0];
  const target = def.target ?? 'self';
  const split = SPLIT_TARGETS.includes(target);
  const sequence = def.trigger === 'load' || def.trigger === 'enter' || def.trigger === 'loop';
  const stateList = states(def);

  const setStep = (i: number, step: AnimStep) => set({ steps: def.steps.map((s, j) => (j === i ? step : s)) });
  const addStep = () => {
    const last = def.steps[def.steps.length - 1];
    set({ steps: [...def.steps, { to: {}, duration: last?.duration ?? 600, ease: last?.ease ?? DEFAULT_EASE }] });
  };

  return (
    <div className="uncoder-ui-anim__body">
      <div className="uncoder-ui-anim__group">
        <Segmented
          ariaLabel="Trigger"
          options={TRIGGERS.map((t) => ({ value: t.value, label: t.label, icon: t.icon }))}
          value={def.trigger}
          onChange={(v) => set({ trigger: v as AnimTrigger })}
        />
        <p className="uncoder-ui-anim__hint">
          <strong>{trigger.label}.</strong> {trigger.hint}
        </p>
      </div>

      {targets.length > 1 && (
        <Row label="Animate">
          <select className="uncoder-ui-select" value={target} aria-label="What to animate" onChange={(e) => set({ target: e.currentTarget.value === 'self' ? undefined : (e.currentTarget.value as AnimTarget) })}>
            {targets.map((t) => (
              <option key={t} value={t}>
                {TARGET_LABELS[t]}
              </option>
            ))}
          </select>
        </Row>
      )}
      {split && (
        <Row label="Mask" hint="Each word slides out from behind its own edge (pair with Move Y 120%).">
          <Toggle checked={!!def.mask} onChange={(v) => set({ mask: v || undefined })} label="Mask" />
        </Row>
      )}

      {def.trigger === 'enter' && (
        <>
          <Row label="Replay">
            <select className="uncoder-ui-select" value={def.replay ?? 'once'} aria-label="Replay" onChange={(e) => set({ replay: e.currentTarget.value === 'once' ? undefined : (e.currentTarget.value as AnimDef['replay']) })}>
              <option value="once">Once</option>
              <option value="every">Every time it comes into view</option>
              <option value="reverse">Play back when it leaves</option>
            </select>
          </Row>
          <Row label="Starts at" hint="How far above the bottom of the screen the element must be (% of the screen height).">
            <NumberInput value={def.offset ?? 10} min={0} max={50} suffix="%" ariaLabel="Start offset" onChange={(v) => set({ offset: v === '' || v === 10 ? undefined : v })} />
          </Row>
        </>
      )}
      {def.trigger === 'scroll' && (
        <>
          <Row label="Range" hint="0% = the element's top meets the bottom of the screen, 100% = its bottom passes the top.">
            <div className="uncoder-ui-anim__pair">
              <NumberInput value={def.start ?? 0} min={0} max={99} suffix="%" ariaLabel="Range start" onChange={(v) => set({ start: v === '' ? 0 : v })} />
              <span aria-hidden>→</span>
              <NumberInput value={def.end ?? 100} min={1} max={100} suffix="%" ariaLabel="Range end" onChange={(v) => set({ end: v === '' ? 100 : v })} />
            </div>
          </Row>
          <Row label="Catch-up" hint="How softly it follows the scrollbar (0 = locked to it).">
            <NumberInput value={def.smooth ?? 0} min={0} max={10} step={0.1} suffix="s" ariaLabel="Catch-up time" onChange={(v) => set({ smooth: v === '' || v === 0 ? undefined : v })} />
          </Row>
        </>
      )}
      {def.trigger === 'click' && (
        <Row label="Toggle" hint="A second click plays it back.">
          <Toggle checked={!!def.toggle} onChange={(v) => set({ toggle: v || undefined })} label="Toggle" />
        </Row>
      )}

      {def.trigger !== 'scroll' && (
        <Row label="Delay">
          <NumberInput value={def.delay ?? 0} min={0} max={20000} step={50} suffix="ms" ariaLabel="Delay" onChange={(v) => set({ delay: ms(v) || undefined })} />
        </Row>
      )}
      {target !== 'self' && (
        <Row label="Stagger" hint="Time between one item and the next.">
          <div className="uncoder-ui-anim__pair">
            <NumberInput value={def.stagger ?? 0} min={0} max={5000} step={10} suffix="ms" ariaLabel="Stagger" onChange={(v) => set({ stagger: ms(v) || undefined })} />
            <select className="uncoder-ui-select" value={def.order ?? 'start'} aria-label="Stagger order" onChange={(e) => set({ order: e.currentTarget.value === 'start' ? undefined : (e.currentTarget.value as AnimDef['order']) })}>
              <option value="start">First to last</option>
              <option value="end">Last to first</option>
              <option value="center">From the center</option>
              <option value="edges">From the edges</option>
              <option value="random">Random</option>
            </select>
          </div>
        </Row>
      )}
      {sequence && (
        <Row label={def.trigger === 'loop' ? 'Yoyo' : 'Repeat'} hint={def.trigger === 'loop' ? 'Plays forward, then backward.' : 'Extra plays after the first; yoyo plays every other one backward.'}>
          <div className="uncoder-ui-anim__pair">
            {def.trigger !== 'loop' && (
              <NumberInput value={(def.repeat ?? 0) < 0 ? '' : def.repeat ?? 0} placeholder="∞" min={0} max={100} ariaLabel="Repeat" onChange={(v) => set({ repeat: v === '' ? -1 : v || undefined })} />
            )}
            <label className="uncoder-ui-anim__check">
              <Toggle checked={!!def.yoyo} onChange={(v) => set({ yoyo: v || undefined })} label="Yoyo" />
              {def.trigger !== 'loop' && <span>Yoyo</span>}
            </label>
          </div>
        </Row>
      )}

      <Timeline def={def} />

      <div className="uncoder-ui-anim__steps">
        <StateBlock title="Start" hint="Where it starts. Leave empty to start as styled." state={def.from ?? {}} previous={{}} onChange={(from) => set({ from: Object.keys(from).length ? from : undefined })} />
        {def.steps.map((step, i) => (
          <StepBlock key={i} index={i} step={step} previous={stateList[i]} canRemove={def.steps.length > 1} onChange={(s) => setStep(i, s)} onRemove={() => set({ steps: def.steps.filter((_, j) => j !== i) })} />
        ))}
        <Button size="sm" icon="plus" onClick={addStep} disabled={def.steps.length >= 12}>
          Add step
        </Button>
      </div>

      <Row label="Runs on">
        <div className="uncoder-ui-anim__devices">
          {DEVICES.map((d) => {
            const on = !def.devices || def.devices.includes(d.id);
            return (
              <IconButton
                key={d.id}
                icon={d.icon}
                label={`${on ? 'Runs' : 'Off'} on ${d.label.toLowerCase()}`}
                size={13}
                active={on}
                onClick={() => {
                  const cur = def.devices ?? DEVICES.map((x) => x.id);
                  const next = on ? cur.filter((x) => x !== d.id) : [...cur, d.id];
                  set({ devices: next.length === DEVICES.length ? undefined : next.length ? DEVICES.map((x) => x.id).filter((x) => next.includes(x)) : cur });
                }}
              />
            );
          })}
        </div>
      </Row>
    </div>
  );
}

/** Proportional bar of the timeline: holds, steps and the stagger tail. */
function Timeline({ def }: { def: AnimDef }) {
  const parts: Array<{ ms: number; kind: 'hold' | 'step'; label: string }> = [];
  if (def.delay && def.trigger !== 'scroll') parts.push({ ms: def.delay, kind: 'hold', label: `Delay ${def.delay}ms` });
  def.steps.forEach((s, i) => {
    if (s.delay) parts.push({ ms: s.delay, kind: 'hold', label: `Hold ${s.delay}ms` });
    parts.push({ ms: Math.max(s.duration, 40), kind: 'step', label: `Step ${i + 1}: ${s.duration}ms` });
  });
  const total = parts.reduce((t, p) => t + p.ms, 0) || 1;
  return (
    <div className="uncoder-ui-anim__timeline" aria-hidden>
      {parts.map((p, i) => (
        <span key={i} className={`uncoder-ui-anim__seg uncoder-ui-anim__seg--${p.kind}`} style={{ flexGrow: p.ms / total }} data-tip={p.label}>
          {p.kind === 'step' && p.ms / total > 0.14 ? p.label.split(':')[0] : ''}
        </span>
      ))}
    </div>
  );
}

function StepBlock({ index, step, previous, canRemove, onChange, onRemove }: { index: number; step: AnimStep; previous: AnimState; canRemove: boolean; onChange: (s: AnimStep) => void; onRemove: () => void }) {
  return (
    <StateBlock
      title={`Step ${index + 1}`}
      state={step.to ?? {}}
      previous={previous}
      onChange={(to) => onChange({ ...step, to })}
      tools={canRemove ? <IconButton icon="x" label={`Remove step ${index + 1}`} size={12} onClick={onRemove} /> : null}
    >
      <div className="uncoder-ui-anim__timing">
        <NumberInput value={step.duration} min={0} max={20000} step={50} suffix="ms" ariaLabel="Duration" onChange={(v) => onChange({ ...step, duration: ms(v) })} />
        <EasePicker value={step.ease ?? DEFAULT_EASE} onChange={(ease) => onChange({ ...step, ease })} />
      </div>
      <label className="uncoder-ui-anim__hold">
        <span>Hold first</span>
        <NumberInput value={step.delay ?? 0} min={0} max={20000} step={50} suffix="ms" ariaLabel="Hold before this step" onChange={(v) => onChange({ ...step, delay: ms(v) || undefined })} />
      </label>
    </StateBlock>
  );
}

/** Sensible value for a property that was just added. */
function suggest(prop: AnimProp, previous: AnimState, isStart: boolean): number | string {
  if (prop === 'clip') return isStart || previous.clip === undefined || previous.clip === 'none' ? 'wipe-up' : 'none';
  const meta = PROP_META[prop];
  const natural = prop === 'opacity' || prop.startsWith('scale') ? 1 : 0;
  // A step brings back what the start moved; otherwise it moves by a noticeable amount.
  if (!isStart && previous[prop] !== undefined && previous[prop] !== natural) return natural;
  return meta.nudge;
}

function StateBlock({ title, hint, state, previous, onChange, tools, children }: { title: string; hint?: string; state: AnimState; previous: AnimState; onChange: (s: AnimState) => void; tools?: ReactNode; children?: ReactNode }) {
  const addRef = useRef<HTMLButtonElement>(null);
  const [menu, setMenu] = useState(false);
  const isStart = title === 'Start';
  const keys = PROP_ORDER.filter((p) => state[p] !== undefined);
  const setProp = (p: AnimProp, v: number | string | undefined) => {
    const next = { ...state } as Record<string, unknown>;
    if (v === undefined) delete next[p];
    else next[p] = v;
    onChange(next as AnimState);
  };
  return (
    <div className="uncoder-ui-anim__block">
      <div className="uncoder-ui-anim__block-head">
        <span className="uncoder-ui-anim__dot" aria-hidden />
        <strong>{title}</strong>
        {tools}
      </div>
      {children}
      {keys.length === 0 && hint && <p className="uncoder-ui-anim__hint">{hint}</p>}
      {keys.map((p) => (
        <PropRow key={p} prop={p} value={state[p]!} onChange={(v) => setProp(p, v)} onRemove={() => setProp(p, undefined)} />
      ))}
      <button ref={addRef} type="button" className="uncoder-ui-anim__addprop" onClick={() => setMenu(true)} disabled={keys.length === PROP_ORDER.length}>
        <Icon name="plus" size={12} /> Property
      </button>
      <Menu
        anchor={addRef}
        open={menu}
        onClose={() => setMenu(false)}
        width={200}
        items={PROP_ORDER.filter((p) => state[p] === undefined).map((p) => ({
          label: p === 'clip' ? 'Clip reveal' : PROP_META[p].label,
          onSelect: () => setProp(p, suggest(p, previous, isStart)),
        }))}
      />
    </div>
  );
}

const LENGTH_UNITS = ['px', '%', 'em', 'vw', 'vh'];

function PropRow({ prop, value, onChange, onRemove }: { prop: AnimProp; value: number | string; onChange: (v: number | string) => void; onRemove: () => void }) {
  let field: ReactNode;
  if (prop === 'clip') {
    field = (
      <select className="uncoder-ui-select" value={String(value)} aria-label="Clip" onChange={(e) => onChange(e.currentTarget.value as AnimClip)}>
        {Object.entries(CLIP_LABELS).map(([k, label]) => (
          <option key={k} value={k}>
            {label}
          </option>
        ))}
      </select>
    );
  } else {
    const meta = PROP_META[prop];
    if (prop === 'x' || prop === 'y') {
      const m = typeof value === 'string' ? /^(-?[\d.]+)([a-z%]+)$/.exec(value) : null;
      const n = m ? parseFloat(m[1]) : Number(value) || 0;
      const unit = m ? m[2] : 'px';
      const emit = (num: number, u: string) => onChange(u === 'px' ? num : `${num}${u}`);
      field = (
        <div className="uncoder-ui-anim__len">
          <NumberInput value={n} min={unit === 'px' ? meta.min : -1000} max={unit === 'px' ? meta.max : 1000} ariaLabel={meta.label} onChange={(v) => emit(v === '' ? 0 : v, unit)} />
          <select className="uncoder-ui-anim__unit" value={unit} aria-label={`${meta.label} unit`} onChange={(e) => emit(n, e.currentTarget.value)}>
            {LENGTH_UNITS.map((u) => (
              <option key={u} value={u}>
                {u}
              </option>
            ))}
          </select>
        </div>
      );
    } else {
      field = <NumberInput value={Number(value)} min={meta.min} max={meta.max} step={meta.step} suffix={meta.unit} ariaLabel={meta.label} onChange={(v) => onChange(v === '' ? 0 : v)} />;
    }
  }
  return (
    <div className="uncoder-ui-anim__prop">
      <span className="uncoder-ui-anim__prop-name">{prop === 'clip' ? 'Clip' : PROP_META[prop].label}</span>
      <div className="uncoder-ui-anim__prop-field">{field}</div>
      <IconButton icon="x" label={`Remove ${prop}`} size={11} onClick={onRemove} />
    </div>
  );
}

// ------------------------------------------------------------------ Easing

/** The easing curve, drawn with room for overshoot (back, elastic). */
export function EaseCurve({ ease, size = 40, className }: { ease: string; size?: number; className?: string }) {
  const f = easeFn(ease);
  const pts: string[] = [];
  for (let i = 0; i <= 48; i++) {
    const t = i / 48;
    // y range −0.35 … 1.35 maps onto the box.
    const y = 1 - (f(t) + 0.35) / 1.7;
    pts.push(`${(t * 100).toFixed(1)},${(y * 100).toFixed(1)}`);
  }
  return (
    <svg className={className ?? 'uncoder-ui-ease-curve'} width={size} height={size} viewBox="-6 -6 112 112" aria-hidden>
      <line x1="0" y1="79.4" x2="100" y2="79.4" className="uncoder-ui-ease-curve__base" />
      <line x1="0" y1="20.6" x2="100" y2="20.6" className="uncoder-ui-ease-curve__base" />
      <polyline points={pts.join(' ')} className="uncoder-ui-ease-curve__line" />
    </svg>
  );
}

function easeLabel(ease: string): string {
  if (ease === 'none') return 'Linear';
  if (ease.startsWith('cubic-bezier')) return 'Custom curve';
  const [family, dir = 'out'] = ease.split('.');
  return `${EASE_NAMES[family] ?? family} ${DIR_NAMES[dir] ?? dir}`;
}

function EasePicker({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const ref = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const [family, dirRaw] = value.startsWith('cubic-bezier') ? ['custom', 'out'] : value.split('.');
  const [dir, setDir] = useState(dirRaw || 'out');
  const [custom, setCustom] = useState(value.startsWith('cubic-bezier') ? value : '');
  return (
    <>
      <button ref={ref} type="button" className="uncoder-ui-anim__ease" onClick={() => setOpen(true)} aria-label={`Easing: ${easeLabel(value)}`}>
        <EaseCurve ease={value} size={20} />
        <span>{easeLabel(value)}</span>
        <Icon name="chevron-down" size={12} />
      </button>
      <Popover anchor={ref} open={open} onClose={() => setOpen(false)} width={312} label="Easing">
        <div className="uncoder-ui-easepick">
          <Segmented
            size="sm"
            ariaLabel="Direction"
            options={[
              { value: 'in', label: 'Ease in' },
              { value: 'out', label: 'Ease out' },
              { value: 'inOut', label: 'In-out' },
            ]}
            value={dir}
            onChange={(d) => {
              setDir(d);
              if (family !== 'none' && family !== 'custom') onChange(`${family}.${d}`);
            }}
          />
          <div className="uncoder-ui-easepick__grid">
            {EASE_FAMILIES.map((f) => {
              const name = f === 'none' ? 'none' : `${f}.${dir}`;
              return (
                <button key={f} type="button" className={`uncoder-ui-easepick__item${name === value ? ' is-active' : ''}`} data-tip={name} onClick={() => onChange(name)}>
                  <EaseCurve ease={name} size={46} />
                  <span>{EASE_NAMES[f]}</span>
                </button>
              );
            })}
          </div>
          <label className="uncoder-ui-easepick__custom">
            <span>Custom</span>
            <TextInput
              value={custom}
              placeholder="cubic-bezier(.2, .8, .2, 1)"
              onChange={setCustom}
              onCommit={(v) => {
                const clean = v.trim().replace(/\s+/g, '');
                if (isEase(clean.replace(/,/g, ', '))) onChange(clean.replace(/,/g, ', '));
              }}
            />
          </label>
        </div>
      </Popover>
    </>
  );
}

// ------------------------------------------------------------------ Preset gallery

const supportsLinear = typeof CSS !== 'undefined' && !!CSS.supports?.('animation-timing-function', 'linear(0, 1)');

/** Smaller distances for the thumbnails (a 120px stage, not a page section). */
function thumbDef(def: AnimDef): AnimDef {
  const shrink = (s: AnimState | undefined): AnimState | undefined => {
    if (!s) return s;
    const out = { ...s };
    for (const k of ['x', 'y'] as const) if (typeof out[k] === 'number') out[k] = Math.round((out[k] as number) * 0.4);
    return out;
  };
  return { ...def, from: shrink(def.from), steps: def.steps.map((st) => ({ ...st, to: shrink(st.to) ?? {} })) };
}

function Demo({ def }: { def: AnimDef }) {
  const target = def.target ?? 'self';
  const wrap = (node: ReactNode, key: number | string) =>
    def.mask ? (
      <span key={key} className="uncoder-ui-animlib__mask">
        {node}
      </span>
    ) : (
      <Fragment key={key}>{node}</Fragment>
    );
  if (target === 'children')
    return (
      <span className="uncoder-ui-animlib__row">
        {[0, 1, 2, 3].map((i) => (
          <span key={i} className="uncoder-ui-animlib__chip" data-unit />
        ))}
      </span>
    );
  if (target === 'words')
    return (
      <span className="uncoder-ui-animlib__text">
        {['Make', 'it', 'move'].map((w, i) =>
          wrap(
            <span className="uncoder-ui-animlib__word" data-unit>
              {w}
            </span>,
            i,
          ),
        )}
      </span>
    );
  if (target === 'chars')
    return (
      <span className="uncoder-ui-animlib__text uncoder-ui-animlib__text--chars">
        {Array.from('Motion').map((c, i) => (
          <span key={i} className="uncoder-ui-animlib__word" data-unit>
            {c}
          </span>
        ))}
      </span>
    );
  if (target === 'lines')
    return (
      <span className="uncoder-ui-animlib__text uncoder-ui-animlib__text--lines">
        {['Lines rise', 'one by one'].map((l, i) =>
          wrap(
            <span className="uncoder-ui-animlib__line" data-unit>
              {l}
            </span>,
            i,
          ),
        )}
      </span>
    );
  return <span className="uncoder-ui-animlib__box" data-unit />;
}

function PresetTile({ preset, disabled, autoplay, onPick }: { preset: AnimPreset; disabled: boolean; autoplay: number; onPick: () => void }) {
  const stage = useRef<HTMLSpanElement>(null);
  const anims = useRef<Animation[]>([]);
  const stop = () => {
    anims.current.forEach((a) => a.cancel());
    anims.current = [];
  };
  const play = () => {
    stop();
    const root = stage.current;
    if (!root || typeof root.animate !== 'function') return;
    const units = Array.from(root.querySelectorAll<HTMLElement>('[data-unit]'));
    const def = thumbDef(preset.def);
    const scrub = def.trigger === 'scroll';
    const interactive = def.trigger === 'hover' || def.trigger === 'click';
    const plain: AnimDef = scrub || interactive ? { ...def, repeat: 0, yoyo: false } : def.trigger === 'loop' ? { ...def, trigger: 'enter', repeat: def.yoyo ? 3 : 1 } : def;
    const c = compile(plain, units.length, { linear: supportsLinear });
    const back = scrub || (interactive && !c.endsNatural);
    units.forEach((el, i) => {
      const a = el.animate(c.keyframes[i], {
        duration: scrub ? 1400 : c.duration,
        iterations: back ? 2 : c.iterations,
        direction: back ? 'alternate' : c.direction,
        easing: scrub ? 'ease-in-out' : 'linear',
        endDelay: back ? 0 : 400,
        fill: 'both',
      });
      anims.current.push(a);
    });
    Promise.all(anims.current.map((a) => a.finished)).then(stop, () => {});
  };
  useEffect(() => {
    if (autoplay < 0) return;
    const t = window.setTimeout(play, autoplay);
    return () => {
      window.clearTimeout(t);
      stop();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [autoplay, preset.id]);
  const trigger = TRIGGERS.find((t) => t.value === preset.def.trigger);
  return (
    <button type="button" className="uncoder-ui-animlib__tile" disabled={disabled} onPointerEnter={play} onFocus={play} onClick={onPick} data-tip={disabled ? 'Needs a text widget (heading, text…)' : undefined}>
      <span className="uncoder-ui-animlib__stage" ref={stage}>
        <Demo def={preset.def} />
      </span>
      <span className="uncoder-ui-animlib__name">{preset.label}</span>
      <span className="uncoder-ui-animlib__meta">
        {trigger && <Icon name={trigger.icon} size={11} />}
        {trigger?.label}
      </span>
    </button>
  );
}

function AnimGallery({ targets, onPick, onClose }: { targets: AnimTarget[]; onPick: (def: AnimDef) => void; onClose: () => void }) {
  const [group, setGroup] = useState(PRESET_GROUPS[0]?.id ?? 'entrance');
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);
  const list = PRESETS.filter((p) => p.group === group);
  return createPortal(
    <div className="uncoder-ui-scrim" onPointerDown={(e) => e.target === e.currentTarget && onClose()}>
      <div className="uncoder-ui-dialog uncoder-ui-animlib" role="dialog" aria-modal="true" aria-labelledby="uncoder-ui-animlib-title">
        <div className="uncoder-ui-animlib__head">
          <div>
            <h2 id="uncoder-ui-animlib-title">Animation presets</h2>
            <p>Hover a preset to watch it. Every value can be changed after you add it.</p>
          </div>
          <IconButton icon="x" label="Close" onClick={onClose} />
        </div>
        <div className="uncoder-ui-animlib__tabs" role="tablist" aria-label="Preset groups">
          {PRESET_GROUPS.map((g) => (
            <button key={g.id} type="button" role="tab" aria-selected={g.id === group} className={`uncoder-ui-iconlib__tab${g.id === group ? ' is-active' : ''}`} onClick={() => setGroup(g.id)}>
              {g.label}
              <span className="uncoder-ui-animlib__count">{PRESETS.filter((p) => p.group === g.id).length}</span>
            </button>
          ))}
        </div>
        <div className="uncoder-ui-animlib__grid" key={group}>
          {list.map((p, i) => {
            const needs = !!p.def.target && !targets.includes(p.def.target);
            return <PresetTile key={p.id} preset={p} disabled={needs} autoplay={needs ? -1 : 150 + i * 90} onPick={() => onPick(fromPreset(p))} />;
          })}
        </div>
        <div className="uncoder-ui-animlib__foot">
          <span>Plays with the browser's own animation engine: no library, nothing extra to download.</span>
          <Button size="sm" icon="plus" onClick={() => onPick(structuredClone(BLANK))}>
            Start from scratch
          </Button>
        </div>
      </div>
    </div>,
    document.querySelector('.uncoder-ui-portal') ?? document.body,
  );
}
