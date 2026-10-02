// Hover/selection outlines, element toolbar and drop indicators, drawn inside the canvas document.
import { useEffect, useReducer, useRef } from 'react';
import { schemaOf } from '../lib/config';
import { iconSvg } from '../lib/icons';
import { duplicateElement, insertElements, lockedBy, removeElements, toggleLocked, useDoc } from '../store/doc';
import { select, useUi } from '../store/ui';
import { beginDrag } from './dnd';
import { elementFor, frame, onGeometry, toParent } from './frame';
import { focusInsertSearch, pick, revealAdded } from '../app/smart';
import { Handles } from './Handles';
import { useNotes } from '../store/notes';
import { usePrefs } from '../store/prefs';

type Rect = { x: number; y: number; w: number; h: number } | null;

function rectOf(id: string | null): Rect {
  if (!id || !frame.win) return null;
  const el = elementFor(id);
  if (!el) return null;
  const r = el.getBoundingClientRect();
  if (!r.width && !r.height) return null;
  return { x: r.left + frame.win.scrollX, y: r.top + frame.win.scrollY, w: r.width, h: r.height };
}

function useGeometryTick() {
  const [, force] = useReducer((n: number) => n + 1, 0);
  useEffect(() => {
    let raf = 0;
    const schedule = () => {
      if (!raf) raf = requestAnimationFrame(() => ((raf = 0), force()));
    };
    const off = onGeometry(schedule);
    const win = frame.win;
    win?.addEventListener('resize', schedule);
    const mo = frame.mount ? new MutationObserver(schedule) : null;
    mo?.observe(frame.mount!, { subtree: true, childList: true, attributes: true, attributeFilter: ['class', 'style', 'hidden', 'open'] });
    const ro = new ResizeObserver(schedule);
    if (frame.mount) ro.observe(frame.mount);
    // Late-loading media changes layout.
    const onLoad = (e: Event) => (e.target as Element)?.tagName === 'IMG' && schedule();
    frame.doc?.addEventListener('load', onLoad, true);
    return () => {
      off();
      win?.removeEventListener('resize', schedule);
      mo?.disconnect();
      ro.disconnect();
      frame.doc?.removeEventListener('load', onLoad, true);
      cancelAnimationFrame(raf);
    };
  }, []);
}

const Svg = ({ name, size = 12 }: { name: string; size?: number }) => <span className="uncoder-ui-ov__ic" dangerouslySetInnerHTML={{ __html: iconSvg(name, size, 2.4) }} />;

export function Overlay() {
  useGeometryTick();
  const hovered = useUi((s) => s.hovered);
  const selected = useUi((s) => s.selected);
  const drop = useUi((s) => s.drop);
  const dragging = useUi((s) => s.dragging);
  const showHandles = usePrefs((s) => s.handles);
  const editing = useUi((s) => s.editingInline);
  const nodes = useDoc((s) => s.doc.nodes);
  const zoom = useUi((s) => s.zoom * s.fit);

  const primary = selected[0] ?? null;
  const selNode = primary ? nodes[primary] : null;
  const hoverNode = hovered ? nodes[hovered] : null;
  const hoverRect = !dragging && hovered && !selected.includes(hovered) ? rectOf(hovered) : null;
  const toolbarRef = useRef<HTMLDivElement>(null);

  const selRects = selected.map((id) => ({ id, rect: rectOf(id) }));
  const primaryRect = selRects[0]?.rect ?? null;
  const parentOfSel = selNode?.parent ? nodes[selNode.parent] : null;
  const parentRect = parentOfSel && selected.length === 1 ? rectOf(parentOfSel.id) : null;
  const isSlot = !!(parentOfSel && schemaOf(parentOfSel.type)?.nested);
  const selLock = primary ? lockedBy(primary) : null;

  const title = (id: string) => {
    const n = nodes[id];
    if (!n) return '';
    const name = n.label || schemaOf(n.type)?.title || n.type;
    // A looping container repeats on the live page; the canvas shows its first item.
    return n.settings?._loop ? `${name} ↻` : name;
  };
  const isContainer = (id: string) => !!schemaOf(nodes[id]?.type)?.container;

  // The page section (top-level container) around what the pointer is over, for the section handle.
  const sectionOf = (id: string | null): string | null => {
    let n = id ? nodes[id] : undefined;
    while (n && n.parent) n = nodes[n.parent];
    return n && isContainer(n.id) ? n.id : null;
  };
  const sectionId = !dragging && !editing ? sectionOf(hovered ?? primary) : null;
  const sectionRect = sectionId ? rectOf(sectionId) : null;
  const root = useDoc.getState().doc.root;
  const notes = useNotes((s) => s.notes);
  const noted = new Map<string, number>();
  for (const n of notes ?? []) if (!n.resolved && nodes[n.element]) noted.set(n.element, (noted.get(n.element) ?? 0) + 1);

  return (
    <div className="uncoder-ui-ov" aria-hidden>
      {hoverRect && hoverNode && (
        <div className={`uncoder-ui-ov__hover${isContainer(hovered!) ? ' is-container' : ''}`} style={box(hoverRect)}>
          <span className="uncoder-ui-ov__tag uncoder-ui-ov__tag--hover">{title(hovered!)}</span>
        </div>
      )}

      {!dragging && parentRect && <div className="uncoder-ui-ov__parent" style={box(parentRect)} />}

      {!dragging &&
        selRects.map(({ id, rect }, i) =>
          rect ? (
            <div key={id} className={`uncoder-ui-ov__sel${isContainer(id) ? ' is-container' : ''}${editing === id ? ' is-editing' : ''}${rect.y < 40 ? ' is-top' : ''}`} style={box(rect)}>
              {i === 0 && editing !== id && (
                <div className="uncoder-ui-ov__bar" ref={toolbarRef} data-uncoder-ui-for={id}>
                  <span className="uncoder-ui-ov__name">{title(id)}</span>
                  {selLock && (
                    <button type="button" className="uncoder-ui-ov__btn is-locked" title={selLock === id ? 'Locked · click to unlock' : 'Inside a locked element · click to select it'} onClick={() => (selLock === id ? toggleLocked(id) : pick(selLock))}>
                      <Svg name="lock" />
                    </button>
                  )}
                  {isContainer(id) && (
                    <button type="button" className="uncoder-ui-ov__btn" title="Insert inside" onClick={() => focusInsertSearch()}>
                      <Svg name="plus" />
                    </button>
                  )}
                  {parentOfSel && (
                    <button type="button" className="uncoder-ui-ov__btn uncoder-ui-ov__btn--text" title="Select parent (Esc)" onClick={() => pick(parentOfSel.id)}>
                      Parent
                    </button>
                  )}
                  {!isSlot && !selLock && (
                    <button
                      type="button"
                      className="uncoder-ui-ov__btn uncoder-ui-ov__btn--text uncoder-ui-ov__btn--grip"
                      title="Drag to move"
                      onPointerDown={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const p = toParent(e.clientX, e.clientY, zoom);
                        beginDrag({ clientX: p.x, clientY: p.y }, { kind: 'move', ids: selected }, title(id), schemaOf(nodes[id]?.type)?.icon ?? 'box');
                      }}
                    >
                      Move
                    </button>
                  )}
                  {!isSlot && (
                    <button
                      type="button"
                      className="uncoder-ui-ov__btn"
                      title="Duplicate"
                      onClick={() => {
                        const nid = duplicateElement(id);
                        if (nid) select(nid);
                      }}
                    >
                      <Svg name="copy" />
                    </button>
                  )}
                  {!isSlot && !selLock && (
                    <button
                      type="button"
                      className="uncoder-ui-ov__btn"
                      title="Delete"
                      onClick={() => {
                        if (removeElements(selected).length) select(null);
                      }}
                    >
                      <Svg name="trash-2" />
                    </button>
                  )}
                  <button
                    type="button"
                    className="uncoder-ui-ov__btn"
                    title="More"
                    onClick={(e) => {
                      const p = toParent(e.clientX, e.clientY, zoom);
                      useUi.setState({ contextMenu: { x: p.x, y: p.y, id, source: 'canvas' } });
                    }}
                  >
                    <Svg name="ellipsis" />
                  </button>
                </div>
              )}
            </div>
          ) : null,
        )}

      {sectionId && sectionRect && (
        <div className={`uncoder-ui-ov__sec${sectionRect.y < 30 ? ' is-top' : ''}`} style={{ transform: `translate(${Math.round(sectionRect.x + sectionRect.w / 2)}px, ${Math.round(sectionRect.y)}px)` }}>
          <div className="uncoder-ui-ov__secbar">
            <button
              type="button"
              className="uncoder-ui-ov__secbtn is-add"
              title="Add a section above"
              onClick={() => {
                const ids = insertElements(null, Math.max(0, root.indexOf(sectionId)), [{ id: '', type: 'container', settings: {}, children: [] }], 'Add section');
                revealAdded(ids);
              }}
            >
              <Svg name="plus" size={13} />
            </button>
            <button
              type="button"
              className="uncoder-ui-ov__secbtn is-edit"
              title="Edit section · drag to move · right-click for more"
              aria-label={`Edit ${title(sectionId)}`}
              data-uncoder-ui-for={sectionId}
              onPointerDown={(e) => {
                if (e.button !== 0) return;
                e.preventDefault();
                e.stopPropagation();
                editOrDrag(e.nativeEvent, sectionId, title(sectionId), zoom);
              }}
            >
              <Svg name="pencil" size={13} />
            </button>
            {!lockedBy(sectionId) && (
            <button
              type="button"
              className="uncoder-ui-ov__secbtn is-delete"
              title="Delete this section"
              onClick={() => {
                if (removeElements([sectionId]).length) select(null);
              }}
            >
              <Svg name="x" size={13} />
            </button>
            )}
          </div>
        </div>
      )}

      {!dragging &&
        [...noted].map(([nid, count]) => {
          const r = rectOf(nid);
          return r ? (
            <button
              key={`note-${nid}`}
              type="button"
              className="uncoder-ui-ov__note"
              title={`${count} open note${count === 1 ? '' : 's'}`}
              style={{ translate: `${Math.round(r.x + r.w - 10)}px ${Math.round(r.y - 10)}px` }}
              onClick={() => {
                select(nid);
                useUi.setState({ panel: 'notes' });
              }}
            >
              <Svg name="message-square" size={11} />
              {count}
            </button>
          ) : null;
        })}
      {dragging && drop?.box && <div className="uncoder-ui-ov__target" style={box(drop.box)} />}
      {dragging && drop?.line && <div className={`uncoder-ui-ov__line${drop.line.w <= 4 ? ' is-vertical' : ''}`} style={box(drop.line)} />}
      {primaryRect && !dragging && selNode && isContainer(primary!) && <PaddingGuides id={primary!} />}
      {primaryRect && !dragging && selNode && selected.length === 1 && !editing && !selLock && showHandles && <Handles id={primary!} />}
    </div>
  );
}

/** Section pencil: a click edits the section (the build panel follows), moving the pointer while pressed drags it. */
function editOrDrag(down: PointerEvent, id: string, label: string, zoom: number) {
  const doc = (down.target as Node).ownerDocument ?? document;
  const done = () => {
    doc.removeEventListener('pointermove', move, true);
    doc.removeEventListener('pointerup', up, true);
  };
  const move = (e: PointerEvent) => {
    if (Math.hypot(e.clientX - down.clientX, e.clientY - down.clientY) < 5 || lockedBy(id)) return;
    done();
    const p = toParent(e.clientX, e.clientY, zoom);
    beginDrag({ clientX: p.x, clientY: p.y }, { kind: 'move', ids: [id] }, label, 'grip-horizontal');
  };
  const up = () => {
    done();
    pick(id);
  };
  doc.addEventListener('pointermove', move, true);
  doc.addEventListener('pointerup', up, true);
}

function box(r: { x: number; y: number; w: number; h: number }) {
  return { transform: `translate(${Math.round(r.x)}px, ${Math.round(r.y)}px)`, width: Math.round(r.w), height: Math.round(r.h) };
}

/** Shades a selected container's padding so spacing is visible while editing. */
function PaddingGuides({ id }: { id: string }) {
  const el = elementFor(id);
  if (!el || !frame.win) return null;
  const cs = frame.win.getComputedStyle(el);
  const r = el.getBoundingClientRect();
  const sx = frame.win.scrollX;
  const sy = frame.win.scrollY;
  const pt = parseFloat(cs.paddingTop),
    pr = parseFloat(cs.paddingRight),
    pb = parseFloat(cs.paddingBottom),
    pl = parseFloat(cs.paddingLeft);
  const parts = [
    pt > 0 && { x: r.left, y: r.top, w: r.width, h: pt },
    pb > 0 && { x: r.left, y: r.bottom - pb, w: r.width, h: pb },
    pl > 0 && { x: r.left, y: r.top + pt, w: pl, h: r.height - pt - pb },
    pr > 0 && { x: r.right - pr, y: r.top + pt, w: pr, h: r.height - pt - pb },
  ].filter(Boolean) as Array<{ x: number; y: number; w: number; h: number }>;
  return (
    <>
      {parts.map((p, i) => (
        <div key={i} className="uncoder-ui-ov__pad" style={box({ x: p.x + sx, y: p.y + sy, w: p.w, h: p.h })} />
      ))}
    </>
  );
}
