import { useEffect, useMemo, useRef, useState } from 'react';
import type { ElementSchema } from '@shared/types';
import { config, schemaOf, schemas } from '../lib/config';
import { beginDrag, insertNearSelection, insertTarget } from '../canvas/dnd';
import { Icon } from '../ui/Icon';
import { widgetIconSvg } from '../lib/widgetIcons';
import { useDoc } from '../store/doc';
import { useUi, showPanel } from '../store/ui';
import { takeInsertSearch } from '../app/smart';
import { disabledWidgets, toggleFavorite, usePrefs } from '../store/prefs';

const HIDDEN_IN_CONTEXT: Record<string, string[]> = {};

interface Group {
  id: string;
  label: string;
  items: ElementSchema[];
}

/**
 * Insert → Elements: widget tiles (icon and name; hover for the description, ☆ to pin it to Favourites), three to a
 * row, Favourites and recent first. Click adds at the target shown above, drag drops anywhere; in the search, ↑ / ↓
 * pick a result and Enter adds it. Widgets turned off in Settings → Elements are not offered.
 */
export function WidgetsPanel() {
  const [query, setQuery] = useState('');
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>({});
  const [active, setActive] = useState(-1);
  const recent = useUi((s) => s.recentWidgets);
  const favorites = usePrefs((s) => s.favorites);
  const input = useRef<HTMLInputElement>(null);
  const list = useRef<HTMLDivElement>(null);
  const docType = config.post.docType;

  // Deliberate "add something" moves (Insert tab, Shift+A, the + buttons, typing on the canvas) focus the search.
  useEffect(() => {
    const take = () => {
      const text = takeInsertSearch();
      if (text === undefined) return;
      if (text !== null) setQuery(text);
      input.current?.focus();
    };
    take();
    window.addEventListener('uncoder-ui:insert-search', take);
    return () => window.removeEventListener('uncoder-ui:insert-search', take);
  }, []);

  const groups = useMemo(() => {
    const q = query.trim().toLowerCase();
    const all = Object.values(schemas).filter((s) => {
      if (HIDDEN_IN_CONTEXT[docType]?.includes(s.name) || disabledWidgets.has(s.name)) return false;
      if (!q) return true;
      return s.title.toLowerCase().includes(q) || s.name.includes(q) || s.keywords.some((k) => k.toLowerCase().includes(q));
    });
    if (q) {
      if (!all.length) return [] as Group[];
      // Best matches first: the name starting with the query, then the name containing it, then keywords.
      const rank = (s: ElementSchema) => (s.title.toLowerCase().startsWith(q) ? 0 : s.title.toLowerCase().includes(q) ? 1 : 2);
      return [{ id: 'results', label: 'Results', items: [...all].sort((a, b) => rank(a) - rank(b)) }] as Group[];
    }
    const out: Group[] = [];
    const offered = (n: string) => !!schemas[n] && !disabledWidgets.has(n);
    // Pinned widgets, then the ones used lately: one row of quick picks.
    const quick = [...favorites.filter(offered), ...recent.filter((n) => offered(n) && !favorites.includes(n))].slice(0, 9).map((n) => schemas[n]);
    if (quick.length) out.push({ id: 'favorites', label: favorites.length ? 'Favourites and recent' : 'Recent', items: quick });
    for (const [id, label] of Object.entries(config.schema.categories)) {
      const items = all.filter((s) => s.category === id);
      if (items.length) out.push({ id, label, items });
    }
    const known = new Set(Object.keys(config.schema.categories));
    const other = all.filter((s) => !known.has(s.category));
    if (other.length) out.push({ id: 'other', label: 'Other', items: other });
    return out;
  }, [query, docType, recent, favorites]);

  // Rows in reading order, for the keyboard.
  const flat = useMemo(() => groups.flatMap((g) => (!query && collapsed[g.id] ? [] : g.items.map((s) => ({ key: `${g.id}:${s.name}`, schema: s })))), [groups, collapsed, query]);
  useEffect(() => setActive(query.trim() ? 0 : -1), [query]);
  useEffect(() => {
    if (active < 0) return;
    list.current?.querySelector(`[data-uncoder-ui-row="${active}"]`)?.scrollIntoView({ block: 'nearest' });
  }, [active]);

  const add = (type: string) => insertNearSelection({ kind: 'new', type });
  let row = -1;

  return (
    <div className="uncoder-ui-widgets">
      <div className="uncoder-ui-widgets__search">
        <label className="uncoder-ui-search">
          <Icon name="search" size={14} />
          <input
            ref={input}
            type="search"
            placeholder="Search widgets"
            value={query}
            onChange={(e) => setQuery(e.currentTarget.value)}
            onKeyDown={(e) => {
              if (e.key === 'Escape') {
                if (query) setQuery('');
                else input.current?.blur();
              } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const step = e.key === 'ArrowDown' ? 1 : -1;
                setActive((a) => (flat.length ? (a + step + flat.length) % flat.length : -1));
              } else if (e.key === 'Enter') {
                const pickRow = flat[active >= 0 ? active : 0];
                if (!pickRow) return;
                e.preventDefault();
                add(pickRow.schema.name);
                setQuery('');
              }
            }}
            aria-label="Search widgets"
            aria-activedescendant={active >= 0 ? `uncoder-ui-wrow-${active}` : undefined}
          />
        </label>
      </div>
      <InsertTarget />
      <div ref={list} className="uncoder-ui-widgets__list">
        {groups.map((g) => {
          const isCollapsed = !query && collapsed[g.id];
          return (
            <section key={g.id} className="uncoder-ui-wgroup">
              <button type="button" className="uncoder-ui-wgroup__head" aria-expanded={!isCollapsed} onClick={() => setCollapsed((c) => ({ ...c, [g.id]: !c[g.id] }))}>
                <span>{g.label}</span>
                <span className="uncoder-ui-wgroup__count">{g.items.length}</span>
                <Icon name="chevron-right" size={12} className="uncoder-ui-wgroup__caret" />
              </button>
              {!isCollapsed && (
                <div className="uncoder-ui-wlist">
                  {g.items.map((s) => {
                    row++;
                    const index = row;
                    return (
                      <button
                        key={s.name}
                        id={`uncoder-ui-wrow-${index}`}
                        type="button"
                        className={`uncoder-ui-wrow${index === active ? ' is-active' : ''}`}
                        data-uncoder-ui-tile=""
                        data-uncoder-ui-row={index}
                        aria-description={s.tip || s.description || undefined}
                        onDragStart={(e) => e.preventDefault()}
                        onPointerDown={(e) => {
                          if (e.button !== 0) return;
                          // A click without moving adds at the target (dnd onUp); dragging drops anywhere.
                          beginDrag(e, { kind: 'new', type: s.name }, s.title, s.icon);
                        }}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter' || e.key === ' ') {
                            e.preventDefault();
                            add(s.name);
                          } else if (e.key.toLowerCase() === 'f' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                            // F pins / unpins the focused widget (the ☆ is pointer-only inside the row button).
                            e.preventDefault();
                            e.stopPropagation();
                            toggleFavorite(s.name);
                          }
                        }}
                      >
                        <WidgetIcon name={s.name} icon={s.icon} />
                        <span className="uncoder-ui-wrow__label">{s.title}</span>
                        <FavoriteStar name={s.name} title={s.title} on={favorites.includes(s.name)} />
                        {(s.tip || s.description) && (
                          <span className="uncoder-ui-wrow__info" data-tip={s.tip || s.description} data-tip-force="" aria-hidden onPointerDown={(e) => e.stopPropagation()}>
                            <Icon name="info" size={12} stroke={1.8} />
                          </span>
                        )}
                      </button>
                    );
                  })}
                </div>
              )}
            </section>
          );
        })}
        {!groups.length && (
          <div className="uncoder-ui-widgets__none">
            No widget matches “{query}”.
            <button type="button" className="uncoder-ui-link" onClick={() => showPanel('ai')}>
              Ask AI to build it instead
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

/** ☆ on a widget row: pins it to Favourites (saved with the user's preferences). */
function FavoriteStar({ name, title, on }: { name: string; title: string; on: boolean }) {
  const label = on ? `Remove ${title} from favourites` : `Add ${title} to favourites`;
  return (
    <span
      role="button"
      tabIndex={-1}
      className={`uncoder-ui-wrow__fav${on ? ' is-on' : ''}`}
      aria-label={label}
      aria-pressed={on}
      data-tip={on ? 'Unpin from Favourites' : 'Pin to Favourites'}
      onPointerDown={(e) => e.stopPropagation()}
      onClick={(e) => {
        e.stopPropagation();
        toggleFavorite(name);
      }}
    >
      <Icon name="star" size={13} stroke={1.8} />
    </span>
  );
}

/** Where a click adds a widget: inside the selected container, after the selected element, or a new section. */
function InsertTarget() {
  const selected = useUi((s) => s.selected[0] ?? null);
  const nodes = useDoc((s) => s.doc.nodes);
  const name = (id: string) => nodes[id]?.label || schemaOf(nodes[id]?.type)?.title || 'element';
  let text: React.ReactNode = 'Adds a new section at the end of the page';
  let icon = 'arrow-down-to-line';
  if (selected && nodes[selected]) {
    const { parent, index } = insertTarget('heading');
    const siblings = parent ? nodes[parent]?.children ?? [] : useDoc.getState().doc.root;
    if (parent === selected) {
      text = (
        <>
          Adds inside <strong>{name(selected)}</strong>
        </>
      );
      icon = 'corner-down-right';
    } else if (index > 0 && siblings[index - 1]) {
      text = (
        <>
          Adds after <strong>{name(siblings[index - 1])}</strong>
        </>
      );
      icon = 'arrow-down';
    }
  }
  return (
    <p className="uncoder-ui-insert__target" role="status">
      <Icon name={icon} size={13} />
      <span>{text}</span>
    </p>
  );
}

/** Custom drawing for our widgets (lib/widgetIcons.ts); third-party widgets keep their Lucide icon. */
function WidgetIcon({ name, icon }: { name: string; icon: string }) {
  const svg = widgetIconSvg(name, 22);
  return svg ? <span className="uncoder-ui-wrow__icon" dangerouslySetInnerHTML={{ __html: svg }} /> : <Icon name={icon} size={20} stroke={1.6} className="uncoder-ui-wrow__icon" />;
}
