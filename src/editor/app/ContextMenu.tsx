import { schemaOf } from '../lib/config';
import { lockedBy, toggleLocked, useDoc } from '../store/doc';
import { useNotes } from '../store/notes';
import { select, useUi, showPanel } from '../store/ui';
import { MenuList, Popover, type MenuItem } from '../ui/Popover';
import { copySelection, deleteSelection, duplicateSelection, pasteAfterSelection, pasteStyle, resetStyle, wrapSelection, moveSelection } from './actions';
import { MOD } from './shortcuts';
import { elementFor } from '../canvas/frame';

export function elementMenuItems(id: string | null): MenuItem[] {
  const doc = useDoc.getState().doc;
  const node = id ? doc.nodes[id] : null;
  const parent = node?.parent ? doc.nodes[node.parent] : null;
  const isSlot = !!(parent && schemaOf(parent.type)?.nested);
  if (!node) {
    return [{ label: 'Paste', icon: 'clipboard-paste', shortcut: `${MOD}V`, onSelect: pasteAfterSelection }];
  }
  const schema = schemaOf(node.type);
  const title = node.label || schema?.title || 'element';
  return [
    {
      label: `Edit ${title}`,
      icon: schema?.icon ?? 'pencil',
      onSelect: () => {
        select(node.id);
        useUi.setState({ inspectorTab: 'content' });
      },
    },
    'separator',
    { label: 'Copy', icon: 'copy', shortcut: `${MOD}C`, onSelect: copySelection },
    { label: 'Paste after', icon: 'clipboard-paste', shortcut: `${MOD}V`, onSelect: pasteAfterSelection },
    { label: 'Paste style', icon: 'paintbrush', shortcut: `${MOD}⇧V`, onSelect: pasteStyle },
    { label: 'Reset style', icon: 'eraser', onSelect: resetStyle },
    'separator',
    { label: 'Duplicate', icon: 'copy-plus', shortcut: `${MOD}D`, onSelect: duplicateSelection, disabled: isSlot },
    { label: 'Wrap in container', icon: 'square-dashed', shortcut: `${MOD}G`, onSelect: wrapSelection, disabled: isSlot },
    { label: 'Move up', icon: 'arrow-up', shortcut: `${MOD}↑`, onSelect: () => (select(node.id), moveSelection(-1)), disabled: isSlot },
    { label: 'Move down', icon: 'arrow-down', shortcut: `${MOD}↓`, onSelect: () => (select(node.id), moveSelection(1)), disabled: isSlot },
    ...(parent ? [{ label: 'Select parent', icon: 'arrow-up-left', shortcut: 'Esc', onSelect: () => select(parent.id) } as MenuItem] : []),
    { label: 'Add note…', icon: 'message-square-plus', onSelect: () => (select(node.id), useNotes.setState({ composeFor: node.id }), showPanel('notes')) },
    { label: 'Save as template', icon: 'folder-plus', onSelect: () => useUi.setState({ saveTemplate: node.id }), disabled: isSlot },
    node.locked
      ? { label: 'Unlock', icon: 'lock-open', onSelect: () => toggleLocked(node.id) }
      : { label: 'Lock', icon: 'lock', onSelect: () => toggleLocked(node.id), disabled: !!lockedBy(node.id) },
    { label: 'Show in layers', icon: 'layers', shortcut: `${MOD}I`, onSelect: () => showPanel('layers') },
    { label: 'Scroll into view', icon: 'locate-fixed', onSelect: () => elementFor(node.id)?.scrollIntoView({ block: 'center', behavior: 'smooth' }) },
    'separator',
    { label: 'Delete', icon: 'trash-2', shortcut: 'Del', danger: true, onSelect: deleteSelection, disabled: isSlot },
  ];
}

export function ContextMenu() {
  const menu = useUi((s) => s.contextMenu);
  if (!menu) return null;
  const close = () => useUi.setState({ contextMenu: null });
  return (
    <Popover anchor={{ x: menu.x, y: menu.y }} open onClose={close} width={230} className="uncoder-ui-pop--menu" offset={0}>
      <MenuList items={elementMenuItems(menu.id)} onClose={close} />
    </Popover>
  );
}
