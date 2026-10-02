// Shared types between the editor, the admin and the CSS engine.
// They mirror the PHP schema produced by Element_Base::schema().

export type Device = string; // 'desktop' | 'tablet' | 'mobile' | 'laptop' | …

export interface Breakpoint {
  id: Device;
  label: string;
  value: number | null;
  direction: 'min' | 'max';
}

export interface SizeValue {
  size: number | string;
  unit: string;
}

export interface DimensionsValue {
  top: number | string;
  right: number | string;
  bottom: number | string;
  left: number | string;
  unit: string;
  linked?: boolean;
}

export interface MediaValue {
  id: number;
  url: string;
  alt?: string;
  size?: string;
}

export interface IconValue {
  /** lucide, svg, none, or an icon library id (fa-solid, phosphor, bootstrap…). */
  library: string;
  value?: string;
  id?: number;
  url?: string;
}

export interface LinkValue {
  url: string;
  external?: boolean;
  nofollow?: boolean;
  attributes?: string;
}

export type Settings = Record<string, any>;

export interface DynamicDef {
  tag: string;
  options?: Record<string, any>;
  before?: string;
  after?: string;
  fallback?: string;
}

export interface ElementNode {
  id: string;
  type: string;
  settings: Settings;
  children?: ElementNode[];
  dynamic?: Record<string, DynamicDef>;
  label?: string;
  disabled?: boolean;
  /** Locked in the editor: not moved, deleted or edited until unlocked (children too). */
  locked?: boolean;
}

export type ControlOptions = Record<string, string | { label: string; icon?: string }>;

export interface ControlDef {
  type: string;
  label?: string;
  description?: string;
  placeholder?: string;
  /** Text / URL fields that can also be filled from the media library ("video", "image", "audio"…). */
  media?: string;
  default?: any;
  options?: ControlOptions;
  options_dynamic?: boolean;
  source?: string;
  size_units?: string[];
  range?: Record<string, { min?: number; max?: number; step?: number }>;
  min?: number;
  max?: number;
  step?: number;
  responsive?: boolean;
  selectors?: Record<string, string>;
  selector?: string;
  selectors_dictionary?: Record<string, string>;
  condition?: Record<string, any>;
  dynamic?: boolean;
  inline?: boolean;
  html?: 'inline';
  render?: 'css' | 'template' | 'none';
  css_default?: boolean;
  section?: string | null;
  tab?: 'content' | 'style' | 'advanced';
  ui_tab?: [string, string];
  fields?: Record<string, ControlDef>;
  types?: string[];
  language?: string;
  rows?: number;
  ai?: string;
  title_field?: string;
  allow_auto?: boolean;
  /** Editor-only presentation hint, e.g. "shape" for the shape divider picker. */
  ui?: string;
  /** Choose icons drawn for a row that turn with the flex direction: main / cross axis of the element, or of its parent (self). */
  axis?: 'main' | 'cross' | 'self';
  /** Animation control: what the element can animate (itself, child items, words / letters / lines). */
  targets?: Array<'self' | 'children' | 'words' | 'chars' | 'lines'>;
}

export interface SectionDef {
  id: string;
  label: string;
  tab: 'content' | 'style' | 'advanced';
  condition?: Record<string, any> | null;
  controls: string[];
  tab_controls?: Record<string, Record<string, string[]>>;
  open?: boolean | null;
}

export interface ElementSchema {
  name: string;
  title: string;
  icon: string;
  category: string;
  keywords: string[];
  description: string;
  container: boolean;
  nested: { items: string } | null;
  render: 'static' | 'dynamic';
  sections: SectionDef[];
  controls: Record<string, ControlDef>;
  ui_tabs: Record<string, Record<string, string>>;
  inline: string[];
  preset: Settings;
}

export interface DynamicTagSchema {
  name: string;
  title: string;
  group: string;
  categories: string[];
  controls: Record<string, ControlDef>;
  capability?: string;
}

export interface KitColor {
  id: string;
  name: string;
  value: string;
  /** Value in dark mode (settings.color_scheme toggle | auto). */
  dark?: string;
}
/** Global class: shared style settings for one element type (rendered as .uncoder .uncoder-gc-{id}). */
export interface KitClass {
  id: string;
  name: string;
  type: string;
  settings: Settings;
}
export interface KitFont {
  id: string;
  name: string;
  family: string;
}
export interface KitTypography {
  id: string;
  name: string;
  value: Settings;
}

/** A design variable (Styles → Variables): --uncoder-v-{id}. */
export interface KitVariable {
  id: string;
  name: string;
  group: 'spacing' | 'size' | 'radius' | 'other';
  value: string;
}

export interface Kit {
  variables?: KitVariable[];
  colors: KitColor[];
  fonts: KitFont[];
  typography: KitTypography[];
  layout: Settings;
  buttons: Settings;
  forms: Settings;
  theme: Settings;
  settings: Settings;
  custom_css: string;
  breakpoints: Breakpoint[];
  classes?: KitClass[];
}
