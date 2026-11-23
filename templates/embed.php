<?php
/**
 * Bare game page used inside the library modal.
 *
 * @package YouEmulator
 */

defined( 'ABSPATH' ) || exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'youem-embed' ); ?>>
<?php
while ( have_posts() ) {
	the_post();
	echo Youem_Public::player( (int) get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderer.
}
wp_footer();
?>
</body>
</html>
