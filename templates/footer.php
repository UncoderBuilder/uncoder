<?php
/**
 * Replacement footer for classic themes when an Uncoder footer template applies.
 *
 * @package Uncoder\Builder
 */

defined( 'ABSPATH' ) || exit;

apply_filters( 'uncoder_wb/theme/print_location', false, 'footer' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- prefixed hook.
wp_footer();
?>
</body>
</html>
