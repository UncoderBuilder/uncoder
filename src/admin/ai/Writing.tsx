import { useEffect, useState } from 'react';
import { Toggle } from '@editor/ui/primitives';
import { api, type PluginSettings } from '../lib/api';
import { useResource, useUnsavedGuard } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { Card, ErrorState, SaveBar, SettingRow, SkeletonRows } from '../ui/kit';

type Draft = { enabled: boolean; model: string; key: string; images: { enabled: boolean; model: string; endpoint: string; key: string } };

const pick = (s: PluginSettings): Draft => ({
  enabled: s.ai?.enabled ?? false,
  model: s.ai?.model ?? 'claude-sonnet-5',
  key: '',
  images: { enabled: s.aiImages?.enabled ?? false, model: s.aiImages?.model ?? 'gpt-image-1', endpoint: s.aiImages?.endpoint ?? 'https://api.openai.com/v1/images/generations', key: '' },
});

/**
 * AI writing in the editor (improve, shorten, translate, alt text…) with the owner's Anthropic key.
 * Saved on its own through the settings endpoint, which only touches the fields it receives.
 */
export function AiWritingPanel() {
  const settings = useResource((signal) => api<PluginSettings>('settings', { signal }), []);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [saving, setSaving] = useState(false);
  const s = settings.data;

  useEffect(() => {
    if (s) setDraft(pick(s));
  }, [s]);

  const dirty = !!s && !!draft && JSON.stringify(draft) !== JSON.stringify(pick(s));
  useUnsavedGuard(dirty);

  if (settings.error && !s) return <ErrorState error={settings.error} onRetry={settings.reload} />;
  if (!s || !draft) {
    return (
      <Card>
        <SkeletonRows rows={3} cols={2} />
      </Card>
    );
  }

  const save = async () => {
    setSaving(true);
    try {
      // The key is write-only: empty keeps the stored one.
      const { images, ...ai } = draft;
      const res = await api<PluginSettings>('settings', { body: { ai, aiImages: images } });
      settings.setData(res);
      toast('AI writing settings saved');
    } catch (e) {
      toastError(e);
    } finally {
      setSaving(false);
    }
  };

  return (
    <>
      <Card title="AI writing in the editor" description="Adds AI actions to text fields and images in the editor: improve, shorten, fix spelling, change tone, translate and write alt text. Uses your own Anthropic (Claude) API key; the text of the field or the chosen image is sent to Anthropic only when an editor clicks an AI action.">
        <div className="uncoder-ui-setlist">
          <SettingRow title="AI actions in the editor">
            <Toggle checked={draft.enabled} onChange={(v) => setDraft({ ...draft, enabled: v })} label="AI actions in the editor" />
          </SettingRow>
          {draft.enabled && (
            <>
              <SettingRow title="Anthropic API key" htmlFor="uncoder-ui-ai-key" description={s.ai?.has_key ? 'A key is saved. Leave empty to keep it.' : 'Create one at console.anthropic.com. Stays on the server; never shown again after saving.'}>
                <input id="uncoder-ui-ai-key" className="uncoder-ui-input" type="password" autoComplete="new-password" spellCheck={false} placeholder={s.ai?.has_key ? '•••••••• saved' : 'sk-ant-…'} value={draft.key} onChange={(e) => setDraft({ ...draft, key: e.currentTarget.value.trim() })} />
              </SettingRow>
              <SettingRow title="Model" htmlFor="uncoder-ui-ai-model">
                <select id="uncoder-ui-ai-model" className="uncoder-ui-select" value={draft.model} onChange={(e) => setDraft({ ...draft, model: e.currentTarget.value })}>
                  {Object.entries(s.aiModels ?? {}).map(([value, label]) => (
                    <option key={value} value={value}>
                      {label}
                    </option>
                  ))}
                </select>
              </SettingRow>
            </>
          )}
        </div>
      </Card>
      <Card title="AI images" description="Adds “Generate with AI” to image fields in the editor: describe a picture and it is created and saved to your media library. Uses your own OpenAI API key (or another OpenAI-compatible image service); the description is sent to that service when an editor generates an image.">
        <div className="uncoder-ui-setlist">
          <SettingRow title="Generate images in the editor">
            <Toggle checked={draft.images.enabled} onChange={(v) => setDraft({ ...draft, images: { ...draft.images, enabled: v } })} label="Generate images in the editor" />
          </SettingRow>
          {draft.images.enabled && (
            <>
              <SettingRow title="API key" htmlFor="uncoder-ui-aiimg-key" description={s.aiImages?.has_key ? 'A key is saved. Leave empty to keep it.' : 'Create one at platform.openai.com. Stays on the server; never shown again after saving.'}>
                <input id="uncoder-ui-aiimg-key" className="uncoder-ui-input" type="password" autoComplete="new-password" spellCheck={false} placeholder={s.aiImages?.has_key ? '•••••••• saved' : 'sk-…'} value={draft.images.key} onChange={(e) => setDraft({ ...draft, images: { ...draft.images, key: e.currentTarget.value.trim() } })} />
              </SettingRow>
              <SettingRow title="Model" htmlFor="uncoder-ui-aiimg-model" description="The image model name, e.g. gpt-image-1.">
                <input id="uncoder-ui-aiimg-model" className="uncoder-ui-input" value={draft.images.model} spellCheck={false} onChange={(e) => setDraft({ ...draft, images: { ...draft.images, model: e.currentTarget.value.trim() } })} />
              </SettingRow>
              <SettingRow title="Endpoint" htmlFor="uncoder-ui-aiimg-endpoint" description="Keep the default for OpenAI; change it for another service with the same API.">
                <input id="uncoder-ui-aiimg-endpoint" className="uncoder-ui-input uncoder-ui-input--mono" value={draft.images.endpoint} spellCheck={false} onChange={(e) => setDraft({ ...draft, images: { ...draft.images, endpoint: e.currentTarget.value.trim() } })} />
              </SettingRow>
            </>
          )}
        </div>
      </Card>
      <SaveBar dirty={dirty} saving={saving} onSave={save} onDiscard={() => setDraft(pick(s))} />
    </>
  );
}
