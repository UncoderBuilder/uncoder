// Live sync: picks up changes other clients (an AI over MCP, another tab) save while the editor is open.
// Polls a tiny state endpoint; a new revision is applied as one undoable step when there are no
// unsaved local edits, otherwise the user decides.
import type { ElementNode, Kit, Settings } from '@shared/types';
import { api } from '../lib/api';
import { config } from '../lib/config';
import { fromTree } from '../lib/tree';
import { commit, isDirty, undo, useDoc } from '../store/doc';
import { useKit } from '../store/kit';
import { toast, useUi } from '../store/ui';

interface DocState {
  rev: string;
  by: string;
  self: boolean;
  kit: string;
}

interface DocPayload {
  rev: string;
  title: string;
  status: string;
  elements: ElementNode[];
  pageSettings: Settings;
}

const BASE_INTERVAL = 4000;
const MAX_INTERVAL = 60000;

let knownRev = config.post.rev ?? '';
let knownKit = config.kitVersion ?? '';
let offeredRev = '';
let offeredKit = '';
let interval = BASE_INTERVAL;
let timer: number | null = null;
let busy = false;

/** Called after the editor itself saved, so its own revision is not treated as a remote change. */
export function setKnownRev(rev: string | undefined): void {
  if (rev) knownRev = rev;
}

export function setKnownKit(version: string | undefined): void {
  if (version) knownKit = version;
}

function label(by: string, self: boolean): string {
  if (!by || by === 'editor') return self ? 'another tab' : 'another editor';
  return by;
}

async function applyRemote(by: string): Promise<void> {
  const payload = await api<DocPayload>(`documents/${config.post.id}`);
  const next = fromTree(payload.elements ?? []);
  commit(`Changes by ${by}`, (draft) => {
    draft.nodes = next.nodes;
    draft.root = next.root;
  });
  useDoc.setState((s) => ({
    savedVersion: s.version,
    title: payload.title ?? s.title,
    status: payload.status ?? s.status,
    pageSettings: (payload.pageSettings as Settings) ?? s.pageSettings,
  }));
  knownRev = payload.rev || knownRev;
  // Drop selections that no longer exist.
  const nodes = useDoc.getState().doc.nodes;
  const ui = useUi.getState();
  const selected = ui.selected.filter((id) => nodes[id]);
  if (selected.length !== ui.selected.length) useUi.setState({ selected });
}

async function applyKit(): Promise<void> {
  const kit = await api<Kit>('kit');
  useKit.setState({ kit, dirty: false });
}

async function tick(): Promise<void> {
  if (busy || document.visibilityState !== 'visible' || useUi.getState().saving) return;
  busy = true;
  try {
    const state = await api<DocState>(`documents/${config.post.id}/state`);
    interval = BASE_INTERVAL;

    if (state.rev && state.rev !== knownRev && !useUi.getState().saving) {
      const who = label(state.by, state.self);
      if (!isDirty()) {
        await applyRemote(who);
        toast(`Updated by ${who}`, 'info', { label: 'Undo', run: () => undo() }, 6000);
      } else if (offeredRev !== state.rev) {
        offeredRev = state.rev;
        toast(`${who} changed this page. Load their version? Your unsaved changes will be replaced.`, 'warning', {
          label: 'Load',
          run: () => {
            applyRemote(who).catch(() => toast('Could not load the changes.', 'error'));
          },
        }, 0);
      }
    }

    if (state.kit && state.kit !== knownKit) {
      if (!useKit.getState().dirty) {
        await applyKit();
        knownKit = state.kit;
      } else if (offeredKit !== state.kit) {
        offeredKit = state.kit;
        toast('The Design System was changed elsewhere. Save yours to keep your edits, or reload the page to load the new kit.', 'warning', undefined, 9000);
      }
    }
  } catch {
    interval = Math.min(MAX_INTERVAL, interval * 2);
  } finally {
    busy = false;
  }
}

function schedule(): void {
  if (timer) window.clearTimeout(timer);
  timer = window.setTimeout(async () => {
    await tick();
    schedule();
  }, interval);
}

export function startLiveSync(): void {
  if (timer) return;
  schedule();
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') tick();
  });
}
