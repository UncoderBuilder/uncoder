// Hover/selection outlines, element toolbar and drop indicators, drawn inside the canvas document.
import { useEffect, useLayoutEffect, useReducer, useRef, useState } from 'react';
import { config, schemaOf } from '../lib/config';
import { iconSvg } from '../lib/icons';
import { duplicateElement, insertElements, lockedBy, removeElements, toggleLocked, useDoc } from '../store/doc';
import { select, useUi, showPanel } from '../store/ui';
import { beginDrag } from './dnd';
import { elementFor, frame, onGeometry, toParent } from './frame';
import { focusInsertSearch, pick, revealAdded } from '../app/smart';
import { openTemplate } from '../app/actions';
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

/**
 * Keeps the overlay layer exactly as big as the page and clips what pokes out of it. Toolbars, handles and badges at
 * an edge then never add scrollbars to the canvas (a scrollbar narrowed the page, the selection moved, the scrollbar
 * went away again: the canvas shook).
 */
function fitLayer(): void {
  const root = frame.overlay;
  const doc = frame.doc;
  if (!root || !doc) return;
  const page = (doc.scrollingElement ?? doc.documentElement) as HTMLElement;
  // Collapsed, the layer adds nothing: what is left is the page's own size.
  root.style.width = '0px';
  root.style.height = '0px';
  const w = page.scrollWidth;
  const h = page.scrollHeight;
  root.style.width = `${w}px`;
  root.style.height = `${h}px`;
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

  useLayoutEffect(fitLayer);
  const [barRight, setBarRight] = useState(false);

  const primary = selected[0] ?? null;
  const selNode = primary ? nodes[primary] : null;
  const hoverNode = hovered ? nodes[hovered] : null;
  const hoverRect = !dragging && hovered && !selected.includes(hovered) ? rectOf(hovered) : null;
  const toolbarRef = useRef<HTMLDivElement>(null);
  const nameRef = useRef<HTMLSpanElement>(null);
  const actsRef = useRef<HTMLSpanElement>(null);
  // The name tag sits at the selection's top-left and the actions at its top-right; on an element too narrow for
  // both, the actions follow the name tag instead.
  const [tight, setTight] = useState(false);

  const selRects = selected.map((id) => ({ id, rect: rectOf(id) }));
  const primaryRect = selRects[0]?.rect ?? null;
  const parentOfSel = selNode?.parent ? nodes[selNode.parent] : null;
  const parentRect = parentOfSel && selected.length === 1 ? rectOf(parentOfSel.id) : null;
  const isSlot = !!(parentOfSel && schemaOf(parentOfSel.type)?.nested);
  const selLock = primary ? lockedBy(primary) : null;

  // The toolbar starts at the selection's left edge; when that runs past the right edge of the page, it ends at the
  // selection's right edge instead. Its width does not depend on the side, so the choice is stable.
  useLayoutEffect(() => {
    const bar = toolbarRef.current;
    const win = frame.win;
    const vw = frame.doc?.documentElement.clientWidth ?? 0;
    if (!bar || !win || !primaryRect || !vw) return;
    const w = bar.getBoundingClientRect().width;
    const left = primaryRect.x - win.scrollX - 2;
    const right = left + 4 + primaryRect.w;
    const flip = left + w > vw && right - w >= 0;
    if (flip !== barRight) setBarRight(flip);
    const need = (nameRef.current?.getBoundingClientRect().width ?? 0) + (actsRef.current?.getBoundingClientRect().width ?? 0) + 12;
    const t = need > primaryRect.w;
    if (t !== tight) setTight(t);
  });

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
  // Only while the pointer is over the section (a selection inside it has its own toolbar).
  const sectionId = !dragging && !editing ? sectionOf(hovered) : null;
  const sectionRect = sectionId ? rectOf(sectionId) : null;
  const root = useDoc.getState().doc.root;
  const template = useTemplateHover();
  const notes = useNotes((s) => s.notes);
  const noted = new Map<string, number>();
  for (const n of notes ?? []) if (!n.resolved && nodes[n.element]) noted.set(n.element, (noted.get(n.element) ?? 0) + 1);

  const acts = (id: string) => (
                  <span className="uncoder-ui-ov__acts" ref={actsRef}>
                  {selLock && (
                    <button type="button" className="uncoder-ui-ov__btn is-locked" title={selLock === id ? 'Locked · click to unlock' : 'Inside a locked element · click to select it'} onClick={() => (selLock === id ? toggleLocked(id) : pick(selLock))}>
                      <Svg name="lock" />
                    </button>
                  )}
                  {parentOfSel && (
                    <button type="button" className="uncoder-ui-ov__btn" title="Select parent (Esc)" aria-label="Select parent" onClick={() => pick(parentOfSel.id)}>
                      <Svg name="arrow-up" />
                    </button>
                  )}
                  {isContainer(id) && (
                    <button type="button" className="uncoder-ui-ov__btn" title="Insert inside" aria-label="Insert inside" onClick={() => focusInsertSearch()}>
                      <Svg name="plus" />
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
                  </span>
  );

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
            <div key={id} className={`uncoder-ui-ov__sel${isContainer(id) ? ' is-container' : ''}${editing === id ? ' is-editing' : ''}${rect.y < 40 ? (rect.h < 160 ? ' is-top is-below' : ' is-top') : ''}${i === 0 && barRight ? ' is-bar-right' : ''}`} style={box(rect)}>
              {i === 0 && editing !== id && (
                <>
                <div className="uncoder-ui-ov__bar" ref={toolbarRef} data-uncoder-ui-for={id}>
                  {/* The name tag is also the handle: drag it to move the selection. */}
                  <span
                    ref={nameRef}
                    className={`uncoder-ui-ov__name${!isSlot && !selLock ? ' is-grip' : ''}`}
                    title={!isSlot && !selLock ? 'Drag to move' : undefined}
                    onPointerDown={
                      !isSlot && !selLock
                        ? (e) => {
                            if (e.button !== 0) return;
                            e.preventDefault();
                            e.stopPropagation();
                            dragOnMove(e.nativeEvent, selected, title(id), schemaOf(nodes[id]?.type)?.icon ?? 'box', zoom);
                          }
                        : undefined
                    }
                  >
                    {title(id)}
                    {nodes[id]?.type === 'heading' && headingTag(id) && <span className="uncoder-ui-ov__tagname">{headingTag(id)}</span>}
                  </span>
                  {tight && acts(id)}
                </div>
                {!tight && (
                  <div className="uncoder-ui-ov__bar is-acts" data-uncoder-ui-for={id}>
                    {acts(id)}
                  </div>
                )}
                </>
              )}
            </div>
          ) : null,
        )}

      {sectionId && sectionRect && (
        <div className="uncoder-ui-ov__sec" style={{ transform: `translate(${Math.round(sectionRect.x + sectionRect.w / 2)}px, ${Math.round(sectionRect.y)}px)` }}>
          {/* Inside the section's top edge, so it never covers the site's own header above it. */}
          <button
            type="button"
            className="uncoder-ui-ov__secadd"
            title="Add a section above this one"
            onClick={() => {
              const ids = insertElements(null, Math.max(0, root.indexOf(sectionId)), [{ id: '', type: 'container', settings: {}, children: [] }], 'Add section');
              revealAdded(ids);
            }}
          >
            <Svg name="plus" size={12} />
            Section
          </button>
        </div>
      )}

      {!dragging &&
        [...noted].map(([nid, count]) => {
          const r = rectOf(nid);
          // Inside the page, also for full-width elements.
          const maxX = (frame.win?.scrollX ?? 0) + (frame.doc?.documentElement.clientWidth ?? Infinity) - 34;
          return r ? (
            <button
              key={`note-${nid}`}
              type="button"
              className="uncoder-ui-ov__note"
              title={`${count} open note${count === 1 ? '' : 's'}`}
              style={{ translate: `${Math.round(Math.min(r.x + r.w - 10, maxX))}px ${Math.round(Math.max(r.y - 10, 0))}px` }}
              onClick={() => {
                select(nid);
                showPanel('notes');
              }}
            >
              <Svg name="message-square" size={11} />
              {count}
            </button>
          ) : null;
        })}
      {!dragging && template && <TemplateHover el={template} />}
      {dragging && drop?.box && <div className="uncoder-ui-ov__target" style={box(drop.box)} />}
      {dragging && drop?.line && <div className={`uncoder-ui-ov__line${drop.line.w <= 4 ? ' is-vertical' : ''}`} style={box(drop.line)} />}
      {primaryRect && !dragging && selNode && isContainer(primary!) && <PaddingGuides id={primary!} />}
      {primaryRect && !dragging && selNode && selected.length === 1 && !editing && !selLock && showHandles && <Handles id={primary!} />}
    </div>
  );
}

/** The name tag as a handle: moving the pointer a few pixels while pressed drags the selection; a click does nothing. */
function dragOnMove(down: PointerEvent, ids: string[], label: string, icon: string, zoom: number) {
  const doc = (down.target as Node).ownerDocument ?? document;
  const done = () => {
    doc.removeEventListener('pointermove', move, true);
    doc.removeEventListener('pointerup', done, true);
  };
  const move = (e: PointerEvent) => {
    if (Math.hypot(e.clientX - down.clientX, e.clientY - down.clientY) < 4) return;
    done();
    const p = toParent(e.clientX, e.clientY, zoom);
    beginDrag({ clientX: p.x, clientY: p.y }, { kind: 'move', ids }, label, icon);
  };
  doc.addEventListener('pointermove', move, true);
  doc.addEventListener('pointerup', done, true);
}

/** "h1"…"h6" for a heading (the level matters for SEO and is easy to get wrong), else nothing. */
function headingTag(id: string): string {
  const el = elementFor(id);
  if (!el) return '';
  const h = el.matches('h1,h2,h3,h4,h5,h6') ? el : el.querySelector(':scope > :is(h1,h2,h3,h4,h5,h6)');
  return h ? h.tagName.toLowerCase() : '';
}

/**
 * The header or footer around the page (another template, shown but not editable here) under the pointer. The page
 * marks them with data-uncoder-edit-title (Theme_Builder::canvas_attrs, canvas only).
 */
function useTemplateHover(): HTMLElement | null {
  const [el, setEl] = useState<HTMLElement | null>(null);
  useEffect(() => {
    const doc = frame.doc;
    if (!doc) return;
    const onMove = (e: PointerEvent) => {
      const t = e.target as Element | null;
      // Over its own button: keep it.
      if (t?.closest?.('.uncoder-ui-ov__tpl')) return;
      const hit = (t?.closest?.('[data-uncoder-edit-title]') as HTMLElement | null) ?? null;
      const other = hit && hit.getAttribute('data-uncoder-doc') !== String(config.post.id) ? hit : null;
      setEl((cur) => (cur === other ? cur : other));
    };
    const onLeave = () => setEl(null);
    doc.addEventListener('pointermove', onMove, true);
    doc.documentElement.addEventListener('pointerleave', onLeave);
    return () => {
      doc.removeEventListener('pointermove', onMove, true);
      doc.documentElement.removeEventListener('pointerleave', onLeave);
    };
  }, []);
  return el;
}

/** Outline and "Edit Header · Main header": opens that template in the editor (unsaved changes are asked about). */
function TemplateHover({ el }: { el: HTMLElement }) {
  if (!el.isConnected || !frame.win) return null;
  const r = el.getBoundingClientRect();
  const rect = { x: r.left + frame.win.scrollX, y: r.top + frame.win.scrollY, w: r.width, h: r.height };
  const id = el.getAttribute('data-uncoder-doc') ?? '';
  const kind = el.getAttribute('data-uncoder-edit-kind') || 'Template';
  const title = el.getAttribute('data-uncoder-edit-title') || '';
  const open = () => void openTemplate(id, kind);
  return (
    <div className="uncoder-ui-ov__tplbox" style={box(rect)}>
      <button type="button" className="uncoder-ui-ov__tpl" title={`Open the ${kind.toLowerCase()} in the editor`} onClick={open}>
        <Svg name="pencil" size={12} />
        <span>Edit {kind}</span>
        {title && <span className="uncoder-ui-ov__tplname">{title}</span>}
      </button>
    </div>
  );
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
