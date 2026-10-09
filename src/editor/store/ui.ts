import { create } from 'zustand';
import { config } from '../lib/config';

/**
 * What the build panel (left island) shows: the four tabs Insert ('add', or 'library' for its Sections
 * view), Layers, Styles ('kit') and Page, or a tool view opened from the command bar.
 */
export type LeftPanel = 'add' | 'library' | 'layers' | 'kit' | 'page' | 'history' | 'ai' | 'find' | 'a11y' | 'notes';
export const MAIN_PANELS: LeftPanel[] = ['add', 'library', 'layers', 'kit', 'page'];
const ALL_PANELS: LeftPanel[] = ['add', 'library', 'layers', 'kit', 'page', 'history', 'ai', 'find', 'a11y', 'notes'];

/** Deep links from wp-admin, e.g. …&panel=kit opens the Design System panel. */
function requestedPanel(): LeftPanel | undefined {
  const p = new URLSearchParams(window.location.search).get('panel');
  if (p === 'navigator') return 'layers';
  return p && ALL_PANELS.includes(p as LeftPanel) ? (p as LeftPanel) : undefined;
}
/** Inspector tabs: Content, Design (every visual setting incl. spacing), Behaviour (motion, visibility, attributes, CSS). */
export type InspectorTab = 'content' | 'design' | 'behaviour';

/** Where the inspector (settings panel) sits: docked next to the build panel, docked on the right, or floating. */
export type InspectorAt = 'left' | 'right' | 'float';
/** Floating inspector: top-left corner and height in window pixels. */
export interface InspectorFloat {
  x: number;
  y: number;
  h: number;
}

export interface Toast {
  id: number;
  kind: 'info' | 'success' | 'error' | 'warning';
  message: string;
  action?: { label: string; run: () => void };
}

export interface DropTarget {
  parent: string | null;
  index: number;
  /** Rectangle for the insertion indicator in canvas (iframe) coordinates. */
  line?: { x: number; y: number; w: number; h: number };
  /** Highlight rectangle of the receiving container. */
  box?: { x: number; y: number; w: number; h: number };
}

interface UiState {
  selected: string[];
  hovered: string | null;
  device: string;
  zoom: number;
  customWidth: number | null;
  theme: 'dark' | 'light';
  panel: LeftPanel;
  /** The build panel next to the rail is open; collapsed, only the 48px rail shows and the canvas gets the room. */
  panelOpen: boolean;
  /** Last build-panel tab, restored when a tool view closes. */
  mainPanel: LeftPanel;
  /** Islands floating over the canvas, or docked edge to edge ("docked panels"). */
  layout: 'float' | 'dock';
  inspectorAt: InspectorAt;
  /** Where the floating inspector was last (kept while docked, for the next time it floats). */
  inspectorFloat: InspectorFloat | null;
  /** The side the inspector docks to when it stops floating (the last docked side). */
  inspectorSide: 'left' | 'right';
  /**
   * State the inspector edits: 'normal', hover / focus / active / before / after, or a custom selector around
   * "&" ("&.is-open"). Style controls then write into `_states[state]`; sections with native Normal/Hover tabs
   * show their Hover tab for 'hover'.
   */
  inspectorState: string;
  /** Automatic scale so the desktop view renders at a real desktop width (see Canvas). */
  fit: number;
  /** Width the canvas renders at, in CSS pixels (shown in the command bar). */
  canvasWidth: number;
  /** Accessibility issues found on the page (badge on the Checks button). */
  checksCount: number;
  inspectorTab: InspectorTab;
  openSections: Record<string, boolean>;
  editingInline: string | null;
  dragging: null | { kind: 'new' | 'move'; label: string; icon: string; ids?: string[]; type?: string };
  drop: DropTarget | null;
  saving: boolean;
  lastSaved: number | null;
  toasts: Toast[];
  canvasReady: boolean;
  preview: boolean;
  palette: boolean;
  contextMenu: null | { x: number; y: number; id: string | null; source: 'canvas' | 'navigator' };
  /** Element being saved as a section template (dialog open). */
  saveTemplate: string | null;
  navigatorCollapsed: Record<string, boolean>;
  /** Widgets added most recently, newest first (the Recent group in Insert). */
  recentWidgets: string[];
  /** Editor preferences dialog open. */
  prefsOpen: boolean;
  /** Style book overlay open. */
  styleBook: boolean;
}

const stored = (() => {
  try {
    return JSON.parse(localStorage.getItem('uncoder-editor') || '{}');
  } catch {
    return {};
  }
})();

function validFloat(v: any): InspectorFloat | null {
  return v && [v.x, v.y, v.h].every((n) => typeof n === 'number' && Number.isFinite(n)) ? { x: v.x, y: v.y, h: v.h } : null;
}

/** The last build-panel tab, except that an empty page opens on Insert (nothing to see in Layers yet). */
function startPanel(): LeftPanel {
  if (!config.elements?.length && !config.user?.contentOnly) return 'add';
  return MAIN_PANELS.includes(stored.panel) ? (stored.panel as LeftPanel) : 'layers';
}

export const useUi = create<UiState>(() => ({
  selected: [],
  hovered: null,
  device: 'desktop',
  zoom: 1,
  customWidth: null,
  theme: stored.theme === 'dark' ? 'dark' : 'light',
  panel: requestedPanel() ?? startPanel(),
  // Open unless collapsed last time (a deep link to a panel always opens it).
  panelOpen: stored.panelOpen !== false || !!requestedPanel(),
  mainPanel: startPanel(),
  // Edge to edge since the 2026-10 layout (prefs v2); floating islands only for whoever picks them again.
  layout: stored.v === 2 && stored.layout === 'float' ? 'float' : 'dock',
  // Docked on the right unless the user chose otherwise. Saved as inspectorDock: the old inspectorAt also held the
  // former default (left) for people who never chose, so it is not read.
  inspectorAt: stored.inspectorDock === 'left' || stored.inspectorDock === 'float' ? stored.inspectorDock : 'right',
  inspectorFloat: validFloat(stored.inspectorFloat),
  inspectorSide: stored.inspectorDock && stored.inspectorSide === 'left' ? 'left' : 'right',
  inspectorState: 'normal',
  fit: 1,
  canvasWidth: 0,
  checksCount: 0,
  inspectorTab: 'content',
  openSections: stored.openSections ?? {},
  editingInline: null,
  dragging: null,
  drop: null,
  saving: false,
  lastSaved: null,
  toasts: [],
  canvasReady: false,
  preview: false,
  palette: false,
  contextMenu: null,
  saveTemplate: null,
  navigatorCollapsed: {},
  recentWidgets: Array.isArray(stored.recent) ? stored.recent.filter((n: unknown) => typeof n === 'string').slice(0, 6) : [],
  prefsOpen: false,
  styleBook: false,
}));

useUi.subscribe((s, prev) => {
  // A custom selector belongs to the element it was made on: another selection starts from Normal.
  if (s.selected !== prev.selected && s.inspectorState !== 'normal' && s.inspectorState.includes('&')) useUi.setState({ inspectorState: 'normal' });
  if (s.panel !== prev.panel && MAIN_PANELS.includes(s.panel) && s.mainPanel !== s.panel) useUi.setState({ mainPanel: s.panel });
  if (
    s.theme !== prev.theme ||
    s.mainPanel !== prev.mainPanel ||
    s.openSections !== prev.openSections ||
    s.layout !== prev.layout ||
    s.panelOpen !== prev.panelOpen ||
    s.recentWidgets !== prev.recentWidgets ||
    s.inspectorAt !== prev.inspectorAt ||
    s.inspectorFloat !== prev.inspectorFloat
  ) {
    try {
      localStorage.setItem(
        'uncoder-editor',
        JSON.stringify({ v: 2, theme: s.theme, panel: s.mainPanel, panelOpen: s.panelOpen, openSections: s.openSections, layout: s.layout, recent: s.recentWidgets, inspectorDock: s.inspectorAt, inspectorFloat: s.inspectorFloat, inspectorSide: s.inspectorSide }),
      );
    } catch {
      /* private mode */
    }
  }
});

export const select = (ids: string[] | string | null, additive = false) => {
  const list = ids === null ? [] : Array.isArray(ids) ? ids : [ids];
  const cur = useUi.getState().selected;
  if (additive) {
    const set = new Set(cur);
    for (const id of list) {
      if (set.has(id)) set.delete(id);
      else set.add(id);
    }
    useUi.setState({ selected: [...set], contextMenu: null });
  } else {
    useUi.setState({ selected: list, contextMenu: null });
  }
};

/** Zoom applied to the canvas frame: the chosen zoom times the automatic desktop fit. */
export const viewZoom = (): number => {
  const s = useUi.getState();
  return s.zoom * s.fit;
};

/** Opens a build-panel view; opening an open tool view again returns to the last tab. */
export const togglePanel = (panel: LeftPanel) => useUi.setState((s) => ({ panel: s.panel === panel && s.panelOpen && !MAIN_PANELS.includes(panel) ? s.mainPanel : panel, panelOpen: true }));

/** Shows a build-panel view, opening the panel if it was collapsed (for anything the user asked to see). */
export const showPanel = (panel: LeftPanel) => useUi.setState({ panel, panelOpen: true });

/** Collapses the build panel to the rail, or opens it again (rail button, Ctrl/⌘ \). */
export const togglePanelOpen = () => useUi.setState((s) => ({ panelOpen: !s.panelOpen }));

/** A rail button: its view, or, when that view is already open, collapse to the rail. */
export const railSelect = (panel: LeftPanel) =>
  useUi.setState((s) => {
    const current = s.panel === panel || (panel === 'add' && s.panel === 'library');
    return current && s.panelOpen ? { panelOpen: false } : { panel: current ? s.panel : panel, panelOpen: true };
  });

export const setDevice = (device: string) => useUi.setState({ device, customWidth: null });

/** Docks the inspector on a side, or lets it float (where it floated last, or lifted from where it is). */
export const placeInspector = (at: InspectorAt, float?: InspectorFloat) =>
  useUi.setState((s) => ({ inspectorAt: at, inspectorSide: at === 'float' ? s.inspectorSide : at, inspectorFloat: float ?? s.inspectorFloat }));

let toastId = 0;
export function toast(message: string, kind: Toast['kind'] = 'info', action?: Toast['action'], timeout = 4200): void {
  const id = ++toastId;
  useUi.setState((s) => ({ toasts: [...s.toasts.slice(-3), { id, kind, message, action }] }));
  if (timeout) setTimeout(() => dismissToast(id), timeout);
}
export const dismissToast = (id: number) => useUi.setState((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) }));

export const breakpoints = config.breakpoints;

export function deviceWidth(device: string): number | null {
  if (device === 'desktop') return null;
  const bp = breakpoints.find((b) => b.id === device);
  if (!bp || bp.value === null) return null;
  // Preview a little inside the range so max-width queries apply.
  if (device === 'mobile') return 390;
  if (device === 'tablet') return 820 > bp.value ? bp.value : 820;
  return bp.direction === 'min' ? bp.value + 80 : bp.value;
}
