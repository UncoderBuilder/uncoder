import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { schemas } from '../lib/config';
import { insertNearSelection } from '../canvas/dnd';
import { placeInspector, setDevice, useUi } from '../store/ui';
import { Icon } from '../ui/Icon';
import { doRedo, doUndo, previewPage, save, copySelection, pasteAfterSelection, duplicateSelection, deleteSelection, wrapSelection, moveSelection } from './actions';
import { MOD } from './shortcuts';
import { settingsTitle } from '../lib/docInfo';

interface Command {
  id: string;
  label: string;
  icon: string;
  group: string;
  shortcut?: string;
  run: () => void;
}

function commands(): Command[] {
  const list: Command[] = [
    { id: 'save', label: 'Save', icon: 'save', group: 'Document', shortcut: `${MOD}S`, run: () => save() },
    { id: 'publish', label: 'Publish', icon: 'send', group: 'Document', run: () => save('publish') },
    { id: 'preview', label: 'Open page in new tab', icon: 'external-link', group: 'Document', shortcut: `${MOD}P`, run: previewPage },
    { id: 'undo', label: 'Undo', icon: 'undo-2', group: 'Edit', shortcut: `${MOD}Z`, run: doUndo },
    { id: 'redo', label: 'Redo', icon: 'redo-2', group: 'Edit', shortcut: `${MOD}⇧Z`, run: doRedo },
    { id: 'copy', label: 'Copy element', icon: 'copy', group: 'Edit', shortcut: `${MOD}C`, run: copySelection },
    { id: 'paste', label: 'Paste element', icon: 'clipboard-paste', group: 'Edit', shortcut: `${MOD}V`, run: pasteAfterSelection },
    { id: 'dup', label: 'Duplicate element', icon: 'copy-plus', group: 'Edit', shortcut: `${MOD}D`, run: duplicateSelection },
    { id: 'wrap', label: 'Wrap in container', icon: 'square-dashed', group: 'Edit', shortcut: `${MOD}G`, run: wrapSelection },
    { id: 'up', label: 'Move element up', icon: 'arrow-up', group: 'Edit', shortcut: `${MOD}↑`, run: () => moveSelection(-1) },
    { id: 'down', label: 'Move element down', icon: 'arrow-down', group: 'Edit', shortcut: `${MOD}↓`, run: () => moveSelection(1) },
    { id: 'del', label: 'Delete element', icon: 'trash-2', group: 'Edit', shortcut: 'Del', run: deleteSelection },
    { id: 'nav', label: 'Open layers', icon: 'layers', group: 'Panels', shortcut: '⇧N', run: () => useUi.setState({ panel: 'layers' }) },
    { id: 'add', label: 'Insert elements', icon: 'plus', group: 'Panels', shortcut: '⇧A', run: () => useUi.setState({ panel: 'add' }) },
    { id: 'library', label: 'Insert sections & saved templates', icon: 'layout-template', group: 'Panels', run: () => useUi.setState({ panel: 'library' }) },
    { id: 'kit', label: 'Styles (Design System)', icon: 'palette', group: 'Panels', run: () => useUi.setState({ panel: 'kit' }) },
    { id: 'page', label: settingsTitle(), icon: 'file-cog', group: 'Panels', run: () => useUi.setState({ panel: 'page' }) },
    { id: 'history', label: 'History', icon: 'history', group: 'Panels', run: () => useUi.setState({ panel: 'history' }) },
    { id: 'find', label: 'Find & replace', icon: 'replace-all', group: 'Panels', run: () => useUi.setState({ panel: 'find' }) },
    { id: 'a11y', label: 'Accessibility checks', icon: 'shield-check', group: 'Panels', run: () => useUi.setState({ panel: 'a11y' }) },
    { id: 'ai', label: 'Ask AI', icon: 'sparkles', group: 'Panels', run: () => useUi.setState({ panel: 'ai' }) },
    { id: 'desktop', label: 'Desktop view', icon: 'monitor', group: 'View', run: () => setDevice('desktop') },
    { id: 'tablet', label: 'Tablet view', icon: 'tablet', group: 'View', run: () => setDevice('tablet') },
    { id: 'mobile', label: 'Mobile view', icon: 'smartphone', group: 'View', run: () => setDevice('mobile') },
    { id: 'theme', label: 'Toggle Paper / Petrol night interface', icon: 'sun-moon', group: 'View', run: () => useUi.setState((s) => ({ theme: s.theme === 'dark' ? 'light' : 'dark' })) },
    { id: 'dock', label: 'Toggle island / edge-to-edge panels', icon: 'panels-top-left', group: 'View', run: () => useUi.setState((s) => ({ layout: s.layout === 'dock' ? 'float' : 'dock' })) },
    { id: 'insp-left', label: 'Settings panel: dock left', icon: 'panel-left', group: 'View', run: () => placeInspector('left') },
    { id: 'insp-right', label: 'Settings panel: dock right', icon: 'panel-right', group: 'View', run: () => placeInspector('right') },
    { id: 'insp-float', label: 'Settings panel: float', icon: 'picture-in-picture-2', group: 'View', run: () => placeInspector('float') },
    { id: 'prefs', label: 'Editor preferences', icon: 'sliders-horizontal', group: 'View', run: () => useUi.setState({ prefsOpen: true }) },
    { id: 'stylebook', label: 'Style book (all widgets with the Design System)', icon: 'book-open', group: 'View', run: () => useUi.setState({ styleBook: true }) },
  ];
  for (const s of Object.values(schemas)) {
    list.push({ id: 'w:' + s.name, label: `Insert ${s.title}`, icon: s.icon, group: 'Insert', run: () => insertNearSelection({ kind: 'new', type: s.name }) });
  }
  return list;
}

export function CommandPalette() {
  const open = useUi((s) => s.palette);
  const [q, setQ] = useState('');
  const [active, setActive] = useState(0);
  const input = useRef<HTMLInputElement>(null);
  const all = useMemo(commands, []);
  const results = useMemo(() => {
    const needle = q.trim().toLowerCase();
    const list = needle ? all.filter((c) => c.label.toLowerCase().includes(needle) || c.group.toLowerCase().includes(needle)) : all.filter((c) => c.group !== 'Insert');
    return list.slice(0, 40);
  }, [q, all]);

  useEffect(() => {
    if (open) {
      setQ('');
      setActive(0);
      setTimeout(() => input.current?.focus(), 0);
    }
  }, [open]);

  if (!open) return null;
  const close = () => useUi.setState({ palette: false });
  const run = (c: Command) => {
    close();
    c.run();
  };
  return createPortal(
    <div className="uncoder-ui-scrim" onMouseDown={close}>
      <div className="uncoder-ui-palette" role="dialog" aria-label="Command palette" onMouseDown={(e) => e.stopPropagation()}>
        <div className="uncoder-ui-palette__input">
          <Icon name="search" size={16} />
          <input
            ref={input}
            placeholder="Type a command or widget name…"
            value={q}
            onChange={(e) => {
              setQ(e.currentTarget.value);
              setActive(0);
            }}
            onKeyDown={(e) => {
              if (e.key === 'Escape') close();
              if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActive((a) => Math.min(results.length - 1, a + 1));
              }
              if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive((a) => Math.max(0, a - 1));
              }
              if (e.key === 'Enter' && results[active]) run(results[active]);
            }}
            aria-label="Command"
          />
          <kbd className="uncoder-ui-kbd">Esc</kbd>
        </div>
        <div className="uncoder-ui-palette__list" role="listbox">
          {results.map((c, i) => (
            <button key={c.id} type="button" role="option" aria-selected={i === active} className={`uncoder-ui-palette__item${i === active ? ' is-active' : ''}`} onMouseEnter={() => setActive(i)} onClick={() => run(c)}>
              <Icon name={c.icon} size={15} />
              <span className="uncoder-ui-palette__label">{c.label}</span>
              <span className="uncoder-ui-palette__group">{c.group}</span>
              {c.shortcut && <kbd className="uncoder-ui-kbd">{c.shortcut}</kbd>}
            </button>
          ))}
          {!results.length && <div className="uncoder-ui-palette__none">Nothing found.</div>}
        </div>
      </div>
    </div>,
    document.querySelector('.uncoder-ui-portal') ?? document.body,
  );
}
