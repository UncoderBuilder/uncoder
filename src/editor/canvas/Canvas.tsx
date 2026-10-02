// Hosts the canvas iframe (the real front-end page in preview mode) and mounts <CanvasApp/> into it.
import { useEffect, useRef, useState } from 'react';
import { Loader } from '../ui/Brand';
import { createRoot, type Root } from 'react-dom/client';
import { config } from '../lib/config';
import { attachCss, detachCss } from '../lib/cssManager';
import { fonts } from '../lib/fonts';
import { breakpoints, deviceWidth, useUi } from '../store/ui';
import { Button } from '../ui/primitives';
import { CanvasApp } from './CanvasApp';
import { frame, invalidateGeometry } from './frame';
import { keepPlaceOnResize } from './keepPlace';

// Narrowest width that still counts as desktop (above every max-width breakpoint).
const DESKTOP_MIN = Math.max(0, ...breakpoints.filter((b) => b.direction === 'max' && b.value != null).map((b) => Number(b.value))) + 1;

export function Canvas() {
  const hostRef = useRef<HTMLDivElement>(null);
  const [avail, setAvail] = useState(0);
  const iframeRef = useRef<HTMLIFrameElement>(null);
  const rootRef = useRef<Root | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [reloadKey, setReloadKey] = useState(0);
  const device = useUi((s) => s.device);
  const customWidth = useUi((s) => s.customWidth);
  const zoom = useUi((s) => s.zoom);
  const ready = useUi((s) => s.canvasReady);
  const theme = useUi((s) => s.theme);

  const width = customWidth ?? deviceWidth(device);
  // Desktop in a narrow window: render at DESKTOP_MIN and scale down, so tablet styles never leak in.
  const frameWidth = width ?? (avail && avail < DESKTOP_MIN ? DESKTOP_MIN : null);
  const fit = frameWidth && avail ? Math.min(1, avail / frameWidth) : 1;
  const scale = zoom * fit;
  const scaleRef = useRef(scale);
  scaleRef.current = scale;

  useEffect(() => {
    const host = hostRef.current;
    if (!host) return;
    const measure = () => {
      const cs = getComputedStyle(host);
      setAvail(Math.floor(host.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight)));
    };
    measure();
    const ro = new ResizeObserver(measure);
    ro.observe(host);
    return () => ro.disconnect();
  }, []);
  const shownWidth = frameWidth ?? avail;
  useEffect(() => {
    const s = useUi.getState();
    if (s.fit !== fit || s.canvasWidth !== shownWidth) useUi.setState({ fit, canvasWidth: shownWidth });
  }, [fit, shownWidth]);

  const onLoad = () => {
    const iframe = iframeRef.current;
    if (!iframe) return;
    rootRef.current?.unmount();
    rootRef.current = null;
    detachCss();
    let doc: Document | null = null;
    try {
      doc = iframe.contentDocument;
    } catch {
      doc = null;
    }
    const mount = doc?.getElementById('uncoder-canvas-root') ?? null;
    if (!doc || !mount) {
      setError(
        doc
          ? 'The page preview loaded, but the theme did not print the page content, so there is nowhere to mount the canvas. Switch the page template to “Uncoder Canvas” or “Uncoder Full Width” in Page settings.'
          : 'The page preview could not be loaded.',
      );
      useUi.setState({ canvasReady: false });
      return;
    }
    setError(null);
    const overlay = doc.createElement('div');
    overlay.className = 'uncoder-ui-ov-root';
    doc.body.appendChild(overlay);
    frame.iframe = iframe;
    frame.doc = doc;
    frame.win = iframe.contentWindow as any;
    frame.mount = mount;
    frame.overlay = overlay;
    doc.documentElement.classList.add('uncoder-editing');
    doc.documentElement.setAttribute('data-uncoder-ui-device', useUi.getState().device);
    doc.documentElement.style.setProperty('--uncoder-ui-inv-scale', String(1 / scaleRef.current));
    // Links inside the canvas never navigate away.
    doc.addEventListener('click', (e) => {
      const a = (e.target as Element).closest?.('a');
      if (a) e.preventDefault();
    });
    fonts.attach(doc);
    attachCss(doc);
    rootRef.current = createRoot(mount);
    rootRef.current.render(<CanvasApp />);
    useUi.setState({ canvasReady: true });
    setTimeout(invalidateGeometry, 50);
  };

  useEffect(() => () => rootRef.current?.unmount(), []);
  // Switching device / width / zoom keeps the element you are editing in view.
  useEffect(() => keepPlaceOnResize(), []);
  useEffect(() => {
    // Selection chrome inside the frame keeps its on-screen size when the page is scaled down.
    frame.doc?.documentElement.style.setProperty('--uncoder-ui-inv-scale', String(1 / scale));
    invalidateGeometry();
  }, [frameWidth, scale]);

  return (
    <div ref={hostRef} className={`uncoder-ui-canvas${width ? ' is-device' : ''}`} data-uncoder-ui-theme-canvas={theme}>
      <div
        className="uncoder-ui-canvas__frame"
        style={{
          width: frameWidth ? frameWidth : '100%',
          maxWidth: frameWidth && frameWidth > avail ? 'none' : undefined,
          flexShrink: 0,
          transform: scale !== 1 ? `scale(${scale})` : undefined,
          height: scale !== 1 ? `${100 / scale}%` : '100%',
        }}
      >
        <iframe
          key={reloadKey}
          ref={iframeRef}
          className="uncoder-ui-canvas__iframe"
          title="Page canvas"
          src={config.post.previewUrl}
          onLoad={onLoad}
        />
        {!ready && !error && (
          <div className="uncoder-ui-canvas__loading">
            <Loader label="Loading page…" />
          </div>
        )}
        {error && (
          <div className="uncoder-ui-canvas__error" role="alert">
            <strong>Canvas unavailable</strong>
            <p>{error}</p>
            <Button icon="rotate-ccw" onClick={() => setReloadKey((k) => k + 1)}>
              Reload canvas
            </Button>
          </div>
        )}
      </div>
    </div>
  );
}

export function reloadCanvas(): void {
  const iframe = frame.iframe;
  if (iframe) {
    useUi.setState({ canvasReady: false });
    iframe.contentWindow?.location.reload();
  }
}
