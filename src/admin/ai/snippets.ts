// Connection instructions per MCP client, generated with the real endpoint and, once created, the client's own
// connection link (one URL that carries its own key: …/mcp?token=uncoder_link_…). The link is the default way for
// every client; signing in (OAuth) and API keys in a header stay as alternatives.

export type ClientId = 'claude' | 'chatgpt' | 'cursor' | 'vscode' | 'windsurf' | 'other';

export const KEY_PLACEHOLDER = 'YOUR_API_KEY';
export const LINK_PLACEHOLDER = 'YOUR_CONNECTION_LINK';

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
  /** Renders the client's connection link (or the button that creates it) with a copy button. */
  link?: boolean;
  /** A one-click install that opens the app with this server filled in (shown once the link exists). */
  open?: { label: string; href: string; icon?: string };
}

export interface Method {
  id: string;
  label: string;
  /** Connection link (one URL with its own key), OAuth sign-in or an API key in a header. */
  auth: 'link' | 'oauth' | 'key';
  /** Connects from the vendor's servers: the site must be public and use HTTPS. */
  cloud?: boolean;
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
  /** Each client's connection link, once created ('' before). */
  links: Partial<Record<ClientId, string>>;
  site: string;
  metadata: string;
}

const json = (v: unknown) => JSON.stringify(v, null, 2);
/** Base64 of a UTF-8 string (Cursor's install links carry the server config this way). */
const b64 = (s: string) => btoa(String.fromCharCode(...new TextEncoder().encode(s)));

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
  const link = (id: ClientId) => c.links[id] || LINK_PLACEHOLDER;
  const create = (who: string): Step => ({ text: `Create a connection link for ${who} and copy it:`, link: true });
  const try_ = `Try: “Read the Uncoder build guide, then design a home page for ${c.site}.”`;
  const keepPrivate = 'Keep the link private, like a password: anyone who has it can use these permissions. Revoke it any time under API keys.';

  // mcp-remote refuses plain http except to localhost, so local .test/.local sites need --allow-http.
  const plainHttp = (() => {
    try {
      const u = new URL(c.url);
      return u.protocol === 'http:' && !['localhost', '127.0.0.1'].includes(u.hostname);
    } catch {
      return false;
    }
  })();
  const bridge = (l: string) => ['-y', 'mcp-remote', l, ...(plainHttp ? ['--allow-http'] : [])];

  const claude = link('claude');
  const cursor = link('cursor');
  const vscode = link('vscode');
  const windsurf = link('windsurf');
  const other = link('other');

  return [
    {
      id: 'claude',
      logo: 'claude',
      name: 'Claude',
      mono: 'C',
      tint: '#d97757',
      blurb: 'Claude app, Claude Code and Claude Desktop',
      methods: [
        {
          id: 'app',
          label: 'Claude app',
          auth: 'link',
          cloud: true,
          intro: 'Claude on the web, desktop and mobile: add your site as a custom connector with one link.',
          steps: [
            create('Claude'),
            {
              text: 'Open Claude with the connector filled in, then click Add:',
              open: { label: 'Add to Claude', icon: 'external-link', href: `https://claude.ai/customize/connectors?modal=add-custom-connector&connectorName=${encodeURIComponent(c.site)}&connectorUrl=${encodeURIComponent(claude)}` },
            },
            { text: `Or by hand: in Claude, open Customize → Connectors → Add custom connector, name it “${c.site}”, paste the link as the URL and click Add. If Claude asks how to sign in, choose No sign-in.` },
            { text: 'In a chat, check that the connector is turned on in the tools menu.' },
            { text: try_ },
          ],
          note: 'On Team and Enterprise plans an owner adds custom connectors under Organization settings → Connectors. Claude connects from Anthropic’s servers: your site must be public, use HTTPS and let Claude through any firewall.',
        },
        {
          id: 'code',
          label: 'Claude Code',
          auth: 'link',
          intro: 'One command in your terminal. Claude Code connects from your computer, so a local site works too.',
          steps: [
            create('Claude Code'),
            { text: 'Run this (leave out “--scope user” to add it to the current project only):', snippet: { lang: 'shell', label: 'Terminal', code: `claude mcp add --transport http --scope user ${name} "${claude}"` } },
            { text: 'Start Claude Code and run /mcp to check that the server is connected.' },
            { text: try_ },
          ],
          note: keepPrivate,
        },
        {
          id: 'desktop',
          label: 'Claude Desktop',
          auth: 'link',
          intro: 'Claude Desktop uses the same connectors as the Claude app, so “Claude app” works there too. For a site on your own computer, add it to the config file instead (needs Node.js 18+).',
          steps: [
            create('Claude Desktop'),
            { text: 'Open Claude Desktop → Settings → Developer → Edit Config.' },
            {
              text: 'Add your site to claude_desktop_config.json (merge it into “mcpServers” if the file already has servers):',
              snippet: { lang: 'json', file: 'claude_desktop_config.json', code: json({ mcpServers: { [name]: { command: 'npx', args: bridge(claude) } } }) },
            },
            { text: 'Quit and reopen Claude Desktop. The tools appear in the tools menu.' },
          ],
          note: 'mcp-remote is a small open-source bridge that runs on your computer and forwards to this site.',
        },
        {
          id: 'signin',
          label: 'Sign in instead',
          auth: 'oauth',
          cloud: true,
          intro: 'No key in the URL: Claude signs in to this site and you approve it once.',
          steps: [
            { text: 'In Claude, open Customize → Connectors → Add custom connector.' },
            { text: `Name it “${c.site}” and paste this URL:`, url: true },
            { text: `Click Add and sign in when Claude asks: log in to ${c.site}, review the permissions and click Approve.` },
            { text: try_ },
          ],
          note: `Claude Code can sign in too: run “claude mcp add --transport http ${name} ${c.url}”, then /mcp → Authenticate.`,
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
      blurb: 'Desktop app and web connectors',
      methods: [
        {
          id: 'link',
          label: 'Desktop app',
          auth: 'link',
          intro: 'The ChatGPT app for Windows and Mac: paste one link. Works with a site on your own computer too.',
          steps: [
            create('ChatGPT'),
            { text: 'In the ChatGPT desktop app, open Settings → Plugins → MCPs and click Add → Add MCP server (in some versions: Settings → MCP servers → Add server).' },
            { text: `Name it “${name}”, choose Streamable HTTP, paste the link into URL and click Save. Leave every other field empty.` },
            { text: try_ },
          ],
          note: `${keepPrivate} The same link works in the Codex CLI: codex mcp add ${name} --url "<link>".`,
        },
        {
          id: 'web',
          label: 'On the web',
          auth: 'link',
          cloud: true,
          intro: 'A custom connector on chatgpt.com, on plans with developer mode.',
          steps: [
            create('ChatGPT'),
            { text: 'In ChatGPT’s settings, turn on Developer mode.' },
            { text: `Create a connector named “${c.site}”, paste the link as its MCP server URL, choose No authentication and create it.` },
            { text: 'In a new chat, enable the connector from the + menu.' },
            { text: try_ },
          ],
          note: 'On Business and Enterprise workspaces an admin turns on developer mode first. ChatGPT connects from OpenAI’s servers, so your site must be public and use HTTPS. Write actions ask for confirmation in ChatGPT.',
        },
        {
          id: 'signin',
          label: 'Sign in instead',
          auth: 'oauth',
          intro: 'No key in the URL: ChatGPT signs in to this site and you approve it once.',
          steps: [
            { text: 'Desktop app: Settings → Plugins → MCPs → Add → Add MCP server. Choose Streamable HTTP and paste this URL, leave every other field empty and click Save:', url: true },
            { text: `Sign in when ChatGPT asks (or run “codex mcp login ${name}” in a terminal). Your browser opens ${c.site}: log in, review the permissions and click Approve.` },
            { text: 'On the web: create the connector with the same URL and choose OAuth as the authentication.' },
          ],
          note: '“Bearer token env var” takes the name of an environment variable, not a key: a key pasted there is ignored and the connection fails.',
        },
      ],
    },
    {
      id: 'cursor',
      logo: 'cursor',
      name: 'Cursor',
      mono: 'Cu',
      tint: '#1f2328',
      blurb: 'One-click install or mcp.json',
      methods: [
        {
          id: 'link',
          label: 'Connection link',
          auth: 'link',
          intro: 'Install in one click, or add one line to mcp.json.',
          steps: [
            create('Cursor'),
            { text: 'Open Cursor and confirm the install:', open: { label: 'Add to Cursor', icon: 'download', href: `cursor://anysphere.cursor-deeplink/mcp/install?name=${encodeURIComponent(name)}&config=${encodeURIComponent(b64(JSON.stringify({ url: cursor })))}` } },
            { text: 'Or by hand: add this to ~/.cursor/mcp.json (all projects) or .cursor/mcp.json in a project:', snippet: { lang: 'json', file: 'mcp.json', code: json({ mcpServers: { [name]: { url: cursor } } }) } },
            { text: 'In Cursor’s MCP settings, check that the server shows a green dot and its tools.' },
          ],
          note: 'Do not commit a project mcp.json that contains a link. Revoke the link under API keys if it was shared.',
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
          id: 'link',
          label: 'Connection link',
          auth: 'link',
          intro: 'Install in one click, or add it with “MCP: Add Server”.',
          steps: [
            create('VS Code'),
            { text: 'Open VS Code and confirm the install:', open: { label: 'Install in VS Code', icon: 'download', href: `vscode:mcp/install?${encodeURIComponent(JSON.stringify({ name, type: 'http', url: vscode }))}` } },
            { text: 'Or by hand: run “MCP: Add Server” → HTTP and paste the link, or add it to your user mcp.json (“MCP: Open User Configuration”):', snippet: { lang: 'json', file: 'mcp.json', code: json({ servers: { [name]: { type: 'http', url: vscode } } }) } },
            { text: 'Start the server, then pick its tools in Copilot Chat → Agent mode → Tools.' },
          ],
          note: 'On Copilot Business and Enterprise, an organization admin must allow MCP servers first. Keep links in your user configuration, not in a shared workspace.',
        },
      ],
    },
    {
      id: 'windsurf',
      logo: 'windsurf',
      name: 'Windsurf',
      mono: 'W',
      tint: '#0b8a7a',
      blurb: 'Windsurf and Devin Desktop',
      methods: [
        {
          id: 'link',
          label: 'Connection link',
          auth: 'link',
          intro: 'Windsurf (now Devin Desktop) connects to remote servers with a serverUrl.',
          steps: [
            create('Windsurf'),
            { text: 'Open the MCP config: in the app’s MCP settings choose View raw config, or open mcp_config.json (Devin Desktop: %APPDATA%\\devin on Windows, ~/.config/devin on Mac and Linux; older Windsurf: ~/.codeium/windsurf).' },
            { text: 'Add the server:', snippet: { lang: 'json', file: 'mcp_config.json', code: json({ mcpServers: { [name]: { serverUrl: windsurf } } }) } },
            { text: 'Refresh the MCP servers list.' },
          ],
          note: `With the Devin CLI: devin mcp add ${name} "<link>". ${keepPrivate}`,
        },
      ],
    },
    {
      id: 'other',
      icon: 'plug',
      name: 'Other clients',
      mono: '…',
      tint: '#6b7280',
      blurb: 'Gemini CLI, Cline, Zed and any MCP client',
      methods: [
        {
          id: 'link',
          label: 'Connection link',
          auth: 'link',
          intro: 'Any client that takes a remote (Streamable HTTP) server URL: paste the link, no headers or sign-in needed.',
          steps: [
            create('your app'),
            { text: 'Gemini CLI:', snippet: { lang: 'shell', label: 'Terminal', code: `gemini mcp add --transport http ${name} "${other}"` } },
            { text: 'Cline (MCP Servers → Remote Servers, or cline_mcp_settings.json):', snippet: { lang: 'json', file: 'cline_mcp_settings.json', code: json({ mcpServers: { [name]: { type: 'streamableHttp', url: other } } }) } },
            { text: 'Zed (settings.json):', snippet: { lang: 'json', file: 'settings.json', code: json({ context_servers: { [name]: { url: other } } }) } },
            { text: 'Continue (.continue/mcpServers/uncoder.yaml):', snippet: { lang: 'text', file: 'uncoder.yaml', code: `name: ${c.site}\nversion: 0.0.1\nschema: v1\nmcpServers:\n  - name: ${c.site}\n    type: streamable-http\n    url: ${other}` } },
          ],
          note: keepPrivate,
        },
        {
          id: 'stdio',
          label: 'Local (stdio) apps',
          auth: 'link',
          intro: 'For apps that only launch local servers. mcp-remote, a small open-source bridge, runs on your computer and forwards to this site (needs Node.js 18+).',
          steps: [create('your app'), { text: 'Use this as the server command:', snippet: { lang: 'shell', label: 'Command', code: `npx ${bridge(`"${other}"`).join(' ')}` } }],
          note: keepPrivate,
        },
        {
          id: 'http',
          label: 'API key in a header',
          auth: 'key',
          intro: 'For clients that send headers and when you prefer no key in the URL.',
          steps: [
            { text: 'Server URL:', url: true },
            { text: 'Send the API key as a bearer token in every request:', snippet: { lang: 'text', label: 'HTTP header', code: `Authorization: ${bearer}` } },
            { text: 'Clients that support OAuth 2.1 can sign in instead of using a key. They discover everything from:', snippet: { lang: 'text', label: 'Protected resource metadata', code: c.metadata } },
          ],
        },
      ],
    },
  ];
}
