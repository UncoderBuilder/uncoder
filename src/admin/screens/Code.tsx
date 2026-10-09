import { useEffect, useMemo, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button, IconButton, Toggle } from '@editor/ui/primitives';
import { NAME } from '@shared/brand';
import { api, cleanConditions, templatesMeta, type Condition, type TemplatesMeta } from '../lib/api';
import { cfg } from '../lib/config';
import { absoluteTime, cx, relativeTime } from '../lib/format';
import { useResource } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { ConditionRow, newRow, toCondition, toRow, type Row } from '../templates/ConditionsDialog';
import { confirmDialog, Drawer } from '../ui/Dialog';
import { Callout, EmptyState, ErrorState, Field, PageHeader, SkeletonRows } from '../ui/kit';

type Location = 'head' | 'body_open' | 'footer';

type Category = 'necessary' | 'analytics' | 'marketing';

interface Snippet {
  id: string;
  name: string;
  category?: Category;
  location: Location;
  priority: number;
  enabled: boolean;
  conditions: Array<Condition & { label?: string }>;
  code: string;
  modified: string;
  summary: string;
  authorName: string;
}

const LOCATIONS: Array<{ value: Location; label: string; help: string }> = [
  { value: 'head', label: 'Head', help: 'Inside <head> — analytics, meta tags, fonts, CSS.' },
  { value: 'body_open', label: 'Body start', help: 'Right after <body> — tag manager noscript fallbacks.' },
  { value: 'footer', label: 'Body end', help: 'Before </body> — chat widgets and scripts that can wait.' },
];
/** Starters for common snippets: the IDs in capitals are placeholders to replace. */
const PRESETS: Array<{ label: string; icon: string; name: string; location: Location; category: Category; code: string; note: string }> = [
  {
    label: 'Google tag (GA4)',
    icon: 'chart-line',
    name: 'Google tag',
    location: 'head',
    category: 'analytics',
    note: 'Replace G-XXXXXXXXXX with your measurement ID (Google Analytics → Admin → Data streams).',
    code: `<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>`,
  },
  {
    label: 'Google Tag Manager',
    icon: 'tags',
    name: 'Google Tag Manager',
    location: 'head',
    category: 'analytics',
    note: 'Replace GTM-XXXXXXX with your container ID. For visitors without JavaScript, add a second snippet at Body start with the <noscript> iframe from Tag Manager.',
    code: `<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-XXXXXXX');</script>`,
  },
  {
    label: 'Meta Pixel',
    icon: 'target',
    name: 'Meta Pixel',
    location: 'head',
    category: 'marketing',
    note: 'Replace YOUR_PIXEL_ID with the ID from Meta Events Manager.',
    code: `<script>
  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
  n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
  document,'script','https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', 'YOUR_PIXEL_ID');
  fbq('track', 'PageView');
</script>`,
  },
  {
    label: 'Custom CSS',
    icon: 'paintbrush',
    name: 'Custom CSS',
    location: 'head',
    category: 'necessary',
    note: 'Site-wide CSS. For one page use Page settings → Page CSS; for one element, its Custom CSS.',
    code: `<style>
  /* Your CSS */
</style>`,
  },
  {
    label: 'Custom JavaScript',
    icon: 'braces',
    name: 'Custom JavaScript',
    location: 'footer',
    category: 'necessary',
    note: 'Runs at the end of the page, after the content has loaded.',
    code: `<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Your code
  });
</script>`,
  },
];

const locationLabel = (l: Location) => LOCATIONS.find((x) => x.value === l)?.label ?? l;

interface Draft {
  id?: string;
  name: string;
  category: Category;
  location: Location;
  priority: number;
  enabled: boolean;
  code: string;
  rows: Row[];
}

const blank = (): Draft => ({ name: '', category: 'necessary', location: 'head', priority: 10, enabled: true, code: '', rows: [newRow(true)] });
const CATEGORY_LABELS: Record<Category, string> = { necessary: 'Necessary', analytics: 'Analytics', marketing: 'Marketing' };

/** Custom Code: HTML / JS / CSS snippets with Theme Builder display conditions. */
export function CodeScreen() {
  const allowed = !!cfg.user.caps.unfiltered_html;
  const list = useResource((signal) => (allowed ? api<Snippet[]>('snippets', { signal }) : Promise.resolve([])), [allowed]);
  const [meta, setMeta] = useState<TemplatesMeta | null>(null);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [initial, setInitial] = useState('');
  const [saving, setSaving] = useState(false);
  const [busy, setBusy] = useState<string | null>(null);

  useEffect(() => {
    templatesMeta().then(setMeta, () => undefined);
  }, []);

  const serialize = (d: Draft | null) => (d && meta ? JSON.stringify({ ...d, rows: cleanConditions(d.rows.map((r) => toCondition(r, meta))) }) : '');
  const dirty = !!draft && serialize(draft) !== initial;

  const open = (s?: Snippet) => {
    const d: Draft = s ? { id: s.id, name: s.name, category: s.category ?? 'necessary', location: s.location, priority: s.priority, enabled: s.enabled, code: s.code, rows: s.conditions.map(toRow) } : blank();
    setDraft(d);
    setInitial(serialize(d));
  };
  // Meta arrives after the list sometimes: recompute the baseline once it is known.
  useEffect(() => {
    if (draft && meta && !initial) setInitial(serialize(draft));
  }, [meta]); // eslint-disable-line react-hooks/exhaustive-deps

  const close = async () => {
    if (dirty && !(await confirmDialog({ title: 'Discard unsaved changes?', body: 'Your changes to this snippet will be lost.', confirmLabel: 'Discard', danger: true }))) return;
    setDraft(null);
  };

  const save = async () => {
    if (!draft || !meta) return;
    setSaving(true);
    try {
      const body = { name: draft.name, category: draft.category, location: draft.location, priority: draft.priority, enabled: draft.enabled, code: draft.code, conditions: draft.rows.map((r) => toCondition(r, meta)) };
      await api<Snippet>(draft.id ? `snippets/${draft.id}` : 'snippets', { body });
      toast(draft.id ? 'Snippet saved' : 'Snippet added');
      setDraft(null);
      list.reload();
    } catch (e) {
      toastError(e);
    } finally {
      setSaving(false);
    }
  };

  const toggle = async (s: Snippet, enabled: boolean) => {
    setBusy(s.id);
    try {
      await api(`snippets/${s.id}`, { body: { enabled } });
      list.setData((l) => (l ?? []).map((x) => (x.id === s.id ? { ...x, enabled } : x)));
      toast(enabled ? `“${s.name}” is live` : `“${s.name}” is paused`);
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(null);
    }
  };

  const remove = async (s: Snippet) => {
    if (!(await confirmDialog({ title: `Delete “${s.name}”?`, body: 'The code stops loading on the site right away. This cannot be undone.', confirmLabel: 'Delete', danger: true }))) return;
    setBusy(s.id);
    try {
      await api(`snippets/${s.id}`, { method: 'DELETE' });
      list.setData((l) => (l ?? []).filter((x) => x.id !== s.id));
      toast('Snippet deleted');
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(null);
    }
  };

  const header = (
    <PageHeader
      title="Custom code"
      description="Add analytics, tag managers, chat widgets or any HTML, CSS and JavaScript to the head or body of your pages — everywhere or only where you choose."
      actions={
        allowed && (
          <Button variant="primary" icon="plus" onClick={() => open()}>
            Add snippet
          </Button>
        )
      }
    />
  );

  if (!allowed) {
    return (
      <>
        {header}
        <Callout tone="warning" title="Not available for your account">
          Snippets print raw code on every page, so they need the “unfiltered HTML” permission (administrators of single sites, super admins on multisite).
        </Callout>
      </>
    );
  }

  const items = list.data;
  return (
    <>
      {header}
      {list.error && !items ? (
        <ErrorState error={list.error} onRetry={list.reload} />
      ) : !items ? (
        <div className="uncoder-ui-card">
          <SkeletonRows rows={3} cols={4} />
        </div>
      ) : items.length === 0 ? (
        <EmptyState icon="code-xml" title="No custom code yet" action={<Button variant="primary" icon="plus" onClick={() => open()}>Add snippet</Button>}>
          Snippets are printed as written on the pages you choose. {NAME} never loads them inside the editor.
        </EmptyState>
      ) : (
        <div className="uncoder-ui-tablewrap">
          <table className="uncoder-ui-table uncoder-ui-table--snippets">
            <thead>
              <tr>
                <th scope="col">Name</th>
                <th scope="col" className="uncoder-ui-col-status">
                  Active
                </th>
                <th scope="col">Where it appears</th>
                <th scope="col" className="uncoder-ui-col-date">
                  Modified
                </th>
                <th scope="col" className="uncoder-ui-col-actions">
                  <span className="uncoder-ui-sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {items.map((s) => (
                <tr key={s.id} className={cx(busy === s.id && 'is-busy', !s.enabled && 'is-draft')}>
                  <td>
                    <div className="uncoder-ui-namecell">
                      <span className="uncoder-ui-namecell__icon" aria-hidden>
                        <Icon name="code-xml" size={15} />
                      </span>
                      <div className="uncoder-ui-namecell__text">
                        <button type="button" className="uncoder-ui-namecell__title uncoder-ui-linkbtn" onClick={() => open(s)}>
                          {s.name}
                        </button>
                        <span className="uncoder-ui-namecell__meta">
                          {locationLabel(s.location)} · {CATEGORY_LABELS[s.category ?? 'necessary']} · priority {s.priority} · {s.code.split('\n').length} line{s.code.split('\n').length === 1 ? '' : 's'}
                        </span>
                      </div>
                    </div>
                  </td>
                  <td className="uncoder-ui-col-status">
                    <Toggle checked={s.enabled} onChange={(v) => toggle(s, v)} label={`${s.enabled ? 'Pause' : 'Activate'} “${s.name}”`} disabled={busy === s.id} />
                  </td>
                  <td className="uncoder-ui-cell-muted">{s.summary || 'Nowhere'}</td>
                  <td className="uncoder-ui-col-date">
                    <time dateTime={s.modified} title={`${absoluteTime(s.modified)}${s.authorName ? ` · ${s.authorName}` : ''}`}>
                      {relativeTime(s.modified)}
                    </time>
                  </td>
                  <td className="uncoder-ui-col-actions">
                    <div className="uncoder-ui-rowactions">
                      <Button size="sm" icon="pencil" onClick={() => open(s)}>
                        Edit
                      </Button>
                      <IconButton icon="trash-2" tone="danger" label={`Delete ${s.name}`} onClick={() => remove(s)} disabled={busy === s.id} />
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <SnippetDrawer draft={draft} meta={meta} setDraft={setDraft} saving={saving} dirty={dirty} onClose={close} onSave={save} />
    </>
  );
}

function SnippetDrawer({ draft, meta, setDraft, saving, dirty, onClose, onSave }: { draft: Draft | null; meta: TemplatesMeta | null; setDraft: (fn: (d: Draft | null) => Draft | null) => void; saving: boolean; dirty: boolean; onClose: () => void; onSave: () => void }) {
  const set = (patch: Partial<Draft>) => setDraft((d) => (d ? { ...d, ...patch } : d));
  const includes = draft?.rows.filter((r) => r.type === 'include').length ?? 0;
  const scriptWarning = useMemo(() => !!draft && /<script\b[^>]*\bsrc=["']?http:/i.test(draft.code), [draft]);
  return (
    <Drawer
      open={!!draft}
      onClose={onClose}
      width={640}
      title={draft?.id ? 'Edit snippet' : 'New snippet'}
      subtitle="Printed exactly as written. Test on a staging site first: broken markup here can break every page."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button variant="primary" onClick={onSave} loading={saving} disabled={!draft?.name.trim() || !meta || (!!draft?.id && !dirty)}>
            {draft?.id ? 'Save snippet' : 'Add snippet'}
          </Button>
        </>
      }
    >
      {draft && (
        <div className="uncoder-ui-stack">
          {!draft.id && (
            <div className="uncoder-ui-snippresets">
              <span className="uncoder-ui-snippresets__label">Start from</span>
              <div className="uncoder-ui-snippresets__list">
                {PRESETS.map((p) => (
                  <button
                    key={p.label}
                    type="button"
                    className={`uncoder-ui-snippresets__item${draft.name === p.name && draft.code === p.code ? ' is-active' : ''}`}
                    onClick={() => set({ name: p.name, location: p.location, category: p.category, code: p.code })}
                  >
                    <Icon name={p.icon} size={14} /> {p.label}
                  </button>
                ))}
              </div>
              {PRESETS.find((p) => p.name === draft.name && draft.code.startsWith(p.code.slice(0, 20)))?.note && (
                <Callout tone="info" icon="info">
                  {PRESETS.find((p) => p.name === draft.name && draft.code.startsWith(p.code.slice(0, 20)))!.note}
                </Callout>
              )}
            </div>
          )}
          <Field label="Name" htmlFor="uncoder-ui-snip-name">
            <input id="uncoder-ui-snip-name" className="uncoder-ui-input" value={draft.name} placeholder="Google Analytics" onChange={(e) => set({ name: e.currentTarget.value })} data-autofocus />
          </Field>
          <div className="uncoder-ui-formgrid">
            <Field label="Location" htmlFor="uncoder-ui-snip-loc" help={LOCATIONS.find((l) => l.value === draft.location)?.help}>
              <select id="uncoder-ui-snip-loc" className="uncoder-ui-select" value={draft.location} onChange={(e) => set({ location: e.currentTarget.value as Location })}>
                {LOCATIONS.map((l) => (
                  <option key={l.value} value={l.value}>
                    {l.label}
                  </option>
                ))}
              </select>
            </Field>
            <Field label="Priority" htmlFor="uncoder-ui-snip-prio" help="Lower runs first (1–100).">
              <input id="uncoder-ui-snip-prio" className="uncoder-ui-input" type="number" min={1} max={100} value={draft.priority} onChange={(e) => set({ priority: Math.max(1, Math.min(100, Number(e.currentTarget.value) || 10)) })} />
            </Field>
          </div>
          <Field label="Cookie category" htmlFor="uncoder-ui-snip-cat" help="With the cookie banner on (Settings), analytics and marketing snippets only run after the visitor agrees.">
            <select id="uncoder-ui-snip-cat" className="uncoder-ui-select" value={draft.category} onChange={(e) => set({ category: e.currentTarget.value as Category })}>
              <option value="necessary">Necessary — always runs</option>
              <option value="analytics">Analytics — needs consent</option>
              <option value="marketing">Marketing — needs consent</option>
            </select>
          </Field>
          <Field label="Code" htmlFor="uncoder-ui-snip-code" help="HTML with <script>, <style>, <link> or <meta> tags.">
            <textarea
              id="uncoder-ui-snip-code"
              className="uncoder-ui-textarea uncoder-ui-textarea--code"
              rows={14}
              spellCheck={false}
              value={draft.code}
              placeholder={'<script>\n  // …\n</script>'}
              onChange={(e) => set({ code: e.currentTarget.value })}
              onKeyDown={(e) => {
                // Tab inserts two spaces instead of leaving the field (Esc then Tab still moves focus).
                if (e.key !== 'Tab' || e.shiftKey) return;
                e.preventDefault();
                const t = e.currentTarget;
                const { selectionStart: a, selectionEnd: b, value } = t;
                set({ code: value.slice(0, a) + '  ' + value.slice(b) });
                requestAnimationFrame(() => t.setSelectionRange(a + 2, a + 2));
              }}
            />
          </Field>
          {scriptWarning && <Callout tone="warning">This loads a script over plain http: browsers block it on https sites.</Callout>}
          <Field label="Where it appears" help="The same rules as Theme Builder templates. Any matching exclude wins.">
            {!meta ? (
              <SkeletonRows rows={1} cols={2} />
            ) : (
              <div className="uncoder-ui-stack uncoder-ui-stack--tight">
                {draft.rows.length > 0 && (
                  <ol className="uncoder-ui-conds" aria-label="Conditions">
                    {draft.rows.map((r, i) => (
                      <ConditionRow
                        key={r.key}
                        row={r}
                        index={i}
                        meta={meta}
                        onChange={(p) => set({ rows: draft.rows.map((x) => (x.key === r.key ? { ...x, ...p } : x)) })}
                        onRemove={() => set({ rows: draft.rows.filter((x) => x.key !== r.key) })}
                      />
                    ))}
                  </ol>
                )}
                <div>
                  <Button variant="secondary" icon="plus" className="uncoder-ui-btn--dashed" onClick={() => set({ rows: [...draft.rows, newRow(draft.rows.length === 0)] })}>
                    Add condition
                  </Button>
                </div>
                {includes === 0 && <Callout tone="warning">No include condition: the snippet will not load anywhere.</Callout>}
              </div>
            )}
          </Field>
          <Field label="Active" inline help="Paused snippets stay saved but do not load.">
            <Toggle checked={draft.enabled} onChange={(v) => set({ enabled: v })} label="Active" />
          </Field>
        </div>
      )}
    </Drawer>
  );
}
