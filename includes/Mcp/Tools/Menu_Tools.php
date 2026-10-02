<?php
/**
 * Navigation menu tools.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Mcp\Tools;

use Uncoder\Builder\Core\Post_Types;
use Uncoder\Builder\Core\Utils;
use Uncoder\Builder\Mcp\Call;
use Uncoder\Builder\Mcp\Registry;
use Uncoder\Builder\Menus\Mega_Menu;
use Uncoder\Builder\Menus\Menu_Item_Extras;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * list_menus, create_menu, set_menu_items, assign_menu_location, set_mega_menu.
 */
final class Menu_Tools {

	private const MAX_ITEMS = 150;

	public function register( Registry $r ): void {
		$items_schema = array(
			'type'        => 'array',
			'description' => 'Menu items, nested with "children": [{"title":"Home","url":"/"},{"title":"Services","page_id":12,"children":[{"title":"Design","page_id":14}]},{"title":"Contact","url":"#contact"}]. Link with page_id, post_id, term_id (+ taxonomy) or url. Optional: target "_blank", classes, description (one short line, shown under the label in dropdowns and the mobile menu), icon (a Lucide name such as "users", or "library:name" for another library or a custom icon set, shown before the label).',
			'items'       => array( 'type' => 'object' ),
		);

		$r->add(
			array(
				'name'        => 'list_menus',
				'title'       => 'List menus',
				'description' => 'Navigation menus with their ids, locations and (with include_items or menu_id) their item tree including item ids for set_mega_menu. Use a menu id in the nav-menu widget "menu" setting.',
				'scope'       => 'read',
				'annotations' => array( 'readOnlyHint' => true, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'menu_id'       => array( 'type' => 'integer', 'description' => 'Return only this menu with its items.' ),
						'include_items' => array( 'type' => 'boolean' ),
					),
				),
				'callback'    => array( $this, 'list_menus' ),
			)
		);

		$r->add(
			array(
				'name'        => 'create_menu',
				'title'       => 'Create menu',
				'description' => 'Creates a navigation menu with nested items and optionally assigns it to a theme location. Returns the menu id for the nav-menu widget.',
				'scope'       => 'site',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'name'     => array( 'type' => 'string' ),
						'items'    => $items_schema,
						'location' => array( 'type' => 'string', 'description' => 'Theme location slug from list_menus (optional).' ),
					),
					'required'   => array( 'name' ),
				),
				'callback'    => array( $this, 'create_menu' ),
			)
		);

		$r->add(
			array(
				'name'        => 'set_menu_items',
				'title'       => 'Set menu items',
				'description' => 'Replaces all items of a menu with a new nested list (same format as create_menu). The previous items are returned so the change can be reverted.',
				'scope'       => 'site',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => true, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'menu_id' => array( 'type' => 'integer' ),
						'items'   => $items_schema,
					),
					'required'   => array( 'menu_id', 'items' ),
				),
				'callback'    => array( $this, 'set_items' ),
			)
		);

		$r->add(
			array(
				'name'        => 'assign_menu_location',
				'title'       => 'Assign menu location',
				'description' => 'Assigns a menu to a theme menu location (menu_id 0 unassigns). Only matters for theme-rendered menus; Uncoder headers use the nav-menu widget with a menu id.',
				'scope'       => 'site',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'menu_id'  => array( 'type' => 'integer' ),
						'location' => array( 'type' => 'string' ),
					),
					'required'   => array( 'menu_id', 'location' ),
				),
				'callback'    => array( $this, 'assign_location' ),
			)
		);

		$r->add(
			array(
				'name'        => 'set_mega_menu',
				'title'       => 'Set mega menu',
				'description' => 'Shows a mega-menu template (create_template type "mega-menu") as the dropdown of a top-level menu item instead of its sub-items. template_id 0 removes it. Get item ids from list_menus {menu_id}.',
				'scope'       => 'design',
				'annotations' => array( 'readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false ),
				'input'       => array(
					'type'       => 'object',
					'properties' => array(
						'item_id'     => array( 'type' => 'integer', 'description' => 'Menu item id.' ),
						'template_id' => array( 'type' => 'integer' ),
						'width'       => array( 'type' => 'string', 'enum' => array( 'container', 'full', 'auto' ), 'description' => 'Panel width (default container).' ),
					),
					'required'   => array( 'item_id', 'template_id' ),
				),
				'callback'    => array( $this, 'set_mega' ),
			)
		);
	}

	/**
	 * Menus with locations (used by get_site_overview too).
	 *
	 * @return array<string,mixed>
	 */
	public function menus_summary(): array {
		$locations = get_registered_nav_menus();
		$assigned  = get_nav_menu_locations();
		$menus     = array();
		foreach ( wp_get_nav_menus() as $menu ) {
			$menus[] = array(
				'id'        => (int) $menu->term_id,
				'name'      => $menu->name,
				'items'     => (int) $menu->count,
				'locations' => array_keys( array_filter( $assigned, static fn( $id ) => (int) $id === (int) $menu->term_id ) ),
			);
		}
		return array(
			'menus'     => $menus,
			'locations' => $locations,
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function list_menus( array $a ) {
		if ( ! empty( $a['menu_id'] ) ) {
			$menu = wp_get_nav_menu_object( absint( $a['menu_id'] ) );
			if ( ! $menu ) {
				return new WP_Error( 'not_found', 'No menu with id ' . absint( $a['menu_id'] ) . '.' );
			}
			return array(
				'menu'  => array(
					'id'   => (int) $menu->term_id,
					'name' => $menu->name,
				),
				'items' => $this->item_tree( (int) $menu->term_id ),
			);
		}
		$out = $this->menus_summary();
		if ( ! empty( $a['include_items'] ) ) {
			foreach ( $out['menus'] as $i => $menu ) {
				$out['menus'][ $i ]['tree'] = $this->item_tree( $menu['id'] );
			}
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_menu( array $a, Call $call ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'forbidden', 'Managing menus requires the edit_theme_options capability.' );
		}
		$name = sanitize_text_field( (string) $a['name'] );
		if ( '' === $name ) {
			return new WP_Error( 'invalid', 'The menu needs a name.' );
		}
		if ( wp_get_nav_menu_object( $name ) ) {
			$existing = wp_get_nav_menu_object( $name );
			return new WP_Error( 'exists', sprintf( 'A menu named "%s" already exists (id %d). Use set_menu_items to change its items, or choose another name.', $name, $existing->term_id ) );
		}
		$errors = array();
		$items  = $this->validate_items( (array) ( $a['items'] ?? array() ), $errors );
		if ( $errors && ! $items && ! empty( $a['items'] ) ) {
			return new WP_Error( 'invalid', 'No valid menu items.', array( 'details' => $errors ) );
		}
		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			return $menu_id;
		}
		$count = $this->insert_items( (int) $menu_id, $items, 0, $errors );
		if ( ! empty( $a['location'] ) ) {
			$result = $this->assign( (int) $menu_id, (string) $a['location'] );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}
		foreach ( $errors as $e ) {
			$call->warn( $e );
		}
		$call->object_id = (int) $menu_id;
		$call->summary   = sprintf( 'Created menu "%s" (%d items)', $name, $count );
		return array(
			'id'      => (int) $menu_id,
			'menu_id' => (int) $menu_id,
			'name'    => $name,
			'items'   => $this->item_tree( (int) $menu_id ),
			'next'    => sprintf( 'Use it in a header: nav-menu widget with settings {"menu": %d}.', $menu_id ),
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function set_items( array $a, Call $call ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'forbidden', 'Managing menus requires the edit_theme_options capability.' );
		}
		$menu = wp_get_nav_menu_object( absint( $a['menu_id'] ) );
		if ( ! $menu ) {
			return new WP_Error( 'not_found', 'No menu with id ' . absint( $a['menu_id'] ) . '. Call list_menus.' );
		}
		$errors = array();
		$items  = $this->validate_items( (array) $a['items'], $errors );
		if ( ! $items ) {
			return new WP_Error( 'invalid', 'No valid menu items (nothing was changed).', array( 'details' => $errors ) );
		}
		$previous = $this->item_tree( (int) $menu->term_id );
		foreach ( (array) wp_get_nav_menu_items( $menu->term_id, array( 'post_status' => 'any' ) ) as $item ) {
			wp_delete_post( $item->ID, true );
		}
		$count = $this->insert_items( (int) $menu->term_id, $items, 0, $errors );
		foreach ( $errors as $e ) {
			$call->warn( $e );
		}
		$call->object_id = (int) $menu->term_id;
		$call->summary   = sprintf( 'Replaced items of menu "%s" (%d items)', $menu->name, $count );
		return array(
			'menu_id'  => (int) $menu->term_id,
			'items'    => $this->item_tree( (int) $menu->term_id ),
			'previous' => $previous,
			'note'     => 'Mega menus attached to removed items were dropped; set them again with set_mega_menu.',
		);
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function assign_location( array $a, Call $call ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'forbidden', 'Managing menus requires the edit_theme_options capability.' );
		}
		$result = $this->assign( absint( $a['menu_id'] ), (string) $a['location'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$call->summary = sprintf( 'Menu %d → location %s', absint( $a['menu_id'] ), $a['location'] );
		return array( 'locations' => get_nav_menu_locations() );
	}

	/**
	 * @return true|WP_Error
	 */
	private function assign( int $menu_id, string $location ) {
		$location   = sanitize_key( $location );
		$registered = get_registered_nav_menus();
		if ( ! isset( $registered[ $location ] ) ) {
			return new WP_Error( 'invalid', sprintf( 'Unknown menu location "%s". This theme has: %s.', $location, $registered ? implode( ', ', array_keys( $registered ) ) : 'no menu locations (block themes use the Navigation block; Uncoder headers use the nav-menu widget)' ) );
		}
		if ( $menu_id && ! wp_get_nav_menu_object( $menu_id ) ) {
			return new WP_Error( 'not_found', 'No menu with id ' . $menu_id . '.' );
		}
		$locations              = get_nav_menu_locations();
		$locations[ $location ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
		return true;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function set_mega( array $a, Call $call ) {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			return new WP_Error( 'forbidden', 'Managing menus requires the edit_theme_options capability.' );
		}
		$item_id = absint( $a['item_id'] );
		$item    = get_post( $item_id );
		if ( ! $item || 'nav_menu_item' !== $item->post_type ) {
			return new WP_Error( 'not_found', 'No menu item with id ' . $item_id . '. Call list_menus {menu_id} for item ids.' );
		}
		$template_id = absint( $a['template_id'] );
		if ( $template_id ) {
			if ( Post_Types::TEMPLATE !== get_post_type( $template_id ) || 'mega-menu' !== get_post_meta( $template_id, Utils::META_TYPE, true ) ) {
				return new WP_Error( 'invalid', 'template_id must be a template of type "mega-menu" (create_template {"type":"mega-menu"}).' );
			}
			if ( (int) get_post_meta( $item_id, '_menu_item_menu_item_parent', true ) ) {
				$call->warn( 'This is a sub-item: mega menus display on top-level items only.' );
			}
		}
		Mega_Menu::set( $item_id, $template_id, (string) ( $a['width'] ?? 'container' ) );
		$call->object_id = $item_id;
		$call->summary   = $template_id ? sprintf( 'Mega menu #%d on item "%s"', $template_id, $item->post_title ) : 'Removed mega menu from item #' . $item_id;
		return array(
			'item_id'     => $item_id,
			'template_id' => $template_id,
			'width'       => Mega_Menu::get( $item_id )['width'] ?? 'container',
		);
	}

	/* ---------------------------------------------------------------- Items */

	/**
	 * Validates a nested item list. Returns clean items; problems go to $errors.
	 *
	 * @param array<int, mixed> $items  Raw items.
	 * @param string[]          $errors Errors.
	 * @return array<int, array<string,mixed>>
	 */
	private function validate_items( array $items, array &$errors, string $path = 'items', int &$count = 0 ): array {
		$out = array();
		foreach ( array_values( $items ) as $i => $raw ) {
			$p = $path . '[' . $i . ']';
			if ( ! is_array( $raw ) ) {
				$errors[] = $p . ': must be an object.';
				continue;
			}
			if ( ++$count > self::MAX_ITEMS ) {
				$errors[] = 'Too many items (max ' . self::MAX_ITEMS . ').';
				break;
			}
			$item = array(
				'title'       => sanitize_text_field( (string) ( $raw['title'] ?? $raw['label'] ?? '' ) ),
				'target'      => '_blank' === ( $raw['target'] ?? '' ) || ! empty( $raw['new_tab'] ) ? '_blank' : '',
				'classes'     => implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) ( $raw['classes'] ?? '' ) ) ) ),
				'description' => sanitize_text_field( (string) ( $raw['description'] ?? '' ) ),
				'icon'        => Menu_Item_Extras::clean( (string) ( $raw['icon'] ?? '' ) ),
			);
			if ( '' !== (string) ( $raw['icon'] ?? '' ) && '' === $item['icon'] ) {
				$errors[] = sprintf( '%s.icon: unknown icon "%s" (use a Lucide name such as "users" or "library:name").', $p, (string) $raw['icon'] );
			}
			$object_id = absint( $raw['page_id'] ?? $raw['post_id'] ?? 0 );
			if ( $object_id ) {
				$post = get_post( $object_id );
				if ( ! $post || in_array( $post->post_status, array( 'trash', 'auto-draft' ), true ) ) {
					$errors[] = sprintf( '%s: no post/page with id %d.', $p, $object_id );
					continue;
				}
				$item['type']      = 'post_type';
				$item['object']    = $post->post_type;
				$item['object_id'] = $object_id;
				if ( '' === $item['title'] ) {
					$item['title'] = get_the_title( $post );
				}
				if ( 'publish' !== $post->post_status ) {
					$errors[] = sprintf( '%s: "%s" is %s — the menu item stays hidden until it is published.', $p, get_the_title( $post ), $post->post_status );
				}
			} elseif ( ! empty( $raw['term_id'] ) ) {
				$tax  = sanitize_key( (string) ( $raw['taxonomy'] ?? 'category' ) );
				$term = get_term( absint( $raw['term_id'] ), $tax );
				if ( ! $term || is_wp_error( $term ) ) {
					$errors[] = sprintf( '%s: no %s term with id %d.', $p, $tax, absint( $raw['term_id'] ) );
					continue;
				}
				$item['type']      = 'taxonomy';
				$item['object']    = $tax;
				$item['object_id'] = (int) $term->term_id;
				if ( '' === $item['title'] ) {
					$item['title'] = $term->name;
				}
			} else {
				$url = trim( (string) ( $raw['url'] ?? '' ) );
				if ( '' === $url ) {
					$url = '#';
				}
				if ( '/' === $url[0] && 0 !== strpos( $url, '//' ) ) {
					$url = home_url( $url );
				}
				$clean = '#' === $url[0] ? '#' . sanitize_title_with_dashes( substr( $url, 1 ) ) : esc_url_raw( $url, array( 'http', 'https', 'mailto', 'tel' ) );
				if ( '' === $clean ) {
					$errors[] = sprintf( '%s: invalid url "%s".', $p, $url );
					continue;
				}
				if ( '#' === $url || '#' === $clean ) {
					$clean = '#';
				}
				$item['type'] = 'custom';
				$item['url']  = $clean;
			}
			if ( '' === $item['title'] ) {
				$errors[] = $p . ': needs a title.';
				continue;
			}
			if ( ! empty( $raw['children'] ) && is_array( $raw['children'] ) ) {
				$item['children'] = $this->validate_items( $raw['children'], $errors, $p . '.children', $count );
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * @param array<int, array<string,mixed>> $items  Clean items.
	 * @param string[]                        $errors Errors.
	 */
	private function insert_items( int $menu_id, array $items, int $parent, array &$errors ): int {
		$count = 0;
		foreach ( $items as $pos => $item ) {
			$data = array(
				'menu-item-title'       => $item['title'],
				'menu-item-status'      => 'publish',
				'menu-item-parent-id'   => $parent,
				'menu-item-position'    => $pos + 1,
				'menu-item-target'      => $item['target'],
				'menu-item-classes'     => $item['classes'],
				'menu-item-description' => $item['description'],
				'menu-item-type'        => $item['type'],
			);
			if ( 'custom' === $item['type'] ) {
				$data['menu-item-url'] = $item['url'];
			} else {
				$data['menu-item-object']    = $item['object'];
				$data['menu-item-object-id'] = $item['object_id'];
			}
			$id = wp_update_nav_menu_item( $menu_id, 0, $data );
			if ( is_wp_error( $id ) ) {
				$errors[] = sprintf( '"%s": %s', $item['title'], $id->get_error_message() );
				continue;
			}
			++$count;
			if ( '' !== ( $item['icon'] ?? '' ) ) {
				Menu_Item_Extras::set_icon( (int) $id, $item['icon'] );
			}
			if ( ! empty( $item['children'] ) ) {
				$count += $this->insert_items( $menu_id, $item['children'], (int) $id, $errors );
			}
		}
		return $count;
	}

	/**
	 * @return array<int, array<string,mixed>>
	 */
	private function item_tree( int $menu_id ): array {
		$items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
		if ( ! $items ) {
			return array();
		}
		$by_parent = array();
		foreach ( $items as $item ) {
			$by_parent[ (int) $item->menu_item_parent ][] = $item;
		}
		$build = static function ( int $parent ) use ( &$build, $by_parent ): array {
			$out = array();
			foreach ( $by_parent[ $parent ] ?? array() as $item ) {
				$node = array(
					'item_id' => (int) $item->ID,
					'title'   => $item->title,
					'url'     => $item->url,
				);
				if ( 'custom' !== $item->type ) {
					$node['object']    = $item->object;
					$node['object_id'] = (int) $item->object_id;
				}
				$mega = Mega_Menu::get( (int) $item->ID );
				if ( $mega ) {
					$node['mega_menu'] = $mega;
				}
				$icon = Menu_Item_Extras::icon( (int) $item->ID );
				if ( '' !== $icon ) {
					$node['icon'] = $icon;
				}
				$description = Menu_Item_Extras::description( $item );
				if ( '' !== $description ) {
					$node['description'] = $description;
				}
				$children = $build( (int) $item->ID );
				if ( $children ) {
					$node['children'] = $children;
				}
				$out[] = $node;
			}
			return $out;
		};
		return $build( 0 );
	}
}
