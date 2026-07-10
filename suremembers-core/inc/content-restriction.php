<?php
/**
 * Content Restriction.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Content Restriction
 *
 * @since 0.0.1
 */
class Content_Restriction {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since  0.0.1
	 */
	public function __construct() {
		add_shortcode( 'suremembers_restrict', [ $this, 'restricted_content' ] );
	}

	/**
	 * Generates content from shortcode 'suremembers_restrict' as per users plan.
	 *
	 * @param array       $atts shortcode attributes.
	 * @param string|null $content shortcode content.
	 *
	 * @since 1.0.0
	 */
	public function restricted_content( $atts, $content = null ) {
		$atts                  = array_map( 'sanitize_text_field', $atts );
		$access_group_ids      = ! empty( $atts['access_group_ids'] ) ? $atts['access_group_ids'] : '';
		$content_access_groups = ! empty( $access_group_ids ) ? explode( ',', str_replace( ' ', '', trim( $access_group_ids ) ) ) : [];
		$has_access            = Access_Groups::check_if_user_has_access( $content_access_groups );

		// If user has access, show the content.
		if ( $has_access ) {
			return '<p>' . ( $content ?? '' ) . '</p>';
		}

		// User doesn't have access - show restriction message from access group settings.
		if ( ! empty( $content_access_groups ) ) {
			$access_group_id = intval( $content_access_groups[0] ); // Get first access group.
			$action          = get_post_meta( $access_group_id, SUREMEMBERS_PLAN_RULES, true );
			$restrict        = is_array( $action ) && isset( $action['restrict'] ) ? $action['restrict'] : [];

			if ( ! empty( $restrict ) ) {
				$unauthorized_action = $restrict['unauthorized_action'] ?? 'preview';

				// Handle redirect action.
				if ( $unauthorized_action === 'redirect' && ! empty( $restrict['redirect_url'] ) ) {
					$redirect_url = esc_url( trim( $restrict['redirect_url'] ) );
					if ( ! empty( $redirect_url ) ) {
						Utils::stop_infinite_redirect( $redirect_url );
						wp_redirect( $redirect_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
						exit;
					}
				}

				// Handle preview action - show restriction message.
				if ( $unauthorized_action === 'preview' || empty( $unauthorized_action ) ) {
					// Add default message if preview_content is empty.
					if ( empty( $restrict['preview_content'] ) ) {
						$restrict['preview_content'] = __( 'This content is restricted', 'suremembers-core' );
					}
					return wpautop( Restricted::get_unauthorized_message( $restrict ) );
				}
			}
		}

		// Fallback: show default restriction message if no access group or restriction settings found.
		return '<p>' . esc_html__( 'This content is restricted', 'suremembers-core' ) . '</p>';
	}
}
