<?php
/**
 * Uncoder Canvas: a blank page without the theme header and footer.
 *
 * @package Uncoder\Builder
 */

defined( 'ABSPATH' ) || exit;

$uncoder_wb_canvas = static function (): void {
	do_action( 'uncoder_wb/canvas/before' );
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	do_action( 'uncoder_wb/canvas/after' );
};
// Block themes print the import map and script modules in the head only: render the page before wp_head(), as
// core's block template canvas does (see Theme\Shell::page()).
$uncoder_wb_html = null;
if ( wp_is_block_theme() ) {
	ob_start();
	$uncoder_wb_canvas();
	$uncoder_wb_html = (string) ob_get_clean();
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'uncoder-canvas' ); ?>>
<?php
wp_body_open();
if ( null !== $uncoder_wb_html ) {
	echo $uncoder_wb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the page content.
} else {
	$uncoder_wb_canvas();
}
wp_footer();
?>
</body>
</html>
