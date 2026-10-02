import { memo } from 'react';
import { iconBody } from '../lib/icons';

interface Props {
  name: string;
  size?: number;
  stroke?: number;
  className?: string;
  title?: string;
}

/** Lucide icon from the bundled icon data (trusted, generated at build time). */
export const Icon = memo(function Icon({ name, size = 16, stroke = 2, className, title }: Props) {
  return (
    <svg
      className={'uncoder-ui-icon' + (className ? ' ' + className : '')}
      width={size}
      height={size}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={stroke}
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden={title ? undefined : true}
      role={title ? 'img' : undefined}
      aria-label={title}
      dangerouslySetInnerHTML={{ __html: iconBody(name) }}
    />
  );
});
