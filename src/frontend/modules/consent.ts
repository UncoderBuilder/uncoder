// Cookie consent (Site\Consent): remembers the visitor's choice in a cookie, brings held-back code
// snippets (<template data-uncoder-consent>) to life for the granted categories and updates Google
// Consent Mode. Withdrawing a category reloads the page, since running scripts cannot be undone.
window.UncoderWB.register('consent', (el, api) => {
  if (api.editor) return;
  const s = api.settings<{ version?: number; consentMode?: boolean; cookie?: string }>(el);
  const name = s.cookie || 'uncoder_consent';
  const version = `v${s.version || 1}`;
  const prefs = el.querySelector<HTMLFormElement>('.uncoder-consent__prefs');
  const prefsBtn = el.querySelector<HTMLButtonElement>('[data-consent="prefs"]');
  const saveBtn = el.querySelector<HTMLButtonElement>('[data-consent="save"]');
  const reopen = document.querySelector<HTMLButtonElement>('.uncoder-consent-reopen');
  type Choice = { analytics: boolean; marketing: boolean };

  const read = (): Choice | null => {
    const m = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    const parts = m ? decodeURIComponent(m[1]).split('.') : [];
    if (parts[0] !== version) return null; // No decision yet, or the site asks again.
    return { analytics: parts.includes('analytics'), marketing: parts.includes('marketing') };
  };
  const write = (c: Choice) => {
    const value = [version, c.analytics && 'analytics', c.marketing && 'marketing'].filter(Boolean).join('.');
    document.cookie = `${name}=${encodeURIComponent(value)}; max-age=${60 * 60 * 24 * 180}; path=/; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
  };

  /** Recreates each script so the browser runs it (template content is inert). */
  const activate = (c: Choice) => {
    document.querySelectorAll<HTMLTemplateElement>('template[data-uncoder-consent]').forEach((tpl) => {
      const cat = tpl.dataset.uncoderConsent as keyof Choice;
      if (!c[cat] || tpl.dataset.done) return;
      tpl.dataset.done = '1';
      const frag = document.importNode(tpl.content, true);
      frag.querySelectorAll('script').forEach((old) => {
        const script = document.createElement('script');
        for (const attr of Array.from(old.attributes)) script.setAttribute(attr.name, attr.value);
        script.text = old.text;
        old.replaceWith(script);
      });
      tpl.after(frag);
    });
    const w = window as any;
    if (s.consentMode && typeof w.gtag === 'function') {
      const state = (on: boolean) => (on ? 'granted' : 'denied');
      w.gtag('consent', 'update', { analytics_storage: state(c.analytics), ad_storage: state(c.marketing), ad_user_data: state(c.marketing), ad_personalization: state(c.marketing) });
    }
    document.dispatchEvent(new CustomEvent('uncoder:consent', { detail: c }));
  };

  let returnTo: HTMLElement | null = null;
  // Focus moves in only when the visitor asked for the panel; on page load the banner waits politely.
  const open = (withPrefs = false, trigger: HTMLElement | null = null) => {
    returnTo = trigger;
    el.hidden = false;
    if (reopen) reopen.hidden = true;
    const c = read();
    if (prefs) {
      const a = prefs.elements.namedItem('analytics') as HTMLInputElement | null;
      const m = prefs.elements.namedItem('marketing') as HTMLInputElement | null;
      if (a) a.checked = !!c?.analytics;
      if (m) m.checked = !!c?.marketing;
    }
    togglePrefs(withPrefs);
    if (trigger) el.querySelector<HTMLInputElement>('.uncoder-consent__prefs input:not(:disabled)')?.focus();
  };
  const close = () => {
    const hadFocus = el.contains(document.activeElement);
    el.hidden = true;
    if (reopen) reopen.hidden = false;
    if (hadFocus) (returnTo?.isConnected && !returnTo.hidden ? returnTo : reopen)?.focus();
    returnTo = null;
  };
  const togglePrefs = (on: boolean) => {
    if (prefs) prefs.hidden = !on;
    if (saveBtn) saveBtn.hidden = !on;
    prefsBtn?.setAttribute('aria-expanded', String(on));
  };

  const decide = (c: Choice) => {
    const before = read();
    write(c);
    close();
    // A category that was on and is now off: its scripts already run, start over.
    if (before && ((before.analytics && !c.analytics) || (before.marketing && !c.marketing))) {
      window.location.reload();
      return;
    }
    activate(c);
  };

  const onClick = (event: MouseEvent) => {
    const action = (event.target as HTMLElement).closest<HTMLElement>('[data-consent]')?.dataset.consent;
    if (action === 'accept') decide({ analytics: true, marketing: true });
    else if (action === 'reject') decide({ analytics: false, marketing: false });
    else if (action === 'prefs') togglePrefs(prefs?.hidden ?? false);
    else if (action === 'save' && prefs) {
      decide({ analytics: !!(prefs.elements.namedItem('analytics') as HTMLInputElement)?.checked, marketing: !!(prefs.elements.namedItem('marketing') as HTMLInputElement)?.checked });
    }
  };
  // Any link to #uncoder-consent (e.g. "Cookie settings" in the footer) reopens the preferences.
  const onDocClick = (event: MouseEvent) => {
    const trigger = (event.target as HTMLElement).closest<HTMLElement>('a[href$="#uncoder-consent"], [data-consent-open]');
    if (!trigger) return;
    event.preventDefault();
    open(true, trigger);
  };

  const onKey = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && returnTo && !el.hidden && read()) close();
  };
  el.addEventListener('click', onClick);
  el.addEventListener('keydown', onKey);
  document.addEventListener('click', onDocClick);
  const choice = read();
  if (choice) {
    activate(choice);
    if (reopen) reopen.hidden = false;
  } else {
    open(false);
  }
  return () => {
    el.removeEventListener('click', onClick);
    el.removeEventListener('keydown', onKey);
    document.removeEventListener('click', onDocClick);
  };
});
