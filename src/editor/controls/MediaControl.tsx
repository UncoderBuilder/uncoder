import { useState } from 'react';
import type { MediaValue } from '@shared/types';
import { config } from '../lib/config';
import { Icon } from '../ui/Icon';
import { Button, IconButton } from '../ui/primitives';
import { TextInput } from '../ui/inputs';
import type { ControlProps } from './ControlRow';

type WpAttachment = { id: number; url: string; alt?: string; sizes?: Record<string, { url: string }>; type?: string; mime?: string };

export function openMedia(opts: { multiple?: boolean; type?: string; title?: string; selected?: number[] }): Promise<WpAttachment[]> {
  return new Promise((resolve) => {
    const wp = window.wp;
    if (!wp?.media) {
      resolve([]);
      return;
    }
    const frame = wp.media({
      title: opts.title ?? 'Select media',
      library: opts.type ? { type: opts.type } : undefined,
      multiple: opts.multiple ? 'add' : false,
      button: { text: 'Use selected' },
    });
    frame.on('open', () => {
      const sel = frame.state().get('selection');
      for (const id of opts.selected ?? []) {
        const att = wp.media.attachment(id);
        att.fetch();
        sel.add(att);
      }
    });
    frame.on('select', () => {
      resolve(frame.state().get('selection').toJSON());
    });
    frame.on('close', () => setTimeout(() => resolve([]), 0));
    frame.open();
  });
}

const thumb = (a: { url: string; sizes?: Record<string, { url: string }> }) => a.sizes?.medium?.url ?? a.sizes?.thumbnail?.url ?? a.url;

export function MediaControl({ control, value, placeholder, onChange }: ControlProps<MediaValue>) {
  const [urlMode, setUrlMode] = useState(false);
  const v = value ?? (placeholder as MediaValue | undefined);
  const hasImage = !!v?.url;
  const isVideo = /\.(mp4|webm|ogv|mov)(\?|$)/i.test(v?.url ?? '');
  // Non-image files (e.g. Lottie JSON) show their file name instead of a thumbnail.
  const kind = control.types?.[0] ?? 'image';
  const isFile = hasImage && !isVideo && kind !== 'image' && kind !== 'video';
  const noun = kind === 'image' ? 'image' : kind === 'video' ? 'video' : 'file';

  const choose = async () => {
    const [att] = await openMedia({ title: control.label, type: kind, selected: v?.id ? [v.id] : [] });
    if (att) onChange({ id: att.id, url: att.url, ...(att.alt ? { alt: att.alt } : {}) });
  };

  return (
    <div className={`uncoder-ui-media${hasImage ? '' : ' is-empty'}`}>
      <button type="button" className={`uncoder-ui-media__preview${hasImage ? '' : ' is-empty'}`} onClick={choose} disabled={!config.user.caps.upload_files && !hasImage} aria-label={hasImage ? `Replace ${noun}` : `Choose ${noun}`}>
        {hasImage ? (
          isFile ? (
            <span className="uncoder-ui-media__empty">
              <Icon name="file-json" size={20} stroke={1.6} />
              {decodeURIComponent(v!.url.split('/').pop() ?? '')}
            </span>
          ) : isVideo ? (
            <video src={v!.url} muted />
          ) : (
            <img src={v!.url} alt="" />
          )
        ) : (
          <span className="uncoder-ui-media__empty">
            <Icon name={noun === 'image' ? 'image-plus' : noun === 'video' ? 'file-video' : 'file-plus'} size={16} stroke={1.7} />
            Choose {noun}
          </span>
        )}
      </button>
      <div className="uncoder-ui-media__actions">
        {/* Empty: the dashed row itself opens the library, so only the URL toggle sits beside it. */}
        {hasImage && (
          <Button size="sm" icon="images" onClick={choose}>
            Replace
          </Button>
        )}
        <IconButton icon="link" label="Use a URL" size={13} active={urlMode} onClick={() => setUrlMode((m) => !m)} />
        {value && <IconButton icon="trash-2" label={`Remove ${noun}`} size={13} tone="danger" onClick={() => onChange(undefined)} />}
      </div>
      {urlMode && (
        <TextInput
          className="uncoder-ui-input--mono"
          value={value?.id ? '' : value?.url ?? ''}
          placeholder="https://…"
          onCommit={(url) => onChange(url.trim() ? { id: 0, url: url.trim() } : undefined)}
          aria-label="Image URL"
        />
      )}
    </div>
  );
}

export function GalleryControl({ control, value, onChange }: ControlProps<MediaValue[]>) {
  const items = value ?? [];
  const add = async () => {
    const atts = await openMedia({ multiple: true, title: control.label, type: 'image', selected: items.map((i) => i.id).filter(Boolean) });
    if (atts.length) onChange(atts.map((a) => ({ id: a.id, url: a.url, ...(a.alt ? { alt: a.alt } : {}) })));
  };
  const move = (from: number, to: number) => {
    if (to < 0 || to >= items.length) return;
    const next = [...items];
    const [x] = next.splice(from, 1);
    next.splice(to, 0, x);
    onChange(next);
  };
  return (
    <div className="uncoder-ui-gallery">
      {items.length > 0 && (
        <div className="uncoder-ui-gallery__grid">
          {items.map((it, i) => (
            <div key={`${it.id}-${i}`} className="uncoder-ui-gallery__item">
              <img src={it.url} alt="" />
              <div className="uncoder-ui-gallery__tools">
                <button type="button" aria-label="Move left" onClick={() => move(i, i - 1)}>
                  <Icon name="chevron-left" size={12} />
                </button>
                <button type="button" aria-label="Remove" onClick={() => onChange(items.filter((_, j) => j !== i))}>
                  <Icon name="x" size={12} />
                </button>
                <button type="button" aria-label="Move right" onClick={() => move(i, i + 1)}>
                  <Icon name="chevron-right" size={12} />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
      <Button size="sm" icon={items.length ? 'pencil' : 'images'} onClick={add}>
        {items.length ? `Edit gallery (${items.length})` : 'Add images'}
      </Button>
    </div>
  );
}

export { thumb };
