<?php
/**
 * The template for displaying restricted pages.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package Suremembers
 *
 * @since 1.0.0
 */

use SureMembersCore\Inc\Restricted;
use SureMembersCore\Inc\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Block themes (FSE) don't ship `header.php`/`footer.php`, so `get_header()`/
 * `get_footer()` fall back to the generic `wp-includes/theme-compat` templates.
 * Those fallbacks ignore the active block theme's designed header/footer template
 * parts and its root layout, so the restricted "Message" page renders unstyled.
 *
 * When a block theme is active we render our own canvas: a document that fires
 * `wp_head()`/`wp_footer()` (so theme global styles and the plugin CSS load) and
 * the theme's real `header`/`footer` template parts via `block_header_area()`/
 * `block_footer_area()`. Classic themes keep using `get_header()`/`get_footer()`.
 *
 * @since 1.3.1
 */
$suremembers_is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() && function_exists( 'block_header_area' );

if ( $suremembers_is_block_theme ) {
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	<div class="wp-site-blocks">
		<?php block_header_area(); ?>
	<?php
} else {
	get_header();
}
?>
	<main id="site-content" class="suremembers-main-container">
		<div class="suremembers-container-div">
			<h2>
				<?php
					$heading   = Settings::get_custom_content_data( 'custom_template_heading' );
				$content_title = ! empty( $heading['value'] ) ? sanitize_text_field( $heading['value'] ) : sanitize_text_field( $heading['default'] );
				echo esc_html( $content_title );
				?>
			</h2>
			<div class="suremembers-unauthorized-container">
				<?php
				if ( ! empty( $args ) ) {
					if ( isset( $args['drip_string'] ) ) {
						echo wp_kses_post( $args['drip_string'] );
					} else {
						echo Restricted::get_unauthorized_message( $args ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					}

					do_action( 'suremembers_after_restricted_message_content', $args );
				}
				?>
			</div>
		</div>
	</main>

<?php
if ( $suremembers_is_block_theme ) {
	?>
		<?php block_footer_area(); ?>
	</div><!-- .wp-site-blocks -->
	<?php wp_footer(); ?>
</body>
</html>
	<?php
} else {
	get_footer();
}
?>
