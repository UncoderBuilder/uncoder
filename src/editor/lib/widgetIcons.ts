// Uncoder widget icons: small drawings of what each widget puts on the page, on a 32px grid.
// Classes (styled in studio.css, so both themes work): s = 1.6px stroke in the text colour,
// b = heavier stroke (titles), f = soft terracotta fill, a = solid accent, as = accent stroke,
// d = dashed stroke, h = "hole" in the surface colour.

const S = (d: string) => `<path class="s" d="${d}"/>`;
const B = (d: string) => `<path class="b" d="${d}"/>`;
const F = (d: string) => `<path class="f" d="${d}"/>`;
const A = (d: string) => `<path class="a" d="${d}"/>`;
const AS = (d: string) => `<path class="as" d="${d}"/>`;
const r = (x: number, y: number, w: number, h: number, rad = 0, cls = 's') => `<rect class="${cls}" x="${x}" y="${y}" width="${w}" height="${h}" rx="${rad}"/>`;
const c = (x: number, y: number, rad: number, cls = 's') => `<circle class="${cls}" cx="${x}" cy="${y}" r="${rad}"/>`;
/** Filled + outlined rounded rectangle. */
const fr = (x: number, y: number, w: number, h: number, rad = 0) => r(x, y, w, h, rad, 'f') + r(x, y, w, h, rad);

const STAR = (cx: number, cy: number, R: number, cls = 'a') => {
  const pts: string[] = [];
  for (let i = 0; i < 10; i++) {
    const rad = i % 2 ? R * 0.45 : R;
    const ang = -Math.PI / 2 + (i * Math.PI) / 5;
    pts.push(`${(cx + rad * Math.cos(ang)).toFixed(2)} ${(cy + rad * Math.sin(ang)).toFixed(2)}`);
  }
  return `<path class="${cls}" d="M${pts.join('L')}Z"/>`;
};

const PAGE = 'M9 4h10l6 6v15a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3z';
const PAGE_FOLD = 'M19 4v4a2 2 0 0 0 2 2h4';

export const WIDGET_ICONS: Record<string, string> = {
  container: r(4, 6, 24, 20, 3, 'd') + fr(9, 11, 14, 10, 2),
  accordion: r(5, 4.5, 22, 6, 2) + fr(5, 13, 22, 6, 2) + r(5, 21.5, 22, 6, 2) + S('M21 7.5h3M21 24.5h3M22.5 23v3') + AS('M21 16h3'),
  alert: fr(4, 8, 24, 16, 3) + S('M14 13.5h9M14 18.5h6') + AS('M9.5 12.5v4') + c(9.5, 19.6, 1.1, 'a'),
  'animated-headline': S('M6 9.5h14') + r(5, 14, 19, 8, 2.5, 'f') + B('M8 18h13') + STAR(26, 9, 3.2),
  'archive-description': fr(5, 5, 22, 7, 2) + S('M13 8.5h6M7 17h18M7 21.5h18M7 26h11'),
  'archive-title': fr(5, 5, 22, 7, 2) + S('M13 8.5h6') + B('M7 18h16') + S('M7 24h11'),
  'author-box': r(4, 7, 24, 18, 3) + c(11, 16, 3.6, 'f') + c(11, 16, 3.6) + S('M17.5 12.5h7M17.5 16.5h7M17.5 20.5h4'),
  blockquote: AS('M7.5 8v16') + S('M12 10h13M12 15h13M12 20h8'),
  breadcrumbs: fr(3.5, 13, 6, 6, 1.6) + S('M12 13.5l2.5 2.5-2.5 2.5') + fr(16.5, 13, 6, 6, 1.6) + AS('M25 13.5l2.5 2.5-2.5 2.5'),
  button: fr(4, 10, 24, 12, 6) + S('M10 16h8') + A('M20.5 17.8l6.3 2.4-2.7 1.1-1.1 2.8z'),
  'call-to-action': r(4, 5.5, 24, 21, 3) + B('M8.5 11h14') + S('M8.5 15.5h10') + r(8.5, 19.5, 10, 3.6, 1.8, 'a'),
  carousel: r(4, 10, 4, 12, 1.6) + r(24, 10, 4, 12, 1.6) + fr(10, 7, 12, 17, 2) + c(13, 27.5, 1, 'a') + c(16, 27.5, 1, 's') + c(19, 27.5, 1, 's'),
  'code-highlight': r(4, 6, 24, 20, 3) + S('M4 11h24') + c(7.5, 8.5, 0.7, 'a') + S('M12 15.5l-2.5 2.5 2.5 2.5M20 15.5l2.5 2.5-2.5 2.5') + AS('M17.2 14.5l-2.4 7'),
  countdown: c(16, 18, 9, 'f') + c(16, 18, 9) + S('M13.5 5h5M16 5v4M24.5 9.5l1.5-1.5') + AS('M16 18v-5') + S('M16 18l3.5 2'),
  counter: S('M6 12l3-2v12M12.5 12.3a2.6 2.6 0 1 1 4.2 2.1L12.5 22h5.2') + AS('M24.5 22V11M21.5 14l3-3 3 3'),
  divider: S('M4 16h8.5M19.5 16H28') + A('M16 12.5l3.5 3.5-3.5 3.5-3.5-3.5z'),
  'featured-image': S(PAGE + PAGE_FOLD) + r(9, 12, 13, 8, 1.5, 'f') + S('M9.5 19l3.5-3.5 2.5 2.5 2-2 3.5 3') + S('M9 24h11'),
  'flip-box': r(12, 4.5, 14, 18, 2, 's') + fr(5, 9, 14, 18, 2) + AS('M22 27a5 5 0 0 0 5-5M25 20.5l2 1.6 1.6-2'),
  form: r(5, 5.5, 22, 5, 1.6) + r(5, 13.5, 22, 5, 1.6) + r(5, 21.5, 11, 5, 2.5, 'a'),
  'google-maps': F('M4 9l7-3 10 3 7-3v17l-7 3-10-3-7 3z') + S('M4 9l7-3 10 3 7-3v17l-7 3-10-3-7 3zM11 6v17M21 9v17') + A('M21 10.5a4 4 0 0 0-4 4c0 3 4 7 4 7s4-4 4-7a4 4 0 0 0-4-4z') + c(21, 14.5, 1.4, 'h'),
  heading: B('M8 7v17M20 7v17M8 15.5h12') + AS('M23.5 24h4.5'),
  hotspot: fr(4, 6, 24, 20, 3) + c(12, 13.5, 2.2, 'a') + c(21, 19.5, 2.2, 'a') + c(21, 19.5, 4.4, 'as'),
  html: S('M11 10l-6 6 6 6M21 10l6 6-6 6') + AS('M18 8l-4 16'),
  icon: r(4, 4, 24, 24, 7, 'f') + STAR(16, 16.5, 8),
  'icon-box': r(5, 5, 22, 22, 3) + r(9, 9, 7, 7, 2, 'a') + S('M9 20h14M9 23.5h9'),
  'icon-list': c(7, 9, 2, 'a') + S('M12 9h14') + c(7, 16, 2, 'a') + S('M12 16h12') + c(7, 23, 2, 'a') + S('M12 23h10'),
  image: fr(4, 6, 24, 20, 3) + c(11, 12, 2.2, 'a') + S('M5 23l7-7 5 5 3-3 7 7'),
  'image-box': r(5, 4, 22, 24, 3) + r(8.5, 7.5, 15, 9, 1.6, 'f') + c(12, 10.5, 1.4, 'a') + S('M9 15.5l3.5-3 2.5 2 2.5-2 3.5 3') + S('M8.5 21h15M8.5 24.5h9'),
  'loop-carousel': r(3, 10, 3, 12, 1.4) + r(26, 10, 3, 12, 1.4) + r(8, 6, 16, 18, 2) + r(10, 8, 12, 7, 1, 'f') + S('M10 18.5h9M10 21h6') + c(14, 27.5, 1, 'a') + c(18, 27.5, 1),
  'image-carousel':r(4, 10, 3, 12, 1.4) + r(25, 10, 3, 12, 1.4) + fr(9, 7, 14, 17, 2) + S('M9.5 21l4-4 3 3 2-2 4 4') + c(13, 11.5, 1.3, 'a') + c(14, 27.5, 1, 'a') + c(18, 27.5, 1),
  'image-compare': F('M7 6h9v20H7a3 3 0 0 1-3-3V9a3 3 0 0 1 3-3z') + r(4, 6, 24, 20, 3) + AS('M16 3.5v25') + c(16, 16, 3, 'a') + c(16, 16, 1, 'h'),
  'image-gallery': fr(4, 4, 11, 11, 2) + r(17, 4, 11, 11, 2) + r(4, 17, 11, 11, 2) + fr(17, 17, 11, 11, 2) + c(22.5, 9.5, 1.5, 'a'),
  login: r(6, 4.5, 20, 23, 3) + c(16, 10.5, 2.8, 'a') + r(10, 15.5, 12, 3.4, 1.7) + r(10, 21, 12, 3.4, 1.7, 'a'),
  'logo-grid': r(4, 7, 24, 18, 3) + S('M12 7v18M20 7v18M4 16h24') + c(8, 11.5, 1.8, 'a') + r(14.5, 10, 3, 3, 0.8, 'f') + r(14.5, 10, 3, 3, 0.8) + S('M22.5 13l1.5-3 1.5 3z') + r(6.3, 18.8, 3.4, 3.4, 1.7, 'f') + c(16, 20.5, 1.6, 'f') + c(16, 20.5, 1.6) + r(22.3, 18.8, 3.4, 3.4, 0.8, 'f'),
  'loop-filter': r(4, 5.5, 8, 5, 2.5, 'a') + r(14, 5.5, 8, 5, 2.5) + r(4, 15, 7, 7, 1.6, 'f') + r(12.5, 15, 7, 7, 1.6, 'f') + r(21, 15, 7, 7, 1.6, 'f') + S('M4 26h7M12.5 26h7M21 26h7'),
  'loop-grid': [4, 17].flatMap((x) => [4, 17].map((y) => r(x, y, 11, 11, 2) + r(x + 2, y + 2, 7, 4, 1, x === 4 && y === 4 ? 'a' : 'f') + S(`M${x + 2} ${y + 8.5}h5`))).join(''),
  lottie: c(16, 16, 11, 'f') + S('M6.5 18c2-6 4.8-6 7 0s5 6 7 0') + STAR(24, 8.5, 3.4),
  marquee: fr(4, 9, 24, 8, 4) + S('M8.5 13h5M17.5 13h5') + AS('M6 24h20M9 21l-3 3 3 3M23 21l3 3-3 3'),
  'menu-anchor': c(16, 7.5, 2.5, 'a') + S('M16 10v16M11.5 14h9M7 19a9 9 0 0 0 18 0'),
  'nav-menu': fr(3, 9, 26, 14, 3) + c(8, 16, 2.2, 'a') + S('M13 16h3.5M19 16h3M24.5 16h1.5'),
  'off-canvas': r(4, 6, 24, 20, 3) + F('M18 6h7a3 3 0 0 1 3 3v14a3 3 0 0 1-3 3h-7z') + S('M18 6v20M21 11h4M21 15h4M21 19h3') + AS('M8 16h6M11.5 13.5 14 16l-2.5 2.5'),
  'post-comments': F('M5 8a3 3 0 0 1 3-3h16a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-9l-6 5v-5H8a3 3 0 0 1-3-3z') + S('M5 8a3 3 0 0 1 3-3h16a3 3 0 0 1 3 3v10a3 3 0 0 1-3 3h-9l-6 5v-5H8a3 3 0 0 1-3-3z') + S('M10 11h12') + AS('M10 15h7'),
  'post-content': F(PAGE) + S(PAGE + PAGE_FOLD) + S('M10 14h12M10 18h12M10 22h8'),
  'post-excerpt': S(PAGE + PAGE_FOLD) + S('M10 14h12M10 18h9') + c(11, 23, 1.1, 'a') + c(14.5, 23, 1.1, 'a') + c(18, 23, 1.1, 'a'),
  'post-info': r(4, 5.5, 11, 11, 2) + S('M4 9.5h11M7.5 4v3M11.5 4v3') + S('M18.5 8.5h9M18.5 13h7') + c(8, 23, 2.8, 'a') + S('M13.5 23h13'),
  'post-navigation': fr(3.5, 10.5, 11.5, 11, 3) + S('M11.5 16H7M9 14l-2 2 2 2') + r(17, 10.5, 11.5, 11, 3) + AS('M20.5 16H25M23 14l2 2-2 2'),
  'post-title': S(PAGE + PAGE_FOLD) + B('M10 14h11') + S('M10 19h12M10 23h8'),
  posts: [5, 13, 21].map((y) => r(4, y, 8, 6, 1.6, 'f') + S(`M15 ${y + 1.5}h12M15 ${y + 4.5}h8`)).join('') + r(4, 5, 8, 6, 1.6, 'a'),
  'price-list': [9, 16, 23].map((y) => S(`M5 ${y}h8`) + `<path class="dot" d="M15 ${y}h7"/>` + AS(`M24 ${y}h3`)).join(''),
  'price-table': r(7, 4, 18, 24, 3) + F('M10 4h12a3 3 0 0 1 3 3v4H7V7a3 3 0 0 1 3-3z') + S('M7 11h18') + AS('M11 16h10') + S('M11 20.5h10M11 24h7'),
  'progress-bar': r(4, 9.5, 24, 4.5, 2.25) + r(4, 9.5, 16, 4.5, 2.25, 'a') + r(4, 18.5, 24, 4.5, 2.25) + r(4, 18.5, 10, 4.5, 2.25, 'f') + r(4, 18.5, 10, 4.5, 2.25),
  'reading-progress': r(4, 5, 24, 22, 3) + AS('M7 9h12') + S('M8 14.5h16M8 18.5h16M8 22.5h11'),
  'text-path': c(16, 16, 12, 'f') + c(16, 16, 9, 'd') + AS('M16 11.5v9M12.5 17l3.5 3.5 3.5-3.5'),
  sitemap: fr(11.5, 4, 9, 6.5, 1.6) + S('M16 10.5V14M7 14h18M7 14v4M16 14v4M25 14v4') + r(3.5, 18, 7, 6, 1.6) + r(12.5, 18, 7, 6, 1.6, 'a') + r(21.5, 18, 7, 6, 1.6),
  'link-in-bio': c(16, 6.5, 3.6, 'f') + c(16, 6.5, 3.6) + S('M12 13h8') + r(5.5, 17, 21, 4.4, 2.2, 'a') + r(5.5, 24, 21, 4.4, 2.2),
  soundcloud: c(9.5, 16, 6.2, 'a') + '<path class="h" d="M7.9 13.1v5.8l4.6-2.9z"/>' + S('M19 12v8M22 8v16M25 11v10M28 13.5v5'),
  'facebook-embed': F('M8 4h16a3 3 0 0 1 3 3v5H5V7a3 3 0 0 1 3-3z') + r(5, 4, 22, 24, 3) + S('M5 12h22') + c(11, 12.5, 4.6, 'h') + c(11, 12.5, 3.3, 'a') + S('M17 16.5h6M9 21.5h14M9 25h9'),
  'video-playlist': fr(4, 4, 24, 14, 3) + A('M14 8.3v5.4l4.6-2.7z') + r(4, 21, 5, 3.4, 1.2, 'a') + S('M12.5 22.7h15.5') + r(4, 26, 5, 3.4, 1.2) + S('M12.5 27.7h11'),
  slides: fr(3, 5, 26, 19, 3) + S('M7.5 12.5l-2 2 2 2M24.5 12.5l2 2-2 2') + B('M10.5 11h11') + S('M12 14.5h8') + r(12.5, 17.5, 7, 3, 1.5, 'a') + c(13, 27.5, 1, 'a') + c(16, 27.5, 1) + c(19, 27.5, 1),
  'language-switcher': c(16, 16, 11) + S('M5 16h22M16 5c-3.5 3-5 6.7-5 11s1.5 8 5 11M16 5c3.5 3 5 6.7 5 11s-1.5 8-5 11') + r(19, 19, 10, 8, 2, 'f') + r(19, 19, 10, 8, 2),
  'scheme-switch':fr(4, 10, 24, 12, 6) + c(22, 16, 4, 'a') + c(10.5, 16, 2) + S('M10.5 11.5v.5M10.5 20v.5M6 16h.5M14.5 16h.5'),
  'search-form': r(4, 10, 24, 12, 6) + c(11, 15.5, 2.8) + S('M13.1 17.6l1.9 1.9') + r(18, 13.3, 7, 5.4, 2.7, 'a'),
  'share-buttons': S('M11.6 14.6l7.8-4.2M11.6 17.4l7.8 4.2') + c(9, 16, 3, 'a') + c(22, 9, 3, 'f') + c(22, 9, 3) + c(22, 23, 3, 'f') + c(22, 23, 3),
  shortcode: S('M10 6H6.5v20H10M22 6h3.5v20H22') + c(12.5, 16, 1.4, 'a') + c(16, 16, 1.4, 'a') + c(19.5, 16, 1.4, 'a'),
  'site-logo': F('M16 4l10 4v7c0 6-4.5 10.5-10 13-5.5-2.5-10-7-10-13V8z') + S('M16 4l10 4v7c0 6-4.5 10.5-10 13-5.5-2.5-10-7-10-13V8z') + AS('M12 15.5l3 3 5-6'),
  'site-tagline': B('M5 11h16') + AS('M5 17h22') + S('M5 22h15'),
  'site-title': r(4, 7, 24, 18, 3) + S('M4 12h24') + c(7.5, 9.5, 0.8, 'a') + c(10.3, 9.5, 0.8) + B('M8.5 18.5h11'),
  'social-icons': c(8, 16, 4.2, 'a') + c(16, 16, 4.2, 'f') + c(16, 16, 4.2) + c(24, 16, 4.2, 'f') + c(24, 16, 4.2),
  spacer: S('M6 5h20M6 27h20') + AS('M16 9.5v13M13 12.5l3-3 3 3M13 19.5l3 3 3-3'),
  'star-rating': STAR(7, 16, 4.6) + STAR(16, 16, 4.6) + STAR(25, 16, 4.6, 'f') + STAR(25, 16, 4.6, 's'),
  steps: c(7, 16, 3.2, 'a') + S('M10.5 16h2') + c(16, 16, 3.2, 'f') + c(16, 16, 3.2) + S('M19.5 16h2') + c(25, 16, 3.2),
  table: F('M7 6h18a3 3 0 0 1 3 3v3H4V9a3 3 0 0 1 3-3z') + r(4, 6, 24, 20, 3) + S('M4 12h24M4 19h24M13 6v20M21 6v20'),
  'table-of-contents': c(6, 7.5, 1.6, 'a') + S('M10 7.5h16') + S('M11 14h2M16 14h10M11 20h2M16 20h8') + c(6, 26, 1.6, 'a') + S('M10 26h13'),
  tabs: S('M4 12h24v12a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3z') + F('M4 12V8a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2v4z') + S('M4 12V8a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2v4') + S('M15.5 12V9.5A1.5 1.5 0 0 1 17 8h4a1.5 1.5 0 0 1 1.5 1.5V12') + AS('M8 17h14') + S('M8 21h10'),
  'team-member': r(6, 4, 20, 24, 3) + c(16, 11.5, 4, 'f') + c(16, 11.5, 4) + B('M11 19.5h10') + c(13, 24, 1, 'a') + c(16, 24, 1, 'a') + c(19, 24, 1, 'a'),
  template: F('M16 5l11 6-11 6-11-6z') + S('M16 5l11 6-11 6-11-6z') + S('M5 16l11 6 11-6') + AS('M5 21l11 6 11-6'),
  testimonial: fr(4, 5, 24, 15, 3) + AS('M9.5 14.5v-2a3 3 0 0 1 3-3M15.5 14.5v-2a3 3 0 0 1 3-3') + c(9, 25, 2.6, 'a') + S('M14 24h9M14 27h6'),
  'testimonial-carousel': r(3.5, 8, 3, 12, 1.4) + r(25.5, 8, 3, 12, 1.4) + fr(8.5, 5, 15, 16, 3) + AS('M12.5 13.5v-1.5a2.5 2.5 0 0 1 2.5-2.5M17 13.5v-1.5a2.5 2.5 0 0 1 2.5-2.5') + S('M12.5 17h7') + c(14, 26.5, 1, 'a') + c(18, 26.5, 1),
  'text-editor': S('M5 8h22M5 13h22M5 18h22M5 23h13') + AS('M21 20.5v5'),
  timeline: S('M10 4v24') + c(10, 9, 2.6, 'a') + c(10, 21, 2.6, 'f') + c(10, 21, 2.6) + r(15, 6, 12, 6, 2, 'f') + r(15, 18, 12, 6, 2),
  video: fr(4, 7, 24, 18, 3) + A('M14 12.3v7.4l6.2-3.7z'),
};

/** Inline SVG for a widget, or null when it has no custom drawing (third-party widgets use their Lucide icon). */
export function widgetIconSvg(name: string, size = 30): string | null {
  const body = WIDGET_ICONS[name];
  return body ? `<svg class="uncoder-ui-wi" xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 32 32" aria-hidden="true">${body}</svg>` : null;
}
