import { memo, useEffect, useMemo, useState } from 'react';
import type { ControlDef, ElementSchema, SectionDef, Settings } from '@shared/types';
import { config, contentOnly, schemaOf } from '../lib/config';
import { deviceLabel } from '../lib/devices';
import { effectiveSettings, readValue, visible } from '../lib/schema';
import { sectionSummary } from '../lib/summary';
import { lockedBy, toggleLocked, useDoc } from '../store/doc';
import { pick } from './smart';
import { breakpoints, setDevice, useUi, type InspectorTab } from '../store/ui';
import { Icon } from '../ui/Icon';
import { Menu, usePopover } from '../ui/Popover';
import { Button, IconButton } from '../ui/primitives';
import { ControlRow } from '../controls/ControlRow';
import { ClassesBar } from './ClassesBar';
import { elementMenuItems } from './ContextMenu';
import { settingsTitle } from '../lib/docInfo';
import { cleanState, stateable, stateLabel, usedStates } from '../lib/states';
import { TextInput } from '../ui/inputs';
import { InspectorTargets, sharedSchema } from './inspectorTargets';

const TAB_LABEL: Record<InspectorTab, string> = { content: 'Content', design: 'Design', behaviour: 'Behaviour' };
/** Advanced sections that are visual: they live in Design, the rest in Behaviour. */
const DESIGN_ADVANCED = new Set(['_section_layout', '_section_style', '_section_mask']);
const SECTION_LABEL: Record<string, string> = { _section_layout: 'Spacing & position', _section_style: 'Background & border' };

const tabOf = (s: SectionDef): InspectorTab => (s.tab === 'content' ? 'content' : s.tab === 'style' || DESIGN_ADVANCED.has(s.id) ? 'design' : 'behaviour');

/** Where a value set on the current device applies ("applies to all widths", "Tablet and smaller"). */
function scopeHint(device: string): string {
  if (device === 'desktop') return breakpoints.length > 1 ? 'applies to all widths' : 'applies everywhere';
  const bp = breakpoints.find((b) => b.id === device);
  if (!bp) return '';
  return bp.direction === 'min' ? `${bp.label} and wider` : `${bp.label} and smaller`;
}

/** The inspector (right island): the selected element's settings, or the page when nothing is selected. */
export function Inspector() {
  const selected = useUi((s) => s.selected);
  const id = selected[0];
  const node = useDoc((s) => (id ? s.doc.nodes[id] : undefined));
  // Several selected: edit them together — every control for one type, the shared ones for mixed types.
  const typesKey = useDoc((s) => selected.map((i) => s.doc.nodes[i]?.type ?? '').join(','));
  const ids = useMemo(() => selected.filter((i) => useDoc.getState().doc.nodes[i]), [selected, typesKey]);
  const schema = useMemo(() => {
    const types = [...new Set(typesKey.split(',').filter(Boolean))];
    if (!node) return undefined;
    return types.length > 1 ? sharedSchema(types) : schemaOf(node.type);
  }, [node?.type, typesKey]);
  return (
    <aside className="uncoder-ui-inspector uncoder-ui-island" aria-label="Settings" data-uncoder-ui-selected={node ? node.id : ''}>
      {!node ? (
        <PageSummary />
      ) : !schema ? (
        <div className="uncoder-ui-inspector__empty">Unknown element type “{node.type}”.</div>
      ) : (
        <InspectorTargets.Provider value={ids}>
          <ElementInspector key={ids.join(',')} id={node.id} schema={schema} multi={ids.length} />
        </InspectorTargets.Provider>
      )}
    </aside>
  );
}

function PageSummary() {
  const count = useDoc((s) => Object.keys(s.doc.nodes).length);
  const title = useDoc((s) => s.title);
  return (
    <div className="uncoder-ui-insp-empty">
      <p className="uncoder-ui-insp-empty__title">{title || 'Untitled'}</p>
      <p className="uncoder-ui-insp-empty__meta">
        {config.post.typeLabel} · {count} element{count === 1 ? '' : 's'}
      </p>
      <p className="uncoder-ui-insp-empty__text">Select an element on the canvas or in Layers to edit it here.</p>
      <div className="uncoder-ui-insp-empty__actions">
        <Button icon="plus" onClick={() => useUi.setState({ panel: 'add' })}>
          Insert elements
        </Button>
        <Button icon="file-cog" onClick={() => useUi.setState({ panel: 'page' })}>
          {settingsTitle()}
        </Button>
      </div>
      <dl className="uncoder-ui-insp-empty__keys">
        <div>
          <dt>
            <kbd>Enter</kbd> / <kbd>Esc</kbd>
          </dt>
          <dd>Child / parent</dd>
        </div>
        <div>
          <dt>
            <kbd>Tab</kbd>
          </dt>
          <dd>Next sibling</dd>
        </div>
        <div>
          <dt>
            <kbd>Ctrl</kbd> <kbd>K</kbd>
          </dt>
          <dd>Commands &amp; AI</dd>
        </div>
      </dl>
    </div>
  );
}

const ElementInspector = memo(function ElementInspector({ id, schema, multi }: { id: string; schema: ElementSchema; multi: number }) {
  const node = useDoc((s) => s.doc.nodes[id]);
  const tab = useUi((s) => s.inspectorTab);
  const device = useUi((s) => s.device);
  const state = useUi((s) => s.inspectorState);
  const [search, setSearch] = useState('');
  const [showSearch, setShowSearch] = useState(false);
  const [changedOnly, setChangedOnly] = useState(false);
  const menu = usePopover();

  // Content-only roles see just the Content tab (the server rejects design changes anyway).
  const tabs = useMemo(() => (['content', 'design', 'behaviour'] as InspectorTab[]).filter((t) => schema.sections.some((s) => tabOf(s) === t) && (t === 'content' || !contentOnly())), [schema]);
  const current = tabs.includes(tab) ? tab : tabs[0];
  const query = search.trim().toLowerCase();
  const sections = useMemo(() => {
    const inTab = (t: InspectorTab) => {
      const list = schema.sections.filter((s) => tabOf(s) === t);
      // Design: the element's own style groups first, then spacing, then background & border.
      return t === 'design' ? [...list.filter((s) => s.tab === 'style'), ...list.filter((s) => s.tab !== 'style')] : list;
    };
    // A search looks through every tab, so "opacity" is found wherever it lives.
    return query ? tabs.flatMap(inTab) : inTab(current);
  }, [schema, current, tabs, query]);
  const tag = String(node.settings.tag ?? node.settings.title_tag ?? node.settings.html_tag ?? '') || '';
  const used = usedStates(node.settings);
  const [customOpen, setCustomOpen] = useState(false);
  const setState = (v: string) => useUi.setState({ inspectorState: v });

  return (
    <div className="uncoder-ui-insp">
      <div className="uncoder-ui-insp__head">
        <div className="uncoder-ui-insp__name">
          <span className="uncoder-ui-insp__title">{multi > 1 ? (schema.name === '__shared' ? `${multi} elements` : `${multi} × ${schema.title}`) : node.label || schema.title}</span>
          {tag && /^(h[1-6]|p|div|span|section|header|footer|nav|article|aside|main)$/.test(tag) && <span className="uncoder-ui-chip-tag">{tag}</span>}
          {multi > 1 && <span className="uncoder-ui-chip-tag" data-tip="Changes apply to every selected element">all</span>}
        </div>
        <IconButton icon="search" label="Search settings" size={15} active={showSearch} onClick={() => setShowSearch((v) => !v)} />
        <IconButton ref={menu.anchorRef} icon="ellipsis" label="Element actions" size={15} onClick={menu.toggle} />
        <Menu anchor={menu.anchorRef} open={menu.open} onClose={menu.close} placement="bottom-end" items={elementMenuItems(id)} />
      </div>
      <div className="uncoder-ui-tabs" role="tablist" aria-label={`${node.label || schema.title} settings`}>
        {tabs.map((t) => (
          <button key={t} type="button" role="tab" aria-selected={t === current} className={`uncoder-ui-tabs__tab${t === current ? ' is-active' : ''}`} onClick={() => useUi.setState({ inspectorTab: t })}>
            {TAB_LABEL[t]}
          </button>
        ))}
      </div>
      {current !== 'content' && (
        <div className="uncoder-ui-context">
          <div className="uncoder-ui-context__row">
            <span className="uncoder-ui-context__label">Editing</span>
            <select className={`uncoder-ui-ctxsel${device !== 'desktop' ? ' is-scoped' : ''}`} aria-label="Device you are editing" value={device} onChange={(e) => setDevice(e.currentTarget.value)}>
              {breakpoints.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.label}
                </option>
              ))}
            </select>
            <select
              className={`uncoder-ui-ctxsel${state !== 'normal' ? ' is-scoped' : ''}`}
              aria-label="State you are editing"
              value={state}
              onChange={(e) => {
                const v = e.currentTarget.value;
                if (v === '__custom') setCustomOpen(true);
                else {
                  setCustomOpen(false);
                  setState(v);
                }
              }}
            >
              {['normal', 'hover', 'focus', 'active', 'before', 'after'].map((k) => (
                <option key={k} value={k}>
                  {stateLabel(k)}
                  {used.includes(k) ? ' •' : ''}
                </option>
              ))}
              {[...new Set([...used.filter((k) => k.includes('&')), ...(state.includes('&') ? [state] : [])])].map((k) => (
                <option key={k} value={k}>
                  {k}
                  {used.includes(k) ? ' •' : ''}
                </option>
              ))}
              <option value="__custom">Custom selector…</option>
            </select>
          </div>
          {customOpen && (
            <div className="uncoder-ui-context__custom">
              <TextInput
                autoFocus
                value=""
                placeholder="&.is-open · & > .icon · &:nth-child(2)"
                aria-label="Custom selector (& is this element)"
                onCommit={(v) => {
                  const raw = v.trim();
                  const sel = cleanState(raw.includes('&') ? raw : raw ? `& ${raw}` : '');
                  setCustomOpen(false);
                  if (sel) setState(sel);
                }}
                onKeyDown={(e) => {
                  if (e.key === 'Escape') setCustomOpen(false);
                }}
              />
            </div>
          )}
          <p className="uncoder-ui-context__hint" title={deviceLabel(device)}>
            {scopeHint(device)}
            {state !== 'normal' ? ` · ${stateLabel(state)} values (Normal shows faded)` : ''}
          </p>
        </div>
      )}
      {(showSearch || search) && (
        <div className="uncoder-ui-insp__search">
          <Icon name="search" size={13} />
          <input type="search" autoFocus placeholder="Search settings…" value={search} onChange={(e) => setSearch(e.currentTarget.value)} aria-label="Search settings" />
          <button type="button" className={`uncoder-ui-chip-toggle${changedOnly ? ' is-on' : ''}`} aria-pressed={changedOnly} onClick={() => setChangedOnly((v) => !v)}>
            Changed only
          </button>
        </div>
      )}
      {lockedBy(id) && (
        <div className="uncoder-ui-insp__lock" role="status">
          <Icon name="lock" size={14} />
          <span>{lockedBy(id) === id ? 'Locked: it cannot be moved, deleted or edited.' : 'Inside a locked element.'}</span>
          {lockedBy(id) === id ? (
            <Button size="sm" icon="lock-open" onClick={() => toggleLocked(id)}>
              Unlock
            </Button>
          ) : (
            <Button size="sm" onClick={() => pick(lockedBy(id)!)}>
              Select it
            </Button>
          )}
        </div>
      )}
      <div className={`uncoder-ui-insp__body${lockedBy(id) ? ' is-locked' : ''}`}>
        {current === 'design' && !search && !changedOnly && multi === 1 && <ClassesBar id={id} />}
        {multi > 1 && <p className="uncoder-ui-insp__multi">Editing {multi} elements{schema.name === '__shared' ? ': only the settings they share' : ''}. “Mixed” means their values differ; a change sets all of them.</p>}
        {sections.map((section, i) => (
          <SectionView key={section.id} id={id} schema={schema} section={section} index={i} search={query} changedOnly={changedOnly} />
        ))}
        {schema.description && current === 'content' && !search && <p className="uncoder-ui-insp__desc">{schema.description}</p>}
        {current !== 'content' && (
          <p className="uncoder-ui-legend" aria-hidden>
            <span className="uncoder-ui-ctl__dot is-set" /> Set here <span className="uncoder-ui-ctl__dot is-inherited" /> Inherited
          </p>
        )}
      </div>
    </div>
  );
});

function hasValue(settings: Settings, key: string, control: ControlDef | undefined, device: string): boolean {
  return !!control && readValue(settings, key, control, device).from !== null;
}

function SectionView({ id, schema, section, index, search, changedOnly }: { id: string; schema: ElementSchema; section: SectionDef; index: number; search: string; changedOnly: boolean }) {
  const settings = useDoc((s) => s.doc.nodes[id]?.settings);
  const device = useUi((s) => s.device);
  const key = `${schema.name}:${section.id}`;
  const openState = useUi((s) => s.openSections[key]);
  const eff = useMemo(() => effectiveSettings(schema, settings ?? {}), [schema, settings]);
  const summary = useMemo(() => sectionSummary(schema, section, settings ?? {}, device), [schema, section, settings, device]);
  const tab = useUi((s) => s.inspectorTab);
  const state = useUi((s) => (tab === 'content' ? 'normal' : s.inspectorState));
  if (section.condition && !visible({ type: 'heading', condition: section.condition } as ControlDef, eff, schema.controls)) return null;

  const matches = (k: string) => {
    // In a state, only what can change in that state (style controls).
    if (state !== 'normal' && !stateable(schema.controls[k], state)) return false;
    if (changedOnly && !hasValue(settings ?? {}, k, schema.controls[k], device)) return false;
    if (!search) return true;
    const c = schema.controls[k];
    return !!c && ((c.label ?? k).toLowerCase().includes(search) || k.includes(search));
  };
  const keys = section.controls.filter((k) => (k.startsWith('@tabs:') ? true : matches(k)));
  const tabKeys = Object.values(section.tab_controls ?? {}).flatMap((t) => Object.values(t).flat());
  const filtering = !!search || changedOnly || state !== 'normal';
  if (filtering && !keys.some((k) => !k.startsWith('@tabs:')) && !tabKeys.some(matches)) return null;

  const open = filtering ? true : openState ?? (index === 0 || section.open === true);

  return (
    <section className={`uncoder-ui-sec${open ? ' is-open' : ''}`}>
      <button type="button" className="uncoder-ui-sec__head" aria-expanded={open} onClick={() => useUi.setState((s) => ({ openSections: { ...s.openSections, [key]: !open } }))}>
        <span className="uncoder-ui-sec__label">{SECTION_LABEL[section.id] ?? section.label}</span>
        {!open && <span className={`uncoder-ui-sec__sum${summary ? '' : ' is-default'}`}>{summary || 'Default'}</span>}
        <Icon name="chevron-down" size={13} className="uncoder-ui-sec__caret" />
      </button>
      {open && (
        <div className="uncoder-ui-sec__body">
          {keys.map((k) =>
            k.startsWith('@tabs:') ? (
              <ControlTabs key={k} id={id} schema={schema} section={section} tabsId={k.slice(6)} settings={eff} matches={matches} />
            ) : (
              <ControlRow key={k} id={id} keyName={k} control={schema.controls[k]} controls={schema.controls} settings={eff} stateKey={state} />
            ),
          )}
        </div>
      )}
    </section>
  );
}

/** Normal / Hover (…) control tabs; they follow the inspector's state switch unless changed by hand. */
function ControlTabs({ id, schema, section, tabsId, settings, matches }: { id: string; schema: ElementSchema; section: SectionDef; tabsId: string; settings: Record<string, any>; matches: (k: string) => boolean }) {
  const tabs = schema.ui_tabs[tabsId] ?? {};
  const inspectorTab = useUi((s) => s.inspectorTab);
  const state = useUi((s) => (inspectorTab === 'content' ? 'normal' : s.inspectorState));
  const ids = Object.keys(tabs);
  const [manual, setManual] = useState<string | null>(null);
  useEffect(() => setManual(null), [state]);
  const active = manual ?? (ids.includes(state) ? state : ids[0]);
  const keys = (section.tab_controls?.[tabsId]?.[active] ?? []).filter(matches);
  return (
    <div className="uncoder-ui-ctabs">
      <div className="uncoder-ui-ctabs__bar" role="tablist">
        {Object.entries(tabs).map(([t, label]) => (
          <button key={t} type="button" role="tab" aria-selected={t === active} className={`uncoder-ui-ctabs__tab${t === active ? ' is-active' : ''}`} onClick={() => setManual(t)}>
            {label}
          </button>
        ))}
      </div>
      {keys.map((k) => (
        // The native Hover tab writes its own keys; any other state goes into _states.
        <ControlRow key={k} id={id} keyName={k} control={schema.controls[k]} controls={schema.controls} settings={settings} stateKey={active === state ? 'normal' : state} />
      ))}
    </div>
  );
}
