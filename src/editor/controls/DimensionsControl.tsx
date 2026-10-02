import type { DimensionsValue } from '@shared/types';
import { NumberInput, UnitSelect } from '../ui/inputs';
import { Icon } from '../ui/Icon';
import type { ControlProps } from './ControlRow';
import { VarChip, varId } from '../ui/VarPicker';

const SIDES = ['top', 'right', 'bottom', 'left'] as const;
const LABEL: Record<string, string> = { top: 'Top', right: 'Right', bottom: 'Bottom', left: 'Left' };
const CORNER: Record<string, string> = { top: 'Top left', right: 'Top right', bottom: 'Bottom right', left: 'Bottom left' };

/** The edge (or corner) a field edits, drawn on a small box — so four bare numbers read at a glance. */
const EDGE: Record<string, string> = { top: 'M2.5 2.5h9', right: 'M11.5 2.5v9', bottom: 'M2.5 11.5h9', left: 'M2.5 2.5v9' };
const CORNER_PATH: Record<string, string> = {
  top: 'M2.5 7V5a2.5 2.5 0 0 1 2.5-2.5h2',
  right: 'M7 2.5h2A2.5 2.5 0 0 1 11.5 5v2',
  bottom: 'M11.5 7v2A2.5 2.5 0 0 1 9 11.5H7',
  left: 'M7 11.5H5A2.5 2.5 0 0 1 2.5 9V7',
};

function Glyph({ side, radius }: { side: (typeof SIDES)[number]; radius: boolean }) {
  return (
    <svg className="uncoder-ui-dims__glyph" width="14" height="14" viewBox="0 0 14 14" aria-hidden>
      <rect className="uncoder-ui-dims__glyph-box" x="2.5" y="2.5" width="9" height="9" rx={radius ? 2.5 : 1} />
      <path className="uncoder-ui-dims__glyph-edge" d={radius ? CORNER_PATH[side] : EDGE[side]} />
    </svg>
  );
}

/**
 * Margin, padding, radius… : one row of four fields (a glyph shows which side), with link and unit in the
 * label row. Linked (default) edits every side at once. Drag a glyph, press ↑/↓ or scroll to adjust.
 */
export function DimensionsControl({ control, value, placeholder, onChange }: ControlProps<DimensionsValue>) {
  const units = control.size_units ?? ['px', '%', 'em', 'rem'];
  const ph = (placeholder ?? {}) as Partial<DimensionsValue>;
  const v: DimensionsValue = value ?? { top: '', right: '', bottom: '', left: '', unit: ph.unit ?? units[0], linked: ph.linked ?? true };
  const linked = v.linked ?? true;
  const radius = /radius/i.test(control.label ?? '') || Object.values(control.selectors ?? {}).some((d) => d.includes('border-radius'));

  const set = (side: (typeof SIDES)[number], n: number | '') => {
    const next: DimensionsValue = { ...v };
    if (linked) for (const s of SIDES) next[s] = n;
    else next[side] = n;
    const empty = SIDES.every((s) => next[s] === '' || next[s] === undefined);
    onChange(empty ? undefined : next);
  };

  return (
    <div className={`uncoder-ui-dims${linked ? ' is-linked' : ''}`}>
      <div className="uncoder-ui-dims__tools">
        <button
          type="button"
          className={`uncoder-ui-dims__link${linked ? ' is-on' : ''}`}
          aria-pressed={linked}
          aria-label={linked ? 'Unlink sides' : 'Link sides'}
          data-tip={linked ? 'Linked: one value for every side' : 'Sides set separately'}
          onClick={() => onChange({ ...v, linked: !linked })}
        >
          <Icon name={linked ? 'link-2' : 'unlink-2'} size={13} />
        </button>
        <UnitSelect units={units} value={v.unit} onChange={(u) => onChange({ ...v, unit: u })} />
      </div>
      <div className="uncoder-ui-dims__row">
        {linked && varId(v.top) && SIDES.every((s) => v[s] === v.top) ? (
          <VarChip id={varId(v.top)!} onClear={() => onChange(undefined)} />
        ) : (
          SIDES.map((side) =>
            varId(v[side]) ? (
              <span key={side} className="uncoder-ui-dims__cell" data-tip={radius ? CORNER[side] : LABEL[side]}>
                <VarChip id={varId(v[side])!} onClear={() => set(side, '')} />
              </span>
            ) : (
          <label key={side} className="uncoder-ui-dims__cell" data-tip={radius ? CORNER[side] : LABEL[side]}>
            <NumberInput
              value={v[side] === '' || v[side] === 'auto' ? '' : Number(v[side])}
              placeholder={ph[side] !== undefined && ph[side] !== '' ? String(ph[side]) : ''}
              onChange={(n) => set(side, n)}
              ariaLabel={`${control.label} ${radius ? CORNER[side] : LABEL[side]}`}
              prefix={<Glyph side={side} radius={radius} />}
              scrub
            />
          </label>
            ),
          )
        )}
      </div>
    </div>
  );
}
