<?php
/**
 * Page shell for theme-builder body templates (single, archive, search, 404).
 *
 * @package Uncoder\Builder
 */

defined( 'ABSPATH' ) || exit;

\Uncoder\Builder\Theme\Shell::header();
?>
<main id="content" class="uncoder-main">
	<?php
	$uncoder_wb_theme = \Uncoder\Builder\Theme\Theme_Builder::instance();
	if ( $uncoder_wb_theme ) {
		$uncoder_wb_theme->print_body();
	}
	?>
</main>
<?php
\Uncoder\Builder\Theme\Shell::footer();
