import type { ComponentType } from 'react';
import { AnimationControl } from './AnimationControl';
import { ChooseControl, CodeControl, DateTimeControl, NumberControl, SelectControl, SliderControl, SwitchControl, TextareaControl, TextControl } from './basic';
import { ColorControl } from './ColorControl';
import { ConditionsControl } from './ConditionsControl';
import { InteractionsControl } from './InteractionsControl';
import { DimensionsControl } from './DimensionsControl';
import { FontControl } from './FontControl';
import { BackgroundControl, BorderControl, FiltersControl, QueryControl, ShadowControl, TransformControl, TypographyControl } from './groups';
import { IconControl } from './IconControl';
import { GalleryControl, MediaControl } from './MediaControl';
import { MultiSelectControl } from './MultiSelectControl';
import { OverridesControl } from './OverridesControl';
import { RepeaterControl } from './RepeaterControl';
import { ShapeControl } from './ShapeControl';
import { UrlControl } from './UrlControl';
import { WysiwygControl } from './WysiwygControl';

export const CONTROLS: Record<string, ComponentType<any>> = {
  text: TextControl,
  hidden: TextControl,
  date_time: DateTimeControl,
  textarea: TextareaControl,
  wysiwyg: WysiwygControl,
  code: CodeControl,
  number: NumberControl,
  slider: SliderControl,
  dimensions: DimensionsControl,
  select: SelectControl,
  choose: ChooseControl,
  switch: SwitchControl,
  color: ColorControl,
  font: FontControl,
  media: MediaControl,
  gallery: GalleryControl,
  icon: IconControl,
  url: UrlControl,
  link: UrlControl,
  repeater: RepeaterControl,
  multiselect: MultiSelectControl,
  select2: MultiSelectControl,
  typography: TypographyControl,
  background: BackgroundControl,
  border: BorderControl,
  box_shadow: ShadowControl,
  text_shadow: ShadowControl,
  css_filters: FiltersControl,
  backdrop_filter: FiltersControl,
  transform: TransformControl,
  query: QueryControl,
  overrides: OverridesControl,
  animation: AnimationControl,
  conditions: ConditionsControl,
  interactions: InteractionsControl,
};

/** Presentation overrides by `ui` hint (the stored value keeps the control type's format). */
const UI: Record<string, ComponentType<any>> = {
  shape: ShapeControl,
};

export const controlComponent = (control: { type: string; ui?: string }): ComponentType<any> | undefined => (control.ui && UI[control.ui]) || CONTROLS[control.type];

export const GROUP_TYPES = new Set(['typography', 'background', 'border', 'box_shadow', 'text_shadow', 'css_filters', 'backdrop_filter', 'transform', 'query']);

/** Controls that take the full inspector width (label above the input). */
export const STACKED = new Set(['wysiwyg', 'code', 'repeater', 'gallery', 'media', 'dimensions', 'background', 'query', 'multiselect', 'select2', 'textarea', 'url', 'link', 'overrides', 'animation', 'conditions', 'interactions']);
