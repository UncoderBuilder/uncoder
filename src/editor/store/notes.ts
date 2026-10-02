// Notes on elements (Rest\Notes_Controller): loaded with the editor, shared by everyone who edits the page.
import { create } from 'zustand';
import { api } from '../lib/api';
import { config } from '../lib/config';
import { toast } from './ui';

export interface Note {
  id: string;
  element: string;
  text: string;
  time: number;
  resolved: boolean;
  author: string;
  avatar: string;
  canDelete: boolean;
}

interface NotesState {
  notes: Note[] | null;
  /** Element the Notes view writes a new note for (set by "Add note…"). */
  composeFor: string | null;
}

export const useNotes = create<NotesState>(() => ({ notes: null, composeFor: null }));

const base = () => `documents/${config.post.id}/notes`;

export async function loadNotes(): Promise<void> {
  try {
    useNotes.setState({ notes: await api<Note[]>(base()) });
  } catch {
    useNotes.setState({ notes: [] });
  }
}

export async function addNote(element: string, text: string): Promise<boolean> {
  try {
    const note = await api<Note>(base(), { body: { element, text } });
    useNotes.setState((s) => ({ notes: [...(s.notes ?? []), note] }));
    return true;
  } catch (e) {
    toast(`Could not add the note: ${e instanceof Error ? e.message : 'error'}`, 'error');
    return false;
  }
}

export async function setResolved(id: string, resolved: boolean): Promise<void> {
  const note = await api<Note>(`${base()}/${id}`, { body: { resolved } });
  useNotes.setState((s) => ({ notes: (s.notes ?? []).map((n) => (n.id === id ? note : n)) }));
}

export async function deleteNote(id: string): Promise<void> {
  await api(`${base()}/${id}`, { method: 'DELETE' });
  useNotes.setState((s) => ({ notes: (s.notes ?? []).filter((n) => n.id !== id) }));
}

/** Open notes per element id (canvas badges, Layers). */
export const openCount = (notes: Note[] | null, element: string): number => (notes ?? []).filter((n) => !n.resolved && n.element === element).length;
