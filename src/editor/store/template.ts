// Theme Builder data of the open template that lives outside the element tree: display conditions and
// popup settings (saved straight to templates/{id}, like the Theme Builder screen does).
import { create } from 'zustand';
import { templatesApi, templatesMeta, type Condition, type PopupSettings, type Template, type TemplatesMeta } from '../../admin/lib/api';
import { api } from '../lib/api';
import { config } from '../lib/config';
import { hasConditions, isTemplate } from '../lib/docInfo';
import { toast } from './ui';

interface TemplateState {
  loaded: boolean;
  template: Template | null;
  meta: TemplatesMeta | null;
  /** The conditions dialog: closed, opened from the settings panel, or right after publishing. */
  dialog: false | 'edit' | 'publish';
  popupSaving: boolean;
}

export const useTemplate = create<TemplateState>(() => ({ loaded: false, template: null, meta: null, dialog: false, popupSaving: false }));

let loading: Promise<void> | null = null;

export function loadTemplate(): Promise<void> {
  if (!isTemplate) return Promise.resolve();
  loading ??= Promise.all([api<Template>(`templates/${config.post.id}`), hasConditions ? templatesMeta() : Promise.resolve(null)])
    .then(([template, meta]) => useTemplate.setState({ loaded: true, template, meta }))
    .catch(() => {
      loading = null;
    });
  return loading;
}

export async function saveConditions(conditions: Condition[]): Promise<boolean> {
  try {
    const template = await templatesApi.update(config.post.id, { conditions });
    useTemplate.setState({ template });
    toast('Display conditions saved', 'success', undefined, 2200);
    return true;
  } catch (e: any) {
    toast(`Could not save the conditions: ${e?.message ?? 'unknown error'}`, 'error', undefined, 7000);
    return false;
  }
}

let popupTimer = 0;

/** Popup settings save shortly after the last change (the whole object, so nested triggers stay intact). */
export function setPopup(next: PopupSettings): void {
  const t = useTemplate.getState().template;
  if (!t) return;
  useTemplate.setState({ template: { ...t, popup: next }, popupSaving: true });
  window.clearTimeout(popupTimer);
  popupTimer = window.setTimeout(async () => {
    try {
      const template = await templatesApi.update(config.post.id, { popup: next });
      useTemplate.setState((s) => ({ template: s.template ? { ...s.template, popup: template.popup } : template, popupSaving: false }));
    } catch (e: any) {
      useTemplate.setState({ popupSaving: false });
      toast(`Could not save the popup settings: ${e?.message ?? 'unknown error'}`, 'error', undefined, 7000);
    }
  }, 500);
}

/** After a template is published without conditions, ask where it should appear. */
export async function afterPublish(): Promise<void> {
  if (!hasConditions) return;
  await loadTemplate();
  const { template } = useTemplate.getState();
  if (template && !(template.conditions ?? []).length) useTemplate.setState({ dialog: 'publish' });
}
