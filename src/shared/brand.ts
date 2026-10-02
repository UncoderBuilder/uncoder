// The Uncoder mark: a U whose N-shaped lightning bolt is cut out as negative space (assets/brand/).
// Two paths draw the U in a 92 × 96 box; BOLT is the cut itself, used by the loader to flash it.
// PHP twin: includes/Core/Brand.php.

export const BRAND_URL = 'https://uncoderbuilder.com';
export const DOCS_URL = 'https://docs.uncoderbuilder.com/';
/** WordPress.org support forum and reviews (slug `uncoder`). */
export const SUPPORT_URL = 'mailto:hello@uncoderbuilder.com';

export const U_LEFT = 'M0 0H70L49 34L29 14L11 86C3.6 80 0 69.6 0 58Z';
export const U_RIGHT = 'M79 0H92V58C92 82 74.6 96 46 96C34.7 96 25.1 94.1 17 90L39 56L59 76Z';
export const BOLT = 'M70 0H79L59 76L39 56L17 90L11 86L29 14L49 34Z';

/**
 * The app icon (assets/brand/uncoder-app-icon.svg): the mark in a true 256-unit circle, at scale 1.34 with
 * its origin at 66.36 / 66.762 — about half the diameter, optically centred (2.3 units lower than true centre).
 */
export const APP_ICON = { x: 66.36, y: 66.762, scale: 1.34 };
