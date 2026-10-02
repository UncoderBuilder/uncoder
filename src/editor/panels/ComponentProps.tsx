import { useMemo, useState } from 'react';
import { config, schemaOf } from '../lib/config';
import { setPageSettings, useDoc } from '../store/doc';
import { useUi } from '../store/ui';
import { Icon } from '../ui/Icon';
import { Button, IconButton } from '../ui/primitives';

/** Control types a component property may expose (twin of Site\Components::TYPES). */
export const PROP_TYPES = ['text', 'textarea', 'wysiwyg', 'media', 'url', 'link', 'icon', 'number', 'select', 'choose', 'switch', 'color', 'gallery'];

export interface ComponentProp {
  key: string;
  label: string;
  element: string;
  setting: string;
}

const NO_PROPS: ComponentProp[] = [];

const keyFrom = (label: string) =>
  label
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_|_$/g, '')
    .slice(0, 40) || 'prop';

/**
 * Section templates: choose which settings each placement (Template widget) may change — the
 * section becomes a component with properties, like "Title", "Image" and "Button link".
 */
export function ComponentProps() {
  const props: ComponentProp[] = useDoc((s) => (Array.isArray(s.pageSettings.component_props) ? s.pageSettings.component_props : NO_PROPS));
  const nodes = useDoc((s) => s.doc.nodes);
  const selected = useUi((s) => s.selected[0]);
  const [setting, setSetting] = useState('');
  const node = selected ? nodes[selected] : undefined;
  const schema = node ? schemaOf(node.type) : undefined;
  const options = useMemoOptions(schema?.controls, node?.id, props);
  if (config.post.docType !== 'section') return null;
  const save = (next: ComponentProp[]) => setPageSettings({ component_props: next });

  const add = () => {
    const c = schema?.controls[setting];
    if (!node || !c) return;
    const base = `${node.label || schema?.title || node.type} ${c.label ?? setting}`.trim();
    const label = (c.label ?? setting).trim();
    let key = keyFrom(label);
    for (let i = 2; props.some((p) => p.key === key); i++) key = `${keyFrom(label)}_${i}`;
    save([...props, { key, label: props.some((p) => p.label === label) ? base : label, element: node.id, setting }]);
    setSetting('');
  };

  return (
    <div className="uncoder-ui-compprops">
      <div className="uncoder-ui-ctl-heading">Component properties</div>
      <p className="uncoder-ui-note">Settings every copy of this section may change (in the Template widget). Everything else stays in sync with this design.</p>
      {props.length > 0 && (
        <ul className="uncoder-ui-compprops__list">
          {props.map((p, i) => {
            const target = nodes[p.element];
            const tSchema = target ? schemaOf(target.type) : undefined;
            return (
              <li key={p.key} className={target ? '' : 'is-missing'}>
                <input
                  className="uncoder-ui-input"
                  value={p.label}
                  aria-label="Property label"
                  onChange={(e) => save(props.map((x, j) => (j === i ? { ...x, label: e.currentTarget.value } : x)))}
                />
                <span className="uncoder-ui-compprops__target" title={target ? undefined : 'The element was deleted'}>
                  {target ? `${target.label || tSchema?.title} · ${tSchema?.controls[p.setting]?.label ?? p.setting}` : 'Missing element'}
                </span>
                <IconButton icon="x" size={12} label={`Remove ${p.label}`} onClick={() => save(props.filter((_, j) => j !== i))} />
              </li>
            );
          })}
        </ul>
      )}
      {node && schema ? (
        options.length ? (
          <div className="uncoder-ui-compprops__add">
            <select className="uncoder-ui-select" value={setting} onChange={(e) => setSetting(e.currentTarget.value)} aria-label="Setting to expose">
              <option value="">Expose a setting of “{node.label || schema.title}”…</option>
              {options.map(([k, label]) => (
                <option key={k} value={k}>
                  {label}
                </option>
              ))}
            </select>
            <Button size="sm" icon="plus" onClick={add} disabled={!setting}>
              Add
            </Button>
          </div>
        ) : (
          <p className="uncoder-ui-note">“{node.label || schema.title}” has no content settings left to expose.</p>
        )
      ) : (
        <p className="uncoder-ui-note">
          <Icon name="mouse-pointer-click" size={12} /> Select an element in the section to expose one of its settings.
        </p>
      )}
    </div>
  );
}

function useMemoOptions(controls: Record<string, any> | undefined, id: string | undefined, props: ComponentProp[]): Array<[string, string]> {
  return useMemo(() => {
    if (!controls || !id) return [];
    return Object.entries(controls)
      .filter(([k, c]) => c.tab === 'content' && PROP_TYPES.includes(c.type) && !k.startsWith('_') && !props.some((p) => p.element === id && p.setting === k))
      .map(([k, c]) => [k, c.label ?? k] as [string, string]);
  }, [controls, id, props]);
}
