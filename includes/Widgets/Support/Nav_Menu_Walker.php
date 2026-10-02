<?php
/**
 * Walker for the Nav Menu widget.
 *
 * Not a widget: the widget registry only scans includes/Widgets/*.php.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Widgets\Support;

use Uncoder\Builder\Core\Icons;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Menus\Menu_Item_Extras;

defined( 'ABSPATH' ) || exit;

/**
 * Disclosure-pattern markup: every parent item gets a real <button aria-expanded> next to its link
 * that controls the submenu (or the mega menu content supplied through
 * the `uncoder_wb/nav_menu/item_content` filter).
 */
class Nav_Menu_Walker extends \Walker_Nav_Menu {

	/** Prefix for submenu ids, unique per widget instance and menu copy. */
	private string $prefix;

	/** Submenu indicator markup (escaped SVG). */
	private string $indicator;

	/** @var array<string,mixed> Widget settings passed to the item content filter. */
	private array $settings;

	/** Mobile (accordion) copy of the menu. */
	private bool $mobile;

	/** @var array<int,string> Mega menu content by menu item id. */
	private array $extra = array();

	/** Id of the submenu opened by the next start_lvl() call. */
	private string $pending_sub = '';

	/** The current page has an item of its own on the top level (see walk()). */
	private bool $top_current = false;

	/**
	 * @param string              $prefix    Id prefix.
	 * @param string              $indicator Indicator SVG markup.
	 * @param array<string,mixed> $settings  Widget settings.
	 * @param bool                $mobile    Mobile copy.
	 */
	public function __construct( string $prefix, string $indicator, array $settings, bool $mobile ) {
		$this->prefix    = $prefix;
		$this->indicator = $indicator;
		$this->settings  = $settings;
		$this->mobile    = $mobile;
	}

	/**
	 * Notes whether the current page has its own top-level item. Then that item alone is the active one on the top
	 * level: a dropdown that also lists the page ("Product › Build with AI" next to "Build with AI") is not marked
	 * as its ancestor, so the menu does not show two active items and the sliding highlight rests on the page.
	 *
	 * @param array $elements  Menu items.
	 * @param int   $max_depth Max depth.
	 * @param mixed ...$args   Args.
	 * @return string
	 */
	public function walk( $elements, $max_depth, ...$args ) {
		$this->top_current = false;
		foreach ( (array) $elements as $item ) {
			if ( is_object( $item ) && empty( $item->menu_item_parent ) && self::is_current( $item ) ) {
				$this->top_current = true;
				break;
			}
		}
		return parent::walk( $elements, $max_depth, ...$args );
	}

	/**
	 * The item links to the page being viewed. WordPress ignores the #fragment when it compares a link with the
	 * page, so every "/#section" link of a one-page menu would count as the current page; section links are
	 * marked by scrollspy.ts instead.
	 *
	 * @param object $item Menu item.
	 */
	private static function is_current( $item ): bool {
		$fragment = wp_parse_url( (string) ( $item->url ?? '' ), PHP_URL_FRAGMENT );
		return ! empty( $item->current ) && ( ! is_string( $fragment ) || '' === $fragment );
	}

	/**
	 * Resolves mega content before children are walked: an item with extra content does not
	 * print its regular submenu (the content replaces it).
	 *
	 * @param object $element           Menu item.
	 * @param array  $children_elements Children by parent id.
	 * @param int    $max_depth         Max depth.
	 * @param int    $depth             Depth.
	 * @param array  $args              Args.
	 * @param string $output            Output.
	 */
	public function display_element( $element, &$children_elements, $max_depth, $depth, $args, &$output ) {
		if ( ! $element ) {
			return;
		}
		$id    = (int) $element->{$this->db_fields['id']};
		$extra = (string) apply_filters( 'uncoder_wb/nav_menu/item_content', '', $element, $depth, $this->settings );
		if ( '' !== trim( $extra ) ) {
			$this->extra[ $id ] = $extra;
			$this->drop_branch( $id, $children_elements );
		}
		parent::display_element( $element, $children_elements, $max_depth, $depth, $args, $output );
	}

	/**
	 * Removes a subtree from the pending children so it is not printed as orphans.
	 *
	 * @param array<int|string, array<int,object>> $children_elements Children by parent id.
	 */
	private function drop_branch( int $id, array &$children_elements ): void {
		if ( empty( $children_elements[ $id ] ) ) {
			return;
		}
		foreach ( $children_elements[ $id ] as $child ) {
			$this->drop_branch( (int) $child->{$this->db_fields['id']}, $children_elements );
		}
		unset( $children_elements[ $id ] );
	}

	/**
	 * @param string    $output Output.
	 * @param int       $depth  Depth.
	 * @param \stdClass $args   Args.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$attrs = array(
			'class' => 'uncoder-menu__sub uncoder-menu__sub--depth-' . ( (int) $depth + 1 ),
			'id'    => '' !== $this->pending_sub ? $this->pending_sub : null,
		);
		$this->pending_sub = '';
		$output           .= '<ul' . Utils::attrs( $attrs ) . '>';
	}

	/**
	 * @param string    $output Output.
	 * @param int       $depth  Depth.
	 * @param \stdClass $args   Args.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= '</ul>';
	}

	/**
	 * @param string    $output            Output.
	 * @param \WP_Post  $data_object       Menu item.
	 * @param int       $depth             Depth.
	 * @param \stdClass $args              Args.
	 * @param int       $current_object_id Current object id.
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
		$item  = $data_object;
		$args  = is_object( $args ) ? $args : (object) array();
		$id    = (int) $item->ID;
		$extra = $this->extra[ $id ] ?? '';
		// Children are only printed when the depth limit allows them.
		$max   = isset( $args->depth ) ? (int) $args->depth : 0;
		$sub   = ( ! empty( $this->has_children ) && ( 0 === $max || $max > $depth + 1 ) ) || '' !== $extra;

		$wp_classes = empty( $item->classes ) ? array() : (array) $item->classes;
		$wp_classes[] = 'menu-item-' . $id;
		/** This filter is documented in wp-includes/class-walker-nav-menu.php */
		$wp_classes = (array) apply_filters( 'nav_menu_css_class', array_filter( $wp_classes ), $item, $args, $depth ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.

		$classes = array( 'uncoder-menu__item' );
		if ( $sub ) {
			$classes[] = 'uncoder-menu__item--has-children';
		}
		if ( '' !== $extra ) {
			$classes[] = 'uncoder-menu__item--mega';
		}
		$current = self::is_current( $item );
		if ( $current ) {
			$classes[] = 'uncoder-menu__item--current';
		}
		$ancestor = ( ! empty( $item->current_item_ancestor ) || ! empty( $item->current_item_parent ) ) && ! ( 0 === $depth && $this->top_current );
		if ( $ancestor ) {
			$classes[] = 'uncoder-menu__item--ancestor';
		}
		// A mobile branch that contains the current page starts expanded.
		$open = $this->mobile && $sub && $ancestor && '' === $extra;
		if ( $open ) {
			$classes[] = 'is-open';
		}
		foreach ( $wp_classes as $class ) {
			$class = sanitize_html_class( (string) $class );
			// Classes WordPress adds to every item repeat the uncoder-menu__item… ones; custom classes stay.
			if ( '' !== $class && ! preg_match( '/^(menu-item(-type-.+|-object-.+|-\d+|-has-children|-home)?|current[-_](menu|page)[-_](item|parent|ancestor)|page[-_]item(-\d+)?)$/', $class ) ) {
				$classes[] = $class;
			}
		}

		$output .= '<li class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '">';

		$atts = array(
			'class'        => 'uncoder-menu__link',
			'title'        => ! empty( $item->attr_title ) ? $item->attr_title : '',
			'target'       => ! empty( $item->target ) ? $item->target : '',
			'rel'          => trim( ( ! empty( $item->xfn ) ? $item->xfn : '' ) . ( '_blank' === ( $item->target ?? '' ) && false === strpos( (string) ( $item->xfn ?? '' ), 'noopener' ) ? ' noopener' : '' ) ),
			'href'         => ! empty( $item->url ) ? $item->url : '',
			'aria-current' => $current ? 'page' : '',
		);
		/** This filter is documented in wp-includes/class-walker-nav-menu.php */
		$atts = (array) apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		foreach ( $atts as $key => $value ) {
			if ( '' === $value || null === $value ) {
				$atts[ $key ] = null;
			}
		}

		/** This filter is documented in wp-includes/post-template.php */
		$title = apply_filters( 'the_title', (string) $item->title, $id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		/** This filter is documented in wp-includes/class-walker-nav-menu.php */
		$title = (string) apply_filters( 'nav_menu_item_title', $title, $item, $args, $depth ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		$title = wp_kses( $title, Utils::kses_inline() );

		// Icon (Appearance → Menus) before the label; the description under it, in dropdowns and the mobile menu.
		$icon = '';
		if ( $this->shows( 'show_icons' ) ) {
			$name = Menu_Item_Extras::icon( $id );
			$svg  = '' !== $name ? Icons::render( $name ) : '';
			$icon = '' !== $svg ? '<span class="uncoder-menu__icon" aria-hidden="true">' . $svg . '</span>' : '';
		}
		$desc = $this->shows( 'show_descriptions' ) && ( $depth > 0 || $this->mobile ) ? Menu_Item_Extras::description( $item ) : '';
		$text = '<span class="uncoder-menu__text">' . $title . '</span>';
		if ( '' !== $icon ) {
			$atts['class'] .= ' uncoder-menu__link--icon';
		}
		if ( '' !== $desc ) {
			$atts['class'] .= ' uncoder-menu__link--rich';
			$text           = '<span class="uncoder-menu__label">' . $text . '<span class="uncoder-menu__desc">' . esc_html( $desc ) . '</span></span>';
		}

		$output .= $args->before ?? '';
		$output .= '<a' . Utils::attrs( $atts ) . '>';
		// $icon is SVG from the bundled icon data; $text is escaped above.
		$output .= ( $args->link_before ?? '' ) . $icon . $text . ( $args->link_after ?? '' );
		$output .= '</a>';
		$output .= $args->after ?? '';

		if ( $sub ) {
			$sub_id = $this->prefix . '-sub-' . $id;
			$label  = wp_strip_all_tags( $title );
			$output .= '<button' . Utils::attrs(
				array(
					'type'          => 'button',
					'class'         => 'uncoder-menu__toggle',
					'aria-expanded' => $open ? 'true' : 'false',
					'aria-controls' => $sub_id,
				)
			) . '>';
			/* translators: %s: menu item label. */
			$output .= '<span class="uncoder-sr-only">' . esc_html( sprintf( __( 'Show submenu for %s', 'uncoder' ), '' !== $label ? $label : __( 'this item', 'uncoder' ) ) ) . '</span>';
			$output .= $this->indicator . '</button>';

			if ( '' !== $extra ) {
				// Extension output (mega menu template), produced by code, not user input.
				$output .= '<div class="uncoder-menu__mega" id="' . esc_attr( $sub_id ) . '">' . $extra . '</div>';
			} else {
				$this->pending_sub = $sub_id;
			}
		}
	}

	/**
	 * Whether a widget switch is on (on unless turned off).
	 */
	private function shows( string $key ): bool {
		return ! array_key_exists( $key, $this->settings ) || ! empty( $this->settings[ $key ] );
	}

	/**
	 * @param string    $output      Output.
	 * @param \WP_Post  $data_object Menu item.
	 * @param int       $depth       Depth.
	 * @param \stdClass $args        Args.
	 */
	public function end_el( &$output, $data_object, $depth = 0, $args = null ) {
		$output .= '</li>';
	}
}
