// Inline text editing directly on the canvas for controls flagged `inline`.
import { schemaOf } from '../lib/config';
import { mutateSilently, updateSettings, useDoc } from '../store/doc';
import { acceptCurrent } from '../store/render';
import { useUi } from '../store/ui';
import { elementFor, frame, invalidateGeometry } from './frame';

const INLINE_TAGS = new Set(['B', 'STRONG', 'I', 'EM', 'U', 'S', 'MARK', 'BR', 'SPAN', 'A', 'SUP', 'SUB', 'CODE']);
const RICH_TAGS = new Set([...INLINE_TAGS, 'P', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'DIV']);

/** Light client-side cleanup; the server sanitizes again on save. */
function clean(html: string, allowed: Set<string>, doc: Document): string {
  const tpl = doc.createElement('template');
  tpl.innerHTML = html;
  const walk = (node: Node) => {
    for (const child of Array.from(node.childNodes)) {
      if (child.nodeType === 1) {
        const el = child as HTMLElement;
        if (!allowed.has(el.tagName)) {
          // Unwrap unknown tags, keep their text.
          walk(el);
          el.replaceWith(...Array.from(el.childNodes));
          continue;
        }
        for (const attr of Array.from(el.attributes)) {
          if (!(el.tagName === 'A' && (attr.name === 'href' || attr.name === 'target' || attr.name === 'rel')) && attr.name !== 'class') el.removeAttribute(attr.name);
          if (attr.name === 'href' && /^\s*javascript:/i.test(attr.value)) el.removeAttribute('href');
        }
        walk(el);
      } else if (child.nodeType === 8) {
        child.remove();
      }
    }
  };
  walk(tpl.content);
  return tpl.innerHTML.replace(/&nbsp;/g, ' ');
}

let session: null | {
  id: string;
  key: string;
  el: HTMLElement;
  original: any;
  controls: Record<string, any>;
  mode: 'plain' | 'inline' | 'rich';
  singleLine: boolean;
  cleanup: () => void;
} = null;

export function isInlineEditing(): boolean {
  return !!session;
}

/** Inline keys are either a control key ("title") or a repeater field path ("tabs.0.title"). */
function resolvePath(settings: Record<string, any>, controls: Record<string, any>, key: string): { control: any; value: any } | null {
  const parts = key.split('.');
  if (parts.length === 1) {
    const control = controls[key];
    return control ? { control, value: settings[key] ?? control.default ?? '' } : null;
  }
  const [rKey, idx, field] = parts;
  const rep = controls[rKey];
  const control = rep?.fields?.[field];
  if (!control) return null;
  const rows = settings[rKey] ?? rep.default ?? [];
  return { control: { ...control, inline: true }, value: rows?.[Number(idx)]?.[field] ?? control.default ?? '' };
}

function writePath(settings: Record<string, any>, controls: Record<string, any>, key: string, value: any): void {
  const parts = key.split('.');
  if (parts.length === 1) {
    settings[key] = value;
    return;
  }
  const [rKey, idx, field] = parts;
  const rows: any[] = Array.isArray(settings[rKey]) ? settings[rKey] : structuredClone(controls[rKey]?.default ?? []);
  const i = Number(idx);
  if (!rows[i]) return;
  rows[i] = { ...rows[i], [field]: value };
  settings[rKey] = rows;
}

export function startInline(id: string, target: HTMLElement, point?: { x: number; y: number }): boolean {
  const node = useDoc.getState().doc.nodes[id];
  const schema = node && schemaOf(node.type);
  const key = target.getAttribute('data-uncoder-inline') || '';
  const resolved = schema ? resolvePath(node.settings, schema.controls, key) : null;
  const control = resolved?.control;
  const rootKey = key.split('.')[0];
  if (!node || !schema || !control || (!control.inline && !key.includes('.')) || node.dynamic?.[rootKey]) return false;
  stopInline();

  const mode: 'plain' | 'inline' | 'rich' = control.type === 'wysiwyg' ? 'rich' : control.html === 'inline' ? 'inline' : 'plain';
  const singleLine = control.type === 'text';
  const original = resolved!.value;
  const controls = schema.controls;
  const doc = frame.doc!;

  target.setAttribute('contenteditable', mode === 'plain' ? 'plaintext-only' : 'true');
  target.setAttribute('spellcheck', 'true');
  target.classList.add('uncoder-ui-inline-editing');
  useUi.setState({ editingInline: id });
  target.focus();
  if (point) {
    const range = (doc as any).caretRangeFromPoint?.(point.x, point.y) as Range | null;
    if (range) {
      const sel = frame.win!.getSelection();
      sel?.removeAllRanges();
      sel?.addRange(range);
    }
  }

  const read = () => {
    if (mode === 'plain') return target.innerText.replace(/\n+$/, '');
    return clean(target.innerHTML, mode === 'rich' ? RICH_TAGS : INLINE_TAGS, doc);
  };

  const onInput = () => {
    const value = read();
    mutateSilently((d) => {
      const n = d.nodes[id];
      if (n) writePath(n.settings, controls, key, value);
    });
    const updated = useDoc.getState().doc.nodes[id];
    if (updated) acceptCurrent(updated);
    invalidateGeometry();
  };
  const onKey = (e: KeyboardEvent) => {
    e.stopPropagation();
    if (e.key === 'Escape' || (e.key === 'Enter' && singleLine)) {
      e.preventDefault();
      stopInline();
      return;
    }
    if (e.key === 'Enter' && mode === 'inline' && !e.shiftKey) {
      // Headings: Enter inserts a line break instead of a new block.
      e.preventDefault();
      doc.execCommand('insertLineBreak');
    }
    if ((e.metaKey || e.ctrlKey) && ['b', 'i', 'u'].includes(e.key.toLowerCase()) && mode !== 'plain') {
      e.preventDefault();
      doc.execCommand(e.key.toLowerCase() === 'b' ? 'bold' : e.key.toLowerCase() === 'i' ? 'italic' : 'underline');
      onInput();
    }
  };
  const onPaste = (e: ClipboardEvent) => {
    e.preventDefault();
    const text = e.clipboardData?.getData('text/plain') ?? '';
    doc.execCommand('insertText', false, text);
  };
  const onBlur = () => stopInline();

  target.addEventListener('input', onInput);
  target.addEventListener('keydown', onKey);
  target.addEventListener('paste', onPaste);
  target.addEventListener('blur', onBlur);

  session = {
    id,
    key,
    el: target,
    original,
    controls,
    mode,
    singleLine,
    cleanup: () => {
      target.removeEventListener('input', onInput);
      target.removeEventListener('keydown', onKey);
      target.removeEventListener('paste', onPaste);
      target.removeEventListener('blur', onBlur);
      target.removeAttribute('contenteditable');
      target.removeAttribute('spellcheck');
      target.classList.remove('uncoder-ui-inline-editing');
    },
  };
  return true;
}

export function stopInline(): void {
  if (!session) return;
  const { id, key, original, controls, cleanup } = session;
  session = null;
  cleanup();
  const node = useDoc.getState().doc.nodes[id];
  const rootKey = key.split('.')[0];
  const finalRoot = node?.settings[rootKey];
  const final = node ? resolvePath(node.settings, controls, key)?.value : undefined;
  if (node && final !== original) {
    // Turn the silent typing into a single undo step.
    mutateSilently((d) => {
      const n = d.nodes[id];
      if (n) writePath(n.settings, controls, key, original);
    });
    updateSettings(id, { [rootKey]: finalRoot }, { label: 'Edit text', mergeKey: `inline:${id}:${key}:${Date.now()}` });
  }
  const wrapper = elementFor(id);
  const updated = useDoc.getState().doc.nodes[id];
  if (updated && wrapper) {
    // Cache the edited markup, without nested children (those are React portals).
    const clone = wrapper.cloneNode(true) as HTMLElement;
    clone.querySelectorAll('[data-uncoder-slot]').forEach((s) => (s.innerHTML = ''));
    acceptCurrent(updated, clone.innerHTML);
  }
  useUi.setState({ editingInline: null });
}
