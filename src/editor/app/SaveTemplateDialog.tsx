import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { api } from '../lib/api';
import { config, schemaOf } from '../lib/config';
import { subtree } from '../lib/tree';
import { useDoc } from '../store/doc';
import { toast, useUi, showPanel } from '../store/ui';
import { refreshLookup } from '../controls/lookup';
import { Icon } from '../ui/Icon';
import { Button, Segmented } from '../ui/primitives';

/**
 * Saves the chosen element (with its children) as a reusable section template on this site, or (Agency licence) in
 * the private cloud library, for every site on the licence (Site\Cloud_Library).
 */
export function SaveTemplateDialog() {
  const id = useUi((s) => s.saveTemplate);
  const [name, setName] = useState('');
  const [busy, setBusy] = useState(false);
  const cloud = !!config.cloud?.write;
  const [where, setWhere] = useState<'site' | 'cloud'>('site');
  const node = useDoc((s) => (id ? s.doc.nodes[id] : null));

  useEffect(() => {
    if (id && node) setName(node.label || schemaOf(node.type)?.title || 'Section');
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);
  if (!id || !node) return null;
  const close = () => useUi.setState({ saveTemplate: null });

  const submit = async () => {
    const tree = subtree(useDoc.getState().doc, id);
    if (!tree || !name.trim()) return;
    setBusy(true);
    try {
      if (where === 'cloud') {
        await api('cloud/section', { body: { title: name.trim(), elements: [tree] } });
        toast(`Saved “${name.trim()}” to the cloud library`, 'success', { label: 'Show', run: () => showPanel('library') }, 5000);
        close();
        return;
      }
      const tpl = await api<{ id: number }>('templates', { body: { type: 'section', title: name.trim() } });
      await api(`documents/${tpl.id}`, { body: { elements: [tree], status: 'publish' } });
      refreshLookup('templates');
      toast(`Saved “${name.trim()}” to Insert → Sections`, 'success', { label: 'Show', run: () => showPanel('library') }, 5000);
      close();
    } catch (e) {
      toast(e instanceof Error ? e.message : 'Could not save the template', 'error');
    } finally {
      setBusy(false);
    }
  };

  return createPortal(
    <div className="uncoder-ui-scrim" onPointerDown={(e) => e.target === e.currentTarget && close()}>
      <form
        className="uncoder-ui-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="uncoder-ui-savetpl-title"
        onSubmit={(e) => {
          e.preventDefault();
          submit();
        }}
        onKeyDown={(e) => e.key === 'Escape' && close()}
      >
        <div className="uncoder-ui-dialog__head">
          <Icon name="folder-plus" size={16} />
          <h2 id="uncoder-ui-savetpl-title">Save as template</h2>
        </div>
        <label className="uncoder-ui-field">
          <span className="uncoder-ui-field__label">Template name</span>
          <input className="uncoder-ui-input" autoFocus value={name} onChange={(e) => setName(e.currentTarget.value)} maxLength={120} />
        </label>
        {cloud && (
          <div className="uncoder-ui-field">
            <span className="uncoder-ui-field__label">Save to</span>
            <Segmented
              ariaLabel="Save to"
              value={where}
              onChange={(v) => setWhere(v as 'site' | 'cloud')}
              options={[
                { value: 'site', label: 'This site' },
                { value: 'cloud', label: 'Cloud library' },
              ]}
            />
          </div>
        )}
        <p className="uncoder-ui-note">
          {where === 'cloud'
            ? 'Saved to your private cloud library: insert it on every site of your licence from Insert → Sections. Its global classes go along; images are copied when it is inserted.'
            : 'Saved sections appear under Insert → Sections on every page, and can be embedded with the Template widget so one edit updates them everywhere.'}
        </p>
        <div className="uncoder-ui-dialog__foot">
          <Button type="button" onClick={close}>
            Cancel
          </Button>
          <Button type="submit" variant="primary" loading={busy} disabled={!name.trim()}>
            Save
          </Button>
        </div>
      </form>
    </div>,
    document.querySelector('.uncoder-ui-portal') ?? document.body,
  );
}
