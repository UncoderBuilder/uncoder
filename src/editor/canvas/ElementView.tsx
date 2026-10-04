import { createElement, memo, useEffect, useLayoutEffect, useRef, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import type { Settings } from '@shared/types';
import { config, schemaOf } from '../lib/config';
import { useDoc } from '../store/doc';
import { requestRender, useRender } from '../store/render';
import { useUi } from '../store/ui';
import { frame, invalidateGeometry } from './frame';
import { EmptyContainer } from './EmptyContainer';
import { ShapeSvg, SHAPES } from '../lib/shapes';
import { elementAnchor } from '@shared/css';

const CONTAINER_TAGS = ['div', 'section', 'header', 'footer', 'main', 'article', 'aside', 'nav', 'a'];

/** Twin of Container::auto_tag(): <section> for page sections, <article> for loop-item cards, <div> elsewhere. */
const autoTag = (depth: number): string => {
  if (depth !== 0) return 'div';
  const type = config.post.docType;
  if (type === 'loop-item') return 'article';
  return ['header', 'footer', 'popup', 'mega-menu'].includes(type) ? 'div' : 'section';
};
const DEVICES = ['desktop', 'widescreen', 'laptop', 'tablet_extra', 'tablet', 'mobile_extra', 'mobile'];

/** Mirrors Common_Controls::wrapper() for the editor (hide classes become data-uncoder-ui-hidden). */
function commonWrapper(s: Settings): { classes: string[]; attrs: Record<string, string>; hidden: string } {
  const classes: string[] = [];
  const attrs: Record<string, string> = {};
  const hidden = DEVICES.filter((d) => s[`_hide_${d}`]).join(' ');
  if (s._hide_dark) classes.push('uncoder-hide-in-dark');
  if (s._hide_light) classes.push('uncoder-hide-in-light');
  if (Array.isArray(s._classes)) for (const c of s._classes) if (/^[a-z0-9_\-]+$/.test(String(c))) classes.push(`uncoder-gc-${c}`);
  if (typeof s._css_classes === 'string') {
    for (const c of s._css_classes.split(/\s+/)) if (/^[A-Za-z0-9_\-]+$/.test(c)) classes.push(c);
  }
  // Twin of Common_Controls::wrapper(): a box an interaction reveals (the canvas shows it dimmed).
  if (s._ix_hidden) classes.push('uncoder-ix-hidden');
  if (s._animation) {
    attrs['data-uncoder-anim'] = String(s._animation);
    classes.push('uncoder-anim');
  }
  if (s._sticky === 'top' || s._sticky === 'bottom') {
    classes.push(...(s._sticky === 'bottom' ? ['uncoder-sticky', 'uncoder-sticky--bottom'] : ['uncoder-sticky']));
    if (Array.isArray(s._sticky_on)) for (const bp of config.breakpoints) if (!s._sticky_on.includes(bp.id)) classes.push(`uncoder-sticky-off-${bp.id}`);
  }
  return { classes, attrs, hidden };
}

export const ElementList = memo(function ElementList({ ids, depth }: { ids: string[]; depth: number }) {
  return (
    <>
      {ids.map((id) => (
        <ElementView key={id} id={id} depth={depth} />
      ))}
    </>
  );
});

export const ElementView = memo(function ElementView({ id, depth }: { id: string; depth: number }) {
  const node = useDoc((s) => s.doc.nodes[id]);
  if (!node) return null;
  if (schemaOf(node.type)?.container) return <ContainerView id={id} depth={depth} />;
  if (!schemaOf(node.type)) return <div className="uncoder-ui-missing" data-id={id}>Unknown element “{node.type}”</div>;
  return <WidgetView id={id} depth={depth} />;
});

const ContainerView = memo(function ContainerView({ id, depth }: { id: string; depth: number }) {
  const node = useDoc((s) => s.doc.nodes[id]);
  const s = node.settings;
  const width = s.content_width;
  // Twin of Renderer::fits_children().
  // Twin of Renderer::fits_children(): justified or wrapping rows let child containers take their content width.
  const fitsChildren = (c: Record<string, any>) =>
    c.layout !== 'grid' && ['row', 'row-reverse'].includes(c.direction ?? 'column') && (['center', 'flex-end', 'space-between', 'space-around', 'space-evenly'].includes(c.justify ?? '') || c.wrap === 'wrap');
  const boxed = width === undefined || width === '' ? depth === 0 : width === 'boxed';
  const tag = CONTAINER_TAGS.includes(s.tag) ? s.tag : autoTag(depth);
  const common = commonWrapper(s);
  const repeat = config.post.docType === 'loop-item';
  // Twin of Renderer::container_parts(): only non-default modifiers (flex and full width are the defaults).
  const classes = [
    'uncoder-container',
    `uncoder-${id}`,
    ...(boxed ? ['uncoder-container--boxed'] : []),
    ...(s.layout === 'grid' ? ['uncoder-container--grid'] : []),
    ...(s.overlay?.type ? ['uncoder-container--overlay'] : []),
    ...(fitsChildren(s) ? ['uncoder-container--fit'] : []),
    ...(s.background?.type === 'video' && s.background?.video_url ? ['uncoder-container--video'] : []),
    ...(bgLayer(s) || animatedBg(s) ? ['uncoder-container--bg'] : []),
    ...(hasShape(s) ? ['uncoder-container--shape'] : []),
    ...common.classes,
  ];
  const children: ReactNode = node.children.length ? <ElementList ids={node.children} depth={depth + 1} /> : <EmptyContainer id={id} />;
  const video = s.background?.type === 'video' && s.background?.video_url ? <BgVideo url={s.background.video_url} poster={s.background.video_fallback?.url} /> : null;
  return createElement(
    tag,
    {
      id: (!repeat && elementAnchor(s)) || undefined,
      className: classes.join(' '),
      'data-id': id,
      'data-uncoder-ui-container': '',
      'data-uncoder-ui-hidden': common.hidden || undefined,
      'data-uncoder-ui-disabled': node.disabled ? '' : undefined,
      ...common.attrs,
    },
    video,
    bgLayer(s) && <BgLayer s={s} />,
    animatedBg(s) && <AnimatedBg s={s} />,
    hasShape(s) && <ShapeDividers s={s} />,
    boxed ? <div className="uncoder-container__inner">{children}</div> : children,
  );
});

const hasShape = (s: Settings) => ['top', 'bottom'].some((side) => SHAPES[s[`shape_${side}`]]);

/** Twin of Renderer::shape_dividers(). */
function ShapeDividers({ s }: { s: Settings }) {
  return (
    <>
      {(['top', 'bottom'] as const).map((side) => {
        if (!SHAPES[s[`shape_${side}`]]) return null;
        const cls = ['uncoder-shape', `uncoder-shape--${side}`, s[`shape_${side}_flip`] ? 'uncoder-shape--flip' : '', s[`shape_${side}_front`] ? 'uncoder-shape--front' : ''].filter(Boolean).join(' ');
        return (
          <div key={side} className={cls} aria-hidden>
            <ShapeSvg name={s[`shape_${side}`]} invert={!!s[`shape_${side}_invert`]} />
          </div>
        );
      })}
    </>
  );
}

const BG_MOTIONS = ['parallax', 'zoom-in', 'zoom-out', 'mouse'];
/** Whether the container gets a background layer (twin of Renderer::background_layer()). */
function bgLayer(s: Settings): 'slides' | 'image' | null {
  const bg = s.background;
  if (bg?.type === 'slideshow') return Array.isArray(bg.slides) && bg.slides.some((i: any) => i?.url) ? 'slides' : null;
  const image = bg?.type === 'classic' && Object.entries(bg).some(([k, v]: [string, any]) => k.startsWith('image') && v?.url);
  return image && BG_MOTIONS.includes(s.bg_motion) ? 'image' : null;
}

/** Static in the editor: the first slide, or the image layer at rest. */
function BgLayer({ s }: { s: Settings }) {
  const kind = bgLayer(s);
  const bg = s.background ?? {};
  const motion = BG_MOTIONS.includes(s.bg_motion) ? s.bg_motion : '';
  const speed = Math.min(10, Math.max(1, Number(s.bg_motion_speed) || 4));
  const style: Record<string, string> = {};
  if (motion === 'parallax') style['--uncoder-bgm-extra'] = `${speed * 3}%`;
  if (motion === 'mouse') style['--uncoder-bgm-extra'] = `${speed * 5 + 4}px`;
  const cls = ['uncoder-container__bg', kind === 'image' ? 'uncoder-container__bg--image' : 'uncoder-container__bg--slides', motion ? `uncoder-container__bg--${motion}` : ''];
  let first: string | undefined;
  if (kind === 'slides') {
    first = (bg.slides as any[]).find((i) => i?.url)?.url;
    Object.assign(style, { '--uncoder-ss-size': ['contain', 'auto'].includes(bg.slide_size) ? bg.slide_size : 'cover', '--uncoder-ss-pos': bg.slide_position || 'center center' });
  }
  return (
    <div className={cls.filter(Boolean).join(' ')} style={style} aria-hidden>
      <div className="uncoder-container__bg-layer">{first && <div className="uncoder-bg-slide is-active" style={{ backgroundImage: `url("${String(first).replace(/["\\\n]/g, '')}")` }} />}</div>
    </div>
  );
}

const ABG_CSS = ['style-1', 'style-2', 'style-3', 'style-4', 'style-5'];
const ABG_SHADERS = ['fluid-gradient', 'borealis', 'gradient-mesh', 'mist', 'mystic-lake', 'noir-haze', 'void-wave', 'halftone', 'the-shining', 'phase-tunnel', 'plasma-line', 'light-strings', 'light-rays', 'flame', 'pulse-bubble', 'neon-eclipse', 'echo-sphere', 'liquid-mask', 'liquid-image', 'bit-wave', 'flux-stripes', 'perspective-grid'];
const ABG_DEFAULTS = { speed: 20, scale: 10, intensity: 50, noise: 20, angle: 0, frame: 10 };

/** The container's animated background (twin of Animated_Backgrounds::name() and ::settings()). */
function animatedBg(s: Settings): { name: string; settings: Record<string, unknown> | null } | null {
  const name = String(s.bg_animation ?? '');
  if (ABG_CSS.includes(name)) return { name, settings: null };
  if (!ABG_SHADERS.includes(name)) return null;
  const num = (key: string, def: keyof typeof ABG_DEFAULTS, min: number, max: number) => {
    const v = s[key];
    const n = v === '' || v === null || v === undefined || isNaN(Number(v)) ? ABG_DEFAULTS[def] : Number(v);
    return Math.max(min, Math.min(max, n));
  };
  const offset = (key: string) => (s[key] === '' || s[key] === null || s[key] === undefined || isNaN(Number(s[key])) ? 0 : Math.max(-400, Math.min(400, Number(s[key]))));
  const settings: Record<string, unknown> = {
    name,
    base: `${config.urls.assets}vendor/animated-bg/`,
    ver: config.version,
    speed: num('bg_anim_speed', 'speed', 1, 100),
    scale: num('bg_anim_scale', 'scale', 0, 100),
    intensity: num('bg_anim_intensity', 'intensity', 0, 100),
    noise: num('bg_anim_noise', 'noise', 0, 100),
    angle: num('bg_anim_angle', 'angle', 0, 360),
    offsetX: offset('bg_anim_offset_x'),
    offsetY: offset('bg_anim_offset_y'),
    interactive: !!s.bg_anim_interactive,
    static: !!s.bg_anim_freeze,
    frame: num('bg_anim_frame', 'frame', 0, 1000),
  };
  if (name === 'liquid-mask' || name === 'liquid-image') {
    const bg = s.background ?? {};
    const image = s.bg_anim_image?.url || (['', 'classic', undefined].includes(bg.type) ? bg.image?.url : '') || '';
    if (image) settings.image = image;
  }
  return { name, settings };
}

/** The animated layer; shaders run in the canvas through the bg-animated module, restarted when a setting changes. */
function AnimatedBg({ s }: { s: Settings }) {
  const abg = animatedBg(s)!;
  const ref = useRef<HTMLDivElement>(null);
  const json = abg.settings ? JSON.stringify(abg.settings) : '';
  // Colours are CSS variables the shader reads once, so they restart it too.
  const key = json + [1, 2, 3, 4].map((n) => s[`bg_anim_color_${n}`] ?? '').join('|') + '|' + (s.bg_anim_bg ?? '');
  useEffect(() => {
    const el = ref.current;
    const api = frame.win?.UncoderWB;
    if (!el || !api || !json) return;
    try {
      api.init(el);
    } catch {
      /* ignore */
    }
    return () => {
      try {
        api.destroy(el);
      } catch {
        /* ignore */
      }
    };
  }, [key, json]);
  if (!abg.settings) {
    return (
      <div className={`uncoder-abg uncoder-abg--css uncoder-abg--${abg.name}`} aria-hidden>
        <div className="uncoder-abg__g" />
      </div>
    );
  }
  return <div key={key} ref={ref} className={`uncoder-abg uncoder-abg--${abg.name}`} data-uncoder-js="bg-animated" data-settings={json} aria-hidden />;
}

function BgVideo({ url, poster }: { url: string; poster?: string }) {
  const isEmbed = /(youtube\.com|youtu\.be|vimeo\.com)/.test(url);
  if (isEmbed) return <div className="uncoder-bg-video" data-uncoder-js="bg-video" data-src={url} aria-hidden />;
  return (
    <div className="uncoder-bg-video" aria-hidden>
      <video className="uncoder-bg-video__el" autoPlay muted loop playsInline poster={poster} src={url} />
    </div>
  );
}

const WidgetView = memo(function WidgetView({ id, depth }: { id: string; depth: number }) {
  const node = useDoc((s) => s.doc.nodes[id]);
  const rendered = useRender((s) => s.items[id]);
  const pending = useRender((s) => !!s.pending[id]);
  const error = useRender((s) => s.errors[id]);
  const editing = useUi((s) => s.editingInline === id);
  const previewPost = useDoc((s) => s.pageSettings.preview_post);
  const ref = useRef<HTMLElement>(null);
  const [slots, setSlots] = useState<HTMLElement[]>([]);
  const nested = !!schemaOf(node.type)?.nested;
  // The widget's own root tag (one element, like the front end): an h2, a ul, a figure…
  const tag = rendered && /^[a-z][a-z0-9-]*$/.test(rendered.tag) ? rendered.tag : 'div';

  useEffect(() => {
    if (!editing) requestRender(node);
  }, [node, editing, previewPost]);

  // Apply the server's inner markup + element attributes, then (re)initialise front-end modules.
  useLayoutEffect(() => {
    const el = ref.current;
    if (!el || !rendered || editing) return;
    const api = frame.win?.UncoderWB;
    try {
      api?.destroy(el);
    } catch {
      /* ignore */
    }
    el.innerHTML = rendered.html;
    // Attributes of the server's element (id, class, data-*), minus hide classes (editor shows those dimmed).
    for (const attr of Array.from(el.attributes)) {
      // Editor markers stay (hidden / disabled / the state preview of cssManager).
      if (!['data-id', 'data-uncoder-ui-widget', 'data-uncoder-ui-hidden', 'data-uncoder-ui-disabled', 'data-uncoder-ui-force'].includes(attr.name)) el.removeAttribute(attr.name);
    }
    const hidden: string[] = [];
    for (const [k, v] of Object.entries(rendered.attrs)) {
      if (k === 'data-id') continue;
      if (k === 'class') {
        const cls = v
          .split(/\s+/)
          .filter((c) => {
            const m = c.match(/^uncoder-hide-(.+)$/);
            if (m) hidden.push(m[1]);
            return !m;
          })
          .join(' ');
        el.setAttribute('class', cls);
      } else {
        el.setAttribute(k, v);
      }
    }
    if (hidden.length) el.setAttribute('data-uncoder-ui-hidden', hidden.join(' '));
    else el.removeAttribute('data-uncoder-ui-hidden');
    if (nested) setSlots(Array.from(el.querySelectorAll<HTMLElement>('[data-uncoder-slot]')).filter((s) => s.closest('[data-uncoder-ui-widget]') === el));
    try {
      api?.init(el);
    } catch {
      /* ignore */
    }
    invalidateGeometry();
  }, [rendered, editing, nested, tag]);

  return createElement(
    tag,
    { ref, 'data-id': id, 'data-uncoder-ui-widget': '', className: `uncoder-${node.type}`, 'data-uncoder-ui-disabled': node.disabled ? '' : undefined },
    !rendered && <WidgetSkeleton type={node.type} error={error} pending={pending} />,
    nested &&
      rendered &&
      slots.map((slot, i) => {
        const child = node.children[i];
        return child ? createPortal(<ElementView id={child} depth={depth + 1} />, slot, child) : null;
      }),
  );
});

function WidgetSkeleton({ type, error, pending }: { type: string; error?: string; pending: boolean }) {
  const title = schemaOf(type)?.title ?? type;
  return (
    <div className={`uncoder-ui-skeleton${error ? ' is-error' : ''}`}>
      {error ? `${title}: ${error}` : pending ? `Loading ${title}…` : title}
    </div>
  );
}
