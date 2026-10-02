import { useState } from 'react';
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

function defaults(fields: Record<string, ControlDef>): Settings {
  const out: Settings = {};
  for (const [k, c] of Object.entries(fields)) if (c.default !== undefined) out[k] = structuredClone(c.default);
  return out;
}

export function RepeaterControl({ control, value, placeholder, onChange, id, keyName }: ControlProps<Settings[]>) {
  const rows: Settings[] = value ?? (placeholder as Settings[]) ?? [];
  const [open, setOpen] = useState<string | null>(null);
  const [dragIndex, setDragIndex] = useState<number | null>(null);
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
    const copy = { ...structuredClone(rows[i]), _id: newId() };
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
  };
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
    <div className="uncoder-ui-rep">
      {rows.map((row, i) => {
        const rid = String(row._id ?? i);
        const isOpen = open === rid;
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
              <span className="uncoder-ui-rep__grip" draggable onDragStart={() => setDragIndex(i)} onDragEnd={() => setDragIndex(null)} aria-label="Drag to reorder">
                <Icon name="grip-vertical" size={13} />
              </span>
              <button type="button" className="uncoder-ui-rep__title" aria-expanded={isOpen} onClick={() => setOpen(isOpen ? null : rid)}>
                {rowTitle(row, control, i)}
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
    </div>
  );
}
