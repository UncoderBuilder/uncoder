<?php
/**
 * Uncoder Full Width: the site header and footer around edge-to-edge builder content.
 *
 * @package Uncoder\Builder
 */

defined( 'ABSPATH' ) || exit;

\Uncoder\Builder\Theme\Shell::header();
?>
<main id="content" class="uncoder-main uncoder-main--full-width">
	<?php
	while ( have_posts() ) {
		the_post();
		the_content();
	}
	?>
</main>
<?php
\Uncoder\Builder\Theme\Shell::footer();
