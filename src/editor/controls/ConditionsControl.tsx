// Behaviour → Conditions: rule sets that decide whether the element is printed (Core\Element_Conditions).
// Any set can match; all rules of a set must match.
import { Fragment } from 'react';
import type { ControlProps } from './ControlRow';
import { useLookup } from './lookup';
import { TextInput } from '../ui/inputs';
import { Icon } from '../ui/Icon';
import { IconButton } from '../ui/primitives';
import { config } from '../lib/config';

export interface CondRule {
  key: string;
  op: string;
  value: string;
  name?: string;
}
type Sets = CondRule[][];

export const COND_KEYS: Record<string, string> = {
  login: 'Visitor',
  role: 'User role',
  date: 'Date',
  time: 'Time of day',
  weekday: 'Day of the week',
  post_type: 'Post type',
  page: 'Page',
  url_param: 'URL parameter',
  referrer: 'Came from (referrer)',
  cookie: 'Cookie',
  meta: 'Custom field',
  term: 'Category / tag / term',
  author: 'Author',
  parent: 'Parent page',
  featured_image: 'Featured image',
  comments: 'Comment count',
  archive: 'Kind of page',
  dynamic: 'Dynamic value',
  browser: 'Browser',
  os: 'Operating system',
  language: 'Language',
};
const ARCHIVE_KINDS: Array<[string, string]> = [
  ['front_page', 'Home page'],
  ['blog', 'Blog'],
  ['singular', 'Single post / page'],
  ['category', 'Category'],
  ['tag', 'Tag'],
  ['taxonomy', 'Other taxonomy'],
  ['author', 'Author'],
  ['date', 'Date archive'],
  ['search', 'Search results'],
  ['post_type_archive', 'Post type archive'],
  ['not_found', '404'],
];
const BROWSERS: Array<[string, string]> = [
  ['chrome', 'Chrome'],
  ['safari', 'Safari'],
  ['firefox', 'Firefox'],
  ['edge', 'Edge'],
  ['opera', 'Opera'],
  ['samsung', 'Samsung Internet'],
];
const SYSTEMS: Array<[string, string]> = [
  ['windows', 'Windows'],
  ['macos', 'macOS'],
  ['ios', 'iOS'],
  ['android', 'Android'],
  ['linux', 'Linux'],
];
const OPS: Record<string, string> = {
  is: 'is',
  is_not: 'is not',
  from: 'from',
  until: 'until',
  exists: 'exists',
  not_exists: 'does not exist',
  contains: 'contains',
  not_contains: 'does not contain',
  empty: 'is empty (direct visit)',
  not_empty: 'is not empty',
  greater: 'greater than',
  less: 'less than',
};
const NO_VALUE = ['exists', 'not_exists', 'empty', 'not_empty'];
const NAMED = ['url_param', 'cookie', 'meta', 'term', 'dynamic'];
const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
const FALLBACK_RULES: Record<string, string[]> = {
  login: ['is'],
  role: ['is', 'is_not'],
  date: ['from', 'until', 'is'],
  time: ['from', 'until'],
  weekday: ['is', 'is_not'],
  post_type: ['is', 'is_not'],
  page: ['is', 'is_not'],
  url_param: ['exists', 'not_exists', 'is', 'is_not', 'contains'],
  referrer: ['contains', 'not_contains', 'empty', 'not_empty'],
  cookie: ['exists', 'not_exists', 'is', 'contains'],
  meta: ['exists', 'not_exists', 'is', 'is_not', 'contains', 'greater', 'less'],
  term: ['is', 'is_not'],
  author: ['is', 'is_not'],
  parent: ['is', 'is_not', 'exists', 'not_exists'],
  featured_image: ['exists', 'not_exists'],
  comments: ['greater', 'less', 'is'],
  archive: ['is', 'is_not'],
  dynamic: ['exists', 'not_exists', 'is', 'is_not', 'contains', 'greater', 'less'],
  browser: ['is', 'is_not'],
  os: ['is', 'is_not'],
  language: ['is', 'is_not'],
};
/** Words for "exists" per rule, where the generic ones read oddly. */
const OP_LABEL: Record<string, Record<string, string>> = {
  parent: { exists: 'has a parent', not_exists: 'is top level' },
  featured_image: { exists: 'is set', not_exists: 'is missing' },
  dynamic: { exists: 'is not empty', not_exists: 'is empty' },
};
/** Dynamic tags usable in a rule: the ones that need no options. */
const plainTags = (): Array<[string, string]> =>
  Object.values(config.schema.dynamicTags ?? {})
    .filter((t: any) => !t.controls || !Object.keys(t.controls).length)
    .map((t: any) => [t.name, t.label ?? t.name]);

/** A readable one-liner of a rule, e.g. "URL parameter utm_source is newsletter". */
export function describeRule(r: CondRule, roles: Record<string, string> = {}, types: Record<string, string> = {}): string {
  const key = COND_KEYS[r.key] ?? r.key;
  if (r.key === 'login') return r.value === 'out' ? 'Visitor is logged out' : 'Visitor is logged in';
  const list = r.value.split(',').filter(Boolean);
  const named = (pairs: Array<[string, string]>) => list.map((v) => pairs.find(([k]) => k === v)?.[1] ?? v).join(', ');
  const value =
    r.key === 'weekday'
      ? list.map((d) => WEEKDAYS[Number(d) - 1]?.slice(0, 3) ?? d).join(', ')
      : r.key === 'role'
        ? list.map((v) => roles[v] ?? v).join(', ')
        : r.key === 'post_type'
          ? list.map((v) => types[v] ?? v).join(', ')
          : r.key === 'archive'
            ? named(ARCHIVE_KINDS)
            : r.key === 'browser'
              ? named(BROWSERS)
              : r.key === 'os'
                ? named(SYSTEMS)
                : r.value;
  const op = OP_LABEL[r.key]?.[r.op] ?? OPS[r.op] ?? r.op;
  return `${key}${r.name ? ` ${r.name}` : ''} ${op}${NO_VALUE.includes(r.op) ? '' : ` ${value}`}`;
}

export function ConditionsControl({ control, value, onChange }: ControlProps<Sets>) {
  const sets: Sets = Array.isArray(value) ? value : [];
  const rules: Record<string, string[]> = (control as any).rules ?? FALLBACK_RULES;
  const roles: Record<string, string> = (control as any).roles ?? {};
  const types: Record<string, string> = (control as any).post_types ?? {};
  const commit = (next: Sets) => {
    const clean = next.map((s) => s.filter(Boolean)).filter((s) => s.length);
    onChange(clean.length ? clean : undefined);
  };
  const setRule = (si: number, ri: number, patch: Partial<CondRule>) => commit(sets.map((s, i) => (i !== si ? s : s.map((r, j) => (j !== ri ? r : { ...r, ...patch })))));
  const blank = (): CondRule => ({ key: 'login', op: 'is', value: 'in' });

  return (
    <div className="uncoder-ui-conds">
      {sets.map((set, si) => (
        <Fragment key={si}>
          {si > 0 && <div className="uncoder-ui-conds__or">or</div>}
          <div className="uncoder-ui-conds__set">
            {set.map((rule, ri) => (
              <Fragment key={ri}>
                {ri > 0 && <div className="uncoder-ui-conds__and">and</div>}
                <div className="uncoder-ui-conds__rule">
                  <select
                    className="uncoder-ui-select uncoder-ui-conds__key"
                    aria-label="Condition"
                    value={rule.key}
                    onChange={(e) => {
                      const key = e.currentTarget.value;
                      const op = (rules[key] ?? ['is'])[0];
                      setRule(si, ri, { key, op, value: key === 'login' ? 'in' : key === 'weekday' ? '1,2,3,4,5' : '', name: NAMED.includes(key) ? rule.name ?? '' : undefined });
                    }}
                  >
                    {Object.entries(COND_KEYS).map(([k, label]) => (
                      <option key={k} value={k}>
                        {label}
                      </option>
                    ))}
                  </select>
                  <IconButton icon="x" label="Remove this rule" size={13} onClick={() => commit(sets.map((s, i) => (i !== si ? s : s.filter((_, j) => j !== ri))))} />
                  {rule.key === 'term' && (
                    <select className="uncoder-ui-select" aria-label="Taxonomy" value={rule.name ?? ''} onChange={(e) => setRule(si, ri, { name: e.currentTarget.value })}>
                      <option value="">Taxonomy…</option>
                      {Object.entries(((control as any).taxonomies ?? { category: 'Category', post_tag: 'Tag' }) as Record<string, string>).map(([k, label]) => (
                        <option key={k} value={k}>
                          {label}
                        </option>
                      ))}
                    </select>
                  )}
                  {rule.key === 'dynamic' && (
                    <select className="uncoder-ui-select" aria-label="Dynamic tag" value={rule.name ?? ''} onChange={(e) => setRule(si, ri, { name: e.currentTarget.value })}>
                      <option value="">Dynamic tag…</option>
                      {plainTags().map(([k, label]) => (
                        <option key={k} value={k}>
                          {label}
                        </option>
                      ))}
                    </select>
                  )}
                  {NAMED.includes(rule.key) && rule.key !== 'term' && rule.key !== 'dynamic' && (
                    <TextInput
                      value={rule.name ?? ''}
                      placeholder={rule.key === 'url_param' ? 'Parameter, e.g. utm_source' : rule.key === 'cookie' ? 'Cookie name' : 'Field name, e.g. price'}
                      onChange={(v) => setRule(si, ri, { name: v })}
                      aria-label="Name"
                    />
                  )}
                  <div className="uncoder-ui-conds__opval">
                    {(rules[rule.key] ?? []).length > 1 && (
                      <select className="uncoder-ui-select" aria-label="Operator" value={rule.op} onChange={(e) => setRule(si, ri, { op: e.currentTarget.value })}>
                        {(rules[rule.key] ?? []).map((op) => (
                          <option key={op} value={op}>
                            {OP_LABEL[rule.key]?.[op] ?? OPS[op] ?? op}
                          </option>
                        ))}
                      </select>
                    )}
                    {!NO_VALUE.includes(rule.op) && <RuleValue rule={rule} roles={roles} types={types} languages={(control as any).languages ?? {}} onChange={(v) => setRule(si, ri, { value: v })} />}
                  </div>
                </div>
              </Fragment>
            ))}
            <button type="button" className="uncoder-ui-conds__add" onClick={() => commit(sets.map((s, i) => (i !== si ? s : [...s, blank()])))}>
              <Icon name="plus" size={12} /> And
            </button>
          </div>
        </Fragment>
      ))}
      <button type="button" className="uncoder-ui-conds__add is-set" onClick={() => commit([...sets, [blank()]])}>
        <Icon name="plus" size={12} /> {sets.length ? 'Or another set' : 'Add a condition'}
      </button>
    </div>
  );
}

function RuleValue({ rule, roles, types, languages, onChange }: { rule: CondRule; roles: Record<string, string>; types: Record<string, string>; languages: Record<string, string>; onChange: (v: string) => void }) {
  const pages = useLookup(rule.key === 'page' || rule.key === 'parent' ? 'posts' : null, '', rule.key === 'parent' ? '&type=page' : '&type=any');
  const select = (options: Array<[string, string]>) => (
    <select className="uncoder-ui-select" aria-label="Value" value={rule.value} onChange={(e) => onChange(e.currentTarget.value)}>
      {!options.some(([v]) => v === rule.value) && <option value={rule.value}>{rule.value ? rule.value : 'Choose…'}</option>}
      {options.map(([v, label]) => (
        <option key={v} value={v}>
          {label}
        </option>
      ))}
    </select>
  );
  // "Is one of": several values as toggle chips, stored as a comma list.
  const chips = (options: Array<[string, string]>) => {
    const on = rule.value.split(',').filter(Boolean);
    return (
      <div className="uncoder-ui-conds__chips" role="group" aria-label="Values">
        {options.map(([v, label]) => (
          <button
            key={v}
            type="button"
            className="uncoder-ui-chip"
            aria-pressed={on.includes(v)}
            onClick={() => onChange((on.includes(v) ? on.filter((x) => x !== v) : [...on, v]).join(','))}
          >
            {label}
          </button>
        ))}
      </div>
    );
  };
  switch (rule.key) {
    case 'login':
      return select([
        ['in', 'logged in'],
        ['out', 'logged out'],
      ]);
    case 'role':
      return chips(Object.entries(roles));
    case 'post_type':
      return chips(Object.entries(types));
    case 'weekday':
      return chips(WEEKDAYS.map((d, i) => [String(i + 1), d.slice(0, 3)]));
    case 'page':
    case 'parent':
      return select((pages ?? []).map((p) => [p.value, p.label]));
    case 'archive':
      return chips(ARCHIVE_KINDS);
    case 'browser':
      return chips(BROWSERS);
    case 'os':
      return chips(SYSTEMS);
    case 'language':
      return Object.keys(languages).length ? chips(Object.entries(languages)) : <TextInput value={rule.value} placeholder="Codes, e.g. en, de" onChange={onChange} aria-label="Languages" />;
    case 'comments':
      return <input type="number" min={0} className="uncoder-ui-input" aria-label="Number of comments" value={rule.value} onChange={(e) => onChange(e.currentTarget.value)} />;
    case 'term':
      return <TextInput value={rule.value} placeholder="Slugs or IDs, comma separated" onChange={onChange} aria-label="Terms" />;
    case 'author':
      return <TextInput value={rule.value} placeholder="Usernames or user IDs" onChange={onChange} aria-label="Authors" />;
    case 'date': {
      // A date, optionally with a time (then compared to the minute).
      const [day = '', time = ''] = rule.value.split(' ');
      return (
        <div className="uncoder-ui-conds__date">
          <input type="date" className="uncoder-ui-input" aria-label="Date" value={day} onChange={(e) => onChange(e.currentTarget.value + (time ? ` ${time}` : ''))} />
          <input type="time" className="uncoder-ui-input" aria-label="Time (optional)" value={time} onChange={(e) => onChange(day + (e.currentTarget.value ? ` ${e.currentTarget.value}` : ''))} />
        </div>
      );
    }
    case 'time':
      return <input type="time" className="uncoder-ui-input" aria-label="Time" value={rule.value} onChange={(e) => onChange(e.currentTarget.value)} />;
    default:
      return <TextInput value={rule.value} placeholder={rule.key === 'referrer' ? 'e.g. google.' : 'Value'} onChange={onChange} aria-label="Value" />;
  }
}
