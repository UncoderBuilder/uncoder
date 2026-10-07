<?php
/**
 * Replacement header for classic themes when an Uncoder header template applies.
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
<body <?php body_class(); ?>>
<?php
wp_body_open();
apply_filters( 'uncoder_wb/theme/print_location', false, 'header' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals -- prefixed hook.
