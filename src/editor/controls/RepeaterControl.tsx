import { useEffect, useRef, useState, type KeyboardEvent } from 'react';
import type { ControlDef, Settings } from '@shared/types';
import { schemaOf } from '../lib/config';
import { visible } from '../lib/schema';
import { newId } from '../lib/tree';
import { changeNestedItems, useDoc, type NestedOp } from '../store/doc';
import { Icon } from '../ui/Icon';
import { Button, IconButton } from '../ui/primitives';
import { ControlForm } from './ControlForm';
import type { ControlProps } from './ControlRow';

function rowTitle(row: Settings, control: ControlDef, index: number): string {
  const fields = control.fields ?? {};
  let field = control.title_field ?? Object.keys(fields).find((k) => ['title', 'text', 'label', 'name', 'question'].includes(k));
  // A title field this row hides (e.g. the text of a marquee image item): name the row after its image instead.
  if (field && fields[field] && !visible(fields[field], { ...defaults(fields), ...row }, fields)) {
    const media = Object.keys(fields).find((k) => fields[k].type === 'media' && visible(fields[k], { ...defaults(fields), ...row }, fields) && row[k]?.url);
    if (media) return mediaName(row[media]) || `Item ${index + 1}`;
    field = undefined;
  }
  const raw = field ? row[field] : '';
  // A select field shows its option label ("Reading time"), not the stored value ("reading-time").
  const options = field ? (control.fields?.[field]?.options as Record<string, unknown> | undefined) : undefined;
  const option = options && typeof raw === 'string' ? options[raw] : undefined;
  const label = typeof option === 'string' ? option : option && typeof option === 'object' && typeof (option as { label?: unknown }).label === 'string' ? (option as { label: string }).label : raw;
  const text = typeof label === 'string' ? label.replace(/<[^>]*>/g, '').trim() : '';
  return text || `Item ${index + 1}`;
}

/** "Team photo" from a media value: its alt text, else the file name without size suffix and extension. */
function mediaName(media: { url?: string; alt?: string }): string {
  if (media.alt) return media.alt;
  const file = decodeURIComponent((media.url ?? '').split('/').pop() ?? '');
  return file.replace(/-\d+x\d+(?=\.)/, '').replace(/\.[a-z0-9]+$/i, '').replace(/[-_]+/g, ' ').trim();
}

/** What identifies a row across a move: its _id, or its content for rows saved without one. */
function rowKey(row: Settings): string {
  return typeof row._id === 'string' && row._id ? row._id : JSON.stringify(row);
}

function defaults(fields: Record<string, ControlDef>): Settings {
  const out: Settings = {};
  for (const [k, c] of Object.entries(fields)) if (c.default !== undefined) out[k] = structuredClone(c.default);
  return out;
}

export function RepeaterControl({ control, value, placeholder, onChange, id, keyName }: ControlProps<Settings[]>) {
  const rows: Settings[] = value ?? (placeholder as Settings[]) ?? [];
  const [open, setOpen] = useState<string | null>(null);
  const [dragIndex, setDragIndex] = useState<number | null>(null);
  // Keyboard moves: the row's handle keeps the focus, and screen readers hear the new position.
  const [pendingFocus, setPendingFocus] = useState<{ index: number; key: string } | null>(null);
  const [announce, setAnnounce] = useState('');
  const listRef = useRef<HTMLDivElement>(null);
  const nodeType = useDoc((s) => s.doc.nodes[id]?.type);
  const nested = nodeType ? schemaOf(nodeType)?.nested?.items === keyName : false;
  const fields = control.fields ?? {};

  const emit = (next: Settings[], op: NestedOp) => {
    if (nested) changeNestedItems(id, keyName, next, op);
    else onChange(next);
  };

  const add = () => {
    const row = { ...defaults(fields), _id: newId() };
    emit([...rows, row], { type: 'add', index: rows.length });
    setOpen(row._id);
  };
  const remove = (i: number) => emit(rows.filter((_, j) => j !== i), { type: 'remove', index: i });
  const duplicate = (i: number) => {
    const copy: Settings = { ...structuredClone(rows[i]), _id: newId() };
    // A form field's ID must be unique in its form: the copy goes back to "Automatic" (made from its label).
    if ('field_id' in fields) delete copy.field_id;
    const next = [...rows];
    next.splice(i + 1, 0, copy);
    emit(next, { type: 'duplicate', index: i });
  };
  const move = (from: number, to: number) => {
    if (to < 0 || to >= rows.length || from === to) return;
    const next = [...rows];
    const [r] = next.splice(from, 1);
    next.splice(to, 0, r);
    emit(next, { type: 'move', from, to });
    setAnnounce(`${rowTitle(r, control, to)}: position ${to + 1} of ${rows.length}`);
  };
  /** Arrow keys on a row's handle move the row (Home and End: to the top or the bottom). */
  const onGripKey = (e: KeyboardEvent, i: number) => {
    const to = { ArrowUp: i - 1, ArrowDown: i + 1, Home: 0, End: rows.length - 1 }[e.key];
    if (to === undefined || e.ctrlKey || e.metaKey || e.altKey) return;
    e.preventDefault();
    // Not an editor shortcut (they listen on the window).
    e.stopPropagation();
    if (to < 0 || to >= rows.length || to === i) return;
    move(i, to);
    setPendingFocus({ index: to, key: rowKey(rows[i]) });
  };
  useEffect(() => {
    // Once the list shows the moved row in its new place, put the focus back on its handle (React moves the row's
    // element in the list, which can drop the focus).
    if (!pendingFocus || !rows[pendingFocus.index] || rowKey(rows[pendingFocus.index]) !== pendingFocus.key) return;
    const grips = listRef.current?.querySelectorAll<HTMLElement>(':scope > .uncoder-ui-rep__row > .uncoder-ui-rep__head > .uncoder-ui-rep__grip');
    grips?.[pendingFocus.index]?.focus();
    setPendingFocus(null);
  }, [pendingFocus, rows]);
  const update = (i: number, key: string, v: any) => {
    const next = rows.map((r, j) => {
      if (j !== i) return r;
      const copy = { ...r };
      if (v === undefined) delete copy[key];
      else copy[key] = v;
      return copy;
    });
    if (nested) {
      // Field edits never change the child containers.
      onChange(next);
    } else onChange(next);
  };

  return (
    <div className="uncoder-ui-rep" ref={listRef}>
      {rows.map((row, i) => {
        const rid = String(row._id ?? i);
        const isOpen = open === rid;
        const title = rowTitle(row, control, i);
        return (
          <div
            key={rid}
            className={`uncoder-ui-rep__row${isOpen ? ' is-open' : ''}${dragIndex === i ? ' is-dragging' : ''}`}
            onDragOver={(e) => {
              if (dragIndex === null) return;
              e.preventDefault();
            }}
            onDrop={(e) => {
              e.preventDefault();
              if (dragIndex !== null) move(dragIndex, i);
              setDragIndex(null);
            }}
          >
            <div className="uncoder-ui-rep__head">
              <button
                type="button"
                className="uncoder-ui-rep__grip"
                draggable
                onDragStart={() => setDragIndex(i)}
                onDragEnd={() => setDragIndex(null)}
                onKeyDown={(e) => onGripKey(e, i)}
                aria-label={`Move ${title}, position ${i + 1} of ${rows.length}. Use the arrow keys.`}
                data-tip="Drag, or use the arrow keys, to move"
              >
                <Icon name="grip-vertical" size={13} />
              </button>
              <button type="button" className="uncoder-ui-rep__title" aria-expanded={isOpen} onClick={() => setOpen(isOpen ? null : rid)}>
                {title}
              </button>
              <IconButton icon="copy" label="Duplicate item" size={12} onClick={() => duplicate(i)} />
              <IconButton icon="trash-2" label="Delete item" size={12} tone="danger" onClick={() => remove(i)} />
            </div>
            {isOpen && (
              <div className="uncoder-ui-rep__body">
                <ControlForm controls={fields} values={row} onChange={(k, v) => update(i, k, v)} elementId={id} />
              </div>
            )}
          </div>
        );
      })}
      <Button size="sm" icon="plus" onClick={add} className="uncoder-ui-rep__add">
        Add item
      </Button>
      <span className="screen-reader-text" role="status" aria-live="polite">
        {announce}
      </span>
    </div>
  );
}
