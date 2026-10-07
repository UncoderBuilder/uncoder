<?php
/**
 * Uncoder Canvas: a blank page without the theme header and footer.
 *
 * @package Uncoder\Builder
 */

defined( 'ABSPATH' ) || exit;
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
do_action( 'uncoder_wb/canvas/before' );
while ( have_posts() ) {
	the_post();
	the_content();
}
do_action( 'uncoder_wb/canvas/after' );
wp_footer();
?>
</body>
</html>
