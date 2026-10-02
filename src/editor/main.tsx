import { createRoot } from 'react-dom/client';
import './ui/tokens.css';
import './editor.css';
import './studio.css';
import './ui/brand.css';
import '../admin/templates/conditions.css';
import { loadIcons } from './lib/icons';
import { fonts } from './lib/fonts';

async function boot() {
  const rootEl = document.getElementById('uncoder-ui-root');
  if (!rootEl || !window.UncoderEditor) return;
  const portal = document.createElement('div');
  portal.className = 'uncoder-ui-portal';
  portal.setAttribute('data-uncoder-ui-theme', 'light');
  document.body.appendChild(portal);

  await Promise.all([loadIcons(), fonts.load()]);
  const { App } = await import('./app/App');
  createRoot(rootEl).render(<App />);
}

boot().catch((e) => {
  console.error('[Uncoder] editor failed to start', e);
  const el = document.getElementById('uncoder-ui-root');
  if (el) el.innerHTML = '<div class="uncoder-ui-fatal"><strong>Uncoder could not start.</strong><p>Reload the page. If it keeps happening, check the browser console for details.</p></div>';
});
