import { useMemo, useRef, useState } from 'react';
import { classControls } from '@shared/kit';
import type { KitClass, Settings } from '@shared/types';
import { config, schemaOf } from '../lib/config';
import { updateSettings, useDoc } from '../store/doc';
import { saveKit, updateKit, useKit } from '../store/kit';
import { toast } from '../store/ui';
import { Icon } from '../ui/Icon';
import { Popover } from '../ui/Popover';
import { Button } from '../ui/primitives';

const DEVICE_SUFFIX = /_(widescreen|laptop|tablet_extra|tablet|mobile_extra|mobile)$/;
const slug = (s: string) =>
  s
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 40) || 'class';

/** The element's settings a class can hold (everything that becomes CSS). */
function styleKeys(type: string, settings: Settings): string[] {
  const schema = schemaOf(type);
  if (!schema) return [];
  const allowed = classControls(schema);
  return Object.keys(settings).filter((k) => allowed[k] || allowed[k.replace(DEVICE_SUFFIX, '')]);
}

async function persist(recipe: (list: KitClass[]) => KitClass[], message: string) {
  updateKit((k) => ({ ...k, classes: recipe(k.classes ?? []) }));
  try {
    await saveKit();
    toast(message, 'success');
  } catch (e) {
    toast(e instanceof Error ? e.message : 'Could not save the class.', 'error');
  }
}

type Mode = { kind: 'list' } | { kind: 'create' } | { kind: 'manage'; id: string } | { kind: 'rename'; id: string } | { kind: 'delete'; id: string };

/**
 * Global classes of the selected element (Style tab): apply shared styles, turn the element's own
 * styles into a class, or push them into an existing class. Classes live in the Design System.
 */
export function ClassesBar({ id }: { id: string }) {
  const node = useDoc((s) => s.doc.nodes[id]);
  const classes = useKit((s) => s.kit.classes ?? []);
  const anchor = useRef<HTMLElement | null>(null);
  const [mode, setMode] = useState<Mode | null>(null);
  const [name, setName] = useState('');
  const applied: string[] = Array.isArray(node?.settings._classes) ? node.settings._classes : [];
  const mine = useMemo(() => classes.filter((c) => c.type === node?.type), [classes, node?.type]);
  if (!node) return null;
  const own = styleKeys(node.type, node.settings);
  const byId = (cid: string) => classes.find((c) => c.id === cid);

  const close = () => {
    setMode(null);
    setName('');
  };
  const apply = (cid: string) => {
    updateSettings(id, { _classes: [...applied, cid] }, { label: 'Add class' });
    close();
  };
  const unapply = (cid: string) => {
    const next = applied.filter((x) => x !== cid);
    updateSettings(id, { _classes: next.length ? next : undefined }, { label: 'Remove class' });
    close();
  };
  // Moves the element's own styles into a class (the element then looks the same, styled by the class).
  const takeOwn = (): Settings => Object.fromEntries(own.map((k) => [k, node.settings[k]]));
  const clearOwn = (extra: Settings) => updateSettings(id, { ...Object.fromEntries(own.map((k) => [k, undefined])), ...extra }, { label: 'Move styles to class' });

  const create = async () => {
    const label = name.trim();
    if (!label) return;
    let cid = slug(label);
    for (let i = 2; classes.some((c) => c.id === cid); i++) cid = `${slug(label)}-${i}`;
    const cls: KitClass = { id: cid, name: label, type: node.type, settings: takeOwn() };
    clearOwn({ _classes: [...applied, cid] });
    close();
    await persist((list) => [...list, cls], `Class “${label}” created`);
  };
  const updateFrom = async (cid: string) => {
    const cls = byId(cid);
    if (!cls) return;
    const merged = { ...cls.settings, ...takeOwn() };
    clearOwn({});
    close();
    await persist((list) => list.map((c) => (c.id === cid ? { ...c, settings: merged } : c)), `“${cls.name}” updated everywhere`);
  };
  const rename = async (cid: string) => {
    const label = name.trim();
    if (!label) return;
    close();
    await persist((list) => list.map((c) => (c.id === cid ? { ...c, name: label } : c)), 'Class renamed');
  };
  const remove = async (cid: string) => {
    const cls = byId(cid);
    unapply(cid);
    await persist((list) => list.filter((c) => c.id !== cid), `Class “${cls?.name ?? cid}” deleted`);
  };

  const managed = mode && 'id' in mode ? byId(mode.id) : undefined;
  // Classes live in the Design System: creating or changing one needs edit_theme_options (POST /kit).
  const canKit = config.user.caps.edit_theme;

  return (
    <div className="uncoder-ui-classes">
      <span className="uncoder-ui-classes__label">Classes</span>
      <div className="uncoder-ui-classes__chips">
        {applied.map((cid) => {
          const cls = byId(cid);
          return (
            <button key={cid} type="button" className={`uncoder-ui-classchip${cls ? '' : ' is-missing'}`} onClick={(e) => { anchor.current = e.currentTarget; setMode({ kind: 'manage', id: cid }); }} title={cls ? `${cls.name} — shared by every element using it` : 'This class was deleted'}>
              <Icon name="hash" size={11} />
              {cls?.name ?? cid}
            </button>
          );
        })}
        <button
          type="button"
          className="uncoder-ui-classchip uncoder-ui-classchip--add"
          aria-label="Add a class"
          onClick={(e) => {
            anchor.current = e.currentTarget;
            setMode({ kind: 'list' });
          }}
        >
          <Icon name="plus" size={12} />
          {applied.length ? '' : 'Add class'}
        </button>
      </div>
      <Popover anchor={anchor} open={!!mode} onClose={close} width={250} placement="left-start" label="Global classes">
        <div className="uncoder-ui-classpop">
          {mode?.kind === 'list' && (
            <>
              <div className="uncoder-ui-classpop__title">Classes for {schemaOf(node.type)?.title ?? node.type}</div>
              {mine.filter((c) => !applied.includes(c.id)).length === 0 && <p className="uncoder-ui-classpop__hint">No other classes for this element type yet.</p>}
              {mine
                .filter((c) => !applied.includes(c.id))
                .map((c) => (
                  <button key={c.id} type="button" className="uncoder-ui-classpop__item" onClick={() => apply(c.id)}>
                    <Icon name="hash" size={12} /> {c.name}
                    <span className="uncoder-ui-classpop__meta">{Object.keys(c.settings ?? {}).length} styles</span>
                  </button>
                ))}
              {canKit && (
                <>
                  <div className="uncoder-ui-classpop__sep" />
                  <button type="button" className="uncoder-ui-classpop__item" onClick={() => setMode({ kind: 'create' })} disabled={!own.length} title={own.length ? undefined : 'Style this element first'}>
                    <Icon name="sparkles" size={12} /> Create class from this style
                  </button>
                </>
              )}
            </>
          )}
          {mode?.kind === 'create' && (
            <form onSubmit={(e) => (e.preventDefault(), create())}>
              <div className="uncoder-ui-classpop__title">New class</div>
              <p className="uncoder-ui-classpop__hint">Moves this element’s {own.length} style setting{own.length === 1 ? '' : 's'} into a class you can reuse on other {schemaOf(node.type)?.title ?? node.type} elements.</p>
              <input className="uncoder-ui-input" autoFocus placeholder="Card, Eyebrow, Primary CTA…" value={name} onChange={(e) => setName(e.currentTarget.value)} aria-label="Class name" />
              <div className="uncoder-ui-classpop__actions">
                <Button size="sm" variant="ghost" onClick={close}>
                  Cancel
                </Button>
                <Button size="sm" variant="primary" type="submit" disabled={!name.trim()}>
                  Create
                </Button>
              </div>
            </form>
          )}
          {mode?.kind === 'manage' && (
            <>
              <div className="uncoder-ui-classpop__title">{managed?.name ?? mode.id}</div>
              {managed && canKit && (
                <button type="button" className="uncoder-ui-classpop__item" onClick={() => updateFrom(mode.id)} disabled={!own.length} title={own.length ? undefined : 'This element has no own styles to push'}>
                  <Icon name="upload" size={12} /> Update class from this element{own.length ? ` (${own.length})` : ''}
                </button>
              )}
              <button type="button" className="uncoder-ui-classpop__item" onClick={() => unapply(mode.id)}>
                <Icon name="unlink" size={12} /> Remove from this element
              </button>
              {managed && canKit && (
                <>
                  <button type="button" className="uncoder-ui-classpop__item" onClick={() => (setName(managed.name), setMode({ kind: 'rename', id: mode.id }))}>
                    <Icon name="pencil" size={12} /> Rename
                  </button>
                  <div className="uncoder-ui-classpop__sep" />
                  <button type="button" className="uncoder-ui-classpop__item is-danger" onClick={() => setMode({ kind: 'delete', id: mode.id })}>
                    <Icon name="trash-2" size={12} /> Delete class…
                  </button>
                </>
              )}
            </>
          )}
          {mode?.kind === 'rename' && (
            <form onSubmit={(e) => (e.preventDefault(), rename(mode.id))}>
              <div className="uncoder-ui-classpop__title">Rename class</div>
              <input className="uncoder-ui-input" autoFocus value={name} onChange={(e) => setName(e.currentTarget.value)} aria-label="Class name" />
              <div className="uncoder-ui-classpop__actions">
                <Button size="sm" variant="ghost" onClick={close}>
                  Cancel
                </Button>
                <Button size="sm" variant="primary" type="submit" disabled={!name.trim()}>
                  Rename
                </Button>
              </div>
            </form>
          )}
          {mode?.kind === 'delete' && (
            <>
              <div className="uncoder-ui-classpop__title">Delete “{managed?.name}”?</div>
              <p className="uncoder-ui-classpop__hint">Every element using it loses these styles, on every page. Its own settings stay.</p>
              <div className="uncoder-ui-classpop__actions">
                <Button size="sm" variant="ghost" onClick={close}>
                  Cancel
                </Button>
                <Button size="sm" variant="danger" onClick={() => remove(mode.id)}>
                  Delete
                </Button>
              </div>
            </>
          )}
        </div>
      </Popover>
    </div>
  );
}
