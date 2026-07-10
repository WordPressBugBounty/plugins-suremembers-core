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

get_header(); ?>
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

<?php get_footer(); ?>
