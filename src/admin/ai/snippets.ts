// Connection instructions per MCP client, generated with the real endpoint (and a fresh key when there is one).

export type ClientId = 'claude' | 'chatgpt' | 'cursor' | 'vscode' | 'windsurf' | 'other';

export const KEY_PLACEHOLDER = 'YOUR_API_KEY';

export interface Snippet {
  code: string;
  lang: 'json' | 'shell' | 'text';
  file?: string;
  label?: string;
}

export interface Step {
  text: string;
  snippet?: Snippet;
  /** Renders the endpoint URL with a copy button. */
  url?: boolean;
}

export interface Method {
  id: string;
  label: string;
  /** API key or OAuth sign-in. */
  auth: 'key' | 'oauth';
  intro: string;
  steps: Step[];
  note?: string;
}

export interface ClientDef {
  id: ClientId;
  name: string;
  mono: string;
  tint: string;
  /** Brand logo (clientLogos.ts) or, where none may be used, a neutral Lucide icon. */
  logo?: string;
  icon?: string;
  blurb: string;
  methods: Method[];
}

interface Ctx {
  url: string;
  key: string;
  site: string;
  bridge: string;
  metadata: string;
}

const json = (v: unknown) => JSON.stringify(v, null, 2);

/** A server name that is valid in every client config: "uncoder" or "uncoder-my-site". */
export function serverName(site: string): string {
  const slug = site
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 24);
  if (!slug) return 'uncoder';
  return slug.startsWith('uncoder') ? slug : `uncoder-${slug}`;
}

export function clients(c: Ctx): ClientDef[] {
  const name = serverName(c.site);
  const bearer = `Bearer ${c.key}`;
  const try_ = `Try: “Read the Uncoder build guide, then design a home page for ${c.site}.”`;

  return [
    {
      id: 'claude',
      logo: 'claude',
      name: 'Claude',
      mono: 'C',
      tint: '#d97757',
      blurb: 'Claude apps, Claude Code and Claude Desktop',
      methods: [
        {
          id: 'connector',
          label: 'Claude app (connector)',
          auth: 'oauth',
          intro: 'Works in Claude on the web, desktop and mobile. You sign in to this site once; no key to copy.',
          steps: [
            { text: 'In Claude, open Settings → Connectors and click “Add custom connector”.' },
            { text: `Name it “${c.site}” and paste this URL:`, url: true },
            { text: `Click Connect. A sign-in window from ${c.site} opens: log in, review the permissions and click Approve.` },
            { text: 'In a chat, open the “Search and tools” menu and make sure the connector is enabled.' },
            { text: try_ },
          ],
          note: 'On Team and Enterprise plans an owner adds the connector in Organization settings → Connectors first.',
        },
        {
          id: 'code',
          label: 'Claude Code',
          auth: 'key',
          intro: 'Add the server from your terminal. Claude Code speaks Streamable HTTP natively.',
          steps: [
            {
              text: 'Run this in your project (add “--scope user” to use it everywhere):',
              snippet: { lang: 'shell', label: 'Terminal', code: `claude mcp add --transport http ${name} ${c.url} \\\n  --header "Authorization: ${bearer}"` },
            },
            { text: 'Start Claude Code and run /mcp to check that the server is connected.' },
            { text: try_ },
          ],
          note: `Prefer signing in instead of a key? Run “claude mcp add --transport http ${name} ${c.url}”, then /mcp → Authenticate.`,
        },
        {
          id: 'desktop',
          label: 'Claude Desktop (config file)',
          auth: 'key',
          intro: 'For Claude Desktop without connectors: a local bridge (mcp-remote, needs Node.js) forwards to this site.',
          steps: [
            { text: 'Open Claude Desktop → Settings → Developer → Edit Config.' },
            {
              text: 'Add this server to claude_desktop_config.json (merge it into “mcpServers” if the file already has servers):',
              snippet: {
                lang: 'json',
                file: 'claude_desktop_config.json',
                code: json({
                  mcpServers: {
                    [name]: {
                      command: 'npx',
                      args: ['-y', 'mcp-remote', c.url, '--header', 'Authorization:${UNCODER_AUTH}'],
                      env: { UNCODER_AUTH: bearer },
                    },
                  },
                }),
              },
            },
            { text: 'Quit and reopen Claude Desktop. The tools appear in the “Search and tools” menu.' },
          ],
          note: 'The header is passed through an environment variable because some systems split arguments that contain spaces.',
        },
      ],
    },
    {
      id: 'chatgpt',
      icon: 'messages-square',
      logo: 'chatgpt',
      name: 'ChatGPT',
      mono: 'G',
      tint: '#10a37f',
      blurb: 'Developer mode connectors',
      methods: [
        {
          id: 'connector',
          label: 'Developer mode connector',
          auth: 'oauth',
          intro: 'Available on ChatGPT plans with developer mode. You sign in to this site; no key to copy.',
          steps: [
            { text: 'In ChatGPT, open Settings → Apps & Connectors → Advanced settings and turn on Developer mode.' },
            { text: 'Back in Apps & Connectors, click Create.' },
            { text: `Enter a name (“${c.site}”), choose OAuth as authentication and paste this MCP server URL:`, url: true },
            { text: `Confirm that you trust the connector and click Create. Log in to ${c.site} and approve access.` },
            { text: 'In a new chat, open the + menu → Developer mode and enable the connector.' },
            { text: try_ },
          ],
          note: 'Write tools ask for confirmation in ChatGPT before they change your site.',
        },
      ],
    },
    {
      id: 'cursor',
      logo: 'cursor',
      name: 'Cursor',
      mono: 'Cu',
      tint: '#1f2328',
      blurb: 'mcp.json with a remote URL',
      methods: [
        {
          id: 'json',
          label: 'mcp.json',
          auth: 'key',
          intro: 'Cursor connects to remote MCP servers directly.',
          steps: [
            {
              text: 'Add this to ~/.cursor/mcp.json (all projects) or .cursor/mcp.json in a project:',
              snippet: { lang: 'json', file: 'mcp.json', code: json({ mcpServers: { [name]: { url: c.url, headers: { Authorization: bearer } } } }) },
            },
            { text: 'Open Cursor Settings → MCP & Integrations and check that the server shows a green dot and its tools.' },
          ],
          note: 'Do not commit a project mcp.json that contains a key.',
        },
      ],
    },
    {
      id: 'vscode',
      logo: 'copilot',
      name: 'VS Code',
      mono: 'VS',
      tint: '#0065a9',
      blurb: 'GitHub Copilot agent mode',
      methods: [
        {
          id: 'json',
          label: 'mcp.json',
          auth: 'key',
          intro: 'VS Code supports Streamable HTTP servers in Copilot agent mode.',
          steps: [
            {
              text: 'Run “MCP: Open User Configuration” from the Command Palette (or create .vscode/mcp.json in a workspace) and add:',
              snippet: { lang: 'json', file: 'mcp.json', code: json({ servers: { [name]: { type: 'http', url: c.url, headers: { Authorization: bearer } } } }) },
            },
            { text: 'Click Start above the server entry, then pick the tools in Copilot Chat → agent mode → Tools.' },
          ],
          note: 'Keep keys in the user configuration so they are never committed with a workspace.',
        },
      ],
    },
    {
      id: 'windsurf',
      logo: 'windsurf',
      name: 'Windsurf',
      mono: 'W',
      tint: '#0b8a7a',
      blurb: 'Cascade MCP servers',
      methods: [
        {
          id: 'json',
          label: 'mcp_config.json',
          auth: 'key',
          intro: 'Windsurf’s Cascade connects to remote servers with a serverUrl.',
          steps: [
            { text: 'Open Windsurf Settings → Cascade → MCP servers → View raw config (~/.codeium/windsurf/mcp_config.json).' },
            {
              text: 'Add the server:',
              snippet: { lang: 'json', file: 'mcp_config.json', code: json({ mcpServers: { [name]: { serverUrl: c.url, headers: { Authorization: bearer } } } }) },
            },
            { text: 'Refresh the MCP servers list in Cascade.' },
          ],
        },
      ],
    },
    {
      id: 'other',
      icon: 'plug',
      name: 'Other clients',
      mono: '…',
      tint: '#6b7280',
      blurb: 'Any Streamable HTTP or stdio client',
      methods: [
        {
          id: 'http',
          label: 'Streamable HTTP',
          auth: 'key',
          intro: 'Any MCP client that supports remote (Streamable HTTP) servers.',
          steps: [
            { text: 'Server URL:', url: true },
            { text: 'Send the API key as a bearer token in every request:', snippet: { lang: 'text', label: 'HTTP header', code: `Authorization: ${bearer}` } },
            {
              text: 'Clients that support OAuth 2.1 can sign in instead of using a key. They discover everything from:',
              snippet: { lang: 'text', label: 'Protected resource metadata', code: c.metadata },
            },
          ],
        },
        {
          id: 'stdio',
          label: 'stdio bridge',
          auth: 'key',
          intro: 'For clients that only launch local (stdio) servers. Needs Node.js 18+.',
          steps: [
            { text: 'Use this as the server command:', snippet: { lang: 'shell', label: 'Command', code: c.bridge.replace('<API_KEY>', c.key) } },
            { text: 'Or with the generic mcp-remote bridge:', snippet: { lang: 'shell', label: 'Command', code: `npx -y mcp-remote ${c.url} --header "Authorization: ${bearer}"` } },
          ],
        },
      ],
    },
  ];
}
