import type { Condition, PopupSettings, TemplatesMeta } from '../lib/api';

export const TYPE_INFO: Record<string, { icon: string; description: string }> = {
  header: { icon: 'panel-top', description: 'Logo, menu and calls to action at the top of the site' },
  footer: { icon: 'panel-bottom', description: 'Links, contact details and legal text at the bottom' },
  'single-post': { icon: 'file-text', description: 'Layout used by blog posts' },
  'single-page': { icon: 'file', description: 'Layout used by static pages' },
  single: { icon: 'files', description: 'Layout for a custom post type' },
  archive: { icon: 'archive', description: 'Category, tag, date and post type archives' },
  'search-results': { icon: 'search', description: 'Results page for site searches' },
  'error-404': { icon: 'file-x', description: 'Shown when a page is not found' },
  'loop-item': { icon: 'repeat', description: 'Card repeated in post grids and carousels' },
  'mega-menu': { icon: 'menu', description: 'Rich dropdown panel for a menu item' },
  section: { icon: 'layers', description: 'Reusable block for pages, shortcodes and widgets' },
  popup: { icon: 'app-window', description: 'Modal, slide-in or bar opened by triggers' },
};

/**
 * Theme Builder sections. `theme: true` types need edit_theme_options; saved sections are open to
 * anyone who can edit posts (authors reuse them in their pages).
 */
export const THEME_GROUPS: Array<{ label: string; types: string[] }> = [
  { label: 'Site parts', types: ['header', 'footer'] },
  { label: 'Page layouts', types: ['single-post', 'single-page', 'single', 'archive', 'search-results', 'error-404'] },
  { label: 'Popups', types: ['popup'] },
  { label: 'Reusable', types: ['section', 'loop-item', 'mega-menu'] },
];

export const THEME_TYPES = THEME_GROUPS.flatMap((g) => g.types);

/** Types an author without theme rights can still manage. */
export const OPEN_TYPES = ['section'];

/** Nav labels where the plain type name reads oddly in a list. */
export const NAV_LABELS: Record<string, string> = { popup: 'Popups', section: 'Saved sections', 'loop-item': 'Loop items', 'mega-menu': 'Mega menus' };

/** Rules grouped for the rule <select>. */
export const RULE_GROUPS: Array<{ label: string; rules: string[] }> = [
  { label: 'General', rules: ['general'] },
  { label: 'Singular', rules: ['singular', 'front_page', 'in_term', 'child_of', 'by_author'] },
  { label: 'Archives', rules: ['archive', 'posts_page', 'author', 'date', 'search'] },
  { label: 'Other', rules: ['not_found'] },
];

export const RULE_LABELS: Record<string, string> = {
  general: 'Entire site',
  singular: 'Posts, pages & custom types',
  front_page: 'Front page',
  in_term: 'Posts in a category or term',
  child_of: 'Child pages of',
  by_author: 'Posts by author',
  archive: 'Archives',
  posts_page: 'Blog (posts page)',
  author: 'Author archives',
  date: 'Date archives',
  search: 'Search results',
  not_found: '404 page',
};

/** Client-side summary for conditions that have not been saved yet (defaults in the create dialog). */
export function describeConditions(conds: Condition[], meta: TemplatesMeta | null): string {
  if (!conds.length) return 'Not displayed anywhere yet';
  const name = (pt?: string) => meta?.postTypes.find((p) => p.name === pt)?.label ?? pt ?? '';
  const parts = conds.map((c) => {
    let label = RULE_LABELS[c.rule] ?? c.rule;
    if (c.rule === 'singular' && c.post_type) label = `All ${name(c.post_type).toLowerCase()}`;
    if (c.rule === 'archive') label = c.post_type ? `${name(c.post_type)} archive` : 'All archives';
    return (c.type === 'exclude' ? 'except ' : '') + label;
  });
  return parts.join(' · ');
}

export function triggerSummary(p: PopupSettings | undefined): string {
  if (!p) return '—';
  const t = p.triggers;
  const out: string[] = [];
  if (t.load?.enabled) out.push(t.load.delay ? `On load, ${t.load.delay}s` : 'On load');
  if (t.scroll?.enabled) out.push(`${t.scroll.percent}% scroll`);
  if (t.scroll_to?.enabled) out.push('Scroll to element');
  if (t.click?.enabled) out.push('On click');
  if (t.exit_intent?.enabled) out.push('Exit intent');
  if (t.inactivity?.enabled) out.push(`Idle ${t.inactivity.seconds}s`);
  if (t.page_views?.enabled) out.push(`After ${t.page_views.count} views`);
  return out.length ? out.join(' · ') : 'Link only';
}

export const LAYOUT_LABELS: Record<string, string> = { modal: 'Modal', slide_in: 'Slide-in', bar: 'Bar', fullscreen: 'Fullscreen' };
