// Crash recovery. If the editor UI throws while rendering, the boundary below keeps the page's unsaved changes in
// this browser and shows a way out instead of a blank screen; the next time the page opens in the editor, a toast
// offers to put them back. Saved versions live in WordPress revisions (History panel); this covers the unsaved part.
import { Component, type ReactNode } from 'react';
import type { ElementNode } from '@shared/types';
import { config } from '../lib/config';
import { fromTree } from '../lib/tree';
import { commit, getTree, isDirty } from '../store/doc';
import { toast } from '../store/ui';

const key = () => `uncoder-ui-recovery-${config.post.id}`;
const MAX_AGE = 7 * 24 * 3600 * 1000;

interface Stash {
  time: number;
  elements: ElementNode[];
}

/** Keeps the unsaved page in localStorage. Returns whether there was anything to keep. */
export function stashRecovery(): boolean {
  try {
    if (!isDirty()) return false;
    localStorage.setItem(key(), JSON.stringify({ time: Date.now(), elements: getTree() } satisfies Stash));
    return true;
  } catch {
    return false; // storage full or blocked: the "Copy page data" button still works
  }
}

export function clearRecovery(): void {
  try {
    localStorage.removeItem(key());
  } catch {
    /* blocked storage */
  }
}

/** After the editor has loaded: offers the changes kept by a crash, once. */
export function offerRecovery(): void {
  let stash: Stash | null = null;
  try {
    stash = JSON.parse(localStorage.getItem(key()) ?? 'null');
  } catch {
    stash = null;
  }
  if (!stash || !Array.isArray(stash.elements) || typeof stash.time !== 'number' || Date.now() - stash.time > MAX_AGE) {
    clearRecovery();
    return;
  }
  const kept = stash;
  const when = new Date(kept.time).toLocaleString();
  toast(
    `The editor closed on an error with unsaved changes (${when}).`,
    'warning',
    {
      label: 'Restore them',
      run: () => {
        const next = fromTree(kept.elements);
        commit('Restore unsaved changes', (d) => {
          d.nodes = next.nodes;
          d.root = next.root;
        });
        clearRecovery();
        toast('Restored. Save to keep them.', 'success', undefined, 5000);
      },
    },
    20000,
  );
}

/** Wraps the editor: a render error shows this panel, with the unsaved changes kept, instead of an empty page. */
export class EditorBoundary extends Component<{ children: ReactNode }, { error: Error | null; kept: boolean; copied: boolean }> {
  override state = { error: null as Error | null, kept: false, copied: false };

  static getDerivedStateFromError(error: Error) {
    return { error };
  }

  override componentDidCatch(error: Error) {
    console.error('[Uncoder] editor error', error);
    this.setState({ kept: stashRecovery() });
  }

  private copy = async () => {
    try {
      await navigator.clipboard.writeText(JSON.stringify(getTree(), null, 1));
      this.setState({ copied: true });
    } catch {
      this.setState({ copied: false });
    }
  };

  override render() {
    const { error, kept, copied } = this.state;
    if (!error) return this.props.children;
    return (
      <div className="uncoder-ui-fatal" role="alert">
        <strong>The editor hit an error.</strong>
        <p>{kept ? 'Your unsaved changes are kept in this browser: reload, and choose “Restore them” when the editor opens.' : 'Nothing unsaved was lost. Reload to continue.'}</p>
        <p className="uncoder-ui-fatal__detail">{error.message}</p>
        <p className="uncoder-ui-fatal__actions">
          <button type="button" className="uncoder-ui-btn uncoder-ui-btn--primary" onClick={() => window.location.reload()}>
            Reload the editor
          </button>
          <button type="button" className="uncoder-ui-btn uncoder-ui-btn--secondary" onClick={this.copy}>
            {copied ? 'Copied' : 'Copy page data'}
          </button>
        </p>
      </div>
    );
  }
}
