// The bar on top of every Uncoder admin screen: brand and version on the left, breadcrumb, then help and
// product updates on the right (What's new, Changelog, Docs, Help) and View site. Design: docs/design/admin-top-bar.md.
// Changelog and announcements ship with the plugin (assets/data/updates.json): no requests to other servers.
import { useRef, useState, type ReactNode, type RefObject } from 'react';
import { AppIcon } from '@editor/ui/Brand';
import { Icon } from '@editor/ui/Icon';
import { Menu, Popover, usePopover, type MenuItem } from '@editor/ui/Popover';
import { BRAND, DOCS_URL, NAME, SUPPORT_URL } from '@shared/brand';
import UPDATES from '../../../assets/data/updates.json';
import { api } from '../lib/api';
import { can, cfg, screenUrl } from '../lib/config';
import { useCrumb } from './kit';
import { OPEN_FLAG } from '../screens/SupportTools';

/** Opens Settings → Tools → System info: flags the card to open, then goes there (or just switches tab). */
function openSystemInfo() {
  try {
    sessionStorage.setItem(OPEN_FLAG, 'system-info');
  } catch {
    /* storage blocked: the tab still opens */
  }
  if (new URLSearchParams(window.location.search).get('page') === 'uncoder-settings') {
    window.location.hash = 'tools';
    window.dispatchEvent(new Event(OPEN_FLAG));
  } else {
    window.location.href = screenUrl('uncoder-settings', 'tools');
  }
}

interface Release {
  version: string;
  date: string;
  title: string;
  groups: { label: string; items: string[] }[];
}
interface NewsItem {
  id: string;
  date: string;
  tag: 'release' | 'tip' | 'guide';
  title: string;
  body: string;
  link?: { label: string; url?: string; screen?: string; docs?: string };
}

const CHANGELOG = UPDATES.changelog as Release[];
const NEWS = UPDATES.news as NewsItem[];
const TAG_LABEL: Record<NewsItem['tag'], string> = { release: 'Release', tip: 'Tip', guide: 'Guide' };

/** Docs page for each screen, and for its sections (the URL hash). */
const DOCS: Record<string, [string, Record<string, string>?]> = {
  uncoder: ['getting-started/interface-tour/'],
  'uncoder-templates': [
    'theme-builder/overview/',
    {
      header: 'theme-builder/headers-footers/',
      footer: 'theme-builder/headers-footers/',
      popup: 'popups/create/',
      section: 'theme-builder/sections/',
      'loop-item': 'theme-builder/loop-items/',
      'mega-menu': 'theme-builder/mega-menus/',
      'single-post': 'theme-builder/singles/',
      'single-page': 'theme-builder/singles/',
      single: 'theme-builder/singles/',
      archive: 'theme-builder/archives-search/',
      'search-results': 'theme-builder/archives-search/',
      'error-404': 'theme-builder/error-404/',
    },
  ],
  'uncoder-design-system': ['design-system/overview/', { fonts: 'design-system/custom-fonts/', icons: 'design-system/custom-icons/' }],
  'uncoder-submissions': ['forms/submissions/'],
  'uncoder-ai': ['ai/overview/', { keys: 'ai/api-keys/', apps: 'ai/connected-apps/', activity: 'ai/activity/', settings: 'ai/server-settings/', writing: 'ai/writing/' }],
  'uncoder-settings': [
    'settings/overview/',
    {
      general: 'settings/general/',
      access: 'settings/roles/',
      privacy: 'settings/cookie-consent/',
      forms: 'settings/forms/',
      seo: 'settings/business-seo/',
      elements: 'settings/elements/',
      transfer: 'import-export/site-kits/',
      code: 'settings/custom-code/',
      tools: 'support/site-tools/',
      advanced: 'settings/advanced/',
    },
  ],
};

function docsUrl(page: string): string {
  const [base, sections] = DOCS[page] ?? [''];
  const hash = decodeURIComponent(window.location.hash.replace(/^#/, ''));
  return DOCS_URL + (sections?.[hash] ?? base);
}

const formatDate = (iso: string) => new Date(iso + 'T12:00:00').toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });

/** Announcements newer than the one this user last opened (all of them the first time). */
function unreadIds(seen: string): string[] {
  const at = seen ? NEWS.findIndex((n) => n.id === seen) : -1;
  return NEWS.slice(0, at < 0 ? NEWS.length : at).map((n) => n.id);
}

const external = { target: '_blank', rel: 'noreferrer' } as const;
const NewTab = () => <span className="screen-reader-text"> (opens in a new tab)</span>;

type Panel = { kind: 'news' | 'log'; from: RefObject<HTMLButtonElement | null> } | null;

export function AppBar() {
  const sub = useCrumb((s) => s.sub);
  const title = cfg.pages[cfg.page] ?? 'Home';
  const home = cfg.page === 'uncoder';
  const [panel, setPanel] = useState<Panel>(null);
  const [seen, setSeen] = useState(cfg.prefs?.newsSeen ?? '');
  // Ids shown as "New" while the panel is open (marking them seen must not hide the markers at once).
  const [fresh, setFresh] = useState<string[]>([]);
  const help = usePopover();
  const versionRef = useRef<HTMLButtonElement>(null);
  const newsRef = useRef<HTMLButtonElement>(null);
  const logRef = useRef<HTMLButtonElement>(null);
  const unread = unreadIds(seen);

  const open = (kind: 'news' | 'log', from: RefObject<HTMLButtonElement | null>) => {
    if (panel?.kind === kind && panel.from === from) return setPanel(null);
    setPanel({ kind, from });
    if (kind === 'news' && unread.length && NEWS[0]) {
      setFresh(unread);
      setSeen(NEWS[0].id);
      api('me/prefs', { body: { newsSeen: NEWS[0].id } }).catch(() => undefined);
    }
  };
  const close = () => {
    panel?.from.current?.focus();
    setPanel(null);
  };

  // White-label "hide links": no docs, support, changelog or announcements from uncoderbuilder.com.
  const links = !BRAND.hideLinks;
  // Settings → Tools with the System info card open and scrolled to (SupportTools.tsx reads the flag).
  const systemInfo: MenuItem = { label: 'System info', icon: 'clipboard-list', onSelect: openSystemInfo };
  const helpItems: MenuItem[] = links
    ? [
        { label: 'Getting started', icon: 'rocket', onSelect: () => window.open(DOCS_URL + 'getting-started/first-page/', '_blank', 'noreferrer') },
        { label: 'Documentation', icon: 'book-open', onSelect: () => window.open(DOCS_URL, '_blank', 'noreferrer') },
        'separator',
        { label: 'Email support', icon: 'life-buoy', onSelect: () => (window.location.href = SUPPORT_URL) },
        ...(can('manage_options') ? (['separator', systemInfo] as MenuItem[]) : []),
      ]
    : can('manage_options')
      ? [systemInfo]
      : [];

  return (
    <header className="uncoder-ui-admin__bar">
      <div className="uncoder-ui-admin__lockup">
        <a className="uncoder-ui-admin__brand" href={screenUrl('uncoder')} aria-label={`${NAME} home`}>
          {BRAND.logo ? (
            // White-label: the agency's logo (its own mark and wordmark).
            <img className="uncoder-ui-admin__logo" src={BRAND.logo} alt="" />
          ) : (
            <>
              <AppIcon size={26} />
              <span className="uncoder-ui-admin__word">{NAME}</span>
            </>
          )}
        </a>
        <span className="uncoder-ui-admin__divider" aria-hidden />
        {links ? (
          <button
            ref={versionRef}
            type="button"
            className="uncoder-ui-admin__ver"
            aria-label={`Version ${cfg.version}: open the changelog`}
            aria-haspopup="dialog"
            aria-expanded={panel?.from === versionRef}
            data-tip="Changelog"
            onClick={() => open('log', versionRef)}
          >
            {cfg.version}
          </button>
        ) : (
          <span className="uncoder-ui-admin__ver">{cfg.version}</span>
        )}
      </div>

      {/* Only inside a section: on the screen itself its title is the page heading right below. */}
      {sub && (
        <nav className="uncoder-ui-admin__crumbs" aria-label="Breadcrumb">
          <a className="uncoder-ui-admin__crumb" href={screenUrl(cfg.page)}>
            {title}
          </a>
          <Icon name="chevron-right" size={13} className="uncoder-ui-admin__sep" />
          <span className="uncoder-ui-admin__crumb is-current" aria-current="page">
            {sub}
          </span>
        </nav>
      )}

      <span className="uncoder-ui-admin__spacer" />

      <div className="uncoder-ui-admin__tools" role="group" aria-label="Help and updates">
        {links && (
          <>
            <BarButton
              buttonRef={newsRef}
              icon="megaphone"
              tip="What's new"
              label={unread.length ? `What's new, ${unread.length} unread` : "What's new"}
              expanded={panel?.from === newsRef}
              dot={unread.length > 0}
              onClick={() => open('news', newsRef)}
            />
            <BarButton buttonRef={logRef} icon="scroll-text" tip="Changelog" label="Changelog" expanded={panel?.from === logRef} className="uncoder-ui-admin__tool--log" onClick={() => open('log', logRef)} />
            <a className="uncoder-ui-admin__tool" href={docsUrl(cfg.page)} {...external} data-tip={home ? 'Documentation' : `Docs: ${sub ?? title}`}>
              <Icon name="book-open" size={17} />
              <span className="screen-reader-text">
                {home ? 'Documentation' : `Documentation for ${sub ?? title}`}
                <NewTab />
              </span>
            </a>
          </>
        )}
        {helpItems.length > 0 && (
          <>
            <BarButton buttonRef={help.anchorRef} icon="circle-help" tip="Help" label="Help" expanded={help.open} haspopup="menu" onClick={help.toggle} />
            <Menu anchor={help.anchorRef} open={help.open} onClose={help.close} items={helpItems} placement="bottom-end" width={210} />
          </>
        )}
      </div>

      <span className="uncoder-ui-admin__divider uncoder-ui-admin__divider--tools" aria-hidden />
      <a className="uncoder-ui-btn uncoder-ui-btn--ghost uncoder-ui-btn--sm uncoder-ui-admin__site" href={cfg.urls.site} {...external} data-tip="View site">
        <Icon name="globe" size={16} className="uncoder-ui-admin__site-icon" />
        <span className="uncoder-ui-admin__site-label">View site</span>
        <Icon name="arrow-up-right" size={14} className="uncoder-ui-admin__site-arrow" />
        <NewTab />
      </a>

      {panel && (
        <Popover anchor={panel.from} open onClose={close} placement={panel.from === versionRef ? 'bottom-start' : 'bottom-end'} width={400} className="uncoder-ui-updates" label={panel.kind === 'news' ? "What's new" : 'Changelog'}>
          {panel.kind === 'news' ? <NewsPanel fresh={fresh} /> : <ChangelogPanel />}
        </Popover>
      )}
    </header>
  );
}

function BarButton({ buttonRef, icon, tip, label, expanded, dot, haspopup = 'dialog', className, onClick }: { buttonRef: RefObject<HTMLButtonElement | null>; icon: string; tip: string; label: string; expanded: boolean; dot?: boolean; haspopup?: 'dialog' | 'menu'; className?: string; onClick: () => void }) {
  return (
    <button ref={buttonRef} type="button" className={'uncoder-ui-admin__tool' + (className ? ' ' + className : '')} aria-label={label} aria-haspopup={haspopup} aria-expanded={expanded} data-tip={tip} onClick={onClick}>
      <Icon name={icon} size={17} />
      {dot && <span className="uncoder-ui-admin__dot" aria-hidden />}
    </button>
  );
}

function PanelHead({ title, link }: { title: string; link?: ReactNode }) {
  return (
    <div className="uncoder-ui-updates__head">
      {/* Focused when the panel opens, so screen readers start at its title. */}
      <h2 className="uncoder-ui-updates__title" tabIndex={-1} data-autofocus>
        {title}
      </h2>
      {link}
    </div>
  );
}

function NewsPanel({ fresh }: { fresh: string[] }) {
  return (
    <>
      <PanelHead title="What's new" />
      <ul className="uncoder-ui-updates__body uncoder-ui-news">
        {NEWS.map((n) => (
          <li key={n.id} className={'uncoder-ui-news__item' + (fresh.includes(n.id) ? ' is-new' : '')}>
            <div className="uncoder-ui-news__meta">
              <span className={`uncoder-ui-news__tag uncoder-ui-news__tag--${n.tag}`}>{TAG_LABEL[n.tag]}</span>
              <time dateTime={n.date}>{formatDate(n.date)}</time>
              {fresh.includes(n.id) && <span className="uncoder-ui-news__new">New</span>}
            </div>
            <h3 className="uncoder-ui-news__title">{n.title}</h3>
            <p className="uncoder-ui-news__body">{n.body}</p>
            {n.link && <NewsLink link={n.link} />}
          </li>
        ))}
      </ul>
    </>
  );
}

function NewsLink({ link }: { link: NonNullable<NewsItem['link']> }) {
  if (link.screen) {
    return (
      <a className="uncoder-ui-news__link" href={screenUrl(link.screen)}>
        {link.label}
        <Icon name="arrow-right" size={13} />
      </a>
    );
  }
  return (
    <a className="uncoder-ui-news__link" href={link.docs ? DOCS_URL + link.docs : link.url} {...external}>
      {link.label}
      <Icon name="arrow-up-right" size={13} />
      <NewTab />
    </a>
  );
}

function ChangelogPanel() {
  return (
    <>
      <PanelHead
        title="Changelog"
        link={
          <a className="uncoder-ui-updates__more" href={DOCS_URL + 'changelog/'} {...external}>
            Full changelog
            <Icon name="arrow-up-right" size={13} />
            <NewTab />
          </a>
        }
      />
      <div className="uncoder-ui-updates__body">
        {CHANGELOG.map((r) => (
          <section key={r.version} className="uncoder-ui-release" aria-label={`Version ${r.version}`}>
            <div className="uncoder-ui-release__head">
              <span className="uncoder-ui-release__ver">{r.version}</span>
              <strong className="uncoder-ui-release__title">{r.title}</strong>
              {r.version === cfg.version && <span className="uncoder-ui-release__current">Installed</span>}
              <time className="uncoder-ui-release__date" dateTime={r.date}>
                {formatDate(r.date)}
              </time>
            </div>
            {r.groups.map((g) => (
              <div key={g.label} className="uncoder-ui-release__group">
                <h3 className="uncoder-ui-release__label">{g.label}</h3>
                <ul className="uncoder-ui-release__list">
                  {g.items.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
              </div>
            ))}
          </section>
        ))}
      </div>
    </>
  );
}
