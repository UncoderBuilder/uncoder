import { useEffect, useState } from 'react';
import { AppIcon } from '../ui/Brand';
import { config, contentOnly } from '../lib/config';
import { DEVICE_ICON, deviceLabel } from '../lib/devices';
import { setTitle, useDoc } from '../store/doc';
import { useKit } from '../store/kit';
import { breakpoints, setDevice, togglePanel, useUi } from '../store/ui';
import { Icon } from '../ui/Icon';
import { Menu, Popover, usePopover, type MenuItem } from '../ui/Popover';
import { TextInput } from '../ui/inputs';
import { doRedo, doUndo, previewPage, save } from './actions';
import { MOD } from './shortcuts';
import { STATUS_LABEL } from '../lib/labels';
import { hasConditions, settingsTitle } from '../lib/docInfo';
import { loadTemplate, useTemplate } from '../store/template';
import { useNotes } from '../store/notes';

/** Device that a canvas width belongs to (so responsive editing follows the chosen width). */
function deviceForWidth(w: number): string {
  let device = 'desktop';
  let best = Infinity;
  for (const b of breakpoints) {
    if (b.value == null) continue;
    if (b.direction === 'max' && w <= b.value && b.value < best) {
      device = b.id;
      best = b.value;
    }
    if (b.direction === 'min' && w >= b.value) return b.id;
  }
  return device;
}

function ago(ts: number | null): string {
  if (!ts) return '';
  const s = Math.round((Date.now() - ts) / 1000);
  if (s < 5) return 'just now';
  if (s < 60) return `${s}s ago`;
  const m = Math.round(s / 60);
  return m < 60 ? `${m}m ago` : `${Math.round(m / 60)}h ago`;
}

/** The command bar: menu and page on the left, device / width / zoom in the middle, actions on the right. */
export function TopBar() {
  const device = useUi((s) => s.device);
  const zoom = useUi((s) => s.zoom);
  const fit = useUi((s) => s.fit);
  const canvasWidth = useUi((s) => s.canvasWidth);
  const saving = useUi((s) => s.saving);
  const lastSaved = useUi((s) => s.lastSaved);
  const theme = useUi((s) => s.theme);
  const layout = useUi((s) => s.layout);
  const preview = useUi((s) => s.preview);
  const checks = useUi((s) => s.checksCount);
  const openNotes = useNotes((s) => (s.notes ?? []).filter((n) => !n.resolved).length);
  const panel = useUi((s) => s.panel);
  const canUndo = useDoc((s) => s.past.length > 0);
  const canRedo = useDoc((s) => s.future.length > 0);
  const docDirty = useDoc((s) => s.version !== s.savedVersion);
  const kitDirty = useKit((s) => s.dirty);
  const dirty = docDirty || kitDirty;
  const status = useDoc((s) => s.status);
  const title = useDoc((s) => s.title);
  const mainMenu = usePopover();
  const docMenu = usePopover();
  const widthMenu = usePopover();
  const zoomMenu = usePopover();
  const publishMenu = usePopover();
  const previewMenu = usePopover();
  const [, tick] = useState(0);
  useEffect(() => {
    const t = setInterval(() => tick((n) => n + 1), 15000);
    return () => clearInterval(t);
  }, []);

  const isPublished = status === 'publish';
  // Published, private and scheduled pages are "live": Update keeps their status (and never unpublishes them).
  const isLive = isPublished || status === 'private' || status === 'future';
  const canPublish = config.user.caps.publish;
  const setWidth = (w: number | null) => (w === null ? setDevice('desktop') : useUi.setState({ device: deviceForWidth(w), customWidth: w }));
  const widths: MenuItem[] = [
    { label: 'Fit to window', icon: 'maximize-2', onSelect: () => setWidth(null) },
    'separator',
    ...[1920, 1440, 1280].map((w) => ({ label: `${w} px · desktop`, onSelect: () => setWidth(w) }) as MenuItem),
    ...(breakpoints.some((b) => b.id === 'tablet') ? [1024, 820, 768].map((w) => ({ label: `${w} px · tablet`, onSelect: () => setWidth(w) }) as MenuItem) : []),
    ...(breakpoints.some((b) => b.id === 'mobile') ? [430, 390, 360].map((w) => ({ label: `${w} px · mobile`, onSelect: () => setWidth(w) }) as MenuItem) : []),
  ];
  const tool = (id: typeof panel) => panel === id;

  return (
    <header className="uncoder-ui-topbar uncoder-ui-island">
      <div className="uncoder-ui-topbar__left">
        <button ref={mainMenu.anchorRef} type="button" className="uncoder-ui-logo" onClick={mainMenu.toggle} aria-haspopup="menu" aria-label="Menu" data-tip="Menu">
          <AppIcon size={32} />
        </button>
        <Menu
          anchor={mainMenu.anchorRef}
          open={mainMenu.open}
          onClose={mainMenu.close}
          width={250}
          items={[
            { label: 'Layers', icon: 'layers', shortcut: `${MOD}I`, onSelect: () => useUi.setState({ panel: 'layers' }) },
            { label: 'Styles (Design System)', icon: 'palette', onSelect: () => useUi.setState({ panel: 'kit' }), disabled: contentOnly() },
            { label: settingsTitle(), icon: 'file-cog', onSelect: () => useUi.setState({ panel: 'page' }), disabled: contentOnly() },
            { label: 'History', icon: 'history', onSelect: () => togglePanel('history') },
            { label: 'Find & replace', icon: 'replace-all', onSelect: () => togglePanel('find'), disabled: contentOnly() },
            ...(config.user.caps.edit_theme ? [{ label: 'Theme Builder', icon: 'layout-template', onSelect: () => window.open(`${config.urls.admin}admin.php?page=uncoder-templates`, '_blank') } as MenuItem] : []),
            'separator',
            { label: theme === 'dark' ? 'Paper (light) interface' : 'Petrol night (dark) interface', icon: theme === 'dark' ? 'sun' : 'moon', onSelect: () => useUi.setState({ theme: theme === 'dark' ? 'light' : 'dark' }) },
            { label: 'Docked panels', icon: 'panels-top-left', checked: layout === 'dock', onSelect: () => useUi.setState({ layout: layout === 'dock' ? 'float' : 'dock' }) },
            { label: 'Commands & shortcuts', icon: 'command', shortcut: `${MOD}K`, onSelect: () => useUi.setState({ palette: true }) },
            { label: 'Style book', icon: 'book-open', onSelect: () => useUi.setState({ styleBook: true }), disabled: contentOnly() },
            { label: 'Preferences…', icon: 'sliders-horizontal', onSelect: () => useUi.setState({ prefsOpen: true }) },
            'separator',
            { label: 'Exit to WordPress', icon: 'log-out', onSelect: () => (window.location.href = config.post.exitUrl) },
          ]}
        />
        <button ref={docMenu.anchorRef} type="button" className="uncoder-ui-pagebtn" onClick={docMenu.toggle} aria-haspopup="dialog" aria-label={`Page: ${title || 'Untitled'}`}>
          <span className="uncoder-ui-pagebtn__title">{title || 'Untitled'}</span>
          <Icon name="chevron-down" size={13} />
        </button>
        <span className={`uncoder-ui-status uncoder-ui-status--${status}`}>{STATUS_LABEL[status] ?? status}</span>
        <DocPopover open={docMenu.open} onClose={docMenu.close} anchor={docMenu.anchorRef} />
      </div>

      <div className="uncoder-ui-topbar__center">
        {!preview && (
          <>
            <div className="uncoder-ui-devseg" role="radiogroup" aria-label="Device">
              {breakpoints.map((bp) => (
                <button key={bp.id} type="button" role="radio" aria-checked={device === bp.id} aria-label={bp.label} className={`uncoder-ui-devseg__item${device === bp.id ? ' is-active' : ''}`} data-tip={deviceLabel(bp.id)} onClick={() => setDevice(bp.id)}>
                  <Icon name={DEVICE_ICON[bp.id] ?? 'monitor'} size={15} />
                  <span className="uncoder-ui-devseg__label">{bp.label}</span>
                </button>
              ))}
            </div>
            <button ref={widthMenu.anchorRef} type="button" className="uncoder-ui-chipbtn" onClick={widthMenu.toggle} aria-label="Canvas width" data-tip="Canvas width">
              {canvasWidth ? `${canvasWidth} px` : '—'}
              <Icon name="chevron-down" size={12} />
            </button>
            <Menu anchor={widthMenu.anchorRef} open={widthMenu.open} onClose={widthMenu.close} width={190} items={widths} />
            <button ref={zoomMenu.anchorRef} type="button" className="uncoder-ui-chipbtn" onClick={zoomMenu.toggle} aria-label="Zoom" data-tip={fit < 1 ? 'Zoom (scaled to show a real desktop width)' : 'Zoom'}>
              {Math.round(zoom * fit * 100)}%
              <Icon name="chevron-down" size={12} />
            </button>
            <Menu anchor={zoomMenu.anchorRef} open={zoomMenu.open} onClose={zoomMenu.close} width={150} items={[1, 0.75, 0.67, 0.5].map((z) => ({ label: z === 1 ? '100% (fit)' : `${Math.round(z * 100)}%`, checked: zoom === z, onSelect: () => useUi.setState({ zoom: z }) }))} />
          </>
        )}
      </div>

      <div className="uncoder-ui-topbar__right">
        {!preview && (
          <>
            <button type="button" className="uncoder-ui-tbtn" aria-label="Undo" data-tip={`Undo  ${MOD}Z`} disabled={!canUndo} onClick={doUndo}>
              <Icon name="undo-2" size={17} stroke={1.6} />
            </button>
            <button type="button" className="uncoder-ui-tbtn" aria-label="Redo" data-tip={`Redo  ${MOD}⇧Z`} disabled={!canRedo} onClick={doRedo}>
              <Icon name="redo-2" size={17} stroke={1.6} />
            </button>
            <button type="button" className={`uncoder-ui-tbtn${tool('a11y') ? ' is-active' : ''}`} aria-label={checks ? `Checks: ${checks} to review` : 'Checks'} data-tip={checks ? `${checks} accessibility check${checks === 1 ? '' : 's'} to review` : 'Checks'} onClick={() => togglePanel('a11y')}>
              <Icon name="shield-check" size={17} stroke={1.6} />
              {checks > 0 && <span className="uncoder-ui-badge">{checks > 99 ? '99+' : checks}</span>}
            </button>
            <button type="button" className={`uncoder-ui-tbtn${tool('notes') ? ' is-active' : ''}`} aria-label={openNotes ? `Notes: ${openNotes} open` : 'Notes'} data-tip={openNotes ? `${openNotes} open note${openNotes === 1 ? '' : 's'}` : 'Notes for your team'} onClick={() => togglePanel('notes')}>
              <Icon name="message-square-text" size={17} stroke={1.6} />
              {openNotes > 0 && <span className="uncoder-ui-badge">{openNotes > 99 ? '99+' : openNotes}</span>}
            </button>
            <button type="button" className={`uncoder-ui-aibtn-top${tool('ai') ? ' is-active' : ''}`} onClick={() => togglePanel('ai')}>
              <Icon name="sparkles" size={15} stroke={1.7} />
              Ask AI
            </button>
            <span className={`uncoder-ui-savestate${dirty ? ' is-dirty' : ''}`} role="status">
              {saving ? 'Saving…' : dirty ? 'Unsaved changes' : lastSaved ? `Saved ${ago(lastSaved)}` : 'Saved'}
            </span>
          </>
        )}
        <div className={`uncoder-ui-split uncoder-ui-previewbtn${preview ? ' is-active' : ''}`}>
          <button type="button" className="uncoder-ui-split__main" onClick={() => useUi.setState({ preview: !preview, selected: [] })}>
            {preview ? 'Back to editing' : 'Preview'}
          </button>
          <button ref={previewMenu.anchorRef} type="button" className="uncoder-ui-split__more" aria-label="Preview options" aria-haspopup="menu" onClick={previewMenu.toggle}>
            <Icon name="chevron-down" size={14} />
          </button>
        </div>
        <Menu
          anchor={previewMenu.anchorRef}
          open={previewMenu.open}
          onClose={previewMenu.close}
          placement="bottom-end"
          width={230}
          items={[
            { label: preview ? 'Back to editing' : 'Preview in editor', icon: preview ? 'pencil' : 'eye', onSelect: () => useUi.setState({ preview: !preview, selected: [] }) },
            { label: 'Preview in new tab', icon: 'external-link', shortcut: `${MOD}P`, onSelect: previewPage },
          ]}
        />
        <div className="uncoder-ui-publish">
          <button type="button" className="uncoder-ui-publish__main" disabled={saving || (isLive && !dirty)} onClick={() => (isLive ? save() : save(canPublish ? 'publish' : 'pending'))}>
            {isLive ? 'Update' : canPublish ? 'Publish' : 'Submit'}
          </button>
          <button ref={publishMenu.anchorRef} type="button" className="uncoder-ui-publish__more" aria-label="More publish options" onClick={publishMenu.toggle}>
            <Icon name="chevron-down" size={14} />
          </button>
        </div>
        <Menu
          anchor={publishMenu.anchorRef}
          open={publishMenu.open}
          onClose={publishMenu.close}
          placement="bottom-end"
          items={[
            // On a live page saving keeps it live, like Update; a draft stays a draft.
            { label: dirty ? (isLive ? 'Save changes' : 'Save draft') : 'Saved', icon: 'save', shortcut: `${MOD}S`, onSelect: () => save(), disabled: !dirty },
            ...(status !== 'draft' && status !== 'auto-draft' ? [{ label: 'Switch to draft', icon: 'file-pen', onSelect: () => save('draft') } as MenuItem] : []),
            ...(canPublish
              ? [
                  (status === 'private'
                    ? { label: 'Make public', icon: 'globe', onSelect: () => save('publish') }
                    : { label: 'Make private', icon: 'lock', onSelect: () => save('private') }) as MenuItem,
                ]
              : []),
            ...(hasConditions
              ? ([
                  'separator',
                  {
                    label: 'Display conditions…',
                    icon: 'map-pin',
                    onSelect: () => {
                      loadTemplate();
                      useTemplate.setState({ dialog: 'edit' });
                    },
                  },
                ] as MenuItem[])
              : []),
            // Only published pages have a public address to open.
            ...(isPublished ? (['separator', { label: 'View live page', icon: 'globe', onSelect: () => window.open(config.post.permalink, '_blank', 'noopener') }] as MenuItem[]) : []),
          ]}
        />
      </div>
    </header>
  );
}

function DocPopover({ open, onClose, anchor }: { open: boolean; onClose: () => void; anchor: React.RefObject<HTMLButtonElement | null> }) {
  const title = useDoc((s) => s.title);
  if (!open) return null;
  return (
    <Popover anchor={anchor} open onClose={onClose} width={280} label="Page">
      <div className="uncoder-ui-docpop">
        <label className="uncoder-ui-field">
          <span className="uncoder-ui-field__label">Title</span>
          <TextInput value={title} onCommit={(v) => v !== title && setTitle(v)} aria-label="Page title" />
        </label>
        <div className="uncoder-ui-docpop__meta">
          <span>{config.post.typeLabel}</span>
          <span>·</span>
          <span className="uncoder-ui-mono">#{config.post.id}</span>
        </div>
        <div className="uncoder-ui-docpop__links">
          <a href={config.post.permalink} target="_blank" rel="noreferrer">
            <Icon name="external-link" size={13} /> View page
          </a>
          <button type="button" onClick={() => (useUi.setState({ panel: 'page' }), onClose())}>
            <Icon name="settings-2" size={13} /> {settingsTitle()}
          </button>
          <a href={config.post.exitUrl}>
            <Icon name="log-out" size={13} /> Back to WordPress
          </a>
        </div>
      </div>
    </Popover>
  );
}
