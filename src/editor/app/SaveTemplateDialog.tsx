import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { api } from '../lib/api';
import { schemaOf } from '../lib/config';
import { subtree } from '../lib/tree';
import { useDoc } from '../store/doc';
import { toast, useUi } from '../store/ui';
import { refreshLookup } from '../controls/lookup';
import { Icon } from '../ui/Icon';
import { Button } from '../ui/primitives';

/** Saves the chosen element (with its children) as a reusable section template. */
export function SaveTemplateDialog() {
  const id = useUi((s) => s.saveTemplate);
  const [name, setName] = useState('');
  const [busy, setBusy] = useState(false);
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
      const tpl = await api<{ id: number }>('templates', { body: { type: 'section', title: name.trim() } });
      await api(`documents/${tpl.id}`, { body: { elements: [tree], status: 'publish' } });
      refreshLookup('templates');
      toast(`Saved “${name.trim()}” to Insert → Sections`, 'success', { label: 'Show', run: () => useUi.setState({ panel: 'library' }) }, 5000);
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
        <p className="uncoder-ui-note">Saved sections appear under Insert → Sections on every page, and can be embedded with the Template widget so one edit updates them everywhere.</p>
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
