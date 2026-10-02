// How front-end code finds an element's id without a data-id attribute: the renderer prints the type class
// first and the element's own class uncoder-{id} second (Renderer::identity()).

const ID_CLASS = /^uncoder-([a-z][a-z0-9]{2,31})$/;

/** The element id of an element root, or null for anything else (decoration layers, inner blocks…). */
export function elementId(el: Element): string | null {
  const m = ID_CLASS.exec(el.classList[1] ?? '');
  return m && el.classList[0]?.startsWith('uncoder-') ? m[1] : null;
}
