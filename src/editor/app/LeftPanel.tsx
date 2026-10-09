import { tabLabel } from '../lib/docInfo';
import { useDoc } from '../store/doc';
import { useUi, type LeftPanel as PanelView } from '../store/ui';
import { IconButton } from '../ui/primitives';
import { WidgetsPanel } from '../panels/WidgetsPanel';
import { NavigatorPanel } from '../panels/NavigatorPanel';
import { KitPanel } from '../panels/KitPanel';
import { PagePanel } from '../panels/PagePanel';
import { HistoryPanel } from '../panels/HistoryPanel';
import { LibraryPanel } from '../panels/LibraryPanel';
import { AiPanel } from '../panels/AiPanel';
import { FindPanel } from '../panels/FindPanel';
import { A11yPanel } from '../panels/A11yPanel';
import { NotesPanel } from '../panels/NotesPanel';
import { Rail } from './Rail';
import { focusInsertSearch } from './smart';

const TITLES: Partial<Record<PanelView, string>> = {
  add: 'Insert',
  library: 'Insert',
  layers: 'Layers',
  kit: 'Styles',
  page: tabLabel(),
  history: 'History',
  find: 'Find & replace',
  a11y: 'Checks',
  ai: 'Ask AI',
  notes: 'Notes',
};

/**
 * The build side: the rail, and next to it the panel for the chosen view (Insert · Layers · Styles · Page, or a
 * tool). The panel collapses to the rail (rail button, Ctrl/⌘ \) so the canvas gets its width.
 */
export function LeftPanel() {
  const panel = useUi((s) => s.panel);
  const open = useUi((s) => s.panelOpen);

  return (
    <aside className={`uncoder-ui-build uncoder-ui-island${open ? ' is-open' : ''}`} aria-label="Build">
      <Rail />
      {open && (
        <div className="uncoder-ui-panel" role="region" aria-label={TITLES[panel]}>
          <div className="uncoder-ui-panel__head">
            <h2 className="uncoder-ui-panel__title">{TITLES[panel]}</h2>
            <div className="uncoder-ui-panel__actions">
              {(panel === 'add' || panel === 'library') && <InsertSwitch sections={panel === 'library'} />}
              {panel === 'layers' && <LayersActions />}
            </div>
          </div>
          <div className={`uncoder-ui-panel__body is-${panel}`}>
            {(panel === 'add' || panel === 'library') && <div className="uncoder-ui-insert">{panel === 'library' ? <LibraryPanel /> : <WidgetsPanel />}</div>}
            {panel === 'layers' && (
              <div className="uncoder-ui-layers">
                <NavigatorPanel />
              </div>
            )}
            {panel === 'kit' && <KitPanel />}
            {panel === 'page' && <PagePanel />}
            {panel === 'history' && <HistoryPanel />}
            {panel === 'ai' && <AiPanel />}
            {panel === 'find' && <FindPanel />}
            {panel === 'a11y' && <A11yPanel />}
            {panel === 'notes' && <NotesPanel />}
          </div>
        </div>
      )}
    </aside>
  );
}

function InsertSwitch({ sections }: { sections: boolean }) {
  return (
    <div className="uncoder-ui-seg uncoder-ui-seg--sm" role="radiogroup" aria-label="Insert">
      <button type="button" role="radio" aria-checked={!sections} className={`uncoder-ui-seg__item${!sections ? ' is-active' : ''}`} onClick={() => focusInsertSearch()}>
        Elements
      </button>
      <button type="button" role="radio" aria-checked={sections} className={`uncoder-ui-seg__item${sections ? ' is-active' : ''}`} onClick={() => useUi.setState({ panel: 'library' })}>
        Sections
      </button>
    </div>
  );
}

function LayersActions() {
  const count = useDoc((s) => Object.keys(s.doc.nodes).length);
  const anyCollapsed = useUi((s) => Object.values(s.navigatorCollapsed).some(Boolean));
  // Expand everything, or fold every element that has children (like Elementor's navigator toggle).
  const toggleAll = () => {
    if (anyCollapsed) {
      useUi.setState({ navigatorCollapsed: {} });
      return;
    }
    const nodes = useDoc.getState().doc.nodes;
    useUi.setState({ navigatorCollapsed: Object.fromEntries(Object.values(nodes).filter((n) => n.children.length).map((n) => [n.id, true])) });
  };
  return (
    <>
      <span className="uncoder-ui-panel__count" data-tip={`${count} element${count === 1 ? '' : 's'} on this page`}>
        {count}
      </span>
      <IconButton icon={anyCollapsed ? 'chevrons-up-down' : 'chevrons-down-up'} label={anyCollapsed ? 'Expand all' : 'Collapse all'} size={14} onClick={toggleAll} />
    </>
  );
}
