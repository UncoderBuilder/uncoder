import { useEffect, useMemo, useState } from 'react';
import { Button } from '@editor/ui/primitives';
import { cleanConditions, templatesApi, type Template, type TemplatesMeta } from '../lib/api';
import { toast } from '../lib/toast';
import { confirmDialog, Drawer } from '../ui/Dialog';
import { Callout, EmptyState } from '../ui/kit';
import { ConditionRow, newRow, toCondition, toRow, type Row } from './conditionRows';

export { ConditionRow, newRow, toCondition, toRow, type Row } from './conditionRows';

interface Props {
  template: Template | null;
  meta: TemplatesMeta | null;
  onClose: () => void;
  onSaved: (t: Template) => void;
}

export function ConditionsDialog({ template, meta, onClose, onSaved }: Props) {
  const [rows, setRows] = useState<Row[]>([]);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!template) return;
    setRows((template.conditions ?? []).map(toRow));
    setError(null);
  }, [template]);

  const initial = useMemo(() => JSON.stringify(cleanConditions(template?.conditions ?? [])), [template]);
  const dirty = !!meta && JSON.stringify(cleanConditions(rows.map((r) => toCondition(r, meta)))) !== initial;

  const update = (key: number, patch: Partial<Row>) => setRows((list) => list.map((r) => (r.key === key ? { ...r, ...patch } : r)));

  const add = () => setRows((list) => [...list, newRow(list.length === 0)]);

  const save = async () => {
    if (!template || !meta) return;
    setSaving(true);
    setError(null);
    try {
      const saved = await templatesApi.update(template.id, { conditions: rows.map((r) => toCondition(r, meta)) });
      toast('Display conditions saved');
      onSaved(saved);
      onClose();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not save the conditions.');
    } finally {
      setSaving(false);
    }
  };

  const includes = rows.filter((r) => r.type === 'include').length;

  const requestClose = async () => {
    if (dirty && !(await confirmDialog({ title: 'Discard unsaved changes?', body: 'Your changes to the display conditions will be lost.', confirmLabel: 'Discard', danger: true }))) return;
    onClose();
  };

  return (
    <Drawer
      open={!!template}
      onClose={requestClose}
      width={560}
      title="Display conditions"
      subtitle={template ? <>“{template.title}” · choose where it appears. The most specific include wins; any matching exclude hides it.</> : null}
      footer={
        <>
          <Button variant="ghost" onClick={requestClose}>
            Cancel
          </Button>
          <Button variant="primary" onClick={save} loading={saving} disabled={!dirty || !meta}>
            Save conditions
          </Button>
        </>
      }
    >
      {error && <Callout tone="danger">{error}</Callout>}
      {template && template.status !== 'publish' && (
        <Callout tone="info" icon="circle-dot">
          This template is a draft: conditions take effect once it is published.
        </Callout>
      )}
      {!meta ? null : rows.length === 0 ? (
        <EmptyState
          icon="map-pin"
          title="Not displayed anywhere"
          action={
            <Button variant="secondary" icon="plus" onClick={add}>
              Add a condition
            </Button>
          }
        >
          Add an include condition, for example “Entire site”.
        </EmptyState>
      ) : (
        <>
          <ol className="uncoder-ui-conds" aria-label="Conditions">
            {rows.map((r, i) => (
              <ConditionRow key={r.key} row={r} index={i} meta={meta} onChange={(p) => update(r.key, p)} onRemove={() => setRows((list) => list.filter((x) => x.key !== r.key))} />
            ))}
          </ol>
          <div>
            <Button variant="secondary" icon="plus" onClick={add} className="uncoder-ui-btn--dashed">
              Add condition
            </Button>
          </div>
        </>
      )}
      {meta && rows.length > 0 && includes === 0 && (
        <Callout tone="warning">Only exclude conditions: add at least one include, otherwise the template never shows.</Callout>
      )}
    </Drawer>
  );
}

