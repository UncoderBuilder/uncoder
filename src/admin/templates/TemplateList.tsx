import { useEffect, useRef, useState } from 'react';
import { Icon } from '@editor/ui/Icon';
import { Menu, type MenuItem } from '@editor/ui/Popover';
import { IconButton } from '@editor/ui/primitives';
import { NAME } from '@shared/brand';
import { templatesApi, type Template } from '../lib/api';
import { absoluteTime, cx, relativeTime } from '../lib/format';
import { copyText } from '../lib/hooks';
import { toast, toastError } from '../lib/toast';
import { confirmDialog } from '../ui/Dialog';
import { Badge } from '../ui/kit';
import { LAYOUT_LABELS, triggerSummary, TYPE_INFO } from './common';
import { downloadExport } from '../lib/transfer';

export type ListMode = 'theme' | 'popup' | 'section';

export interface ListHandlers {
  replace: (t: Template) => void;
  remove: (id: number) => void;
  add: (t: Template) => void;
  openConditions: (t: Template) => void;
  openPopup?: (t: Template) => void;
  /** Live preview (PreviewDialog). */
  openPreview?: (t: Template) => void;
}

/** Row actions shared by every template list. */
export function useTemplateActions(h: ListHandlers) {
  const [busy, setBusy] = useState<number | null>(null);

  const run = async <T,>(id: number, fn: () => Promise<T>): Promise<T | undefined> => {
    setBusy(id);
    try {
      return await fn();
    } catch (e) {
      toastError(e);
      return undefined;
    } finally {
      setBusy(null);
    }
  };

  const setStatus = async (t: Template, status: 'publish' | 'draft') => {
    const saved = await run(t.id, () => templatesApi.update(t.id, { status }));
    if (!saved) return;
    h.replace(saved);
    toast(status === 'publish' ? `“${saved.title}” is published` : `“${saved.title}” moved to draft`, 'success', {
      label: 'Undo',
      run: async () => {
        try {
          h.replace(await templatesApi.update(t.id, { status: t.status === 'publish' ? 'publish' : 'draft' }));
        } catch (e) {
          toastError(e);
        }
      },
    });
  };

  const duplicate = async (t: Template) => {
    const copy = await run(t.id, () => templatesApi.duplicate(t.id));
    if (!copy) return;
    h.add(copy);
    toast(`Duplicated as “${copy.title}” (draft)`, 'success', { label: 'Edit', run: () => (window.location.href = copy.editUrl) });
  };

  const trash = async (t: Template) => {
    const live = t.conditional ? t.active : t.status === 'publish';
    const ok = await confirmDialog({
      title: `Move “${t.title}” to the trash?`,
      body: live
        ? t.type === 'section'
          ? 'Pages that embed this section will no longer show it.'
          : 'It is live on your site and will stop showing immediately.'
        : 'You can restore it right after, or later from the WordPress trash.',
      confirmLabel: 'Move to trash',
      danger: true,
    });
    if (!ok) return;
    const res = await run(t.id, () => templatesApi.trash(t.id));
    if (!res) return;
    h.remove(t.id);
    toast(`“${t.title}” moved to the trash`, 'success', {
      label: 'Undo',
      run: async () => {
        try {
          h.add(await templatesApi.restore(t.id));
          toast('Template restored');
        } catch (e) {
          toastError(e);
        }
      },
    });
  };

  return { busy, setStatus, duplicate, trash };
}

function WhereChips({ t }: { t: Template }) {
  const conds = t.conditions ?? [];
  if (!conds.length) return <span className="uncoder-ui-where__text">Not displayed anywhere</span>;
  const shown = conds.slice(0, 3);
  return (
    <span className="uncoder-ui-condchips">
      {shown.map((c, i) => (
        <span key={i} className={cx('uncoder-ui-condchip', c.type === 'exclude' && 'is-exclude')}>
          {c.type === 'exclude' ? '− ' : ''}
          {c.label ?? c.rule}
        </span>
      ))}
      {conds.length > shown.length && <span className="uncoder-ui-condchip is-more">+{conds.length - shown.length}</span>}
    </span>
  );
}

function StatusCell({ t }: { t: Template }) {
  if (t.status !== 'publish') return <Badge dot>Draft</Badge>;
  if (t.conditional) {
    return t.active ? (
      <Badge tone="success" dot title="Published and displayed by its conditions">
        Live
      </Badge>
    ) : (
      <Badge tone="warning" dot title="Published but without display conditions">
        Not shown
      </Badge>
    );
  }
  return (
    <Badge tone="success" dot>
      Published
    </Badge>
  );
}

function RowActions({ t, actions, h }: { t: Template; actions: ReturnType<typeof useTemplateActions>; h: ListHandlers }) {
  const ref = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false);
  const items: MenuItem[] = [];
  // Per row, not per list: "All templates" mixes popups, sections and theme parts.
  const popup = t.type === 'popup';
  if (h.openPreview) items.push({ label: 'Preview', icon: 'eye', onSelect: () => h.openPreview!(t) });
  if (popup && h.openPopup) items.push({ label: 'Popup settings…', icon: 'settings', onSelect: () => h.openPopup!(t), disabled: !t.canEdit });
  if (t.conditional) items.push({ label: 'Display conditions…', icon: 'map-pin', onSelect: () => h.openConditions(t), disabled: !t.canEdit });
  if (popup && t.openLink) items.push({ label: 'Copy open link', icon: 'link', onSelect: () => copyText(t.openLink!, 'Popup link copied') });
  if (t.type === 'section') items.push({ label: 'Copy shortcode', icon: 'code', onSelect: () => copyText(t.shortcode, 'Shortcode copied') });
  items.push('separator');
  items.push(
    t.status === 'publish'
      ? { label: 'Unpublish (move to draft)', icon: 'eye-off', onSelect: () => actions.setStatus(t, 'draft'), disabled: !t.canEdit }
      : { label: 'Publish', icon: 'eye', onSelect: () => actions.setStatus(t, 'publish'), disabled: !t.canEdit },
  );
  items.push({ label: 'Duplicate', icon: 'copy', onSelect: () => actions.duplicate(t), disabled: !t.canEdit });
  items.push({ label: 'Export', icon: 'download', onSelect: () => downloadExport({ ids: [t.id] }, t.title), disabled: !t.canEdit });
  for (const l of t.languages?.translations ?? []) {
    items.push(
      l.editUrl
        ? { label: `Edit ${l.name} version`, icon: 'languages', onSelect: () => (window.location.href = l.editUrl!) }
        : { label: `Translate into ${l.name}`, icon: 'languages', onSelect: () => (window.location.href = l.translateUrl!), disabled: !t.canEdit },
    );
  }
  items.push('separator');
  items.push({ label: 'Move to trash', icon: 'trash-2', danger: true, onSelect: () => actions.trash(t), disabled: !t.canDelete });

  return (
    <div className="uncoder-ui-rowactions">
      {h.openPreview && <IconButton icon="eye" label={`Preview ${t.title || 'template'}`} onClick={() => h.openPreview!(t)} />}
      {t.canEdit ? (
        <a className="uncoder-ui-btn uncoder-ui-btn--secondary uncoder-ui-btn--sm" href={t.editUrl}>
          <Icon name="pencil" size={13} />
          Edit
        </a>
      ) : null}
      <IconButton ref={ref} icon="ellipsis" label={`More actions for ${t.title}`} onClick={() => setOpen((o) => !o)} aria-haspopup="menu" aria-expanded={open} disabled={actions.busy === t.id} />
      <Menu anchor={ref} open={open} onClose={() => setOpen(false)} items={items} placement="bottom-end" width={230} />
    </div>
  );
}

/* Live thumbnails: the preview page (Theme\Template_Preview) scaled down, loaded one at a time as rows come into
   view so a long list does not ask the server for every page at once. */
const THUMB_W = 112;
let thumbsLoading = 0;
const thumbQueue: Array<() => void> = [];
const nextThumb = () => {
  while (thumbsLoading < 2 && thumbQueue.length) {
    thumbsLoading++;
    thumbQueue.shift()!();
  }
};

function Thumb({ t, onOpen }: { t: Template; onOpen: () => void }) {
  const box = useRef<HTMLButtonElement>(null);
  const [src, setSrc] = useState('');
  const [ready, setReady] = useState(false);
  useEffect(() => {
    const el = box.current;
    if (!el) return;
    let queued = false;
    const io = new IntersectionObserver((entries) => {
      if (!entries.some((e) => e.isIntersecting) || queued) return;
      queued = true;
      io.disconnect();
      thumbQueue.push(() => setSrc(`${t.previewUrl}&thumb=1`));
      nextThumb();
    });
    io.observe(el);
    return () => io.disconnect();
  }, [t.previewUrl, t.modified]);
  const done = () => {
    setReady(true);
    thumbsLoading = Math.max(0, thumbsLoading - 1);
    nextThumb();
  };
  return (
    <button ref={box} type="button" className={cx('uncoder-ui-tplthumb', ready && 'is-ready')} onClick={onOpen} aria-label={`Preview ${t.title || 'template'}`}>
      <Icon name={TYPE_INFO[t.type]?.icon ?? 'file'} size={15} className="uncoder-ui-tplthumb__icon" />
      {src && <iframe src={src} title="" tabIndex={-1} aria-hidden="true" loading="lazy" style={{ transform: `scale(${THUMB_W / 1280})` }} onLoad={done} onError={done} />}
    </button>
  );
}

/** WPML / Polylang: this template's language, then each other language — its version, or "+" to translate. */
function LanguageChips({ t }: { t: Template }) {
  const l = t.languages!;
  return (
    <span className="uncoder-ui-langchips" aria-label="Languages">
      {l.lang && <span className="uncoder-ui-langchip is-own" title="Language of this template">{l.lang.toUpperCase()}</span>}
      {l.translations.map((tr) =>
        tr.editUrl ? (
          <a key={tr.code} className={cx('uncoder-ui-langchip', tr.status !== 'publish' && 'is-draft')} href={tr.editUrl} title={`Edit the ${tr.name} version${tr.status !== 'publish' ? ' (draft: the original shows until it is published)' : ''}`}>
            {tr.code.toUpperCase()}
          </a>
        ) : t.canEdit ? (
          <a key={tr.code} className="uncoder-ui-langchip is-missing" href={tr.translateUrl} title={`Translate into ${tr.name}: opens a copy in ${NAME}`}>
            <Icon name="plus" size={10} />
            {tr.code.toUpperCase()}
          </a>
        ) : null,
      )}
    </span>
  );
}

export function TemplateTable({ items, mode, showType, h }: { items: Template[]; mode: ListMode; showType: boolean; h: ListHandlers }) {
  const actions = useTemplateActions(h);
  return (
    <div className="uncoder-ui-tablewrap">
      <table className={cx('uncoder-ui-table', `uncoder-ui-table--${mode}`)}>
        <thead>
          <tr>
            <th scope="col">Name</th>
            <th scope="col" className="uncoder-ui-col-status">
              Status
            </th>
            {mode === 'popup' && (
              <th scope="col" className="uncoder-ui-col-hide-md">
                Opens
              </th>
            )}
            {mode === 'section' ? <th scope="col">Shortcode</th> : <th scope="col">Where it appears</th>}
            <th scope="col" className="uncoder-ui-col-date">
              Modified
            </th>
            <th scope="col" className="uncoder-ui-col-actions">
              <span className="uncoder-ui-sr-only">Actions</span>
            </th>
          </tr>
        </thead>
        <tbody>
          {items.map((t) => (
            <tr key={t.id} className={cx(actions.busy === t.id && 'is-busy', t.status !== 'publish' && 'is-draft')}>
              <td>
                <div className="uncoder-ui-namecell">
                  {h.openPreview ? (
                    <Thumb t={t} onOpen={() => h.openPreview!(t)} />
                  ) : (
                    <span className="uncoder-ui-namecell__icon" aria-hidden>
                      <Icon name={TYPE_INFO[t.type]?.icon ?? 'file'} size={15} />
                    </span>
                  )}
                  <div className="uncoder-ui-namecell__text">
                    {t.canEdit ? (
                      <a className="uncoder-ui-namecell__title" href={t.editUrl}>
                        {t.title || '(no title)'}
                      </a>
                    ) : (
                      <span className="uncoder-ui-namecell__title">{t.title || '(no title)'}</span>
                    )}
                    <span className="uncoder-ui-namecell__meta">
                      {showType && <>{t.typeLabel} · </>}
                      {t.type === 'popup' && t.popup && <>{LAYOUT_LABELS[t.popup.layout] ?? t.popup.layout} · </>}
                      {t.elements === 0 ? 'Empty' : `${t.elements} element${t.elements === 1 ? '' : 's'}`}
                    </span>
                    {t.languages && <LanguageChips t={t} />}
                  </div>
                </div>
              </td>
              <td className="uncoder-ui-col-status">
                <StatusCell t={t} />
              </td>
              {mode === 'popup' && <td className="uncoder-ui-col-hide-md uncoder-ui-cell-muted">{triggerSummary(t.popup)}</td>}
              {mode === 'section' ? (
                <td>
                  <button type="button" className="uncoder-ui-shortcode" onClick={() => copyText(t.shortcode, 'Shortcode copied')} title="Copy shortcode">
                    <code>{t.shortcode}</code>
                    <Icon name="copy" size={13} />
                  </button>
                </td>
              ) : (
                <td>
                  {t.conditional ? (
                    <button type="button" className={cx('uncoder-ui-where', !t.conditions?.length && 'is-empty')} onClick={() => h.openConditions(t)} disabled={!t.canEdit} title={t.summary ? `${t.summary} — edit display conditions` : 'Add display conditions'} aria-label={`Display conditions: ${t.summary || 'not displayed anywhere'}. Edit`}>
                      <WhereChips t={t} />
                      <Icon name="pencil" size={12} className="uncoder-ui-where__icon" />
                    </button>
                  ) : (
                    <span className="uncoder-ui-cell-muted" title="Placed with a widget, menu item or shortcode">
                      {t.type === 'loop-item' ? 'Used by loop grids & carousels' : t.type === 'mega-menu' ? 'Attached to menu items' : 'Embedded where you place it'}
                    </span>
                  )}
                </td>
              )}
              <td className="uncoder-ui-col-date">
                <time dateTime={t.modified} title={absoluteTime(t.modified)}>
                  {relativeTime(t.modified)}
                </time>
              </td>
              <td className="uncoder-ui-col-actions">
                <RowActions t={t} actions={actions} h={h} />
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
