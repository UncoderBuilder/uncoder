import { Component, useState, type ReactNode } from 'react';
import { Icon } from '@editor/ui/Icon';
import { TooltipLayer } from '@editor/ui/primitives';
import { cfg } from './lib/config';
import { AppBar } from './ui/AppBar';
import { ConfirmHost } from './ui/Dialog';
import { Toasts } from './ui/kit';
import { AiScreen } from './screens/Ai';
import { DashboardScreen } from './screens/Dashboard';
import { DesignSystemScreen } from './screens/Kit';
import { SettingsScreen } from './screens/Settings';
import { LibraryScreen } from './screens/Library';
import { PageChecksScreen } from './screens/PageChecks';
import { SubmissionsScreen } from './screens/Submissions';
import { ThemeBuilderScreen } from './screens/Templates';

/** The screens of Admin::menu(); starter sites, saved sections, popups, custom fonts and custom code are tabs or sections inside them. */
const SCREENS: Record<string, () => ReactNode> = {
  uncoder: DashboardScreen,
  'uncoder-library': LibraryScreen,
  'uncoder-checks': PageChecksScreen,
  'uncoder-templates': ThemeBuilderScreen,
  'uncoder-design-system': DesignSystemScreen,
  'uncoder-submissions': SubmissionsScreen,
  'uncoder-ai': AiScreen,
  'uncoder-settings': SettingsScreen,
};

class ScreenBoundary extends Component<{ children: ReactNode }, { error: Error | null }> {
  override state = { error: null as Error | null };
  static getDerivedStateFromError(error: Error) {
    return { error };
  }
  override render() {
    if (!this.state.error) return this.props.children;
    return (
      <div className="uncoder-ui-state uncoder-ui-state--error" role="alert">
        <span className="uncoder-ui-state__icon">
          <Icon name="circle-alert" size={20} />
        </span>
        <strong>This screen hit an error</strong>
        <p>{this.state.error.message}</p>
        <button type="button" className="uncoder-ui-btn uncoder-ui-btn--secondary uncoder-ui-btn--sm" onClick={() => window.location.reload()}>
          Reload
        </button>
      </div>
    );
  }
}

export function AdminApp() {
  const [root, setRoot] = useState<HTMLDivElement | null>(null);
  const Screen = SCREENS[cfg.page] ?? DashboardScreen;

  return (
    <>
      <div className="uncoder-ui-app uncoder-ui-admin" data-uncoder-ui-theme="light" ref={setRoot}>
        <AppBar />
        <main className="uncoder-ui-admin__main">
          <ScreenBoundary>
            <Screen />
          </ScreenBoundary>
        </main>
        <Toasts />
      </div>
      <div className="uncoder-ui-portal" data-uncoder-ui-theme="light" />
      <TooltipLayer root={root} />
      <ConfirmHost />
    </>
  );
}
