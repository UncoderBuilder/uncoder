import { useMemo, type CSSProperties } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Button } from '@editor/ui/primitives';
import { fontStack } from '@shared/css';
import type { Kit, Settings } from '@shared/types';
import { NAME } from '@shared/brand';
import { api, type Overview } from '../lib/api';
import { can, cfg, editorUrl } from '../lib/config';
import { copyText, useHashState, useResource } from '../lib/hooks';
import { Callout, Card, Embedded, EmptyState, PageHeader, TabPanel, Tabs, useSubCrumb } from '../ui/kit';
import { FontsScreen } from './Fonts';
import { IconsScreen } from './Icons';

const size = (v: unknown): string => {
  if (!v || typeof v !== 'object') return '';
  const s = v as { size?: number | string; unit?: string };
  if (s.size === '' || s.size === undefined || s.size === null) return '';
  return `${s.size}${s.unit ?? ''}`;
};

/** CSS variables that make kit values (var(--uncoder-c-primary), var(--uncoder-f-heading)) resolve inside the preview. */
function kitVars(kit: Kit): CSSProperties {
  const vars: Record<string, string> = {};
  for (const c of kit.colors ?? []) vars[`--uncoder-c-${c.id}`] = c.value;
  for (const f of kit.fonts ?? []) vars[`--uncoder-f-${f.id}`] = f.family ? fontStack(f.family) : 'inherit';
  return vars as CSSProperties;
}

function familyName(kit: Kit, family: string): string {
  const m = /^var\(--uncoder-f-([a-z0-9_-]+)\)$/.exec(family.trim());
  if (m) {
    const font = kit.fonts.find((f) => f.id === m[1]);
    return font?.family ? font.family : `${font?.name ?? m[1]} font (theme default)`;
  }
  return family || 'Theme default';
}

function styleOf(v: Settings): CSSProperties {
  const fs = size(v.size);
  const n = parseFloat(fs);
  return {
    fontFamily: typeof v.family === 'string' && v.family ? (v.family.startsWith('var(') ? v.family : fontStack(v.family)) : undefined,
    // Big display sizes are scaled down to keep the list scannable (the real value is shown next to it).
    fontSize: fs ? (n > 44 && fs.endsWith('px') ? '44px' : fs) : undefined,
    fontWeight: v.weight || undefined,
    lineHeight: size(v.line_height) || undefined,
    letterSpacing: size(v.letter_spacing) || undefined,
    textTransform: v.transform || undefined,
    fontStyle: v.style || undefined,
  } as CSSProperties;
}

const KIT_TABS = ['styles', 'fonts', 'icons'] as const;
type KitTab = (typeof KIT_TABS)[number];

/** Design System: the global styles overview and the custom fonts that feed it. */
export function DesignSystemScreen() {
  const [tab, setTab] = useHashState(KIT_TABS, 'styles');
  useSubCrumb(tab === 'fonts' ? 'Custom fonts' : tab === 'icons' ? 'Custom icons' : null);
  return (
    <>
      <PageHeader title="Design System" description={`Global colors, fonts and text styles shared by every ${NAME} page and template. Change them once, everywhere updates.`} />
      <Tabs<KitTab>
        idBase="uncoder-ui-kit"
        label="Design System sections"
        value={tab}
        onChange={setTab}
        tabs={[
          { id: 'styles', label: 'Styles', icon: 'palette' },
          { id: 'fonts', label: 'Custom fonts', icon: 'type', count: Object.values(cfg.customFonts ?? {}).filter((f) => f.custom).length || null },
          { id: 'icons', label: 'Custom icons', icon: 'shapes' },
        ]}
      />
      <TabPanel idBase="uncoder-ui-kit" active={tab}>
        <Embedded>{tab === 'fonts' ? <FontsScreen /> : tab === 'icons' ? <IconsScreen /> : <KitStyles />}</Embedded>
      </TabPanel>
    </>
  );
}

function KitStyles() {
  const kit = cfg.kit;
  const overview = useResource((signal) => api<Overview>('overview', { signal }), []);
  const vars = useMemo(() => kitVars(kit), [kit]);
  const target = overview.data?.recent.find((p) => p.editUrl);
  const kitEditor = target ? `${editorUrl(target.id)}&panel=kit` : '';
  const buttons = (kit.buttons ?? {}) as Settings;
  const radius = buttons.radius && typeof buttons.radius === 'object' ? `${buttons.radius.top ?? 0}${buttons.radius.unit ?? 'px'}` : undefined;
  const pad = buttons.padding && typeof buttons.padding === 'object' ? `${buttons.padding.top ?? 0}${buttons.padding.unit ?? 'px'} ${buttons.padding.right ?? 0}${buttons.padding.unit ?? 'px'}` : undefined;
  const buttonPreset = kit.typography.find((t) => t.id === 'button');

  return (
    <>
      <PageHeader
        title="Styles"
        description="A read-only overview. The Design System is edited in the builder, where every change previews live."
        actions={
          <>
            <a className="uncoder-ui-btn uncoder-ui-btn--secondary" href={`${cfg.urls.site}?uncoder_style_book=1`} target="_blank" rel="noopener">
              <Icon name="book-open" size={14} />
              Style book
            </a>
            {kitEditor ? (
              <a className="uncoder-ui-btn uncoder-ui-btn--primary" href={kitEditor}>
                <Icon name="palette" size={14} />
                Edit in the builder
              </a>
            ) : (
              <Button variant="primary" icon="palette" disabled title={`Create a page with ${NAME} first`}>
                Edit in the builder
              </Button>
            )}
          </>
        }
      />
      <Callout tone="info" icon="info">
        To change it, open any {NAME} page and choose <strong>Styles</strong> in the left panel{target ? <> (the button opens “{target.title || 'Untitled'}”)</> : null}.
      </Callout>

      <div className="uncoder-ui-kit" style={vars}>
        <Card title="Colors" description="Referenced as CSS variables, so a change here recolors every element that uses them.">
          {kit.colors.length === 0 ? (
            <EmptyState icon="palette" title="No global colors" />
          ) : (
            <ul className="uncoder-ui-swatches">
              {kit.colors.map((c) => (
                <li key={c.id}>
                  <button type="button" className="uncoder-ui-swatch" onClick={() => copyText(`var(--uncoder-c-${c.id})`, `Copied var(--uncoder-c-${c.id})`)} title="Copy CSS variable">
                    <span className="uncoder-ui-swatch__chip" style={{ background: c.value }}>
                      {c.dark && <span className="uncoder-ui-swatch__dark" style={{ background: c.dark }} title={`Dark mode: ${c.dark}`} />}
                    </span>
                    <span className="uncoder-ui-swatch__name">{c.name}</span>
                    <span className="uncoder-ui-swatch__value">{c.value}</span>
                    <span className="uncoder-ui-swatch__var">--uncoder-c-{c.id}</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </Card>

        <div className="uncoder-ui-kit__row">
          <Card title="Fonts" className="uncoder-ui-kit__fonts">
            <ul className="uncoder-ui-fontlist">
              {kit.fonts.map((f) => (
                <li key={f.id} className="uncoder-ui-fontlist__item">
                  <span className="uncoder-ui-fontlist__sample" style={{ fontFamily: f.family ? fontStack(f.family) : undefined }} aria-hidden>
                    Aa
                  </span>
                  <span className="uncoder-ui-fontlist__text">
                    <strong>{f.name}</strong>
                    <span>{f.family || 'Theme default'}</span>
                  </span>
                </li>
              ))}
            </ul>
          </Card>
          <Card title="Buttons" className="uncoder-ui-kit__buttons">
            <div className="uncoder-ui-kit__btnprev">
              <span
                className="uncoder-ui-kit__btn"
                style={
                  {
                    background: buttons.background || 'var(--uncoder-c-primary)',
                    color: buttons.color || '#fff',
                    borderRadius: radius,
                    padding: pad,
                    ...(buttonPreset ? styleOf(buttonPreset.value) : {}),
                  } as CSSProperties
                }
              >
                Get started
              </span>
              <span className="uncoder-ui-muted">
                {buttons.background ? String(buttons.background).replace(/^var\(--uncoder-c-(.+)\)$/, '$1 color') : 'Primary color'} · radius {radius ?? '—'}
              </span>
            </div>
          </Card>
        </div>

        <Card flush title="Text styles" description="Typography presets used by headings, paragraphs and buttons. Sizes are desktop / tablet / mobile.">
          <ul className="uncoder-ui-typelist">
            {kit.typography.map((t) => {
              const v = t.value;
              const sizes = [size(v.size), size(v.size_tablet), size(v.size_mobile)].filter(Boolean).map((s) => s.replace('px', ''));
              return (
                <li key={t.id} className="uncoder-ui-typelist__row">
                  <div className="uncoder-ui-typelist__meta">
                    <strong>{t.name}</strong>
                    <span>
                      {familyName(kit, String(v.family ?? ''))}
                      <br />
                      {sizes.length ? `${sizes.join(' / ')} px` : 'inherit'} · {v.weight || '400'}
                      {size(v.line_height) ? ` · ${size(v.line_height)}` : ''}
                    </span>
                  </div>
                  <div className="uncoder-ui-typelist__sample" style={styleOf(v)}>
                    {t.id === 'eyebrow' ? 'Section eyebrow' : t.id === 'button' ? 'Book a demo' : t.id.startsWith('h') || t.id === 'display' ? 'Build the site you imagine' : `${NAME} turns ideas into fast, accessible pages without code.`}
                  </div>
                </li>
              );
            })}
          </ul>
        </Card>
      </div>
      {!can('edit_theme_options') && <p className="uncoder-ui-muted">You can view the Design System but not change it.</p>}
    </>
  );
}
