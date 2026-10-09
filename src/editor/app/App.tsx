import { useEffect, useRef, useState } from 'react';
import { NAME } from '@shared/brand';
import { Canvas } from '../canvas/Canvas';
import { frame } from '../canvas/frame';
import { config } from '../lib/config';
import { checkAccessibility } from '../lib/a11y';
import { useDoc } from '../store/doc';
import { useUi } from '../store/ui';
import { Icon } from '../ui/Icon';
import { Button, TooltipLayer } from '../ui/primitives';
import { ContextMenu } from './ContextMenu';
import { CommandPalette } from './CommandPalette';
import { SaveTemplateDialog } from './SaveTemplateDialog';
import { PrefsDialog } from './PrefsDialog';
import { StyleBook } from './StyleBook';
import { Inspector } from './Inspector';
import { LeftPanel } from './LeftPanel';
import { PathBar } from './PathBar';
import { Toasts } from './Toasts';
import { TopBar } from './TopBar';
import { handleShortcut } from './shortcuts';
import { handlePasteEvent, startAutosave } from './actions';
import { TemplateConditionsDialog } from '../panels/TemplateSettings';
import { hasConditions } from '../lib/docInfo';
import { loadNotes } from '../store/notes';

/** Below this window width the islands dock edge to edge automatically (gutters cost too much room). */
const AUTO_DOCK = 1280;
/** Below this width the editor does not fit: a notice offers to leave or to continue anyway. */
const MIN_WIDTH = 1024;
const SMALL_OK_KEY = 'uncoder-ui-small-screen-ok';
const NEEDS_H1 = ['page', 'single-page', 'error-404', 'search-results'];

export function App() {
  const theme = useUi((s) => s.theme);
  const preview = useUi((s) => s.preview);
  const layout = useUi((s) => s.layout);
  const inspectorAt = useUi((s) => s.inspectorAt);
  const ref = useRef<HTMLDivElement>(null);
  const [root, setRoot] = useState<HTMLElement | null>(null);
  const [narrow, setNarrow] = useState(() => window.innerWidth < AUTO_DOCK);

  useEffect(() => {
    setRoot(ref.current);
    const onKey = (e: KeyboardEvent) => handleShortcut(e);
    const onResize = () => setNarrow(window.innerWidth < AUTO_DOCK);
    window.addEventListener('keydown', onKey);
    window.addEventListener('paste', handlePasteEvent);
    window.addEventListener('resize', onResize);
    // A file dropped next to the canvas would otherwise replace the editor with the file.
    const noFileNav = (e: DragEvent) => {
      if (Array.from(e.dataTransfer?.types ?? []).includes('Files')) e.preventDefault();
    };
    window.addEventListener('dragover', noFileNav);
    window.addEventListener('drop', noFileNav);
    startAutosave();
    loadNotes();
    return () => {
      window.removeEventListener('keydown', onKey);
      window.removeEventListener('paste', handlePasteEvent);
      window.removeEventListener('resize', onResize);
      window.removeEventListener('dragover', noFileNav);
      window.removeEventListener('drop', noFileNav);
    };
  }, []);

  useEffect(() => {
    document.querySelector('.uncoder-ui-portal')?.setAttribute('data-uncoder-ui-theme', theme);
  }, [theme]);

  useChecksCount();

  return (
    <div ref={ref} className={`uncoder-ui-app${preview ? ' is-preview' : ''}${layout === 'dock' || narrow ? ' is-docked' : ''}`} data-uncoder-ui-theme={theme}>
      {config.safeMode && (
        <div className="uncoder-ui-safemode" role="status">
          Safe mode: only {NAME} is active and a default theme is used, for this browser only.{' '}
          <a href={`${config.urls.admin}admin.php?page=uncoder-settings#tools`}>Turn it off in Settings → Tools</a>
        </div>
      )}
      <TopBar />
      {hasConditions && <TemplateConditionsDialog />}
      <div className="uncoder-ui-body">
        {/* Keyed, so moving the inspector between the sides moves it instead of rebuilding it. */}
        {[
          !preview && <LeftPanel key="build" />,
          !preview && inspectorAt === 'left' && <Inspector key="inspector" />,
          <main key="main" className="uncoder-ui-main">
            <Canvas />
            {!preview && <PathBar />}
          </main>,
          !preview && inspectorAt !== 'left' && <Inspector key="inspector" />,
        ]}
      </div>
      <ContextMenu />
      <CommandPalette />
      <SaveTemplateDialog />
      <PrefsDialog />
      <StyleBook />
      <Toasts />
      <TooltipLayer root={root} />
      <SmallScreenNotice />
    </div>
  );
}

/** Phones and small tablets: the panels would cover the page, so say so first (once per browser session). */
function SmallScreenNotice() {
  const [show, setShow] = useState(() => {
    if (window.innerWidth >= MIN_WIDTH) return false;
    try {
      return sessionStorage.getItem(SMALL_OK_KEY) !== '1';
    } catch {
      return true;
    }
  });
  if (!show) return null;
  const dismiss = () => {
    try {
      sessionStorage.setItem(SMALL_OK_KEY, '1');
    } catch {
      /* private mode: the notice simply shows again next time */
    }
    setShow(false);
  };
  return (
    <div className="uncoder-ui-smallscreen" role="dialog" aria-modal="true" aria-labelledby="uncoder-ui-smallscreen-title">
      <div className="uncoder-ui-smallscreen__box">
        <Icon name="monitor" size={28} />
        <h2 id="uncoder-ui-smallscreen-title">The editor needs a larger screen</h2>
        <p>Use a window at least {MIN_WIDTH}px wide. On a smaller screen the panels cover the page you are editing.</p>
        <div className="uncoder-ui-smallscreen__actions">
          <Button variant="primary" onClick={dismiss} autoFocus>
            Continue anyway
          </Button>
          <a className="uncoder-ui-btn uncoder-ui-btn--ghost uncoder-ui-btn--md" href={config.post.exitUrl}>
            Back to WordPress
          </a>
        </div>
      </div>
    </div>
  );
}

/** Counts accessibility issues for the Checks badge, in idle time a moment after edits settle. */
function useChecksCount() {
  const version = useDoc((s) => s.version);
  const ready = useUi((s) => s.canvasReady);
  useEffect(() => {
    if (!ready) return;
    let idle = 0;
    const timer = window.setTimeout(() => {
      const run = () => {
        const root = frame.mount ?? frame.doc?.body;
        if (!root) return;
        const issues = checkAccessibility(root, { needsH1: NEEDS_H1.includes(config.post.docType) });
        useUi.setState({ checksCount: issues.filter((i) => i.severity !== 'info').length });
      };
      const ric = (window as any).requestIdleCallback as undefined | ((cb: () => void, o?: { timeout: number }) => number);
      idle = ric ? ric(run, { timeout: 4000 }) : window.setTimeout(run, 0);
    }, 2500);
    return () => {
      window.clearTimeout(timer);
      const cancel = (window as any).cancelIdleCallback as undefined | ((h: number) => void);
      if (idle && cancel) cancel(idle);
    };
  }, [version, ready]);
}
