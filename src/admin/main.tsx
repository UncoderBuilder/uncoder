// Must stay first: provides the config the shared editor UI modules read when they load.
import './env';
import { createRoot } from 'react-dom/client';
import { loadIcons } from '@editor/lib/icons';
import '@editor/ui/tokens.css';
import './admin.css';
import './templates/conditions.css';
import '@editor/ui/brand.css';
import { AdminApp } from './App';

const el = document.getElementById('uncoder-ui-admin-root');
if (el && window.UncoderAdmin) {
  // Icons are fetched once (cached by the browser) before the first paint so no icon pops in late.
  loadIcons()
    .catch(() => undefined)
    .finally(() => createRoot(el).render(<AdminApp />));
}
