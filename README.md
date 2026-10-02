# Uncoder

A free visual page and theme builder for WordPress — with an MCP server built in, so Claude, ChatGPT,
Cursor and other AI apps can build and edit your site with the same widgets you use.

- Visual editor with flexbox and grid containers, 80 widgets and 40 section patterns
- Theme Builder: headers, footers, post layouts, archives, 404 pages, popups and mega menus
- Design System: colors, fonts, text styles and buttons every page — and every AI — uses
- 58 MCP tools with permissions, an activity log and one-click undo

**Website:** [uncoderbuilder.com](https://uncoderbuilder.com) · **Documentation:** [docs.uncoderbuilder.com](https://docs.uncoderbuilder.com)

## Install

1. Download [**uncoder.zip**](https://github.com/UncoderBuilder/uncoder/releases/latest/download/uncoder.zip) from the latest release.
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the zip, install and activate it.

Requires WordPress 6.6 or newer and PHP 8.0 or newer. Works with any theme; the free
[Uncoder theme](https://github.com/UncoderBuilder/uncoder-theme) is an optional, lightweight companion.

## Build from source

This repository is the plugin folder itself. The compiled scripts in `assets/build` come from `src/`:

```bash
npm install
npm run build        # production bundles
npm run dev          # rebuild on change
npm run typecheck
```

Node 20 or newer.

## Support

Read the [documentation](https://docs.uncoderbuilder.com) or write to hello@uncoderbuilder.com.
Bug reports and ideas are welcome in [Issues](https://github.com/UncoderBuilder/uncoder/issues).

## License

GPLv2 or later. See [LICENSE](LICENSE). Bundled third-party assets and their licenses are listed in `readme.txt`.
