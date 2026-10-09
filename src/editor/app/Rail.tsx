import { contentOnly } from '../lib/config';
import { tabLabel } from '../lib/docInfo';
import { useNotes } from '../store/notes';
import { railSelect, togglePanelOpen, useUi, type LeftPanel } from '../store/ui';
import { Icon } from '../ui/Icon';
import { MOD } from './shortcuts';
import { focusInsertSearch } from './smart';

interface RailItem {
  id: LeftPanel;
  label: string;
  icon: string;
  /** Hidden for content-only roles (they edit text and media, not design). */
  design?: boolean;
  shortcut?: string;
}

const VIEWS: RailItem[] = [
  { id: 'add', label: 'Insert', icon: 'plus', design: true, shortcut: '⇧A' },
  { id: 'layers', label: 'Layers', icon: 'layers', shortcut: `${MOD}I` },
  { id: 'kit', label: 'Styles', icon: 'palette', design: true },
  { id: 'page', label: tabLabel(), icon: 'file-cog', design: true },
];
const TOOLS: RailItem[] = [
  { id: 'history', label: 'History', icon: 'history' },
  { id: 'find', label: 'Find & replace', icon: 'replace-all', design: true },
  { id: 'a11y', label: 'Checks', icon: 'shield-check' },
  { id: 'notes', label: 'Notes', icon: 'message-square-text' },
  { id: 'ai', label: 'Ask AI', icon: 'sparkles' },
];

/**
 * The 48px rail on the left edge: the build panel's views and the tools, each one click away. Clicking the view that
 * is open collapses the panel to the rail (the canvas gets its width); the button at the bottom does the same.
 */
export function Rail() {
  const panel = useUi((s) => s.panel);
  const open = useUi((s) => s.panelOpen);
  const checks = useUi((s) => s.checksCount);
  const notes = useNotes((s) => (s.notes ?? []).filter((n) => !n.resolved).length);
  const badge: Partial<Record<LeftPanel, number>> = { a11y: checks, notes };
  const show = (list: RailItem[]) => list.filter((i) => !contentOnly() || !i.design);

  const pick = (id: LeftPanel) => {
    // Insert puts the cursor in its search box, as before.
    if (id === 'add' && !(open && (panel === 'add' || panel === 'library'))) {
      focusInsertSearch();
      return;
    }
    railSelect(id);
  };

  const button = (i: RailItem) => {
    const active = open && (panel === i.id || (i.id === 'add' && panel === 'library'));
    const count = badge[i.id] ?? 0;
    const tip = count ? `${i.label} · ${count}` : i.label;
    return (
      <button
        key={i.id}
        type="button"
        className={`uncoder-ui-rail__btn${active ? ' is-active' : ''}${i.id === 'ai' ? ' is-ai' : ''}`}
        aria-label={count ? `${i.label}: ${count}` : i.label}
        aria-pressed={active}
        data-tip={i.shortcut ? `${tip}  ${i.shortcut}` : tip}
        data-tip-side="right"
        onClick={() => pick(i.id)}
      >
        <Icon name={i.icon} size={18} stroke={1.7} />
        {count > 0 && <span className="uncoder-ui-badge">{count > 99 ? '99+' : count}</span>}
      </button>
    );
  };

  return (
    <nav className="uncoder-ui-rail" aria-label="Panels and tools">
      {show(VIEWS).map(button)}
      <span className="uncoder-ui-rail__sep" aria-hidden />
      {show(TOOLS).map(button)}
      <span className="uncoder-ui-rail__spacer" />
      <button
        type="button"
        className="uncoder-ui-rail__btn"
        aria-label="Commands and shortcuts"
        data-tip={`Commands & shortcuts  ${MOD}K`}
        data-tip-side="right"
        onClick={() => useUi.setState({ palette: true })}
      >
        <Icon name="command" size={17} stroke={1.7} />
      </button>
      <button
        type="button"
        className="uncoder-ui-rail__btn"
        aria-label={open ? 'Collapse panel' : 'Expand panel'}
        aria-expanded={open}
        data-tip={`${open ? 'Collapse panel' : 'Expand panel'}  ${MOD}\\`}
        data-tip-side="right"
        onClick={togglePanelOpen}
      >
        <Icon name={open ? 'panel-left-close' : 'panel-left-open'} size={18} stroke={1.7} />
      </button>
    </nav>
  );
}
