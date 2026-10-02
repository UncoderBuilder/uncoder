import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Icon } from '../ui/Icon';
import type { ControlProps } from './ControlRow';

const TOOLS: Array<{ cmd: string; arg?: string; icon: string; label: string }> = [
  { cmd: 'formatBlock', arg: 'p', icon: 'pilcrow', label: 'Paragraph' },
  { cmd: 'formatBlock', arg: 'h2', icon: 'heading-2', label: 'Heading 2' },
  { cmd: 'formatBlock', arg: 'h3', icon: 'heading-3', label: 'Heading 3' },
  { cmd: 'bold', icon: 'bold', label: 'Bold' },
  { cmd: 'italic', icon: 'italic', label: 'Italic' },
  { cmd: 'insertUnorderedList', icon: 'list', label: 'Bulleted list' },
  { cmd: 'insertOrderedList', icon: 'list-ordered', label: 'Numbered list' },
  { cmd: 'formatBlock', arg: 'blockquote', icon: 'text-quote', label: 'Quote' },
  { cmd: 'createLink', icon: 'link', label: 'Link' },
  { cmd: 'removeFormat', icon: 'remove-formatting', label: 'Clear formatting' },
];

export function WysiwygControl({ control, value, placeholder, onChange }: ControlProps<string>) {
  const ref = useRef<HTMLDivElement>(null);
  const [html, setHtml] = useState(false);
  // Full screen: the same editor in a large sheet over the editor, for long text. The canvas still updates live.
  const [full, setFull] = useState(false);
  const [source, setSource] = useState(value ?? '');
  const focused = useRef(false);
  const expandRef = useRef<HTMLButtonElement>(null);

  // The editable area is a new element after switching between the panel and full screen: fill it again.
  useEffect(() => {
    if (!focused.current && ref.current && ref.current.innerHTML !== (value ?? placeholder ?? '')) {
      ref.current.innerHTML = (value ?? (placeholder as string) ?? '') as string;
    }
    if (!html) setSource(value ?? '');
  }, [value, placeholder, html, full]);

  // Entering full screen puts the caret at the end of the text; leaving returns focus to the button.
  useEffect(() => {
    if (!full) return;
    const area = ref.current;
    if (area) {
      area.focus();
      const range = document.createRange();
      range.selectNodeContents(area);
      range.collapse(false);
      window.getSelection()?.removeAllRanges();
      window.getSelection()?.addRange(range);
    }
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        switchFull(false);
      }
    };
    window.addEventListener('keydown', onKey, true);
    return () => window.removeEventListener('keydown', onKey, true);
  }, [full]);
  const wasFull = useRef(false);
  useEffect(() => {
    if (wasFull.current && !full) expandRef.current?.focus({ preventScroll: true });
    wasFull.current = full;
  }, [full]);

  const emit = () => {
    if (!ref.current) return;
    onChange(ref.current.innerHTML.replace(/&nbsp;/g, ' '));
  };
  // The area being left is removed without a blur event: save it and let the other one be filled.
  const switchFull = (on: boolean) => {
    emit();
    focused.current = false;
    setFull(on);
  };

  const run = (cmd: string, arg?: string) => {
    ref.current?.focus();
    if (cmd === 'createLink') {
      const url = window.prompt('Link URL', 'https://');
      if (!url) return;
      document.execCommand('createLink', false, url);
    } else document.execCommand(cmd, false, arg);
    emit();
  };

  const editor = (
    <div className={`uncoder-ui-rte${full ? ' uncoder-ui-rte--full' : ''}`}>
      <div className="uncoder-ui-rte__bar" role="toolbar" aria-label="Formatting">
        {TOOLS.map((t) => (
          <button key={t.label} type="button" className="uncoder-ui-rte__btn" data-tip={t.label} aria-label={t.label} onMouseDown={(e) => e.preventDefault()} onClick={() => run(t.cmd, t.arg)} disabled={html}>
            <Icon name={t.icon} size={full ? 16 : 14} />
          </button>
        ))}
        <span className="uncoder-ui-rte__spacer" />
        <button type="button" className={`uncoder-ui-rte__btn${html ? ' is-active' : ''}`} data-tip="Edit HTML" aria-label="Edit HTML" aria-pressed={html} onClick={() => setHtml((h) => !h)}>
          <Icon name="code" size={full ? 16 : 14} />
        </button>
        {full ? (
          <button type="button" className="uncoder-ui-rte__btn uncoder-ui-rte__done" onClick={() => switchFull(false)}>
            <Icon name="minimize-2" size={15} />
            Exit full screen
          </button>
        ) : (
          <button ref={expandRef} type="button" className="uncoder-ui-rte__btn" data-tip="Full screen" aria-label="Edit in full screen" onClick={() => switchFull(true)}>
            <Icon name="maximize-2" size={14} />
          </button>
        )}
      </div>
      {html ? (
        <textarea className="uncoder-ui-textarea uncoder-ui-textarea--code" rows={full ? 24 : 8} value={source} onChange={(e) => setSource(e.currentTarget.value)} onBlur={() => onChange(source)} aria-label={`${control.label} HTML`} />
      ) : (
        <div
          ref={ref}
          className="uncoder-ui-rte__area"
          contentEditable
          suppressContentEditableWarning
          role="textbox"
          aria-multiline
          aria-label={control.label}
          onFocus={() => (focused.current = true)}
          onBlur={() => {
            focused.current = false;
            emit();
          }}
          onInput={emit}
        />
      )}
    </div>
  );

  if (!full) return editor;
  return (
    <>
      <div className="uncoder-ui-rte uncoder-ui-rte--away">
        <Icon name="maximize-2" size={14} />
        <span>Editing in full screen</span>
      </div>
      {createPortal(
        <div className="uncoder-ui-rte-full" role="dialog" aria-modal="true" aria-label={`${control.label}, full screen`}>
          <div className="uncoder-ui-rte-full__backdrop" onClick={() => switchFull(false)} />
          <div className="uncoder-ui-rte-full__sheet">{editor}</div>
        </div>,
        document.querySelector('.uncoder-ui-portal') ?? document.body,
      )}
    </>
  );
}
