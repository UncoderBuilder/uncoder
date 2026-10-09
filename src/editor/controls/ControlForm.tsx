// Renders a set of controls against a plain values object (group fields, repeater rows, tag options, kit).
import type { ControlDef, Settings } from '@shared/types';
import { readValue, visible, writeKey } from '../lib/schema';
import { useUi } from '../store/ui';
import { Icon } from '../ui/Icon';
import { controlComponent, GROUP_TYPES, STACKED, STACKED_UI } from './registry';
import { HelpTip, rowLayout } from './ControlRow';

interface Props {
  controls: Record<string, ControlDef>;
  values: Settings;
  onChange: (key: string, value: any) => void;
  only?: string[];
  elementId?: string;
}

export function ControlForm({ controls, values, onChange, only, elementId = '' }: Props) {
  const device = useUi((s) => s.device);
  const keys = only ?? Object.keys(controls);
  const eff: Settings = {};
  for (const [k, c] of Object.entries(controls)) if (c.default !== undefined) eff[k] = c.default;
  Object.assign(eff, values);
  return (
    <div className="uncoder-ui-form">
      {keys.map((key) => {
        const control = controls[key];
        if (!control || !visible(control, eff, controls)) return null;
        if (control.type === 'heading') return <div key={key} className="uncoder-ui-ctl-heading">{control.label}</div>;
        if (control.type === 'notice' || control.type === 'divider') return null;
        const Comp = controlComponent(control);
        if (!Comp) return null;
        const read = readValue(values, key, control, device);
        const wk = writeKey(key, control, device);
        const isGroup = GROUP_TYPES.has(control.type);
        const stacked = STACKED.has(control.type) || STACKED_UI.has(control.ui ?? '') || control.type === 'textarea';
        const layout = rowLayout(control, stacked);
        return (
          <div key={key} className={`uncoder-ui-ctl uncoder-ui-ctl--t-${control.type} uncoder-ui-ctl--${layout}${read.own ? ' is-set' : ''}`}>
            {control.label !== undefined && (
              <div className="uncoder-ui-ctl__label">
                <span className="uncoder-ui-ctl__text" title={control.label}>
                  {control.label}
                </span>
                {read.own ? (
                  <button type="button" className="uncoder-ui-ctl__dot is-set" aria-label={`Reset ${control.label}`} data-tip="Set · click to reset" onClick={() => onChange(isGroup ? key : wk, undefined)} />
                ) : (
                  <span className="uncoder-ui-ctl__dot is-default" aria-hidden />
                )}
                {control.description && <HelpTip text={control.description} />}
                {control.responsive && control.type !== 'code' && device !== 'desktop' && (
                  <span className="uncoder-ui-devsw is-device is-static" aria-hidden>
                    <Icon name={device.startsWith('mobile') ? 'smartphone' : 'tablet'} size={12} />
                  </span>
                )}
              </div>
            )}
            <div className="uncoder-ui-ctl__input">
              <Comp
                control={control}
                value={read.own || isGroup ? read.value : undefined}
                placeholder={read.own ? undefined : read.value}
                onChange={(v: any) => onChange(isGroup ? key : wk, v)}
                device={device}
                id={elementId}
                keyName={key}
                settings={eff}
              />
            </div>
            {control.description && control.label === undefined && <p className="uncoder-ui-ctl__desc">{control.description}</p>}
          </div>
        );
      })}
    </div>
  );
}
