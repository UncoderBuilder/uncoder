import { config } from '../lib/config';
import { NAME } from '@shared/brand';
import { getTree } from '../store/doc';
import { toast } from '../store/ui';
import { Icon } from '../ui/Icon';
import { Button } from '../ui/primitives';

const IDEAS = [
  'Read the build guide, then redesign this page for a {business} with a hero, three benefits, testimonials, FAQ and a call to action.',
  'Audit this page for accessibility and SEO and fix the issues you find.',
  'Create a sticky header and a four-column footer and apply them to the whole site.',
  'Turn this page into a dark theme using the Design System colors.',
];

export function AiPanel() {
  const mcp = config.rest.root + 'mcp';
  return (
    <div className="uncoder-ui-ai">
      <div className="uncoder-ui-ai__hero">
        <span className="uncoder-ui-ai__badge">
          <Icon name="sparkles" size={16} />
        </span>
        <h3>Build this site with AI</h3>
        <p>{NAME} ships an MCP server. Claude, ChatGPT, Cursor, VS Code and other clients can read and edit this page, the Design System, templates, menus and media — every change lands here as editable elements and can be undone.</p>
      </div>
      <div className="uncoder-ui-kit__label">Server URL</div>
      <div className="uncoder-ui-copyrow">
        <code className="uncoder-ui-mono">{mcp}</code>
        <button
          type="button"
          className="uncoder-ui-iconbtn"
          aria-label="Copy server URL"
          onClick={() => {
            navigator.clipboard?.writeText(mcp);
            toast('Server URL copied', 'success', undefined, 1600);
          }}
        >
          <Icon name="copy" size={14} />
        </button>
      </div>
      {config.user.caps.manage_options && (
        <Button icon="plug" onClick={() => window.open(config.urls.mcp, '_blank')} className="uncoder-ui-ai__cta">
          Connect a client
        </Button>
      )}
      <div className="uncoder-ui-kit__label">Try asking</div>
      <div className="uncoder-ui-ai__ideas">
        {IDEAS.map((idea) => (
          <button
            key={idea}
            type="button"
            className="uncoder-ui-ai__idea"
            onClick={() => {
              navigator.clipboard?.writeText(idea.replace('{business}', config.site.name) + ` (page id ${config.post.id})`);
              toast('Prompt copied — paste it in your AI client', 'success', undefined, 2400);
            }}
          >
            <Icon name="message-square-text" size={14} />
            <span>{idea.replace('{business}', 'your business')}</span>
          </button>
        ))}
      </div>
      <div className="uncoder-ui-kit__label">This page</div>
      <p className="uncoder-ui-note">
        Page ID <span className="uncoder-ui-mono">{config.post.id}</span>. AI clients can target it with <span className="uncoder-ui-mono">get_page</span> and <span className="uncoder-ui-mono">edit_elements</span>.
      </p>
      <Button
        size="sm"
        icon="braces"
        onClick={() => {
          navigator.clipboard?.writeText(JSON.stringify(getTree(), null, 1));
          toast('Page JSON copied', 'success', undefined, 1600);
        }}
      >
        Copy page JSON
      </Button>
    </div>
  );
}
