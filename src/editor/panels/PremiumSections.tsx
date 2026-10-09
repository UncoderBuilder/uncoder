// Insert → Sections → Premium sections (Site\Section_Library, Pro and Agency): every section of the starter sites,
// shown as photographs, by category. Inserting brings the section's text styles and fonts into the Design System
// (as "<starter>-<style>"), installs uploaded fonts and copies the images; "Use my heading & body styles" keeps the
// site's own instead of the starter's for the standard ones.
import { useEffect, useMemo, useState } from 'react';
import type { ElementNode, KitFont, KitTypography } from '@shared/types';
import { api } from '../lib/api';
import { fonts } from '../lib/fonts';
import { useKit } from '../store/kit';
import { toast } from '../store/ui';
import { Icon } from '../ui/Icon';

interface Section {
  id: string;
  title: string;
  category: string;
  starter: string;
  from: string;
  tier: 'free' | 'pro';
  thumb: string;
  w: number;
  h: number;
  demo: string;
  locked: boolean;
}
interface Catalog {
  sections: Section[];
  categories: Array<{ id: string; label: string }>;
  allowed: boolean;
  pricing: string;
  licence: string;
}
interface Prepared {
  elements: ElementNode[];
  kit: { typography: KitTypography[]; fonts: KitFont[] };
  fonts: string[];
  fontFaces: string;
  custom: Record<string, { c: string; w: string[]; custom?: boolean }> | null;
  images: number;
  failed: number;
}

const MATCH_KEY = 'uncoder-ui-sections-match';
let catalogCache: Promise<Catalog> | null = null;

function readMatch(): boolean {
  try {
    return localStorage.getItem(MATCH_KEY) === '1';
  } catch {
    return false;
  }
}

export function PremiumSections({ insert }: { insert: (nodes: ElementNode[], label: string) => void }) {
  const [catalog, setCatalog] = useState<Catalog | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [cat, setCat] = useState('hero');
  const [q, setQ] = useState('');
  const [busy, setBusy] = useState<string | null>(null);
  const [match, setMatch] = useState(readMatch);

  useEffect(() => {
    let live = true;
    if (!catalogCache) catalogCache = api<Catalog>('sections');
    catalogCache
      .then((c) => live && setCatalog(c))
      .catch((e: any) => {
        catalogCache = null;
        if (live) setError(e?.message ?? 'Could not load the premium sections.');
      });
    return () => {
      live = false;
    };
  }, []);

  const counts = useMemo(() => {
    const n: Record<string, number> = {};
    for (const s of catalog?.sections ?? []) n[s.category] = (n[s.category] ?? 0) + 1;
    return n;
  }, [catalog]);

  const shown = useMemo(() => {
    const words = q.toLowerCase().split(/\s+/).filter(Boolean);
    return (catalog?.sections ?? []).filter((s) => (words.length ? words.every((w) => `${s.title} ${s.from} ${s.category}`.toLowerCase().includes(w)) : s.category === cat));
  }, [catalog, q, cat]);

  const toggleMatch = (v: boolean) => {
    setMatch(v);
    try {
      localStorage.setItem(MATCH_KEY, v ? '1' : '0');
    } catch {
      /* private window: the choice lasts for this session */
    }
  };

  const put = async (s: Section) => {
    if (s.locked) {
      toast('Premium sections come with Pro and Agency.', 'info', { label: 'See the plans', run: () => window.open(catalog?.pricing, '_blank', 'noreferrer') }, 6000);
      return;
    }
    setBusy(s.id);
    try {
      const res = await api<Prepared>(`sections/${encodeURIComponent(s.id)}${match ? '?match=1' : ''}`);
      if (res.kit.typography.length || res.kit.fonts.length) {
        // Added to the Design System on the server: the editor's copy gets them too (unsaved style edits stay).
        useKit.setState((st) => {
          const k = st.kit;
          const haveT = new Set(k.typography.map((t) => t.id));
          const haveF = new Set(k.fonts.map((f) => f.id));
          return { kit: { ...k, typography: [...k.typography, ...res.kit.typography.filter((t) => !haveT.has(t.id))], fonts: [...k.fonts, ...res.kit.fonts.filter((f) => !haveF.has(f.id))] } };
        });
      }
      if (res.fonts.length) fonts.addUploaded(res.fontFaces, res.custom);
      insert(res.elements, `Insert “${s.title}”`);
      const notes = [
        res.kit.typography.length ? `${res.kit.typography.length} text style${res.kit.typography.length === 1 ? '' : 's'}` : '',
        res.fonts.length ? `font${res.fonts.length === 1 ? '' : 's'} ${res.fonts.join(', ')}` : '',
        res.images ? `${res.images} image${res.images === 1 ? '' : 's'}` : '',
      ].filter(Boolean);
      toast(`Inserted “${s.title}” from ${s.from}${notes.length ? ` with ${notes.join(', ')}` : ''}.`, 'success');
    } catch (e: any) {
      toast(`Could not insert: ${e.message}`, 'error');
    } finally {
      setBusy(null);
    }
  };

  return (
    <>
      <div className="uncoder-ui-kit__label">
        Premium sections {catalog && !catalog.allowed && <span className="uncoder-ui-premium__pro">Pro</span>}
      </div>
      {error && <p className="uncoder-ui-note">{error}</p>}
      {!catalog && !error && <p className="uncoder-ui-note">Loading premium sections…</p>}
      {catalog && (
        <>
          <label className="uncoder-ui-search">
            <Icon name="search" size={14} />
            <input type="search" placeholder={`Search ${catalog.sections.length} sections`} value={q} onChange={(e) => setQ(e.currentTarget.value)} aria-label="Search premium sections" />
          </label>
          {!q && (
            <div className="uncoder-ui-chips uncoder-ui-library__cats" role="group" aria-label="Premium section categories">
              {catalog.categories
                .filter((c) => counts[c.id])
                .map((c) => (
                  <button key={c.id} type="button" className="uncoder-ui-chip" aria-pressed={cat === c.id} onClick={() => setCat(c.id)}>
                    {c.label}
                  </button>
                ))}
            </div>
          )}
          {catalog.allowed && (
            <label className="uncoder-ui-premium__match">
              <input type="checkbox" checked={match} onChange={(e) => toggleMatch(e.currentTarget.checked)} />
              <span>Use my heading & body styles</span>
            </label>
          )}
          {!catalog.allowed && (
            <p className="uncoder-ui-note">
              {catalog.sections.length} sections from the starter sites, ready to drop in.{' '}
              <a href={catalog.pricing} target="_blank" rel="noreferrer">
                Unlock with Pro
              </a>
            </p>
          )}
          <div className="uncoder-ui-premium">
            {shown.map((s) => (
              <button
                key={s.id}
                type="button"
                className={`uncoder-ui-premium__item${s.locked ? ' is-locked' : ''}`}
                aria-label={`${s.locked ? 'Locked: ' : 'Insert '}${s.title} from ${s.from}`}
                aria-busy={busy === s.id}
                disabled={busy !== null && busy !== s.id}
                onClick={() => put(s)}
              >
                <span className="uncoder-ui-premium__thumb">
                  {s.thumb ? <img src={s.thumb} alt="" loading="lazy" width={s.w || 720} height={s.h || 400} /> : <Icon name="layout-template" size={20} />}
                  <span className="uncoder-ui-premium__add" aria-hidden="true">
                    <Icon name={busy === s.id ? 'loader' : s.locked ? 'lock' : 'plus'} size={13} />
                  </span>
                </span>
                <span className="uncoder-ui-premium__meta">
                  <span className="uncoder-ui-premium__title">{s.title}</span>
                  <span className="uncoder-ui-premium__from">{s.from}</span>
                </span>
              </button>
            ))}
            {!shown.length && <p className="uncoder-ui-note">Nothing matches “{q}”.</p>}
          </div>
        </>
      )}
    </>
  );
}
