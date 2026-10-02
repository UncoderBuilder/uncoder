// One display-condition row (include/exclude + rule + details) and its conversions. Used by the Theme
// Builder's conditions drawer, Custom Code and the editor's template settings. Styles: conditions.css.
import { IconButton, Segmented } from '@editor/ui/primitives';
import type { Condition, TemplatesMeta } from '../lib/api';
import { LookupSelect, type Picked } from '../ui/LookupSelect';
import { RULE_GROUPS, RULE_LABELS } from './common';

export interface Row {
  key: number;
  type: 'include' | 'exclude';
  rule: string;
  post_type: string;
  taxonomy: string;
  ids: Picked[];
}

let seq = 0;

export const toRow = (c: Condition): Row => ({
  key: ++seq,
  type: c.type === 'exclude' ? 'exclude' : 'include',
  rule: c.rule,
  post_type: c.post_type ?? '',
  taxonomy: c.taxonomy ?? '',
  ids: (c.ids ?? []).map((id) => ({ id, label: c.labels?.[String(id)] ?? `#${id}` })),
});

export const toCondition = (r: Row, meta: TemplatesMeta): Condition => {
  const fields = meta.rules[r.rule]?.fields ?? [];
  const c: Condition = { type: r.type, rule: r.rule };
  if (fields.includes('post_type') && r.post_type) c.post_type = r.post_type;
  if (fields.includes('taxonomy') && r.taxonomy) c.taxonomy = r.taxonomy;
  if (fields.includes('ids') && r.ids.length) c.ids = r.ids.map((p) => p.id);
  return c;
};

/** A fresh row: the first one includes the entire site, later ones exclude singular items. */
export const newRow = (first: boolean): Row => ({ key: ++seq, type: first ? 'include' : 'exclude', rule: first ? 'general' : 'singular', post_type: '', taxonomy: '', ids: [] });

export function ConditionRow({ row, index, meta, onChange, onRemove }: { row: Row; index: number; meta: TemplatesMeta; onChange: (p: Partial<Row>) => void; onRemove: () => void }) {
  const fields = meta.rules[row.rule]?.fields ?? [];
  const n = index + 1;
  const ptLabel = meta.postTypes.find((p) => p.name === row.post_type)?.label;

  let detail: React.ReactNode = null;
  if (row.rule === 'singular') {
    detail = (
      <>
        <select className="uncoder-ui-select" value={row.post_type} aria-label={`Condition ${n}: post type`} onChange={(e) => onChange({ post_type: e.currentTarget.value, ids: [] })}>
          <option value="">Any post type</option>
          {meta.postTypes.map((p) => (
            <option key={p.name} value={p.name}>
              {p.label}
            </option>
          ))}
        </select>
        <LookupSelect
          source="posts"
          filter={row.post_type || undefined}
          value={row.ids}
          onChange={(ids) => onChange({ ids })}
          label={`Condition ${n}: specific items`}
          placeholder={row.post_type ? `All ${ptLabel?.toLowerCase() ?? 'items'} — or pick specific ones…` : 'All items — or pick specific ones…'}
        />
      </>
    );
  } else if (row.rule === 'in_term') {
    const tax = row.taxonomy || 'category';
    detail = (
      <>
        <select className="uncoder-ui-select" value={tax} aria-label={`Condition ${n}: taxonomy`} onChange={(e) => onChange({ taxonomy: e.currentTarget.value, ids: [] })}>
          {meta.taxonomies.map((t) => (
            <option key={t.name} value={t.name}>
              {t.label}
            </option>
          ))}
        </select>
        <LookupSelect source="terms" filter={tax} value={row.ids} onChange={(ids) => onChange({ ids })} label={`Condition ${n}: terms`} placeholder="Any term — or pick specific ones…" />
      </>
    );
  } else if (row.rule === 'child_of') {
    detail = <LookupSelect source="posts" filter="page" value={row.ids} onChange={(ids) => onChange({ ids })} label={`Condition ${n}: parent pages`} placeholder="Any parent — or pick parent pages…" />;
  } else if (row.rule === 'by_author' || row.rule === 'author') {
    detail = meta.canListUsers ? (
      <LookupSelect source="users" value={row.ids} onChange={(ids) => onChange({ ids })} label={`Condition ${n}: authors`} placeholder="Any author — or pick authors…" />
    ) : (
      <span className="uncoder-ui-muted">Applies to every author.</span>
    );
  } else if (row.rule === 'archive') {
    const value = row.taxonomy ? `tax:${row.taxonomy}` : row.post_type ? `pt:${row.post_type}` : '';
    detail = (
      <>
        <select
          className="uncoder-ui-select"
          value={value}
          aria-label={`Condition ${n}: archive of`}
          onChange={(e) => {
            const v = e.currentTarget.value;
            onChange({ post_type: v.startsWith('pt:') ? v.slice(3) : '', taxonomy: v.startsWith('tax:') ? v.slice(4) : '', ids: [] });
          }}
        >
          <option value="">All archives</option>
          <optgroup label="Post type archives">
            {meta.postTypes
              .filter((p) => p.hasArchive)
              .map((p) => (
                <option key={p.name} value={`pt:${p.name}`}>
                  {p.label}
                </option>
              ))}
          </optgroup>
          <optgroup label="Taxonomy archives">
            {meta.taxonomies.map((t) => (
              <option key={t.name} value={`tax:${t.name}`}>
                {t.label}
              </option>
            ))}
          </optgroup>
        </select>
        {row.taxonomy && <LookupSelect source="terms" filter={row.taxonomy} value={row.ids} onChange={(ids) => onChange({ ids })} label={`Condition ${n}: terms`} placeholder="Every term — or pick specific ones…" />}
      </>
    );
  }

  return (
    <li className={`uncoder-ui-cond uncoder-ui-cond--${row.type}`}>
      <div className="uncoder-ui-cond__main">
        <Segmented
          size="md"
          ariaLabel={`Condition ${n}: include or exclude`}
          value={row.type}
          onChange={(v) => onChange({ type: v as Row['type'] })}
          options={[
            { value: 'include', label: 'Include' },
            { value: 'exclude', label: 'Exclude' },
          ]}
        />
        <select
          className="uncoder-ui-select uncoder-ui-cond__rule"
          value={row.rule}
          aria-label={`Condition ${n}: where`}
          onChange={(e) => onChange({ rule: e.currentTarget.value, post_type: '', taxonomy: e.currentTarget.value === 'in_term' ? 'category' : '', ids: [] })}
        >
          {RULE_GROUPS.map((g) => (
            <optgroup key={g.label} label={g.label}>
              {g.rules
                .filter((r) => meta.rules[r])
                .map((r) => (
                  <option key={r} value={r}>
                    {RULE_LABELS[r] ?? meta.rules[r].label}
                  </option>
                ))}
            </optgroup>
          ))}
        </select>
        <IconButton icon="trash-2" tone="danger" label={`Remove condition ${n}`} onClick={onRemove} />
      </div>
      {detail && fields.length > 0 && <div className="uncoder-ui-cond__detail">{detail}</div>}
    </li>
  );
}
