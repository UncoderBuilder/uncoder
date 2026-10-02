// Device (breakpoint) icons and labels shared by the top bar, responsive switches and device tabs.
import { config } from './config';

export const DEVICE_ICON: Record<string, string> = {
  desktop: 'monitor',
  widescreen: 'tv',
  laptop: 'laptop',
  tablet_extra: 'tablet',
  tablet: 'tablet',
  mobile_extra: 'smartphone',
  mobile: 'smartphone',
};

/** "Tablet (up to 1024px)", "Widescreen (1600px and up)", "Desktop". */
export function deviceLabel(id: string): string {
  const bp = config.breakpoints.find((b) => b.id === id);
  if (!bp) return id;
  if (bp.value == null) return bp.label;
  return bp.direction === 'min' ? `${bp.label} (${bp.value}px and up)` : `${bp.label} (up to ${bp.value}px)`;
}

export const deviceSuffix = (id: string) => (id === 'desktop' ? '' : `_${id}`);
