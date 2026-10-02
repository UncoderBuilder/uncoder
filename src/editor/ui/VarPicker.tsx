// Design variables (Styles → Variables) in size and spacing fields: a "{ }" menu to insert one, and a chip that
// shows which variable a value uses.
import type { ControlDef, KitVariable } from '@shared/types';
import { useKit } from '../store/kit';
import { useUi } from '../store/ui';
import { Menu, usePopover, type MenuItem } from './Popover';
import { IconButton } from './primitives';

export const varRef = (id: string) => `var(--uncoder-v-${id})`;
export const varId = (value: unknown): string | null => (typeof value === 'string' ? /^var\(--uncoder-v-([a-z0-9-]+)\)$/.exec(value)?.[1] ?? null : null);

const GROUP_LABEL: Record<string, string> = { spacing: 'Spacing', size: 'Size', radius: 'Radius', other: 'Other' };

/** Which variables a field wants first: spacing for padding / margin / gap, radius for corners, size for widths. */
export function varGroup(control: ControlDef, key: string): KitVariable['group'] {
  const hay = `${key} ${control.label ?? ''} ${Object.values(control.selectors ?? {}).join(' ')}`;
  if (/radius/i.test(hay)) return 'radius';
  if (/padding|margin|gap|space|spacing|offset|inset/i.test(hay)) return 'spacing';
  if (/width|height|size/i.test(hay)) return 'size';
  return 'other';
}

/** Opens Styles → Variables. */
export function manageVariables(): void {
  useUi.setState({ panel: 'kit' });
  window.setTimeout(() => window.dispatchEvent(new CustomEvent('uncoder-ui:kit-tab', { detail: 'variables' })), 0);
}

export function VarPicker({ group, current, onPick }: { group: KitVariable['group']; current?: string | null; onPick: (ref: string) => void }) {
  const vars = useKit((s) => s.kit.variables ?? []);
  const menu = usePopover();
  const ordered = [...vars.filter((v) => v.group === group), ...vars.filter((v) => v.group !== group)];
  const items: MenuItem[] = ordered.length
    ? [
        ...ordered.map(
          (v, i): MenuItem => ({
            label: `${v.name}  ·  ${v.value}`,
            icon: i < vars.filter((x) => x.group === group).length ? 'braces' : undefined,
            checked: current === v.id,
            onSelect: () => onPick(varRef(v.id)),
          }),
        ),
        'separator',
        { label: 'Manage variables…', icon: 'settings-2', onSelect: manageVariables },
      ]
    : [{ label: `No ${GROUP_LABEL[group].toLowerCase()} variables yet — add them…`, icon: 'plus', onSelect: manageVariables }];
  return (
    <>
      <IconButton ref={menu.anchorRef} icon="braces" label="Use a design variable" size={12} active={!!current} aria-expanded={menu.open} onClick={menu.toggle} className="uncoder-ui-varbtn" />
      <Menu anchor={menu.anchorRef} open={menu.open} onClose={menu.close} placement="bottom-end" width={240} items={items} />
    </>
  );
}

/** A value that is a design variable, shown by name; the × clears it. */
export function VarChip({ id, onClear }: { id: string; onClear: () => void }) {
  const v = useKit((s) => (s.kit.variables ?? []).find((x) => x.id === id));
  return (
    <span className={`uncoder-ui-varchip${v ? '' : ' is-missing'}`} data-tip={v ? `${v.name}: ${v.value}` : `Variable “${id}” no longer exists`}>
      <span className="uncoder-ui-varchip__name">{v?.name ?? id}</span>
      <button type="button" aria-label="Stop using this variable" onClick={onClear}>
        ×
      </button>
    </span>
  );
}
