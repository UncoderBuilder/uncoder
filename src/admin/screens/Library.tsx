import { useMemo, useState } from 'react';
import { Button } from '@editor/ui/primitives';
import { Icon } from '@editor/ui/Icon';
import { api } from '../lib/api';
import { can, cfg } from '../lib/config';
import { useHashState, useResource } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { Badge, Callout, Embedded, EmptyState, ErrorState, PageHeader, SearchInput, TabPanel, Tabs, useSubCrumb } from '../ui/kit';
import { StartersScreen } from './Starters';
import { ThemeBuilderScreen } from './Templates';
import { CloudLibraryScreen } from './CloudLibrary';
import { SiteKitCard } from './SiteKit';
import { ElementorImportCard } from './ElementorImport';

type Tab = 'starters' | 'sections' | 'premium' | 'cloud' | 'import';

const LABEL: Record<Tab, string> = {
  starters: 'Starter sites',
  sections: 'Saved sections',
  premium: 'Premium sections',
  cloud: 'Cloud library',
  import: 'Import & export',
};

/**
 * Library: everything you bring into the site in one place. Starter sites, your saved sections, premium sections,
 * the agency cloud library, and import & export (site kits, Elementor). Each tab shows only for who may use it.
 */
export function LibraryScreen() {
  const tabs = useMemo(() => {
    const list: Tab[] = [];
    if (cfg.library && can('manage_options')) list.push('starters');
    list.push('sections');
    if (cfg.premium) list.push('premium');
    if (cfg.cloud) list.push('cloud');
    if (can('manage_options')) list.push('import');
    return list;
  }, []);
  const [tab, setTab] = useHashState<Tab>(tabs, tabs[0]);
  useSubCrumb(tab === tabs[0] ? null : LABEL[tab]);

  return (
    <>
      <PageHeader title="Library" description="Everything you bring into the site: starter sites, sections and imports." />
      <Tabs<Tab>
        idBase="uncoder-ui-library"
        label="Library sections"
        value={tab}
        onChange={setTab}
        tabs={tabs.map((t) => ({
          id: t,
          label: LABEL[t],
          icon: { starters: 'layout-dashboard', sections: 'layout-template', premium: 'gem', cloud: 'cloud', import: 'arrow-left-right' }[t],
        }))}
      />
      <TabPanel idBase="uncoder-ui-library" active={tab}>
        <Embedded>
          {tab === 'starters' && <StartersScreen />}
          {tab === 'sections' && <ThemeBuilderScreen only="section" />}
          {tab === 'premium' && <PremiumLibrary />}
          {tab === 'cloud' && <CloudLibraryScreen />}
          {tab === 'import' && (
            <div className="uncoder-ui-stack">
              <SiteKitCard />
              <ElementorImportCard />
            </div>
          )}
        </Embedded>
      </TabPanel>
    </>
  );
}

interface PremiumSection {
  id: string;
  title: string;
  category: string;
  starter: string;
  from: string;
  thumb: string;
  w: number;
  h: number;
  demo: string;
  tier: 'free' | 'pro';
  locked: boolean;
}
interface Catalog {
  sections: PremiumSection[];
  categories: Array<{ id: string; label: string }>;
  allowed: boolean;
  pricing: string;
  licence: string;
}

/**
 * Premium sections, browsed by category. "Save to my sections" copies one into the site's saved sections (with its
 * text styles, fonts and images), so it shows in the builder under Insert → Sections → Saved sections.
 */
function PremiumLibrary() {
  const catalog = useResource((signal) => api<Catalog>('sections', { signal }), []);
  const [cat, setCat] = useState('');
  const [q, setQ] = useState('');
  const [busy, setBusy] = useState<string | null>(null);
  const data = catalog.data;
  const first = data?.categories[0]?.id ?? '';
  const active = cat || first;
  const shown = useMemo(() => {
    if (!data) return [];
    const words = q.toLowerCase().split(/\s+/).filter(Boolean);
    return data.sections.filter((s) => (words.length ? words.every((w) => `${s.title} ${s.from} ${s.category}`.toLowerCase().includes(w)) : s.category === active));
  }, [data, q, active]);

  const save = async (s: PremiumSection) => {
    setBusy(s.id);
    try {
      const res = await api<{ id: number; title: string; editUrl: string }>(`sections/${encodeURIComponent(s.id)}/save`, { body: {} });
      toast(`Saved “${res.title}” to your sections. Insert it from Insert → Sections in the builder.`, 'success', { label: 'Open', run: () => (window.location.href = res.editUrl) }, 8000);
    } catch (e) {
      toastError(e);
    } finally {
      setBusy(null);
    }
  };

  if (catalog.error && !data) return <ErrorState error={catalog.error} onRetry={catalog.reload} />;
  return (
    <>
      <PageHeader
        title="Premium sections"
        description="Ready-made sections from the starter sites. Save one to your sections, then insert it on any page."
        actions={<SearchInput value={q} onChange={setQ} placeholder="Search premium sections" width={240} />}
      />
      {!data ? (
        <div className="uncoder-ui-starters">
          {Array.from({ length: 6 }, (_, i) => (
            <div key={i} className="uncoder-ui-starter is-loading" aria-hidden />
          ))}
        </div>
      ) : (
        <>
          {!data.allowed && (
            <Callout
              tone="info"
              icon="gem"
              actions={
                <>
                  <a className="uncoder-ui-btn uncoder-ui-btn--ghost uncoder-ui-btn--sm" href={data.licence}>
                    Add a licence
                  </a>
                  <a className="uncoder-ui-btn uncoder-ui-btn--secondary uncoder-ui-btn--sm" href={data.pricing} target="_blank" rel="noopener noreferrer">
                    See the plans
                  </a>
                </>
              }
            >
              Premium sections come with Pro and Agency.{data.sections.some((s) => !s.locked) ? ' The free ones can be saved right away.' : ''}
            </Callout>
          )}
          {!q && (
            <div className="uncoder-ui-starters__bar">
              <div className="uncoder-ui-chipset uncoder-ui-chipset--wrap" role="group" aria-label="Category">
                {data.categories.map((c) => (
                  <button key={c.id} type="button" className={`uncoder-ui-togglechip${c.id === active ? ' is-on' : ''}`} aria-pressed={c.id === active} onClick={() => setCat(c.id)}>
                    {c.label}
                  </button>
                ))}
              </div>
            </div>
          )}
          {!shown.length ? (
            <EmptyState icon="search-x" title={q ? `No results for “${q}”` : 'Nothing here yet'}>
              {q ? 'Try another word, or clear the search.' : 'Pick another category.'}
            </EmptyState>
          ) : (
            <ul className="uncoder-ui-starters">
              {shown.map((s) => (
                <li key={s.id} className={`uncoder-ui-starter${s.locked ? ' is-locked' : ''}`}>
                  <a className="uncoder-ui-starter__thumb" href={s.demo || undefined} target="_blank" rel="noopener noreferrer" aria-label={`Preview ${s.title} (opens the demo in a new tab)`}>
                    {s.thumb ? <img src={s.thumb} alt="" loading="lazy" width={s.w || 720} height={s.h || 450} /> : <Icon name="layout-template" size={28} />}
                    {s.tier !== 'free' && (
                      <span className="uncoder-ui-starter__badge">
                        <Badge tone="info">
                          {s.locked && <Icon name="lock" size={12} />} Pro
                        </Badge>
                      </span>
                    )}
                  </a>
                  <div className="uncoder-ui-starter__body">
                    <h3>{s.title}</h3>
                    <p className="uncoder-ui-muted">From {s.from}</p>
                    <div className="uncoder-ui-starter__actions">
                      {s.locked ? (
                        <a className="uncoder-ui-btn uncoder-ui-btn--sm" href={data.pricing} target="_blank" rel="noopener noreferrer">
                          <Icon name="lock" size={14} /> Unlock with Pro
                        </a>
                      ) : (
                        <Button size="sm" variant="primary" icon="bookmark-plus" loading={busy === s.id} disabled={!!busy && busy !== s.id} onClick={() => save(s)}>
                          Save to my sections
                        </Button>
                      )}
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </>
      )}
    </>
  );
}
