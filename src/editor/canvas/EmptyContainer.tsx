import { useDoc } from '../store/doc';
import { useUi } from '../store/ui';
import { iconSvg } from '../lib/icons';

/** Drop area shown inside an empty container in the canvas. */
export function EmptyContainer({ id }: { id: string }) {
  const isDropTarget = useUi((s) => s.drop?.parent === id);
  const nestedSlot = useDoc((s) => {
    const parent = s.doc.nodes[id]?.parent;
    return parent ? s.doc.nodes[parent]?.type !== 'container' : false;
  });
  return (
    <div className={`uncoder-ui-empty-con${isDropTarget ? ' is-target' : ''}${nestedSlot ? ' is-slot' : ''}`} data-uncoder-ui-empty={id}>
      <button
        type="button"
        className="uncoder-ui-empty-con__add"
        aria-label="Add element"
        data-uncoder-ui-add={id}
        dangerouslySetInnerHTML={{ __html: iconSvg('plus', 16, 2.2) }}
      />
      <span className="uncoder-ui-empty-con__hint">Drop widgets here</span>
    </div>
  );
}
