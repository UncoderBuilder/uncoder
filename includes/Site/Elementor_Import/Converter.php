<?php
/**
 * Elementor import: converts an Elementor element tree (_elementor_data) into an Uncoder element tree.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site\Elementor_Import;

use Uncoder\Builder\Core\Breakpoints;
use Uncoder\Builder\Core\Icons;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Sections + columns and flexbox / grid containers become containers; widgets go through Widgets::map().
 * Whatever cannot be converted is counted in $report (widgets, settings, notes, remote media).
 *
 * Options:
 * - globals: "vars" (the Elementor kit is / was imported: var(--uncoder-c-…) and text styles), "values" (kit
 *   known, not imported: the kit's literal values) or "fallback" (no kit: system colors map to Uncoder's own).
 * - kit: Elementor kit settings (system_colors, custom_colors, system_typography, custom_typography…).
 * - default_colors / default_fonts: apply Elementor's widget defaults (heading = Primary …), as Elementor does
 *   while "Disable default colors / fonts" is off.
 * - remote: the data comes from another site (uploaded template): attachment ids are dropped, URLs kept.
 * - resolve: callable( int $template_id ): ?array returning another Elementor document's elements (global
 *   widgets, Template widget).
 * - templates: Elementor template id => Uncoder template id (loop items, popups) already converted.
 */
final class Converter {

	public const SYSTEM = array( 'primary', 'secondary', 'text', 'accent' );

	/** Elementor entrance animations → Uncoder (null = attention seekers, not converted). */
	private const ANIMATIONS = array(
		'fadeIn'            => 'fade-in',
		'fadeInUp'          => 'fade-up',
		'fadeInDown'        => 'fade-down',
		'fadeInLeft'        => 'fade-left',
		'fadeInRight'       => 'fade-right',
		'zoomIn'            => 'zoom-in',
		'zoomInUp'          => 'zoom-in',
		'zoomInDown'        => 'zoom-in',
		'zoomInLeft'        => 'zoom-in',
		'zoomInRight'       => 'zoom-in',
		'slideInUp'         => 'slide-up',
		'slideInDown'       => 'slide-down',
		'slideInLeft'       => 'slide-left',
		'slideInRight'      => 'slide-right',
		'flipInX'           => 'flip-up',
		'flipInY'           => 'flip-up',
		'bounceIn'          => 'zoom-in',
		'bounceInUp'        => 'fade-up',
		'bounceInDown'      => 'fade-down',
		'bounceInLeft'      => 'fade-left',
		'bounceInRight'     => 'fade-right',
		'rotateIn'          => 'zoom-in',
		'rotateInDownLeft'  => 'fade-left',
		'rotateInDownRight' => 'fade-right',
		'rotateInUpLeft'    => 'fade-left',
		'rotateInUpRight'   => 'fade-right',
		'lightSpeedIn'      => 'fade-right',
		'rollIn'            => 'fade-left',
	);

	/** Exact equivalents (the others are approximated and noted). */
	private const EXACT_ANIMATIONS = array( 'fadeIn', 'fadeInUp', 'fadeInDown', 'fadeInLeft', 'fadeInRight', 'zoomIn', 'slideInUp', 'slideInDown', 'slideInLeft', 'slideInRight', 'flipInX' );

	private const SHAPES = array(
		'mountains'            => 'mountains',
		'clouds'               => 'clouds',
		'zigzag'               => 'zigzag',
		'pyramids'             => 'pyramids',
		'triangle'             => 'triangle',
		'triangle-asymmetrical' => 'triangle-asym',
		'tilt'                 => 'tilt',
		'opacity-tilt'         => 'opacity-tilt',
		'curve'                => 'curve',
		'curve-asymmetrical'   => 'curve-asym',
		'waves'                => 'waves',
		'wave-brush'           => 'wave',
		'waves-pattern'        => 'waves',
		'arrow'                => 'arrow',
		'split'                => 'split',
		'book'                 => 'book',
		'drops'                => 'waves',
		'opacity-fan'          => 'opacity-tilt',
	);

	/** Elementor column gap presets (padding inside every column, px). */
	private const GAPS = array(
		'default'  => 10,
		'no'       => 0,
		'narrow'   => 5,
		'extended' => 15,
		'wide'     => 20,
		'wider'    => 30,
	);

	/** Uncoder tags for Elementor's container / section html_tag. */
	private const TAGS = array( 'div', 'section', 'header', 'footer', 'main', 'article', 'aside', 'nav', 'a' );

	/** Elementor widget defaults tied to global colors / fonts ("default colors / fonts" on). */
	private const DEFAULTS = array(
		'heading'     => array( 'title_color' => 'c:primary', 'typography_typography' => 't:primary' ),
		'text-editor' => array( 'text_color' => 'c:text', 'typography_typography' => 't:text' ),
		'button'      => array( 'background_color' => 'c:accent', 'typography_typography' => 't:accent' ),
		'icon'        => array( 'primary_color' => 'c:primary' ),
		'icon-box'    => array( 'primary_color' => 'c:primary', 'title_color' => 'c:primary', 'title_typography_typography' => 't:primary', 'description_color' => 'c:text', 'description_typography_typography' => 't:text' ),
		'image-box'   => array( 'title_color' => 'c:primary', 'title_typography_typography' => 't:primary', 'description_color' => 'c:text', 'description_typography_typography' => 't:text' ),
		'icon-list'   => array( 'icon_color' => 'c:primary', 'text_color' => 'c:secondary', 'icon_typography_typography' => 't:text' ),
		'counter'     => array( 'number_color' => 'c:primary', 'typography_number_typography' => 't:primary', 'title_color' => 'c:secondary', 'typography_title_typography' => 't:secondary' ),
		'progress'    => array( 'bar_color' => 'c:primary', 'title_color' => 'c:primary', 'typography_typography' => 't:text' ),
		'testimonial' => array( 'content_content_color' => 'c:text', 'content_typography_typography' => 't:text', 'name_text_color' => 'c:primary', 'name_typography_typography' => 't:primary', 'job_text_color' => 'c:secondary', 'job_typography_typography' => 't:secondary' ),
		'tabs'        => array( 'tab_color' => 'c:primary', 'tab_active_color' => 'c:accent', 'tab_typography_typography' => 't:primary', 'content_color' => 'c:text', 'content_typography_typography' => 't:text' ),
		'accordion'   => array( 'title_color' => 'c:primary', 'tab_active_color' => 'c:accent', 'title_typography_typography' => 't:primary', 'content_color' => 'c:text', 'content_typography_typography' => 't:text' ),
		'toggle'      => array( 'title_color' => 'c:primary', 'tab_active_color' => 'c:accent', 'title_typography_typography' => 't:primary', 'content_color' => 'c:text', 'content_typography_typography' => 't:text' ),
		'divider'     => array( 'color' => 'c:secondary' ),
		'form'        => array( 'button_background_color' => 'c:accent', 'button_typography_typography' => 't:accent', 'label_typography_typography' => 't:text', 'field_typography_typography' => 't:text' ),
		'nav-menu'    => array( 'color_menu_item' => 'c:text', 'color_menu_item_hover' => 'c:accent', 'pointer_color_menu_item_hover' => 'c:accent', 'menu_typography_typography' => 't:primary' ),
	);

	/** Elementor dynamic tags → Uncoder tags. */
	private const DYNAMIC_TAGS = array(
		'post-title'             => 'post-title',
		'post-excerpt'           => 'post-excerpt',
		'post-date'              => 'post-date',
		'post-url'               => 'post-url',
		'post-id'                => 'post-id',
		'post-featured-image'    => 'featured-image',
		'post-terms'             => 'post-terms',
		'post-custom-field'      => 'custom-field',
		'archive-title'          => 'archive-title',
		'archive-description'    => 'archive-description',
		'site-title'             => 'site-title',
		'site-tagline'           => 'site-tagline',
		'site-url'               => 'site-url',
		'site-logo'              => 'site-logo',
		'author-name'            => 'author-name',
		'author-info'            => 'author-bio',
		'author-url'             => 'author-url',
		'author-profile-picture' => 'author-avatar',
		'user-info'              => 'user-name',
		'current-date-time'      => 'current-date',
		'request-parameter'      => 'request-param',
		'shortcode'              => 'shortcode',
		'popup'                  => 'popup',
	);

	/** @var array<string,mixed> */
	private array $opt;

	/** @var array<string,mixed> */
	public array $report = array(
		'elements'  => 0,
		'converted' => array(),
		'unmapped'  => array(),
		'settings'  => array(),
		'notes'     => array(),
		'remote'    => array(),
	);

	/** @var array<string,string> Elementor global color id => value. */
	private array $colors = array();

	/** @var array<string,array<string,mixed>> Elementor global typography id => Uncoder value. */
	private array $typos = array();

	/** @var array<int,bool> Templates being resolved (loops). */
	private array $resolving = array();

	/**
	 * @param array<string,mixed> $options See the class comment.
	 */
	public function __construct( array $options = array() ) {
		$this->opt = array_merge(
			array(
				'globals'        => 'fallback',
				'kit'            => array(),
				'default_colors' => false,
				'default_fonts'  => false,
				'remote'         => false,
				'resolve'        => null,
				'templates'      => array(),
			),
			$options
		);
		$kit = is_array( $this->opt['kit'] ) ? $this->opt['kit'] : array();
		foreach ( array_merge( (array) ( $kit['system_colors'] ?? array() ), (array) ( $kit['custom_colors'] ?? array() ) ) as $c ) {
			if ( is_array( $c ) && ! empty( $c['_id'] ) ) {
				$value = Utils::sanitize_color( (string) ( $c['color'] ?? '' ) );
				if ( '' !== $value ) {
					$this->colors[ (string) $c['_id'] ] = $value;
				}
			}
		}
		foreach ( array_merge( (array) ( $kit['system_typography'] ?? array() ), (array) ( $kit['custom_typography'] ?? array() ) ) as $t ) {
			if ( is_array( $t ) && ! empty( $t['_id'] ) ) {
				$this->typos[ (string) $t['_id'] ] = Source::typo_fields( $t, 'typography' ) ?? array();
			}
		}
		if ( 'values' === $this->opt['globals'] && ! $kit ) {
			$this->opt['globals'] = 'fallback';
		}
	}

	/**
	 * @param array<int, mixed> $elements Elementor elements.
	 * @return array<int, array<string,mixed>> Uncoder elements.
	 */
	public function run( array $elements ): array {
		return $this->children( $elements, 0 );
	}

	/**
	 * @param array<int, mixed>   $elements Elementor elements.
	 * @param array<string,mixed> $ctx      Parent context.
	 * @return array<int, array<string,mixed>>
	 */
	public function children( array $elements, int $depth, array $ctx = array() ): array {
		$out = array();
		foreach ( array_values( $elements ) as $el ) {
			foreach ( $this->element( $el, $depth, $ctx ) as $node ) {
				$out[] = $node;
			}
		}
		return $out;
	}

	/**
	 * @param mixed               $el  Elementor element.
	 * @param array<string,mixed> $ctx Parent context.
	 * @return array<int, array<string,mixed>>
	 */
	private function element( $el, int $depth, array $ctx ): array {
		if ( ! is_array( $el ) ) {
			return array();
		}
		if ( $depth > 30 ) {
			$this->note( 'Elements nested more than 30 levels deep were left out.' );
			return array();
		}
		$type = (string) ( $el['elType'] ?? '' );
		switch ( $type ) {
			case 'section':
				return array( $this->section( $el, $depth ) );
			case 'column':
				return array( $this->column( $el, $depth, $ctx ) );
			case 'container':
				return array( $this->container( $el, $depth ) );
			case 'widget':
				return $this->widget( $el, $depth, $ctx );
			default:
				if ( 0 === strpos( $type, 'e-' ) ) {
					return Atomic::element( $el, $depth, $this );
				}
				$this->unmapped( '' !== $type ? $type : 'unknown' );
				return array();
		}
	}

	/* ------------------------------------------------------------------ Layout elements */

	/**
	 * Flexbox / grid container.
	 *
	 * @param array<string,mixed> $el Element.
	 * @return array<string,mixed>
	 */
	private function container( array $el, int $depth ): array {
		$s    = new Source( (array) ( $el['settings'] ?? array() ), 'container', $this );
		$o    = array();
		$cw   = $s->str( 'content_width', 'boxed' );
		$grid = 'grid' === $s->str( 'container_type' );
		if ( 0 === $depth ) {
			if ( 'full' === $cw ) {
				$o['content_width'] = 'full';
			} else {
				$s->sl( $o, 'boxed_width', 'boxed_width' );
			}
		} elseif ( 'boxed' === $cw && $s->has( 'boxed_width' ) ) {
			$o['content_width'] = 'boxed';
			$s->sl( $o, 'boxed_width', 'boxed_width' );
		} else {
			$s->use( 'boxed_width' );
		}
		$s->sl( $o, 'width', 'width' );
		$s->sl( $o, 'min_height', 'min_height' );
		if ( $grid ) {
			$o['layout'] = 'grid';
			foreach ( Source::SUFFIXES as $suffix ) {
				foreach ( array( 'grid_columns_grid' => array( 'grid_columns', 'grid_template' ), 'grid_rows_grid' => array( 'grid_rows', '' ) ) as $from => $to ) {
					$v = $s->raw( $from . $suffix );
					if ( ! is_array( $v ) || Source::blank( $v ) ) {
						continue;
					}
					if ( 'fr' === ( $v['unit'] ?? 'fr' ) && is_numeric( $v['size'] ?? null ) ) {
						$o[ $to[0] . $suffix ] = max( 1, min( 12, (int) $v['size'] ) );
					} elseif ( '' !== $to[1] && is_string( $v['size'] ?? null ) ) {
						$o[ $to[1] . $suffix ] = Utils::css_value( $v['size'] );
					} else {
						$this->setting( 'container', $from );
					}
				}
			}
			$this->gaps( $s, $o, 'grid_gaps' );
			$flow = $s->str( 'grid_auto_flow' );
			if ( 'column' === $flow ) {
				$o['grid_auto_flow'] = 'column';
			}
			$map = array( 'start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end', 'stretch' => 'stretch' );
			$s->opt( $o, 'align', 'grid_align_items', $map, true );
			$s->opt( $o, 'justify', 'grid_justify_content', array( 'start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end', 'space-between' => 'space-between', 'space-around' => 'space-around', 'space-evenly' => 'space-evenly' ), true );
			$s->use( 'grid_justify_items', 'grid_align_content', 'grid_outline' );
		} else {
			$s->opt( $o, 'direction', 'flex_direction', array( 'row' => 'row', 'column' => 'column', 'row-reverse' => 'row-reverse', 'column-reverse' => 'column-reverse' ), true );
			$s->opt( $o, 'justify', 'flex_justify_content', array( 'flex-start' => 'flex-start', 'center' => 'center', 'flex-end' => 'flex-end', 'space-between' => 'space-between', 'space-around' => 'space-around', 'space-evenly' => 'space-evenly' ), true );
			$s->opt( $o, 'align', 'flex_align_items', array( 'flex-start' => 'flex-start', 'center' => 'center', 'flex-end' => 'flex-end', 'stretch' => 'stretch', 'baseline' => 'baseline' ), true );
			$s->opt( $o, 'wrap', 'flex_wrap', array( 'nowrap' => 'nowrap', 'wrap' => 'wrap' ), true );
			$this->gaps( $s, $o, 'flex_gap' );
			if ( ! isset( $o['gap'] ) && ! isset( $o['row_gap'] ) ) {
				$gc = $this->kit_gap( 'column' );
				$gr = $this->kit_gap( 'row' );
				Source::put( $o, 'gap', $gc );
				if ( $gr && $gr !== $gc ) {
					$o['row_gap'] = $gr;
				}
			}
			$s->use( 'flex__is_row', 'flex__is_column', 'flex_align_content', 'flex_wrap_align_content', 'container_type' );
		}
		$tag = $s->str( 'html_tag' );
		if ( in_array( $tag, self::TAGS, true ) ) {
			$o['tag'] = $tag;
			if ( 'a' === $tag ) {
				$s->lnk( $o, 'link', 'link' );
			}
		}
		// Elementor containers are padded by default (Site Settings → Layout → Container padding, 10px).
		$fill = $this->container_padding();
		$pad  = $s->dims( 'padding', $fill );
		$o['_padding'] = $pad ? $pad : $fill + array( 'unit' => 'px', 'linked' => true );
		foreach ( array( '_tablet', '_mobile' ) as $suffix ) {
			Source::put( $o, '_padding' . $suffix, $s->dims( 'padding' . $suffix ) );
		}
		$s->dm( $o, '_margin', 'margin' );
		$this->layout_common( $s, $o );
		$s->use( 'isInner', 'presetTitle', 'presetIcon' );
		$node = $this->node( $el, 'container', $o, $s );
		$node['children'] = $this->children( (array) ( $el['elements'] ?? array() ), $depth + 1, array( 'row' => $grid || in_array( $o['direction'] ?? 'column', array( 'row', 'row-reverse' ), true ) ) );
		return $node;
	}

	/**
	 * Legacy section (and inner section): a row of columns.
	 *
	 * @param array<string,mixed> $el Element.
	 * @return array<string,mixed>
	 */
	private function section( array $el, int $depth ): array {
		$s     = new Source( (array) ( $el['settings'] ?? array() ), 'section', $this );
		$o     = array();
		$inner = $depth > 0 || ! empty( $el['isInner'] );
		$cols  = array_values( array_filter( (array) ( $el['elements'] ?? array() ), static fn( $c ) => is_array( $c ) && 'column' === ( $c['elType'] ?? '' ) ) );

		$layout = $s->str( 'layout', 'boxed' );
		if ( ! $inner ) {
			if ( 'full_width' === $layout ) {
				$o['content_width'] = 'full';
				$s->use( 'content_width' );
			} else {
				$s->sl( $o, 'boxed_width', 'content_width' );
			}
		} elseif ( 'boxed' === $layout && $s->has( 'content_width' ) ) {
			$o['content_width'] = 'boxed';
			$s->sl( $o, 'boxed_width', 'content_width' );
		} else {
			$s->use( 'content_width' );
		}
		$h_key = $inner ? 'height_inner' : 'height';
		$h     = $s->str( $h_key, 'default' );
		if ( 'full' === $h ) {
			$o['min_height'] = array( 'size' => 100, 'unit' => 'vh' );
		} elseif ( 'min-height' === $h ) {
			$s->sl( $o, 'min_height', $inner ? 'custom_height_inner' : 'custom_height' );
		}
		$s->use( 'height', 'height_inner', 'custom_height', 'custom_height_inner', 'structure', 'stretch_section', 'gap_columns_custom' );
		if ( 'default' !== $h ) {
			$s->opt( $o, 'align', 'column_position', array( 'top' => 'flex-start', 'middle' => 'center', 'bottom' => 'flex-end', 'stretch' => 'stretch' ) );
		} else {
			$s->use( 'column_position' );
		}
		$gap = $s->str( 'gap', 'default' );
		if ( 'custom' === $gap ) {
			$custom = $s->num( 'gap_columns_custom' );
			$gap_px = null !== $custom ? $custom / 2 : 10;
		} else {
			$gap_px = self::GAPS[ $gap ] ?? 10;
		}
		$tag = $s->str( 'html_tag' );
		if ( in_array( $tag, self::TAGS, true ) ) {
			$o['tag'] = $tag;
		}
		$pad = $s->dims( 'padding', array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ) );
		if ( $pad ) {
			$o['_padding'] = $pad;
		} elseif ( ! $inner ) {
			// Legacy sections have no padding; Uncoder would add its section spacing.
			$o['_padding'] = array( 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0, 'unit' => 'px', 'linked' => true );
		}
		foreach ( array( '_tablet', '_mobile' ) as $suffix ) {
			Source::put( $o, '_padding' . $suffix, $s->dims( 'padding' . $suffix ) );
		}
		$s->dm( $o, '_margin', 'margin' );
		$this->layout_common( $s, $o );

		$ctx = array(
			'gap'              => $gap_px,
			'content_position' => $s->str( 'content_position' ),
		);

		// One unstyled column: the section itself holds the widgets (a simpler tree, same look).
		if ( 1 === count( $cols ) && $this->plain_column( $cols[0] ) && ( ! isset( $o['_padding'] ) || 'px' === $o['_padding']['unit'] ) ) {
			$cs = new Source( (array) ( $cols[0]['settings'] ?? array() ), 'column', $this );
			foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
				$o['_padding'][ $side ] = ( is_numeric( $o['_padding'][ $side ] ?? null ) ? (float) $o['_padding'][ $side ] : 0 ) + $gap_px;
			}
			$o['_padding']['unit']   = 'px';
			$o['_padding']['linked'] = false;
			$this->column_flow( $cs, $o, $ctx );
			$inline           = $this->inline_flow( $cs, $o, (array) ( $cols[0]['elements'] ?? array() ) );
			$node             = $this->node( $el, 'container', $o, $s );
			$node['children'] = $this->children( (array) ( $cols[0]['elements'] ?? array() ), $depth + 1, $ctx + array( 'inline_align' => $inline ? '' : $cs->str( 'align' ), 'row' => $inline ) );
			if ( $inline ) {
				$node['children'] = self::full_width( $node['children'] );
			}
			$this->converted( 'column' );
			return $node;
		}

		// Columns side by side; they stack on phones unless their mobile widths keep them in a row.
		$o['direction'] = 'row';
		$o['gap']       = array( 'size' => 0, 'unit' => 'px' );
		$reverse_t      = '' !== $s->str( 'reverse_order_tablet' );
		$reverse_m      = '' !== $s->str( 'reverse_order_mobile' );
		foreach ( array( '_tablet' => $reverse_t, '_mobile' => $reverse_m ) as $suffix => $reverse ) {
			$widths = array();
			foreach ( $cols as $col ) {
				$w = $col['settings'][ '_inline_size' . $suffix ] ?? '';
				if ( is_numeric( $w ) ) {
					$widths[] = (float) $w;
				}
			}
			$stack = '_mobile' === $suffix ? ( ! $widths || min( $widths ) >= 100 ) : ( $widths && min( $widths ) >= 100 );
			if ( $stack ) {
				$o[ 'direction' . $suffix ] = $reverse ? 'column-reverse' : 'column';
			} else {
				if ( $reverse ) {
					$o[ 'direction' . $suffix ] = 'row-reverse';
				}
				if ( $widths && array_sum( $widths ) > 100.5 ) {
					$o[ 'wrap' . $suffix ] = 'wrap';
				}
			}
		}
		$node             = $this->node( $el, 'container', $o, $s );
		$node['children'] = array();
		foreach ( (array) ( $el['elements'] ?? array() ) as $child ) {
			foreach ( $this->element( $child, $depth + 1, $ctx ) as $c ) {
				$node['children'][] = $c;
			}
		}
		return $node;
	}

	/**
	 * A column with nothing of its own but its width and spacing.
	 *
	 * @param array<string,mixed> $col Column element.
	 */
	private function plain_column( array $col ): bool {
		$allowed = array( '_column_size', '_inline_size', '_inline_size_tablet', '_inline_size_mobile', 'content_position', 'align', 'space_between_widgets', '_title' );
		foreach ( (array) ( $col['settings'] ?? array() ) as $k => $v ) {
			if ( ! in_array( $k, $allowed, true ) && ! Source::blank( $v ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Vertical alignment and widget spacing of a column (or of a section that absorbed its column).
	 *
	 * @param array<string,mixed> $o   Output settings.
	 * @param array<string,mixed> $ctx Section context.
	 */
	private function column_flow( Source $s, array &$o, array $ctx ): void {
		$pos = $s->str( 'content_position', (string) ( $ctx['content_position'] ?? '' ) );
		$map = array( 'top' => 'flex-start', 'center' => 'center', 'middle' => 'center', 'bottom' => 'flex-end', 'space-between' => 'space-between', 'space-around' => 'space-around', 'space-evenly' => 'space-evenly' );
		if ( isset( $map[ $pos ] ) ) {
			$o['justify'] = $map[ $pos ];
		}
		foreach ( Source::SUFFIXES as $suffix ) {
			$gap = $s->num( 'space_between_widgets' . $suffix );
			if ( null !== $gap ) {
				$o[ 'gap' . $suffix ] = array( 'size' => Utils::number( $gap ), 'unit' => 'px' );
			}
		}
		if ( ! isset( $o['gap'] ) ) {
			Source::put( $o, 'gap', $this->kit_gap( 'row' ) );
		}
		$s->use( 'align' );
	}

	/**
	 * Legacy columns lay "inline" widgets (auto / custom width) side by side and wrap the rest: such a
	 * column becomes a wrapping row whose other children take the full width.
	 *
	 * @param array<string,mixed> $o        Output settings of the column (after column_flow()).
	 * @param array<int, mixed>   $elements The column's Elementor children.
	 */
	private function inline_flow( Source $s, array &$o, array $elements ): bool {
		$inline = false;
		foreach ( $elements as $child ) {
			if ( is_array( $child ) && 'widget' === ( $child['elType'] ?? '' ) && in_array( (string) ( $child['settings']['_element_width'] ?? '' ), array( 'auto', 'initial', 'inline' ), true ) ) {
				$inline = true;
				break;
			}
		}
		if ( ! $inline ) {
			return false;
		}
		$vertical = $o['justify'] ?? '';
		unset( $o['justify'] );
		foreach ( Source::SUFFIXES as $suffix ) {
			if ( isset( $o[ 'gap' . $suffix ] ) ) {
				$o[ 'row_gap' . $suffix ] = $o[ 'gap' . $suffix ];
			}
		}
		$o['row_gap']  = $o['row_gap'] ?? array( 'size' => 20, 'unit' => 'px' );
		$o['gap']      = array( 'size' => 0, 'unit' => 'px' );
		$o['direction'] = 'row';
		$o['wrap']      = 'wrap';
		$align = array( 'flex-start' => 'flex-start', 'center' => 'center', 'flex-end' => 'flex-end', 'space-between' => 'space-between', 'space-around' => 'space-around', 'space-evenly' => 'space-evenly' )[ $s->str( 'align' ) ] ?? '';
		if ( '' !== $align ) {
			$o['justify'] = $align;
		}
		$o['_custom_css'] = trim( ( $o['_custom_css'] ?? '' ) . "\nselector{align-content:" . ( '' !== $vertical ? $vertical : 'flex-start' ) . '}' );
		return true;
	}

	/**
	 * Children of a wrapping column row: everything that is not inline takes the full width.
	 *
	 * @param array<int, array<string,mixed>> $nodes Uncoder children.
	 * @return array<int, array<string,mixed>>
	 */
	private static function full_width( array $nodes ): array {
		foreach ( $nodes as &$node ) {
			if ( 'container' === ( $node['type'] ?? '' ) ) {
				$node['settings']['width'] = $node['settings']['width'] ?? array( 'size' => 100, 'unit' => '%' );
			} elseif ( ! isset( $node['settings']['_width'] ) ) {
				$node['settings']['_width'] = 'full';
			}
		}
		unset( $node );
		return $nodes;
	}

	/**
	 * The Elementor kit's widget spacing when the kit is known but not imported (an imported kit sets the
	 * Design System gap instead). Null when it is Uncoder's default.
	 *
	 * @return array<string,mixed>|null
	 */
	private function kit_gap( string $axis ): ?array {
		if ( 'values' !== $this->opt['globals'] ) {
			return null;
		}
		$gap = $this->opt['kit']['space_between_widgets'] ?? null;
		if ( ! is_array( $gap ) ) {
			return null;
		}
		$v = Source::to_slider(
			array(
				'size' => $gap[ $axis ] ?? $gap['size'] ?? '',
				'unit' => $gap['unit'] ?? 'px',
			)
		);
		return $v && ! ( 20 == $v['size'] && 'px' === $v['unit'] ) ? $v : null; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
	}

	/**
	 * Legacy column.
	 *
	 * @param array<string,mixed> $el  Element.
	 * @param array<string,mixed> $ctx Section context (gap, content_position).
	 * @return array<string,mixed>
	 */
	private function column( array $el, int $depth, array $ctx ): array {
		$s = new Source( (array) ( $el['settings'] ?? array() ), 'column', $this );
		$o = array();
		$w = $s->raw( '_inline_size' );
		if ( ! is_numeric( $w ) ) {
			// Preset column classes: elementor-col-33 is 33.333% etc.
			$w = $s->raw( '_column_size' );
			$w = is_numeric( $w ) ? ( array( 11 => 11.111, 14 => 14.285, 16 => 16.666, 33 => 33.333, 66 => 66.666, 83 => 83.333 )[ (int) $w ] ?? $w ) : $w;
		}
		$s->use( '_column_size' );
		if ( is_numeric( $w ) && (float) $w > 0 ) {
			$o['width'] = array( 'size' => round( (float) $w, 3 ), 'unit' => '%' );
		}
		foreach ( array( '_tablet', '_mobile' ) as $suffix ) {
			$v = $s->raw( '_inline_size' . $suffix );
			if ( is_numeric( $v ) && (float) $v > 0 ) {
				$o[ 'width' . $suffix ] = array( 'size' => round( (float) $v, 3 ), 'unit' => '%' );
			}
		}
		$gap = (float) ( $ctx['gap'] ?? 10 );
		$pad = $s->dims( 'padding' );
		if ( $pad ) {
			$o['_padding'] = $pad;
		} elseif ( $gap > 0 ) {
			$o['_padding'] = array( 'top' => $gap, 'right' => $gap, 'bottom' => $gap, 'left' => $gap, 'unit' => 'px', 'linked' => true );
		}
		foreach ( array( '_tablet', '_mobile' ) as $suffix ) {
			Source::put( $o, '_padding' . $suffix, $s->dims( 'padding' . $suffix ) );
		}
		$s->dm( $o, '_margin', 'margin' );
		$tag = $s->str( 'html_tag' );
		if ( in_array( $tag, self::TAGS, true ) ) {
			$o['tag'] = $tag;
		}
		$this->column_flow( $s, $o, $ctx );
		$inline = $this->inline_flow( $s, $o, (array) ( $el['elements'] ?? array() ) );
		$this->layout_common( $s, $o );
		$node             = $this->node( $el, 'container', $o, $s );
		$node['children'] = $this->children( (array) ( $el['elements'] ?? array() ), $depth + 1, array( 'inline_align' => $inline ? '' : $s->str( 'align' ), 'row' => $inline ) );
		if ( $inline ) {
			$node['children'] = self::full_width( $node['children'] );
		}
		return $node;
	}

	/**
	 * Gaps ({column, row, unit, size}) → gap / row_gap per device.
	 *
	 * @param array<string,mixed> $o Output.
	 */
	private function gaps( Source $s, array &$o, string $key ): void {
		foreach ( Source::SUFFIXES as $suffix ) {
			$v = $s->raw( $key . $suffix );
			if ( ! is_array( $v ) ) {
				continue;
			}
			$unit = isset( $v['unit'] ) && is_string( $v['unit'] ) ? $v['unit'] : 'px';
			$col  = $v['column'] ?? $v['size'] ?? '';
			$row  = $v['row'] ?? $col;
			$gc   = Source::to_slider( array( 'size' => $col, 'unit' => $unit ) );
			$gr   = Source::to_slider( array( 'size' => $row, 'unit' => $unit ) );
			if ( $gc ) {
				$o[ 'gap' . $suffix ] = $gc;
			}
			if ( $gr && $gr !== $gc ) {
				$o[ 'row_gap' . $suffix ] = $gr;
			}
		}
	}

	/** Default container padding (Elementor kit "Container padding", 10px). @return array<string,int|float> */
	private function container_padding(): array {
		$kit = Source::to_dims( $this->opt['kit']['container_padding'] ?? null, array( 'top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10 ) );
		if ( $kit && 'px' === $kit['unit'] ) {
			return array( 'top' => $kit['top'], 'right' => $kit['right'], 'bottom' => $kit['bottom'], 'left' => $kit['left'] );
		}
		return array( 'top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10 );
	}

	/**
	 * Style and behaviour shared by sections, columns and containers.
	 *
	 * @param array<string,mixed> $o Output.
	 */
	private function layout_common( Source $s, array &$o ): void {
		Source::put( $o, 'background', $s->bg( 'background', array( 'classic', 'gradient', 'video', 'slideshow' ) ) );
		Source::put( $o, 'background_hover', $s->bg( 'background_hover' ) );
		$s->use( 'background_hover_transition' );
		$overlay = $s->bg( 'background_overlay' );
		if ( $overlay ) {
			$o['overlay'] = $overlay;
			$opacity      = $s->num( 'background_overlay_opacity' );
			$o['overlay_opacity'] = null !== $opacity ? max( 0, min( 1, $opacity ) ) : 0.5;
		} else {
			$s->use( 'background_overlay_opacity' );
		}
		if ( $s->bg( 'background_overlay_hover' ) ) {
			$this->setting( $s->type, 'background_overlay_hover' );
		}
		$s->use( 'background_overlay_hover_opacity', 'background_overlay_hover_transition', 'overlay_blend_mode', 'css_filters_css_filter' );
		$s->brd( $o, 'border', 'border' );
		$s->radius( $o, 'radius', 'border_radius' );
		$s->shd( $o, 'shadow', 'box_shadow' );
		$s->shd( $o, 'shadow_hover', 'box_shadow_hover' );
		$hover_border = $s->border( 'border_hover' );
		if ( ! empty( $hover_border['color'] ) ) {
			$o['border_hover_color'] = $hover_border['color'];
		}
		$s->use( 'border_hover_radius', 'border_hover_transition' );
		foreach ( array( 'top', 'bottom' ) as $side ) {
			$shape = $s->str( 'shape_divider_' . $side );
			if ( '' === $shape ) {
				continue;
			}
			if ( ! isset( self::SHAPES[ $shape ] ) ) {
				$this->setting( $s->type, 'shape_divider_' . $side . ': ' . $shape );
				continue;
			}
			$o[ 'shape_' . $side ] = self::SHAPES[ $shape ];
			$s->col( $o, 'shape_' . $side . '_color', 'shape_divider_' . $side . '_color' );
			$s->sl( $o, 'shape_' . $side . '_width', 'shape_divider_' . $side . '_width' );
			$s->sl( $o, 'shape_' . $side . '_height', 'shape_divider_' . $side . '_height' );
			$s->flag( $o, 'shape_' . $side . '_flip', 'shape_divider_' . $side . '_flip' );
			$s->flag( $o, 'shape_' . $side . '_invert', 'shape_divider_' . $side . '_negative' );
			$s->flag( $o, 'shape_' . $side . '_front', 'shape_divider_' . $side . '_above_content' );
		}
		// Section / column "Typography" colors.
		$s->col( $o, 'text_color', 'color_text' );
		$s->col( $o, 'link_color', 'color_link' );
		$css     = array();
		$heading = $s->color( 'heading_color' );
		if ( '' !== $heading ) {
			$css[] = 'selector{--uncoder-heading-color:' . $heading . '}selector :is(h1,h2,h3,h4,h5,h6){color:' . $heading . '}';
		}
		$link_hover = $s->color( 'color_link_hover' );
		if ( '' !== $link_hover ) {
			$css[] = 'selector a:not(.uncoder-btn):hover{color:' . $link_hover . '}';
		}
		$align = $s->str( 'text_align' );
		if ( in_array( $align, array( 'left', 'center', 'right', 'justify', 'start', 'end' ), true ) ) {
			$css[] = 'selector{text-align:' . $align . '}';
		}
		$s->use( 'text_align' );
		$overflow = $s->str( 'overflow' );
		if ( in_array( $overflow, array( 'hidden', 'auto', 'clip' ), true ) ) {
			$o['_overflow'] = $overflow;
		}
		$this->identity( $s, $o, $css );
		$this->flex_child( $s, $o );
		$this->position( $s, $o );
		$this->motion( $s, $o );
		$s->use( 'z_index' );
		foreach ( Source::SUFFIXES as $suffix ) {
			$z = $s->raw( 'z_index' . $suffix );
			if ( is_numeric( $z ) ) {
				$o[ '_z_index' . $suffix ] = (int) $z;
			}
		}
		// Background motion effects (Pro): a moving background image.
		if ( $s->yes( 'background_motion_fx_motion_fx_scrolling' ) && $s->yes( 'background_motion_fx_translateY_effect' ) && isset( $o['background'] ) && in_array( $o['background']['type'] ?? '', array( 'classic', 'slideshow' ), true ) ) {
			$o['bg_motion'] = 'parallax';
			$speed          = $s->num( 'background_motion_fx_translateY_speed' );
			if ( null !== $speed ) {
				$o['bg_motion_speed'] = max( 1, min( 10, (int) round( $speed ) ) );
			}
		}
	}

	/**
	 * Classes, anchor, animation, visibility, custom CSS and attributes (every element).
	 *
	 * @param array<string,mixed> $o   Output.
	 * @param string[]            $css Extra custom CSS rules.
	 */
	private function identity( Source $s, array &$o, array $css = array() ): void {
		$classes = trim( $s->str( '_css_classes' ) . ' ' . $s->str( 'css_classes' ) );
		if ( '' !== $classes ) {
			$o['_css_classes'] = $classes;
		}
		$anchor = $s->str( '_element_id' );
		if ( '' !== $anchor ) {
			$o['_css_id'] = sanitize_html_class( $anchor );
		}
		$anim = $s->str( '_animation', $s->str( 'animation' ) );
		if ( '' !== $anim && 'none' !== $anim ) {
			if ( isset( self::ANIMATIONS[ $anim ] ) ) {
				$o['_animation'] = self::ANIMATIONS[ $anim ];
				if ( ! in_array( $anim, self::EXACT_ANIMATIONS, true ) ) {
					$this->note( sprintf( 'The “%s” entrance animation has no exact equivalent; the closest one was used.', $anim ) );
				}
				$duration = $s->str( 'animation_duration' );
				if ( 'slow' === $duration ) {
					$o['_animation_duration'] = 2000;
				} elseif ( 'fast' === $duration ) {
					$o['_animation_duration'] = 750;
				}
				$delay = $s->num( '_animation_delay' ) ?? $s->num( 'animation_delay' );
				if ( null !== $delay && $delay > 0 ) {
					$o['_animation_delay'] = (int) min( 10000, $delay );
				}
			} else {
				$this->setting( $s->type, 'animation: ' . $anim );
			}
		}
		$s->use( '_animation', 'animation', 'animation_duration', '_animation_delay', 'animation_delay' );
		foreach ( array( 'desktop', 'tablet', 'mobile' ) as $device ) {
			if ( '' !== $s->str( 'hide_' . $device ) ) {
				$o[ '_hide_' . $device ] = true;
			}
		}
		$custom = $s->str( 'custom_css' );
		if ( '' !== $custom ) {
			$css[] = $custom;
		}
		if ( $css ) {
			$o['_custom_css'] = trim( ( $o['_custom_css'] ?? '' ) . "\n" . implode( "\n", $css ) );
		}
		$attrs = $s->str( '_attributes' );
		if ( '' !== $attrs ) {
			$o['_attributes'] = $attrs;
		}
	}

	/**
	 * Flex child settings (Elementor "Align self", "Order", "Size").
	 *
	 * @param array<string,mixed> $o Output.
	 */
	private function flex_child( Source $s, array &$o ): void {
		foreach ( Source::SUFFIXES as $suffix ) {
			$align = $s->str( '_flex_align_self' . $suffix );
			$map   = array( 'flex-start' => 'flex-start', 'center' => 'center', 'flex-end' => 'flex-end', 'stretch' => 'stretch' );
			if ( isset( $map[ $align ] ) ) {
				$o[ '_align_self' . $suffix ] = $map[ $align ];
			}
			$order = $s->str( '_flex_order' . $suffix );
			if ( 'start' === $order ) {
				$o[ '_order' . $suffix ] = -99;
			} elseif ( 'end' === $order ) {
				$o[ '_order' . $suffix ] = 99;
			} elseif ( 'custom' === $order ) {
				$n = $s->num( '_flex_order_custom' . $suffix );
				if ( null !== $n ) {
					$o[ '_order' . $suffix ] = (int) max( -99, min( 99, $n ) );
				}
			}
			$size = $s->str( '_flex_size' . $suffix );
			if ( 'grow' === $size ) {
				$o[ '_flex_grow' . $suffix ] = 1;
			} elseif ( 'shrink' === $size ) {
				$o[ '_flex_shrink' . $suffix ] = 1;
			} elseif ( 'none' === $size ) {
				$o[ '_flex_grow' . $suffix ]   = 0;
				$o[ '_flex_shrink' . $suffix ] = 0;
			} elseif ( 'custom' === $size ) {
				$g  = $s->num( '_flex_grow' . $suffix );
				$sh = $s->num( '_flex_shrink' . $suffix );
				if ( null !== $g ) {
					$o[ '_flex_grow' . $suffix ] = Utils::number( max( 0, min( 99, $g ) ) );
				}
				if ( null !== $sh ) {
					$o[ '_flex_shrink' . $suffix ] = Utils::number( max( 0, min( 99, $sh ) ) );
				}
			}
		}
		$s->use( '_flex_grow', '_flex_shrink', '_flex_order_custom' );
	}

	/**
	 * Absolute / fixed positioning with offsets.
	 *
	 * @param array<string,mixed> $o Output.
	 */
	private function position( Source $s, array &$o ): void {
		$pos = $s->str( '_position', $s->str( 'position' ) );
		$s->use( '_position', 'position', '_offset_orientation_h', '_offset_x', '_offset_x_end', '_offset_orientation_v', '_offset_y', '_offset_y_end' );
		if ( ! in_array( $pos, array( 'absolute', 'fixed' ), true ) ) {
			return;
		}
		$o['_position'] = $pos;
		foreach ( Source::SUFFIXES as $suffix ) {
			$h  = $s->str( '_offset_orientation_h', 'start' );
			$v  = $s->str( '_offset_orientation_v', 'start' );
			$x  = Source::to_slider( $s->raw( ( 'end' === $h ? '_offset_x_end' : '_offset_x' ) . $suffix ) );
			$y  = Source::to_slider( $s->raw( ( 'end' === $v ? '_offset_y_end' : '_offset_y' ) . $suffix ) );
			if ( '' !== $suffix && ! $x && ! $y ) {
				continue;
			}
			$x    = $x && 'custom' !== $x['unit'] ? $x : array( 'size' => 0, 'unit' => 'px' );
			$y    = $y && 'custom' !== $y['unit'] ? $y : array( 'size' => 0, 'unit' => 'px' );
			$unit = $x['unit'];
			if ( $y['unit'] !== $unit && 0 != $y['size'] && 0 != $x['size'] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
				$this->note( 'An absolute position mixed two units; the horizontal unit was kept.' );
			} elseif ( 0 == $x['size'] ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
				$unit = $y['unit'];
			}
			$o[ '_offset' . $suffix ] = array(
				'top'    => 'end' === $v ? 'auto' : $y['size'],
				'right'  => 'end' === $h ? $x['size'] : 'auto',
				'bottom' => 'end' === $v ? $y['size'] : 'auto',
				'left'   => 'end' === $h ? 'auto' : $x['size'],
				'unit'   => $unit,
				'linked' => false,
			);
		}
	}

	/**
	 * Motion effects (Pro): scrolling and mouse effects, sticky.
	 *
	 * @param array<string,mixed> $o Output.
	 */
	private function motion( Source $s, array &$o ): void {
		$sticky = $s->str( 'sticky' );
		if ( in_array( $sticky, array( 'top', 'bottom' ), true ) ) {
			$o['_sticky'] = $sticky;
			$offset       = $s->num( 'sticky_offset' );
			if ( null !== $offset ) {
				$o['_sticky_offset'] = (int) max( 0, min( 500, $offset ) );
			}
			$on = $s->raw( 'sticky_on' );
			if ( is_array( $on ) && $on ) {
				$o['_sticky_on'] = array_values( array_intersect( $on, Breakpoints::devices() ) );
			}
			if ( $s->yes( 'sticky_parent' ) ) {
				$this->setting( $s->type, 'sticky_parent' );
			}
		}
		$s->use( 'sticky', 'sticky_offset', 'sticky_on', 'sticky_effects_offset', 'sticky_anchor_link_offset' );
		$dir = static fn( string $d ): string => array( 'out-in' => 'in', 'in-out' => 'out', 'out-in-out' => 'in-out' )[ $d ] ?? '';
		if ( $s->yes( 'motion_fx_motion_fx_scrolling' ) ) {
			if ( $s->yes( 'motion_fx_translateY_effect' ) ) {
				$speed          = $s->num( 'motion_fx_translateY_speed' ) ?? 4;
				$o['_motion_y'] = ( 'negative' === $s->str( 'motion_fx_translateY_direction' ) ? -1 : 1 ) * min( 10, $speed );
				$range          = $s->raw( 'motion_fx_translateY_affectedRange' );
				if ( is_array( $range['sizes'] ?? null ) ) {
					$o['_motion_start'] = (int) ( $range['sizes']['start'] ?? 0 );
					$o['_motion_end']   = (int) ( $range['sizes']['end'] ?? 100 );
				}
			}
			if ( $s->yes( 'motion_fx_translateX_effect' ) ) {
				$speed          = $s->num( 'motion_fx_translateX_speed' ) ?? 4;
				$o['_motion_x'] = ( 'negative' === $s->str( 'motion_fx_translateX_direction' ) ? 1 : -1 ) * min( 10, $speed );
			}
			if ( $s->yes( 'motion_fx_rotateZ_effect' ) ) {
				$speed               = $s->num( 'motion_fx_rotateZ_speed' ) ?? 1;
				$o['_motion_rotate'] = ( 'negative' === $s->str( 'motion_fx_rotateZ_direction' ) ? -1 : 1 ) * min( 10, $speed );
			}
			if ( $s->yes( 'motion_fx_opacity_effect' ) ) {
				Source::put( $o, '_motion_fade', $dir( $s->str( 'motion_fx_opacity_direction', 'out-in' ) ) );
			}
			if ( $s->yes( 'motion_fx_blur_effect' ) ) {
				Source::put( $o, '_motion_blur', $dir( $s->str( 'motion_fx_blur_direction', 'out-in' ) ) );
			}
			if ( $s->yes( 'motion_fx_scale_effect' ) ) {
				$scale = array( 'out-in' => 'grow', 'in-out' => 'shrink', 'out-in-out' => 'in-out' )[ $s->str( 'motion_fx_scale_direction', 'out-in' ) ] ?? '';
				if ( '' !== $scale ) {
					$o['_motion_scale']        = $scale;
					$o['_motion_scale_amount'] = (int) max( 5, min( 100, 10 * ( $s->num( 'motion_fx_scale_speed' ) ?? 4 ) ) );
				}
			}
		}
		if ( $s->yes( 'motion_fx_motion_fx_mouse' ) ) {
			if ( $s->yes( 'motion_fx_mouseTrack_effect' ) ) {
				$o['_motion_mouse']       = 'track';
				$o['_motion_mouse_speed'] = ( 'negative' === $s->str( 'motion_fx_mouseTrack_direction' ) ? -1 : 1 ) * min( 10, $s->num( 'motion_fx_mouseTrack_speed' ) ?? 1 );
			} elseif ( $s->yes( 'motion_fx_tilt_effect' ) ) {
				$o['_motion_mouse']       = 'tilt';
				$o['_motion_mouse_speed'] = ( 'negative' === $s->str( 'motion_fx_tilt_direction' ) ? -1 : 1 ) * min( 10, $s->num( 'motion_fx_tilt_speed' ) ?? 4 );
			}
		}
		if ( isset( $o['_motion_y'] ) || isset( $o['_motion_x'] ) || isset( $o['_motion_mouse'] ) || isset( $o['_motion_fade'] ) ) {
			$devices = $s->raw( 'motion_fx_devices' );
			if ( is_array( $devices ) && $devices && count( $devices ) < 3 ) {
				$o['_motion_devices'] = array_values( array_intersect( $devices, array( 'desktop', 'tablet', 'mobile' ) ) );
			}
		}
		foreach ( array_keys( $s->all() ) as $k ) {
			if ( 0 === strpos( (string) $k, 'motion_fx_' ) && ! Source::blank( $s->all()[ $k ] ) && ! preg_match( '/_(effect|speed|direction|affectedRange|devices)$|motion_fx_motion_fx_(scrolling|mouse)$/', (string) $k ) ) {
				$this->setting( $s->type, (string) $k );
				$s->use( (string) $k );
			}
			if ( 0 === strpos( (string) $k, 'motion_fx_' ) || 0 === strpos( (string) $k, 'background_motion_fx_' ) ) {
				$s->use( (string) $k );
			}
		}
	}

	/* ------------------------------------------------------------------ Widgets */

	/**
	 * @param array<string,mixed> $el  Element.
	 * @param array<string,mixed> $ctx Parent context (inline_align).
	 * @return array<int, array<string,mixed>>
	 */
	private function widget( array $el, int $depth, array $ctx ): array {
		$wtype = (string) ( $el['widgetType'] ?? '' );
		// Global widgets and the Template widget point at another Elementor document.
		if ( 'global' === $wtype || 'template' === $wtype ) {
			return $this->reference( $el, $wtype, $depth );
		}
		$s = new Source( (array) ( $el['settings'] ?? array() ), $wtype, $this );
		foreach ( self::DEFAULTS[ $wtype ] ?? array() as $key => $ref ) {
			$is_font = 't' === $ref[0];
			if ( $is_font ? $this->opt['default_fonts'] : $this->opt['default_colors'] ) {
				$s->default_global( $key, 'globals/' . ( $is_font ? 'typography' : 'colors' ) . '?id=' . substr( $ref, 2 ) );
			}
		}
		$map = Widgets::map( $wtype );
		$res = $map ? $map( $s, $this, $el, $depth ) : null;
		if ( ! is_array( $res ) ) {
			$this->unmapped( '' !== $wtype ? $wtype : 'unknown' );
			return array( $this->placeholder( $el, $wtype ) );
		}
		list( $type, $o ) = $res;
		$this->widget_common( $s, $o, $ctx );
		// The Icon widget's root is the icon itself: in a stacking container it would stretch to the full
		// width, so its alignment becomes the element's own alignment (Elementor centers icons by default).
		if ( 'icon' === $type && empty( $ctx['row'] ) ) {
			foreach ( Source::SUFFIXES as $suffix ) {
				$align = $o[ 'align' . $suffix ] ?? ( '' === $suffix ? 'center' : '' );
				$self  = array( 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' )[ $align ] ?? '';
				if ( '' !== $self && ! isset( $o[ '_align_self' . $suffix ] ) ) {
					$o[ '_align_self' . $suffix ] = $self;
				}
			}
		}
		$node = $this->node( $el, $type, $o, $s, Widgets::DYNAMIC[ $wtype ] ?? array() );
		if ( isset( $res[2] ) && is_array( $res[2] ) ) {
			$node['children'] = $res[2];
		}
		return array( $node );
	}

	/**
	 * Advanced-tab settings every widget has.
	 *
	 * @param array<string,mixed> $o   Output.
	 * @param array<string,mixed> $ctx Parent context.
	 */
	private function widget_common( Source $s, array &$o, array $ctx ): void {
		$s->dm( $o, '_margin', '_margin' );
		$s->dm( $o, '_padding', '_padding' );
		$inline = false;
		foreach ( Source::SUFFIXES as $suffix ) {
			$w = $s->str( '_element_width' . $suffix );
			if ( 'inherit' === $w ) {
				$o[ '_width' . $suffix ] = 'full';
			} elseif ( in_array( $w, array( 'auto', 'inline' ), true ) ) {
				$o[ '_width' . $suffix ] = 'auto';
				$inline                  = $inline || '' === $suffix;
			} elseif ( 'initial' === $w ) {
				$o[ '_width' . $suffix ] = 'custom';
				$inline                  = $inline || '' === $suffix;
			}
			// A custom width per device applies while the device (or the desktop) uses the custom mode.
			if ( 'custom' === ( $o[ '_width' . $suffix ] ?? ( '' === $w ? ( $o['_width'] ?? '' ) : '' ) ) ) {
				Source::put( $o, '_custom_width' . $suffix, $s->slider( '_element_custom_width' . $suffix ) );
			}
			$va  = $s->str( '_element_vertical_align' . $suffix );
			$map = array( 'flex-start' => 'flex-start', 'center' => 'center', 'flex-end' => 'flex-end', 'stretch' => 'stretch' );
			if ( isset( $map[ $va ] ) ) {
				$o[ '_align_self' . $suffix ] = $map[ $va ];
			}
		}
		$s->use( '_element_custom_width' );
		// Inline widgets in a legacy column follow the column's "Horizontal align".
		$col_align = array( 'flex-start' => 'flex-start', 'center' => 'center', 'flex-end' => 'flex-end' )[ (string) ( $ctx['inline_align'] ?? '' ) ] ?? '';
		if ( $inline && '' !== $col_align && ! isset( $o['_align_self'] ) ) {
			$o['_align_self'] = $col_align;
		}
		foreach ( Source::SUFFIXES as $suffix ) {
			$z = $s->raw( '_z_index' . $suffix );
			if ( is_numeric( $z ) ) {
				$o[ '_z_index' . $suffix ] = (int) $z;
			}
		}
		Source::put( $o, '_background', $s->bg( '_background' ) );
		if ( $s->bg( '_background_hover' ) ) {
			$this->setting( $s->type, '_background_hover' );
		}
		$s->use( '_background_hover_transition' );
		$s->brd( $o, '_border', '_border' );
		$s->radius( $o, '_radius', '_border_radius' );
		$s->shd( $o, '_shadow', '_box_shadow' );
		if ( $s->border( '_border_hover' ) || $s->shadow( '_box_shadow_hover' ) ) {
			$this->setting( $s->type, '_border_hover' );
		}
		$s->use( '_border_hover_transition', '_border_radius_hover' );
		$this->identity( $s, $o );
		$this->flex_child( $s, $o );
		$this->position( $s, $o );
		$this->motion( $s, $o );
		foreach ( array_keys( $s->all() ) as $k ) {
			if ( preg_match( '/^_transform_|^_mask_|^_css_filter/', (string) $k ) && ! Source::blank( $s->all()[ $k ] ) && '_mask_switch' !== $k && '_transform_rotate_popover' !== $k ) {
				$this->setting( $s->type, preg_replace( '/^(_transform|_mask)_.*/', '$1', (string) $k ) );
			}
			if ( preg_match( '/^_transform_|^_mask_/', (string) $k ) ) {
				$s->use( (string) $k );
			}
		}
	}

	/**
	 * Global widget / Template widget: the referenced document is converted in place.
	 *
	 * @param array<string,mixed> $el Element.
	 * @return array<int, array<string,mixed>>
	 */
	private function reference( array $el, string $wtype, int $depth ): array {
		$settings = (array) ( $el['settings'] ?? array() );
		$id       = 'global' === $wtype ? (int) ( $el['templateID'] ?? 0 ) : (int) ( $settings['template_id'] ?? 0 );
		$resolve  = $this->opt['resolve'];
		$data     = $id && is_callable( $resolve ) && empty( $this->resolving[ $id ] ) ? $resolve( $id ) : null;
		if ( ! is_array( $data ) || ! $data ) {
			$this->unmapped( 'global' === $wtype ? 'global widget' : 'template' );
			return array( $this->placeholder( $el, $wtype ) );
		}
		$this->resolving[ $id ] = true;
		if ( 'global' === $wtype ) {
			$this->note( 'Global widgets were converted as regular widgets (they are no longer linked).' );
			$first = $data[0] ?? null;
			if ( is_array( $first ) ) {
				$first['id'] = $el['id'] ?? ( $first['id'] ?? '' );
			}
			$nodes = is_array( $first ) ? $this->element( $first, $depth, array() ) : array();
		} else {
			$this->note( 'Template widgets were replaced by a copy of the template’s content.' );
			$children = $this->children( $data, $depth + 1 );
			$nodes    = array(
				array(
					'id'       => self::id( (string) ( $el['id'] ?? '' ) ),
					'type'     => 'container',
					'settings' => array( 'content_width' => 'full' ),
					'children' => $children,
				),
			);
			$this->report['elements']++;
		}
		unset( $this->resolving[ $id ] );
		$this->converted( $wtype );
		return $nodes;
	}

	/**
	 * What an unknown widget becomes: an empty HTML widget named after it (the report lists it).
	 *
	 * @param array<string,mixed> $el Element.
	 * @return array<string,mixed>
	 */
	public function placeholder( array $el, string $wtype ): array {
		$this->report['elements']++;
		return array(
			'id'       => self::id( (string) ( $el['id'] ?? '' ) ),
			'type'     => 'html',
			'label'    => sprintf( 'Elementor: %s (not converted)', '' !== $wtype ? $wtype : 'unknown' ),
			'settings' => array( 'html' => '<!-- Elementor widget "' . esc_html( $wtype ) . '" could not be converted. -->' ),
		);
	}

	/**
	 * Builds an Uncoder node: validates settings against the element schema, adds dynamic tags and reports
	 * the Elementor settings nobody read.
	 *
	 * @param array<string,mixed>  $el      Elementor element.
	 * @param array<string,mixed>  $o       Uncoder settings.
	 * @param array<string,string> $dynamic Elementor key => Uncoder key for dynamic tags.
	 * @return array<string,mixed>
	 */
	public function node( array $el, string $type, array $o, Source $s, array $dynamic = array() ): array {
		$node = array(
			'id'       => self::id( (string) ( $el['id'] ?? '' ) ),
			'type'     => $type,
			'settings' => $this->validate( $type, $o ),
		);
		$label = $s->str( '_title' );
		if ( '' !== $label ) {
			$node['label'] = $label;
		}
		$dyn = $this->dynamic( $s, $type, $dynamic );
		if ( $dyn ) {
			$node['dynamic'] = $dyn;
		}
		list( $keys, $bps ) = $s->leftovers();
		foreach ( $keys as $k ) {
			$this->setting( $s->type, $k );
		}
		foreach ( $bps as $bp ) {
			$this->note( sprintf( 'Values for Elementor’s “%s” breakpoint were not converted (Uncoder imports desktop, tablet and mobile).', str_replace( '_', ' ', $bp ) ) );
		}
		$this->converted( $s->type );
		$this->report['elements']++;
		return $node;
	}

	/**
	 * Keeps only settings the Uncoder element accepts (anything else would be a sanitizer error).
	 *
	 * @param array<string,mixed> $o Settings.
	 * @return array<string,mixed>
	 */
	private function validate( string $type, array $o ): array {
		$element = Plugin::instance()->elements()->get( $type );
		if ( ! $element ) {
			return array();
		}
		$controls = $element->get_controls();
		$registry = Plugin::instance()->controls();
		$out      = array();
		foreach ( $o as $k => $v ) {
			$errors = array();
			$clean  = $registry->process_settings( array( $k => $v ), $controls, 'sanitize', $errors, '' );
			if ( $errors || ! array_key_exists( $k, $clean ) ) {
				$this->note( sprintf( 'Internal: %s.%s was dropped (%s).', $type, $k, $errors[0] ?? 'no value' ) );
				continue;
			}
			$out[ $k ] = $v;
		}
		return $out;
	}

	/**
	 * Elementor dynamic tags that have an Uncoder twin.
	 *
	 * @param array<string,string> $map Elementor key => Uncoder key.
	 * @return array<string, array<string,mixed>>
	 */
	private function dynamic( Source $s, string $type, array $map ): array {
		$out = array();
		foreach ( $s->dynamic() as $key => $shortcode ) {
			if ( ! is_string( $shortcode ) || '' === $shortcode || ! preg_match( '/name="([a-z0-9_\-]+)"/i', $shortcode, $m ) ) {
				continue;
			}
			if ( array_key_exists( $key, $map ) && '' === $map[ $key ] ) {
				continue;
			}
			$settings = array();
			if ( preg_match( '/settings="([^"]*)"/', $shortcode, $sm ) ) {
				$decoded  = json_decode( urldecode( $sm[1] ), true );
				$settings = is_array( $decoded ) ? $decoded : array();
			}
			$tag     = self::DYNAMIC_TAGS[ $m[1] ] ?? '';
			$to      = $map[ $key ] ?? '';
			$element = Plugin::instance()->elements()->get( $type );
			$control = $element && '' !== $to ? $element->get_control( $to ) : null;
			$def     = '' !== $tag ? Plugin::instance()->tags()->get( $tag ) : null;
			if ( ! $control || empty( $control['dynamic'] ) || ! $def || ! array_intersect( (array) $def['categories'], \Uncoder\Builder\Dynamic\Tags::categories_for_control( $control ) ) ) {
				$this->setting( $s->type, 'dynamic tag “' . $m[1] . '” on ' . $key );
				continue;
			}
			$options = array();
			switch ( $tag ) {
				case 'post-excerpt':
					if ( is_numeric( $settings['max_length'] ?? null ) ) {
						$options['length'] = (int) $settings['max_length'];
					}
					break;
				case 'post-date':
					$options['type'] = false !== strpos( (string) ( $settings['type'] ?? '' ), 'modified' ) ? 'modified' : 'published';
					$format          = (string) ( $settings['format'] ?? 'default' );
					$options['format'] = 'custom' === $format ? (string) ( $settings['custom_format'] ?? '' ) : ( 'default' === $format ? '' : $format );
					break;
				case 'custom-field':
					$options['key'] = (string) ( $settings['custom_key'] ?? '' ) ?: (string) ( $settings['key'] ?? '' );
					break;
				case 'post-terms':
					$options['taxonomy']  = (string) ( $settings['taxonomy'] ?? 'category' );
					$options['separator'] = (string) ( $settings['separator'] ?? ', ' );
					break;
				case 'request-param':
					$options['name'] = (string) ( $settings['query_var'] ?? '' );
					break;
				case 'shortcode':
					$options['shortcode'] = (string) ( $settings['shortcode'] ?? '' );
					break;
				case 'popup':
					$popup = (int) ( $settings['popup'] ?? 0 );
					if ( ! isset( $this->opt['templates'][ $popup ] ) ) {
						$this->setting( $s->type, 'popup link (convert the popup first)' );
						continue 2;
					}
					$options['popup']  = (string) $this->opt['templates'][ $popup ];
					$options['action'] = in_array( $settings['action'] ?? 'open', array( 'open', 'close', 'toggle' ), true ) ? $settings['action'] : 'open';
					break;
			}
			$clean = Plugin::instance()->tags()->sanitize(
				array_filter(
					array(
						'tag'      => $tag,
						'options'  => $options,
						'before'   => (string) ( $settings['before'] ?? '' ),
						'after'    => (string) ( $settings['after'] ?? '' ),
						'fallback' => (string) ( $settings['fallback'] ?? '' ),
					)
				)
			);
			if ( null === $clean ) {
				$this->setting( $s->type, 'dynamic tag “' . $m[1] . '” on ' . $key );
				continue;
			}
			$out[ $to ] = $clean;
		}
		return $out;
	}

	/* ------------------------------------------------------------------ Values shared by widgets */

	/**
	 * Media value. Same-site ids are kept; for other sites' data only the URL is kept (and reported).
	 *
	 * @param mixed $v Elementor media ({id, url, alt}).
	 * @return array<string,mixed>|null
	 */
	public function media( $v ): ?array {
		if ( is_string( $v ) && '' !== $v ) {
			$v = array( 'url' => $v );
		}
		if ( ! is_array( $v ) ) {
			return null;
		}
		$url = is_string( $v['url'] ?? null ) ? trim( $v['url'] ) : '';
		$id  = absint( $v['id'] ?? 0 );
		if ( '' !== $url && preg_match( '#/elementor/assets/images/placeholder\.png#', $url ) ) {
			$this->note( 'Elementor placeholder images were left empty.' );
			return null;
		}
		if ( '' === $url && ! $id ) {
			return null;
		}
		if ( $this->opt['remote'] ) {
			$local = $id ? wp_get_attachment_url( $id ) : false;
			if ( ! $local || ( '' !== $url && $local !== $url ) ) {
				$id = 0;
				if ( '' !== $url && wp_parse_url( $url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
					$this->remote( $url );
				}
			}
		} elseif ( $id && '' === $url ) {
			$url = (string) wp_get_attachment_url( $id );
		}
		if ( '' === $url && ! $id ) {
			return null;
		}
		$out = array(
			'id'  => $id,
			'url' => $url,
		);
		if ( ! empty( $v['alt'] ) && is_string( $v['alt'] ) ) {
			$out['alt'] = $v['alt'];
		}
		return $out;
	}

	/**
	 * @param mixed $v Elementor link ({url, is_external, nofollow, custom_attributes}).
	 * @return array<string,mixed>|null
	 */
	public function link( $v ): ?array {
		if ( is_string( $v ) ) {
			$v = array( 'url' => $v );
		}
		if ( ! is_array( $v ) ) {
			return null;
		}
		$url = trim( (string) ( $v['url'] ?? '' ) );
		if ( '' === $url ) {
			return null;
		}
		// Popup and lightbox actions: #elementor-action:action=popup:open&settings=…
		if ( 0 === strpos( $url, '#elementor-action' ) ) {
			$decoded = urldecode( $url );
			$popup   = 0;
			if ( preg_match( '/settings=([A-Za-z0-9+\/=]+)/', $decoded, $m ) ) {
				$json  = json_decode( (string) base64_decode( $m[1] ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				$popup = (int) ( $json['id'] ?? 0 );
			}
			if ( $popup && isset( $this->opt['templates'][ $popup ] ) && false !== strpos( $decoded, 'popup:open' ) ) {
				$url = '#uncoder-popup:open:' . (int) $this->opt['templates'][ $popup ];
			} else {
				$this->note( 'Links that open Elementor popups or lightboxes were replaced with “#”.' );
				$url = '#';
			}
		}
		$out = array( 'url' => $url );
		if ( ! empty( $v['is_external'] ) ) {
			$out['external'] = true;
		}
		if ( ! empty( $v['nofollow'] ) ) {
			$out['nofollow'] = true;
		}
		if ( ! empty( $v['custom_attributes'] ) && is_string( $v['custom_attributes'] ) ) {
			$out['attributes'] = implode( "\n", array_filter( array_map( 'trim', explode( ',', $v['custom_attributes'] ) ) ) );
		}
		return $out;
	}

	/**
	 * Elementor icon ({value: "fas fa-star", library: "fa-solid"} or an SVG) → Uncoder icon.
	 *
	 * @param mixed $v Value.
	 * @return array<string,mixed>|null
	 */
	public function icon( $v ): ?array {
		if ( is_string( $v ) ) {
			$v = array(
				'value'   => $v,
				'library' => '',
			);
		}
		if ( ! is_array( $v ) ) {
			return null;
		}
		$library = (string) ( $v['library'] ?? '' );
		$value   = $v['value'] ?? '';
		if ( 'svg' === $library ) {
			$id = is_array( $value ) ? absint( $value['id'] ?? 0 ) : 0;
			if ( $id && ! $this->opt['remote'] && wp_get_attachment_url( $id ) ) {
				return array(
					'library' => 'svg',
					'id'      => $id,
				);
			}
			$this->note( 'Uploaded SVG icons from another site were left out.' );
			return null;
		}
		if ( ! is_string( $value ) || '' === trim( $value ) ) {
			return null;
		}
		$value = strtolower( trim( $value ) );
		if ( 0 === strpos( $value, 'eicon-' ) ) {
			$name = self::EICONS[ substr( $value, 6 ) ] ?? '';
			if ( '' === $name ) {
				$this->setting( 'icon', $value );
				return null;
			}
			return array(
				'library' => 'lucide',
				'value'   => $name,
			);
		}
		$parsed = Icons::parse( $value );
		if ( ! $parsed ) {
			$lib    = in_array( $library, array( 'fa-solid', 'fa-regular', 'fa-brands' ), true ) ? $library : 'fa-solid';
			$parsed = array( $lib, (string) preg_replace( '/^fa-/', '', $value ) );
		}
		list( $lib, $name ) = $parsed;
		if ( ! Icons::exists( $name, $lib ) ) {
			foreach ( array( 'fa-solid', 'fa-regular', 'fa-brands', 'lucide' ) as $try ) {
				if ( Icons::exists( $name, $try ) ) {
					return array(
						'library' => $try,
						'value'   => $name,
					);
				}
			}
			$this->setting( 'icon', $value );
			return null;
		}
		return array(
			'library' => $lib,
			'value'   => $name,
		);
	}

	/** Elementor's own icon font (eicons) → Lucide. */
	private const EICONS = array(
		'star'          => 'star',
		'star-o'        => 'star',
		'check'         => 'check',
		'close'         => 'x',
		'plus'          => 'plus',
		'minus'         => 'minus',
		'chevron-right' => 'chevron-right',
		'chevron-left'  => 'chevron-left',
		'chevron-down'  => 'chevron-down',
		'chevron-up'    => 'chevron-up',
		'caret-down'    => 'chevron-down',
		'caret-up'      => 'chevron-up',
		'caret-right'   => 'chevron-right',
		'caret-left'    => 'chevron-left',
		'arrow-right'   => 'arrow-right',
		'arrow-left'    => 'arrow-left',
		'menu-bar'      => 'menu',
		'search'        => 'search',
		'heart'         => 'heart',
		'play'          => 'play',
		'info-circle'   => 'info',
		'user-circle-o' => 'circle-user',
		'cart'          => 'shopping-cart',
		'envelope'      => 'mail',
		'phone'         => 'phone',
		'map-pin'       => 'map-pin',
		'clock'         => 'clock',
	);

	/* ------------------------------------------------------------------ Globals */

	public static function global_id( string $ref, string $kind ): string {
		return preg_match( '#^globals/' . $kind . '\?id=([A-Za-z0-9_\-]+)$#', $ref, $m ) ? $m[1] : '';
	}

	/** Uncoder Design System color id for an Elementor global color id. */
	public static function color_id( string $id ): string {
		return in_array( $id, self::SYSTEM, true ) ? $id : sanitize_key( 'e-' . $id );
	}

	/** Uncoder text style id for an Elementor global typography id. */
	public static function typo_id( string $id ): string {
		return in_array( $id, self::SYSTEM, true ) ? $id : sanitize_key( 'e-' . $id );
	}

	public function global_color( string $ref ): string {
		$id = self::global_id( $ref, 'colors' );
		if ( '' === $id ) {
			return '';
		}
		$known = ! $this->opt['kit'] || isset( $this->colors[ $id ] );
		if ( 'vars' === $this->opt['globals'] && $known ) {
			return 'var(--uncoder-c-' . self::color_id( $id ) . ')';
		}
		if ( isset( $this->colors[ $id ] ) ) {
			return $this->colors[ $id ];
		}
		if ( in_array( $id, self::SYSTEM, true ) ) {
			return 'var(--uncoder-c-' . $id . ')';
		}
		$this->note( 'Some global colors of the Elementor kit are not available here; those elements keep their own color.' );
		return '';
	}

	/** @return array<string,mixed>|null */
	public function global_typography( string $ref ): ?array {
		$id = self::global_id( $ref, 'typography' );
		if ( '' === $id ) {
			return null;
		}
		$known = ! $this->opt['kit'] || isset( $this->typos[ $id ] );
		if ( 'vars' === $this->opt['globals'] && $known ) {
			$value = $this->typos[ $id ] ?? null;
			// A text style emits every property; Elementor fonts that leave size or weight unset rely on the
			// element's own style for them, so those are written out (family linked to the Design System font).
			if ( null === $value || self::complete_typo( $value ) ) {
				return array( 'preset' => self::typo_id( $id ) );
			}
			$font = self::font_var( (string) ( $value['family'] ?? '' ), $this->typos );
			if ( '' !== $font ) {
				$value['family'] = $font;
			}
			return $value;
		}
		if ( ! empty( $this->typos[ $id ] ) ) {
			return $this->typos[ $id ];
		}
		$this->note( 'Some global fonts of the Elementor kit are not available here; those elements use the Uncoder text styles.' );
		return null;
	}

	/**
	 * Whether a typography value can stand as a text style (family, size and weight set).
	 *
	 * @param array<string,mixed> $value Uncoder typography value.
	 */
	public static function complete_typo( array $value ): bool {
		return ! empty( $value['family'] ) && ! empty( $value['weight'] ) && isset( $value['size']['size'] ) && '' !== $value['size']['size'];
	}

	/**
	 * The Design System font variable for a family the kit import maps to the heading / body font.
	 *
	 * @param array<string, array<string,mixed>> $typos Elementor global fonts (Uncoder values) by id.
	 */
	public static function font_var( string $family, array $typos ): string {
		if ( '' === $family ) {
			return '';
		}
		if ( $family === ( $typos['primary']['family'] ?? '' ) ) {
			return 'var(--uncoder-f-heading)';
		}
		if ( $family === ( $typos['text']['family'] ?? '' ) ) {
			return 'var(--uncoder-f-body)';
		}
		return '';
	}

	/* ------------------------------------------------------------------ Report */

	public function converted( string $type ): void {
		$this->report['converted'][ $type ] = ( $this->report['converted'][ $type ] ?? 0 ) + 1;
	}

	public function unmapped( string $type ): void {
		$this->report['unmapped'][ $type ] = ( $this->report['unmapped'][ $type ] ?? 0 ) + 1;
	}

	public function setting( string $where, string $key ): void {
		$k                              = $where . ': ' . $key;
		$this->report['settings'][ $k ] = ( $this->report['settings'][ $k ] ?? 0 ) + 1;
	}

	public function note( string $message ): void {
		$this->report['notes'][ $message ] = ( $this->report['notes'][ $message ] ?? 0 ) + 1;
	}

	public function remote( string $url ): void {
		if ( ! in_array( $url, $this->report['remote'], true ) ) {
			$this->report['remote'][] = $url;
		}
	}

	/** @return mixed */
	public function option( string $key ) {
		return $this->opt[ $key ] ?? null;
	}

	/** Uncoder id for an Elementor id ("e" + id keeps it stable across imports). */
	public static function id( string $id ): string {
		$id = 'e' . strtolower( (string) preg_replace( '/[^A-Za-z0-9]/', '', $id ) );
		return Utils::is_valid_id( $id ) ? $id : Utils::generate_id();
	}

	/** A fresh id for elements the import adds (tab panels, wrappers). */
	public static function new_id(): string {
		return Utils::generate_id();
	}
}
