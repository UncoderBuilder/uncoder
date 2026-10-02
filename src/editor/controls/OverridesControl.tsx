import { useEffect, useState } from 'react';
import type { ControlDef, ElementNode, Settings } from '@shared/types';
import { api } from '../lib/api';
import { schemaOf } from '../lib/config';
import { Icon } from '../ui/Icon';
import type { ControlProps } from './ControlRow';
import { CONTROLS, STACKED } from './registry';

interface Prop {
  key: string;
  label: string;
  element: string;
  setting: string;
}
interface Loaded {
  props: Prop[];
  nodes: Record<string, ElementNode>;
}

const cache = new Map<number, Promise<Loaded>>();

function loadSection(id: number): Promise<Loaded> {
  if (!cache.has(id)) {
    cache.set(
      id,
      api<{ elements: ElementNode[]; pageSettings: Settings }>(`documents/${id}`).then((doc) => {
        const nodes: Record<string, ElementNode> = {};
        const walk = (list: ElementNode[]) => list.forEach((n) => ((nodes[n.id] = n), walk(n.children ?? [])));
        walk(doc.elements ?? []);
        return { props: Array.isArray(doc.pageSettings?.component_props) ? doc.pageSettings.component_props : [], nodes };
      }),
    );
    // Keep it fresh when the section is edited in another tab.
    setTimeout(() => cache.delete(id), 30000);
  }
  return cache.get(id)!;
}

/**
 * Template widget → "Content of this copy": one field per component property of the chosen section,
 * rendered with the target setting's own control. Empty fields keep the section's content.
 */
export function OverridesControl({ value, onChange, settings }: ControlProps<Settings>) {
  const id = Number(settings.template_id) || 0;
  const [data, setData] = useState<Loaded | null>(null);
  const [error, setError] = useState(false);

  useEffect(() => {
    if (!id) return;
    let live = true;
    setError(false);
    loadSection(id).then(
      (d) => live && setData(d),
      () => live && setError(true),
    );
    return () => {
      live = false;
    };
  }, [id]);

  if (!id) return null;
  if (error) return <p className="uncoder-ui-note">Could not load the section.</p>;
  if (!data) return <p className="uncoder-ui-note">Loading component properties…</p>;
  if (!data.props.length) {
    return (
      <p className="uncoder-ui-note">
        <Icon name="info" size={12} /> This section has no component properties: every copy shows the same content. Open the section and expose settings in Section settings → Component properties.
      </p>
    );
  }

  const values = value ?? {};
  const set = (key: string, v: any) => {
    const next = { ...values };
    if (v === undefined || v === '' || (Array.isArray(v) && !v.length)) delete next[key];
    else next[key] = v;
    onChange(Object.keys(next).length ? next : undefined);
  };

  return (
    <div className="uncoder-ui-overrides">
      {data.props.map((p) => {
        const target = data.nodes[p.element];
        const control: ControlDef | undefined = target ? schemaOf(target.type)?.controls[p.setting] : undefined;
        const Comp = control ? CONTROLS[control.type] : undefined;
        if (!target || !control || !Comp) return null;
        const own = target.settings?.[p.setting];
        return (
          <div key={p.key} className={`uncoder-ui-ctl${STACKED.has(control.type) ? ' uncoder-ui-ctl--stacked' : ''}`}>
            <div className="uncoder-ui-ctl__label">
              <span className={`uncoder-ui-ctl__text${p.key in values ? ' is-set' : ''}`}>{p.label}</span>
            </div>
            <div className="uncoder-ui-ctl__input">
              <Comp control={{ ...control, label: p.label }} value={values[p.key]} placeholder={own} onChange={(v: any) => set(p.key, v)} device="desktop" id={`${target.id}-${p.key}`} keyName={p.setting} settings={target.settings ?? {}} />
            </div>
          </div>
        );
      })}
    </div>
  );
}
