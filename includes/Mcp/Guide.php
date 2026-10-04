<?php
/**
 * Build guide served to AI clients (server instructions, get_build_guide topics, resources).
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Plain markdown, kept short and concrete: rules first, then copy-paste recipes.
 */
final class Guide {

	public static function instructions(): string {
		return implode(
			"\n",
			array(
				'You are connected to a WordPress site built with Uncoder, an AI-powered website builder (a visual page builder + theme builder).',
				'Workflow: 1) get_site_overview 2) get_build_guide (topic "overview", then as needed) 3) get_design_system / update_design_system to set the brand first 4) build pages with create_page and refine with edit_elements 5) headers/footers/popups with create_template 6) audit_page and fix issues.',
				'Pages are JSON element trees: containers (flex/grid) hold widgets. Call list_widgets and get_widget_schema(type) before using a widget you have not used yet — never guess setting keys; unknown keys are rejected with suggestions.',
				'Use Design System tokens: colors as "var(--uncoder-c-<id>)", text styles as typography {"preset":"h2"}. Keep one h1 per page, real copy (no lorem ipsum), meaningful alt text, and check mobile with *_mobile settings.',
				'Every write is snapshotted: undo_last_change reverts your last change. Prefer edit_elements for small changes instead of rewriting whole pages.',
			)
		);
	}

	/**
	 * @return array<string,string> topic => title
	 */
	public static function topics(): array {
		return array(
			'overview'      => 'Workflow, data model and golden rules (read first)',
			'layout'        => 'Containers, flex/grid, spacing and section recipes',
			'styling'       => 'Values, units, colors, typography, backgrounds, hover states',
			'responsive'    => 'Breakpoints and per-device settings',
			'widgets'       => 'Choosing widgets, schemas, nested widgets, dynamic data',
			'design-system' => 'Design System: brand colors, fonts, text styles, buttons',
			'editing'       => 'edit_elements operations, ids and undo',
			'animations'    => 'Timeline animations: presets, triggers, stagger, text splits, scroll scrubbing, easing',
			'theme-builder' => 'Headers, footers, single/archive/404 templates, conditions, popups, mega menus, loop items',
			'content'       => 'Posts, media, stock images, menus, SEO',
			'quality'       => 'Accessibility, performance and copy checklist',
			'recipes'       => 'Complete JSON examples: hero, features, testimonials, FAQ, CTA, footer',
		);
	}

	public static function topic( string $topic ): ?string {
		$method = 'topic_' . str_replace( '-', '_', $topic );
		return method_exists( self::class, $method ) ? self::$method() : null;
	}

	private static function topic_overview(): string {
		return <<<'MD'
# Uncoder build guide — overview

## Data model
A page is `{ elements: Element[] }`. Element = `{ "type": "…", "settings": { … }, "children": [ … ] }`.
- `container` is the only layout element (flexbox or CSS grid). Containers nest freely.
- Widgets (`heading`, `text-editor`, `button`, `image`, `icon-box`…) are leaves and must live inside a container.
- Nested widgets (`tabs`, `accordion`, `carousel`, `off-canvas`) own one container per item in `children`.
- Ids are optional when creating (the server assigns 7-char ids). Use the ids returned by get_page / create_page for edits.
- Only set what you need: every setting has a default. Settings are validated; errors say which key/value is wrong and what is allowed.

## Workflow that produces good sites
1. `get_site_overview` → know the theme, pages, menus and Design System.
2. `update_design_system` → brand colors (primary, secondary, accent, heading, text, surface, border…), heading/body fonts, text styles (display, h1–h6, body, lead, eyebrow, button), button style. Do this BEFORE pages so everything inherits it.
3. Header + footer as theme templates (`create_template` type header/footer, conditions `[{"type":"include","rule":"general"}]`), with `site-logo`, `nav-menu`, buttons.
4. Pages with `create_page` (sections top to bottom). Top-level containers are sections (boxed by default, content centred at the site width). Inside, use nested containers for rows/columns/cards.
5. Images: `search_images` → `upload_media` (imports into the media library) → use `{"id": attachment_id}` in image settings. Always add alt text.
6. `audit_page` → fix every error it reports, then `publish_page`.

## Golden rules
- Exactly one h1 per page (usually the hero title). Headings in order (h2 for section titles, h3 for cards).
- Use Design System tokens: colors `var(--uncoder-c-primary)`, typography `{"preset":"h2"}`. Do not hard-code the same hex in many places.
- Design variables (sizes): `update_design_system {"variables":[{"id":"space-md","name":"Space md","group":"spacing|size|radius|other","value":"24px"}]}` prints `--uncoder-v-space-md`. Use them in size fields as `{"size":"var(--uncoder-v-space-md)","unit":"custom"}` and in spacing / radius fields per side: `{"top":"var(--uncoder-v-space-md)","right":"var(--uncoder-v-space-md)",…,"unit":"px"}`. Values may be fluid: `clamp(1rem, 3vw, 2rem)`.
- Query loop on a container: `"_loop": true` repeats the container for each item on the live page. Posts: `"_loop_query": {"source":"posts|current|related|manual","post_type":"post","posts_per_page":6}`; terms: `"_loop_source":"terms","_loop_taxonomy":"category","_loop_terms_scope":"all|post","_loop_terms_orderby":"name|count|term_order|id","_loop_terms_number":12,"_loop_terms_hide_empty":true`. Inside, bind dynamic tags (post-title, post-url, featured-image, post-excerpt…; term-name, term-url, term-description, term-count). Put the looping card inside a grid / row container; `_loop_empty` is the text when nothing is found.
- `"locked": true` on an element (next to "id"/"type") locks it in the editor; leave locked elements as they are unless the user asks.
- Section rhythm: top-level containers of pages automatically get the Design System section spacing (layout.section_space: 96px desktop, 72 tablet, 56 mobile) as top/bottom padding — set `_padding` only to override (e.g. a taller hero, or 0 for a full-bleed band). Alternate backgrounds (white / surface / dark) so sections read as separate bands.
- If the user has the page open in the Uncoder editor, your saved changes appear there live (with an Undo button for them).
- Rows: `"direction":"row"` + `"direction_mobile":"column"` + a `gap`. Grids: `"layout":"grid"`, `grid_columns` 3, `grid_columns_tablet` 2, `grid_columns_mobile` 1.
- Real, specific copy for the business. No lorem ipsum, no invented statistics or fake testimonials presented as real — use clearly marked placeholders like [Customer name] when facts are unknown.
- Prefer widget settings over `_custom_css`. Custom CSS is a last resort. It is responsive: `_custom_css_tablet`, `_custom_css_mobile` (and other active breakpoints) add CSS for that width and smaller; `selector` targets the element. Every element is ONE tag with the classes `uncoder-{type} uncoder-{id}` (e.g. `class="uncoder-heading uncoder-ab12cd3"`); it has an id attribute only when `_css_id` (Anchor ID, for #links) is set. `selector` compiles to `.uncoder .uncoder-{id}`. Parts are `.uncoder-{type}__part`, the shared button style is `.uncoder-btn`, SVG icons `.uncoder-svg`; containers carry only non-default modifiers (`uncoder-container--boxed`, `--grid`; flex and full width are the defaults).
- Small change? Use `edit_elements` (update/insert/move/delete by id) instead of rewriting the page.
MD;
	}

	private static function topic_layout(): string {
		return <<<'MD'
# Layout

## Container settings (most used)
| key | values |
|---|---|
| layout | "flex" (default) \| "grid" |
| content_width | "boxed" (default for top-level) \| "full" (default for nested) |
| direction (+_tablet/_mobile) | "column" (default) \| "row" \| "row-reverse" \| "column-reverse" |
| justify (+responsive) | flex-start, center, flex-end, space-between, space-around, space-evenly |
| align (+responsive) | flex-start, center, flex-end, stretch, baseline |
| gap / row_gap (+responsive) | {"size":24,"unit":"px"} or "24px" |
| wrap | "nowrap" \| "wrap" (flex only) |
| width (+responsive) | column width inside a row, e.g. "50%" or {"size":40,"unit":"%"} |
| min_height | e.g. "80vh" for heroes |
| grid_columns (+responsive) | number of equal columns (grid) — or grid_template "2fr 1fr" |
| background | {"type":"classic","color":"var(--uncoder-c-surface)"} \| {"type":"classic","image":{"id":123},"size":"cover","position":"center center"} \| {"type":"gradient","color":"#1e1b4b","color_b":"#4338ca","gradient_angle":{"size":135,"unit":"deg"}} |
| background slideshow | {"type":"slideshow","slides":[{"id":1},{"id":2},{"id":3}],"slide_duration":5000,"slide_transition":""(fade)\|"slide","slide_speed":1000,"ken_burns":"in"\|"out","color":"#0b1f2e"} — only the first image loads with the page |
| bg_motion + bg_motion_speed (1–10) | moving background image (classic image or slideshow): "parallax", "zoom-in", "zoom-out", "mouse" — works on phones, unlike attachment "fixed" |
| marquee (widget) | items of text (+ optional icon) and images (`{"type":"image","image":{"id":…}}`) mixed; `direction` "left" \| "right" \| "up" \| "down" (+ `height` for up/down); `lanes` "2" / "3" rows (columns when vertical) with `alternate`; images: `image_height` (horizontal), `image_width` (vertical), `image_ratio` "1/1" \| "4/3" \| "3/2" \| "16/9" \| "3/4" \| "2/3", `image_radius`, `image_gray`. Logo walls: 2 rows, image_gray, separator {"library":"none"}; photo columns: up, 3 lanes, image_ratio "3/4" |
| bg_animation | animated layer behind the content: CSS "style-1"…"style-5" (soft drifting gradients) or WebGL "fluid-gradient", "borealis", "gradient-mesh", "mist", "mystic-lake", "noir-haze", "void-wave", "halftone", "the-shining", "phase-tunnel", "plasma-line", "light-strings", "flame", "pulse-bubble", "neon-eclipse", "echo-sphere", "liquid-mask", "liquid-image" (+ `bg_anim_image` {"id":…} or the background image), "bit-wave", "flux-stripes", "perspective-grid". Colours `bg_anim_color_1`…`_4` + `bg_anim_bg` (empty = Design System), `bg_anim_speed` (1–100, default 20), `bg_anim_scale`, `bg_anim_intensity`, `bg_anim_noise` (0–100), `bg_anim_angle` (0–360), `bg_anim_offset_x/y`, `bg_anim_freeze` + `bg_anim_frame`, `bg_anim_interactive`. Use on a hero or CTA (one or two per page) and keep text contrast with `text_color` or an overlay |
| shape_top / shape_bottom | SVG edge: tilt, opacity-tilt, triangle, triangle-asym, arrow, split, curve, curve-asym, book, wave, waves, mountains, zigzag, pyramids, clouds. With `shape_bottom_color` (= the next section's background), `shape_bottom_height` (+_mobile, default scales 36–96px), `shape_bottom_width` (100–300%), `shape_bottom_flip`, `shape_bottom_invert`, `shape_bottom_front` |
| overlay + overlay_opacity | same shape as background, drawn over it (e.g. dark overlay on photos) |
| text_color | inherited text color for everything inside (use on dark sections) |
| border, radius, shadow | card styling |
| tag | div, section, header, footer, main, article, aside, nav, a (+ link) |

Every element (containers and widgets) also has the Advanced settings: `_margin`, `_padding` (dimensions), `_width` ("full"/"auto"/"custom" + `_custom_width`), `_align_self`, `_order`, `_position` + `_offset`, `_z_index`, `_background`, `_border`, `_radius`, `_shadow`, `_animations` (timelines, topic "animations"), `_animation` ("fade-up"…), `_hide_desktop/_hide_tablet/_hide_mobile`, `_css_id`, `_css_classes`, `_custom_css`, and the scroll & mouse effects below.

## Motion & effects (any element)
- Animations (the richest option; read topic "animations"): `_animations` takes preset names — `"fade-up"`, `["words-rise","hover-lift"]` — or full timelines with triggers (scroll into view, load, scroll scrub, hover, click, loop), stagger and text splits. Prefer it for new work.
- Simple entrance: `_animation` ("fade-up", "zoom-in"…; `_animation_duration`, `_animation_delay` in ms) plays once when the element scrolls into view.
- While scrolling (speeds −10…10): `_motion_y` (positive rises faster than the page, negative lags = parallax), `_motion_x`, `_motion_rotate`; `_motion_scale` "in" (grow into place) \| "out" \| "in-out" \| "grow" \| "shrink" with `_motion_scale_amount` (%); `_motion_fade` / `_motion_blur` "in" \| "out" \| "in-out". `_motion_start` / `_motion_end` (0–100) narrow the part of the trip across the screen that drives them.
- Pointer: `_motion_mouse` "track" (follows the cursor) or "tilt" (3D tilt on hover) with `_motion_mouse_speed` (−10…10). Desktop pointers only.
- `_motion_devices` ["desktop","tablet"] limits effects to some devices. Effects add to `_transform`, never run in the editor or for visitors who prefer reduced motion.
- Text reveal (heading, text-editor, post-title, archive-title, site-title, site-tagline, post-excerpt, blockquote): `_reveal` "words" \| "letters" \| "lines" with `_reveal_effect` "fade-up" \| "fade" \| "blur" \| "mask" (slides up from behind a mask) \| "rotate"; `_reveal_stagger`, `_reveal_duration`, `_reveal_delay` in ms. `_reveal_trigger` "scroll" makes it follow the scroll: the text starts faint (`_reveal_dim`, default 0.2) and lights up piece by piece as the visitor scrolls, dimming again on the way back (the "statement" look: words + blur + scroll, `_reveal_blur` px, default 4). Links and bold text survive the split. Best on one hero title or a section heading, not on body copy; do not combine with `_animation` on the same element.
- Lottie animations: the `lottie` widget plays a Lottie JSON file (`file` from the media library or `url`) with `trigger` "autoplay" (while visible) \| "hover" \| "click" \| "scroll" (follows the scroll), `loop`, `speed`, `label` for screen readers. Ask the user for the JSON file; do not invent URLs.
- Sticky: `_sticky` "top" \| "bottom" with `_sticky_offset` (px) and `_sticky_on` (devices, e.g. ["desktop","tablet"]; default every device).
- Mask (any element, great on images and videos): `_mask` "circle" \| "squircle" \| "blob" \| "arch" \| "hexagon" \| "triangle" \| "diamond" \| "star" \| "heart" \| "bubble" \| "flower" \| "wave" (wavy bottom edge), "fade-bottom" \| "fade-top" \| "fade-y" \| "fade-x" (a gradient fade, `_mask_fade` its length, e.g. a wall of cards fading out at the bottom), or "custom" with `_mask_image` (an SVG / PNG: opaque parts show). `_mask_size` "" (fit) \| "cover" \| "stretch" \| "custom" (+ `_mask_scale`), `_mask_position` ("top center"…), `_mask_repeat`.
- Taste: one or two effects per page (a lagging hero title, a parallax image, tilting cards), speeds 2–4. Heavy motion on body text hurts reading.

## Global classes (shared styles)
Define a style once and reuse it: update_design_system `{"classes":[{"id":"card","name":"Card","type":"container","settings":{"_padding":"32px","background":{"type":"classic","color":"var(--uncoder-c-surface)"},"radius":"16px","shadow":{"x":0,"y":12,"blur":32,"spread":-12,"color":"rgba(15,23,42,.18)"}}}]}`, then give elements `"_classes":["card"]` instead of repeating those settings. A class belongs to one element type; its settings are that type's style/layout keys. The element's own settings still override the class, so leave them out unless the element differs. Changing the class later restyles every element that uses it.

## Components (sections with properties)
A section template can expose settings as properties: create_template `{"type":"section","page_settings":{"component_props":[{"key":"title","label":"Title","element":"<heading id>","setting":"title"},{"key":"image","label":"Image","element":"<image id>","setting":"image"}]},"elements":[…]}` (give those elements fixed ids). Place copies with the template widget and change only the properties: `{"type":"template","settings":{"template_id":"<id>","overrides":{"title":"Faster installs","image":{"url":"…"}}}}`. Everything else stays in sync with the section — use this for repeated cards, testimonials and CTAs.

## Dark mode
update_design_system `{"settings":{"color_scheme":"auto"},"colors":[{"id":"text","dark":"#d7dde2"},{"id":"heading","dark":"#ffffff"},{"id":"surface","dark":"#121820"}…]}` ("toggle" starts light, "auto" follows the device) and add the `scheme-switch` widget to the header. Only kit colors (var(--uncoder-c-…)) change in dark mode: use them instead of hex values. Swap logos or images per scheme with `_hide_dark` / `_hide_light`.

## Filters, portfolios and forms
- Live filters: put `loop-filter` widgets above a `loop-grid` or `posts` widget — `{"filter":"taxonomy","taxonomy":"category","display":"pills"|"dropdown"|"checkboxes","show_count":true}`, `{"filter":"search"}`, `{"filter":"sort","sorts":["date_desc","title_asc"]}`. They filter without reloading and keep a shareable URL. With several grids on one page give each grid a `_css_id` and set the filter's `target` to it.
- Portfolio / filterable gallery: `image-gallery` with `"filterable":true,"groups":[{"title":"Residential","images":[{"id":…}]},{"title":"Commercial","images":[…]}]` (layout "masonry" looks best).
- Custom fields: dynamic tags `acf-field` (options field = ACF field key, source post|term|author|option) and `metabox-field` exist when those plugins are active; `post-meta`-style "Custom field" works for plain meta keys.
- Forms: split long forms into steps with `{"type":"step","label":"Details"}` rows (progress bar, Next/Back); show a field only when another answer matches with `"show_if_field":"project_type","show_if_op":"is","show_if_value":"Commercial"` (hidden fields are not required); `"captcha":true` adds the Turnstile / hCaptcha / reCAPTCHA v3 an administrator configured.
- Form actions after submit (besides `email` and `webhook_url`): confirmation email to the visitor `"autoreply":true,"autoreply_subject":"Thanks, [name]","autoreply_message":"…[all-fields]…"` (`autoreply_to` = the email field id, default the first email field); mailing lists `"newsletter":true,"newsletter_service":"mailchimp"|"mailerlite"|"brevo","newsletter_list":"<audience / group / list id>"` (+ `newsletter_consent` = id of an opt-in checkbox field, `newsletter_double` for Mailchimp double opt-in) — the API key is connected by an administrator in Settings → Forms, never in the page; team alerts `slack_webhook` / `discord_webhook` (incoming webhook URLs the user gives you).
- Loop carousel: `loop-carousel` shows the same loop-item template cards as `loop-grid`, in a swipeable carousel: `{"loop_template":"<id>","query":{"source":"posts","post_type":"post","posts_per_page":8},"slides_per_view":3,"slides_per_view_mobile":1.15,"arrows":true,"pagination":"dots","autoplay":false}`.
- Hero slider: `slides` (not nested, no children) is a full-width image slider: `{"slides":[{"image":{"id":123},"heading":"…","description":"…","button_text":"Book now","link":{"url":"/book"}}],"height":{"size":80,"unit":"vh"},"transition":"slide"|"fade","autoplay":true}` — 2–5 slides with short headings; per slide `h_position`/`v_position`/`text_align`, `ken_burns` "in"|"out", `link_whole`, `overlay_color`/`overlay_opacity`; `heading_tag:"h1"` only when it is the page hero (its first image then loads eagerly by itself).
- Video playlist: `video-playlist` = player + clickable list: `{"videos":[{"title":"Lesson 1","url":"https://www.youtube.com/watch?v=…","duration":"4:32","description":"…"}],"list_position":"right","list_position_mobile":"below","autoplay_next":true}`. Rows take YouTube / Vimeo / .mp4 links (or `"file":{"id":…}`); give Vimeo and file rows a `thumbnail`. Embeds load only when played. Ask the user for the links.
- Audio & social embeds: `soundcloud` `{"url":"https://soundcloud.com/artist/track","player":"visual"|"classic","color":"ff5500"}`; `facebook-embed` `{"embed_type":"page","page_url":"…","tabs":["timeline"]}` / `{"embed_type":"post","post_url":"…","height":"640px"}` / `{"embed_type":"video","video_url":"…"}`. Both load behind a click-to-load privacy facade (no third-party request before a click).
- Maps: `google-maps` `{"address":"…","zoom":14,"height":{"size":400,"unit":"px"}}`; add `"click_to_load":"yes"` (a local placeholder, Google loads only on click) for sites in the EU/UK or when the user cares about privacy/GDPR.
- Text on a curve: `text-path` `{"path":"circle"|"wave"|"arc"|"oval"|"line"|"spiral"|"custom","text":"Scroll down • Scroll down • ","spin":true}` (custom = `custom_path` + `custom_viewbox`; `reverse` puts circle text inside). Short decorative text only.
- Sitemap: `sitemap` with `sections` `[{"source":"post_type","post_type":"page"},{"source":"post_type","post_type":"post","orderby":"date","order":"desc","limit":20},{"source":"taxonomy","taxonomy":"category","show_count":true}]` — for a Sitemap or 404 page; it updates itself.
- Link in bio: create_page with `"template":"uncoder-canvas"` (no theme header/footer) and one `link-in-bio` widget `{"full_height":true,"name":"…","handle":"@…","bio":"…","links":[{"label":"My latest work","url":"…"}],"social":[…]}`; highlight at most one link; `link_style` filled|outline|soft.
- Outlined text: heading-type widgets (heading, post-title, archive-title, site-title, animated-headline) take `text_stroke_width` (`{"size":1,"unit":"px"}`) and `text_stroke_color`; with a transparent `color` the letters are outlined.
- Scroll snap: page_settings `{"scroll_snap":"proximity"|"mandatory","scroll_snap_align":"start"|"center"}` makes the live page stop at each top-level section (never in the editor; off for visitors who prefer reduced motion).
- Live search: `search-form` with `"live":true` lists matching published pages and posts while typing (`live_count` 1–10, `live_image`, `live_excerpt`, `live_all` for a "See all results" link; `post_type` limits it).
- Multilingual (WPML / Polylang): a translation starts as a copy of the design and is edited on its own; templates (header, footer, sections, loop items, popups, mega menus) resolve to the visitor's language once their translation is published. People translate templates in Theme Builder ("Translate into …") and pages with the multilingual plugin's own "+". Add the `language-switcher` widget to the header (`layout` "inline" \| "dropdown", `label_type` "name" \| "native" \| "code" \| "none", `flags`).

## Display conditions (any element)
Elements that fail are left out of the HTML on the site (the editor always shows them). Every rule that is set must pass:
- `_conditions`: rule sets — the element shows when ANY set matches; a set matches when ALL its rules do. Rule `{"key","op","value","name"?}`:
  - `login` is "in" \| "out" — e.g. a "Log in" button for out next to "My account" for in.
  - `role` is \| is_not "editor,customer" (any of). `post_type` is \| is_not "post,product". `page` is \| is_not {post id}.
  - `date` from \| until \| is "2026-12-01" or "2026-12-01 09:00" (site time zone) — limited offers. `time` from \| until "09:00". `weekday` is \| is_not "1,2,3,4,5" (1 = Monday).
  - `url_param` / `cookie` / `meta` (custom field) with `name`: exists \| not_exists \| is \| is_not \| contains (meta also greater \| less). `referrer` contains \| not_contains \| empty \| not_empty.
  - Example: `[[{"key":"login","op":"is","value":"in"}],[{"key":"url_param","name":"preview","op":"exists"}]]`. The old `_show_*` settings are converted automatically.
- `_interactions`: behaviour without code, run on the live page. Rows `{"trigger":"click|mouseenter|mouseleave|enter|leave|load|scroll","action":"toggle|show|hide|toggle_class|add_class|remove_class|set_attr|remove_attr|open_popup|close_popup|scroll_to","target":"self|element|selector","element":"<id>","selector":".css","value":"is-open | aria-pressed=true | <popup id>","offset":80,"delay":0}`. A box revealed by an interaction sets `"_ix_hidden": true`. Scroll triggers undo themselves above the offset (add_class ↔ remove_class, show ↔ hide).
- `_states`: styles for a state of the element: `{"hover": {…}, "focus": {…}, "active": {…}, "before": {…}, "after": {…}, "&.is-open": {…}, "& > .icon": {…}}`, each holding the same style settings as the element (responsive keys too), e.g. `{"hover": {"_opacity": 0.8, "_shadow": {…}}}`. `before` / `after` and selectors pointing elsewhere (`& > .x`) take the element-level style settings only (spacing, background, border, size…); `content:""` is added for pseudo-elements. Combine a custom state with an interaction: toggle_class "is-open" + `_states["&.is-open"]`.
Page caches can serve one version to everyone: mention it when you personalise a cached page.

## Dimensions (padding, margin, radius)
`{"top":80,"right":24,"bottom":80,"left":24,"unit":"px"}` — or CSS shorthand "80px 24px". Empty sides are left untouched.

## Patterns
- Section: top-level container (section spacing is automatic, see above), background band. Headers, footers, popups, mega menus and loop items get no automatic padding.
- Rows with `justify` center / flex-end / space-* or `wrap:"wrap"` let child containers size to their content (so the justification and wrapping work); give a child a `width` to control it. Other rows split the space between children.
- Two columns (text + image): container `direction:"row"`, `direction_mobile:"column"`, `align:"center"`, `gap:"64px"`; two child containers (`width:"50%"` each, or leave widths empty for equal).
- Card grid: container `layout:"grid"`, `grid_columns:3`, `grid_columns_tablet:2`, `grid_columns_mobile:1`, `gap:"24px"`; each card = container with `_padding` 32px, `background` surface/white, `radius` 16px, optional `border`/`shadow`.
- Centered intro: container `align:"center"` + heading/text with `align:"center"` and text `max_width` ~ 60ch.
- Hero: `min_height:"80vh"`, `justify:"center"`, background image + dark overlay (`overlay` color #000, `overlay_opacity` 0.55) + `text_color:"#fff"`.
MD;
	}

	private static function topic_styling(): string {
		return <<<'MD'
# Styling values

- Sizes: {"size":48,"unit":"px"} or "48px" / "3rem" / "60%" / "clamp(2rem, 5vw, 4rem)" (custom expression).
- Colors: any CSS color ("#0f172a", "rgba(0,0,0,.5)") or a Design System color "var(--uncoder-c-primary)" (shorthand "@primary" is accepted and converted).
- Typography group (on text parts, e.g. heading `typography`, button `typography`):
  `{"preset":"h2"}` uses the Design System text style; add overrides: `{"preset":"h2","size_mobile":"30px","weight":"600"}`.
  Fields: preset, family, size*, weight ("400"…"900", or any 1–1000 such as "650" for variable fonts like Geist or Inter), transform, style, decoration, line_height* (unitless {"size":1.2,"unit":""} or "1.2"), letter_spacing*, word_spacing*. (* responsive)
- Background group: see layout guide. Gradients: type "gradient", color, color_b, color_stop/color_b_stop (%), gradient_angle (deg), gradient_type "radial" + gradient_position.
- Border group: {"style":"solid","width":"1px","color":"var(--uncoder-c-border)"} (shorthand "1px solid #e5e7eb" accepted). Per-device values go inside the group: {"width":{"left":1,…},"width_mobile":{"left":0,…}} (not "border_mobile"). Radius is a separate dimensions setting.
- Box shadow: {"x":0,"y":12,"blur":32,"spread":-12,"color":"rgba(15,23,42,.18)"} or CSS "0 12px 32px -12px rgba(15,23,42,.18)".
- Hover: widgets expose hover_* settings (e.g. button hover_text_color, hover_background). Elements: `_transform_hover` {"translate_y":"-4px"} + `_transition` 250 and container `shadow_hover`.
- Entrance animation: `_animations` "fade-up" (or "cascade" on the parent container to stagger its children); the simple `_animation` fade-up | zoom-in … with `_animation_delay` still works.
- Buttons: `variant` primary (Design System style) | secondary | outline | ghost | link; `size` sm|md|lg|xl.
MD;
	}

	private static function topic_responsive(): string {
		return <<<'MD'
# Responsive design

Breakpoints (desktop-first): desktop (default), tablet (≤1024px), mobile (≤767px); laptop/widescreen may be enabled in the Design System.
Any responsive setting accepts a suffixed key: `gap_tablet`, `direction_mobile`, `_padding_mobile`, `grid_columns_mobile`. Inside groups the sub-field is suffixed: `"typography":{"preset":"display","size_mobile":"40px"}`.
Values cascade down: tablet inherits desktop, mobile inherits tablet — set only what changes.

Checklist for every section:
- Rows → `direction_mobile:"column"` (and often `direction_tablet:"column"` for 3+ columns).
- Grids → `grid_columns_tablet` and `grid_columns_mobile`.
- Big titles → smaller `size_mobile` (or use presets, which already scale).
- Custom section padding (if you set any) has a smaller `_padding_mobile`; gaps smaller on mobile.
- Hide decorative extras on mobile with `_hide_mobile:true` when they crowd the layout.
- Fixed pixel widths above ~340px need a `_mobile` override (or use %).
MD;
	}

	private static function topic_widgets(): string {
		return <<<'MD'
# Widgets

1. `list_widgets` (optionally with category) → pick the right widget. Prefer specialised widgets (icon-box for features, testimonial for quotes, price-table for pricing, accordion for FAQ) over stacks of headings and text.
2. `get_widget_schema(type)` → exact keys, allowed values, defaults and hints. Content keys first; style keys under "style".
3. Only send keys you change. Unknown keys and invalid values are rejected with a message naming the allowed values.

Inline HTML: headings/text fields marked "inline HTML" accept `<strong>`, `<em>`, `<span>`, `<a>`, `<br>`, `<mark>` (use `highlight_color` to color `<mark>/<strong>` inside a heading).
Rich text (`text-editor` content): paragraphs, lists, links. Plain text is auto-wrapped in <p>.
Icons: Lucide names by default, e.g. "check", "arrow-right", "star", "shield-check", "truck", "phone", "mail", "map-pin", "clock", "sparkles". Other libraries use "library:name": Font Awesome ("fa-solid:house", "fa-regular:heart", brand logos "fa-brands:whatsapp"), "phosphor:…", "bootstrap:…", "feather:…", "heroicons-outline:…", "heroicons-solid:…", "themify:…". Find names with search_icons (library "all" searches everything). Keep one library per page for a consistent look; brand logos are the usual exception.

Nested widgets (`tabs`, `accordion`, `carousel`, `off-canvas`): the repeater (`tabs`/`items`/`slides`/`panels`) defines titles; `children` holds one container per item, in the same order. Put the item's content inside that container:
```json
{"type":"accordion","settings":{"items":[{"title":"Do you deliver?"},{"title":"Can I pause?"}]},
 "children":[{"type":"container","children":[{"type":"text-editor","settings":{"content":"Yes, across the country."}}]},
             {"type":"container","children":[{"type":"text-editor","settings":{"content":"Anytime from your account."}}]}]}
```

Dynamic data (templates, loop items): settings marked dynamic accept `"dynamic": {"title": {"tag":"post-title"}}` on the element. Tags: post-title, post-excerpt, post-date, post-url, featured-image, post-terms, custom-field {key}, author-name, author-avatar, archive-title, site-title, site-logo, current-date, popup {popup id} (link that opens a popup), contact-url {type,value}. See list_dynamic_tags.
MD;
	}

	private static function topic_design_system(): string {
		return <<<'MD'
# Design System (design system)

`get_design_system` returns colors, fonts, text styles, buttons and layout. `update_design_system` merges what you send (lists merge by id):
```json
{
  "colors": [{"id":"primary","name":"Primary","value":"#1f4d3a"},{"id":"accent","name":"Accent","value":"#e3a33b"},
             {"id":"heading","value":"#10231b"},{"id":"text","value":"#3b4a43"},{"id":"surface","value":"#f5f1e8"}],
  "fonts": [{"id":"heading","family":"Fraunces"},{"id":"body","family":"Inter"}],
  "typography": [{"id":"display","value":{"size":"76px","size_tablet":"60px","size_mobile":"44px","weight":"600","line_height":"1.02","letter_spacing":"-1.5px"}},
                 {"id":"h2","value":{"size":"44px","size_mobile":"30px","weight":"600"}}],
  "buttons": {"background":"var(--uncoder-c-primary)","color":"#ffffff","hover_background":"#163a2b","radius":"999px","padding":"16px 30px"},
  "layout": {"container_width":"1240px","gutter":"24px","gutter_mobile":"18px"}
}
```
- Keep standard ids so widgets inherit correctly: primary, secondary, accent, heading, text, muted, surface, border, white, black.
- Fonts: any Google Font by name. Pair a characterful heading face with a readable body face; vary choices between sites.
- Text styles: display, h1–h6, lead, body, small, eyebrow, button. Headings inside widgets use them via `{"preset":"h2"}`.
- Contrast: text on backgrounds ≥ 4.5:1 (≥3:1 for large text). Check light text on brand colors.
- `revert_design_system` restores the previous version.
MD;
	}

	private static function topic_editing(): string {
		return <<<'MD'
# Editing existing pages

`get_page(id)` (format "outline" is compact; "tree" returns full JSON) → note element ids.
`edit_elements(id, operations)` applies operations in order, atomically (all or nothing):
- `{"op":"update","id":"k3f9a2x","settings":{"title":"New title","color":null}}` — merges; null deletes a key. Add `"replace":true` to replace all settings.
- `{"op":"insert","parent_id":"abc1234","position":"end","element":{…}}` — position "start" | "end" | index number; or `"before":"id"` / `"after":"id"`. `parent_id` null = top level (sections).
- `{"op":"insert", …, "elements":[…]}` inserts several.
- `{"op":"move","id":"…","parent_id":"…","position":0}` (or before/after).
- `{"op":"delete","id":"…"}`, `{"op":"duplicate","id":"…"}`, `{"op":"replace","id":"…","element":{…}}`,
- `{"op":"wrap","ids":["a","b"],"settings":{…}}` wraps siblings in a new container.
- `{"op":"set_dynamic","id":"…","key":"title","tag":"post-title"}` / `{"op":"unset_dynamic","id":"…","key":"title"}`.
Use `"dry_run": true` to validate without saving. `find_elements` searches by type/text. Every write returns an undo snapshot; `undo_last_change` reverts it.
MD;
	}

	private static function topic_animations(): string {
		$presets = '';
		foreach ( \Uncoder\Builder\Core\Animations::preset_index() as $group => $ids ) {
			$presets .= '- ' . $group . ': ' . implode( ', ', $ids ) . "\n";
		}
		return <<<'MD'
# Animations

Every element has `_animations`: a list of timelines, played by the browser's own animation engine (no library). They never run in the editor or for visitors who prefer reduced motion.

## Presets (easiest)
`"_animations": "fade-up"`, `"_animations": ["words-rise", "hover-lift"]` or `[{"preset":"pop","delay":200,"duration":1200,"trigger":"load"}]` — keys sent with a preset override it; `duration` stretches its steps.

MD
			. "\n" . $presets . "\n" . <<<'MD'
Text presets (words / letters / lines) work on heading, text-editor, post-title, archive-title, site-title, site-tagline, post-excerpt and blockquote. Stagger presets animate a container's child elements (or a widget's items) one after another.

## Full form
```json
{"trigger":"enter","target":"words","mask":true,"from":{"y":"120%"},
 "steps":[{"to":{"y":"0%"},"duration":900,"ease":"expo.out"}],"stagger":70}
```
- `trigger`: "enter" (scrolls into view; `replay` "once" | "every" | "reverse", `offset` 0–50 % from the bottom), "load", "scroll" (scrubbed by the scrollbar across `start`–`end` 0–100 of the element's trip through the screen, `smooth` 0–10 s catch-up), "hover" (plays back on leave; also keyboard focus), "click" (`toggle` true plays back on the second click), "loop" (forever, `yoyo` to go back and forth).
- `target`: "self" (default), "children", "words", "chars", "lines"; `mask` true slides split text out from behind its own edge (pair with y "120%"). `stagger` ms between items, `order` "start" | "end" | "center" | "edges" | "random".
- `from` is the start state; each step's `to` changes only the properties it lists, over `duration` ms with `ease`, after an optional `delay` hold. `delay` on the animation waits before it starts; `repeat` (−1 = forever) and `yoyo` for load / enter.
- State properties, relative to the element's own styles (y 0, scale 1, opacity 1 = as styled): opacity 0–1, x / y (px number or "50%", "2em", "10vh"), scale, scaleX, scaleY, rotate, rotateX, rotateY, skewX, skewY (degrees), blur (px), clip ("wipe-up", "wipe-down", "wipe-left", "wipe-right", "center-x", "center-y", "circle", "none").
- Eases (GSAP names): none, power1…power4, sine, expo, circ, back, elastic, bounce, each with .in / .out / .inOut; or "cubic-bezier(.2,.8,.2,1)".
- `devices`: ["desktop","tablet"] limits where it runs.

## Taste
One entrance style used consistently down the page (fade-up or cascade), a text reveal on the hero title only, scroll scrubbing on one or two images, hover on cards and buttons, loops only on small accents (badges, icons). Keep entrances around 0.6–1.2 s and staggers 50–120 ms.
MD;
	}

	private static function topic_theme_builder(): string {
		return <<<'MD'
# Theme builder

Template types: header, footer, single-post, single-page, single (custom post types), archive, search-results, error-404, section (reusable block), popup, mega-menu, loop-item.
`create_template({ "type":"header", "title":"Main header", "elements":[…], "conditions":[…] })`. Templates are published (live) by default; pass `"status":"draft"` to stage one and publish it later with `update_template`. Conditions default per type (header/footer: entire site; single-post: all posts; error-404: 404 page…). `get_conditions_map` shows what applies where.

## Conditions
`[{"type":"include","rule":"general"}]` = entire site. Rules:
- general
- singular (+ "post_type":"page"|"post"|… and optional "ids":[…]); front_page; posts_page; not_found (404); search
- archive (+ "post_type" for CPT archives, or "taxonomy":"category" + optional "ids" for terms); author; date
- in_term ("taxonomy","ids") and child_of ("ids") for singulars
Exclude with `"type":"exclude"`. The most specific matching template wins; exclusions always win.

## Site behaviour
- Smooth #anchor scrolling is on by default and stops below a sticky header (Design System `settings.smooth_scroll`, gap `layout.scroll_offset`). Link buttons/menu items to sections with "#section-id" and give sections `_css_id`.
- One-page menus: nav-menu links to "#sections" of the current page highlight while scrolling (`highlight_anchors`, on by default). On other pages link to "/#section".
- Menu hover (desktop): one `hover_effect`: "underline" (animated underline, the default; `underline_from` "start"/"center", color `pointer_color`), "flip" (text flip; `flip_by` "word"/"letter"), "magnet" (pill that leans to the pointer; `magnet_strength` 0.05–0.6), "focus" (other items dim; `focus_opacity`), "highlight" (one pill slides between items and rests on the current page) or "none" (color only). The pill color of "magnet" and "highlight" is `hover_bg`.
- Menu items can have an `icon` (a Lucide name such as "users", or "library:name" from another library or a custom set, before the label) and a short one-line `description` (under the label in dropdowns and the mobile menu): set them in create_menu / update_menu items. For a dropdown laid out like a small mega menu (title + description per item), set the nav-menu `dd_columns` "2" (or "3"/"4"); `show_icons` / `show_descriptions` turn them off per widget. `mobile_menu` (a menu id, like `menu`) shows a different menu in the mobile menu; empty = the same menu.
- Modern mobile menu: nav-menu `mobile_mode:"panel"` (a full-width sheet hanging from the bottom of the header, airy rows, accordion submenus) + `toggle_style:"lines"` (two lines that turn into an X) + `m_button_text`/`m_button_link` for a full-width button at the end of the menu. Give it the header's background (`m_bg`) and side padding (`m_padding`, e.g. 12px 16px 24px 16px on phones) so it reads as part of the header.
- Back-to-top button: update_design_system `{"settings":{"back_to_top":true,"back_to_top_position":"right"}}`.
- Button hover animation for the whole site: update_design_system `{"buttons":{"hover_effect":"lift"}}` ("grow", "shine", "fill", "fill-up" + `hover_fill` colour, "underline", "flip" = text roll, "arrow"…). A button's own `hover_effect` wins.
- Page transitions: `{"settings":{"page_transition":"fade"|"slide"}}` (View Transitions, no delay). A preloader (`{"settings":{"preloader":"spinner"|"bar"|"logo"}}`) hides the page until it loads: only when the user asks for one.
- Reading progress: put a `reading-progress` widget (position "top" \| "bottom" \| "inline", track "content" \| "page" \| "element" + `target` CSS ID) in the single post template or the header.
- Cookie banner: an administrator turns it on under Uncoder → Settings → Cookie consent and marks analytics/marketing snippets in Settings → Custom code; they then wait for consent. A footer link to "#uncoder-consent" reopens the choices. Not configurable through MCP.
- Moving a whole site: Uncoder → Settings → Import & export makes one zip (Design System, theme templates, pages, menus, media, fonts, settings without secrets, snippets) that another Uncoder site imports, with every link and id pointed at the new site. It is a person's action in wp-admin, not an MCP tool.
- Elementor sites: when get_site_overview or list_pages shows pages built with Elementor, do not rebuild them by hand first. Tell the user about Uncoder → Settings → Import & export → Import from Elementor, which converts Elementor pages, templates (header, footer, popups, loop items) and the global colors and fonts into editable Uncoder designs (copy or replace; Elementor data is left untouched). Then refine the converted pages with get_page / edit_elements.
- Popup targeting (popup settings `rules`): `{"referrer":"search"|"external"|"internal"|"direct"|"contains","referrer_value":"facebook.com","url_param":"utm_campaign=spring","sessions":3,"schedule":{"enabled":true,"from":"2026-11-27","until":"2026-11-30T23:59","timezone":"site"|"visitor"},"browsers":["chrome","safari"]}` — they limit automatic triggers only (a link to the popup always opens it) and work on cached pages.
- Lightbox: Design System `settings.lightbox_auto` (on by default) opens every link to an image file (also in blog posts and WordPress galleries) in the lightbox; `lightbox_bg`, `lightbox_ui`, `lightbox_caption`, `lightbox_counter`, `lightbox_download`. Add `data-uncoder-no-lightbox` to a link to skip it.
- Background images below the first section load as visitors scroll near them (Settings → General → Performance → lazy backgrounds, on by default). Put the hero image in the first section.
- Icons: besides Lucide and the bundled sets, administrators can upload IcoMoon / Fontello / SVG icon sets (Design System → Custom icons); use them as `{"library":"custom-<set>","value":"<name>"}` only when they exist (search_icons / the icon list shows them). Adobe Fonts project families appear with the custom fonts and can be used like any font family.
- Widgets an administrator turned off (Settings → Elements) are not offered in the editor: avoid them in new designs (existing pages keep rendering them).
- Coming soon / maintenance: build the page first (create_page, a draft is fine), then update_site_settings `{"maintenance":{"mode":"coming_soon"|"maintenance","page_id":…}}`; `{"maintenance":{"mode":""}}` opens the site again. Ask the user before closing a live site.
- Structured data: when the user gives business details, update_site_settings `{"business":{"enabled":true,"type":"HomeAndConstructionBusiness","name":…,"phone":…,"street":…,"city":…,"postal":…,"country":"US","hours":["Mo-Fr 08:00-17:00"],"same_as":[…]}}` prints LocalBusiness JSON-LD on the home page. FAQ sections: accordion with `"faq_schema":true`. Never invent addresses, hours or phone numbers.
- Brand fonts uploaded under Uncoder → Design System → Custom fonts are listed in get_design_system → custom_fonts; use them by family name like Google Fonts.
- Analytics, tag managers and other site-wide code are added by an administrator under Uncoder → Settings → Custom code (not available through MCP). Never paste scripts into HTML widgets for that.

## Recipes
- Header: container row (`justify:"space-between"`, `align:"center"`, `_padding` 18px 0) → `site-logo` + `nav-menu` (menu id from list_menus / create_menu) + `button`. Behaviour is set on the header template's `page_settings` (create_template / update_template), not on its containers: `{"header_sticky":"always"}` (or "reveal" = hides on scroll down, shows on scroll up), `header_scrolled_shadow`, `header_scrolled_bg`, `header_scrolled_height:"64px"` (shrink). To pin only part of the header (a top bar above the menu bar scrolls away), give the row that should stay `"_sticky":"top"`; the header then sticks from that row (on its own it makes the header sticky, with its `_sticky_on` devices). Transparent header over a hero: `{"header_transparent":"front"|"all"|"selected","header_transparent_color":"#fff","header_transparent_logo":{"id":…} or "header_transparent_logo_white":true}`; with sticky it turns solid once the page scrolls. Over a light hero (a floating bar that keeps its look), add `"header_transparent_keep_colors":true` so only the overlay applies. Pages opt in/out with update_page `page_settings.header_transparent` "yes"/"no" — give their first section extra top padding (header height + space). Tablet/phone: when the menu collapses to a hamburger, put it at the far right next to the button — logo `_flex_grow_tablet: 1`, nav-menu `_order_tablet: 3` + `toggle_align: "end"`, row `gap_tablet: "16px"` (hide the button on phones with `_hide_mobile`).
- Footer: dark section, grid 4 columns (logo + text, link lists with `icon-list` inline links, contact), bottom row with copyright text (`current-date` dynamic tag with format "Y").
- Single post: `post-title` (h1), `post-info`, `featured-image`, `post-content`, `author-box`, `post-navigation`, `post-comments`.
- Archive: `archive-title`, `archive-description`, `posts` (query source "current") or `loop-grid` with a loop-item template.
- 404: friendly title, `search-form`, button home.
- Popup: `create_template` type popup with `popup` settings, or `configure_popup` later: `{"layout":"modal"|"slide_in"|"bar"|"fullscreen","width":"520px","triggers":{"load":{"enabled":true,"delay":5},"scroll":{"enabled":false,"percent":50},"exit_intent":{"enabled":false},"click":{"enabled":false,"selector":".open-offer"}},"frequency":{"times":1,"period":"week"}}`. Conditions decide on which pages it is available. Open it from any button/link with the URL "#uncoder-popup:open:{popup_id}" (disable the load trigger for link-only popups); a link "#uncoder-popup:close" inside the popup closes it.
- Mega menu: `create_template` type mega-menu (a grid of icon-box/icon-list links, no section padding needed), then `set_mega_menu({"item_id": <top-level menu item id from list_menus {menu_id}>, "template_id": …, "width":"container"|"full"|"auto"})`.
- Reusable section: type section; place it with `{"type":"template","settings":{"template_id":…}}` so edits to it update every page.
- Loop item: a card template using dynamic tags (featured-image, post-title, post-excerpt, post-url) → used by `loop-grid` `loop_template`.
MD;
	}

	private static function topic_content(): string {
		return <<<'MD'
# Content, media, menus, SEO

- Images: `search_images({"query":"coffee roaster","orientation":"landscape"})` returns openly licensed photos with attribution (add `"size":"large"` for heroes and full-width backgrounds — default results are often only 1024px wide); `upload_media({"url":…, "alt":…})` imports one (returns attachment id). Use `{"id":123}` in image/background settings. Hero images: set image `loading:"eager"`.
- Pass the `attribution` from search_images to upload_media (CC BY licenses require credit; it is stored as the caption). Imported file names and titles come from the alt text.
- `generate_placeholder` creates a labelled SVG when no photo fits; `generate_logo({"text":"Verdant","icon":"leaf","icon_background":"#1f4d3a","icon_color":"#fff","style":"serif","set_as_site_logo":true})` creates a simple SVG wordmark for the site-logo widget.
- Posts: `create_post({"title","content" (HTML or Markdown), "status","categories":["News"],"tags":[…],"featured_image":id})`.
- Menus: `create_menu({"name":"Main","items":[{"title":"Home","url":"/"},{"title":"Services","page_id":12,"children":[…]}],"location":"primary"})`, then use its id in `nav-menu` (`menu`).
- Site: `update_site_settings({"title","tagline","front_page_id","logo_id","site_icon_id"})`. Set the new home page as front page.
- SEO: `set_seo_meta({"id":…, "title":…, "description":…})` (writes to Yoast, Rank Math, AIOSEO, SEOPress or The SEO Framework when active; otherwise Uncoder prints the tags). Titles ≈ 50–60 characters, descriptions 140–160.
- `list_content_types` / `list_terms` / `create_term` for taxonomies; `update_post` edits regular posts (Uncoder pages use edit_elements).
MD;
	}

	private static function topic_quality(): string {
		return <<<'MD'
# Quality checklist (run audit_page)

Accessibility: one h1; headings in order; alt text on informative images (empty alt only for decorative ones); link and button text that makes sense out of context; contrast ≥ 4.5:1; icon-only links need an accessible label; don't rely on color alone.
Performance: import images (not hotlinks); eager-load only the first hero image; avoid more than 2–3 font families and 4–5 weights; prefer CSS gradients to big decorative images; keep nesting shallow (≤ 5 levels).
Copy: specific, benefit-led headings; short paragraphs; one primary CTA per section; no lorem ipsum; no fabricated reviews, clients, awards or numbers — use [placeholders] for unknown facts.
Layout: consistent section padding; aligned edges; generous whitespace; check tablet and mobile settings for every row/grid.
MD;
	}

	private static function topic_recipes(): string {
		return <<<'MD'
# Recipes (copy, then adapt copy and tokens)

## Start from a section pattern (preferred)
The site ships a library of professionally designed, on-brand sections (header, hero, features, social proof, stats, pricing, FAQ, CTA, about, team, process, timeline, contact, blog, footer). Instead of writing sections from scratch: `list_patterns` (optionally `category` / `search`) → `get_pattern(id)` → rewrite the copy for this business, replace every [bracketed placeholder] and the placeholder images (search_images → upload_media → `{"id": …}` + alt), keep the Design System tokens (adjust colors only through tokens) → pass the elements to `create_page` or `edit_elements` (insert, `parent_id` null). Combine several patterns top to bottom for a page; only hero patterns use an h1, so use exactly one hero per page. Header and footer patterns belong in `create_template` (type header/footer). The recipes below show the underlying JSON if you need to go beyond the patterns.

## Hero (split)
```json
{"type":"container","label":"Hero","settings":{"direction":"row","direction_mobile":"column","align":"center","gap":"64px","gap_mobile":"32px","_padding":{"top":112,"bottom":112,"unit":"px"},"_padding_mobile":{"top":64,"bottom":64,"unit":"px"},"background":{"type":"classic","color":"var(--uncoder-c-surface)"}},
 "children":[
  {"type":"container","settings":{"width":"55%","gap":"24px"},"children":[
    {"type":"heading","settings":{"title":"Small-batch coffee, roasted the week you order","tag":"h1","typography":{"preset":"display"}}},
    {"type":"text-editor","settings":{"content":"<p>Single-origin beans from growers we know by name, shipped within days of roasting.</p>","typography":{"preset":"lead"},"max_width":"52ch"}},
    {"type":"container","settings":{"direction":"row","gap":"12px","direction_mobile":"column"},"children":[
      {"type":"button","settings":{"text":"Start a subscription","link":"/subscribe","size":"lg"}},
      {"type":"button","settings":{"text":"Browse coffee","link":"/shop","variant":"outline","size":"lg"}}]}]},
  {"type":"container","settings":{"width":"45%"},"children":[
    {"type":"image","settings":{"image":{"id":0,"url":"https://…"},"alt":"Freshly roasted beans cooling in the roaster","loading":"eager","aspect_ratio":"4/5","object_fit":"cover","radius":"24px"}}]}]}
```

## Feature grid
```json
{"type":"container","settings":{"_padding":{"top":96,"bottom":96,"unit":"px"},"gap":"48px"},"children":[
  {"type":"container","settings":{"align":"center","gap":"12px"},"children":[
    {"type":"heading","settings":{"title":"Why customers switch","tag":"h2","align":"center","typography":{"preset":"h2"}}},
    {"type":"text-editor","settings":{"content":"Three things we never compromise on.","align":"center"}}]},
  {"type":"container","settings":{"layout":"grid","grid_columns":3,"grid_columns_tablet":2,"grid_columns_mobile":1,"gap":"24px"},"children":[
    {"type":"icon-box","settings":{"icon":"leaf","title":"Traceable","description":"Every bag lists the farm and harvest."}},
    {"type":"icon-box","settings":{"icon":"flame","title":"Roasted to order","description":"No warehouse shelves."}},
    {"type":"icon-box","settings":{"icon":"calendar","title":"Flexible","description":"Skip or pause in two clicks."}}]}]}
```
(Check get_widget_schema("icon-box") for exact keys before using it.)

## FAQ
Accordion with one child container per item (see widgets topic).

## Call to action band
Dark container (`background` {"type":"classic","color":"var(--uncoder-c-secondary)"}, `text_color` "#fff"), centered heading h2 + text + button, `_padding` 88px.
MD;
	}
}
