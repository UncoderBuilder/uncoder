// Notes: comments for the team on elements of this page — write one for the selected element, jump to the
// element a note is about, resolve or delete it.
import { useEffect, useRef, useState } from 'react';
import { schemaOf } from '../lib/config';
import { useDoc } from '../store/doc';
import { addNote, deleteNote, setResolved, useNotes, type Note } from '../store/notes';
import { useUi, showPanel } from '../store/ui';
import { pick, scrollToElement } from '../app/smart';
import { Icon } from '../ui/Icon';
import { Button, IconButton } from '../ui/primitives';

function ago(unix: number): string {
  const s = Math.round(Date.now() / 1000 - unix);
  if (s < 60) return 'just now';
  if (s < 3600) return `${Math.round(s / 60)} min ago`;
  if (s < 86400) return `${Math.round(s / 3600)} h ago`;
  return new Date(unix * 1000).toLocaleDateString([], { day: 'numeric', month: 'short' });
}

export function NotesPanel() {
  const notes = useNotes((s) => s.notes);
  const composeFor = useNotes((s) => s.composeFor);
  const selected = useUi((s) => s.selected[0] ?? null);
  const nodes = useDoc((s) => s.doc.nodes);
  const [text, setText] = useState('');
  const [busy, setBusy] = useState(false);
  const [showResolved, setShowResolved] = useState(false);
  const box = useRef<HTMLTextAreaElement>(null);
  const target = composeFor && nodes[composeFor] ? composeFor : selected;
  const name = (id: string) => (nodes[id] ? nodes[id].label || schemaOf(nodes[id].type)?.title || nodes[id].type : 'Deleted element');

  useEffect(() => {
    if (composeFor) box.current?.focus();
  }, [composeFor]);

  const submit = async () => {
    if (!target || !text.trim()) return;
    setBusy(true);
    if (await addNote(target, text.trim())) setText('');
    setBusy(false);
    useNotes.setState({ composeFor: null });
  };
  const open = (notes ?? []).filter((n) => !n.resolved);
  const resolved = (notes ?? []).filter((n) => n.resolved);
  const go = (n: Note) => {
    if (!nodes[n.element]) return;
    pick(n.element);
    showPanel('notes');
    scrollToElement(n.element);
  };

  const item = (n: Note) => (
    <div key={n.id} className={`uncoder-ui-note-item${n.resolved ? ' is-resolved' : ''}${n.element === selected ? ' is-current' : ''}`}>
      <div className="uncoder-ui-note-item__head">
        {n.avatar ? <img src={n.avatar} alt="" width={20} height={20} /> : <Icon name="user" size={14} />}
        <strong>{n.author}</strong>
        <span>{ago(n.time)}</span>
      </div>
      <button type="button" className="uncoder-ui-note-item__on" disabled={!nodes[n.element]} onClick={() => go(n)}>
        <Icon name="crosshair" size={12} /> {name(n.element)}
      </button>
      <p className="uncoder-ui-note-item__text">{n.text}</p>
      <div className="uncoder-ui-note-item__actions">
        <Button size="sm" icon={n.resolved ? 'rotate-ccw' : 'check'} onClick={() => setResolved(n.id, !n.resolved)}>
          {n.resolved ? 'Reopen' : 'Resolve'}
        </Button>
        {n.canDelete && <IconButton icon="trash-2" label="Delete note" size={13} onClick={() => deleteNote(n.id)} />}
      </div>
    </div>
  );

  return (
    <div className="uncoder-ui-notes">
      <div className="uncoder-ui-notes__compose">
        {target ? (
          <>
            <label className="uncoder-ui-field__label" htmlFor="uncoder-ui-note-text">
              Note on <strong>{name(target)}</strong>
            </label>
            <textarea
              id="uncoder-ui-note-text"
              ref={box}
              className="uncoder-ui-input"
              rows={3}
              value={text}
              placeholder="e.g. Swap this photo for the new one from the shoot"
              onChange={(e) => setText(e.currentTarget.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) submit();
              }}
            />
            <Button variant="primary" size="sm" icon="message-square-plus" loading={busy} disabled={!text.trim()} onClick={submit}>
              Add note
            </Button>
          </>
        ) : (
          <p className="uncoder-ui-note">Select an element on the canvas to leave a note on it.</p>
        )}
      </div>
      {notes === null && <p className="uncoder-ui-note">Loading notes…</p>}
      {notes && !open.length && <p className="uncoder-ui-note">No open notes. Notes are for your team: everyone who can edit this page sees them; visitors never do.</p>}
      {open.map(item)}
      {resolved.length > 0 && (
        <button type="button" className="uncoder-ui-link uncoder-ui-notes__toggle" onClick={() => setShowResolved((v) => !v)}>
          {showResolved ? 'Hide' : 'Show'} resolved ({resolved.length})
        </button>
      )}
      {showResolved && resolved.map(item)}
    </div>
  );
}
