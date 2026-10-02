// Uploads files to the WordPress media library (pasted screenshots, images dropped on the canvas).
import { config } from './config';

export interface Uploaded {
  id: number;
  url: string;
  alt: string;
}

/** Uploads one file through the core REST API (the editor's nonce, the user's upload rights). */
export async function uploadFile(file: File): Promise<Uploaded> {
  const name = (file.name && file.name !== 'image.png' ? file.name : `pasted-${Date.now()}.${(file.type.split('/')[1] || 'png').replace('jpeg', 'jpg')}`).replace(/[^\w.\-]+/g, '-');
  const res = await fetch(`${config.rest.wp}media`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'X-WP-Nonce': config.rest.nonce,
      'Content-Type': file.type || 'application/octet-stream',
      'Content-Disposition': `attachment; filename="${name}"`,
    },
    body: file,
  });
  const data = await res.json().catch(() => null);
  if (!res.ok || !data?.id) throw new Error(data?.message || `Upload failed (${res.status})`);
  return { id: Number(data.id), url: String(data.source_url ?? ''), alt: String(data.alt_text ?? '') };
}

export const imageFiles = (list: FileList | File[] | null | undefined): File[] => Array.from(list ?? []).filter((f) => /^image\/(png|jpe?g|gif|webp|avif|svg\+xml)$/.test(f.type));
