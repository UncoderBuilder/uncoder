// Behaviour → Interactions: "When [trigger] → [action] [target]" rows (Core\Interactions, frontend/modules/interactions.ts).
import { useMemo } from 'react';
import type { ControlProps } from './ControlRow';
import { schemaOf } from '../lib/config';
import { useDoc } from '../store/doc';
import { useLookup } from './lookup';
import { NumberInput, TextInput } from '../ui/inputs';
import { Icon } from '../ui/Icon';
import { IconButton } from '../ui/primitives';

interface IxRow {
  _id: string;
  trigger: string;
  action: string;
  target?: string;
  element?: string;
  selector?: string;
  value?: string;
  offset?: number;
  delay?: number;
}

const TRIGGERS: Record<string, string> = {
  click: 'Clicked',
  mouseenter: 'Mouse enters',
  mouseleave: 'Mouse leaves',
  enter: 'Scrolls into view',
  leave: 'Scrolls out of view',
  load: 'Page loads',
  scroll: 'Page scrolled past…',
};
const ACTIONS: Record<string, string> = {
  toggle: 'Show / hide',
  show: 'Show',
  hide: 'Hide',
  toggle_class: 'Toggle class',
  add_class: 'Add class',
  remove_class: 'Remove class',
  set_attr: 'Set attribute',
  remove_attr: 'Remove attribute',
  open_popup: 'Open popup',
  close_popup: 'Close popup',
  scroll_to: 'Scroll to',
};
const TARGETS: Record<string, string> = { self: 'this element', element: 'another element', selector: 'CSS selector' };
const newId = () => Math.random().toString(36).slice(2, 9);

export function InteractionsControl({ id, value, onChange }: ControlProps<IxRow[]>) {
  const rows: IxRow[] = Array.isArray(value) ? value : [];
  const set = (i: number, patch: Partial<IxRow>) => onChange(rows.map((r, j) => (j === i ? { ...r, ...patch } : r)));
  const nodes = useDoc((s) => s.doc.nodes);
  const root = useDoc((s) => s.doc.root);
  const popups = useLookup(rows.some((r) => r.action === 'open_popup' || r.action === 'close_popup') ? 'popups' : null);

  // Other elements, in page order, indented by depth (named ones first read best).
  const elements = useMemo(() => {
    const out: Array<{ id: string; label: string }> = [];
    const walk = (ids: string[], depth: number) => {
      for (const nid of ids) {
        const n = nodes[nid];
        if (!n) continue;
        if (nid !== id) out.push({ id: nid, label: `${'  '.repeat(depth)}${n.label || schemaOf(n.type)?.title || n.type}` });
        walk(n.children, depth + 1);
      }
    };
    walk(root, 0);
    return out;
  }, [nodes, root, id]);

  return (
    <div className="uncoder-ui-ix">
      {rows.map((row, i) => {
        const popup = row.action === 'open_popup' || row.action === 'close_popup';
        return (
          <div key={row._id || i} className="uncoder-ui-ix__row">
            <div className="uncoder-ui-ix__line">
              <span className="uncoder-ui-ix__word">When</span>
              <select className="uncoder-ui-select" aria-label="Trigger" value={row.trigger} onChange={(e) => set(i, { trigger: e.currentTarget.value, offset: e.currentTarget.value === 'scroll' ? row.offset ?? 100 : undefined })}>
                {Object.entries(TRIGGERS).map(([k, l]) => (
                  <option key={k} value={k}>
                    {l}
                  </option>
                ))}
              </select>
              <IconButton icon="x" label="Remove this interaction" size={13} onClick={() => onChange(rows.length > 1 ? rows.filter((_, j) => j !== i) : undefined)} />
            </div>
            {row.trigger === 'scroll' && (
              <div className="uncoder-ui-ix__line">
                <span className="uncoder-ui-ix__word">by</span>
                <NumberInput value={row.offset ?? 100} min={0} onChange={(v) => set(i, { offset: Number(v) || 0 })} ariaLabel="Scroll offset in pixels" />
                <span className="uncoder-ui-ix__word">px</span>
              </div>
            )}
            <div className="uncoder-ui-ix__line">
              <span className="uncoder-ui-ix__word">do</span>
              <select className="uncoder-ui-select" aria-label="Action" value={row.action} onChange={(e) => set(i, { action: e.currentTarget.value, value: undefined })}>
                {Object.entries(ACTIONS).map(([k, l]) => (
                  <option key={k} value={k}>
                    {l}
                  </option>
                ))}
              </select>
            </div>
            {!popup && (
              <div className="uncoder-ui-ix__line">
                <span className="uncoder-ui-ix__word">on</span>
                <select className="uncoder-ui-select" aria-label="Target" value={row.target ?? 'self'} onChange={(e) => set(i, { target: e.currentTarget.value })}>
                  {Object.entries(TARGETS).map(([k, l]) => (
                    <option key={k} value={k}>
                      {l}
                    </option>
                  ))}
                </select>
              </div>
            )}
            {!popup && row.target === 'element' && (
              <select className="uncoder-ui-select" aria-label="Element" value={row.element ?? ''} onChange={(e) => set(i, { element: e.currentTarget.value })}>
                <option value="">Choose an element…</option>
                {elements.map((el) => (
                  <option key={el.id} value={el.id}>
                    {el.label}
                  </option>
                ))}
              </select>
            )}
            {!popup && row.target === 'selector' && <TextInput value={row.selector ?? ''} placeholder=".menu-panel, #faq" onChange={(v) => set(i, { selector: v })} aria-label="CSS selector" />}
            {['add_class', 'remove_class', 'toggle_class'].includes(row.action) && <TextInput value={row.value ?? ''} placeholder="Class name, e.g. is-open" onChange={(v) => set(i, { value: v })} aria-label="Class name" />}
            {row.action === 'set_attr' && <TextInput value={row.value ?? ''} placeholder="name=value, e.g. aria-pressed=true" onChange={(v) => set(i, { value: v })} aria-label="Attribute" />}
            {row.action === 'remove_attr' && <TextInput value={row.value ?? ''} placeholder="Attribute name" onChange={(v) => set(i, { value: v })} aria-label="Attribute name" />}
            {popup && (
              <select className="uncoder-ui-select" aria-label="Popup" value={row.value ?? ''} onChange={(e) => set(i, { value: e.currentTarget.value })}>
                <option value="">{row.action === 'close_popup' ? 'The popup it is in' : 'Choose a popup…'}</option>
                {(popups ?? []).map((p) => (
                  <option key={p.value} value={p.value}>
                    {p.label}
                  </option>
                ))}
              </select>
            )}
            <div className="uncoder-ui-ix__line is-sub">
              <span className="uncoder-ui-ix__word">after</span>
              <NumberInput value={row.delay ?? 0} min={0} step={100} onChange={(v) => set(i, { delay: Number(v) || undefined })} ariaLabel="Delay in milliseconds" />
              <span className="uncoder-ui-ix__word">ms</span>
            </div>
          </div>
        );
      })}
      <button type="button" className="uncoder-ui-conds__add is-set" onClick={() => onChange([...rows, { _id: newId(), trigger: 'click', action: 'toggle', target: 'element' }])}>
        <Icon name="plus" size={12} /> Add an interaction
      </button>
    </div>
  );
}
