import { contentOnly } from '../lib/config';
import { tabLabel } from '../lib/docInfo';
import { useDoc } from '../store/doc';
import { MAIN_PANELS, useUi, type LeftPanel as PanelView } from '../store/ui';
import { Icon } from '../ui/Icon';
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
import { focusInsertSearch } from './smart';

const TABS: Array<{ id: PanelView; label: string; icon: string; design?: boolean }> = [
  { id: 'add', label: 'Insert', icon: 'plus', design: true },
  { id: 'layers', label: 'Layers', icon: 'layers' },
  { id: 'kit', label: 'Styles', icon: 'palette', design: true },
  { id: 'page', label: tabLabel(), icon: 'file-cog', design: true },
];
const TOOL_TITLES: Partial<Record<PanelView, string>> = { history: 'History', find: 'Find & replace', a11y: 'Checks', ai: 'Ask AI', notes: 'Notes' };

/** The build panel (left island): Insert · Layers · Styles · Page, or a tool view from the command bar. */
export function LeftPanel() {
  const panel = useUi((s) => s.panel);
  const tool = !MAIN_PANELS.includes(panel);
  const tabs = TABS.filter((t) => !contentOnly() || !t.design);
  const active = panel === 'library' ? 'add' : panel;

  return (
    <aside className="uncoder-ui-panel uncoder-ui-island" aria-label={tool ? TOOL_TITLES[panel] : 'Build'}>
      {tool ? (
        <div className="uncoder-ui-panel__tool">
          <IconButton icon="arrow-left" label="Back" size={15} onClick={() => useUi.setState((s) => ({ panel: s.mainPanel }))} />
          <h2 className="uncoder-ui-panel__title">{TOOL_TITLES[panel]}</h2>
        </div>
      ) : (
        <div className="uncoder-ui-tabs uncoder-ui-tabs--build" role="tablist" aria-label="Build panel">
          {tabs.map((t) => (
            <button key={t.id} type="button" role="tab" aria-selected={active === t.id} className={`uncoder-ui-tabs__tab${active === t.id ? ' is-active' : ''}`} onClick={() => (t.id === 'add' ? focusInsertSearch() : useUi.setState({ panel: t.id }))}>
              <Icon name={t.icon} size={16} className="uncoder-ui-tabs__icon" />
              <span>{t.label}</span>
            </button>
          ))}
        </div>
      )}
      <div className="uncoder-ui-panel__body">
        {(panel === 'add' || panel === 'library') && <InsertView sections={panel === 'library'} />}
        {panel === 'layers' && <LayersView />}
        {panel === 'kit' && <KitPanel />}
        {panel === 'page' && <PagePanel />}
        {panel === 'history' && <HistoryPanel />}
        {panel === 'ai' && <AiPanel />}
        {panel === 'find' && <FindPanel />}
        {panel === 'a11y' && <A11yPanel />}
        {panel === 'notes' && <NotesPanel />}
      </div>
    </aside>
  );
}

function InsertView({ sections }: { sections: boolean }) {
  return (
    <div className="uncoder-ui-insert">
      <div className="uncoder-ui-seg uncoder-ui-seg--sm uncoder-ui-insert__switch" role="radiogroup" aria-label="Insert">
        <button type="button" role="radio" aria-checked={!sections} className={`uncoder-ui-seg__item${!sections ? ' is-active' : ''}`} onClick={() => focusInsertSearch()}>
          Elements
        </button>
        <button type="button" role="radio" aria-checked={sections} className={`uncoder-ui-seg__item${sections ? ' is-active' : ''}`} onClick={() => useUi.setState({ panel: 'library' })}>
          Sections
        </button>
      </div>
      {sections ? <LibraryPanel /> : <WidgetsPanel />}
    </div>
  );
}

function LayersView() {
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
    <div className="uncoder-ui-layers">
      <div className="uncoder-ui-layers__meta">
        <span>
          {count} element{count === 1 ? '' : 's'}
        </span>
        <IconButton icon={anyCollapsed ? 'chevrons-up-down' : 'chevrons-down-up'} label={anyCollapsed ? 'Expand all' : 'Collapse all'} size={14} onClick={toggleAll} />
      </div>
      <NavigatorPanel />
    </div>
  );
}
