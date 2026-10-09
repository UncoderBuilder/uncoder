import type { ControlDef } from '@shared/types';
import { NAME } from '@shared/brand';
import { config } from '../lib/config';
import { setPageSettings, setTitle, useDoc } from '../store/doc';
import { ControlForm } from '../controls/ControlForm';
import { TextInput } from '../ui/inputs';
import { STATUS_LABEL } from '../lib/labels';
import { ComponentProps } from './ComponentProps';
import { ConditionsSection, PopupSection } from './TemplateSettings';
import { docIcon, hasConditions, settingsTitle } from '../lib/docInfo';
import { Icon } from '../ui/Icon';

export function PagePanel() {
  const title = useDoc((s) => s.title);
  const status = useDoc((s) => s.status);
  const page = useDoc((s) => s.pageSettings);
  const isTemplate = config.post.type === 'uncoder_template';

  const controls: Record<string, ControlDef> = {
    ...(!isTemplate
      ? {
          template: {
            type: 'select',
            label: 'Page template',
            options: { '': 'Theme default', 'uncoder-full-width': `${NAME} Full Width`, 'uncoder-canvas': `${NAME} Canvas (no header/footer)` },
            description: 'Full Width keeps your header and footer; Canvas is a blank page for landing pages.',
          },
          hide_title: { type: 'switch', label: 'Hide page title', description: 'Hides the theme’s title above the content.' },
          header_transparent: {
            type: 'select',
            label: 'Transparent header',
            options: { '': 'Use the header’s setting', yes: 'Transparent on this page', no: 'Solid on this page' },
            description: 'A transparent header overlays the first section — give it enough top padding.',
          },
        }
      : {}),
    ...(config.post.docType === 'header'
      ? {
          _h_behavior: { type: 'heading', label: 'Header behaviour' },
          header_sticky: {
            type: 'select',
            label: 'Sticky',
            options: { '': 'Off — scrolls away', always: 'Always visible', reveal: 'Show when scrolling up' },
            description: 'To keep only part of the header, set Sticky → Top (Advanced) on the row that should stay: the rows above it, like a top bar, scroll away.',
          },
          header_scrolled_shadow: { type: 'switch', label: 'Shadow when scrolled', default: true, condition: { 'header_sticky!': '' } },
          header_scrolled_bg: { type: 'color', label: 'Background when scrolled', condition: { 'header_sticky!': '' } },
          header_scrolled_height: {
            type: 'slider',
            label: 'Shrink to height',
            size_units: ['px'],
            range: { px: { min: 40, max: 160 } },
            description: 'Minimum height of the header row once the page scrolls.',
            condition: { 'header_sticky!': '' },
          },
          header_scroll_offset: { type: 'number', label: 'Scrolled after (px)', min: 0, max: 2000, placeholder: '10' },
          _h_transparent: { type: 'heading', label: 'Transparent header' },
          header_transparent: {
            type: 'select',
            label: 'Transparent on',
            options: { '': 'Nowhere', all: 'All pages', front: 'Front page', selected: 'Pages that turn it on' },
            description: 'Overlays the first section of the page. With Sticky it turns solid once the page scrolls.',
          },
          header_transparent_keep_colors: {
            type: 'switch',
            label: 'Keep the header’s own colors',
            description: 'Only overlay the page; text, menu and logo keep their colors (for light heroes).',
            condition: { 'header_transparent!': '' },
          },
          header_transparent_color: { type: 'color', label: 'Text color while transparent', condition: { 'header_transparent!': '' } },
          header_transparent_logo: { type: 'media', label: 'Logo while transparent', condition: { 'header_transparent!': '' } },
          header_transparent_logo_white: {
            type: 'switch',
            label: 'Make the logo white instead',
            description: 'When no light logo is set.',
            condition: { 'header_transparent!': '' },
          },
        }
      : {}),
    ...(isTemplate && ['single-post', 'single-page', 'single', 'loop-item'].includes(config.post.docType)
      ? {
          preview_post: {
            type: 'select2',
            label: 'Preview with',
            source: 'posts',
            description: 'Content shown by dynamic widgets while you design. Empty = the latest post.',
          } as ControlDef,
        }
      : {}),
    background: { type: 'color', label: 'Background color' },
    ...(!isTemplate
      ? {
          scroll_snap: {
            type: 'select',
            label: 'Scroll snap',
            options: { '': 'Off', proximity: 'Gentle (when close to a section)', mandatory: 'Always (one section at a time)' },
            description: 'On the live site the page stops at each top-level section while scrolling (not in the editor).',
          } as ControlDef,
          scroll_snap_align: {
            type: 'select',
            label: 'Snap to',
            options: { start: 'Top of the section', center: 'Middle of the section' },
            condition: { 'scroll_snap!': '' },
          } as ControlDef,
        }
      : {}),
    ...(config.user.caps.unfiltered_html
      ? { custom_css: { type: 'code', language: 'css', label: 'Page CSS', responsive: true, description: 'Use “selector” to target this page’s content wrapper. Device tabs add CSS for that screen size and smaller.' } as ControlDef }
      : {}),
  };

  return (
    <div className="uncoder-ui-pagepanel">
      <div className="uncoder-ui-pagepanel__head">
        <span className="uncoder-ui-pagepanel__icon" aria-hidden>
          <Icon name={docIcon()} size={15} />
        </span>
        <h2>{settingsTitle()}</h2>
        <span className={`uncoder-ui-status uncoder-ui-status--${status}`}>{STATUS_LABEL[status] ?? status}</span>
      </div>
      <label className="uncoder-ui-field">
        <span className="uncoder-ui-field__label">Title</span>
        <TextInput value={title} onCommit={(v) => v !== title && setTitle(v)} aria-label="Title" />
      </label>
      {hasConditions && <ConditionsSection />}
      {config.post.docType === 'popup' && <PopupSection />}
      <ControlForm
        controls={controls}
        values={{
          template: config.post.pageTemplate === 'default' ? '' : config.post.pageTemplate,
          ...page,
          preview_post: page.preview_post ? [String(page.preview_post)] : [],
        }}
        onChange={(k, v) => {
          // "Preview with" is a single post: keep the most recent pick.
          if (k === 'preview_post') v = Array.isArray(v) && v.length ? Number(v[v.length - 1]) : 0;
          setPageSettings({ [k]: v === undefined ? '' : v });
        }}
      />
      {page.template !== undefined && page.template !== (config.post.pageTemplate || '') && <p className="uncoder-ui-note">Save and reload the canvas to see the new page template.</p>}
      <ComponentProps />
    </div>
  );
}
