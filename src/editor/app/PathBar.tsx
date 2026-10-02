import { useEffect, useReducer } from 'react';
import { schemaOf } from '../lib/config';
import { ancestors } from '../lib/tree';
import { useDoc } from '../store/doc';
import { select, useUi } from '../store/ui';
import { elementFor, onGeometry } from '../canvas/frame';
import { pick } from './smart';

/** Where the selection sits in the tree (every step clickable, hover highlights it) and its rendered size. */
export function PathBar() {
  const selected = useUi((s) => s.selected[0] ?? null);
  const doc = useDoc((s) => s.doc);
  const [, tick] = useReducer((n: number) => n + 1, 0);
  useEffect(() => onGeometry(() => tick()), []);

  const node = selected ? doc.nodes[selected] : null;
  const trail = node ? [...ancestors(doc, node.id).reverse(), node.id] : [];
  const name = (id: string) => doc.nodes[id]?.label || schemaOf(doc.nodes[id]?.type)?.title || '';
  const el = node ? elementFor(node.id) : null;
  const rect = el?.getBoundingClientRect();
  const tag = el ? (el.querySelector('h1,h2,h3,h4,h5,h6,p,a,button,img,ul,ol,form,video') as HTMLElement | null)?.tagName.toLowerCase() : '';

  return (
    <nav className="uncoder-ui-pathbar uncoder-ui-island" aria-label="Selection path">
      <button type="button" className="uncoder-ui-pathbar__step" onClick={() => select(null)}>
        Page
      </button>
      {trail.map((id, i) => (
        <span key={id} className="uncoder-ui-pathbar__item">
          <span className="uncoder-ui-pathbar__sep" aria-hidden>
            /
          </span>
          <button
            type="button"
            className={`uncoder-ui-pathbar__step${i === trail.length - 1 ? ' is-current' : ''}`}
            aria-current={i === trail.length - 1 ? 'true' : undefined}
            onClick={() => pick(id)}
            onPointerEnter={() => useUi.setState({ hovered: id })}
            onPointerLeave={() => useUi.setState({ hovered: null })}
          >
            {name(id)}
          </button>
        </span>
      ))}
      {rect && (
        <span className="uncoder-ui-pathbar__size">
          {tag && node && !schemaOf(node.type)?.container ? `${tag} · ` : ''}
          {Math.round(rect.width)} × {Math.round(rect.height)}
        </span>
      )}
    </nav>
  );
}
