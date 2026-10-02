import { useCallback, useEffect, useState } from 'react';
import { checkAccessibility, type A11yIssue } from '../lib/a11y';
import { config, schemaOf } from '../lib/config';
import { useDoc } from '../store/doc';
import { select } from '../store/ui';
import { elementFor, frame } from '../canvas/frame';
import { Icon } from '../ui/Icon';
import { Button } from '../ui/primitives';

const NEEDS_H1 = ['page', 'single-page', 'error-404', 'search-results'];
const SEVERITY_LABEL = { error: 'Error', warning: 'Warning', info: 'Tip' } as const;

/** Checks the rendered page for common accessibility problems and jumps to each element. */
export function A11yPanel() {
  const nodes = useDoc((s) => s.doc.nodes);
  const [issues, setIssues] = useState<A11yIssue[] | null>(null);

  const run = useCallback(() => {
    const root = frame.mount ?? frame.doc?.body;
    if (!root) return;
    setIssues(checkAccessibility(root, { needsH1: NEEDS_H1.includes(config.post.docType) }));
  }, []);

  // Runs on open and again shortly after edits (widgets re-render asynchronously).
  useEffect(() => {
    const t = window.setTimeout(run, issues === null ? 0 : 900);
    return () => window.clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [nodes, run]);

  const counts = { error: 0, warning: 0, info: 0 };
  for (const i of issues ?? []) counts[i.severity]++;

  return (
    <div className="uncoder-ui-a11y">
      <div className="uncoder-ui-a11y__summary" role="status">
        {issues === null ? (
          'Checking…'
        ) : issues.length === 0 ? (
          <>
            <Icon name="circle-check" size={14} /> No problems found on this page.
          </>
        ) : (
          <>
            <span className="uncoder-ui-a11y__count uncoder-ui-a11y__count--error">{counts.error} error{counts.error === 1 ? '' : 's'}</span>
            <span className="uncoder-ui-a11y__count uncoder-ui-a11y__count--warning">{counts.warning} warning{counts.warning === 1 ? '' : 's'}</span>
          </>
        )}
        <Button size="sm" icon="refresh-cw" onClick={run} className="uncoder-ui-a11y__rerun">
          Check again
        </Button>
      </div>
      <ul className="uncoder-ui-a11y__list">
        {(issues ?? []).map((issue, i) => {
          const node = issue.id ? nodes[issue.id] : undefined;
          const schema = node ? schemaOf(node.type) : undefined;
          return (
            <li key={i}>
              <button
                type="button"
                className={`uncoder-ui-a11y__item uncoder-ui-a11y__item--${issue.severity}`}
                disabled={!node}
                onClick={() => {
                  if (!issue.id) return;
                  select(issue.id);
                  elementFor(issue.id)?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                }}
              >
                <span className="uncoder-ui-a11y__sev">{SEVERITY_LABEL[issue.severity]}</span>
                <span className="uncoder-ui-a11y__msg">{issue.message}</span>
                <span className="uncoder-ui-a11y__fix">{issue.fix}</span>
                {schema && (
                  <span className="uncoder-ui-a11y__where">
                    <Icon name={schema.icon ?? 'box'} size={11} /> {node?.label || schema.title}
                  </span>
                )}
              </button>
            </li>
          );
        })}
      </ul>
      <p className="uncoder-ui-note">Automatic checks find about a third of accessibility problems. Also try the page with the keyboard only (Tab, Enter, Esc) and a screen reader.</p>
    </div>
  );
}
