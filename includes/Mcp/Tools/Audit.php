<?php
/**
 * Page audit: accessibility, SEO, responsive and content checks.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

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
		$elements = Plugin::instance()->documents()->get( $post->ID )->elements();
		$state    = array(
			'headings' => array(),
			'fonts'    => array(),
		);
		$this->walk( $elements, 0, null, '#ffffff', $state );

		// Rendered HTML: icon-only links and buttons, form labels, images in any widget, frames, ids.
		try {
			$html = Plugin::instance()->documents()->get( $post->ID )->render( array( 'post_id' => $post->ID ) );
			foreach ( Html_A11y::check( $html ) as $issue ) {
				$this->add( $issue['severity'], 'accessibility', $issue['id'], $issue['message'], $issue['fix'] );
			}
		} catch ( \Throwable $e ) {
			unset( $e ); // The static checks above still apply.
		}

		// Headings.
		$h1 = array_values( array_filter( $state['headings'], static fn( $h ) => 1 === $h['level'] ) );
		$template_type = (string) get_post_meta( $post->ID, Utils::META_TYPE, true );
		$needs_h1      = '' === $template_type || in_array( $template_type, array( 'single-page', 'error-404', 'search-results' ), true );
		if ( ! $h1 && $needs_h1 && $state['headings'] ) {
			$this->add( 'error', 'headings', $state['headings'][0]['id'], 'The page has no h1.', 'Make the main hero title tag "h1".' );
		} elseif ( count( $h1 ) > 1 ) {
			foreach ( array_slice( $h1, 1 ) as $h ) {
				$this->add( 'error', 'headings', $h['id'], 'More than one h1 on the page.', 'Use h2 for section titles; keep a single h1.' );
			}
		}
		$prev = 0;
		foreach ( $state['headings'] as $h ) {
			if ( $prev && $h['level'] > $prev + 1 ) {
				$this->add( 'warning', 'headings', $h['id'], sprintf( 'Heading level jumps from h%d to h%d.', $prev, $h['level'] ), sprintf( 'Use h%d here, or style it with a text style preset instead of skipping levels.', $prev + 1 ) );
			}
			$prev = $h['level'];
		}
		if ( count( $state['fonts'] ) > 3 ) {
			$this->add( 'warning', 'performance', null, 'The page loads ' . count( $state['fonts'] ) . ' font families: ' . implode( ', ', array_keys( $state['fonts'] ) ) . '.', 'Use the Design System heading/body fonts (var(--uncoder-f-heading), var(--uncoder-f-body)) and at most 2–3 families.' );
		}
		if ( ! $this->has_meta_description( $post->ID ) && '' === $template_type ) {
			$this->add( 'info', 'seo', null, 'No meta description.', 'Call set_seo_meta with a 140–160 character description.' );
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
				$this->add( 'warning', 'structure', $id, 'Deep nesting (' . $depth . ' levels).', 'Flatten the layout: use grid containers or fewer wrapper containers.' );
			}

			$my_bg   = $bg;
			$my_text = $text;
			if ( 'container' === $type ) {
				$my_bg = $this->surface_color( $s, $bg );
				if ( ! empty( $s['text_color'] ) ) {
					$my_text = (string) $s['text_color'];
				}
				// A childless box with a background or border is a shape (swatch, dot, divider), not a leftover.
				if ( empty( $node['children'] ) && ! self::is_shape( $s ) ) {
					$this->add( 'warning', 'structure', $id, 'Empty container.', 'Add content or delete it.' );
				}
				$this->check_responsive_container( $id, $s, (array) ( $node['children'] ?? array() ) );
			}

			// Collect font families from typography groups.
			foreach ( $s as $v ) {
				if ( is_array( $v ) && isset( $v['family'] ) && is_string( $v['family'] ) && '' !== $v['family'] && 0 !== strpos( $v['family'], 'var(' ) ) {
					$state['fonts'][ $v['family'] ] = true;
				}
			}

			$this->check_widget( $id, $type, $s, $eff, $node, $my_bg, $state, $my_text );

			if ( ! empty( $node['children'] ) ) {
				$this->walk( (array) $node['children'], $depth + 1, $node, $my_bg, $state, $my_text );
			}
		}
	}

	private function check_widget( string $id, string $type, array $s, array $eff, array $node, string $bg, array &$state, string $text = '' ): void {
		$dynamic = (array) ( $node['dynamic'] ?? array() );

		if ( in_array( $type, array( 'heading', 'post-title', 'archive-title', 'site-title' ), true ) ) {
			$tag = (string) ( $eff['tag'] ?? $eff['title_tag'] ?? 'h2' );
			if ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
				$state['headings'][] = array( 'id' => $id, 'level' => (int) $m[1] );
			}
			$title = wp_strip_all_tags( (string) ( $eff['title'] ?? '' ) );
			if ( 'heading' === $type && ! isset( $dynamic['title'] ) ) {
				if ( '' === trim( $title ) ) {
					$this->add( 'error', 'content', $id, 'Empty heading.', 'Write a heading or delete the widget.' );
				} elseif ( mb_strlen( $title ) > 110 ) {
					$this->add( 'warning', 'content', $id, 'Very long heading (' . mb_strlen( $title ) . ' characters).', 'Shorten it; move detail into a paragraph.' );
				}
			}
			$this->check_contrast( $id, (string) ( $s['color'] ?? '' ), $bg, '' !== $text ? $text : 'var(--uncoder-c-heading)', true );
		}

		if ( 'text-editor' === $type ) {
			$this->check_contrast( $id, (string) ( $s['color'] ?? '' ), $bg, '' !== $text ? $text : 'var(--uncoder-c-text)', false );
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
					$this->add( 'warning', 'content', $id, sprintf( '%s still has placeholder text ("%s").', $type, mb_substr( $val, 0, 40 ) ), 'Replace it with real copy.' );
					break 2;
				}
			}
		}

		if ( 'button' === $type ) {
			$url = (string) ( $eff['link']['url'] ?? '' );
			if ( ! isset( $dynamic['link'] ) && ( '' === $url || '#' === $url ) ) {
				$this->add( 'warning', 'links', $id, 'Button links to "' . ( '' === $url ? '(nothing)' : '#' ) . '".', 'Set link to a real page URL, #section anchor or tel:/mailto:.' );
			}
			if ( '' === trim( (string) ( $eff['text'] ?? '' ) ) && ! isset( $dynamic['text'] ) ) {
				$this->add( 'error', 'accessibility', $id, 'Button without text.', 'Give the button a label.' );
			}
		}

		if ( 'image' === $type ) {
			$media = (array) ( $eff['image'] ?? array() );
			$alt   = (string) ( $eff['alt'] ?? '' );
			if ( '' === $alt && ! empty( $media['id'] ) ) {
				$alt = (string) get_post_meta( (int) $media['id'], '_wp_attachment_image_alt', true );
			}
			if ( '' === $alt && '' === (string) ( $media['alt'] ?? '' ) && ! isset( $dynamic['image'] ) && empty( $eff['decorative'] ) ) {
				$this->add( empty( $media['url'] ) && empty( $media['id'] ) ? 'warning' : 'error', 'accessibility', $id, empty( $media['url'] ) && empty( $media['id'] ) ? 'Image widget without an image.' : 'Image without alt text.', 'Describe the image in "alt" (or set_image_alt on the attachment). Purely ornamental images: set "decorative": true.' );
			}
			if ( ! empty( $media['url'] ) && empty( $media['id'] ) && false === strpos( (string) $media['url'], (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
				$this->add( 'warning', 'performance', $id, 'Image is hotlinked from another site.', 'Import it with upload_media and use {"id": attachment_id}.' );
			}
		}

		// Common: fixed pixel widths that overflow phones.
		// max_width only caps (harmless on small screens); fixed widths are what overflow.
		foreach ( array( '_custom_width', 'width' ) as $key ) {
			$v = $s[ $key ] ?? null;
			if ( is_array( $v ) && 'px' === ( $v['unit'] ?? '' ) && (float) ( $v['size'] ?? 0 ) > 360 && ! isset( $s[ $key . '_mobile' ] ) ) {
				$this->add( 'warning', 'responsive', $id, sprintf( '%s is %spx without a mobile value.', $key, $v['size'] ), sprintf( 'Add %s_mobile (e.g. 100%%) or use a percentage.', $key ) );
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
		return ! empty( $border['style'] ) && 'none' !== $border['style'];
	}

	/**
	 * @param array<int, array<string,mixed>> $children Child nodes.
	 */
	private function check_responsive_container( string $id, array $s, array $children ): void {
		$containers = count( array_filter( $children, static fn( $c ) => 'container' === ( $c['type'] ?? '' ) ) );
		// Rows of small items (avatar + name, icon + label, buttons) may stay rows; column layouts should stack.
		// A header bar (logo · menu · button) stays a row too: its menu turns into a toggle on phones.
		$has_menu  = (bool) array_filter( $children, static fn( $c ) => 'nav-menu' === ( $c['type'] ?? '' ) );
		$is_layout = ( $containers >= 2 || count( $children ) >= 3 ) && ! $has_menu;
		if ( 'grid' !== ( $s['layout'] ?? '' ) && in_array( self::on_mobile( $s, 'direction', 'column' ), array( 'row', 'row-reverse' ), true ) && $is_layout && 'wrap' !== self::on_mobile( $s, 'wrap', '' ) ) {
			$this->add( 'warning', 'responsive', $id, sprintf( 'Row with %d columns keeps the row layout on phones.', count( $children ) ), 'Add "direction_mobile": "column" (and a smaller gap_mobile).' );
		}
		$mobile_cols = (int) self::on_mobile( $s, 'grid_columns', 3 );
		if ( 'grid' === ( $s['layout'] ?? '' ) && $mobile_cols >= 3 && ! isset( $s['grid_template_mobile'] ) ) {
			$this->add( 'warning', 'responsive', $id, 'Grid keeps ' . $mobile_cols . ' columns on phones.', 'Add "grid_columns_mobile": 1 (and grid_columns_tablet: 2).' );
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
		if ( 'gradient' === $type ) {
			return $this->resolve_color( (string) ( $bg['color'] ?? '' ) );
		}
		if ( ! empty( $bg['image']['id'] ) || ! empty( $bg['image']['url'] ) || 'video' === $type ) {
			return ''; // A photo: contrast cannot be judged from settings.
		}
		$color = $this->resolve_color( (string) ( $bg['color'] ?? '' ) );
		return '' !== $color && self::alpha( $color ) >= 0.6 ? $color : $inherited;
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

	private function check_contrast( string $id, string $color, string $bg, string $default, bool $large ): void {
		$fg = $this->resolve_color( '' !== $color ? $color : $default );
		$fg_rgb = self::rgb( $fg );
		$bg_rgb = self::rgb( $bg );
		if ( ! $fg_rgb || ! $bg_rgb ) {
			return;
		}
		$ratio = self::contrast( $fg_rgb, $bg_rgb );
		$min   = $large ? 3.0 : 4.5;
		if ( $ratio < $min ) {
			$this->add( 'error', 'accessibility', $id, sprintf( 'Low contrast %.1f:1 (text %s on %s; minimum %.1f:1).', $ratio, $fg, $bg, $min ), 'Darken/lighten the text color or change the section background. On dark sections set the container text_color to #fff.' );
		}
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

	private function add( string $severity, string $category, ?string $id, string $message, string $fix ): void {
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
		$this->issues[] = $issue;
	}
}
