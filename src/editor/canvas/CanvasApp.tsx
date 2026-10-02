// Root rendered INSIDE the canvas iframe (its own React root sharing the editor stores).
import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import type { ElementNode } from '@shared/types';
import { iconSvg } from '../lib/icons';
import { insertElements, lockedBy, useDoc } from '../store/doc';
import { select, useUi, viewZoom } from '../store/ui';
import { handleShortcut } from '../app/shortcuts';
import { handlePasteEvent } from '../app/actions';
import { ElementList } from './ElementView';
import { elementFor, frame, invalidateGeometry, toParent } from './frame';
import { config, schemaOf } from '../lib/config';
import { loadTemplate, useTemplate } from '../store/template';
import { isInlineEditing, startInline, stopInline } from './inline';
import { Overlay } from './Overlay';
import { focusInsertSearch, pick, revealAdded } from '../app/smart';
import { insertImages } from '../app/actions';
import { imageFiles } from '../lib/media';
import { computeDrop } from './dnd';

const INTERACTIVE = 'a, button, input, select, textarea, label, summary, [role="button"], [role="tab"]';

export const STRUCTURES: Array<{ id: string; label: string; cols: number[] }> = [
  { id: '1', label: 'One column', cols: [100] },
  { id: '2', label: 'Two columns', cols: [50, 50] },
  { id: '3', label: 'Three columns', cols: [33.33, 33.33, 33.33] },
  { id: '4', label: 'Four columns', cols: [25, 25, 25, 25] },
  { id: '66-33', label: 'Two thirds + one third', cols: [66.66, 33.33] },
  { id: '33-66', label: 'One third + two thirds', cols: [33.33, 66.66] },
];

export function structureTree(cols: number[]): ElementNode {
  if (cols.length === 1) return { id: '', type: 'container', settings: {}, children: [] };
  return {
    id: '',
    type: 'container',
    settings: { direction: 'row', direction_mobile: 'column', gap: { size: 32, unit: 'px' } },
    children: cols.map((w) => ({ id: '', type: 'container', settings: cols.every((c) => c === cols[0]) ? {} : { width: { size: w, unit: '%' } }, children: [] })),
  };
}

function AddSection({ index, big }: { index: number; big?: boolean }) {
  const [open, setOpen] = useState(!!big);
  const add = (cols: number[]) => {
    const ids = insertElements(null, index, [structureTree(cols)], 'Add section');
    setOpen(!!big);
    revealAdded(ids);
  };
  return (
    <div className={`uncoder-ui-addsec${big ? ' is-big' : ''}`} data-uncoder-ui-chrome="">
      {open ? (
        <div className="uncoder-ui-addsec__panel">
          {big && (
            <div className="uncoder-ui-addsec__intro">
              <strong>Start with a layout</strong>
              <span>Pick a structure, or drag widgets from the left panel. Connect an AI client in AI &amp; MCP to build the whole page for you.</span>
            </div>
          )}
          <div className="uncoder-ui-addsec__grid">
            {STRUCTURES.map((s) => (
              <button key={s.id} type="button" className="uncoder-ui-addsec__struct" onClick={() => add(s.cols)} aria-label={s.label} title={s.label}>
                {s.cols.map((c, i) => (
                  <span key={i} style={{ flexGrow: c }} />
                ))}
              </button>
            ))}
          </div>
          {!big && (
            <button type="button" className="uncoder-ui-addsec__close" onClick={() => setOpen(false)} aria-label="Close" dangerouslySetInnerHTML={{ __html: iconSvg('x', 14) }} />
          )}
        </div>
      ) : (
        <button type="button" className="uncoder-ui-addsec__btn" onClick={() => setOpen(true)} aria-label="Add section">
          <span dangerouslySetInnerHTML={{ __html: iconSvg('plus', 14, 2.4) }} />
          Add section
        </button>
      )}
    </div>
  );
}

/**
 * Popup templates: the canvas shows the dialog as visitors see it — width, padding, corners, background, overlay
 * color, position and close button from the popup settings (canvas.css), updated as they change.
 */
function usePopupFrame() {
  const popup = useTemplate((s) => s.template?.popup);
  const isPopup = config.post.docType === 'popup';
  useEffect(() => {
    if (isPopup) void loadTemplate();
  }, [isPopup]);
  useEffect(() => {
    const html = frame.doc?.documentElement;
    const root = frame.mount;
    if (!isPopup || !html || !root) return;
    const layout = (popup?.layout ?? 'modal').replace('_', '-');
    const classes = ['uncoder-canvas-popup', `uncoder-canvas-popup--${layout}`, `uncoder-canvas-popup--${popup?.position ?? 'center'}`];
    if (popup?.close_button ?? true) classes.push('uncoder-canvas-popup--close');
    html.classList.add(...classes);
    const vars: Record<string, string> = {
      '--uncoder-popup-width': popup ? `${popup.width.size}${popup.width.unit}` : '',
      '--uncoder-popup-bg': popup?.background ?? '',
      '--uncoder-popup-radius': popup ? `${popup.radius}px` : '',
      '--uncoder-popup-pad': popup ? `${popup.padding}px` : '',
    };
    for (const [name, value] of Object.entries(vars)) {
      if (value) root.style.setProperty(name, value);
      else root.style.removeProperty(name);
    }
    if (popup?.overlay && popup.overlay_color) html.style.setProperty('--uncoder-canvas-popup-overlay', popup.overlay_color);
    else html.style.removeProperty('--uncoder-canvas-popup-overlay');
    invalidateGeometry();
    return () => html.classList.remove(...classes);
  }, [isPopup, popup]);
}

export function CanvasApp() {
  const root = useDoc((s) => s.doc.root);
  const device = useUi((s) => s.device);
  usePopupFrame();

  useEffect(() => {
    frame.doc?.documentElement.setAttribute('data-uncoder-ui-device', device);
    invalidateGeometry();
  }, [device]);

  // Selecting something inside a nested widget item (tab, accordion item, slide) reveals that item.
  useEffect(
    () =>
      useUi.subscribe((s, prev) => {
        if (s.selected === prev.selected || !s.selected[0]) return;
        const doc = useDoc.getState().doc;
        let child = s.selected[0];
        let parent = doc.nodes[child]?.parent ?? null;
        const chain: Array<{ widget: string; index: number }> = [];
        while (parent) {
          const p = doc.nodes[parent];
          if (p && schemaOf(p.type)?.nested) chain.unshift({ widget: p.id, index: p.children.indexOf(child) });
          child = parent;
          parent = doc.nodes[parent]?.parent ?? null;
        }
        for (const { widget, index } of chain) {
          if (index < 0) continue;
          elementFor(widget)?.dispatchEvent(new CustomEvent('uncoder:nested-select', { detail: { index }, bubbles: false }));
        }
        if (chain.length) setTimeout(invalidateGeometry, 60);
      }),
    [],
  );

  useEffect(() => {
    const doc = frame.doc!;
    const win = frame.win!;
    let lastHover: string | null = null;

    const closestId = (t: EventTarget | null): string | null => {
      const el = (t as Element | null)?.closest?.('[data-id]') as HTMLElement | null;
      return el && frame.mount?.contains(el) ? el.dataset.id! : null;
    };
    const inChrome = (t: EventTarget | null) => !!(t as Element | null)?.closest?.('.uncoder-ui-ov, [data-uncoder-ui-chrome]');

    const onMove = (e: PointerEvent) => {
      if (useUi.getState().dragging) return;
      const id = inChrome(e.target) ? lastHover : closestId(e.target);
      if (id !== lastHover) {
        lastHover = id;
        useUi.setState({ hovered: id });
      }
    };
    const onLeave = () => {
      lastHover = null;
      useUi.setState({ hovered: null });
    };
    const onClick = (e: MouseEvent) => {
      const target = e.target as Element;
      if (target.closest('.uncoder-ui-inline-editing')) return;
      const addBtn = target.closest('[data-uncoder-ui-add]') as HTMLElement | null;
      if (addBtn) {
        e.preventDefault();
        select(addBtn.getAttribute('data-uncoder-ui-add'));
        focusInsertSearch();
        return;
      }
      if (inChrome(target)) return;
      if (target.closest(INTERACTIVE)) e.preventDefault();
      if (isInlineEditing()) stopInline();
      // The build panel follows: Layers for an element, Insert for an empty container (app/smart.ts).
      pick(closestId(target), e.shiftKey || e.metaKey || e.ctrlKey);
    };
    const onDbl = (e: MouseEvent) => {
      const target = (e.target as Element).closest('[data-uncoder-inline]') as HTMLElement | null;
      const id = closestId(e.target);
      if (!target || !id || lockedBy(id)) return;
      e.preventDefault();
      select(id);
      startInline(id, target, { x: e.clientX, y: e.clientY });
    };
    const onContext = (e: MouseEvent) => {
      if ((e.target as Element).closest('.uncoder-ui-inline-editing')) return;
      e.preventDefault();
      // The element toolbar and the section handle stand for their element.
      const owner = (e.target as Element).closest?.('[data-uncoder-ui-for]') as HTMLElement | null;
      const id = owner ? owner.getAttribute('data-uncoder-ui-for') : closestId(e.target);
      if (id && !useUi.getState().selected.includes(id)) select(id);
      const p = toParent(e.clientX, e.clientY, viewZoom());
      useUi.setState({ contextMenu: { x: p.x, y: p.y, id, source: 'canvas' } });
    };
    const onKey = (e: KeyboardEvent) => {
      if (isInlineEditing()) return;
      handleShortcut(e);
    };
    const onSubmit = (e: Event) => e.preventDefault();
    // Image files dragged from the desktop: show where they will land, upload them on drop.
    const hasFiles = (e: DragEvent) => Array.from(e.dataTransfer?.types ?? []).includes('Files');
    const onDragOver = (e: DragEvent) => {
      if (!hasFiles(e)) return;
      e.preventDefault();
      if (e.dataTransfer) e.dataTransfer.dropEffect = 'copy';
      const drop = computeDrop(e.clientX, e.clientY, { kind: 'new', type: 'image' });
      useUi.setState({ dragging: { kind: 'new', label: 'Images', icon: 'image' }, drop });
    };
    const onDragLeave = (e: DragEvent) => {
      if (!e.relatedTarget) useUi.setState({ dragging: null, drop: null });
    };
    const onDrop = (e: DragEvent) => {
      if (!hasFiles(e)) return;
      e.preventDefault();
      const drop = useUi.getState().drop ?? computeDrop(e.clientX, e.clientY, { kind: 'new', type: 'image' });
      useUi.setState({ dragging: null, drop: null });
      const files = imageFiles(e.dataTransfer?.files);
      if (files.length) insertImages(files, drop ? { parent: drop.parent, index: drop.index } : undefined);
    };
    const onScroll = () => invalidateGeometry();
    const onPointerDown = () => {
      if (useUi.getState().contextMenu) useUi.setState({ contextMenu: null });
    };

    doc.addEventListener('pointermove', onMove);
    doc.documentElement.addEventListener('pointerleave', onLeave);
    doc.addEventListener('click', onClick, true);
    doc.addEventListener('dblclick', onDbl, true);
    doc.addEventListener('contextmenu', onContext, true);
    doc.addEventListener('keydown', onKey);
    doc.addEventListener('paste', handlePasteEvent);
    doc.addEventListener('submit', onSubmit, true);
    doc.addEventListener('dragover', onDragOver);
    doc.addEventListener('dragleave', onDragLeave);
    doc.addEventListener('drop', onDrop);
    doc.addEventListener('pointerdown', onPointerDown, true);
    win.addEventListener('scroll', onScroll, { passive: true });
    return () => {
      doc.removeEventListener('pointermove', onMove);
      doc.documentElement.removeEventListener('pointerleave', onLeave);
      doc.removeEventListener('click', onClick, true);
      doc.removeEventListener('dblclick', onDbl, true);
      doc.removeEventListener('contextmenu', onContext, true);
      doc.removeEventListener('keydown', onKey);
      doc.removeEventListener('paste', handlePasteEvent);
      doc.removeEventListener('submit', onSubmit, true);
      doc.removeEventListener('dragover', onDragOver);
      doc.removeEventListener('dragleave', onDragLeave);
      doc.removeEventListener('drop', onDrop);
      doc.removeEventListener('pointerdown', onPointerDown, true);
      win.removeEventListener('scroll', onScroll);
    };
  }, []);

  return (
    <>
      <ElementList ids={root} depth={0} />
      {root.length === 0 ? <AddSection key="big" index={0} big /> : <AddSection key="end" index={root.length} />}
      {frame.overlay && createPortal(<Overlay />, frame.overlay)}
    </>
  );
}
