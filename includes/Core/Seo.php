<?php
/**
 * SEO title / meta description: writes to the active SEO plugin, or prints them itself.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Core;

defined( 'ABSPATH' ) || exit;

/**
 * When Yoast, Rank Math, AIOSEO, SEOPress or The SEO Framework is active, values are stored in that
 * plugin's fields so it stays the single source of truth. Otherwise Uncoder stores them and prints
 * the description / Open Graph tags on its own.
 */
final class Seo {

	public const META_TITLE = '_uncoder_wb_seo_title';
	public const META_DESC  = '_uncoder_wb_seo_description';

	/**
	 * Post meta keys per plugin: [title, description].
	 */
	private const PLUGIN_KEYS = array(
		'yoast'    => array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc' ),
		'rankmath' => array( 'rank_math_title', 'rank_math_description' ),
		'aioseo'   => array( '_aioseo_title', '_aioseo_description' ),
		'seopress' => array( '_seopress_titles_title', '_seopress_titles_desc' ),
		'tsf'      => array( '_genesis_title', '_genesis_description' ),
	);

	public function register(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		if ( ! is_admin() ) {
			add_action( 'wp', array( $this, 'maybe_output' ) );
		}
	}

	public function register_meta(): void {
		foreach ( array( self::META_TITLE, self::META_DESC ) as $key ) {
			register_meta(
				'post',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', $post_id ),
				)
			);
		}
	}

	/**
	 * The active SEO plugin, or '' when none.
	 */
	public static function plugin(): string {
		if ( defined( 'WPSEO_VERSION' ) ) {
			return 'yoast';
		}
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			return 'rankmath';
		}
		if ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			return 'aioseo';
		}
		if ( defined( 'SEOPRESS_VERSION' ) ) {
			return 'seopress';
		}
		if ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) ) {
			return 'tsf';
		}
		return '';
	}

	/**
	 * @return array{title:string, description:string, plugin:string}
	 */
	public static function get( int $post_id ): array {
		$plugin = self::plugin();
		$keys   = self::PLUGIN_KEYS[ $plugin ] ?? array( self::META_TITLE, self::META_DESC );
		$title  = (string) get_post_meta( $post_id, $keys[0], true );
		$desc   = (string) get_post_meta( $post_id, $keys[1], true );
		if ( 'aioseo' === $plugin && function_exists( 'aioseo' ) && class_exists( '\AIOSEO\Plugin\Common\Models\Post' ) ) {
			$model = \AIOSEO\Plugin\Common\Models\Post::getPost( $post_id );
			if ( $model && ! empty( $model->exists() ) ) {
				$title = (string) $model->title;
				$desc  = (string) $model->description;
			}
		}
		return array(
			'title'       => $title,
			'description' => $desc,
			'plugin'      => '' !== $plugin ? $plugin : 'uncoder',
		);
	}

	public static function has_description( int $post_id ): bool {
		if ( '' !== self::get( $post_id )['description'] ) {
			return true;
		}
		return '' !== (string) get_post_meta( $post_id, self::META_DESC, true );
	}

	/**
	 * Stores title and/or description (null leaves a value unchanged, '' clears it).
	 *
	 * @return array{title:string, description:string, plugin:string}
	 */
	public static function set( int $post_id, ?string $title, ?string $description ): array {
		$plugin = self::plugin();
		$keys   = self::PLUGIN_KEYS[ $plugin ] ?? array( self::META_TITLE, self::META_DESC );
		$values = array(
			0 => null === $title ? null : sanitize_text_field( $title ),
			1 => null === $description ? null : sanitize_textarea_field( $description ),
		);
		foreach ( $values as $i => $value ) {
			if ( null === $value ) {
				continue;
			}
			if ( '' === $value ) {
				delete_post_meta( $post_id, $keys[ $i ] );
				delete_post_meta( $post_id, 0 === $i ? self::META_TITLE : self::META_DESC );
			} else {
				update_post_meta( $post_id, $keys[ $i ], wp_slash( $value ) );
				// Keep our own copy too, so values survive switching SEO plugins.
				update_post_meta( $post_id, 0 === $i ? self::META_TITLE : self::META_DESC, wp_slash( $value ) );
			}
		}
		if ( 'aioseo' === $plugin && class_exists( '\AIOSEO\Plugin\Common\Models\Post' ) ) {
			$model = \AIOSEO\Plugin\Common\Models\Post::getPost( $post_id );
			if ( $model ) {
				$model->post_id = $post_id;
				if ( null !== $values[0] ) {
					$model->title = $values[0];
				}
				if ( null !== $values[1] ) {
					$model->description = $values[1];
				}
				$model->save();
			}
		}
		return self::get( $post_id );
	}

	/* ---------------------------------------------------------------- Output (no SEO plugin) */

	public function maybe_output(): void {
		if ( '' !== self::plugin() ) {
			return;
		}
		// A page, a post, or the page set as the blog ("Posts page"), whose own title and description apply.
		$post_id = is_singular() ? (int) get_queried_object_id() : ( is_home() ? (int) get_option( 'page_for_posts' ) : 0 );
		if ( ! $post_id ) {
			return;
		}
		$title   = (string) get_post_meta( $post_id, self::META_TITLE, true );
		$desc    = (string) get_post_meta( $post_id, self::META_DESC, true );
		if ( '' !== $title ) {
			add_filter( 'pre_get_document_title', static fn() => esc_html( $title ), 20 );
		}
		if ( '' === $desc && '' === $title ) {
			return;
		}
		add_action(
			'wp_head',
			static function () use ( $post_id, $title, $desc ) {
				$og_title = '' !== $title ? $title : get_the_title( $post_id );
				if ( '' !== $desc ) {
					echo '<meta name="description" content="' . esc_attr( $desc ) . '" />' . "\n";
				}
				echo '<meta property="og:type" content="website" />' . "\n";
				echo '<meta property="og:title" content="' . esc_attr( $og_title ) . '" />' . "\n";
				if ( '' !== $desc ) {
					echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
				}
				echo '<meta property="og:url" content="' . esc_url( (string) get_permalink( $post_id ) ) . '" />' . "\n";
				$image = get_the_post_thumbnail_url( $post_id, 'large' );
				if ( $image ) {
					echo '<meta property="og:image" content="' . esc_url( $image ) . '" />' . "\n";
				}
			},
			2
		);
	}
}
