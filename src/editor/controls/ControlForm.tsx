// Renders a set of controls against a plain values object (group fields, repeater rows, tag options, kit).
import type { ControlDef, Settings } from '@shared/types';
import { readValue, visible, writeKey } from '../lib/schema';
import { useUi } from '../store/ui';
import { Icon } from '../ui/Icon';
import { controlComponent, GROUP_TYPES, STACKED } from './registry';

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
        const stacked = STACKED.has(control.type) || control.type === 'textarea';
        return (
          <div key={key} className={`uncoder-ui-ctl uncoder-ui-ctl--t-${control.type}${stacked ? ' uncoder-ui-ctl--stacked' : ''}`}>
            {control.label !== undefined && (
              <div className="uncoder-ui-ctl__label">
                <span className={`uncoder-ui-ctl__text${read.own ? ' is-set' : ''}`}>{control.label}</span>
                {control.responsive && control.type !== 'code' && device !== 'desktop' && (
                  <span className="uncoder-ui-devsw is-device is-static" aria-hidden>
                    <Icon name={device.startsWith('mobile') ? 'smartphone' : 'tablet'} size={12} />
                  </span>
                )}
                {!isGroup && read.own && (
                  <button type="button" className="uncoder-ui-ctl__reset" aria-label={`Reset ${control.label}`} onClick={() => onChange(wk, undefined)}>
                    <Icon name="rotate-ccw" size={11} />
                  </button>
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
            {control.description && <p className="uncoder-ui-ctl__desc">{control.description}</p>}
          </div>
        );
      })}
    </div>
  );
}
