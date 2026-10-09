import { useEffect, useRef, useState } from 'react';
import { Button, IconButton } from '@editor/ui/primitives';
import { Icon } from '@editor/ui/Icon';
import { api } from '../lib/api';
import { cfg } from '../lib/config';
import { useResource } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { confirmDialog } from '../ui/Dialog';
import { Callout, Card, EmptyState, ErrorState, SkeletonRows } from '../ui/kit';

interface IconSetEntry {
  id: string;
  title: string;
  count: number;
  viewBox: string;
  source: string;
  url: string;
}

const SOURCE: Record<string, string> = { icomoon: 'IcoMoon', fontello: 'Fontello', font: 'SVG font', svg: 'SVG files' };

/**
 * Design System → Custom icons: IcoMoon / Fontello downloads or SVG files become icon libraries in every icon
 * picker. They are stored as inline SVG, so pages load no icon font.
 */
export function IconsScreen() {
  const sets = useResource((signal) => api<IconSetEntry[]>('icon-sets', { signal }), []);
  const [busy, setBusy] = useState(false);
  const [drag, setDrag] = useState(false);
  const input = useRef<HTMLInputElement>(null);

  const upload = async (files: FileList | File[]) => {
    const list = Array.from(files);
    if (!list.length) return;
    const zip = list.find((f) => /\.zip$/i.test(f.name));
    const svgs = list.filter((f) => /\.svg$/i.test(f.name));
    if (!zip && !svgs.length) {
      toast('Choose an IcoMoon or Fontello .zip, or .svg files', 'error');
      return;
    }
    const form = new FormData();
    if (zip) form.append('file', zip);
    else svgs.forEach((f) => form.append('file[]', f));
    setBusy(true);
    try {
      const res = await fetch(cfg.rest.root + 'icon-sets', { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.rest.nonce }, body: form });
      const data = await res.json();
      if (!res.ok) throw new Error(data?.message || 'Upload failed');
      toast(`Added “${data.title}” with ${data.count} icons`, 'success');
      sets.reload();
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(false);
      if (input.current) input.current.value = '';
    }
  };

  const remove = async (set: IconSetEntry) => {
    if (!(await confirmDialog({ title: `Delete “${set.title}”?`, body: 'Icons from this set that are already on pages stop showing. This cannot be undone.', confirmLabel: 'Delete', danger: true }))) return;
    try {
      await api(`icon-sets/${set.id}`, { method: 'DELETE' });
      toast('Icon set deleted');
      sets.reload();
    } catch (e) {
      toastError(e);
    }
  };

  return (
    <div className="uncoder-ui-stack">
      <Card title="Add an icon set" description="Upload the .zip you download from IcoMoon (with selection.json) or Fontello, or a set of .svg files. The icons appear under “Custom” in every icon picker.">
        <label
          className={`uncoder-ui-kitdrop${drag ? ' is-drag' : ''}`}
          onDragOver={(e) => {
            e.preventDefault();
            setDrag(true);
          }}
          onDragLeave={() => setDrag(false)}
          onDrop={(e) => {
            e.preventDefault();
            setDrag(false);
            upload(e.dataTransfer.files);
          }}
        >
          <Icon name={busy ? 'loader' : 'upload'} size={20} />
          <span>{busy ? 'Converting icons…' : 'Drop a .zip or .svg files here, or click to choose'}</span>
          <input ref={input} type="file" accept=".zip,.svg" multiple disabled={busy} onChange={(e) => e.currentTarget.files && upload(e.currentTarget.files)} />
        </label>
        <p className="uncoder-ui-muted">Multicolour icons become single-colour (they take the text color). Up to 3,000 icons per set.</p>
      </Card>
      <Card title="Your icon sets">
        {sets.error && !sets.data ? (
          <ErrorState error={sets.error} onRetry={sets.reload} />
        ) : !sets.data ? (
          <SkeletonRows rows={2} cols={3} />
        ) : !sets.data.length ? (
          <EmptyState icon="shapes" title="No custom icons yet">
            Bundled sets (Lucide, Font Awesome, Phosphor, Bootstrap, Heroicons, Feather, Themify) are always available.
          </EmptyState>
        ) : (
          <ul className="uncoder-ui-iconsets">
            {sets.data.map((set) => (
              <li key={set.id} className="uncoder-ui-iconset">
                <div className="uncoder-ui-iconset__head">
                  <div>
                    <strong>{set.title}</strong>
                    <span className="uncoder-ui-muted">
                      {set.count} icons · {SOURCE[set.source] ?? 'Upload'} · <code>{set.id}</code>
                    </span>
                  </div>
                  <IconButton icon="trash-2" label={`Delete ${set.title}`} tone="danger" onClick={() => remove(set)} />
                </div>
                <IconPreview set={set} />
              </li>
            ))}
          </ul>
        )}
      </Card>
      <Callout tone="info" icon="info">
        In the builder, open any icon field → “Custom” to use them. AI clients can use them as <code>{'{"library":"<set id>","value":"<icon name>"}'}</code>.
      </Callout>
    </div>
  );
}

/** The first icons of a set (the file holds server-built SVG shapes only). */
function IconPreview({ set }: { set: IconSetEntry }) {
  const [icons, setIcons] = useState<Array<[string, string, string]>>([]);
  useEffect(() => {
    let live = true;
    fetch(set.url, { credentials: 'same-origin' })
      .then((r) => (r.ok ? r.json() : null))
      .then((data: { viewBox: string; icons: Record<string, string | [string, string]> } | null) => {
        if (!live || !data) return;
        setIcons(
          Object.entries(data.icons)
            .slice(0, 48)
            .map(([name, entry]) => (Array.isArray(entry) ? [name, entry[0], entry[1]] : [name, entry, data.viewBox])),
        );
      })
      .catch(() => undefined);
    return () => {
      live = false;
    };
  }, [set.url]);
  return (
    <div className="uncoder-ui-iconset__grid">
      {icons.map(([name, body, box]) => (
        <span key={name} className="uncoder-ui-iconset__icon" title={name}>
          <svg viewBox={box} width="22" height="22" fill="currentColor" aria-hidden dangerouslySetInnerHTML={{ __html: body }} />
        </span>
      ))}
      {set.count > icons.length && icons.length > 0 && <span className="uncoder-ui-muted">+{set.count - icons.length} more</span>}
    </div>
  );
}
