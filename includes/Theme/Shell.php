<?php
/**
 * Page shell used by Uncoder page templates, for classic and block themes.
 *
 * @package Uncoder\Builder
 */

namespace Uncoder\Builder\Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Prints <html>…<body> plus the header location, and the footer location plus closing tags.
 */
final class Shell {

	private static bool $opened = false;

	/**
	 * Prints a theme-builder location. Returns true when an Uncoder template handled it.
	 */
	public static function location( string $location ): bool {
		/**
		 * Theme builder hooks in here to print header/footer templates.
		 *
		 * @param bool   $printed  Whether something was printed.
		 * @param string $location header|footer.
		 */
		return (bool) apply_filters( 'uncoder_wb/theme/print_location', false, $location );
	}

	public static function open_document(): void {
		if ( self::$opened ) {
			return;
		}
		self::$opened = true;
		?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
		<?php
		wp_body_open();
	}

	/** Footer markup rendered ahead of wp_head() (block themes), printed by footer(). */
	private static ?string $footer_html = null;

	public static function header(): void {
		if ( wp_is_block_theme() ) {
			// Like core's block template canvas, render the template parts before wp_head(): blocks enqueue
			// their styles, scripts and script modules while rendering (the navigation block's interactivity
			// module needs the import map printed in the head).
			$header            = self::capture( 'header' );
			self::$footer_html = self::capture( 'footer' );
			self::open_document();
			echo '<div class="wp-site-blocks">';
			echo $header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme template part / Uncoder template markup.
			return;
		}
		// Classic themes: the theme builder intercepts get_header when a header template applies.
		get_header();
	}

	public static function footer(): void {
		if ( wp_is_block_theme() ) {
			echo null !== self::$footer_html ? self::$footer_html : self::capture( 'footer' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- theme template part / Uncoder template markup.
			self::$footer_html = null;
			echo '</div>';
			wp_footer();
			echo "\n</body>\n</html>\n";
			return;
		}
		get_footer();
	}

	/**
	 * Markup of the header or footer: the Uncoder template for that location, else the theme's template part.
	 */
	private static function capture( string $location ): string {
		ob_start();
		if ( ! self::location( $location ) ) {
			block_template_part( $location );
		}
		return (string) ob_get_clean();
	}
}
