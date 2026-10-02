import { copySelection, deleteSelection, doRedo, doUndo, duplicateSelection, moveSelection, pasteStyle, previewPage, save, selectParent, wrapSelection } from './actions';
import { select, useUi } from '../store/ui';
import { canvasHasFocus, enterSelection, focusInsertSearch, selectSibling } from './smart';

const isMac = /Mac|iPhone|iPad/.test(navigator.platform);
export const MOD = isMac ? '⌘' : 'Ctrl+';

function inEditable(e: KeyboardEvent): boolean {
  const t = e.target as HTMLElement | null;
  if (!t) return false;
  const tag = t.tagName;
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || t.isContentEditable;
}

/** Global editor shortcuts. Called from the editor window and forwarded from the canvas iframe. */
export function handleShortcut(e: KeyboardEvent): void {
  const mod = isMac ? e.metaKey : e.ctrlKey;
  const key = e.key.toLowerCase();

  if (mod && key === 's') {
    e.preventDefault();
    save();
    return;
  }
  if (mod && key === 'k') {
    e.preventDefault();
    useUi.setState((s) => ({ palette: !s.palette }));
    return;
  }
  if (inEditable(e)) return;

  if (mod && key === 'z') {
    e.preventDefault();
    if (e.shiftKey) doRedo();
    else doUndo();
  } else if (mod && key === 'y') {
    e.preventDefault();
    doRedo();
  } else if (mod && key === 'c') {
    if ((window.getSelection()?.toString() ?? '') !== '') return;
    copySelection();
  } else if (mod && key === 'v') {
    // Plain paste is handled by the native "paste" event (handlePasteEvent) so the system clipboard
    // can be read; paste-style keeps the keyboard path.
    if (e.shiftKey) {
      e.preventDefault();
      pasteStyle();
    }
  } else if (mod && key === 'd') {
    e.preventDefault();
    duplicateSelection();
  } else if (mod && !e.shiftKey && !e.altKey && (key === 'arrowup' || key === 'arrowdown') && useUi.getState().selected.length === 1) {
    e.preventDefault();
    moveSelection(key === 'arrowup' ? -1 : 1);
  } else if (mod && key === 'g') {
    e.preventDefault();
    wrapSelection();
  } else if (mod && key === 'p') {
    e.preventDefault();
    previewPage();
  } else if (key === 'delete' || key === 'backspace') {
    if (useUi.getState().selected.length) {
      e.preventDefault();
      deleteSelection();
    }
  } else if (key === 'escape') {
    if (useUi.getState().contextMenu) useUi.setState({ contextMenu: null });
    else if (useUi.getState().selected.length) selectParent();
  } else if (key === 'enter' && !mod && !e.shiftKey && !e.altKey && canvasHasFocus(e) && useUi.getState().selected.length) {
    if (enterSelection()) e.preventDefault();
  } else if (key === 'tab' && !mod && !e.altKey && canvasHasFocus(e)) {
    if (selectSibling(e.shiftKey ? -1 : 1)) e.preventDefault();
  } else if (e.shiftKey && key === 'a' && !mod) {
    e.preventDefault();
    focusInsertSearch();
  } else if (mod && key === 'i' && !e.shiftKey) {
    e.preventDefault();
    useUi.setState((s) => ({ panel: s.panel === 'layers' ? 'add' : 'layers' }));
  } else if (e.shiftKey && key === 'n' && !mod) {
    useUi.setState((s) => ({ panel: s.panel === 'layers' ? 'add' : 'layers' }));
  } else if (mod && (key === 'e' || key === 'k')) {
    // Finder (Elementor's Ctrl/Cmd+E) = the command palette.
    e.preventDefault();
    useUi.setState({ palette: true });
  } else if (key === '?' ) {
    useUi.setState({ palette: true });
  } else if (mod && key === 'a') {
    e.preventDefault();
    select(null);
  } else if (!mod && !e.altKey && !e.shiftKey && e.key.length === 1 && /[a-z0-9]/i.test(e.key) && useUi.getState().panel === 'add') {
    // Type-to-search: with Insert open, typing on the canvas starts a widget search (then Enter adds it).
    e.preventDefault();
    focusInsertSearch(e.key);
  }
}
