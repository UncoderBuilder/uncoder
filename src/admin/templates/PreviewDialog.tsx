import { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { Segmented } from '@editor/ui/primitives';
import { cfg } from '../lib/config';
import type { Template } from '../lib/api';
import { Icon } from '@editor/ui/Icon';
import { Dialog } from '../ui/Dialog';
import { PostStatus } from '../ui/kit';

const DEVICES = { desktop: 1280, tablet: 820, mobile: 390 } as const;
type Device = keyof typeof DEVICES;

/** Site parts shown on their own by default, like Elementor; "On the home page" shows them in place. */
const PARTS = ['header', 'footer'];

/** Layouts shown on a post or page: they can be previewed with a chosen one. */
const SAMPLE_SOURCE: Record<string, 'posts' | 'pages'> = { 'single-post': 'posts', single: 'posts', 'single-page': 'pages' };

const HINT: Record<string, string> = {
  header: 'The header on its own, or on the home page in place of the current one.',
  footer: 'The footer on its own, or on the home page in place of the current one.',
  'single-post': 'Shown on a post, in place of the layout that would apply.',
  'single-page': 'Shown on a page, in place of the layout that would apply.',
  single: 'Shown on a post, in place of the layout that would apply.',
  archive: 'Shown on the blog / an archive, in place of the layout that would apply.',
  'search-results': 'Shown on a search results page.',
  'error-404': 'Shown on a page that does not exist.',
  popup: 'Opened on the home page.',
  section: 'The section on its own, inside your theme.',
  'loop-item': 'Three cards with your latest posts.',
  'mega-menu': 'The menu panel on its own.',
};

/**
 * Theme Builder → Preview: the template live on the site (drafts too), at desktop, tablet or phone width,
 * without opening the editor.
 */
export function PreviewDialog({ template, onClose }: { template: Template | null; onClose: () => void }) {
  const [device, setDevice] = useState<Device>('desktop');
  const [sample, setSample] = useState(0);
  const [inPage, setInPage] = useState(false);
  const [samples, setSamples] = useState<Array<{ id: number; title: string }>>([]);
  const [loading, setLoading] = useState(true);
  const [nonce, setNonce] = useState(0);
  const [box, setBox] = useState({ w: 0, h: 0 });
  const stage = useRef<HTMLDivElement>(null);
  const source = template ? SAMPLE_SOURCE[template.type] : undefined;

  useEffect(() => {
    setDevice('desktop');
    setSample(0);
    setInPage(false);
    setSamples([]);
  }, [template?.id]);

  // A few recent posts / pages to preview a single layout with.
  useEffect(() => {
    if (!template || !source) return;
    const ctrl = new AbortController();
    fetch(`${cfg.rest.wp}${source}?per_page=12&status=publish&_fields=id,title&orderby=date&order=desc`, { headers: { 'X-WP-Nonce': cfg.rest.nonce }, signal: ctrl.signal, credentials: 'same-origin' })
      .then((r) => (r.ok ? r.json() : []))
      .then((list: Array<{ id: number; title: { rendered: string } }>) => {
        const tmp = document.createElement('textarea');
        setSamples(
          list.map((p) => {
            tmp.innerHTML = p.title?.rendered ?? '';
            return { id: p.id, title: tmp.value || `#${p.id}` };
          }),
        );
      })
      .catch(() => undefined);
    return () => ctrl.abort();
  }, [template?.id, source]);

  useLayoutEffect(() => {
    const el = stage.current;
    if (!el) return;
    const measure = () => setBox({ w: el.clientWidth, h: el.clientHeight });
    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(el);
    return () => ro.disconnect();
  }, [template?.id]);

  const part = !!template && PARTS.includes(template.type);
  const url = template ? template.previewUrl + (sample ? `&sample=${sample}` : '') + (part && inPage ? '&in_page=1' : '') : '';
  useEffect(() => setLoading(true), [url, nonce]);

  if (!template) return null;
  const width = DEVICES[device];
  const scale = box.w ? Math.min(1, (box.w - 24) / width) : 1;
  const height = box.h ? Math.max(200, (box.h - 24) / scale) : 600;

  return (
    <Dialog
      open
      onClose={onClose}
      width={1360}
      className="uncoder-ui-tplpreview"
      title={
        <span className="uncoder-ui-tplpreview__title">
          {template.title || '(no title)'}
          <span className="uncoder-ui-tplpreview__type">{template.typeLabel}</span>
          {template.languages?.lang && <span className="uncoder-ui-langchip is-own">{template.languages.lang.toUpperCase()}</span>}
          {template.status !== 'publish' && <PostStatus status={template.status} />}
        </span>
      }
      description={HINT[template.type]}
    >
      <div className="uncoder-ui-tplpreview__bar">
        <Segmented
          ariaLabel="Preview width"
          value={device}
          onChange={(v) => setDevice(v as Device)}
          options={[
            { value: 'desktop', label: 'Desktop', icon: 'monitor' },
            { value: 'tablet', label: 'Tablet', icon: 'tablet' },
            { value: 'mobile', label: 'Mobile', icon: 'smartphone' },
          ]}
        />
        {part && (
          <Segmented
            ariaLabel="Show the template"
            value={inPage ? 'page' : 'alone'}
            onChange={(v) => setInPage(v === 'page')}
            options={[
              { value: 'alone', label: 'Template only' },
              { value: 'page', label: 'On the home page' },
            ]}
          />
        )}
        {source && samples.length > 0 && (
          <label className="uncoder-ui-tplpreview__sample">
            <span>Preview with</span>
            <select className="uncoder-ui-select uncoder-ui-select--sm" value={sample} onChange={(e) => setSample(Number(e.currentTarget.value))}>
              <option value={0}>{source === 'pages' ? 'Home page / first page' : 'Latest post'}</option>
              {samples.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.title}
                </option>
              ))}
            </select>
          </label>
        )}
        <span className="uncoder-ui-tplpreview__spacer" />
        <button type="button" className="uncoder-ui-btn uncoder-ui-btn--ghost uncoder-ui-btn--sm" onClick={() => setNonce((n) => n + 1)}>
          <Icon name="rotate-cw" size={13} />
          Reload
        </button>
        <a className="uncoder-ui-btn uncoder-ui-btn--secondary uncoder-ui-btn--sm" href={url} target="_blank" rel="noopener">
          <Icon name="external-link" size={13} />
          New tab
        </a>
        {template.canEdit && (
          <a className="uncoder-ui-btn uncoder-ui-btn--primary uncoder-ui-btn--sm" href={template.editUrl}>
            <Icon name="pencil" size={13} />
            Edit
          </a>
        )}
      </div>
      <div ref={stage} className="uncoder-ui-tplpreview__stage">
        {box.w > 0 && (
          <div className="uncoder-ui-tplpreview__device" style={{ width: width * scale, height: height * scale }}>
            <iframe
              key={`${url}|${nonce}`}
              src={url}
              title={`Preview of ${template.title}`}
              style={{ width, height, transform: `scale(${scale})` }}
              onLoad={(e) => {
                setLoading(false);
                // On the home page a footer sits at the end: show it without scrolling.
                if (template.type === 'footer' && inPage) {
                  try {
                    const win = e.currentTarget.contentWindow;
                    win?.scrollTo(0, win.document.documentElement.scrollHeight);
                  } catch {
                    /* another origin */
                  }
                }
              }}
            />
          </div>
        )}
        {loading && (
          <div className="uncoder-ui-tplpreview__loading" role="status">
            <span className="uncoder-ui-spinner" aria-hidden />
            Loading preview…
          </div>
        )}
      </div>
    </Dialog>
  );
}
