import { config } from './config';

// Lucide icon data (name → inner SVG). Loaded once before the editor mounts; used for the
// editor chrome, the widget panel and the icon picker.
let icons: Record<string, string> = {};
let tags: Record<string, string[]> | null = null;

export async function loadIcons(): Promise<void> {
  const res = await fetch(config.urls.icons, { credentials: 'same-origin' });
  icons = await res.json();
}

export async function loadIconTags(): Promise<Record<string, string[]>> {
  if (tags) return tags;
  const res = await fetch(config.urls.icons.replace('lucide.json', 'lucide-tags.json'), { credentials: 'same-origin' });
  tags = await res.json();
  return tags!;
}

export function iconBody(name: string): string {
  return icons[name] ?? icons['circle-help'] ?? '';
}

export function hasIcon(name: string): boolean {
  return name in icons;
}

export function iconNames(): string[] {
  return Object.keys(icons);
}

export function iconSvg(name: string, size = 16, strokeWidth = 2): string {
  return `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="${strokeWidth}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${iconBody(name)}</svg>`;
}
