import { useRef, useState } from 'react';
import { ShapeSvg, SHAPES } from '../lib/shapes';
import { Icon } from '../ui/Icon';
import { Popover } from '../ui/Popover';
import type { ControlProps } from './ControlRow';

/** Visual picker for shape dividers (a select control with ui: "shape"). */
export function ShapeControl({ control, value, onChange }: ControlProps<string>) {
  const ref = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const current = value && SHAPES[value] ? value : '';
  const pick = (name: string) => {
    onChange(name || undefined);
    setOpen(false);
  };
  return (
    <>
      <button ref={ref} type="button" className={`uncoder-ui-selectbtn uncoder-ui-shapepick${current ? ' is-set' : ''}`} onClick={() => setOpen((o) => !o)} aria-expanded={open} aria-label={`${control.label}: ${current ? SHAPES[current].label : 'None'}`}>
        {current && (
          <span className="uncoder-ui-shapepick__thumb">
            <ShapeSvg name={current} />
          </span>
        )}
        <span className="uncoder-ui-selectbtn__label">{current ? SHAPES[current].label : 'None'}</span>
        <Icon name="chevron-down" size={12} />
      </button>
      <Popover anchor={ref} open={open} onClose={() => setOpen(false)} width={272} placement="left-start" label={control.label}>
        <div className="uncoder-ui-shapegrid" role="listbox" aria-label={control.label}>
          <button type="button" role="option" aria-selected={!current} className={`uncoder-ui-shapegrid__item uncoder-ui-shapegrid__item--none${!current ? ' is-active' : ''}`} onClick={() => pick('')} data-tip="None">
            <Icon name="ban" size={16} />
          </button>
          {Object.entries(SHAPES).map(([name, shape]) => (
            <button key={name} type="button" role="option" aria-selected={current === name} className={`uncoder-ui-shapegrid__item${current === name ? ' is-active' : ''}`} onClick={() => pick(name)} data-tip={shape.label} aria-label={shape.label}>
              <ShapeSvg name={name} />
            </button>
          ))}
        </div>
      </Popover>
    </>
  );
}
