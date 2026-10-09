// Settings → White-label (Site\White_Label, Agency licence): the builder under the agency's name, logo and icon in the
// WordPress admin, the editor, "Edit with …" links, the plugins list and the AI connection. Saved settings keep
// applying when the licence ends; changing them needs the "white_label" feature. The page reloads after saving,
// since the brand is part of every screen's config.
import { useEffect, useState } from 'react';
import { Button, Toggle } from '@editor/ui/primitives';
import { api, ApiError } from '../lib/api';
import { pickImage } from '../lib/media';
import { toast, toastError } from '../lib/toast';
import { Badge, Callout, Card, ErrorState, SettingRow, SkeletonRows } from '../ui/kit';

interface Image {
  id: number;
  url: string;
}
interface WhiteLabelState {
  name: string;
  logo: Image;
  icon: Image;
  url: string;
  hideLinks: boolean;
  onlyMe: boolean;
  allowed: boolean;
  pricing: string;
}

const NONE: Image = { id: 0, url: '' };

function ImageRow({ title, description, value, wide, disabled, onChange }: { title: string; description: string; value: Image; wide?: boolean; disabled: boolean; onChange: (v: Image) => void }) {
  const choose = async () => {
    const img = await pickImage(title);
    if (img) onChange(img);
  };
  return (
    <SettingRow title={title} description={description}>
      <div className="uncoder-ui-wl-image">
        <span className={'uncoder-ui-wl-image__preview' + (wide ? ' is-wide' : '')}>{value.url ? <img src={value.url} alt="" /> : <span className="uncoder-ui-muted">None</span>}</span>
        <Button size="sm" icon="image" disabled={disabled} onClick={choose}>
          {value.id ? 'Replace' : 'Choose'}
        </Button>
        {value.id > 0 && (
          <Button size="sm" variant="ghost" disabled={disabled} onClick={() => onChange(NONE)}>
            Remove
          </Button>
        )}
      </div>
    </SettingRow>
  );
}

export function WhiteLabelCard() {
  const [saved, setSaved] = useState<WhiteLabelState | null>(null);
  const [draft, setDraft] = useState<WhiteLabelState | null>(null);
  const [error, setError] = useState<Error | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api<WhiteLabelState>('white-label')
      .then((s) => {
        setSaved(s);
        setDraft(s);
      })
      .catch(setError);
  }, []);

  if (error) return <ErrorState error={error} />;
  if (!draft || !saved) {
    return (
      <Card title="White-label">
        <SkeletonRows rows={5} />
      </Card>
    );
  }

  const set = <K extends keyof WhiteLabelState>(key: K, value: WhiteLabelState[K]) => setDraft({ ...draft, [key]: value });
  const locked = !draft.allowed;
  const active = !!(saved.name.trim() || saved.logo.id || saved.icon.id);
  const dirty = JSON.stringify(draft) !== JSON.stringify(saved);

  const save = async (next: WhiteLabelState) => {
    setBusy(true);
    try {
      await api('white-label', { body: { name: next.name, logo: next.logo.id, icon: next.icon.id, url: next.url, hideLinks: next.hideLinks, onlyMe: next.onlyMe } });
      toast('White-label saved. Reloading…', 'success');
      window.setTimeout(() => window.location.reload(), 600);
    } catch (e) {
      setBusy(false);
      toastError(e instanceof ApiError ? e : new Error('White-label could not be saved.'));
    }
  };

  return (
    <div className="uncoder-ui-stack">
      <Card
        title={
          <>
            White-label {locked && <Badge tone="accent">Agency</Badge>}
            {active && !locked && <Badge tone="success">On</Badge>}
          </>
        }
        description="Show the builder under your agency’s name and logo: in the WordPress admin, the editor, “Edit with …” buttons, the Plugins list and the AI connection. Visitors of the site never see the builder’s name."
      >
        {locked && (
          <Callout
            tone={active ? 'warning' : 'info'}
            title={active ? 'Your brand keeps applying' : 'White-label comes with the Agency licence'}
            actions={
              <a className="uncoder-ui-btn uncoder-ui-btn--sm" href={draft.pricing} target="_blank" rel="noopener noreferrer">
                See the plans
              </a>
            }
          >
            {active ? 'Changing it needs an active Agency licence. You can turn it off at any time.' : 'Activate an Agency licence under Licence to set your own name, logo and icon.'}
          </Callout>
        )}
        <SettingRow title="Name" description="Replaces “Uncoder” in the admin menu, the editor and the AI connection." htmlFor="uncoder-ui-wl-name">
          <input
            id="uncoder-ui-wl-name"
            className="uncoder-ui-input"
            value={draft.name}
            maxLength={40}
            placeholder="Uncoder"
            disabled={locked}
            onChange={(e) => set('name', e.currentTarget.value)}
          />
        </SettingRow>
        <ImageRow title="Logo" description="Top of the admin screens, in place of the icon and name. SVG or PNG, shown 26 px high." value={draft.logo} wide disabled={locked} onChange={(v) => set('logo', v)} />
        <ImageRow title="Icon" description="Square: the WordPress menu, the editor’s menu button, “Edit with …” and loading screens. At least 64 × 64 px." value={draft.icon} disabled={locked} onChange={(v) => set('icon', v)} />
        <SettingRow title="Website" description="Where the name links on the Plugins screen and in the AI connection." htmlFor="uncoder-ui-wl-url">
          <input id="uncoder-ui-wl-url" className="uncoder-ui-input" type="url" value={draft.url} placeholder="https://" disabled={locked} onChange={(e) => set('url', e.currentTarget.value)} />
        </SettingRow>
        <SettingRow title="Hide Uncoder links" description="Hides documentation, support, changelog and news links to uncoderbuilder.com.">
          <Toggle checked={draft.hideLinks} disabled={locked} onChange={(v) => set('hideLinks', v)} label="Hide Uncoder links" />
        </SettingRow>
        <SettingRow title="Only me" description="Licence, White-label, Starter sites, Client handoff and the branded reports show only to you. Other administrators, such as your client, don’t see them. The Cloud Library stays with you and the people you give full access in Client handoff.">
          <Toggle checked={draft.onlyMe} disabled={locked} onChange={(v) => set('onlyMe', v)} label="Only me" />
        </SettingRow>
        {(!locked || active) && (
          <div className="uncoder-ui-wl-actions">
            {active && (
              <Button variant="ghost" disabled={busy} onClick={() => void save({ ...draft, name: '', logo: NONE, icon: NONE, url: '', hideLinks: false, onlyMe: false })}>
                Turn off white-label
              </Button>
            )}
            {!locked && (
              <Button variant="primary" loading={busy} disabled={!dirty} onClick={() => void save(draft)}>
                Save
              </Button>
            )}
          </div>
        )}
      </Card>
    </div>
  );
}
