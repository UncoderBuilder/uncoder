// Library → Cloud library (Site\Cloud_Library, Agency licence): the agency's sections, pages, templates and site kits,
// saved from any site of the licence and reused here. Sections are inserted from the editor (Insert → Sections) or
// copied to this site's saved sections; pages and templates come in as drafts; site kits open the usual import.
import { useEffect, useMemo, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button, IconButton } from '@editor/ui/primitives';
import { CLOUD_KIND_ICON, CLOUD_KIND_LABEL, type CloudItem, type CloudKind, type CloudList } from '@shared/cloud';
import type { ElementNode } from '@shared/types';
import { api } from '../lib/api';
import { can, editorUrl } from '../lib/config';
import { plural, relativeTime, absoluteTime } from '../lib/format';
import { toast, toastError } from '../lib/toast';
import { Badge, Callout, Card, Checkbox, EmptyState, ErrorState, PageHeader, SearchInput, SkeletonRows } from '../ui/kit';
import { confirmDialog, Dialog } from '../ui/Dialog';
import { ALL_PARTS, KitImport, KitReport, PART_LABEL, type Part, type Preview, type Report } from './SiteKit';

type Filter = 'all' | 'section' | 'document' | 'kit';
const FILTERS: Array<{ id: Filter; label: string; match: (k: CloudKind) => boolean }> = [
  { id: 'all', label: 'All', match: () => true },
  { id: 'section', label: 'Sections', match: (k) => k === 'section' },
  { id: 'document', label: 'Pages & templates', match: (k) => k === 'page' || k === 'template' },
  { id: 'kit', label: 'Site kits', match: (k) => k === 'kit' },
];
/** What a new cloud kit carries by default: the design and its content, not blog posts, settings or code. */
const KIT_DEFAULT: Part[] = ['design', 'templates', 'content', 'menus', 'media', 'fonts'];

export function formatBytes(n: number): string {
  if (n < 1024) return `${n} B`;
  if (n < 1024 * 1024) return `${Math.round(n / 1024)} KB`;
  if (n < 1024 ** 3) return `${(n / 1024 / 1024).toFixed(n < 10 * 1024 * 1024 ? 1 : 0)} MB`;
  return `${(n / 1024 ** 3).toFixed(1)} GB`;
}

/** One line about an item: what it holds and where it came from. */
function details(item: CloudItem): string {
  const m = item.meta;
  const parts: string[] = [CLOUD_KIND_LABEL[item.kind]];
  if (item.kind === 'kit') {
    if (typeof m.content === 'number') parts.push(plural(m.content, 'page', 'pages'));
    if (typeof m.templates === 'number') parts.push(plural(m.templates, 'template', 'templates'));
    if (typeof m.media === 'number') parts.push(plural(m.media, 'file', 'files'));
  } else if (typeof m.elements === 'number') {
    if (item.kind === 'template' && m.type) parts[0] = `${String(m.type).replace(/-/g, ' ')} template`;
    parts.push(plural(m.elements, 'element', 'elements'));
  }
  parts.push(formatBytes(item.size), `from ${item.site}`);
  return parts.join(' · ');
}

export function CloudLibraryScreen() {
  const [list, setList] = useState<CloudList | null>(null);
  const [error, setError] = useState<Error | null>(null);
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState<Filter>('all');
  const [busy, setBusy] = useState<number | null>(null);
  const [saving, setSaving] = useState(false);
  const [renaming, setRenaming] = useState<CloudItem | null>(null);
  const [importing, setImporting] = useState<{ item: CloudItem; preview: Preview } | null>(null);
  // The import dialog stays put while the kit comes in (no Close, Escape or click outside).
  const [importBusy, setImportBusy] = useState(false);
  const [report, setReport] = useState<{ item: CloudItem; report: Report } | null>(null);

  const load = () => {
    setError(null);
    api<CloudList>('cloud').then(setList).catch(setError);
  };
  useEffect(load, []);

  const shown = useMemo(() => {
    const f = FILTERS.find((x) => x.id === filter)!;
    const q = query.trim().toLowerCase();
    return (list?.items ?? []).filter((i) => f.match(i.kind) && (!q || `${i.title} ${i.site}`.toLowerCase().includes(q)));
  }, [list, query, filter]);

  const run = async (item: CloudItem, task: () => Promise<void>) => {
    setBusy(item.id);
    try {
      await task();
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(null);
    }
  };

  /** A section becomes one of this site's saved sections (images copied, classes added). */
  const copySection = (item: CloudItem) =>
    run(item, async () => {
      const res = await api<{ elements: ElementNode[]; images: number }>(`cloud/${item.id}/elements`);
      const tpl = await api<{ id: number }>('templates', { body: { type: 'section', title: item.title } });
      await api(`documents/${tpl.id}`, { body: { elements: res.elements, status: 'publish' } });
      toast(`“${item.title}” is now in this site’s saved sections${res.images ? ` (${plural(res.images, 'image', 'images')} copied)` : ''}.`, 'success', { label: 'Edit', run: () => (window.location.href = editorUrl(tpl.id)) });
    });

  const importDocument = (item: CloudItem) =>
    run(item, async () => {
      const res = await api<{ created: Array<{ id: number; title: string; edit: string }>; warnings: string[] }>(`cloud/${item.id}/import`, { method: 'POST', body: {} });
      const first = res.created[0];
      toast(first ? `“${first.title}” was added as a draft.${res.warnings.length ? ' ' + res.warnings.join(' ') : ''}` : 'Nothing was imported.', first ? 'success' : 'error', first ? { label: 'Edit', run: () => (window.location.href = first.edit) } : undefined, 8000);
    });

  const stageKit = (item: CloudItem) =>
    run(item, async () => {
      const preview = await api<Preview>(`cloud/${item.id}/stage`, { method: 'POST', body: {} });
      setImporting({ item, preview });
    });

  const remove = async (item: CloudItem) => {
    const ok = await confirmDialog({
      title: `Delete “${item.title}”?`,
      body: 'It is removed from the cloud library for every site of your licence. What sites already inserted or imported stays.',
      confirmLabel: 'Delete',
      danger: true,
    });
    if (!ok) return;
    await run(item, async () => {
      await api(`cloud/${item.id}`, { method: 'DELETE' });
      setList((l) => (l ? { ...l, items: l.items.filter((i) => i.id !== item.id), usage: { items: l.usage.items - 1, bytes: Math.max(0, l.usage.bytes - item.size) } } : l));
      toast(`Deleted “${item.title}”.`, 'success');
    });
  };

  const locked = list && !list.allowed;

  return (
    <>
      <PageHeader
        title="Cloud Library"
        description="Your sections, pages, templates and site kits, saved once and reused on every site of your licence."
        actions={
          list?.allowed && (
            <>
              <SearchInput value={query} onChange={setQuery} placeholder="Search the library…" width={220} />
              {list.write && can('manage_options') && (
                <Button variant="primary" icon="cloud-upload" onClick={() => setSaving(true)}>
                  Save this site as a kit
                </Button>
              )}
            </>
          )
        }
      />
      {error ? (
        <ErrorState error={error} onRetry={load} />
      ) : !list ? (
        <Card flush>
          <SkeletonRows rows={5} cols={3} />
        </Card>
      ) : locked ? (
        <Card>
          <EmptyState
            icon="cloud"
            title="The cloud library comes with the Agency licence"
            action={
              <a className="uncoder-ui-btn uncoder-ui-btn--primary" href={list.pricing} target="_blank" rel="noopener noreferrer">
                See the plans
              </a>
            }
          >
            Save sections, pages and whole sites to your account and reuse them on every client site. <a href={list.licence}>Add a licence</a>
          </EmptyState>
        </Card>
      ) : (
        <div className="uncoder-ui-stack">
          {!list.write && (
            <Callout tone="warning" title="Your licence has ended: the library is read-only">
              Everything in it can still be inserted and imported. Renew to save, rename or delete.{' '}
              <a href={list.pricing} target="_blank" rel="noopener noreferrer">
                See the plans
              </a>
            </Callout>
          )}
          <div className="uncoder-ui-cloud__bar">
            <div className="uncoder-ui-chipset" role="group" aria-label="Show">
              {FILTERS.map((f) => {
                const n = list.items.filter((i) => f.match(i.kind)).length;
                return (
                  <button key={f.id} type="button" className={`uncoder-ui-togglechip${filter === f.id ? ' is-on' : ''}`} aria-pressed={filter === f.id} onClick={() => setFilter(f.id)}>
                    {f.label} <span className="uncoder-ui-muted">{n}</span>
                  </button>
                );
              })}
            </div>
            {list.limits && (
              <span className="uncoder-ui-muted uncoder-ui-cloud__usage">
                {plural(list.usage.items, 'item', 'items')} · {formatBytes(list.usage.bytes)} of {formatBytes(list.limits.bytes)}
              </span>
            )}
          </div>
          <Card flush>
            {shown.length === 0 ? (
              <EmptyState icon={list.items.length ? 'search-x' : 'cloud'} title={list.items.length ? 'Nothing matches' : 'Your cloud library is empty'}>
                {list.items.length ? (
                  'Try another word or filter.'
                ) : (
                  <>
                    In the editor, right-click an element and choose “Save as template”, then Cloud library; or use “Save to cloud library” in the editor’s menu for a whole page. A site kit saves this whole site.
                  </>
                )}
              </EmptyState>
            ) : (
              <ul className="uncoder-ui-recent">
                {shown.map((item) => (
                  <li key={item.id} className="uncoder-ui-recent__row" aria-busy={busy === item.id}>
                    <span className="uncoder-ui-recent__icon" aria-hidden>
                      <Icon name={CLOUD_KIND_ICON[item.kind]} size={15} />
                    </span>
                    <div className="uncoder-ui-recent__main">
                      <span className="uncoder-ui-recent__title">{item.title}</span>
                      <span className="uncoder-ui-recent__meta">
                        {details(item)} · <time title={absoluteTime(item.updated)}>saved {relativeTime(item.updated)}</time>
                      </span>
                    </div>
                    <div className="uncoder-ui-rowactions">
                      {item.kind === 'section' && (
                        <Button size="sm" icon="copy-plus" loading={busy === item.id} disabled={busy !== null} onClick={() => copySection(item)}>
                          Add to this site
                        </Button>
                      )}
                      {(item.kind === 'page' || item.kind === 'template') && (
                        <Button size="sm" icon="download" loading={busy === item.id} disabled={busy !== null} onClick={() => importDocument(item)}>
                          Import as draft
                        </Button>
                      )}
                      {item.kind === 'kit' && can('manage_options') && (
                        <Button size="sm" variant="primary" icon="download" loading={busy === item.id} disabled={busy !== null} onClick={() => stageKit(item)}>
                          Import
                        </Button>
                      )}
                      {list.write && (
                        <>
                          <IconButton icon="pencil" label={`Rename “${item.title}”`} disabled={busy !== null} onClick={() => setRenaming(item)} />
                          <IconButton icon="trash-2" tone="danger" label={`Delete “${item.title}”`} disabled={busy !== null} onClick={() => void remove(item)} />
                        </>
                      )}
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Card>
          <p className="uncoder-ui-muted uncoder-ui-cloud__note">
            <Badge tone="accent">Agency</Badge> Sections bring their global classes along; images are copied into this site’s media library when you insert or import. Inserting a section in the editor: Insert → Sections → Cloud library.
          </p>
        </div>
      )}

      <SaveKitDialog
        open={saving}
        onClose={() => setSaving(false)}
        onSaved={(item) => {
          setSaving(false);
          setList((l) => (l ? { ...l, items: [item, ...l.items], usage: { items: l.usage.items + 1, bytes: l.usage.bytes + item.size } } : l));
        }}
      />
      <RenameDialog
        item={renaming}
        onClose={() => setRenaming(null)}
        onRenamed={(item) => {
          setRenaming(null);
          setList((l) => (l ? { ...l, items: l.items.map((i) => (i.id === item.id ? item : i)) } : l));
        }}
      />
      <Dialog open={!!importing} onClose={() => !importBusy && setImporting(null)} locked={importBusy} title={importing ? `Import ${importing.item.title}` : ''} description="Choose what comes in. Pages and templates you already have are kept unless you choose otherwise." width={680}>
        {importing && (
          <KitImport
            preview={importing.preview}
            onBusy={setImportBusy}
            from={
              <>
                Cloud library · <strong>{importing.item.title}</strong> · from {importing.item.site}
              </>
            }
            onCancel={() => setImporting(null)}
            onDone={(r) => {
              setReport({ item: importing.item, report: r });
              setImporting(null);
            }}
          />
        )}
      </Dialog>
      <Dialog open={!!report} onClose={() => setReport(null)} title={report ? `${report.item.title} is imported` : ''} width={560}>
        {report && <KitReport report={report.report} again="Done" onAgain={() => setReport(null)} />}
      </Dialog>
    </>
  );
}

function SaveKitDialog({ open, onClose, onSaved }: { open: boolean; onClose: () => void; onSaved: (item: CloudItem) => void }) {
  const [title, setTitle] = useState('');
  const [parts, setParts] = useState<Record<Part, boolean>>(() => Object.fromEntries(ALL_PARTS.map((p) => [p, KIT_DEFAULT.includes(p)])) as Record<Part, boolean>);
  const [busy, setBusy] = useState(false);

  const save = async () => {
    setBusy(true);
    try {
      const res = await api<{ item: CloudItem }>('cloud/kit', { body: { title: title.trim(), parts } });
      toast(`Saved “${res.item.title}” to the cloud library (${formatBytes(res.item.size)}).`, 'success');
      onSaved(res.item);
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog
      open={open}
      onClose={() => !busy && onClose()}
      dismissable={!busy}
      title="Save this site as a kit"
      description="A site kit carries the parts you choose, with every image, so any site of your licence can start from it."
      width={600}
      footer={
        <>
          <Button onClick={onClose} disabled={busy}>
            Cancel
          </Button>
          <Button variant="primary" icon="cloud-upload" loading={busy} disabled={!ALL_PARTS.some((p) => parts[p])} onClick={save}>
            {busy ? 'Uploading…' : 'Save to cloud'}
          </Button>
        </>
      }
    >
      <div className="uncoder-ui-stack">
        <label className="uncoder-ui-fld">
          <span className="uncoder-ui-fld__label">Name</span>
          <input className="uncoder-ui-input" value={title} maxLength={120} placeholder={document.title.split(' ‹ ')[0] || 'This site'} onChange={(e) => setTitle(e.currentTarget.value)} disabled={busy} />
        </label>
        <div className="uncoder-ui-cloud__parts">
          {ALL_PARTS.map((p) => (
            <Checkbox key={p} checked={parts[p]} disabled={busy} onChange={(v) => setParts({ ...parts, [p]: v })} label={PART_LABEL[p].label} description={PART_LABEL[p].help} />
          ))}
        </div>
        {busy && <p className="uncoder-ui-muted">Creating the kit and uploading it. With many images this takes a few minutes: keep this page open.</p>}
      </div>
    </Dialog>
  );
}

function RenameDialog({ item, onClose, onRenamed }: { item: CloudItem | null; onClose: () => void; onRenamed: (item: CloudItem) => void }) {
  const [title, setTitle] = useState('');
  const [busy, setBusy] = useState(false);
  useEffect(() => setTitle(item?.title ?? ''), [item]);

  const save = async () => {
    if (!item || !title.trim()) return;
    setBusy(true);
    try {
      const res = await api<{ item: CloudItem }>(`cloud/${item.id}`, { body: { title: title.trim() } });
      onRenamed(res.item);
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Dialog
      open={!!item}
      onClose={onClose}
      title="Rename"
      width={440}
      footer={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button variant="primary" loading={busy} disabled={!title.trim()} onClick={save}>
            Rename
          </Button>
        </>
      }
    >
      <form
        onSubmit={(e) => {
          e.preventDefault();
          void save();
        }}
      >
        <input className="uncoder-ui-input" aria-label="Name" value={title} maxLength={120} autoFocus onChange={(e) => setTitle(e.currentTarget.value)} />
      </form>
    </Dialog>
  );
}
