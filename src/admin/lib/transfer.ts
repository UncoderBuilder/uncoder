import { api } from './api';
import { toast, toastError } from './toast';

export interface ExportFile {
  format: 'uncoder-export';
  version: number;
  exported: string;
  site: string;
  kit?: Record<string, unknown>;
  items: Array<{ id: number; kind: 'template' | 'page'; type: string; title: string; status: string; elements: unknown[] }>;
}

export interface ImportResult {
  created: Array<{ id: number; title: string; kind: 'template' | 'page'; type: string; edit: string }>;
  kit: boolean;
  images: number;
  warnings: string[];
}

const slug = (s: string) =>
  s
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
    .slice(0, 40) || 'export';

/** Asks the server for an export and saves it as a .json download. */
export async function downloadExport(body: { ids?: number[]; all_templates?: boolean; kit?: boolean }, name: string): Promise<void> {
  try {
    const data = await api<ExportFile>('transfer/export', { body });
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `uncoder-${slug(name)}-${new Date().toISOString().slice(0, 10)}.json`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    const n = data.items.length;
    toast(`Exported ${n ? `${n} item${n === 1 ? '' : 's'}` : ''}${n && data.kit ? ' and ' : ''}${data.kit ? 'the Design System' : ''}`);
  } catch (e) {
    toastError(e);
  }
}

/** Reads and validates an export file picked by the user. */
export async function readExportFile(file: File): Promise<ExportFile> {
  if (file.size > 20 * 1024 * 1024) throw new Error('The file is larger than 20 MB.');
  let data: any;
  try {
    data = JSON.parse(await file.text());
  } catch {
    throw new Error('This file is not valid JSON.');
  }
  if (!data || data.format !== 'uncoder-export' || !Array.isArray(data.items)) throw new Error('This is not an Uncoder export file.');
  return data as ExportFile;
}
