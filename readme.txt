=== Uncoder – AI-Powered Website Builder ===
Contributors: uncoderstudio
Tags: page builder, website builder, theme builder, drag and drop, mcp
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Visual drag-and-drop page and theme builder with a built-in MCP server, so Claude, ChatGPT, Cursor and other AI apps can build your site with you.

== Description ==

Uncoder is a visual website builder for WordPress. Design pages with flexbox and grid containers and 80 widgets. Build headers, footers, single and archive layouts, 404 and search pages, popups, mega menus and loop items in the Theme Builder. Keep everything consistent with a global Design System of colors, fonts, text styles, buttons and spacing.

Its main feature is a built-in **Model Context Protocol (MCP) server**. Connect Claude, ChatGPT, Cursor, VS Code, Windsurf or any MCP client and ask it to build or change the site. The AI works with the same elements you edit by hand, so everything it makes stays fully editable in the visual editor.

Everything is free and GPL. There is no account, no upsell and no tracking.

= Build with AI =

* 58 tools. With them an AI can:
  * create and edit pages element by element
  * use 40 ready-made section patterns
  * build headers, footers, popups and mega menus
  * manage menus, the Design System, media, posts, terms and SEO meta
* A build guide and widget schemas the AI reads before it builds, so settings are valid and designs stay on-brand.
* A page audit for accessibility, headings, contrast, responsive layout, placeholder copy and SEO. It gives element ids so the AI can fix what it finds.
* Every AI change is snapshotted and can be undone. Destructive actions need confirmation.
* Live sync: when a page is open in the editor, changes an AI makes appear instantly with an Undo button.
* Secure connections:
  * OAuth 2.1 with PKCE (one-click connect from claude.ai and ChatGPT), or API keys with scopes (read, content, design, site)
  * rate limits
  * an activity log with per-change undo
* The tools are also registered as WordPress Abilities (WordPress 6.9+), so other AI integrations can use them.
* Optional AI writing and AI images inside the editor, with your own API key.

= Visual editor =

* Fast editor with a live canvas that renders your real theme.
* Flexbox and CSS grid containers.
* Responsive settings for every breakpoint, and custom breakpoints.
* Undo and redo, history, revisions and autosave.
* Layers (navigator) and a command palette.
* Copy and paste, also between pages, including styles only.
* Find and replace, keyboard shortcuts, notes, and an accessibility checker.
* Global classes and components. A component is a reusable section whose placements can override its properties.
* Library of section patterns and saved templates, and starter sites to begin from.
* Dynamic tags: post title, featured image, custom fields, site logo, author and more.
* Display conditions for any element, by login, role, date and time, page, taxonomy, URL parameter, cookie, browser, operating system, language and more. Any element can also be hidden per device.
* Motion effects: entrance animations, scroll and mouse effects, sticky elements, hover transforms, shape dividers and masks.
* An icon library with more than 9,000 free icons: Lucide, Font Awesome (solid, regular, brands), Phosphor, Bootstrap Icons, Feather, Heroicons and Themify. Icons render as inline SVG, so no icon fonts load. You can also upload your own icon sets (IcoMoon, Fontello or SVG).
* Custom fonts: upload WOFF2, WOFF, TTF or OTF files, variable fonts too. Weight and style are read from the file. Adobe Fonts projects are supported too.

= Widgets =

**Basic:**

* heading, text, button, image, icon
* divider, spacer, alert, blockquote
* HTML, shortcode, menu anchor

**Media:**

* image box, image carousel, gallery, image compare
* video, video playlist, slides
* Lottie, hotspots
* Google Maps, SoundCloud, Facebook embed

**Content:**

* icon box, icon list, tabs, accordion, carousel
* testimonial, testimonial carousel, team member
* price table, price list
* counter, progress bar, star rating, countdown
* flip box, call to action, animated headline
* timeline, steps, table, code highlight
* marquee, logo grid, text path
* social icons, share buttons, link in bio

**Site and navigation:**

* nav menu, off-canvas, search with live results
* login, breadcrumbs, table of contents
* site logo, site title, site tagline
* language switcher, dark mode switch, reading progress, sitemap

**Posts and archives:**

* posts, loop grid, loop carousel, loop filter
* post title, post content, post excerpt, post info, post navigation, post comments
* featured image, author box
* archive title, archive description, template

**Forms:** the form widget, with many field types, file uploads, multi-step forms and conditional fields.

= Theme Builder =

Build all of these, each with display conditions:

* headers, footers, sticky and transparent headers
* single posts, pages and custom post types
* archives and search results
* 404 pages
* popups
* mega menus
* loop items and reusable sections

Templates show live thumbnails and a preview. The Theme Builder works with classic and block themes.

= Popups and forms =

* Popups come as modals, slide-ins, bars and full screen.
* Popup triggers: page load, scroll, exit intent, click, inactivity.
* Popup targeting: referrer, URL parameters, visit count, schedule, browser. Frequency limits too.
* Forms can send email and auto-replies, redirect, call webhooks, and post Slack or Discord notifications.
* Forms can subscribe people to Mailchimp, MailerLite, Brevo or ActiveCampaign.
* Spam protection: honeypot, rate limiting, Cloudflare Turnstile, hCaptcha or Google reCAPTCHA (v2 and v3).
* Submissions are stored in WordPress and can be exported. You choose how long to keep them, and they are covered by WordPress's personal data export and erase tools.

= Site tools =

* Multilingual with WPML or Polylang. Templates, popups, mega menus and loop items follow the visitor's language.
* Import and export templates, pages and the Design System. Site kits move a whole site to another WordPress install.
* **Import from Elementor**: turn existing Elementor pages and templates into editable Uncoder pages.
* Maintenance and coming-soon mode, a cookie consent banner, and custom code snippets (head, body, footer).
* Structured data (JSON-LD), a page preloader and page transitions.
* A role manager decides who may use the builder and how much they may change.
* An element manager lets you turn off widgets you don't use.
* Support tools: system info, safe mode for troubleshooting, and rollback to an earlier version from WordPress.org.

= Performance, accessibility and privacy =

* Server-rendered HTML and per-page CSS files. Widget CSS and JavaScript load only where they are used (the front-end runtime is about 2 KB).
* The hero image is prioritized, and images, background images and embeds lazy load.
* Fonts come from the Google Fonts CDN, are self-hosted on your server, or are not loaded at all.
* Accessible markup: landmarks, keyboard support in interactive widgets, reduced-motion support, and a built-in accessibility checker.
* No tracking, no account needed, and no data is sent to Uncoder.

== Installation ==

1. Install and activate the plugin from **Plugins → Add New**, or upload the zip file there.
2. Edit any page with **Edit with Uncoder**, or create a header in **Uncoder → Theme Builder**.
3. To build with AI, open **Uncoder → AI & MCP** and follow the steps for your AI app.

Documentation: https://builder.uncoder.co/documentation

== Frequently Asked Questions ==

= Is Uncoder free? =

Yes. Every feature described here is included and licensed under the GPL. There is no account to create.

= Which AI apps can connect? =

Any MCP client that supports remote (Streamable HTTP) servers can connect: Claude (claude.ai, Claude Desktop, Claude Code), ChatGPT, Cursor, VS Code, Windsurf and others. Clients that only support local (stdio) servers can use the small `@uncoder/mcp` bridge from npm.

= Does the AI get access to my whole site? =

Only what you approve. OAuth connections and API keys have scopes (read, content, design, site) and act as your WordPress user, so they can never do more than your account can. You can revoke access at any time, every call is logged, and changes can be undone.

= Do I need an AI subscription to use Uncoder? =

No. The visual editor, Theme Builder and every widget work without any AI. AI features are optional.

= Can I move my Elementor pages to Uncoder? =

Yes. **Uncoder → Settings → Import & export → Import from Elementor** converts Elementor pages, templates and global settings into Uncoder pages you can edit. Your Elementor data is left untouched.

= What happens to my pages if I deactivate the plugin? =

Pages keep a plain HTML copy of their content, so your text and images stay visible. The layout styling needs the plugin.

= Does it work with my theme? =

Yes. Uncoder pages use a full-width template inside your theme, or a blank canvas. Theme Builder headers and footers replace the theme's own, in both classic and block themes.

= Is it multilingual? =

Yes, with WPML or Polylang. Pages are translated like any other page. Theme Builder templates, popups, mega menus and loop items are translated in the Theme Builder, and each language shows its own version.

== External services ==

This plugin connects to the following third-party services only in the situations described.

= Google Fonts =

This applies when a page uses a Google Font and **Font delivery** is set to "Google Fonts CDN" (the default).

* Visitors' browsers load the font stylesheet and files from fonts.googleapis.com and fonts.gstatic.com. The visitor's IP address and browser details are sent to Google.
* With "Self-hosted", the plugin downloads the font files once from the same Google servers into your uploads folder. Visitors' browsers never contact Google.
* With "Do not load fonts", no request is made.

Google Fonts: https://fonts.google.com/ — Privacy policy: https://policies.google.com/privacy — Terms: https://developers.google.com/fonts/terms

= Adobe Fonts (optional) =

This applies only when an administrator enters an Adobe Fonts Web Project ID in **Uncoder → Design System → Custom fonts**.

* Your server reads that project's stylesheet once from use.typekit.net to learn its font families.
* On pages that use one of those fonts (and in the editor), visitors' browsers load the stylesheet and font files from Adobe (use.typekit.net). This sends the visitor's IP address and browser details to Adobe.

Adobe Fonts: https://fonts.adobe.com/ — Privacy policy: https://www.adobe.com/privacy.html — Terms: https://www.adobe.com/legal/terms.html

= Form spam protection: Cloudflare Turnstile, hCaptcha, Google reCAPTCHA (optional) =

This applies only when an administrator enters CAPTCHA keys in **Uncoder → Settings → Forms** and a form turns on "CAPTCHA".

* Pages with such a form load the provider's script, which checks the visitor's browser.
* Each submission's CAPTCHA answer is sent from your server to the provider for verification, together with the visitor's IP address.

Cloudflare Turnstile: https://www.cloudflare.com/products/turnstile/ — Privacy policy: https://www.cloudflare.com/privacypolicy/ — Terms: https://www.cloudflare.com/website-terms/
hCaptcha: https://www.hcaptcha.com/ — Privacy policy: https://www.hcaptcha.com/privacy — Terms: https://www.hcaptcha.com/terms
Google reCAPTCHA: https://www.google.com/recaptcha/ — Privacy policy: https://policies.google.com/privacy — Terms: https://policies.google.com/terms

= Newsletter services: Mailchimp, MailerLite, Brevo, ActiveCampaign (optional) =

This applies only when an administrator connects a service with their own API key in **Uncoder → Settings → Forms** and a form turns on the "Newsletter" action. When a visitor submits that form, your server sends their email address and name (and the chosen list) to that service's API:

* Mailchimp: {datacenter}.api.mailchimp.com
* MailerLite: connect.mailerlite.com
* Brevo: api.brevo.com
* ActiveCampaign: your account's API address (*.api-us1.com and similar)

Mailchimp: https://mailchimp.com/ — Privacy policy: https://www.intuit.com/privacy/statement/ — Terms: https://mailchimp.com/legal/terms/
MailerLite: https://www.mailerlite.com/ — Privacy policy: https://www.mailerlite.com/legal/privacy-policy — Terms: https://www.mailerlite.com/legal/terms-of-service
Brevo: https://www.brevo.com/ — Privacy policy: https://www.brevo.com/legal/privacypolicy/ — Terms: https://www.brevo.com/legal/termsofuse/
ActiveCampaign: https://www.activecampaign.com/ — Privacy policy: https://www.activecampaign.com/legal/privacy-policy — Terms: https://www.activecampaign.com/legal/terms-of-service

= Slack, Discord and webhooks (optional form actions) =

This applies only when an editor adds a Slack or Discord webhook URL, or any webhook URL, to a form. Each submission's field values are then sent from your server to that address.

Slack: https://slack.com/ — Privacy policy: https://slack.com/trust/privacy/privacy-policy — Terms: https://slack.com/terms-of-service
Discord: https://discord.com/ — Privacy policy: https://discord.com/privacy — Terms: https://discord.com/terms

= Anthropic API (optional AI writing) =

This applies only when an administrator turns on "AI writing" in **Uncoder → AI & MCP** and enters their own Anthropic API key.

* When an editor clicks an AI action on a text field or image in the editor, that field's text or that image is sent from your server to the Anthropic API (api.anthropic.com). The rewritten text or alt text is returned.
* Nothing is sent otherwise, and visitors never contact Anthropic.

Anthropic: https://www.anthropic.com/ — Privacy policy: https://www.anthropic.com/legal/privacy — Commercial terms: https://www.anthropic.com/legal/commercial-terms

= OpenAI Images API or a compatible service (optional AI images) =

This applies only when an administrator enters their own API key under **Uncoder → AI & MCP → AI images**.

* When an editor clicks **Generate with AI** on an image field and submits a description, that description is sent from your server to the OpenAI Images API (api.openai.com), or to the compatible address the administrator entered.
* The generated image is saved to your media library.
* Nothing is sent otherwise, and visitors never contact the service.

OpenAI: https://openai.com/ — Privacy policy: https://openai.com/policies/privacy-policy/ — Terms: https://openai.com/policies/terms-of-use/

= Openverse (image search for AI clients) =

When a connected AI client calls the `search_images` tool, the search words are sent from your server to the Openverse API (api.openverse.org) to find openly licensed images. No personal data is sent. Image search can be turned off in **Uncoder → AI & MCP**.

Openverse: https://openverse.org/ — Terms: https://docs.openverse.org/terms_of_service.html — Privacy: https://wordpress.org/about/privacy/

= Images imported by AI clients =

When a connected AI client calls `upload_media` with a URL, your server downloads that image from the address the client provides and stores it in your media library. The address might be an image host returned by Openverse, such as Flickr or Wikimedia. Requests to private or local network addresses are blocked.

= OAuth client metadata =

When an AI app connects with OAuth and identifies itself with a Client ID Metadata Document (an https URL), your server fetches that document from the app's address to read its name and allowed redirect addresses. No site data is sent.

= WordPress.org (version rollback) =

This applies only when an administrator opens **Uncoder → Settings → Tools → Version rollback**.

* Your server asks the WordPress.org plugin directory (api.wordpress.org) which versions of Uncoder exist.
* If the administrator confirms a rollback, the chosen version is downloaded from downloads.wordpress.org and installed with the WordPress upgrader.

WordPress.org privacy policy: https://wordpress.org/about/privacy/

= Embeds chosen by editors =

These widgets embed third-party content only when an editor adds them to a page:

* Video: YouTube (youtube.com / youtube-nocookie.com, with preview images from i.ytimg.com) or Vimeo (player.vimeo.com)
* Video playlist
* Google Maps (maps.google.com)
* SoundCloud (w.soundcloud.com)
* Facebook embed (facebook.com)

Video, video playlist, SoundCloud, Facebook and Google Maps embeds can show a click-to-load preview, so nothing is requested from the provider until the visitor clicks. Share buttons, social icons and link-in-bio links are plain links, so nothing is loaded from social networks until a visitor clicks one.

YouTube / Google privacy policy: https://policies.google.com/privacy — Vimeo privacy policy: https://vimeo.com/privacy — SoundCloud privacy policy: https://soundcloud.com/pages/privacy — Meta (Facebook) privacy policy: https://www.facebook.com/privacy/policy/

= Custom code added by administrators =

**Uncoder → Custom code** offers ready-made examples for Google Analytics, Google Tag Manager and the Meta Pixel. They load nothing unless an administrator adds and enables them. With the cookie consent banner turned on, analytics and marketing snippets wait until the visitor agrees.

== Source code ==

The editor and front-end scripts in `assets/build/` are compiled with esbuild from the human-readable TypeScript/React sources included in the `src/` folder of this plugin. The build setup (`package.json`, `tsconfig.json` and `build.mjs`) is included in the plugin folder: run `npm install` and then `npm run build` there to rebuild them.

Third-party code bundled in the compiled files: React and React DOM (MIT), Zustand (MIT), Immer (MIT).

Bundled assets:

* Lucide icons (ISC; icons derived from Feather are MIT), see `assets/data/icons/LICENSES.txt`.
* The icon libraries in `assets/data/icons/` (see `assets/data/icons/LICENSES.txt`):
  * Font Awesome Free icons by Fonticons, Inc. (CC BY 4.0)
  * Phosphor Icons (MIT)
  * Bootstrap Icons (MIT)
  * Feather (MIT)
  * Heroicons by Tailwind Labs (MIT)
  * Themify Icons (SIL Open Font License 1.1)

  Brand icons are trademarks of their owners.
* Geist and Geist Mono fonts (SIL Open Font License 1.1, see `assets/fonts/GEIST-LICENSE.txt`).
* The Google Fonts catalog metadata (Apache 2.0).
* The lottie-web "light" player (MIT, `assets/vendor/lottie/`, see its LICENSE.md). It is shipped minified as published; source: https://github.com/airbnb/lottie-web
* The AI app logos on the AI & MCP screen, from Simple Icons (CC0). They are trademarks of their owners, shown only to identify compatible apps.

== Screenshots ==

1. The visual editor: live canvas, Layers and the inspector.
2. The Design System: global colors, fonts, text styles and buttons.
3. The Theme Builder with live thumbnails and display conditions.
4. AI & MCP: connect Claude, ChatGPT or Cursor in a few clicks.
5. Popups with triggers and targeting rules.
6. Forms with actions, newsletter services and spam protection.

== Changelog ==

= 0.1.0 =
* First release.

== Upgrade Notice ==

= 0.1.0 =
First release.
