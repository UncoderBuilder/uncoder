<?php
/**
 * Built-in dynamic tags.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Dynamic;

use Uncoder\Builder\Core\Render_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Post, archive, site, author, user, media and action tags.
 */
final class Core_Tags {

	public static function register( Tags $tags ): void {
		$post = static function ( Render_Context $ctx ): ?\WP_Post {
			$id = $ctx->post_id ? $ctx->post_id : (int) get_the_ID();
			$p  = $id ? get_post( $id ) : null;
			return $p instanceof \WP_Post ? $p : null;
		};

		$tags->register(
			'post-title',
			array(
				'title'    => __( 'Post title', 'uncoder' ),
				'group'    => 'post',
				'callback' => static fn( $o, $ctx ) => ( $p = $post( $ctx ) ) ? get_the_title( $p ) : '',
			)
		);
		$tags->register(
			'post-excerpt',
			array(
				'title'    => __( 'Post excerpt', 'uncoder' ),
				'group'    => 'post',
				'controls' => array( 'length' => array( 'type' => 'number', 'label' => __( 'Words', 'uncoder' ), 'min' => 0, 'max' => 200 ) ),
				'callback' => static function ( $o, $ctx ) use ( $post ) {
					$p = $post( $ctx );
					if ( ! $p || post_password_required( $p ) ) {
						return '';
					}
					$text = has_excerpt( $p ) ? $p->post_excerpt : wp_strip_all_tags( strip_shortcodes( $p->post_content ) );
					$len  = isset( $o['length'] ) && '' !== $o['length'] ? (int) $o['length'] : 30;
					return $len ? wp_trim_words( $text, $len ) : $text;
				},
			)
		);
		$tags->register(
			'post-date',
			array(
				'title'      => __( 'Post date', 'uncoder' ),
				'group'      => 'post',
				'categories' => array( 'text', 'date' ),
				'controls'   => array(
					'type'   => array( 'type' => 'select', 'label' => __( 'Date', 'uncoder' ), 'options' => array( 'published' => __( 'Published', 'uncoder' ), 'modified' => __( 'Modified', 'uncoder' ) ) ),
					'format' => array( 'type' => 'text', 'label' => __( 'Format', 'uncoder' ), 'description' => __( 'PHP date format such as "F j, Y" or "Y"; "human" for relative time; empty for the site default.', 'uncoder' ) ),
				),
				'callback'   => static function ( $o, $ctx ) use ( $post ) {
					$p = $post( $ctx );
					if ( ! $p ) {
						return '';
					}
					$modified = 'modified' === ( $o['type'] ?? '' );
					$format   = (string) ( $o['format'] ?? '' );
					if ( 'human' === $format ) {
						/* translators: %s: human time difference. */
						return sprintf( __( '%s ago', 'uncoder' ), human_time_diff( (int) get_post_time( 'U', true, $p ), time() ) );
					}
					return $modified ? get_the_modified_date( $format, $p ) : get_the_date( $format, $p );
				},
			)
		);
		$tags->register(
			'post-url',
			array(
				'title'      => __( 'Post URL', 'uncoder' ),
				'group'      => 'post',
				'categories' => array( 'url' ),
				'callback'   => static fn( $o, $ctx ) => ( $p = $post( $ctx ) ) ? get_permalink( $p ) : '',
			)
		);
		$tags->register(
			'post-id',
			array(
				'title'      => __( 'Post ID', 'uncoder' ),
				'group'      => 'post',
				'categories' => array( 'text', 'number' ),
				'callback'   => static fn( $o, $ctx ) => ( $p = $post( $ctx ) ) ? (string) $p->ID : '',
			)
		);
		$tags->register(
			'featured-image',
			array(
				'title'      => __( 'Featured image', 'uncoder' ),
				'group'      => 'post',
				'categories' => array( 'image', 'url' ),
				'callback'   => static function ( $o, $ctx ) use ( $post ) {
					$p  = $post( $ctx );
					$id = $p ? (int) get_post_thumbnail_id( $p ) : 0;
					if ( ! $id ) {
						return array( 'id' => 0, 'url' => '' );
					}
					return array(
						'id'  => $id,
						'url' => (string) wp_get_attachment_image_url( $id, 'full' ),
						'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
					);
				},
			)
		);
		$tags->register(
			'post-terms',
			array(
				'title'    => __( 'Post terms', 'uncoder' ),
				'group'    => 'post',
				'controls' => array(
					'taxonomy'  => array( 'type' => 'select', 'label' => __( 'Taxonomy', 'uncoder' ), 'options_dynamic' => true, 'options' => array() ),
					'separator' => array( 'type' => 'text', 'label' => __( 'Separator', 'uncoder' ) ),
				),
				'callback' => static function ( $o, $ctx ) use ( $post ) {
					$p = $post( $ctx );
					if ( ! $p ) {
						return '';
					}
					$tax = (string) ( $o['taxonomy'] ?? 'category' );
					$tax = '' !== $tax ? $tax : 'category';
					// Private taxonomies (menus, plugin-internal ones) are never shown to visitors.
					if ( ! taxonomy_exists( $tax ) || ! is_taxonomy_viewable( $tax ) ) {
						return '';
					}
					$terms = get_the_terms( $p, $tax );
					if ( ! is_array( $terms ) ) {
						return '';
					}
					return implode( (string) ( $o['separator'] ?? ', ' ), wp_list_pluck( $terms, 'name' ) );
				},
			)
		);
		$tags->register(
			'custom-field',
			array(
				'title'      => __( 'Custom field', 'uncoder' ),
				'group'      => 'post',
				'categories' => array( 'text', 'url', 'image', 'number', 'color' ),
				'controls'   => array( 'key' => array( 'type' => 'text', 'label' => __( 'Meta key', 'uncoder' ) ) ),
				'callback'   => static function ( $o, $ctx ) use ( $post ) {
					$p   = $post( $ctx );
					$key = sanitize_key( (string) ( $o['key'] ?? '' ) );
					// Protected meta (leading underscore) is never exposed, nor fields of a password-protected post.
					if ( ! $p || '' === $key || is_protected_meta( $key, 'post' ) || post_password_required( $p ) ) {
						return '';
					}
					if ( function_exists( 'get_field' ) ) {
						$acf = get_field( $key, $p->ID );
						if ( is_array( $acf ) && isset( $acf['url'] ) ) {
							return array( 'id' => (int) ( $acf['ID'] ?? $acf['id'] ?? 0 ), 'url' => (string) $acf['url'] );
						}
						if ( is_scalar( $acf ) && '' !== (string) $acf ) {
							return (string) $acf;
						}
					}
					$value = get_post_meta( $p->ID, $key, true );
					return is_scalar( $value ) ? (string) $value : '';
				},
			)
		);
		$tags->register(
			'archive-title',
			array(
				'title'    => __( 'Archive title', 'uncoder' ),
				'group'    => 'archive',
				'callback' => static function () {
					if ( is_search() ) {
						/* translators: %s: search query. */
						return sprintf( __( 'Search results for “%s”', 'uncoder' ), get_search_query() );
					}
					if ( is_home() && ! is_front_page() ) {
						return get_the_title( (int) get_option( 'page_for_posts' ) );
					}
					return wp_strip_all_tags( get_the_archive_title() );
				},
			)
		);
		$tags->register(
			'archive-description',
			array(
				'title'    => __( 'Archive description', 'uncoder' ),
				'group'    => 'archive',
				'callback' => static fn() => wp_strip_all_tags( (string) get_the_archive_description() ),
			)
		);
		// Terms: the item of a container term loop, else the term archive being shown.
		$term = static function ( Render_Context $ctx ): ?\WP_Term {
			if ( $ctx->term instanceof \WP_Term ) {
				return $ctx->term;
			}
			$q = get_queried_object();
			return $q instanceof \WP_Term ? $q : null;
		};
		$tags->register(
			'term-name',
			array(
				'title'    => __( 'Term name', 'uncoder' ),
				'group'    => 'term',
				'callback' => static fn( $o, $ctx ) => ( $t = $term( $ctx ) ) ? $t->name : '',
			)
		);
		$tags->register(
			'term-url',
			array(
				'title'      => __( 'Term link', 'uncoder' ),
				'group'      => 'term',
				'categories' => array( 'url' ),
				'callback'   => static function ( $o, $ctx ) use ( $term ) {
					$t    = $term( $ctx );
					$link = $t ? get_term_link( $t ) : '';
					return is_string( $link ) ? $link : '';
				},
			)
		);
		$tags->register(
			'term-description',
			array(
				'title'    => __( 'Term description', 'uncoder' ),
				'group'    => 'term',
				'callback' => static fn( $o, $ctx ) => ( $t = $term( $ctx ) ) ? wp_strip_all_tags( $t->description ) : '',
			)
		);
		$tags->register(
			'term-count',
			array(
				'title'      => __( 'Term post count', 'uncoder' ),
				'group'      => 'term',
				'categories' => array( 'text', 'number' ),
				'callback'   => static fn( $o, $ctx ) => ( $t = $term( $ctx ) ) ? (string) $t->count : '',
			)
		);
		$tags->register(
			'site-title',
			array(
				'title'    => __( 'Site title', 'uncoder' ),
				'group'    => 'site',
				'callback' => static fn() => get_bloginfo( 'name' ),
			)
		);
		$tags->register(
			'site-tagline',
			array(
				'title'    => __( 'Site tagline', 'uncoder' ),
				'group'    => 'site',
				'callback' => static fn() => get_bloginfo( 'description' ),
			)
		);
		$tags->register(
			'site-url',
			array(
				'title'      => __( 'Site URL', 'uncoder' ),
				'group'      => 'site',
				'categories' => array( 'url' ),
				'callback'   => static fn() => home_url( '/' ),
			)
		);
		$tags->register(
			'site-logo',
			array(
				'title'      => __( 'Site logo', 'uncoder' ),
				'group'      => 'site',
				'categories' => array( 'image' ),
				'callback'   => static function () {
					$id = (int) get_theme_mod( 'custom_logo' );
					return $id ? array( 'id' => $id, 'url' => (string) wp_get_attachment_image_url( $id, 'full' ) ) : array( 'id' => 0, 'url' => '' );
				},
			)
		);
		$tags->register(
			'author-name',
			array(
				'title'    => __( 'Author name', 'uncoder' ),
				'group'    => 'author',
				'callback' => static fn( $o, $ctx ) => ( $p = $post( $ctx ) ) ? get_the_author_meta( 'display_name', (int) $p->post_author ) : '',
			)
		);
		$tags->register(
			'author-bio',
			array(
				'title'    => __( 'Author bio', 'uncoder' ),
				'group'    => 'author',
				'callback' => static fn( $o, $ctx ) => ( $p = $post( $ctx ) ) ? get_the_author_meta( 'description', (int) $p->post_author ) : '',
			)
		);
		$tags->register(
			'author-url',
			array(
				'title'      => __( 'Author archive URL', 'uncoder' ),
				'group'      => 'author',
				'categories' => array( 'url' ),
				'callback'   => static fn( $o, $ctx ) => ( $p = $post( $ctx ) ) ? get_author_posts_url( (int) $p->post_author ) : '',
			)
		);
		$tags->register(
			'author-avatar',
			array(
				'title'      => __( 'Author avatar', 'uncoder' ),
				'group'      => 'author',
				'categories' => array( 'image' ),
				'callback'   => static fn( $o, $ctx ) => array( 'id' => 0, 'url' => ( $p = $post( $ctx ) ) ? (string) get_avatar_url( (int) $p->post_author, array( 'size' => 256 ) ) : '' ),
			)
		);
		$tags->register(
			'user-name',
			array(
				'title'    => __( 'Current user name', 'uncoder' ),
				'group'    => 'user',
				'callback' => static fn() => is_user_logged_in() ? wp_get_current_user()->display_name : '',
			)
		);
		$tags->register(
			'current-date',
			array(
				'title'      => __( 'Current date', 'uncoder' ),
				'group'      => 'other',
				'categories' => array( 'text', 'date' ),
				'controls'   => array( 'format' => array( 'type' => 'text', 'label' => __( 'Format', 'uncoder' ), 'description' => __( 'PHP date format such as "F j, Y" or "Y"; "human" for relative time; empty for the site default.', 'uncoder' ) ) ),
				'callback'   => static fn( $o ) => wp_date( '' !== ( $o['format'] ?? '' ) && 'human' !== $o['format'] ? $o['format'] : (string) get_option( 'date_format' ) ),
			)
		);
		$tags->register(
			'request-param',
			array(
				'title'    => __( 'Request parameter', 'uncoder' ),
				'group'    => 'other',
				'controls' => array( 'name' => array( 'type' => 'text', 'label' => __( 'Query parameter', 'uncoder' ) ) ),
				'callback' => static function ( $o ) {
					// Only for real page views: never bake request data into saved content or API output.
					if ( \Uncoder\Builder\Core\Document::$static_render || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
						return '';
					}
					$name = sanitize_key( (string) ( $o['name'] ?? '' ) );
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of a public query arg, escaped by the widget.
					return '' !== $name && isset( $_GET[ $name ] ) ? sanitize_text_field( wp_unslash( (string) $_GET[ $name ] ) ) : '';
				},
			)
		);
		$tags->register(
			'shortcode',
			array(
				'title'      => __( 'Shortcode', 'uncoder' ),
				'group'      => 'other',
				'categories' => array( 'text', 'html', 'url' ),
				'capability' => 'unfiltered_html',
				'controls'   => array( 'shortcode' => array( 'type' => 'text', 'label' => __( 'Shortcode', 'uncoder' ) ) ),
				'callback'   => static fn( $o ) => do_shortcode( (string) ( $o['shortcode'] ?? '' ) ),
			)
		);
		$tags->register(
			'popup',
			array(
				'title'      => __( 'Open popup', 'uncoder' ),
				'group'      => 'actions',
				'categories' => array( 'url' ),
				'controls'   => array(
					'popup'  => array( 'type' => 'select', 'label' => __( 'Popup', 'uncoder' ), 'options_dynamic' => true, 'options' => array(), 'source' => 'popups' ),
					'action' => array( 'type' => 'select', 'label' => __( 'Action', 'uncoder' ), 'options' => array( 'open' => __( 'Open', 'uncoder' ), 'close' => __( 'Close', 'uncoder' ), 'toggle' => __( 'Toggle', 'uncoder' ) ) ),
				),
				'callback'   => static fn( $o ) => '#uncoder-popup:' . ( $o['action'] ?? 'open' ) . ':' . absint( $o['popup'] ?? 0 ),
			)
		);
		$tags->register(
			'contact-url',
			array(
				'title'      => __( 'Contact link', 'uncoder' ),
				'group'      => 'actions',
				'categories' => array( 'url' ),
				'controls'   => array(
					'type'  => array( 'type' => 'select', 'label' => __( 'Type', 'uncoder' ), 'options' => array( 'email' => 'Email', 'tel' => 'Phone', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp' ) ),
					'value' => array( 'type' => 'text', 'label' => __( 'Email / number', 'uncoder' ) ),
				),
				'callback'   => static function ( $o ) {
					$v = trim( (string) ( $o['value'] ?? '' ) );
					switch ( $o['type'] ?? 'email' ) {
						case 'tel':
							return 'tel:' . preg_replace( '/[^0-9+]/', '', $v );
						case 'sms':
							return 'sms:' . preg_replace( '/[^0-9+]/', '', $v );
						case 'whatsapp':
							return 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $v );
						default:
							return is_email( $v ) ? 'mailto:' . $v : '';
					}
				},
			)
		);
	}
}
