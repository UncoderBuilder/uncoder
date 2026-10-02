import { useEffect, useMemo, useState } from 'react';
import { Toggle } from '@editor/ui/primitives';
import { NumberInput } from '@editor/ui/inputs';
import { mcpApi, type McpSettings, type McpStatus } from '../lib/api';
import { useUnsavedGuard } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { Callout, Card, Checkbox, SaveBar, SettingRow, SkeletonRows } from '../ui/kit';

const originsText = (list: string[]) => list.join('\n');
const parseOrigins = (text: string) =>
  text
    .split(/[\s,]+/)
    .map((s) => s.trim())
    .filter(Boolean);

export function McpSettingsPanel({ status, onSaved }: { status: McpStatus | undefined; onSaved: (s: McpSettings) => void }) {
  const [draft, setDraft] = useState<McpSettings | null>(null);
  const [origins, setOrigins] = useState('');
  const [saving, setSaving] = useState(false);
  const saved = status?.settings;

  useEffect(() => {
    if (!saved) return;
    setDraft(saved);
    setOrigins(originsText(saved.allowed_origins));
  }, [saved]);

  const current = useMemo(() => (draft ? { ...draft, allowed_origins: parseOrigins(origins) } : null), [draft, origins]);
  const dirty = !!current && !!saved && JSON.stringify(current) !== JSON.stringify(saved);
  useUnsavedGuard(dirty);

  if (!draft || !status) return <SkeletonRows rows={6} cols={2} />;

  const set = <K extends keyof McpSettings>(key: K, value: McpSettings[K]) => setDraft((d) => (d ? { ...d, [key]: value } : d));

  const save = async () => {
    if (!current) return;
    setSaving(true);
    try {
      const res = await mcpApi.saveSettings({
        enabled: current.enabled,
        allowed_roles: current.allowed_roles,
        rate_limit: current.rate_limit,
        allow_registration: current.allow_registration,
        allowed_origins: current.allowed_origins,
        snapshots: current.snapshots,
        confirm_destructive: current.confirm_destructive,
        links: current.links,
        image_search: current.image_search,
        log_days: current.log_days,
      });
      onSaved(res);
      toast('MCP settings saved');
    } catch (e) {
      toastError(e);
    } finally {
      setSaving(false);
    }
  };

  const discard = () => {
    setDraft(saved!);
    setOrigins(originsText(saved!.allowed_origins));
  };

  return (
    <div className="uncoder-ui-stack">
      <Card title="Server">
        <div className="uncoder-ui-setlist">
          <SettingRow title="MCP server" description="When off, every MCP request is refused. Keys and connected apps are kept." htmlFor="uncoder-ui-mcp-enabled">
            <Toggle checked={draft.enabled} onChange={(v) => set('enabled', v)} label="MCP server enabled" />
          </SettingRow>
          <SettingRow title="Allowed roles" description="Users with these roles can connect AI clients. What they can change still follows their WordPress capabilities.">
            <div className="uncoder-ui-checklist uncoder-ui-checklist--cols">
              {status.roles.map((r) => (
                <Checkbox
                  key={r.slug}
                  label={r.name}
                  checked={r.slug === 'administrator' || draft.allowed_roles.includes(r.slug)}
                  disabled={r.slug === 'administrator'}
                  onChange={(v) => set('allowed_roles', v ? [...draft.allowed_roles, r.slug] : draft.allowed_roles.filter((x) => x !== r.slug))}
                />
              ))}
            </div>
          </SettingRow>
          <SettingRow title="Rate limit" description="Maximum tool calls per minute for each key or connected app (10–2000).">
            <NumberInput value={draft.rate_limit} onChange={(v) => set('rate_limit', v === '' ? 120 : v)} min={10} max={2000} step={10} ariaLabel="Rate limit" suffix={<span className="uncoder-ui-num__suffix">/ min</span>} width={130} />
          </SettingRow>
        </div>
      </Card>

      <Card title="Connections">
        <div className="uncoder-ui-setlist">
          <SettingRow title="Dynamic client registration" description="Lets new OAuth apps (Claude, ChatGPT connectors) register themselves. Turn off to allow only apps that already connected.">
            <Toggle checked={draft.allow_registration} onChange={(v) => set('allow_registration', v)} label="Allow dynamic client registration" />
          </SettingRow>
          <SettingRow title="Allowed origins" description="Browser-based clients whose Origin header is accepted, one per line (e.g. https://app.example.com). Requests without an Origin, like desktop apps, are not affected." htmlFor="uncoder-ui-mcp-origins">
            <textarea id="uncoder-ui-mcp-origins" className="uncoder-ui-textarea uncoder-ui-textarea--code" rows={3} value={origins} placeholder="https://app.example.com" onChange={(e) => setOrigins(e.currentTarget.value)} spellCheck={false} />
          </SettingRow>
          <SettingRow title="Connection links" description="One URL with its own key (?token=…), the simplest way to connect apps such as the ChatGPT desktop app. Only keys created as connection links work in a URL — never API keys or sign-ins — and only over HTTPS. Turn off to refuse every link.">
            <Toggle checked={draft.links} onChange={(v) => set('links', v)} label="Accept connection links" />
          </SettingRow>
        </div>
      </Card>

      <Card title="Safety">
        <div className="uncoder-ui-setlist">
          <SettingRow title="Undo snapshots" description="Save the previous state before every AI change, so it can be undone from the activity log.">
            <Toggle checked={draft.snapshots} onChange={(v) => set('snapshots', v)} label="Undo snapshots" />
          </SettingRow>
          <SettingRow title="Confirm destructive actions" description="Tools that delete or replace content require an explicit confirmation from the AI client.">
            <Toggle checked={draft.confirm_destructive} onChange={(v) => set('confirm_destructive', v)} label="Confirm destructive actions" />
          </SettingRow>
          <SettingRow title="Image search" description="Let AI clients search openly licensed images (Openverse) and import them into the media library.">
            <Toggle checked={draft.image_search} onChange={(v) => set('image_search', v)} label="Image search" />
          </SettingRow>
          <SettingRow title="Keep activity for" description="Older log entries are deleted daily (1–365 days).">
            <NumberInput value={draft.log_days} onChange={(v) => set('log_days', v === '' ? 30 : v)} min={1} max={365} ariaLabel="Log retention in days" suffix={<span className="uncoder-ui-num__suffix">days</span>} width={120} />
          </SettingRow>
        </div>
      </Card>
      {!draft.snapshots && <Callout tone="warning">Without snapshots, AI changes cannot be undone from the activity log.</Callout>}
      <SaveBar dirty={dirty} saving={saving} onSave={save} onDiscard={discard} />
    </div>
  );
}
