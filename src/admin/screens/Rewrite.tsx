// "Make it yours" (Site\Starter_Rewrite, Pro): the owner's own AI app, connected over MCP, rewrites the texts of an
// imported starter for their business. Uncoder needs no AI key and calls no AI service: it keeps the business
// details, keeps a copy of every page (Undo), and hands over the instruction to paste into the AI app. Two steps:
// the business, then the hand-over with the pages as the AI app rewrites them. Opened from Uncoder → Starter sites.
import { useEffect, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button } from '@editor/ui/primitives';
import { api } from '../lib/api';
import { plural } from '../lib/format';
import { toast, toastError } from '../lib/toast';
import { Badge, Callout, Checkbox, CopyButton, SkeletonRows } from '../ui/kit';
import { confirmDialog, Dialog } from '../ui/Dialog';

interface Profile {
  name: string;
  description: string;
  location: string;
  audience: string;
  services: string;
  phone: string;
  email: string;
  address: string;
  hours: string;
  tone: string;
  language: string;
  notes: string;
}
interface Doc {
  id: number;
  title: string;
  kind: string;
  status: string;
  /** starter: no copy kept yet · ready: copy kept, not changed yet · rewritten: changed since the copy. */
  state: 'starter' | 'ready' | 'rewritten';
  placeholders: string[];
  edit: string;
  url: string;
}
interface State {
  allowed: boolean;
  profile: Profile;
  tones: string[];
  documents: Doc[];
  /** AI apps that can reach the site (connected apps, connection links, API keys); 0 when the MCP server is off. */
  apps: number;
  connect: string;
  prompt: string;
  pricing: string;
}

type Step = 'business' | 'handoff';
const TONE_LABEL: Record<string, string> = { professional: 'Professional', friendly: 'Friendly', confident: 'Confident', simple: 'Simple and plain', persuasive: 'Persuasive' };
const POLL_MS = 8000;

export function RewriteDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [state, setState] = useState<State | null>(null);
  const [error, setError] = useState<Error | null>(null);
  const [step, setStep] = useState<Step>('business');
  const [p, setP] = useState<Profile | null>(null);
  const [site, setSite] = useState({ title: true, business: true });
  const [prompt, setPrompt] = useState('');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!open) return;
    setError(null);
    api<State>('rewrite')
      .then((s) => {
        setState(s);
        setP(s.profile);
        // Started before (copies kept): straight to the hand-over, where the pages show how far the AI app got.
        const started = s.documents.some((d) => d.state !== 'starter') && s.prompt !== '';
        setPrompt(s.prompt);
        setStep(started ? 'handoff' : 'business');
      })
      .catch(setError);
  }, [open]);

  // While the AI app works, the page list follows along.
  useEffect(() => {
    if (!open || step !== 'handoff') return;
    const timer = window.setInterval(() => {
      if (document.hidden) return;
      api<State>('rewrite')
        .then((s) => setState((old) => (old ? { ...old, documents: s.documents, apps: s.apps } : s)))
        .catch(() => undefined);
    }, POLL_MS);
    return () => window.clearInterval(timer);
  }, [open, step]);

  const set = (k: keyof Profile, v: string) => setP((x) => (x ? { ...x, [k]: v } : x));
  const docs = state?.documents ?? [];
  const canGo = !!p && p.name.trim() !== '' && p.description.trim() !== '';
  const rewritten = docs.filter((d) => d.state === 'rewritten');
  const placeholders = docs.reduce((n, d) => n + d.placeholders.length, 0);
  const undoable = docs.filter((d) => d.state !== 'starter');

  const prepare = async () => {
    if (!p) return;
    setBusy(true);
    try {
      const r = await api<{ prompt: string; kept: number; documents: Doc[] }>('rewrite/prepare', { body: { profile: p, ...site } });
      setPrompt(r.prompt);
      setState((s) => (s ? { ...s, documents: r.documents } : s));
      setStep('handoff');
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(false);
    }
  };

  const undo = async (d: Doc | null) => {
    const ok = await confirmDialog({
      title: d ? `Put “${d.title}” back?` : 'Put every page back?',
      body: d
        ? 'It gets the starter’s texts again, as before the rewrite. Changes made to it since then are lost.'
        : 'Every page and site part gets the starter’s texts again, as before the rewrite. Changes made since then are lost.',
      confirmLabel: d ? 'Put it back' : 'Put them all back',
      danger: true,
    });
    if (!ok) return;
    try {
      const r = await api<{ undone: number; documents: Doc[] }>('rewrite/undo', { body: d ? { id: d.id } : { all: true } });
      setState((s) => (s ? { ...s, documents: r.documents } : s));
      toast(d ? `“${d.title}” is back to the starter’s texts.` : `${plural(r.undone, 'page is', 'pages are')} back to the starter’s texts.`, 'success');
    } catch (e) {
      toastError(e);
    }
  };

  const footer =
    !state || !p ? null : !state.allowed ? (
      <Button onClick={onClose}>Close</Button>
    ) : step === 'business' ? (
      <>
        <Button onClick={onClose}>Cancel</Button>
        <Button variant="primary" iconRight="arrow-right" disabled={!canGo || busy} onClick={() => void prepare()}>
          {busy ? 'Preparing…' : 'Continue'}
        </Button>
      </>
    ) : (
      <>
        <Button icon="arrow-left" onClick={() => setStep('business')}>
          Edit the details
        </Button>
        {undoable.length > 0 && (
          <Button variant="ghost" icon="undo-2" onClick={() => void undo(null)}>
            Undo all
          </Button>
        )}
        <Button variant="primary" onClick={onClose}>
          Done
        </Button>
      </>
    );

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title="Make it yours with your AI app"
      description={
        step === 'business'
          ? 'Tell your AI app about your business. It rewrites every text of the starter for it; layout, images and styles stay.'
          : 'Paste the instruction into the AI app connected to this site. The pages below change as it works.'
      }
      width={760}
      footer={footer}
    >
      {error ? (
        <Callout tone="danger" title="Could not load">
          {error.message}
        </Callout>
      ) : !state || !p ? (
        <SkeletonRows rows={6} />
      ) : !state.allowed ? (
        <Callout
          tone="info"
          title="“Make it yours” comes with Pro and Agency"
          actions={
            <a className="uncoder-ui-btn uncoder-ui-btn--sm uncoder-ui-btn--primary" href={state.pricing} target="_blank" rel="noopener noreferrer">
              See the plans
            </a>
          }
        >
          The AI app you already use, such as Claude or ChatGPT, rewrites every text of your starter site for your business in a few minutes. No AI key needed.
        </Callout>
      ) : step === 'business' ? (
        <div className="uncoder-ui-stack">
          <div className="uncoder-ui-rw__form">
            <Field label="Business name" required>
              <input className="uncoder-ui-input" value={p.name} maxLength={120} onChange={(e) => set('name', e.currentTarget.value)} placeholder="Northlight Dental" autoFocus />
            </Field>
            <Field label="Location or area served">
              <input className="uncoder-ui-input" value={p.location} maxLength={160} onChange={(e) => set('location', e.currentTarget.value)} placeholder="Leeds and West Yorkshire" />
            </Field>
            <Field label="What you do" required wide help="Two or three sentences: what you offer, what makes you different.">
              <textarea className="uncoder-ui-input" rows={3} value={p.description} maxLength={800} onChange={(e) => set('description', e.currentTarget.value)} />
            </Field>
            <Field label="Services or products" wide help="One per line, with a few words each if you like.">
              <textarea className="uncoder-ui-input" rows={3} value={p.services} maxLength={1200} onChange={(e) => set('services', e.currentTarget.value)} />
            </Field>
            <Field label="Your customers">
              <input className="uncoder-ui-input" value={p.audience} maxLength={300} onChange={(e) => set('audience', e.currentTarget.value)} placeholder="Families and busy professionals" />
            </Field>
            <Field label="Tone">
              <select className="uncoder-ui-input" value={p.tone} onChange={(e) => set('tone', e.currentTarget.value)}>
                {state.tones.map((t) => (
                  <option key={t} value={t}>
                    {TONE_LABEL[t] ?? t}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Phone">
              <input className="uncoder-ui-input" value={p.phone} maxLength={40} onChange={(e) => set('phone', e.currentTarget.value)} />
            </Field>
            <Field label="Email">
              <input className="uncoder-ui-input" type="email" value={p.email} onChange={(e) => set('email', e.currentTarget.value)} />
            </Field>
            <Field label="Address">
              <input className="uncoder-ui-input" value={p.address} maxLength={200} onChange={(e) => set('address', e.currentTarget.value)} />
            </Field>
            <Field label="Language">
              <input className="uncoder-ui-input" value={p.language} maxLength={40} onChange={(e) => set('language', e.currentTarget.value)} />
            </Field>
            <Field label="Opening hours" help="One line per day or range.">
              <textarea className="uncoder-ui-input" rows={2} value={p.hours} maxLength={300} onChange={(e) => set('hours', e.currentTarget.value)} />
            </Field>
            <Field label="Anything else" help="Prices, years in business, awards: only what is true. Without them the AI app leaves [placeholders].">
              <textarea className="uncoder-ui-input" rows={2} value={p.notes} maxLength={600} onChange={(e) => set('notes', e.currentTarget.value)} />
            </Field>
          </div>
          <div className="uncoder-ui-rw__site">
            <Checkbox checked={site.title} onChange={(v) => setSite({ ...site, title: v })} label="Use the business name as the site title" />
            <Checkbox checked={site.business} onChange={(v) => setSite({ ...site, business: v })} label="Fill the empty Business & SEO details" />
          </div>
          <p className="uncoder-ui-muted">Continuing keeps a copy of every page first, so you can put any page back afterwards.</p>
        </div>
      ) : (
        <div className="uncoder-ui-stack">
          {state.apps === 0 && (
            <Callout
              tone="warning"
              title="Connect your AI app first"
              actions={
                <a className="uncoder-ui-btn uncoder-ui-btn--sm uncoder-ui-btn--primary" href={state.connect} target="_blank" rel="noopener noreferrer">
                  Connect an AI app
                </a>
              }
            >
              Claude, ChatGPT, Cursor or another app that supports MCP. It works with your own account in that app: no AI key in WordPress, and nothing to pay us for it.
            </Callout>
          )}
          <div className="uncoder-ui-rw__handoff">
            <div className="uncoder-ui-rw__handoff-head">
              <span className="uncoder-ui-fld__label">Paste this into your AI app</span>
              <CopyButton text={prompt} label="Copy the instruction" what="The instruction" variant="primary" />
            </div>
            <textarea className="uncoder-ui-input uncoder-ui-rw__prompt" readOnly rows={8} value={prompt} aria-label="The instruction for your AI app" onFocus={(e) => e.currentTarget.select()} />
            <p className="uncoder-ui-muted">In Claude you can also pick the prompt “Make a starter site yours” from this site’s connector and type your business in it.</p>
          </div>
          <ul className="uncoder-ui-rw__progress" aria-live="polite">
            {docs.map((d) => (
              <li key={d.id}>
                <Icon name={d.state === 'rewritten' ? 'circle-check' : 'circle'} size={16} className={`uncoder-ui-rw__icon is-${d.state === 'rewritten' ? 'done' : 'waiting'}`} />
                <span className="uncoder-ui-rw__name">
                  {d.title} <span className="uncoder-ui-muted">· {d.kind}</span>
                </span>
                <span className="uncoder-ui-rw__state">
                  {d.state === 'rewritten' ? <Badge tone="success">Rewritten</Badge> : <span className="uncoder-ui-muted">Starter text</span>}
                  {d.placeholders.length > 0 && (
                    <span className="uncoder-ui-muted" title={d.placeholders.join(' ')}>
                      {' '}
                      · {plural(d.placeholders.length, 'placeholder', 'placeholders')}
                    </span>
                  )}
                </span>
                <span className="uncoder-ui-rw__acts">
                  <a className="uncoder-ui-btn uncoder-ui-btn--sm uncoder-ui-btn--secondary" href={d.edit} target="_blank" rel="noreferrer">
                    Open
                  </a>
                  {d.state === 'rewritten' && (
                    <Button size="sm" variant="ghost" onClick={() => void undo(d)}>
                      Undo
                    </Button>
                  )}
                </span>
              </li>
            ))}
          </ul>
          {rewritten.length > 0 && (
            <Callout tone={placeholders ? 'info' : 'success'} title={`${plural(rewritten.length, 'page', 'pages')} of ${docs.length} rewritten`}>
              {placeholders > 0 && <>Fill in the {plural(placeholders, 'placeholder', 'placeholders')} in square brackets, like [Client name]: search for “[” with Find &amp; replace (Settings → Tools). </>}
              Swap the photos for your own, then read each page once before publishing.
            </Callout>
          )}
        </div>
      )}
    </Dialog>
  );
}

function Field({ label, help, required, wide, children }: { label: string; help?: string; required?: boolean; wide?: boolean; children: React.ReactNode }) {
  return (
    <label className={`uncoder-ui-fld uncoder-ui-rw__field${wide ? ' is-wide' : ''}`}>
      <span className="uncoder-ui-fld__label">
        {label}
        {required && <span className="uncoder-ui-muted"> (required)</span>}
      </span>
      {children}
      {help && <span className="uncoder-ui-fld__help">{help}</span>}
    </label>
  );
}
