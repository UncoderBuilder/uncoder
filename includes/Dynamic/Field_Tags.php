<?php
/**
 * Dynamic tags for custom field plugins: Advanced Custom Fields and Meta Box.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Dynamic;

use Uncoder\Builder\Core\Render_Context;

defined( 'ABSPATH' ) || exit;

/**
 * Registered only when the plugin is active. Each tag picks a field from a list and formats its value
 * by field type: images and files become media / URLs, choices their labels, relations their titles,
 * true/false Yes/No. Fields can be read from the current post, the current term, the post author or
 * an ACF options page.
 */
final class Field_Tags {

	public const CATEGORIES = array( 'text', 'url', 'image', 'number', 'color', 'date' );

	public static function register( Tags $tags ): void {
		$sources = array(
			'post'   => __( 'Current post', 'uncoder' ),
			'term'   => __( 'Current category / term', 'uncoder' ),
			'author' => __( 'Post author', 'uncoder' ),
		);
		if ( function_exists( 'acf_get_field_groups' ) ) {
			$tags->register(
				'acf-field',
				array(
					'title'      => __( 'ACF field', 'uncoder' ),
					'group'      => 'post',
					'categories' => self::CATEGORIES,
					'controls'   => array(
						'field'  => array( 'type' => 'select', 'label' => __( 'Field', 'uncoder' ), 'options' => self::acf_fields() ),
						'source' => array( 'type' => 'select', 'label' => __( 'From', 'uncoder' ), 'options' => $sources + array( 'option' => __( 'Options page', 'uncoder' ) ) ),
					),
					'callback'   => static fn( $o, $ctx ) => self::acf_value( (string) ( $o['field'] ?? '' ), (string) ( $o['source'] ?? 'post' ), $ctx ),
				)
			);
		}
		if ( function_exists( 'rwmb_get_value' ) && function_exists( 'rwmb_get_registry' ) ) {
			$tags->register(
				'metabox-field',
				array(
					'title'      => __( 'Meta Box field', 'uncoder' ),
					'group'      => 'post',
					'categories' => self::CATEGORIES,
					'controls'   => array(
						'field'  => array( 'type' => 'select', 'label' => __( 'Field', 'uncoder' ), 'options' => self::metabox_fields() ),
						'source' => array( 'type' => 'select', 'label' => __( 'From', 'uncoder' ), 'options' => $sources ),
					),
					'callback'   => static fn( $o, $ctx ) => self::metabox_value( (string) ( $o['field'] ?? '' ), (string) ( $o['source'] ?? 'post' ), $ctx ),
				)
			);
		}
	}

	/**
	 * Where to read a field from, in the plugins' own notation.
	 *
	 * @return array{0:string, 1:int|string}|null [ object type, id ] ("option" for ACF options pages).
	 */
	private static function target( string $source, Render_Context $ctx ): ?array {
		switch ( $source ) {
			case 'option':
				return array( 'option', 'option' );
			case 'term':
				$term = get_queried_object();
				return $term instanceof \WP_Term ? array( 'term', (int) $term->term_id ) : null;
			case 'author':
				$post = get_post( $ctx->post_id ? $ctx->post_id : (int) get_the_ID() );
				return $post ? array( 'user', (int) $post->post_author ) : null;
			default:
				$id = $ctx->post_id ? $ctx->post_id : (int) get_the_ID();
				// The fields of a password-protected post stay hidden like its content.
				return $id && ! post_password_required( $id ) ? array( 'post', $id ) : null;
		}
	}

	/* ------------------------------------------------------------------ ACF */

	/**
	 * @return array<string,string> field key => "Group › Label"
	 */
	private static function acf_fields(): array {
		$out = array( '' => __( '— Choose —', 'uncoder' ) );
		foreach ( (array) acf_get_field_groups() as $group ) {
			foreach ( (array) acf_get_fields( $group ) as $field ) {
				if ( in_array( $field['type'] ?? '', array( 'tab', 'message', 'accordion', 'group', 'repeater', 'flexible_content', 'clone' ), true ) ) {
					continue;
				}
				$out[ (string) $field['key'] ] = $group['title'] . ' › ' . $field['label'];
			}
		}
		return $out;
	}

	/**
	 * @return mixed
	 */
	private static function acf_value( string $key, string $source, Render_Context $ctx ) {
		if ( '' === $key || ! function_exists( 'get_field_object' ) ) {
			return '';
		}
		$target = self::target( $source, $ctx );
		if ( ! $target ) {
			return '';
		}
		$ref   = 'option' === $target[0] ? 'option' : ( 'post' === $target[0] ? $target[1] : $target[0] . '_' . $target[1] );
		$field = get_field_object( $key, $ref );
		return is_array( $field ) ? self::format( (string) ( $field['type'] ?? 'text' ), $field['value'] ?? null, $field ) : '';
	}

	/* ------------------------------------------------------------------ Meta Box */

	/**
	 * @return array<string,string> field id => "Box › Name"
	 */
	private static function metabox_fields(): array {
		$out = array( '' => __( '— Choose —', 'uncoder' ) );
		$boxes = rwmb_get_registry( 'meta_box' )->all();
		foreach ( (array) $boxes as $box ) {
			$title = is_object( $box ) && isset( $box->meta_box['title'] ) ? (string) $box->meta_box['title'] : '';
			foreach ( (array) ( is_object( $box ) ? ( $box->meta_box['fields'] ?? array() ) : array() ) as $field ) {
				if ( ! empty( $field['id'] ) && ! in_array( $field['type'] ?? '', array( 'heading', 'divider', 'custom_html', 'group', 'tab' ), true ) ) {
					$out[ (string) $field['id'] ] = ( '' !== $title ? $title . ' › ' : '' ) . ( $field['name'] ?? $field['id'] );
				}
			}
		}
		return $out;
	}

	/**
	 * @return mixed
	 */
	private static function metabox_value( string $id, string $source, Render_Context $ctx ) {
		$target = '' !== $id ? self::target( $source, $ctx ) : null;
		if ( ! $target || 'option' === $target[0] ) {
			return '';
		}
		$args  = 'post' === $target[0] ? array() : array( 'object_type' => $target[0] );
		$field = function_exists( 'rwmb_get_field_settings' ) ? rwmb_get_field_settings( $id, $args, $target[1] ) : array();
		$value = rwmb_get_value( $id, $args, $target[1] );
		$type  = (string) ( $field['type'] ?? 'text' );
		// Meta Box names a few types differently.
		$map = array(
			'single_image'  => 'image',
			'image_advanced' => 'gallery',
			'image_upload'  => 'gallery',
			'file_advanced' => 'file',
			'checkbox'      => 'true_false',
			'checkbox_list' => 'checkbox',
			'post'          => 'relationship',
			'color'         => 'color_picker',
			'date'          => 'date_picker',
			'datetime'      => 'date_time_picker',
		);
		return self::format( $map[ $type ] ?? $type, $value, is_array( $field ) ? $field : array() );
	}

	/* ------------------------------------------------------------------ Formatting */

	/**
	 * Turns a field value into what a dynamic tag returns: a string, or {id,url} for images.
	 *
	 * @param mixed               $value Field value.
	 * @param array<string,mixed> $field Field definition (choices, labels).
	 * @return string|array{id:int,url:string}
	 */
	public static function format( string $type, $value, array $field = array() ) {
		switch ( $type ) {
			case 'password':
				return ''; // Never printed on the site.
			case 'image':
			case 'gallery':
				if ( 'gallery' === $type && is_array( $value ) && isset( $value[0] ) ) {
					$value = reset( $value ); // A single image slot shows the first one.
				}
				return self::image( $value );
			case 'file':
				if ( is_array( $value ) ) {
					$value = isset( $value[0] ) ? reset( $value ) : $value;
					return esc_url_raw( (string) ( $value['url'] ?? '' ) );
				}
				return is_numeric( $value ) ? (string) wp_get_attachment_url( (int) $value ) : esc_url_raw( (string) $value );
			case 'link':
				return is_array( $value ) ? esc_url_raw( (string) ( $value['url'] ?? '' ) ) : esc_url_raw( (string) $value );
			case 'page_link':
			case 'url':
			case 'oembed':
				return is_array( $value ) ? esc_url_raw( (string) reset( $value ) ) : esc_url_raw( (string) $value );
			case 'true_false':
				return $value ? __( 'Yes', 'uncoder' ) : __( 'No', 'uncoder' );
			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				$choices = (array) ( $field['choices'] ?? $field['options'] ?? array() );
				$labels  = array();
				foreach ( (array) $value as $k => $v ) {
					if ( is_array( $v ) && isset( $v['label'] ) ) {
						$labels[] = (string) $v['label'];
					} elseif ( is_scalar( $v ) ) {
						$labels[] = (string) ( $choices[ $v ] ?? $v );
					}
				}
				return implode( ', ', $labels );
			case 'relationship':
			case 'post_object':
				$titles = array();
				foreach ( is_array( $value ) ? $value : array( $value ) as $item ) {
					$post = $item instanceof \WP_Post ? $item : ( is_numeric( $item ) ? get_post( (int) $item ) : null );
					// Related drafts / private posts are only named for users who may read them.
					if ( $post && ( is_post_publicly_viewable( $post ) || current_user_can( 'read_post', $post->ID ) ) ) {
						$titles[] = get_the_title( $post );
					}
				}
				return implode( ', ', $titles );
			case 'taxonomy':
				$names = array();
				foreach ( is_array( $value ) ? $value : array( $value ) as $item ) {
					$term = $item instanceof \WP_Term ? $item : ( is_numeric( $item ) ? get_term( (int) $item ) : null );
					if ( $term instanceof \WP_Term && is_taxonomy_viewable( $term->taxonomy ) ) {
						$names[] = $term->name;
					}
				}
				return implode( ', ', $names );
			case 'user':
				$user = $value instanceof \WP_User ? $value : ( is_array( $value ) && isset( $value['ID'] ) ? get_userdata( (int) $value['ID'] ) : ( is_numeric( $value ) ? get_userdata( (int) $value ) : null ) );
				return $user ? $user->display_name : '';
			default:
				if ( is_array( $value ) ) {
					$flat = array_filter( array_map( static fn( $v ) => is_scalar( $v ) ? (string) $v : '', $value ), 'strlen' );
					return implode( ', ', $flat );
				}
				return is_scalar( $value ) ? (string) $value : '';
		}
	}

	/**
	 * @param mixed $value Image field value (array, id or URL).
	 * @return array{id:int,url:string}|string
	 */
	private static function image( $value ) {
		if ( is_array( $value ) ) {
			$id  = (int) ( $value['ID'] ?? $value['id'] ?? 0 );
			$url = (string) ( $value['url'] ?? $value['full_url'] ?? ( $id ? wp_get_attachment_url( $id ) : '' ) );
			return '' !== $url ? array( 'id' => $id, 'url' => $url ) : '';
		}
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			$url = (string) wp_get_attachment_url( (int) $value );
			return '' !== $url ? array( 'id' => (int) $value, 'url' => $url ) : '';
		}
		return is_string( $value ) && '' !== $value ? array( 'id' => 0, 'url' => esc_url_raw( $value ) ) : '';
	}
}
