// Uncoder front-end runtime (~2 KB). Widgets opt in with data-uncoder-js="name another";
// modules call UncoderWB.register(name, init) and are initialised once per element.

type Cleanup = void | (() => void);
export type ModuleInit = (el: HTMLElement, api: UncoderApi) => Cleanup;

export interface UncoderApi {
  register(name: string, init: ModuleInit, options?: { lazy?: boolean }): void;
  init(root?: ParentNode): void;
  destroy(root?: ParentNode): void;
  config: Record<string, any>;
  editor: boolean;
  settings<T = Record<string, any>>(el: HTMLElement): T;
  onVisible(el: Element, cb: () => void, rootMargin?: string): () => void;
  reducedMotion(): boolean;
}

declare global {
  interface Window {
    UncoderWB: UncoderApi;
    UncoderWBConfig?: Record<string, any>;
  }
}

(() => {
  if (window.UncoderWB) return;

  const modules = new Map<string, { init: ModuleInit; lazy: boolean }>();
  const done = new WeakMap<HTMLElement, Map<string, Cleanup>>();
  const editor = document.documentElement.classList.contains('uncoder-editing') || document.body?.classList.contains('uncoder-editing');

  let io: IntersectionObserver | null = null;
  const visibleCallbacks = new WeakMap<Element, Array<() => void>>();

  const api: UncoderApi = {
    config: window.UncoderWBConfig || {},
    editor: !!editor,

    register(name, init, options = {}) {
      modules.set(name, { init, lazy: !!options.lazy });
      if (document.readyState === 'loading') return;
      scan(document, name);
    },

    init(root = document) {
      scan(root);
    },

    destroy(root = document) {
      const els = root instanceof HTMLElement && root.hasAttribute('data-uncoder-js') ? [root] : [];
      root.querySelectorAll<HTMLElement>('[data-uncoder-js]').forEach((el) => els.push(el));
      for (const el of els) {
        const map = done.get(el);
        if (!map) continue;
        for (const cleanup of map.values()) if (typeof cleanup === 'function') cleanup();
        done.delete(el);
      }
    },

    settings(el) {
      try {
        return JSON.parse(el.getAttribute('data-settings') || '{}');
      } catch {
        return {} as any;
      }
    },

    onVisible(el, cb, rootMargin = '0px 0px -10% 0px') {
      if (!('IntersectionObserver' in window)) {
        cb();
        return () => {};
      }
      const list = visibleCallbacks.get(el) ?? [];
      list.push(cb);
      visibleCallbacks.set(el, list);
      const observer = new IntersectionObserver(
        (entries, obs) => {
          for (const entry of entries) {
            if (!entry.isIntersecting) continue;
            obs.unobserve(entry.target);
            (visibleCallbacks.get(entry.target) ?? []).forEach((fn) => fn());
            visibleCallbacks.delete(entry.target);
          }
        },
        { rootMargin },
      );
      observer.observe(el);
      return () => observer.disconnect();
    },

    reducedMotion() {
      return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    },
  };

  function run(el: HTMLElement, name: string) {
    const mod = modules.get(name);
    if (!mod) return;
    let map = done.get(el);
    if (!map) done.set(el, (map = new Map()));
    if (map.has(name)) return;
    map.set(name, undefined);
    const start = () => {
      try {
        map!.set(name, mod.init(el, api));
      } catch (e) {
        console.error('[Uncoder] module "' + name + '" failed', e);
      }
    };
    if (mod.lazy && !api.editor) {
      if (!io) {
        io = new IntersectionObserver(
          (entries) => {
            for (const entry of entries) {
              if (!entry.isIntersecting) continue;
              io!.unobserve(entry.target);
              const fn = (entry.target as any).__uncStart as Array<() => void>;
              fn?.forEach((f) => f());
              delete (entry.target as any).__uncStart;
            }
          },
          { rootMargin: '200px 0px' },
        );
      }
      ((el as any).__uncStart ||= []).push(start);
      io.observe(el);
    } else {
      start();
    }
  }

  // Lazy backgrounds (Settings → Performance): load a background image when its element nears the screen.
  let bgObserver: IntersectionObserver | null = null;
  function lazyBackgrounds(root: ParentNode) {
    const els = root.querySelectorAll<HTMLElement>('.uncoder-lazy-bg:not(.is-bg-in)');
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) {
      els.forEach((el) => el.classList.add('is-bg-in'));
      return;
    }
    bgObserver ||= new IntersectionObserver(
      (entries) => {
        for (const e of entries) {
          if (!e.isIntersecting) continue;
          e.target.classList.add('is-bg-in');
          bgObserver!.unobserve(e.target);
        }
      },
      { rootMargin: '400px 0px' },
    );
    els.forEach((el) => bgObserver!.observe(el));
  }

  function scan(root: ParentNode, only?: string) {
    if (!only) lazyBackgrounds(root);
    const els: HTMLElement[] = [];
    if (root instanceof HTMLElement && root.hasAttribute('data-uncoder-js')) els.push(root);
    root.querySelectorAll<HTMLElement>('[data-uncoder-js]').forEach((el) => els.push(el));
    for (const el of els) {
      const names = (el.getAttribute('data-uncoder-js') || '').split(/\s+/).filter(Boolean);
      for (const name of names) if (!only || only === name) run(el, name);
    }
  }

  window.UncoderWB = api;
  document.documentElement.classList.add('uncoder-js');

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => scan(document));
  } else {
    queueMicrotask(() => scan(document));
  }

  // Popup actions encoded in links: #uncoder-popup:open:123 (link fields) or #uncoder-popup-open-123 (rich text).
  document.addEventListener('click', (event) => {
    const a = (event.target as Element | null)?.closest?.('a[href^="#uncoder-popup"]') as HTMLAnchorElement | null;
    const m = a?.getAttribute('href')!.match(/^#uncoder-popup[:-](open|close|toggle)(?:[:-](\d+))?$/);
    if (!a || !m) return;
    event.preventDefault();
    document.dispatchEvent(new CustomEvent('uncoder:popup', { detail: { action: m[1], id: Number(m[2]), trigger: a } }));
  });
})();

export {};
