import { useRef, useState } from 'react';
import type { ControlDef } from '@shared/types';
import { api } from '../lib/api';
import { config } from '../lib/config';
import { toast } from '../store/ui';
import { Icon } from '../ui/Icon';
import { Popover } from '../ui/Popover';
import { MOD } from '../app/shortcuts';

const TEXT_TYPES = new Set(['text', 'textarea', 'wysiwyg']);
// Technical text fields (ids, URLs, CSS…) get no writing tools.
const TECHNICAL = /(^_|_id$|url|link|class|css|anchor|target|slug|key|icon|html|code|shortcode|selector|format|placeholder_image)/i;
const ACTIONS: Array<{ action: string; label: string; icon: string }> = [
  { action: 'improve', label: 'Improve writing', icon: 'wand-sparkles' },
  { action: 'shorten', label: 'Make shorter', icon: 'fold-vertical' },
  { action: 'expand', label: 'Make longer', icon: 'unfold-vertical' },
  { action: 'fix', label: 'Fix spelling & grammar', icon: 'spell-check' },
];
const TONES = ['professional', 'friendly', 'confident', 'simple', 'persuasive'];

/** Whether a control gets the AI writing button. */
export function aiWritable(control: ControlDef, keyName: string): boolean {
  return !!config.ai?.enabled && TEXT_TYPES.has(control.type) && (control.tab ?? 'content') === 'content' && !TECHNICAL.test(keyName);
}

/** AI writing tools for one text field; the result replaces the text as one undoable change. */
export function AiTextButton({ control, value, onApply }: { control: ControlDef; value: string; onApply: (v: string) => void }) {
  const ref = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState('');
  const [language, setLanguage] = useState('');
  const [instruction, setInstruction] = useState('');

  const run = async (action: string, extra: Record<string, string> = {}) => {
    if (!value.trim()) {
      toast('Write some text first', 'info');
      return;
    }
    setBusy(action);
    try {
      const res = await api<{ text: string }>('ai/text', { body: { action, text: value, html: control.type === 'wysiwyg', ...extra } });
      if (res.text.trim()) {
        onApply(res.text);
        toast(`Text updated — ${MOD}Z to undo`, 'success', undefined, 2400);
        setOpen(false);
      }
    } catch (e) {
      toast(e instanceof Error ? e.message : 'The AI request failed', 'error');
    } finally {
      setBusy('');
    }
  };

  return (
    <>
      <button ref={ref} type="button" className={`uncoder-ui-aibtn${busy ? ' is-busy' : ''}`} aria-label={`AI writing tools for ${control.label ?? 'this field'}`} data-tip="AI writing" onClick={() => setOpen((o) => !o)}>
        <Icon name={busy ? 'loader-circle' : 'sparkles'} size={12} />
      </button>
      <Popover anchor={ref} open={open} onClose={() => setOpen(false)} width={260} placement="bottom-end" label="AI writing">
        <div className="uncoder-ui-aimenu" aria-busy={!!busy}>
          {ACTIONS.map((a) => (
            <button key={a.action} type="button" className="uncoder-ui-menu__item" disabled={!!busy} onClick={() => run(a.action)}>
              <span className="uncoder-ui-menu__icon">
                <Icon name={busy === a.action ? 'loader-circle' : a.icon} size={14} />
              </span>
              <span className="uncoder-ui-menu__label">{a.label}</span>
            </button>
          ))}
          <div className="uncoder-ui-aimenu__label">Tone</div>
          <div className="uncoder-ui-aimenu__chips">
            {TONES.map((t) => (
              <button key={t} type="button" className="uncoder-ui-chip" disabled={!!busy} onClick={() => run('tone', { tone: t })}>
                {busy === 'tone' ? '…' : t}
              </button>
            ))}
          </div>
          <form
            className="uncoder-ui-aimenu__row"
            onSubmit={(e) => {
              e.preventDefault();
              if (language.trim()) run('translate', { language });
            }}
          >
            <input className="uncoder-ui-input" placeholder="Translate to… (e.g. Spanish)" value={language} onChange={(e) => setLanguage(e.currentTarget.value)} aria-label="Translate to language" />
            <button type="submit" className="uncoder-ui-iconbtn" aria-label="Translate" disabled={!!busy || !language.trim()}>
              <Icon name={busy === 'translate' ? 'loader-circle' : 'languages'} size={14} />
            </button>
          </form>
          <form
            className="uncoder-ui-aimenu__row"
            onSubmit={(e) => {
              e.preventDefault();
              if (instruction.trim()) run('custom', { instruction });
            }}
          >
            <input className="uncoder-ui-input" placeholder="Or tell it what to change…" value={instruction} onChange={(e) => setInstruction(e.currentTarget.value)} aria-label="Custom instruction" />
            <button type="submit" className="uncoder-ui-iconbtn" aria-label="Apply instruction" disabled={!!busy || !instruction.trim()}>
              <Icon name={busy === 'custom' ? 'loader-circle' : 'arrow-right'} size={14} />
            </button>
          </form>
          <p className="uncoder-ui-aimenu__note">Sends this field’s text to Anthropic. Check the result: AI can make mistakes.</p>
        </div>
      </Popover>
    </>
  );
}

/** Writes alt text for a library image with AI and saves it on the attachment. */
export async function aiAltText(id: number): Promise<string | null> {
  try {
    const res = await api<{ alt: string; saved: boolean }>('ai/alt', { body: { id, save: true } });
    toast(res.alt ? `Alt text saved: “${res.alt}”` : 'The image looks decorative: alt text left empty', 'success', undefined, 3200);
    return res.alt;
  } catch (e) {
    toast(e instanceof Error ? e.message : 'The AI request failed', 'error');
    return null;
  }
}
