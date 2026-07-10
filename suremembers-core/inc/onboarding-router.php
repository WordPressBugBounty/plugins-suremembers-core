<?php
/**
 * Onboarding Router.
 *
 * @package SureMembers
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Onboarding_Router.
 */
class Onboarding_Router {
	use Get_Instance;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		register_rest_route(
			'suremembers/v1',
			'/skip-onboarding',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'skip_onboarding' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);

		register_rest_route(
			'suremembers/v1',
			'/complete-onboarding',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'complete_onboarding' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);

		register_rest_route(
			'suremembers/v1',
			'/process-onboarding',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'process_onboarding' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);

		register_rest_route(
			'suremembers/v1',
			'/activate-plugin',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'activate_plugin' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);
	}

	/**
	 * Check permissions
	 */
	public function check_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Skip onboarding
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response
	 */
	public function skip_onboarding( $request ) {
		update_option( 'suremembers_onboarding_skipped', 'yes' );

		$step = sanitize_key( $request->get_param( 'step' ) ?? '' );
		if ( ! empty( $step ) ) {
			update_option( 'suremembers_onboarding_skipped_step', $step );
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Onboarding skipped successfully.', 'suremembers-core' ),
			],
			200
		);
	}

	/**
	 * Complete onboarding
	 */
	public function complete_onboarding() {
		update_option( 'suremembers_onboarding_completed', 'yes' );

		// Enable analytics optin if requested.
		if ( ! empty( $_POST['share_non_sensitive_data'] ) && sanitize_text_field( wp_unslash( $_POST['share_non_sensitive_data'] ) ) === 'on' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST API endpoint handles authentication.
			update_option( 'suremembers_usage_optin', 'yes' );
		}

		// Handle newsletter subscription if enabled.
		if ( ! empty( $_POST['subscribe_to_newsletter'] ) && sanitize_text_field( wp_unslash( $_POST['subscribe_to_newsletter'] ) ) === 'on' ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST API endpoint handles authentication.
			$this->subscribe_to_suremembers();
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Onboarding completed successfully.', 'suremembers-core' ),
			],
			200
		);
	}

	/**
	 * Process onboarding data
	 */
	public function process_onboarding() {
		$action = ! empty( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST API endpoint handles authentication.

		switch ( $action ) {
			case 'setup_access_groups':
				$this->setup_access_groups();
				break;
			case 'setup_integrations':
				$this->setup_integrations();
				break;
			case 'update_search_restriction':
				$this->update_search_restriction();
				break;
			default:
				break;
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Onboarding data updated successfully.', 'suremembers-core' ),
			],
			200
		);
	}

	/**
	 * Activate plugin.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response
	 */
	public function activate_plugin( $request ) {
		$plugin_path = '';

		// Get plugin_init from request (supports both FormData and JSON).
		$plugin_init = $request->get_param( 'plugin_init' );
		$plugins     = $request->get_param( 'plugins' );

		// Support direct plugin_init parameter or plugins array.
		if ( ! empty( $plugin_init ) ) {
			// Direct plugin_init parameter (FormData or JSON).
			$plugin_path = sanitize_text_field( $plugin_init );
		} elseif ( ! empty( $plugins ) && is_array( $plugins ) && isset( $plugins[0]['init'] ) ) {
			// JSON format with plugins array.
			$plugin_path = sanitize_text_field( $plugins[0]['init'] );
		}

		if ( empty( $plugin_path ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid plugin.', 'suremembers-core' ),
				],
				200
			);
		}

		if ( ! function_exists( 'activate_plugin' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Check if plugin file exists.
		$plugin_file = WP_PLUGIN_DIR . '/' . $plugin_path;
		if ( ! file_exists( $plugin_file ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Plugin file not found. Please install the plugin first.', 'suremembers-core' ),
				],
				200
			);
		}

		// Check if plugin is already active.
		if ( is_plugin_active( $plugin_path ) ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'Plugin is already active.', 'suremembers-core' ),
				],
				200
			);
		}

		$activate = activate_plugin( $plugin_path );

		if ( is_wp_error( $activate ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => $activate->get_error_message(),
				],
				200
			);
		}

		// Get plugin slug from request.
		$plugin_slug = $request->get_param( 'plugin_slug' );
		if ( empty( $plugin_slug ) && ! empty( $plugins ) && is_array( $plugins ) && isset( $plugins[0]['slug'] ) ) {
			$plugin_slug = sanitize_text_field( $plugins[0]['slug'] );
		}

		/**
		 * Fires after a plugin is activated via SureMembers.
		 *
		 * @since 2.0.0
		 *
		 * @param string $plugin_path Plugin init path (e.g., 'plugin-folder/plugin-file.php').
		 * @param string $plugin_slug Plugin slug.
		 */
		do_action( 'suremembers_after_plugin_activation', $plugin_path, $plugin_slug );

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Plugin activated successfully.', 'suremembers-core' ),
			],
			200
		);
	}

	/**
	 * Setup access groups
	 */
	private function setup_access_groups() {
		$access_group_name          = ! empty( $_POST['portal_name'] ) ? sanitize_text_field( wp_unslash( $_POST['portal_name'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST API endpoint handles authentication.
		$enable_downloads           = ! empty( $_POST['enable_downloads'] ) && sanitize_text_field( wp_unslash( $_POST['enable_downloads'] ) ) === 'on'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST API endpoint handles authentication.
		$enable_email_notifications = ! empty( $_POST['enable_email_notifications'] ) && sanitize_text_field( wp_unslash( $_POST['enable_email_notifications'] ) ) === 'on'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST API endpoint handles authentication.

		// Create access group post if name is provided.
		if ( ! empty( $access_group_name ) ) {
			$access_group_id = wp_insert_post(
				[
					'post_title'  => $access_group_name,
					'post_status' => 'publish',
					'post_type'   => SUREMEMBERS_POST_TYPE,
					'post_author' => get_current_user_id(),
				],
				true
			);

			// Set priority for the access group.
			if ( ! is_wp_error( $access_group_id ) && $access_group_id ) {
				update_post_meta( $access_group_id, SUREMEMBERS_PLAN_PRIORITY, 10 );
			}
		}

		// Save settings.
		$settings                               = get_option( SUREMEMBERS_ADMIN_SETTINGS, [] );
		$settings['enable_downloads']           = $enable_downloads;
		$settings['enable_email_notifications'] = $enable_email_notifications;
		update_option( SUREMEMBERS_ADMIN_SETTINGS, $settings );
	}

	/**
	 * Setup integrations
	 */
	private function setup_integrations() {
		$integrations_raw = ! empty( $_POST['integrations'] ) ? wp_unslash( $_POST['integrations'] ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REST API endpoint handles authentication.
		$integrations     = is_array( $integrations_raw ) ? array_map( 'sanitize_text_field', $integrations_raw ) : [];

		// Save integration settings.
		$settings                 = get_option( SUREMEMBERS_ADMIN_SETTINGS, [] );
		$settings['integrations'] = $integrations;
		update_option( SUREMEMBERS_ADMIN_SETTINGS, $settings );
	}

	/**
	 * Update search restriction setting
	 */
	private function update_search_restriction() {
		$enable_search_restriction = ! empty( $_POST['enable_search_restriction'] ) && sanitize_text_field( wp_unslash( $_POST['enable_search_restriction'] ) ) === 'on'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- REST API endpoint handles authentication.

		// Get current admin settings.
		$settings = \SureMembersCore\Inc\Settings::get_setting( 'suremembers_admin_settings' );

		// Update the search restriction setting.
		$settings['enable_search_restriction'] = $enable_search_restriction;

		// Save the updated settings.
		\SureMembersCore\Inc\Settings::update_setting( 'suremembers_admin_settings', $settings );
	}

	/**
	 * Subscribe to SureMembers newsletter via webhook
	 *
	 * @since 1.10.13
	 */
	private function subscribe_to_suremembers() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- REST API endpoint handles authentication.
		$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( empty( $user_email ) ) {
			return;
		}

		$domain = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! is_string( $domain ) ) {
			$domain = '';
		}

		// Send data to webhook/CRM.
		$url  = 'https://metrics.brainstormforce.com/wp-json/bsf-metrics-server/v1/subscribe/';
		$body = wp_json_encode(
			[
				'email'      => $user_email,
				'first_name' => $first_name,
				'last_name'  => $last_name,
				'domain'     => $domain,
				'source'     => 'suremembers',
			]
		);

		if ( $body === false ) {
			wp_send_json_error( [ 'message' => __( 'Failed to encode subscription payload.', 'suremembers-core' ) ] );
		}

		$args = [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => $body,
		];

		$response = wp_safe_remote_post( $url, $args );

		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $response_body['success'] ) && $response_body['success'] ) {
				update_user_meta( get_current_user_id(), 'suremembers-subscribed', 'yes' );
			}
		}
	}
}
