// What kind of document the editor has open, in words: "Page settings", "Header settings", "Popup
// settings"… and whether it is a Theme Builder template with display conditions.
import { config } from './config';

export const isTemplate = config.post.type === 'uncoder_template';

/** Template types placed by display conditions (Templates_Controller::CONDITIONAL). */
const CONDITIONAL = ['header', 'footer', 'single-post', 'single-page', 'single', 'archive', 'search-results', 'error-404', 'popup'];
export const hasConditions = isTemplate && CONDITIONAL.includes(config.post.docType);

const NOUNS: Record<string, string> = { single: 'Single item' };
const SHORT = ['header', 'footer', 'popup', 'section'];

/** "Header", "Single post", "Page", "Post", "Product"… */
export function docNoun(): string {
  if (!isTemplate) return config.post.typeLabel || 'Page';
  const t = config.post.docType;
  return NOUNS[t] ?? config.schema.templateTypes[t] ?? 'Template';
}

/** Title of the settings panel and of every link that opens it. */
export const settingsTitle = (): string => `${docNoun()} settings`;

/** Build-panel tab: short names fit, longer template types read "Template". */
export function tabLabel(): string {
  if (isTemplate) return SHORT.includes(config.post.docType) ? docNoun() : 'Template';
  const noun = docNoun();
  return noun.length <= 9 ? noun : 'Settings';
}

/** Lucide icon for the document type. */
export function docIcon(): string {
  const icons: Record<string, string> = {
    header: 'panel-top',
    footer: 'panel-bottom',
    popup: 'app-window',
    section: 'layers',
    'single-post': 'file-text',
    'single-page': 'file',
    single: 'files',
    archive: 'archive',
    'search-results': 'search',
    'error-404': 'file-x',
    'loop-item': 'repeat',
    'mega-menu': 'menu',
  };
  if (isTemplate) return icons[config.post.docType] ?? 'layout-template';
  return config.post.type === 'post' ? 'file-text' : 'file';
}
