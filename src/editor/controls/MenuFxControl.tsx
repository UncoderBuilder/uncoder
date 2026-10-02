import { useRef, type KeyboardEvent } from 'react';
import { optionList } from './basic';
import type { ControlProps } from './ControlRow';

/** What each effect does, for the tile's tooltip. */
const HINTS: Record<string, string> = {
  underline: 'A line draws in under the item and leaves the other way',
  flip: 'The label rolls up and a copy rolls in',
  magnet: 'The item becomes a pill that leans towards the pointer',
  focus: 'The other items fade back',
  highlight: 'One pill glides from item to item and rests on the current page',
  none: 'Only the text color changes',
};

/**
 * Visual picker for the menu hover effect (a select control with ui: "menu_fx"): one tile per effect with a
 * small menu that shows it, played on hover or keyboard focus. A radio group: arrow keys move and pick.
 */
export function MenuFxControl({ control, value, placeholder, onChange }: ControlProps<string>) {
  const options = optionList(control.options).filter((o) => o.value !== '');
  const current = value ?? (placeholder as string) ?? options[0]?.value ?? '';
  const refs = useRef<Array<HTMLButtonElement | null>>([]);

  const onKeyDown = (e: KeyboardEvent<HTMLDivElement>) => {
    const step = { ArrowRight: 1, ArrowDown: 2, ArrowLeft: -1, ArrowUp: -2 }[e.key];
    if (!step) return;
    e.preventDefault();
    const index = Math.max(0, options.findIndex((o) => o.value === current));
    const next = (index + step + options.length) % options.length;
    onChange(options[next].value);
    refs.current[next]?.focus();
  };

  return (
    <div className="uncoder-ui-mfx" role="radiogroup" aria-label={control.label} onKeyDown={onKeyDown}>
      {options.map((o, i) => {
        const on = o.value === current;
        return (
          <button
            key={o.value}
            ref={(node) => {
              refs.current[i] = node;
            }}
            type="button"
            role="radio"
            aria-checked={on}
            tabIndex={on ? 0 : -1}
            className={`uncoder-ui-mfx__tile uncoder-ui-mfx--${o.value}${on ? ' is-active' : ''}`}
            data-tip={HINTS[o.value]}
            onClick={() => onChange(o.value)}
          >
            <span className="uncoder-ui-mfx__demo" aria-hidden>
              {o.value === 'highlight' && <span className="uncoder-ui-mfx__glide" />}
              <span className="uncoder-ui-mfx__i">Home</span>
              <span className="uncoder-ui-mfx__i is-hot">
                <span className="uncoder-ui-mfx__t">Work</span>
              </span>
              <span className="uncoder-ui-mfx__i">Shop</span>
            </span>
            <span className="uncoder-ui-mfx__label">{o.label}</span>
          </button>
        );
      })}
    </div>
  );
}
