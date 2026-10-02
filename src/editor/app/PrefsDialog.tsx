import { createPortal } from 'react-dom';
import { useUi } from '../store/ui';
import { setPref, usePrefs } from '../store/prefs';
import { Icon } from '../ui/Icon';
import { Button, Segmented, Toggle } from '../ui/primitives';
import { MOD } from './shortcuts';

/** Editor → Preferences: how the editor behaves for this user (saved to their account, theme per browser). */
export function PrefsDialog() {
  const open = useUi((s) => s.prefsOpen);
  const theme = useUi((s) => s.theme);
  const layout = useUi((s) => s.layout);
  const prefs = usePrefs();
  if (!open) return null;
  const close = () => useUi.setState({ prefsOpen: false });

  const rows: Array<{ key: 'autoPanels' | 'handles' | 'hints'; title: string; text: string }> = [
    { key: 'autoPanels', title: 'Panel follows the selection', text: 'Selecting an element opens Layers; selecting an empty container opens Insert.' },
    { key: 'handles', title: 'Spacing handles on the canvas', text: 'Drag padding, margin, gap and column width directly on the selected element.' },
    { key: 'hints', title: 'Tooltips on hover', text: 'Names and shortcuts of buttons. Keyboard focus and ⓘ icons always show them.' },
  ];

  return createPortal(
    <div className="uncoder-ui-scrim" onPointerDown={(e) => e.target === e.currentTarget && close()}>
      <div className="uncoder-ui-dialog uncoder-ui-prefs" role="dialog" aria-modal="true" aria-labelledby="uncoder-ui-prefs-title" onKeyDown={(e) => e.key === 'Escape' && close()}>
        <div className="uncoder-ui-dialog__head">
          <Icon name="sliders-horizontal" size={16} />
          <h2 id="uncoder-ui-prefs-title">Editor preferences</h2>
        </div>
        <div className="uncoder-ui-prefs__row">
          <div>
            <strong>Interface</strong>
            <p>Saved in this browser.</p>
          </div>
          <Segmented
            ariaLabel="Interface theme"
            options={[
              { value: 'light', label: 'Paper' },
              { value: 'dark', label: 'Petrol night' },
            ]}
            value={theme}
            onChange={(v) => useUi.setState({ theme: v === 'dark' ? 'dark' : 'light' })}
          />
        </div>
        <div className="uncoder-ui-prefs__row">
          <div>
            <strong>Panels</strong>
            <p>Floating islands over the canvas, or docked to the edges.</p>
          </div>
          <Segmented
            ariaLabel="Panel layout"
            options={[
              { value: 'float', label: 'Floating' },
              { value: 'dock', label: 'Docked' },
            ]}
            value={layout}
            onChange={(v) => useUi.setState({ layout: v === 'dock' ? 'dock' : 'float' })}
          />
        </div>
        {rows.map((r) => (
          <div key={r.key} className="uncoder-ui-prefs__row">
            <div id={`uncoder-ui-pref-${r.key}`}>
              <strong>{r.title}</strong>
              <p>{r.text}</p>
            </div>
            <Toggle checked={prefs[r.key]} onChange={(v) => setPref(r.key, v)} label={r.title} />
          </div>
        ))}
        <div className="uncoder-ui-prefs__row">
          <div>
            <strong>Favourite widgets</strong>
            <p>
              {prefs.favorites.length ? `${prefs.favorites.length} pinned to the top of Insert.` : 'Pin widgets with ☆ in Insert (or F on a focused row).'} Move the selected element with {MOD}↑ / ↓.
            </p>
          </div>
          {prefs.favorites.length > 0 && (
            <Button size="sm" onClick={() => setPref('favorites', [])}>
              Clear
            </Button>
          )}
        </div>
        <div className="uncoder-ui-dialog__foot">
          <Button type="button" variant="primary" onClick={close}>
            Done
          </Button>
        </div>
      </div>
    </div>,
    document.querySelector('.uncoder-ui-portal') ?? document.body,
  );
}
