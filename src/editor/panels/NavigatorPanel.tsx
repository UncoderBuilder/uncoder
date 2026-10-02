import { memo, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { create } from 'zustand';
import { schemaOf } from '../lib/config';
import { ancestors, isDescendant } from '../lib/tree';
import { moveElement, renameElement, toggleDisabled, useDoc, toggleLocked } from '../store/doc';
import { select, useUi } from '../store/ui';
import { Icon } from '../ui/Icon';
import { elementFor } from '../canvas/frame';
import { openCount, useNotes } from '../store/notes';
import { scrollToElement } from '../app/smart';

/** Conditions (Behaviour → Conditions; Core\Element_Conditions). */
const hasDisplayRules = (s: Record<string, any>) => Array.isArray(s._conditions) && s._conditions.length > 0;

type DropPos = { id: string; where: 'before' | 'after' | 'inside' } | null;

/** Ancestors of the selection: each row subscribes to its own flag, so only the changed rows re-render. */
const usePath = create<{ path: Record<string, true> }>(() => ({ path: {} }));

const ROW_H = 28;
const OVERSCAN = 8;

/**
 * Layers: the page tree as a windowed list — only the rows in view exist in the DOM, so a page with
 * thousands of elements scrolls as smoothly as a small one. The selection's ancestors auto-expand and
 * get a subtle fill; drag rows to reorder or nest.
 */
export function NavigatorPanel() {
  const root = useDoc((s) => s.doc.root);
  const nodes = useDoc((s) => s.doc.nodes);
  const collapsed = useUi((s) => s.navigatorCollapsed);
  const selected = useUi((s) => s.selected[0] ?? null);
  const [drag, setDrag] = useState<string | null>(null);
  const [drop, setDrop] = useState<DropPos>(null);
  const scroller = useRef<HTMLDivElement>(null);
  const [view, setView] = useState({ top: 0, height: 600 });

  const rows = useMemo(() => {
    const out: Array<{ id: string; depth: number }> = [];
    const walk = (ids: string[], depth: number) => {
      for (const id of ids) {
        const n = nodes[id];
        if (!n) continue;
        out.push({ id, depth });
        if (n.children.length && !collapsed[id]) walk(n.children, depth + 1);
      }
    };
    walk(root, 0);
    return out;
  }, [root, nodes, collapsed]);

  // Selection: expand its ancestors, mark them, and bring the row into view.
  useEffect(() => {
    const doc = useDoc.getState().doc;
    const list = selected && doc.nodes[selected] ? ancestors(doc, selected) : [];
    usePath.setState({ path: Object.fromEntries(list.map((a) => [a, true as const])) });
    const closed = list.filter((a) => useUi.getState().navigatorCollapsed[a]);
    if (closed.length) useUi.setState((s) => ({ navigatorCollapsed: { ...s.navigatorCollapsed, ...Object.fromEntries(closed.map((a) => [a, false])) } }));
  }, [selected]);
  useLayoutEffect(() => {
    const el = scroller.current;
    if (!el || !selected) return;
    const i = rows.findIndex((r) => r.id === selected);
    if (i < 0) return;
    const y = i * ROW_H;
    if (y < el.scrollTop || y + ROW_H > el.scrollTop + el.clientHeight) el.scrollTop = Math.max(0, y - el.clientHeight / 2);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selected, rows.length]);
  useEffect(() => {
    const el = scroller.current;
    if (!el) return;
    const measure = () => setView({ top: el.scrollTop, height: el.clientHeight });
    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(el);
    return () => ro.disconnect();
  }, []);

  const onDrop = () => {
    if (!drag || !drop) return;
    const doc = useDoc.getState().doc;
    const target = doc.nodes[drop.id];
    if (!target || drag === drop.id || isDescendant(doc, drop.id, drag)) return;
    if (drop.where === 'inside') {
      if (schemaOf(target.type)?.nested) return;
      moveElement(drag, target.id, target.children.length);
    } else {
      const parent = target.parent;
      if (parent && schemaOf(doc.nodes[parent].type)?.nested) return;
      if (parent === null && !schemaOf(doc.nodes[drag].type)?.container) return;
      const list = parent ? doc.nodes[parent].children : doc.root;
      moveElement(drag, parent, list.indexOf(target.id) + (drop.where === 'after' ? 1 : 0));
    }
  };

  if (!root.length) return <div className="uncoder-ui-nav__empty">The page is empty. Insert a section to see its structure here.</div>;

  const first = Math.max(0, Math.floor(view.top / ROW_H) - OVERSCAN);
  const last = Math.min(rows.length, Math.ceil((view.top + view.height) / ROW_H) + OVERSCAN);

  return (
    <div
      ref={scroller}
      className="uncoder-ui-nav"
      role="tree"
      aria-label="Page structure"
      onScroll={(e) => setView({ top: e.currentTarget.scrollTop, height: e.currentTarget.clientHeight })}
      onDragEnd={() => {
        setDrag(null);
        setDrop(null);
      }}
    >
      <div className="uncoder-ui-nav__space" style={{ height: rows.length * ROW_H }}>
        {rows.slice(first, last).map((r, i) => (
          <NavRow key={r.id} id={r.id} depth={r.depth} top={(first + i) * ROW_H} drag={drag} setDrag={setDrag} dropHere={drop?.id === r.id ? drop.where : null} setDrop={setDrop} onDrop={onDrop} />
        ))}
      </div>
    </div>
  );
}

interface RowProps {
  id: string;
  depth: number;
  top: number;
  drag: string | null;
  setDrag: (id: string | null) => void;
  dropHere: 'before' | 'after' | 'inside' | null;
  setDrop: (d: DropPos) => void;
  onDrop: () => void;
}

const NavRow = memo(function NavRow({ id, depth, top, drag, setDrag, dropHere, setDrop, onDrop }: RowProps) {
  const node = useDoc((s) => s.doc.nodes[id]);
  const selected = useUi((s) => s.selected.includes(id));
  const hovered = useUi((s) => s.hovered === id);
  const collapsed = useUi((s) => !!s.navigatorCollapsed[id]);
  const onPath = usePath((s) => !!s.path[id]);
  const [renaming, setRenaming] = useState(false);
  const schema = schemaOf(node?.type ?? '');

  if (!node) return null;
  const hasChildren = node.children.length > 0;
  const container = !!schema?.container;
  const label = node.label || schema?.title || node.type;
  const openNoteCount = useNotes((s) => openCount(s.notes, id));
  const badge = node.type === 'template' ? (node.settings.overrides && Object.keys(node.settings.overrides).length ? 'Component' : 'Template') : '';

  return (
    <div
      role="treeitem"
      aria-level={depth + 1}
      aria-expanded={hasChildren ? !collapsed : undefined}
      aria-selected={selected}
      className={`uncoder-ui-nav__row${selected ? ' is-selected' : ''}${onPath ? ' is-path' : ''}${hovered ? ' is-hovered' : ''}${node.disabled ? ' is-disabled' : ''}${dropHere ? ' drop-' + dropHere : ''}`}
      style={{ transform: `translateY(${top}px)`, paddingLeft: 6 + depth * 14 }}
      draggable={!renaming && !node.locked}
      onDragStart={(e) => {
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', id);
        setDrag(id);
      }}
      onDragOver={(e) => {
        if (!drag || drag === id) return;
        e.preventDefault();
        const r = e.currentTarget.getBoundingClientRect();
        const y = (e.clientY - r.top) / r.height;
        const where = container && y > 0.3 && y < 0.7 ? 'inside' : y < 0.5 ? 'before' : 'after';
        if (dropHere !== where) setDrop({ id, where });
      }}
      onDrop={(e) => {
        e.preventDefault();
        onDrop();
        setDrag(null);
        setDrop(null);
      }}
      onClick={(e) => {
        const additive = e.shiftKey || e.metaKey || e.ctrlKey;
        select(id, additive);
        // Bring the element into view on the canvas (nothing moves when it is already visible).
        if (!additive) scrollToElement(id);
      }}
      onMouseEnter={() => useUi.setState({ hovered: id })}
      onMouseLeave={() => useUi.setState({ hovered: null })}
      onDoubleClick={() => setRenaming(true)}
      onContextMenu={(e) => {
        e.preventDefault();
        select(id);
        useUi.setState({ contextMenu: { x: e.clientX, y: e.clientY, id, source: 'navigator' } });
      }}
    >
      <button
        type="button"
        className="uncoder-ui-nav__caret"
        aria-label={collapsed ? 'Expand' : 'Collapse'}
        tabIndex={-1}
        style={{ visibility: hasChildren ? 'visible' : 'hidden' }}
        onClick={(e) => {
          e.stopPropagation();
          useUi.setState((s) => ({ navigatorCollapsed: { ...s.navigatorCollapsed, [id]: !collapsed } }));
        }}
      >
        <Icon name="chevron-right" size={12} className={collapsed ? '' : 'is-open'} />
      </button>
      <Icon name={schema?.icon ?? 'box'} size={14} stroke={1.6} className="uncoder-ui-nav__icon" />
      {renaming ? (
        <input
          className="uncoder-ui-nav__rename"
          autoFocus
          defaultValue={node.label ?? ''}
          placeholder={schema?.title}
          onClick={(e) => e.stopPropagation()}
          onBlur={(e) => {
            renameElement(id, e.currentTarget.value);
            setRenaming(false);
          }}
          onKeyDown={(e) => {
            if (e.key === 'Enter') (e.target as HTMLInputElement).blur();
            if (e.key === 'Escape') setRenaming(false);
            e.stopPropagation();
          }}
        />
      ) : (
        <span className="uncoder-ui-nav__label">{label}</span>
      )}
      {badge && <span className={`uncoder-ui-nav__badge is-${badge.toLowerCase()}`}>{badge}</span>}
      {openNoteCount > 0 && (
        <span className="uncoder-ui-nav__flag is-note" data-tip={`${openNoteCount} open note${openNoteCount === 1 ? '' : 's'}`}>
          <Icon name="message-square" size={12} />
        </span>
      )}
      {!!node.settings._loop && (
        <span className="uncoder-ui-nav__flag" data-tip="Query loop: repeats for each item on the live page">
          <Icon name="repeat" size={12} />
        </span>
      )}
      {hasDisplayRules(node.settings) && (
        <span className="uncoder-ui-nav__flag" data-tip="Conditions: shown only when they match">
          <Icon name="user-check" size={12} />
        </span>
      )}
      <button
        type="button"
        className={`uncoder-ui-nav__lock${node.locked ? ' is-on' : ''}`}
        tabIndex={-1}
        aria-label={node.locked ? 'Unlock element' : 'Lock element'}
        data-tip={node.locked ? 'Locked: not moved, deleted or edited' : 'Lock'}
        onClick={(e) => {
          e.stopPropagation();
          toggleLocked(id);
        }}
      >
        <Icon name={node.locked ? 'lock' : 'lock-open'} size={12} />
      </button>
      <button
        type="button"
        className="uncoder-ui-nav__vis"
        tabIndex={-1}
        aria-label={node.disabled ? 'Enable element' : 'Disable element'}
        data-tip={node.disabled ? 'Hidden on the site' : 'Hide on the site'}
        onClick={(e) => {
          e.stopPropagation();
          toggleDisabled(id);
        }}
      >
        <Icon name={node.disabled ? 'eye-off' : 'eye'} size={13} />
      </button>
      <button
        type="button"
        className="uncoder-ui-nav__locate"
        tabIndex={-1}
        aria-label="Scroll to element"
        onClick={(e) => {
          e.stopPropagation();
          select(id);
          elementFor(id)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }}
      >
        <Icon name="locate-fixed" size={13} />
      </button>
    </div>
  );
});
