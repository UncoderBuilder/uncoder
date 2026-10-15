<?php
/**
 * Page audit: accessibility, SEO, responsive and content checks.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Static analysis of the element tree (no browser needed). Issues carry element ids and fixes.
 */
final class Audit {

	/** @var array<int, array<string,mixed>> */
	private array $issues = array();

	/** @var array<string,string> */
	private array $kit_colors = array();

	/** Depth of containers hidden on phones around the node being checked (their rows never show there). */
	private int $hidden_mobile = 0;

	/** Depth of aria-hidden elements around the node: decoration (a faded giant wordmark) has no contrast minimum. */
	private int $decorative = 0;

	/**
	 * The design system's typography presets (id => value), to tell large text from normal text.
	 *
	 * @var array<string,array<string,mixed>>
	 */
	private array $presets = array();

	private const DEFAULT_COPY = array( 'add your heading text here', 'click here', 'lorem ipsum', 'add your text here', 'button', 'your title here' );

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function run( array $a ) {
		$post = Helpers::editable_post( $a['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		// One instance serves every call in the request (batches, WP-CLI): start each audit clean.
		$this->issues     = array();
		$this->kit_colors = array();
		foreach ( Plugin::instance()->kit()->get( 'colors', array() ) as $c ) {
			$this->kit_colors[ $c['id'] ] = $c['value'];
		}
		$this->presets = array();
		foreach ( Plugin::instance()->kit()->get( 'typography', array() ) as $preset ) {
			if ( isset( $preset['id'] ) && is_array( $preset['value'] ?? null ) ) {
				$this->presets[ (string) $preset['id'] ] = $preset['value'];
			}
		}
		$elements = Plugin::instance()->documents()->get( $post->ID )->elements();
		$state    = array(
			'headings' => array(),
			'fonts'    => array(),
		);
		// Sections without their own background sit on the page: the design system's page background (a dark site
		// sets it once in its theme), else white.
		$theme = (array) Plugin::instance()->kit()->get( 'theme', array() );
		$page  = ! empty( $theme['enabled'] ) ? $this->resolve_color( (string) ( $theme['background'] ?? '' ) ) : '';
		$start = '' !== $page && self::alpha( $page ) >= 1.0 ? $page : '#ffffff';
		// A see-through header lies over the top of the pages (usually a hero photo): what its words sit on is
		// unknown, unless a box of the header has its own background.
		if ( 'header' === (string) get_post_meta( $post->ID, Utils::META_TYPE, true ) ) {
			$over = (string) ( Plugin::instance()->documents()->get( $post->ID )->page_settings()['header_transparent'] ?? '' );
			$start = in_array( $over, array( 'all', 'front' ), true ) ? '' : $start;
		}
		$this->walk( $elements, 0, null, $start, $state );

		// Rendered HTML: icon-only links and buttons, form labels, images in any widget, frames, ids.
		try {
			// A loop item or single-post layout shows a post's title, image and fields: render it for the latest
			// published post, as the site will (rendered for the template itself, every dynamic text is empty).
			$context = $post->ID;
			if ( Post_Types::TEMPLATE === $post->post_type && in_array( (string) get_post_meta( $post->ID, Utils::META_TYPE, true ), array( 'loop-item', 'single-post', 'single' ), true ) ) {
				$latest  = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids' ) );
				$context = $latest ? (int) $latest[0] : $post->ID;
			}
			$html = Plugin::instance()->documents()->get( $post->ID )->render( array( 'post_id' => $context ) );
			foreach ( Html_A11y::check( $html ) as $issue ) {
				$this->add( $issue['severity'], 'accessibility', $issue['id'], $issue['message'], $issue['fix'], (string) ( $issue['rule'] ?? '' ) );
			}
		} catch ( \Throwable $e ) {
			unset( $e ); // The static checks above still apply.
		}

		// Headings.
		$h1 = array_values( array_filter( $state['headings'], static fn( $h ) => 1 === $h['level'] ) );
		$template_type = (string) get_post_meta( $post->ID, Utils::META_TYPE, true );
		$needs_h1      = '' === $template_type || in_array( $template_type, array( 'single-page', 'error-404', 'search-results' ), true );
		if ( ! $h1 && $needs_h1 && $state['headings'] ) {
			$this->add( 'error', 'headings', $state['headings'][0]['id'], 'The page has no h1.', 'Make the main hero title tag "h1".', 'no-h1' );
		} elseif ( count( $h1 ) > 1 ) {
			foreach ( array_slice( $h1, 1 ) as $h ) {
				$this->add( 'error', 'headings', $h['id'], 'More than one h1 on the page.', 'Use h2 for section titles; keep a single h1.', 'multiple-h1' );
			}
		}
		$prev = 0;
		foreach ( $state['headings'] as $h ) {
			if ( $prev && $h['level'] > $prev + 1 ) {
				$this->add( 'warning', 'headings', $h['id'], sprintf( 'Heading level jumps from h%d to h%d.', $prev, $h['level'] ), sprintf( 'Use h%d here, or style it with a text style preset instead of skipping levels.', $prev + 1 ), 'heading-skip' );
			}
			$prev = $h['level'];
		}
		if ( count( $state['fonts'] ) > 3 ) {
			$this->add( 'warning', 'performance', null, 'The page loads ' . count( $state['fonts'] ) . ' font families: ' . implode( ', ', array_keys( $state['fonts'] ) ) . '.', 'Use the Design System heading/body fonts (var(--uncoder-f-heading), var(--uncoder-f-body)) and at most 2–3 families.', 'many-fonts' );
		}
		if ( ! $this->has_meta_description( $post->ID ) && '' === $template_type ) {
			$this->add( 'info', 'seo', null, 'No meta description.', 'Call set_seo_meta with a 140–160 character description.', 'meta-description' );
		}

		$counts = array_count_values( array_column( $this->issues, 'severity' ) );
		return array(
			'page'    => array( 'id' => $post->ID, 'title' => get_the_title( $post ) ),
			'score'   => max( 0, 100 - 12 * ( $counts['error'] ?? 0 ) - 4 * ( $counts['warning'] ?? 0 ) - 1 * ( $counts['info'] ?? 0 ) ),
			'summary' => array(
				'errors'   => $counts['error'] ?? 0,
				'warnings' => $counts['warning'] ?? 0,
				'info'     => $counts['info'] ?? 0,
			),
			'issues'  => $this->issues,
			'next'    => $this->issues ? 'Fix errors first with edit_elements (the ids are listed), then run audit_page again.' : 'No issues found.',
		);
	}

	/**
	 * @param array<int, array<string,mixed>> $nodes Nodes.
	 */
	private function walk( array $nodes, int $depth, ?array $parent, string $bg, array &$state, string $text = '' ): void {
		foreach ( $nodes as $node ) {
			$id   = (string) ( $node['id'] ?? '' );
			$type = (string) ( $node['type'] ?? '' );
			$s    = (array) ( $node['settings'] ?? array() );
			$el   = Plugin::instance()->elements()->get( $type );
			$eff  = $el ? $el->effective_settings( $s ) : $s;

			if ( ! empty( $node['disabled'] ) ) {
				continue;
			}
			if ( $depth > 7 ) {
				$this->add( 'warning', 'structure', $id, 'Deep nesting (' . $depth . ' levels).', 'Flatten the layout: use grid containers or fewer wrapper containers.', 'deep-nesting' );
			}

			$my_bg   = $bg;
			$my_text = $text;
			if ( 'container' === $type ) {
				// A box filled by a picture (an image laid over the whole box, the words on top) is a photo surface:
				// its contrast cannot be judged from settings, as with a background photo.
				$my_bg = self::photo_box( $node ) ? '' : $this->surface_color( $s, $bg );
				if ( ! empty( $s['text_color'] ) ) {
					$my_text = (string) $s['text_color'];
				}
				// A childless box with a background or border is a shape (swatch, dot, divider), not a leftover.
				if ( empty( $node['children'] ) && ! self::is_shape( $s ) ) {
					$this->add( 'warning', 'structure', $id, 'Empty container.', 'Add content or delete it.', 'empty-container' );
				}
				// Decorations hidden from screen readers (a colour stripe, a row of bars) are drawings, not layouts.
				if ( ! $this->hidden_mobile && empty( $s['_hide_mobile'] ) && ! $this->decorative && ! self::aria_hidden( $s ) ) {
					$this->check_responsive_container( $id, $s, (array) ( $node['children'] ?? array() ) );
				}
			}

			// Collect font families from typography groups.
			foreach ( $s as $v ) {
				if ( is_array( $v ) && isset( $v['family'] ) && is_string( $v['family'] ) && '' !== $v['family'] && 0 !== strpos( $v['family'], 'var(' ) ) {
					$state['fonts'][ $v['family'] ] = true;
				}
			}

			$decor             = self::aria_hidden( $s );
			$this->decorative += $decor ? 1 : 0;
			$this->check_widget( $id, $type, $s, $eff, $node, $my_bg, $state, $my_text );
			$this->check_wrapper_label( $id, $type, $s );
			$this->check_sticky_position( $id, $s );

			if ( ! empty( $node['children'] ) ) {
				$hidden               = ! empty( $s['_hide_mobile'] );
				$this->hidden_mobile += $hidden ? 1 : 0;
				$this->walk( (array) $node['children'], $depth + 1, $node, $my_bg, $state, $my_text );
				$this->hidden_mobile -= $hidden ? 1 : 0;
			}
			$this->decorative -= $decor ? 1 : 0;
		}
	}

	private function check_widget( string $id, string $type, array $s, array $eff, array $node, string $bg, array &$state, string $text = '' ): void {
		$dynamic = (array) ( $node['dynamic'] ?? array() );
		// A widget's own background (a pill label, a badge) is the surface its text sits on.
		$own = is_array( $s['_background'] ?? null ) ? $s['_background'] : array();
		if ( 'classic' === ( $own['type'] ?? 'classic' ) && '' !== (string) ( $own['color'] ?? '' ) && empty( $own['image']['id'] ) && empty( $own['image']['url'] ) ) {
			$bg = $this->surface_color( array( 'background' => $own ), $bg );
		}

		// Headings written inside a text block (a title with inline icons) count too, in their order.
		if ( 'text-editor' === $type && ! isset( $dynamic['content'] ) && preg_match_all( '/<h([1-6])[\s>]/i', (string) ( $eff['content'] ?? '' ), $hm ) ) {
			foreach ( $hm[1] as $level ) {
				$state['headings'][] = array(
					'id'    => $id,
					'level' => (int) $level,
				);
			}
		}

		if ( in_array( $type, array( 'heading', 'post-title', 'archive-title', 'site-title' ), true ) ) {
			$tag = (string) ( $eff['tag'] ?? $eff['title_tag'] ?? 'h2' );
			if ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
				$state['headings'][] = array( 'id' => $id, 'level' => (int) $m[1] );
			}
			$title = wp_strip_all_tags( (string) ( $eff['title'] ?? '' ) );
			if ( 'heading' === $type && ! isset( $dynamic['title'] ) ) {
				if ( '' === trim( $title ) ) {
					$this->add( 'error', 'content', $id, 'Empty heading.', 'Write a heading or delete the widget.', 'empty-heading' );
				} elseif ( mb_strlen( $title ) > 110 && isset( $m[1] ) ) {
					// Only a real heading (h1–h6): a Heading set to p, div or span is a styled statement, read as text.
					$this->add( 'warning', 'content', $id, 'Very long heading (' . mb_strlen( $title ) . ' characters).', 'Shorten it, move detail into a paragraph, or set the tag to p if it is a statement rather than a heading.', 'long-heading' );
				}
			}
			$this->check_contrast( $id, (string) ( $s['color'] ?? '' ), $bg, '' !== $text ? $text : 'var(--uncoder-c-heading)', $this->large_text( $eff ) ?? true );
		}

		if ( 'text-editor' === $type ) {
			$this->check_contrast( $id, (string) ( $s['color'] ?? '' ), $bg, '' !== $text ? $text : 'var(--uncoder-c-text)', $this->large_text( $eff ) ?? false );
		}

		// Buttons and form buttons/fields with their own colors: the words on their own ground (the section's ground
		// shows through a half-transparent one).
		if ( 'button' === $type ) {
			$this->check_pair( $id, (string) ( $eff['text_color'] ?? '' ), self::classic_color( $eff['background'] ?? null ), $bg, $this->large_text( $eff ) ?? false, 'button' );
		}
		if ( 'form' === $type ) {
			$this->check_pair( $id, (string) ( $eff['button_color'] ?? '' ), self::classic_color( $eff['button_background'] ?? null ), $bg, false, 'submit button' );
			$this->check_pair( $id, (string) ( $eff['field_color'] ?? '' ), (string) ( $eff['field_bg'] ?? '' ), $bg, false, 'form field' );
		}

		// Leftover default copy.
		foreach ( array( 'title', 'text', 'content', 'description' ) as $key ) {
			if ( isset( $dynamic[ $key ] ) ) {
				continue;
			}
			$val = strtolower( trim( wp_strip_all_tags( (string) ( $eff[ $key ] ?? '' ) ) ) );
			foreach ( self::DEFAULT_COPY as $placeholder ) {
				// "Button" is only leftover copy on a Button; elsewhere it is a real word (a label, a setting name).
				if ( 'button' === $placeholder && 'button' !== $type ) {
					continue;
				}
				if ( '' !== $val && ( $val === $placeholder || 0 === strpos( $val, 'lorem ipsum' ) || ( 'add your text here' === $placeholder && 0 === strpos( $val, 'add your text here' ) ) ) ) {
					$this->add( 'warning', 'content', $id, sprintf( '%s still has placeholder text ("%s").', $type, mb_substr( $val, 0, 40 ) ), 'Replace it with real copy.', 'placeholder' );
					break 2;
				}
			}
		}

		if ( 'button' === $type ) {
			$url = (string) ( $eff['link']['url'] ?? '' );
			// A button that works through a click interaction (a toggle, a popup) needs no link.
			$acts = array_filter( is_array( $eff['_interactions'] ?? null ) ? $eff['_interactions'] : array(), static fn( $row ) => is_array( $row ) && 'click' === ( $row['trigger'] ?? '' ) );
			if ( ! isset( $dynamic['link'] ) && ! $acts && ( '' === $url || '#' === $url ) ) {
				$this->add( 'warning', 'links', $id, 'Button links to "' . ( '' === $url ? '(nothing)' : '#' ) . '".', 'Set link to a real page URL, #section anchor or tel:/mailto:.', 'button-link' );
			}
			// An icon-only button is named by its "Label for screen readers" (aria_label).
			if ( '' === trim( (string) ( $eff['text'] ?? '' ) ) && ! isset( $dynamic['text'] ) && '' === trim( (string) ( $eff['aria_label'] ?? '' ) ) ) {
				$this->add( 'error', 'accessibility', $id, 'Button without text.', 'Give the button a label, or for an icon-only button set "Label for screen readers" (aria_label).', 'button-text' );
			}
		}

		if ( 'image' === $type ) {
			$media = (array) ( $eff['image'] ?? array() );
			$alt   = (string) ( $eff['alt'] ?? '' );
			if ( '' === $alt && ! empty( $media['id'] ) ) {
				$alt = (string) get_post_meta( (int) $media['id'], '_wp_attachment_image_alt', true );
			}
			if ( '' === $alt && '' === (string) ( $media['alt'] ?? '' ) && ! isset( $dynamic['image'] ) && empty( $eff['decorative'] ) ) {
				$this->add( empty( $media['url'] ) && empty( $media['id'] ) ? 'warning' : 'error', 'accessibility', $id, empty( $media['url'] ) && empty( $media['id'] ) ? 'Image widget without an image.' : 'Image without alt text.', 'Describe the image in "alt" (or set_image_alt on the attachment). Purely ornamental images: set "decorative": true.', empty( $media['url'] ) && empty( $media['id'] ) ? 'image-missing' : 'image-alt' );
			}
			if ( ! empty( $media['url'] ) && empty( $media['id'] ) && false === strpos( (string) $media['url'], (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
				$this->add( 'warning', 'performance', $id, 'Image is hotlinked from another site.', 'Import it with upload_media and use {"id": attachment_id}.', 'image-hotlink' );
			}
		}

		// Common: fixed pixel widths that overflow phones. Containers and the widget width (_custom_width) are
		// clamped by max-width: 100%, so only an image whose max width is lifted can overflow — and an absolutely
		// positioned decoration does that on purpose (its section clips it).
		$lifted = 'none' === ( $s['max_width']['size'] ?? '' ) || 'none' === ( $s['max_width'] ?? '' );
		if ( 'image' === $type && $lifted && ! in_array( $s['_position'] ?? '', array( 'absolute', 'fixed' ), true ) ) {
			$v = $s['width'] ?? null;
			if ( is_array( $v ) && 'px' === ( $v['unit'] ?? '' ) && (float) ( $v['size'] ?? 0 ) > 360 && ! isset( $s['width_mobile'] ) ) {
				$this->add( 'warning', 'responsive', $id, sprintf( 'Image is %spx wide with no maximum, without a mobile value.', $v['size'] ), 'Add width_mobile (e.g. 100%), or keep max_width so it shrinks.', 'image-width' );
			}
		}
	}

	/**
	 * Value that applies on phones: mobile, else tablet (desktop-first cascade), else desktop.
	 *
	 * @param array<string,mixed> $s Settings.
	 * @return mixed
	 */
	private static function on_mobile( array $s, string $key, $default = null ) {
		foreach ( array( '_mobile', '_mobile_extra', '_tablet', '_tablet_extra', '' ) as $suffix ) {
			if ( isset( $s[ $key . $suffix ] ) && '' !== $s[ $key . $suffix ] ) {
				return $s[ $key . $suffix ];
			}
		}
		return $default;
	}

	/**
	 * How many columns a grid has on phones. A custom column template (grid_template "1fr 1fr") and the column count
	 * write the same CSS variable, so the value set for the narrowest width wins; at one width the template wins. A
	 * desktop template with grid_columns_mobile: 1 is one column on phones.
	 */
	private static function mobile_columns( array $s ): int {
		foreach ( array( '_mobile', '_mobile_extra', '_tablet', '_tablet_extra', '' ) as $suffix ) {
			$template = trim( (string) ( $s[ 'grid_template' . $suffix ] ?? '' ) );
			if ( '' !== $template ) {
				return self::template_tracks( $template );
			}
			if ( isset( $s[ 'grid_columns' . $suffix ] ) && '' !== $s[ 'grid_columns' . $suffix ] ) {
				return (int) $s[ 'grid_columns' . $suffix ];
			}
		}
		return 3;
	}

	/**
	 * How many columns a grid-template-columns value makes: "1fr 1fr" = 2, "minmax(0, 1fr) 106px" = 2,
	 * "repeat(3, 1fr)" = 3. repeat(auto-fit / auto-fill, …) adapts to the width and counts as one.
	 */
	private static function template_tracks( string $template ): int {
		$count    = 0;
		$template = (string) preg_replace_callback(
			'/repeat\(\s*([^,]+),((?:[^()]|\([^()]*\))*)\)/i',
			static function ( array $m ) use ( &$count ): string {
				$times  = trim( $m[1] );
				$count += ctype_digit( $times ) ? (int) $times * max( 1, self::template_tracks( $m[2] ) ) : 1;
				return ' ';
			},
			$template
		);
		// minmax(a, b), fit-content(…) and [line names] are part of one track.
		while ( preg_match( '/\([^()]*\)/', $template ) ) {
			$template = (string) preg_replace( '/\([^()]*\)/', '', $template );
		}
		$template = (string) preg_replace( '/\[[^\]]*\]/', ' ', $template );
		$tracks   = preg_split( '/\s+/', trim( $template ), -1, PREG_SPLIT_NO_EMPTY );
		return $count + count( (array) $tracks );
	}

	/**
	 * Whether an empty container still shows something: a background or a border.
	 *
	 * @param array<string,mixed> $s Container settings.
	 */
	private static function is_shape( array $s ): bool {
		$bg = (array) ( $s['background'] ?? array() );
		if ( ! empty( $bg['color'] ) || ! empty( $bg['image']['id'] ) || ! empty( $bg['image']['url'] ) || 'gradient' === ( $bg['type'] ?? '' ) ) {
			return true;
		}
		$border = (array) ( $s['border'] ?? array() );
		if ( ! empty( $border['style'] ) && 'none' !== $border['style'] ) {
			return true;
		}
		// A layer painted in custom CSS (a gradient shade, a masked tint, ruled sides or a hairline) or placed over its
		// parent (an overlay).
		if ( preg_match( '/(^|[\s;{])(background(-color|-image)?|border(-(top|right|bottom|left|block|inline)(-(start|end))?)?|outline|box-shadow)\s*:/i', (string) ( $s['_custom_css'] ?? '' ) ) ) {
			return true;
		}
		return in_array( (string) ( $s['_position'] ?? '' ), array( 'absolute', 'fixed' ), true );
	}

	/**
	 * @param array<int, array<string,mixed>> $children Child nodes.
	 */
	private function check_responsive_container( string $id, array $s, array $children ): void {
		// Rows of small items (avatar + name, icon tile + label, time · icon · text, buttons) may stay rows;
		// layouts of two or more real columns should stack. A column is a container that is not a small fixed
		// tile (≤ 160px wide), or a wide widget (text, image or media that is not a small fixed size).
		// A header bar (logo · menu · button, the menu maybe in a group of the bar) stays a row too: its menu turns
		// into a toggle on phones, and so does a sideways strip (custom CSS width: max-content, overflow-x:
		// auto/scroll or scroll-snap-type).
		// Content-sized items (an icon tile, a price next to "/ month"), items hidden on phones and decorations
		// placed with absolute / fixed positioning (out of the flow) are not columns.
		$small   = static function ( array $c ): bool {
			$s = (array) ( $c['settings'] ?? array() );
			if ( in_array( self::on_mobile( $s, '_position', '' ), array( 'absolute', 'fixed' ), true ) ) {
				return true;
			}
			$w = self::on_mobile( $s, 'width', $s['_custom_width'] ?? null ); // the width on phones decides.
			$w = is_array( $w ) && 'custom' === ( $w['unit'] ?? '' ) ? (string) ( $w['size'] ?? '' ) : $w;
			if ( ! empty( $s['_hide_mobile'] ) || 'auto' === ( $s['_width'] ?? '' ) || in_array( $w, array( 'auto', 'fit-content', 'max-content' ), true ) ) {
				return true;
			}
			return is_array( $w ) && 'px' === ( $w['unit'] ?? '' ) && (float) ( $w['size'] ?? 0 ) <= 160;
		};
		$columns = count(
			array_filter(
				$children,
				static function ( $c ) use ( $small ) {
					$type = (string) ( $c['type'] ?? '' );
					if ( in_array( $type, array( 'icon', 'button', 'heading', 'site-logo', 'social-icons', 'star-rating', 'divider', 'spacer' ), true ) ) {
						return false;
					}
					// A layer laid over the box (a background photo, a shade) is not a column.
					$cs  = (array) ( $c['settings'] ?? array() );
					$css = is_string( $cs['_custom_css'] ?? null ) ? $cs['_custom_css'] : '';
					if ( in_array( $cs['_position'] ?? '', array( 'absolute', 'fixed' ), true ) || preg_match( '/^\s*selector\s*\{[^}]*\bposition\s*:\s*(?:absolute|fixed)/i', $css ) ) {
						return false;
					}
					return ! $small( (array) $c );
				}
			)
		);
		// The menu may sit in a group of the bar (a left and a right group around a centred logo).
		$holds_menu = static function ( array $c, int $depth = 0 ) use ( &$holds_menu ): bool {
			if ( 'nav-menu' === ( $c['type'] ?? '' ) ) {
				return true;
			}
			if ( $depth >= 2 || 'container' !== ( $c['type'] ?? '' ) ) {
				return false;
			}
			foreach ( (array) ( $c['children'] ?? array() ) as $child ) {
				if ( is_array( $child ) && $holds_menu( $child, $depth + 1 ) ) {
					return true;
				}
			}
			return false;
		};
		$has_menu  = (bool) array_filter( $children, static fn( $c ) => is_array( $c ) && $holds_menu( $c ) );
		// A strip meant to scroll or slide sideways (its own CSS makes it wider than the screen or a scroller)
		// is a row on purpose: cards in a carousel-like band, a pinned horizontal scroll.
		$css       = is_string( $s['_custom_css'] ?? null ) ? $s['_custom_css'] : '';
		$strip     = '' !== $css && preg_match( '/\bselector\s*\{[^}]*(?:width\s*:\s*max-content|overflow-x\s*:\s*(?:auto|scroll)|scroll-snap-type\s*:)/i', $css );
		// A row of a table built from containers (attribute role|row): its cells line up with the columns above
		// and below, so it stays a row (the table scrolls sideways on phones, or is short enough to fit).
		$table_row = (bool) preg_match( '/(?:^|,)\s*role\s*\|\s*row\s*(?:,|$)/i', (string) ( $s['_attributes'] ?? '' ) );
		// Photos side by side (picture widgets, or boxes a picture fills) may stay side by side on phones.
		$photos    = count( $children ) >= 2 && ! array_filter( $children, static fn( $c ) => ! ( in_array( (string) ( $c['type'] ?? '' ), array( 'image', 'video' ), true ) || self::photo_box( (array) $c ) ) );
		$is_layout = $columns >= 2 && ! $has_menu && ! $strip && ! $table_row && ! $photos;
		if ( 'grid' !== ( $s['layout'] ?? '' ) && in_array( self::on_mobile( $s, 'direction', 'column' ), array( 'row', 'row-reverse' ), true ) && $is_layout && 'wrap' !== self::on_mobile( $s, 'wrap', '' ) ) {
			$this->add( 'warning', 'responsive', $id, sprintf( 'Row with %d columns keeps the row layout on phones.', count( $children ) ), 'Add "direction_mobile": "column" (and a smaller gap_mobile).', 'row-mobile' );
		}
		$mobile_cols = self::mobile_columns( $s );
		// A grid of pictures only (a photo strip, a logo wall, a gallery) may keep three or four small columns.
		$pictures = $children && ! array_filter( $children, static fn( $c ) => ! in_array( (string) ( $c['type'] ?? '' ), array( 'image', 'icon', 'site-logo' ), true ) );
		if ( 'grid' === ( $s['layout'] ?? '' ) && $mobile_cols >= 3 && ! isset( $s['grid_template_mobile'] ) && ! ( $pictures && $mobile_cols <= 4 ) && ! $table_row ) {
			$this->add( 'warning', 'responsive', $id, 'Grid keeps ' . $mobile_cols . ' columns on phones.', 'Add "grid_columns_mobile": 1 (and grid_columns_tablet: 2).', 'grid-mobile' );
		}
	}

	/**
	 * The color text sits on inside a container: an overlay or solid background color; '' when a photo
	 * (or a mostly transparent overlay) makes it unknowable, which skips contrast checks.
	 *
	 * @param array<string,mixed> $s Container settings.
	 */
	private function surface_color( array $s, string $inherited ): string {
		$overlay = is_array( $s['overlay'] ?? null ) ? $s['overlay'] : array();
		if ( ! empty( $overlay['type'] ) ) {
			$stops = 'gradient' === $overlay['type'] ? array( $overlay['color'] ?? '', $overlay['color_b'] ?? '' ) : array( $overlay['color'] ?? '' );
			$best  = '';
			$alpha = 0.0;
			foreach ( $stops as $stop ) {
				$c = $this->resolve_color( (string) $stop );
				$a = self::alpha( $c ) * (float) ( $s['overlay_opacity'] ?? 1 );
				if ( '' !== $c && $a > $alpha ) {
					$best  = $c;
					$alpha = $a;
				}
			}
			if ( $alpha >= 0.6 ) {
				return $best;
			}
		}
		$bg   = is_array( $s['background'] ?? null ) ? $s['background'] : array();
		$type = $bg['type'] ?? 'classic';
		if ( ! empty( $bg['image']['id'] ) || ! empty( $bg['image']['url'] ) || 'video' === $type ) {
			return ''; // A photo: contrast cannot be judged from settings.
		}
		// A gradient is judged by its first color, a see-through color (a 12% tint) as it shows over the surface
		// below it; over an unknown surface (a photo) only a mostly opaque color is a known one.
		$color = $this->resolve_color( (string) ( $bg['color'] ?? '' ) );
		if ( '' === $color || ( '' === $inherited && self::alpha( $color ) < 0.6 ) ) {
			return $inherited;
		}
		return self::over( $color, '' !== $inherited ? $inherited : '#ffffff' );
	}

	/** A color as it shows over another: a see-through rgba() / #rrggbbaa blended onto the base, as #rrggbb. */
	private static function over( string $c, string $base ): string {
		$a   = self::alpha( $c );
		$top = self::rgb( $c );
		$low = self::rgb( $base ) ?? array( 255, 255, 255 );
		if ( $a >= 1.0 || ! $top ) {
			return $c;
		}
		$mix = array_map( static fn( $t, $l ) => (int) round( $t * $a + $l * ( 1 - $a ) ), $top, $low );
		return sprintf( '#%02x%02x%02x', $mix[0], $mix[1], $mix[2] );
	}

	private static function alpha( string $c ): float {
		$c = strtolower( trim( $c ) );
		if ( preg_match( '/^rgba?\([^)]*[,\/]\s*([\d.]+%?)\s*\)$/', $c, $m ) && substr_count( $c, ',' ) + substr_count( $c, '/' ) >= 3 ) {
			return '%' === substr( $m[1], -1 ) ? (float) $m[1] / 100 : (float) $m[1];
		}
		if ( preg_match( '/^#[0-9a-f]{8}$/', $c ) ) {
			return hexdec( substr( $c, 7, 2 ) ) / 255;
		}
		return '' === $c ? 0.0 : 1.0;
	}

	/**
	 * Whether an element's custom attributes hide it from assistive technology ("aria-hidden|true").
	 *
	 * @param array<string,mixed> $s Element settings.
	 */
	/**
	 * A container a picture fills: one of its children is an image (or featured image / video) placed absolutely over
	 * the box (Position absolute, or custom CSS "position: absolute"), and nothing but more absolutely placed layers
	 * or the words on top.
	 *
	 * @param array<string,mixed> $node Element node.
	 */
	private static function photo_box( array $node ): bool {
		if ( 'container' !== ( $node['type'] ?? '' ) ) {
			return false;
		}
		foreach ( (array) ( $node['children'] ?? array() ) as $c ) {
			$cs  = (array) ( $c['settings'] ?? array() );
			$css = is_string( $cs['_custom_css'] ?? null ) ? $cs['_custom_css'] : '';
			$abs = 'absolute' === ( $cs['_position'] ?? '' ) || preg_match( '/^\s*selector\s*\{[^}]*\bposition\s*:\s*absolute[^}]*\binset\s*:\s*0/i', $css );
			if ( $abs && in_array( (string) ( $c['type'] ?? '' ), array( 'image', 'featured-image', 'video' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	private static function aria_hidden( array $s ): bool {
		return (bool) preg_match( '/(^|[,\n])\s*aria-hidden\s*\|\s*true\s*([,\n]|$)/i', (string) ( $s['_attributes'] ?? '' ) );
	}

	/**
	 * An aria-label (or aria-labelledby) in an element's Attributes lands on its outer box: a plain <div> that screen
	 * readers do not announce. A label belongs on the link itself (Link → Attributes) or in the HTML (<ul aria-label>);
	 * a box with a role, or a container with a landmark or list tag, can carry one.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function check_wrapper_label( string $id, string $type, array $s ): void {
		$attrs = Utils::parse_custom_attributes( (string) ( $s['_attributes'] ?? '' ) );
		if ( ( ! isset( $attrs['aria-label'] ) && ! isset( $attrs['aria-labelledby'] ) ) || isset( $attrs['role'] ) ) {
			return;
		}
		// A box that is a link names the link (its tag is the <a>); a figure takes a label too.
		if ( 'container' === $type && in_array( (string) ( $s['tag'] ?? 'div' ), array( 'section', 'nav', 'aside', 'form', 'header', 'footer', 'main', 'article', 'ul', 'ol', 'a', 'figure' ), true ) ) {
			return;
		}
		$fix = in_array( $type, array( 'button', 'image', 'icon', 'heading' ), true )
			? 'Move it to the link: link.attributes "aria-label|…" (or make the visible text say where it goes).'
			: 'Put the label on the element it names (for a list, <ul aria-label="…"> in the HTML), or give the box a role ("role|region").';
		$this->add( 'warning', 'accessibility', $id, 'aria-label on a plain wrapper is not announced.', $fix, 'wrapper-label' );
	}

	/**
	 * An element set to "Sticky: Top" (or the row a header sticks from) whose own custom CSS sets `position` on it:
	 * the CSS wins over the sticky position, so the element scrolls away and a header never pins from it.
	 *
	 * @param array<string,mixed> $s Settings.
	 */
	private function check_sticky_position( string $id, array $s ): void {
		if ( ! in_array( (string) ( $s['_sticky'] ?? '' ), array( 'top', 'bottom' ), true ) ) {
			return;
		}
		// Only the rules aimed at the element itself ("selector { … }"), outside media queries.
		$css = preg_replace( '/@media[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/', '', (string) ( $s['_custom_css'] ?? '' ) );
		if ( preg_match( '/(^|\})\s*selector\s*\{[^}]*\bposition\s*:(?!\s*sticky)/i', (string) $css ) ) {
			$this->add( 'warning', 'structure', $id, 'Custom CSS sets position on a sticky element, so it no longer sticks.', 'Remove "position" from the element’s custom CSS (Sticky sets it), or move that rule to a child.', 'sticky-position' );
		}
	}

	/**
	 * Large text in the WCAG sense (at least 24px, or 18.66px at weight 700 or more), from the widget's typography or
	 * its preset, at its smallest size (desktop, tablet, phone). Null when the size is unknown (inherited, relative).
	 *
	 * @param array<string,mixed> $s Effective settings.
	 */
	private function large_text( array $s ): ?bool {
		$t      = is_array( $s['typography'] ?? null ) ? $s['typography'] : array();
		$preset = isset( $t['preset'] ) ? ( $this->presets[ (string) $t['preset'] ] ?? array() ) : array();
		$sizes  = array();
		foreach ( array( 'size', 'size_tablet', 'size_mobile' ) as $key ) {
			$px = self::px_size( $t[ $key ] ?? null ) ?? self::px_size( $preset[ $key ] ?? null );
			if ( null !== $px ) {
				$sizes[] = $px;
			}
		}
		if ( ! $sizes ) {
			return null;
		}
		$size   = min( $sizes );
		$weight = (int) ( $t['weight'] ?? ( $preset['weight'] ?? 400 ) );
		return $size >= 24 || ( $size >= 18.66 && $weight >= 700 );
	}

	/**
	 * A font size in px: a {size, unit} value or a "48px" / "3rem" string (rem and em count as 16px); null otherwise.
	 *
	 * @param mixed $v Value.
	 */
	private static function px_size( $v ): ?float {
		if ( is_array( $v ) && isset( $v['size'] ) && is_numeric( $v['size'] ) && '' !== (string) $v['size'] ) {
			$unit = (string) ( $v['unit'] ?? 'px' );
			if ( 'px' === $unit ) {
				return (float) $v['size'];
			}
			return in_array( $unit, array( 'rem', 'em' ), true ) ? (float) $v['size'] * 16 : null;
		}
		if ( is_string( $v ) && preg_match( '/^\s*([\d.]+)\s*(px|rem|em)?\s*$/', $v, $m ) ) {
			return ( isset( $m[2] ) && in_array( $m[2], array( 'rem', 'em' ), true ) ) ? (float) $m[1] * 16 : (float) $m[1];
		}
		return null;
	}

	private function check_contrast( string $id, string $color, string $bg, string $default, bool $large ): void {
		if ( $this->decorative > 0 ) {
			return; // hidden from assistive tech: ornament, not content
		}
		$fg = $this->resolve_color( '' !== $color ? $color : $default );
		$fg = self::over( $fg, $bg ); // half-transparent text shows lighter (or darker) than its color
		$fg_rgb = self::rgb( $fg );
		$bg_rgb = self::rgb( $bg );
		if ( ! $fg_rgb || ! $bg_rgb ) {
			return;
		}
		$ratio = self::contrast( $fg_rgb, $bg_rgb );
		$min   = $large ? 3.0 : 4.5;
		if ( $ratio < $min ) {
			$this->add( 'error', 'accessibility', $id, sprintf( 'Low contrast %.1f:1 (text %s on %s; minimum %.1f:1).', $ratio, $fg, $bg, $min ), 'Darken/lighten the text color or change the section background. On dark sections set the container text_color to #fff.', 'contrast' );
		}
	}

	/**
	 * Contrast of a widget's own words on its own ground (a button, a form's submit button or fields), checked only
	 * when both colors are set on the widget.
	 */
	private function check_pair( string $id, string $color, string $ground, string $section_bg, bool $large, string $what ): void {
		if ( $this->decorative > 0 || '' === trim( $color ) || '' === trim( $ground ) ) {
			return;
		}
		$ground = $this->resolve_color( $ground );
		if ( '' === $section_bg && self::alpha( $ground ) < 0.6 ) {
			return; // A see-through field or button over a photo: what shows through cannot be judged from settings.
		}
		$under  = self::over( $ground, $section_bg );
		$fg     = self::over( $this->resolve_color( $color ), $under );
		$fg_rgb = self::rgb( $fg );
		$bg_rgb = self::rgb( $under );
		if ( ! $fg_rgb || ! $bg_rgb ) {
			return;
		}
		$ratio = self::contrast( $fg_rgb, $bg_rgb );
		$min   = $large ? 3.0 : 4.5;
		if ( $ratio < $min ) {
			$this->add( 'error', 'accessibility', $id, sprintf( 'Low contrast %.1f:1 on the %s (text %s on %s; minimum %.1f:1).', $ratio, $what, $fg, $under, $min ), 'Darken the ' . $what . ' background or change its text color.', 'contrast' );
		}
	}

	/**
	 * The color of a classic background group value ('' for gradients, images or none).
	 *
	 * @param mixed $v Background group value.
	 */
	private static function classic_color( $v ): string {
		if ( is_string( $v ) ) {
			return $v;
		}
		if ( ! is_array( $v ) || ( isset( $v['type'] ) && 'classic' !== $v['type'] ) ) {
			return '';
		}
		return (string) ( $v['color'] ?? '' );
	}

	private function resolve_color( string $value ): string {
		if ( preg_match( '/^var\(--uncoder-c-([a-z0-9_\-]+)\)$/', $value, $m ) ) {
			return $this->kit_colors[ $m[1] ] ?? '';
		}
		return $value;
	}

	/**
	 * @return int[]|null
	 */
	public static function rgb( string $c ): ?array {
		$c = strtolower( trim( $c ) );
		if ( preg_match( '/^#([0-9a-f]{3})$/', $c, $m ) ) {
			$c = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}
		if ( preg_match( '/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/', $c, $m ) ) {
			return array( hexdec( $m[1] ), hexdec( $m[2] ), hexdec( $m[3] ) );
		}
		if ( preg_match( '/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/', $c, $m ) ) {
			return array( (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		$named = array( 'white' => array( 255, 255, 255 ), 'black' => array( 0, 0, 0 ) );
		return $named[ $c ] ?? null;
	}

	public static function contrast( array $a, array $b ): float {
		$lum = static function ( array $c ): float {
			$f = static function ( $x ) {
				$x /= 255;
				return $x <= 0.03928 ? $x / 12.92 : pow( ( $x + 0.055 ) / 1.055, 2.4 );
			};
			return 0.2126 * $f( $c[0] ) + 0.7152 * $f( $c[1] ) + 0.0722 * $f( $c[2] );
		};
		$l1 = $lum( $a );
		$l2 = $lum( $b );
		return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
	}

	private function has_meta_description( int $post_id ): bool {
		return \Uncoder\Builder\Core\Seo::has_description( $post_id ) || '' !== trim( (string) get_post_field( 'post_excerpt', $post_id ) );
	}

	private function add( string $severity, string $category, ?string $id, string $message, string $fix, string $rule = '' ): void {
		if ( count( $this->issues ) >= 150 ) {
			return;
		}
		$issue = array(
			'severity' => $severity,
			'category' => $category,
			'message'  => $message,
			'fix'      => $fix,
		);
		if ( $id ) {
			$issue['element_id'] = $id;
		}
		if ( '' !== $rule ) {
			$issue['rule'] = $rule; // Site\Page_Checks explains each rule in plain words.
		}
		$this->issues[] = $issue;
	}
}
