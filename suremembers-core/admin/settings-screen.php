<?php
/**
 * Global Settings class.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Admin;

use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Settings;
use SureMembersCore\Inc\Traits\Get_Instance;
use SureMembersCore\Inc\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Global settings class.
 *
 * @since 1.0.0
 */
class Settings_Screen {
	use Get_Instance;

	/**
	 * Class Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		// All legacy AJAX handlers have been removed - now using REST API only.
		// REST API endpoints are in inc/routers/settings.php.
		// @deprecated 2.0.0 All AJAX endpoints replaced with REST API endpoints at /wp-json/suremembers/v1/.

		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'add_suremembers_logo' ] );
	}

	/**
	 * Update email template settings (returns data instead of echoing JSON).
	 *
	 * @param string $settings_data JSON string or array of settings data.
	 * @param string $setting_key Settings key.
	 *
	 * @return array{success: bool, data?: array<string, mixed>, message?: string} Result array.
	 *
	 * @since 1.10.14
	 */
	public function update_email_global_template_settings_data( $settings_data, $setting_key ) {
		// Check user permission.
		if ( ! current_user_can( 'manage_options' ) ) {
			return [
				'success' => false,
				'message' => __( 'Current user does not have the required permission.', 'suremembers-core' ),
			];
		}

		// Verify the presence of setting data.
		if ( empty( $settings_data ) ) {
			return [
				'success' => false,
				'message' => __( 'Settings cannot be empty.', 'suremembers-core' ),
			];
		}

		if ( empty( $setting_key ) ) {
			return [
				'success' => false,
				'message' => __( 'Setting key is required to save data.', 'suremembers-core' ),
			];
		}

		// Decode if it's a JSON string. REST API JSON body params are not slashed,
		// so unslashing here would corrupt JSON-escaped characters like \"
		// (e.g. inside <a href="..."> values) and produce an invalid decode.
		if ( is_string( $settings_data ) ) {
			$settings_data = json_decode( $settings_data, true, 512, JSON_OBJECT_AS_ARRAY );
		}

		// Sending an array of the keys which we need to allow the basic HTML in the database allowed by the WordPress.
		$data_to_allow_wp_kses = [ 'user_onboarding_content', 'reset_email_content', 'access_exp_content' ];

		$settings_data = is_array( $settings_data ) ? $this->sanitize_settings( $settings_data, $data_to_allow_wp_kses ) : [];

		Settings::update_setting( $setting_key, $settings_data );

		return [
			'success' => true,
			'data'    => [
				'message'       => __( 'Email template updated successfully.', 'suremembers-core' ),
				'settings_data' => $settings_data,
			],
		];
	}

	/**
	 * Get user roles array.
	 *
	 * @return array<string, mixed> array of user roles.
	 *
	 * @since 1.0.0
	 */
	public function get_formated_user_roles() {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			return [];
		}

		$available_roles_names = $wp_roles->get_names();
		$excluded_roles        = apply_filters( 'suremembers_settings_excluded_roles', [ 'administrator' => esc_html__( 'Administrator', 'suremembers-core' ) ] );

		$included_roles = array_diff( $available_roles_names, $excluded_roles );
		return Utils::get_react_select_format( $included_roles );
	}

	/**
	 * Return redirection rules
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.3.0
	 */
	public function get_redirection_rules() {
		return Settings::get_setting( SUREMEMBERS_REDIRECT_RULES );
	}

	/**
	 * Sanitize global settings array.
	 *
	 * @param array<string, mixed> $settings Array of settings to sanitize.
	 * @param array<string, mixed> $keys_to_wp_kses The keys which needs to allow some HTML tags to be saved.
	 *
	 * @return array<string, mixed> Array of settings sanitized.
	 *
	 * @since 1.0.0
	 */
	public function sanitize_settings( $settings, $keys_to_wp_kses = [] ) {
		$response = [];
		foreach ( $settings as $key => $data ) {
			if ( is_array( $data ) ) {
				$val = $this->sanitize_settings( $data, $keys_to_wp_kses );
			} elseif ( is_bool( $data ) ) {
				$val = rest_sanitize_boolean( $data );
			} else {
				if ( ! empty( $keys_to_wp_kses ) && in_array( $key, $keys_to_wp_kses ) ) {
					$val = wp_kses_post( $data );
				} else {
					$val = sanitize_text_field( $data );
				}
			}
			$response[ $key ] = $val;
		}

		return $response;
	}

	/**
	 * Update global settings AJAX.
	 *
	 * @since 1.0.0
	 * @deprecated 2.0.0 Use REST API endpoint /suremembers/v1/update-global-settings instead (handled by Global_Settings utility class)
	 */
	public function update_global_settings() {
		// Skip nonce verification for REST API requests (handled by REST permission_callback).
		if ( ! $this->is_rest_request() ) {
			check_ajax_referer( 'suremembers_global_settings_nonce', 'security' );
		}

		if ( ! $this->check_user_cap() ) {
			wp_send_json_error( [ 'message' => __( 'Current user does not have required permission.', 'suremembers-core' ) ] );
		}

		if ( ! isset( $_POST['setting_key'] ) || empty( $_POST['setting_key'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Setting key is required to save data.', 'suremembers-core' ) ] );
		}

		if ( ! isset( $_POST['settings_data'] ) || empty( $_POST['settings_data'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Settings cannot be empty.', 'suremembers-core' ) ] );
		}

		// Ignoring sanitization as it it done below with custom function.
		$settings_data = json_decode( stripslashes_deep( $_POST['settings_data'] ), true, 512, JSON_OBJECT_AS_ARRAY ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings_data = is_array( $settings_data ) ? $this->sanitize_settings( $settings_data ) : [];
		$key           = sanitize_text_field( $_POST['setting_key'] );

		Settings::update_setting( $key, $settings_data );

		if ( $key === SUREMEMBERS_CUSTOM_CONTENT ) {
			$data = Settings::get_custom_content_data();
		} else {
			$data = Settings::get_setting( $key );
		}
		wp_send_json_success(
			[
				'message'       => __( 'Settings Saved', 'suremembers-core' ),
				'settings_data' => $data,
			]
		);
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		echo '<div id="suremembers-global-settings"></div>';
	}

	/**
	 * Add user role (returns data instead of echoing JSON).
	 *
	 * @param string $slug User role slug.
	 * @param string $title User role title.
	 *
	 * @return array{success: bool, data?: array<string, mixed>, message?: string} Result array.
	 *
	 * @since 1.10.14
	 */
	public function suremembers_add_user_roles_data( $slug, $title ) {
		if ( ! $this->check_user_cap() ) {
			return [
				'success' => false,
				'message' => __( 'Current user does not have required permission.', 'suremembers-core' ),
			];
		}

		if ( empty( $slug ) || empty( $title ) ) {
			return [
				'success' => false,
				'message' => __( 'Slug and title are required.', 'suremembers-core' ),
			];
		}

		// Check if role already exists.
		if ( get_role( $slug ) ) {
			return [
				'success' => false,
				'message' => __( 'User role already exists.', 'suremembers-core' ),
			];
		}

		$result = $this->create_user_role( $slug, $title );
		if ( ! empty( $result ) ) {
			return [
				'success' => true,
				'data'    => $result,
			];
		}
			return [
				'success' => false,
				'message' => __( 'User role addition failed', 'suremembers-core' ),
			];
	}

	/**
	 * Update user role (returns data instead of echoing JSON).
	 *
	 * @param string $id User role ID.
	 * @param string $slug User role slug.
	 * @param string $title User role title.
	 *
	 * @return array{success: bool, data?: array<string, mixed>, message?: string} Result array.
	 *
	 * @since 1.10.14
	 */
	public function suremembers_update_user_roles_data( $id, $slug, $title ) {
		if ( ! $this->check_user_cap() ) {
			return [
				'success' => false,
				'message' => __( 'Current user does not have required permission.', 'suremembers-core' ),
			];
		}

		if ( empty( $id ) || empty( $title ) || empty( $slug ) ) {
			return [
				'success' => false,
				'message' => __( 'ID, slug and title are required.', 'suremembers-core' ),
			];
		}

		remove_role( $id );
		$result = $this->create_user_role( $slug, $title );
		if ( $result ) {
			return [
				'success' => true,
				'data'    => $result,
			];
		}
			return [
				'success' => false,
				'message' => __( 'User role update failed', 'suremembers-core' ),
			];
	}

	/**
	 * Remove user role (returns data instead of echoing JSON).
	 *
	 * @param string $id User role ID.
	 *
	 * @return array{success: bool, data?: array<string, mixed>, message?: string} Result array.
	 *
	 * @since 1.10.14
	 */
	public function suremembers_remove_user_roles_data( $id ) {
		if ( ! $this->check_user_cap() ) {
			return [
				'success' => false,
				'message' => __( 'Current user does not have required permission.', 'suremembers-core' ),
			];
		}

		if ( empty( $id ) ) {
			return [
				'success' => false,
				'message' => __( 'ID is required.', 'suremembers-core' ),
			];
		}

		// Replace three backslashes with a single backslash.
		$replaced_id = str_replace( '\\\\\\', '\\', $id );

		remove_role( $replaced_id );

		return [
			'success' => true,
			'data'    => [],
		];
	}

	/**
	 * Update redirection rules data.
	 *
	 * @param string $login_redirect Login redirect URL.
	 * @param string $logout_redirect Logout redirect URL.
	 *
	 * @return array{success: bool, data?: array<string, mixed>, message?: string} Result of the operation.
	 *
	 * @since 1.3.0
	 */
	public function save_redirection_rules_data( $login_redirect, $logout_redirect ) {
		if ( ! $this->check_user_cap() ) {
			return [
				'success' => false,
				'message' => __( 'Unauthorized access', 'suremembers-core' ),
			];
		}

		$settings = [
			'login_redirect'  => $login_redirect,
			'logout_redirect' => $logout_redirect,
		];

		Settings::update_setting( SUREMEMBERS_REDIRECT_RULES, $settings );

		return [
			'success' => true,
			'data'    => [
				'message' => __( 'Settings saved', 'suremembers-core' ),
			],
		];
	}

	/**
	 * Returns active access groups matching search term
	 *
	 * @since 1.4.0
	 */
	public function search_access_groups() {
		// Skip nonce verification for REST API requests (handled by REST permission_callback).
		if ( ! $this->is_rest_request() ) {
			check_ajax_referer( 'suremembers_global_settings_nonce', 'security' );
		}

		$search = ! empty( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Call the data method.
		$result = $this->search_access_groups_data( $search );

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] ?? [] );
		} else {
			wp_send_json_error( [ 'message' => $result['message'] ?? '' ] );
		}
	}

	/**
	 * Search access groups data.
	 *
	 * @param string $search Search term.
	 *
	 * @return array{success: bool, data?: array<string, mixed>, message?: string} Result of the search operation.
	 *
	 * @since 1.4.0
	 */
	public function search_access_groups_data( $search ) {
		if ( ! $this->check_user_cap() ) {
			return [
				'success' => false,
				'message' => __( 'Unauthorized access', 'suremembers-core' ),
			];
		}

		if ( empty( $search ) ) {
			return [
				'success' => false,
				'message' => __( 'No search result found', 'suremembers-core' ),
			];
		}

		$filter_args = [
			's' => $search,
		];

		$access_groups = Access_Groups::get_active( $filter_args );
		$access_groups = Utils::get_react_select_format( $access_groups );

		return [
			'success' => true,
			'data'    => $access_groups,
		];
	}

	/**
	 * Adds logo for SureMembers plugins on updater page
	 *
	 * @param object $transient Transient object.
	 *
	 * @since 1.10.12
	 */
	public function add_suremembers_logo( $transient ) {
		$logo_url    = SUREMEMBERS_CORE_URL . 'admin/assets/images/icon.svg';
		$plugin_slug = 'suremembers/suremembers.php';

		if ( isset( $transient->response[ $plugin_slug ] ) ) {
			$plugin_data = $transient->response[ $plugin_slug ];

			// Only update the icons.
			$plugin_data->icons = [
				'1x' => $logo_url,
				'2x' => $logo_url,
			];

			$transient->response[ $plugin_slug ] = $plugin_data;
		}

		return $transient;
	}

	/**
	 * Check if the current request is a REST API request.
	 *
	 * @return bool True if REST request, false otherwise.
	 * @since 1.0.0
	 */
	private function is_rest_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], '/wp-json/' ) !== false ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return true;
		}

		return false;
	}

	/**
	 * Check if user can manage settings.
	 *
	 * @since 1.0.0
	 */
	private function check_user_cap() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Create user role.
	 *
	 * @param string $slug Slug will be the id of role.
	 * @param string $title User role title.
	 *
	 * @since 1.1.0
	 *
	 * @return \WP_Role|false
	 */
	/**
	 * Create a new user role.
	 *
	 * @param string $slug  The role slug.
	 * @param string $title The role title.
	 * @return \WP_Role|false The role object on success, false on failure.
	 * @since 1.0.0
	 */
	private function create_user_role( $slug, $title ) {
		// Check if role already exists.
		if ( get_role( $slug ) ) {
			return false;
		}
		$role = add_role( $slug, $title );
		// Ensure false instead of null if role creation fails.
		return $role instanceof \WP_Role ? $role : false;
	}

	/**
	 * Return selected access group in format required for react select, empty if not access group is selected.
	 *
	 * @since 1.4.0
	 */
	private function get_selected_registration_access_group() {
		$settings = Settings::get_setting( SUREMEMBERS_ADMIN_SETTINGS );

		$access_group_ids = isset( $settings['registration_access_group'] ) ? Utils::sanitize_recursively( 'absint', $settings['registration_access_group'] ) : [];
		if ( empty( $access_group_ids ) ) {
			return [];
		}

		$access_groups = Access_Groups::get_active( [ 'post__in' => $access_group_ids ] );
		if ( empty( $access_groups ) ) {
			return [];
		}

		return Utils::get_react_select_format( $access_groups );
	}
}
