import { useEffect, useMemo, useState } from 'react';
import { Button } from '@editor/ui/primitives';
import { templatesApi, templatesMeta, type Template } from '../lib/api';
import { can, cfg, screenUrl } from '../lib/config';
import { useHashState, useResource } from '../lib/hooks';
import { Callout, Card, EmptyState, ErrorState, PageHeader, SearchInput, SectionNav, SkeletonRows, Workspace, useSubCrumb, type NavGroup } from '../ui/kit';
import { Icon } from '@editor/ui/Icon';
import { NAV_LABELS, THEME_GROUPS, THEME_TYPES, TYPE_INFO } from '../templates/common';
import { ConditionsDialog } from '../templates/ConditionsDialog';
import { NewTemplateDialog } from '../templates/NewTemplateDialog';
import { ImportDialog } from '../templates/ImportDialog';
import { PopupDrawer } from '../templates/PopupDrawer';
import { PreviewDialog } from '../templates/PreviewDialog';
import { TemplateTable, type ListHandlers, type ListMode } from '../templates/TemplateList';

type Filter = 'all' | (typeof THEME_TYPES)[number];

const matches = (t: Template, q: string) => !q || t.title.toLowerCase().includes(q.toLowerCase()) || String(t.id) === q.trim();

/** What the empty state of a section suggests. */
const EMPTY_COPY: Record<string, { title: string; body: string }> = {
  all: { title: 'Design your first template', body: 'Start with a header and a footer: they replace your theme’s ones on every page.' },
  popup: { title: 'No popups yet', body: 'Collect emails, announce offers or confirm actions. Design it in the builder, then choose its triggers and where it may open.' },
  section: { title: 'No saved sections yet', body: 'Save any section from the builder (right-click → Save as template), or create one here. Edit it once and every page that embeds it updates.' },
};

/** The Theme Builder's types: saved sections have their own tab in the Library. */
const BUILDER_TYPES = THEME_TYPES.filter((t) => t !== 'section');

/**
 * Theme Builder: site parts, page layouts, popups and reusable parts (loop items, mega menus) in one place. The
 * section is in the URL hash (#popup, #loop-item…). With only="section" it is the Library's Saved sections tab
 * (authors without theme rights manage saved sections there).
 */
export function ThemeBuilderScreen({ only }: { only?: 'section' } = {}) {
  const theme = can('edit_theme_options');
  const types = useMemo(() => (only ? [only] : BUILDER_TYPES), [only]);
  const meta = useResource(() => templatesMeta(), []);
  const list = useResource((signal) => templatesApi.list(types, signal), [types.join(',')]);
  // In the Library the hash names the Library's tab, so it never matches here and the list stays on sections.
  const [filter, setFilter] = useHashState<Filter>((only ? [only] : ['all', ...BUILDER_TYPES]) as Filter[], only ?? 'all');
  useEffect(() => {
    if (only) return;
    // Saved sections moved to the Library: old links to Theme Builder → Saved sections land there.
    const moved = () => window.location.hash === '#section' && window.location.replace(screenUrl('uncoder-library', 'sections'));
    moved();
    window.addEventListener('hashchange', moved);
    return () => window.removeEventListener('hashchange', moved);
  }, [only]);
  const [query, setQuery] = useState('');
  const [newType, setNewType] = useState<string | null>(null);
  const [importing, setImporting] = useState(false);
  const [conditionsFor, setConditionsFor] = useState<Template | null>(null);
  const [popupFor, setPopupFor] = useState<Template | null>(null);
  const [previewFor, setPreviewFor] = useState<Template | null>(null);

  const h: ListHandlers = {
    replace: (t) => list.setData((prev) => (prev ?? []).map((x) => (x.id === t.id ? t : x))),
    remove: (id) => list.setData((prev) => (prev ?? []).filter((x) => x.id !== id)),
    add: (t) => list.setData((prev) => [t, ...(prev ?? []).filter((x) => x.id !== t.id)]),
    openConditions: setConditionsFor,
    openPopup: setPopupFor,
    openPreview: setPreviewFor,
  };

  const counts = useMemo(() => {
    const c: Record<string, number> = {};
    for (const t of list.data ?? []) c[t.type] = (c[t.type] ?? 0) + 1;
    return c;
  }, [list.data]);

  const label = (type: string) => NAV_LABELS[type] ?? meta.data?.types[type] ?? cfg.templateTypes[type] ?? type;
  const singular = (type: string) => (meta.data?.types[type] ?? cfg.templateTypes[type] ?? type).toLowerCase();
  const title = filter === 'all' ? 'All templates' : label(filter);
  useSubCrumb(only ? undefined : filter === 'all' ? null : title);

  const items = (list.data ?? []).filter((t) => (filter === 'all' || t.type === filter) && matches(t, query));
  const mode: ListMode = filter === 'popup' ? 'popup' : filter === 'section' ? 'section' : 'theme';
  const live = (list.data ?? []).filter((t) => t.active && (t.type === 'header' || t.type === 'footer'));
  const createType = filter === 'all' ? 'header' : filter;
  const createLabel = filter === 'all' ? 'New template' : filter === 'popup' ? 'New popup' : filter === 'section' ? 'New section' : `New ${singular(filter)}`;
  const canCreate = only ? theme || can('edit_pages') : theme;

  const groups: Array<NavGroup<Filter>> = [
    ...(theme ? [{ items: [{ id: 'all' as Filter, label: 'All templates', icon: 'layout-template', count: list.data ? list.data.length : null }] }] : []),
    ...THEME_GROUPS.map((g) => ({
      label: g.types.length > 1 ? g.label : undefined,
      items: g.types
        .filter((t) => types.includes(t))
        .map((t) => ({ id: t as Filter, label: label(t), icon: TYPE_INFO[t]?.icon ?? 'file', count: counts[t] ?? null })),
    })),
  ];

  const copy = EMPTY_COPY[filter] ?? { title: `No ${label(filter).toLowerCase()} yet`, body: `${TYPE_INFO[filter]?.description ?? ''}.` };
  const empty = (
    <EmptyState
      icon={filter === 'all' ? 'layout-template' : (TYPE_INFO[filter]?.icon ?? 'file')}
      title={copy.title}
      action={
        canCreate ? (
          <Button variant="primary" icon="plus" onClick={() => setNewType(createType)}>
            {createLabel}
          </Button>
        ) : undefined
      }
    >
      {copy.body}
    </EmptyState>
  );

  const search = <SearchInput value={query} onChange={setQuery} placeholder={`Search ${filter === 'all' ? 'templates' : label(filter).toLowerCase()}…`} width={220} />;
  const body = (
    <>
      <Card
        flush
        title={only ? undefined : title}
        description={only ? undefined : filter === 'all' ? 'Every template, most recently edited first.' : TYPE_INFO[filter]?.description}
        actions={only ? undefined : search}
      >
        {list.error && !list.data ? (
          <ErrorState error={list.error} onRetry={list.reload} />
        ) : !list.data ? (
          <SkeletonRows rows={4} cols={4} />
        ) : !items.length ? (
          query ? (
            <EmptyState icon="search" title={`No results for “${query}”`}>
              Try another name or clear the search.
            </EmptyState>
          ) : (
            empty
          )
        ) : (
          <TemplateTable items={items} mode={mode} showType={filter === 'all'} h={h} />
        )}
      </Card>
      {can('manage_options') && (
        <p className="uncoder-ui-aihint">
          <Icon name="sparkles" size={14} />
          <span>
            Prefer to describe it? Ask your AI client to “design a {filter === 'all' ? 'header' : singular(filter)} for this site”: it creates the template{filter === 'section' ? '' : ' and where it appears'} for you.{' '}
            <a href={`${cfg.urls.admin}admin.php?page=uncoder-ai`}>Connect AI</a>
          </span>
        </p>
      )}
    </>
  );

  return (
    <>
      <PageHeader
        title={only ? 'Saved sections' : 'Theme Builder'}
        description={
          only
            ? 'Sections you saved to reuse. Insert them from Insert → Sections in the builder, or anywhere with their shortcode. Edit one and every page that shows it updates.'
            : 'Headers, footers, page layouts, popups and reusable parts. Design them in the builder, then choose where they appear.'
        }
        actions={
          <>
            {only && search}
            {theme && !only && (
              <Button icon="upload" onClick={() => setImporting(true)}>
                Import
              </Button>
            )}
            {canCreate && (
              <Button variant="primary" icon="plus" onClick={() => setNewType(createType)}>
                {createLabel}
              </Button>
            )}
          </>
        }
      />
      {list.data && live.length === 0 && filter === 'all' && list.data.length > 0 && (
        <Callout tone="info" icon="panel-top">
          No header or footer is live yet, so your theme’s own are used. Publish one with display conditions to replace them.
        </Callout>
      )}
      {only ? body : <Workspace nav={<SectionNav<Filter> label="Template types" groups={groups} value={filter} onChange={setFilter} />}>{body}</Workspace>}
      <ConditionsDialog template={conditionsFor} meta={meta.data ?? null} onClose={() => setConditionsFor(null)} onSaved={h.replace} />
      <PopupDrawer template={popupFor} onClose={() => setPopupFor(null)} onSaved={h.replace} />
      <PreviewDialog template={previewFor} onClose={() => setPreviewFor(null)} />
      <NewTemplateDialog open={newType !== null} onClose={() => setNewType(null)} meta={meta.data ?? null} types={types} initialType={newType ?? undefined} />
      <ImportDialog open={importing} onClose={() => setImporting(false)} onImported={() => list.reload()} />
    </>
  );
}
