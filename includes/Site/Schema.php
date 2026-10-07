<?php
/**
 * Structured data (JSON-LD): the business on the home page, articles on posts.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Site;

use Uncoder\Builder\Core\Seo;
use Uncoder\Builder\Rest\Settings_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * uncoder_wb_settings['business'] describes the organization or local business: printed as
 * schema.org JSON-LD (with a WebSite node) on the front page. Single posts get a BlogPosting.
 * With an SEO plugin active, which prints its own Organization / Article graph, only a local
 * business type is added (SEO plugins' free versions do not describe those). FAQ and breadcrumb
 * data come from the Accordion ("FAQ schema") and Breadcrumbs widgets.
 */
final class Schema {

	/** Business types offered in the admin (schema.org LocalBusiness subtypes and Organization). */
	public const TYPES = array(
		'Organization'                => 'Organization (company, brand, non-profit)',
		'LocalBusiness'               => 'Local business (general)',
		'ProfessionalService'         => 'Professional service',
		'HomeAndConstructionBusiness' => 'Home & construction (contractor, electrician, plumber…)',
		'AutomotiveBusiness'          => 'Automotive',
		'FinancialService'            => 'Financial service',
		'LegalService'                => 'Legal service',
		'MedicalBusiness'             => 'Medical practice',
		'Dentist'                     => 'Dentist',
		'HealthAndBeautyBusiness'     => 'Health & beauty',
		'Restaurant'                  => 'Restaurant',
		'CafeOrCoffeeShop'            => 'Café',
		'Store'                       => 'Shop / store',
		'RealEstateAgent'             => 'Real estate agent',
		'LodgingBusiness'             => 'Hotel / lodging',
		'EducationalOrganization'     => 'School / education',
		'SportsActivityLocation'      => 'Gym / sports',
	);

	public function register(): void {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function get(): array {
		$stored = get_option( Settings_Controller::OPTION, array() );
		$raw    = is_array( $stored ) && is_array( $stored['business'] ?? null ) ? $stored['business'] : array();
		return self::sanitize( $raw );
	}

	/**
	 * @param array<string,mixed> $raw Raw values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $raw ): array {
		$text  = static fn( string $key, int $max = 200 ): string => mb_substr( sanitize_text_field( (string) ( $raw[ $key ] ?? '' ) ), 0, $max );
		$lines = static function ( string $key, callable $clean ) use ( $raw ): array {
			$value = $raw[ $key ] ?? array();
			$list  = is_array( $value ) ? $value : preg_split( '/\r\n|\r|\n/', (string) $value );
			return array_values( array_filter( array_map( $clean, array_slice( (array) $list, 0, 20 ) ) ) );
		};
		$type = (string) ( $raw['type'] ?? 'Organization' );
		return array(
			'enabled'     => ! empty( $raw['enabled'] ),
			'type'        => isset( self::TYPES[ $type ] ) ? $type : 'Organization',
			'name'        => $text( 'name' ),
			'description' => mb_substr( sanitize_textarea_field( (string) ( $raw['description'] ?? '' ) ), 0, 500 ),
			'phone'       => $text( 'phone', 40 ),
			'email'       => sanitize_email( (string) ( $raw['email'] ?? '' ) ),
			'street'      => $text( 'street' ),
			'city'        => $text( 'city', 100 ),
			'region'      => $text( 'region', 100 ),
			'postal'      => $text( 'postal', 20 ),
			'country'     => strtoupper( mb_substr( sanitize_text_field( (string) ( $raw['country'] ?? '' ) ), 0, 2 ) ),
			'area'        => $text( 'area' ),
			'price_range' => $text( 'price_range', 10 ),
			// "Mo-Fr 09:00-17:00" per line (schema.org openingHours format).
			'hours'       => $lines( 'hours', static fn( $l ) => preg_match( '/^[A-Za-z,\- ]+\s+\d{1,2}:\d{2}-\d{1,2}:\d{2}$/', trim( (string) $l ) ) ? trim( (string) $l ) : '' ),
			'same_as'     => $lines( 'same_as', static fn( $l ) => esc_url_raw( trim( (string) $l ), array( 'https', 'http' ) ) ),
		);
	}

	/**
	 * The JSON-LD graph for the front page, or null when off.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function business_graph(): ?array {
		$b = self::get();
		if ( ! $b['enabled'] ) {
			return null;
		}
		$home = home_url( '/' );
		$org  = array(
			'@type' => $b['type'],
			'@id'   => $home . '#organization',
			'name'  => '' !== $b['name'] ? $b['name'] : get_bloginfo( 'name' ),
			'url'   => $home,
		);
		if ( '' !== $b['description'] ) {
			$org['description'] = $b['description'];
		}
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';
		if ( $logo ) {
			$org['logo'] = $logo;
			if ( 'Organization' !== $b['type'] ) {
				$org['image'] = $logo;
			}
		}
		foreach ( array( 'phone' => 'telephone', 'email' => 'email', 'price_range' => 'priceRange', 'area' => 'areaServed' ) as $key => $prop ) {
			if ( '' !== $b[ $key ] && ( 'priceRange' !== $prop || 'Organization' !== $b['type'] ) ) {
				$org[ $prop ] = $b[ $key ];
			}
		}
		if ( '' !== $b['street'] || '' !== $b['city'] ) {
			$org['address'] = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $b['street'],
					'addressLocality' => $b['city'],
					'addressRegion'   => $b['region'],
					'postalCode'      => $b['postal'],
					'addressCountry'  => $b['country'],
				)
			);
		}
		if ( $b['hours'] && 'Organization' !== $b['type'] ) {
			$org['openingHours'] = $b['hours'];
		}
		if ( $b['same_as'] ) {
			$org['sameAs'] = $b['same_as'];
		}
		return array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				$org,
				array(
					'@type'     => 'WebSite',
					'@id'       => $home . '#website',
					'url'       => $home,
					'name'      => get_bloginfo( 'name' ),
					'publisher' => array( '@id' => $home . '#organization' ),
				),
			),
		);
	}

	/**
	 * BlogPosting for a single post.
	 *
	 * @return array<string,mixed>
	 */
	public static function article( \WP_Post $post ): array {
		$b       = self::get();
		$author  = get_userdata( (int) $post->post_author );
		$image   = get_the_post_thumbnail_url( $post, 'full' );
		$logo_id = (int) get_theme_mod( 'custom_logo' );
		$data    = array(
			'@context'         => 'https://schema.org',
			'@type'            => 'BlogPosting',
			'headline'         => mb_substr( wp_strip_all_tags( get_the_title( $post ) ), 0, 110 ),
			'datePublished'    => get_post_time( 'c', true, $post ),
			'dateModified'     => get_post_modified_time( 'c', true, $post ),
			'mainEntityOfPage' => (string) get_permalink( $post ),
			'publisher'        => array_filter(
				array(
					'@type' => 'Organization',
					'name'  => '' !== $b['name'] ? $b['name'] : get_bloginfo( 'name' ),
					'logo'  => $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : null,
				)
			),
		);
		if ( $author ) {
			$data['author'] = array(
				'@type' => 'Person',
				'name'  => $author->display_name,
				'url'   => get_author_posts_url( $author->ID ),
			);
		}
		if ( $image ) {
			$data['image'] = $image;
		}
		$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
		if ( '' !== $excerpt ) {
			$data['description'] = wp_strip_all_tags( $excerpt );
		}
		return $data;
	}

	public function output(): void {
		if ( is_admin() || is_feed() ) {
			return;
		}
		$seo_plugin = '' !== Seo::plugin();
		if ( is_front_page() ) {
			$graph = self::business_graph();
			if ( $graph && ( ! $seo_plugin || 'Organization' !== self::get()['type'] ) ) {
				if ( $seo_plugin ) {
					// Keep only the business node; the SEO plugin already describes the website.
					$graph['@graph'] = array( $graph['@graph'][0] );
				}
				self::print( $graph );
			}
		} elseif ( is_singular( 'post' ) && ! $seo_plugin ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				self::print( self::article( $post ) );
			}
		}
	}

	/**
	 * @param array<string,mixed> $data JSON-LD.
	 */
	private static function print( array $data ): void {
		// JSON_HEX_TAG escapes < and >, so nothing in the data can close the script element.
		wp_print_inline_script_tag( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ), array( 'type' => 'application/ld+json' ) );
	}
}
