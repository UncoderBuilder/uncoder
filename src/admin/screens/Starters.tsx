// Library → Starter sites (Site\Library): complete sites from uncoderbuilder.com to start from. Free starters import
// without a licence; Pro starters with a Pro or Agency licence. Import downloads the kit and opens the usual Site Kit
// import (choose what comes in, what happens to existing items) in a dialog.
import { useEffect, useMemo, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button } from '@editor/ui/primitives';
import { api } from '../lib/api';
import { toastError } from '../lib/toast';
import { Badge, EmptyState, ErrorState, PageHeader, SearchInput } from '../ui/kit';
import { Dialog } from '../ui/Dialog';
import { KitImport, KitReport, type Preview, type Report } from './SiteKit';
import { RewriteDialog } from './Rewrite';

interface Starter {
  slug: string;
  title: string;
  category: string;
  summary: string;
  description: string;
  tags: string[];
  pages: number;
  demo: string;
  thumb: string;
  tier: 'free' | 'pro';
  version: string;
  size: number;
  added: string;
  locked: boolean;
}
interface Catalog {
  starters: Starter[];
  updated: string | null;
  plan: 'free' | 'pro' | 'agency';
  pricing: string;
  licence: string;
}

type Tier = 'all' | 'free' | 'pro';

export function StartersScreen() {
  const [catalog, setCatalog] = useState<Catalog | null>(null);
  const [error, setError] = useState<Error | null>(null);
  const [query, setQuery] = useState('');
  const [category, setCategory] = useState('all');
  const [tier, setTier] = useState<Tier>('all');
  const [staging, setStaging] = useState<string | null>(null);
  const [importing, setImporting] = useState<{ starter: Starter; preview: Preview } | null>(null);
  // The import dialog stays put while the site comes in (no Close, Escape or click outside).
  const [importBusy, setImportBusy] = useState(false);
  const [report, setReport] = useState<{ starter: Starter; report: Report } | null>(null);
  // "Make it yours": the AI rewrite of the imported starter (also opened by #rewrite).
  const [rewrite, setRewrite] = useState(() => window.location.hash === '#rewrite');

  const load = (refresh = false) => {
    setError(null);
    api<Catalog>(refresh ? 'library?refresh=1' : 'library').then(setCatalog).catch(setError);
  };
  useEffect(() => load(), []);

  const categories = useMemo(() => [...new Set((catalog?.starters ?? []).map((s) => s.category))].sort(), [catalog]);
  const shown = useMemo(() => {
    const q = query.trim().toLowerCase();
    return (catalog?.starters ?? []).filter(
      (s) =>
        (category === 'all' || s.category === category) &&
        (tier === 'all' || s.tier === tier) &&
        (!q || [s.title, s.category, s.description, ...s.tags].join(' ').toLowerCase().includes(q)),
    );
  }, [catalog, query, category, tier]);

  const start = async (s: Starter) => {
    setStaging(s.slug);
    try {
      const preview = await api<Preview>('library/stage', { body: { slug: s.slug } });
      setImporting({ starter: s, preview });
    } catch (e) {
      toastError(e);
    } finally {
      setStaging(null);
    }
  };

  // No counts: the library keeps growing.
  const planNote = !catalog
    ? null
    : catalog.plan === 'free'
      ? { tone: 'neutral' as const, text: 'The Free starters are yours to use. Pro and Agency unlock all of them, and new ones each month.' }
      : { tone: 'success' as const, text: `Your ${catalog.plan === 'agency' ? 'Agency' : 'Pro'} licence unlocks every starter, and new ones each month.` };

  return (
    <>
      <PageHeader
        title="Starter sites"
        description="Complete sites to start from: pages, header and footer, the Design System and every image. Import one, then make it yours."
        actions={
          catalog && (
            <>
              <SearchInput value={query} onChange={setQuery} placeholder="Search starters…" width={220} />
              <Button icon="sparkles" onClick={() => setRewrite(true)}>
                Make it yours with AI
              </Button>
            </>
          )
        }
      />
      {error ? (
        <ErrorState error={error} onRetry={() => load(true)} />
      ) : !catalog ? (
        <div className="uncoder-ui-starters">
          {Array.from({ length: 6 }, (_, i) => (
            <div key={i} className="uncoder-ui-starter is-loading" aria-hidden />
          ))}
        </div>
      ) : (
        <>
          <div className="uncoder-ui-starters__bar">
            <div className="uncoder-ui-chipset uncoder-ui-chipset--wrap" role="group" aria-label="Category">
              {['all', ...categories].map((c) => (
                <button key={c} type="button" className={`uncoder-ui-togglechip${category === c ? ' is-on' : ''}`} aria-pressed={category === c} onClick={() => setCategory(c)}>
                  {c === 'all' ? 'All' : c}
                </button>
              ))}
            </div>
            <div className="uncoder-ui-chipset" role="group" aria-label="Plan">
              {(['all', 'free', 'pro'] as const).map((t) => (
                <button key={t} type="button" className={`uncoder-ui-togglechip${tier === t ? ' is-on' : ''}`} aria-pressed={tier === t} onClick={() => setTier(t)}>
                  {t === 'all' ? 'Free & Pro' : t === 'free' ? 'Free' : 'Pro'}
                </button>
              ))}
            </div>
          </div>
          {planNote && (
            <p className="uncoder-ui-starters__note">
              <Badge tone={planNote.tone}>{catalog.plan === 'free' ? 'Free' : catalog.plan === 'agency' ? 'Agency' : 'Pro'}</Badge> {planNote.text}{' '}
              {catalog.plan === 'free' && (
                <>
                  <a href={catalog.pricing} target="_blank" rel="noopener noreferrer">
                    See the plans
                  </a>{' '}
                  · <a href={catalog.licence}>Add a licence</a>
                </>
              )}
            </p>
          )}
          {shown.length === 0 ? (
            <EmptyState icon="search-x" title="No starter matches">
              Try another word or category.
            </EmptyState>
          ) : (
            <ul className="uncoder-ui-starters">
              {shown.map((s) => (
                <li key={s.slug} className={`uncoder-ui-starter${s.locked ? ' is-locked' : ''}`}>
                  <a className="uncoder-ui-starter__thumb" href={s.demo} target="_blank" rel="noopener noreferrer" aria-label={`Preview ${s.title} (opens the demo in a new tab)`}>
                    {s.thumb ? <img src={s.thumb} alt="" loading="lazy" width={720} height={450} /> : <Icon name="layout-template" size={28} />}
                    <span className="uncoder-ui-starter__badge">
                      {s.tier === 'free' ? (
                        <Badge tone="success">Free</Badge>
                      ) : (
                        <Badge tone="info">
                          {s.locked && <Icon name="lock" size={12} />} Pro
                        </Badge>
                      )}
                    </span>
                  </a>
                  <div className="uncoder-ui-starter__body">
                    <h3>{s.title}</h3>
                    <p className="uncoder-ui-muted">
                      {s.category} · {s.summary}
                    </p>
                    <div className="uncoder-ui-starter__actions">
                      {s.locked ? (
                        <a className="uncoder-ui-btn uncoder-ui-btn--sm" href={catalog.plan === 'free' ? catalog.licence : catalog.pricing}>
                          <Icon name="lock" size={14} /> Unlock with Pro
                        </a>
                      ) : (
                        <Button size="sm" variant="primary" icon="download" loading={staging === s.slug} disabled={!!staging && staging !== s.slug} onClick={() => start(s)}>
                          Import
                        </Button>
                      )}
                      <a className="uncoder-ui-btn uncoder-ui-btn--sm uncoder-ui-btn--ghost" href={s.demo} target="_blank" rel="noopener noreferrer">
                        <Icon name="external-link" size={14} /> Preview
                      </a>
                    </div>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </>
      )}
      <Dialog
        open={!!importing}
        onClose={() => !importBusy && setImporting(null)}
        locked={importBusy}
        title={importing ? `Import ${importing.starter.title}` : ''}
        description="Choose what comes in. Pages and templates you already have are kept unless you choose otherwise."
        width={680}
      >
        {importing && (
          <KitImport
            preview={importing.preview}
            onBusy={setImportBusy}
            from={
              <>
                Starter site <strong>{importing.starter.title}</strong> · {importing.starter.summary}
              </>
            }
            onCancel={() => setImporting(null)}
            onDone={(r) => {
              setReport({ starter: importing.starter, report: r });
              setImporting(null);
            }}
          />
        )}
      </Dialog>
      <Dialog open={!!report} onClose={() => setReport(null)} title={report ? `${report.starter.title} is in` : ''} width={680}>
        {report && (
          <KitReport
            report={report.report}
            again="Done"
            onAgain={() => setReport(null)}
            extra={
              <Button
                variant="primary"
                icon="sparkles"
                onClick={() => {
                  setReport(null);
                  setRewrite(true);
                }}
              >
                Make it yours with AI
              </Button>
            }
          />
        )}
      </Dialog>
      <RewriteDialog
        open={rewrite}
        onClose={() => {
          setRewrite(false);
          if (window.location.hash === '#rewrite') history.replaceState(null, '', window.location.pathname + window.location.search);
        }}
      />
    </>
  );
}
