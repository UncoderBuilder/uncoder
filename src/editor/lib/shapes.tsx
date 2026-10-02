import data from '../../../assets/data/shapes.json';

/** Shape divider library (assets/data/shapes.json, shared with Core\Shapes). */
export type ShapeDef = { label: string; invert?: boolean; paths: { d: string; o?: number }[] };
export const SHAPES = data as Record<string, ShapeDef>;

/** Twin of Shapes::svg(). Paths default to currentColor; the canvas recolours .uncoder-shape__fill. */
export function ShapeSvg({ name, invert = false }: { name: string; invert?: boolean }) {
  const shape = SHAPES[name];
  if (!shape) return null;
  const neg = invert && !!shape.invert;
  return (
    <svg viewBox="0 0 1000 100" preserveAspectRatio="none" aria-hidden focusable="false">
      {shape.paths.map((p, i) => (
        <path key={i} className="uncoder-shape__fill" fill="currentColor" fillRule={neg ? 'evenodd' : undefined} opacity={p.o} d={(neg ? 'M0 0H1000V100H0Z' : '') + p.d} />
      ))}
    </svg>
  );
}
