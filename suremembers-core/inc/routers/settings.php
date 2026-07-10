<?php
/**
 * Settings Router - Handles settings-related API endpoints.
 *
 * @package SureMembers\Inc\Routers
 */

namespace SureMembersCore\Inc\Routers;

use SureMembersCore\Inc\Traits\Get_Instance;
use SureMembersCore\Inc\Utils\Global_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings Router.
 */
class Settings {
	use Get_Instance;

	/**
	 * Get global settings.
	 * REST API endpoint to retrieve all global settings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 * @since 2.0.0
	 */
	public function get_global_settings( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		// Get setting key from request (optional - if provided, return that specific setting).
		$setting_key = $request->get_param( 'setting_key' );

		try {
			if ( ! empty( $setting_key ) ) {
				// Get specific setting.
				$data = Global_Settings::get_setting( $setting_key );
			} else {
				// Get all settings.
				$data = Global_Settings::get_all_settings();
			}

			return new \WP_REST_Response(
				[
					'success' => true,
					'data'    => $data,
				],
				200
			);
		} catch ( \Exception $e ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Failed to retrieve settings: ', 'suremembers-core' ) . $e->getMessage(),
				],
				500
			);
		}
	}

	/**
	 * Update global settings.
	 * REST API endpoint to update settings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 * @since 2.0.0
	 */
	public function update_global_settings( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		// Get settings data from request.
		$settings_data = $request->get_param( 'settings_data' );
		$setting_key   = $request->get_param( 'setting_key' );

		// Validate input.
		if ( empty( $settings_data ) || empty( $setting_key ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid settings data or setting key.', 'suremembers-core' ),
				],
				400
			);
		}

		// Whitelist allowed setting keys to prevent arbitrary option updates.
		$allowed_keys = array_keys( Global_Settings::get_settings_defaults() );
		if ( ! in_array( $setting_key, $allowed_keys, true ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid setting key.', 'suremembers-core' ),
				],
				400
			);
		}

		// Block premium settings when Pro is not active.
		$premium_settings  = [
			SUREMEMBERS_LOGIN_RESTRICTIONS_SETTINGS,
			SUREMEMBERS_EMAIL_TEMPLATE_SETTINGS,
		];
		$is_premium_active = apply_filters( 'suremembers_is_premium_active', false );

		if ( in_array( $setting_key, $premium_settings, true ) && ! $is_premium_active ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'This feature requires SureMembers Pro.', 'suremembers-core' ),
				],
				403
			);
		}

		try {
			// Sanitize settings data based on setting key.
			$sanitized_data = $this->sanitize_settings_data( $settings_data, $setting_key );

			// Update the setting.
			Global_Settings::update_setting( $setting_key, $sanitized_data );

			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => __( 'Settings saved successfully.', 'suremembers-core' ),
				],
				200
			);
		} catch ( \Exception $e ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Failed to update settings: ', 'suremembers-core' ) . $e->getMessage(),
				],
				500
			);
		}
	}

	/**
	 * Update email template settings.
	 * Converts the old wp_ajax_suremembers_email_template_global_settings to REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_email_template_settings( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		// Get Settings_Screen instance to access helper methods.
		if ( ! class_exists( '\SureMembersCore\Admin\Settings_Screen' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Settings_Screen class not found.', 'suremembers-core' ),
				],
				500
			);
		}

		$settings_screen = \SureMembersCore\Admin\Settings_Screen::get_instance();
		$settings_data   = $request->get_param( 'settings_data' ) ?? '';
		$setting_key     = $request->get_param( 'setting_key' ) ?? '';

		// Call the refactored method that returns data instead of echoing JSON.
		$result = $settings_screen->update_email_global_template_settings_data( $settings_data, $setting_key );

		if ( $result['success'] ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'data'    => $result['data'],
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $result['message'] ?? __( 'Update email template failed.', 'suremembers-core' ),
			],
			400
		);
	}

	/**
	 * Search access groups.
	 * Converts the old wp_ajax_suremembers_search_access_groups to REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function search_access_groups( $request ) {
		// Permission is already checked by permission_callback in routes.php.

		// Get Settings_Screen instance to access helper methods.
		if ( ! class_exists( '\SureMembersCore\Admin\Settings_Screen' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Settings_Screen class not found.', 'suremembers-core' ),
				],
				500
			);
		}

		$settings_screen = \SureMembersCore\Admin\Settings_Screen::get_instance();

		$search = sanitize_text_field( $request->get_param( 'search' ) ?? '' );

		// Call the refactored method - NO $_POST or ob_start.
		$result = $settings_screen->search_access_groups_data( $search );

		if ( $result['success'] ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'data'    => $result['data'],
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $result['message'],
			],
			400
		);
	}

	/**
	 * Save redirection rules.
	 * Converts the old wp_ajax_suremembers_redirection_rules to REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function save_redirection_rules( $request ) {
		// Permission is already checked by permission_callback in routes.php.

		// Get Settings_Screen instance to access helper methods.
		if ( ! class_exists( '\SureMembersCore\Admin\Settings_Screen' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Settings_Screen class not found.', 'suremembers-core' ),
				],
				500
			);
		}

		$settings_screen = \SureMembersCore\Admin\Settings_Screen::get_instance();

		$login_redirect  = sanitize_text_field( $request->get_param( 'login_redirect' ) ?? '' );
		$logout_redirect = sanitize_text_field( $request->get_param( 'logout_redirect' ) ?? '' );

		// Call the refactored method - NO $_POST or ob_start.
		$result = $settings_screen->save_redirection_rules_data( $login_redirect, $logout_redirect );

		if ( $result['success'] ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'data'    => $result['data'],
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $result['message'],
			],
			400
		);
	}

	/**
	 * Sanitize settings data.
	 *
	 * @param array|string $data        Settings data to sanitize.
	 * @param string       $setting_key Setting key.
	 *
	 * @return array<string, mixed> Sanitized settings data.
	 * @since 2.0.0
	 */
	private function sanitize_settings_data( $data, $setting_key ) {
		// Decode if JSON string. REST API JSON body params are not slashed,
		// so calling stripslashes() here would corrupt JSON-escaped characters
		// like \" (e.g. inside <a href="..."> values), breaking the decode and
		// causing settings to silently save as an empty array.
		if ( is_string( $data ) ) {
			$data = json_decode( $data, true );
		}

		if ( ! is_array( $data ) ) {
			return [];
		}

		// Sanitize based on setting key.
		switch ( $setting_key ) {
			case SUREMEMBERS_ADMIN_SETTINGS:
				return $this->sanitize_admin_settings( $data );

			case SUREMEMBERS_REDIRECT_RULES:
				return $this->sanitize_redirect_rules( $data );

			case SUREMEMBERS_CUSTOM_CONTENT:
				return $this->sanitize_custom_content( $data );

			case SUREMEMBERS_LOGIN_FORM_SETTINGS:
				return $this->sanitize_login_form_settings( $data );

			case SUREMEMBERS_WEBHOOK_ENDPOINTS:
				return $this->sanitize_webhook_endpoints( $data );

			default:
				/**
				 * Filter to sanitize settings data for custom/premium setting keys.
				 *
				 * Allows Pro or other extensions to handle sanitization of their own settings.
				 *
				 * @since 1.0.0
				 *
				 * @param array<string, mixed>|null $sanitized   Sanitized data. Return array to use, null to fall back to default.
				 * @param array<string, mixed>      $data        Raw settings data.
				 * @param string                    $setting_key The setting key being sanitized.
				 */
				$filtered_data = apply_filters( 'suremembers_sanitize_settings_data', null, $data, $setting_key );

				if ( is_array( $filtered_data ) ) {
					return $filtered_data;
				}

				// Generic sanitization for unknown keys.
				return $this->sanitize_array_recursive( $data );
		}
	}

	/**
	 * Sanitize admin settings.
	 *
	 * @param array<string, mixed> $data Settings data.
	 * @return array<string, mixed> Sanitized data.
	 * @since 2.0.0
	 */
	private function sanitize_admin_settings( $data ) {
		$usage_tracking = isset( $data['usage_tracking'] ) ? (bool) $data['usage_tracking'] : false;

		// Sync BSF Analytics opt-in option.
		$this->sync_bsf_analytics_optin( $usage_tracking );

		return [
			'enable_gutenberg_icon'     => isset( $data['enable_gutenberg_icon'] ) ? (bool) $data['enable_gutenberg_icon'] : true,
			'decline_admin_screen'      => isset( $data['decline_admin_screen'] ) && is_array( $data['decline_admin_screen'] ) ? array_values(
				array_map(
					static function ( $item ) {
						if ( is_array( $item ) ) {
							return [
								'label' => isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '',
								'value' => isset( $item['value'] ) ? sanitize_text_field( $item['value'] ) : '',
							];
						}
						return sanitize_text_field( $item );
					},
					$data['decline_admin_screen']
				)
			) : [],
			'registration_access_group' => isset( $data['registration_access_group'] ) && is_array( $data['registration_access_group'] ) ? array_map( 'absint', $data['registration_access_group'] ) : [],
			'enable_search_restriction' => isset( $data['enable_search_restriction'] ) ? (bool) $data['enable_search_restriction'] : false,
			'hide_woocommerce_coupon'   => isset( $data['hide_woocommerce_coupon'] ) ? (bool) $data['hide_woocommerce_coupon'] : false,
			'usage_tracking'            => $usage_tracking,
		];
	}

	/**
	 * Sync BSF Analytics opt-in option.
	 *
	 * Updates the suremembers_usage_optin option based on usage_tracking setting.
	 *
	 * @param bool $usage_tracking Whether usage tracking is enabled.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	private function sync_bsf_analytics_optin( $usage_tracking ) {
		$optin_value = $usage_tracking ? 'yes' : 'no';
		update_option( 'suremembers_usage_optin', $optin_value );
	}

	/**
	 * Sanitize redirect rules.
	 *
	 * @param array<string, mixed> $data Settings data.
	 * @return array<string, mixed> Sanitized data.
	 * @since 2.0.0
	 */
	private function sanitize_redirect_rules( $data ) {
		return [
			'login_redirect'  => isset( $data['login_redirect'] ) ? esc_url_raw( $data['login_redirect'] ) : '',
			'logout_redirect' => isset( $data['logout_redirect'] ) ? esc_url_raw( $data['logout_redirect'] ) : '',
		];
	}

	/**
	 * Sanitize custom content.
	 *
	 * @param array<string, mixed> $data Settings data.
	 * @return array<string, mixed> Sanitized data.
	 * @since 2.0.0
	 */
	private function sanitize_custom_content( $data ) {
		$sanitized = [];
		foreach ( $data as $key => $value ) {
			$sanitized[ sanitize_key( $key ) ] = sanitize_text_field( $value );
		}
		return $sanitized;
	}

	/**
	 * Sanitize hex color value without requiring # prefix.
	 *
	 * WordPress sanitize_hex_color() requires # prefix, but our frontend sends colors without it.
	 * This helper adds # if needed, sanitizes, then returns the color without #.
	 *
	 * @param string $color Hex color value (with or without #).
	 * @return string Sanitized hex color without # prefix, or empty string if invalid.
	 * @since 2.0.0
	 */
	private function sanitize_hex_color_no_hash( $color ) {
		if ( empty( $color ) ) {
			return '';
		}

		// Remove # if present for consistent handling.
		$color = ltrim( $color, '#' );

		// Add # for WordPress sanitization.
		$sanitized = sanitize_hex_color( '#' . $color );

		// Return without # (our storage format) or empty if invalid.
		return $sanitized ? ltrim( $sanitized, '#' ) : '';
	}

	/**
	 * Sanitize login form settings.
	 *
	 * @param array<string, mixed> $data Settings data.
	 * @return array<string, mixed> Sanitized data.
	 * @since 2.0.0
	 */
	private function sanitize_login_form_settings( $data ) {
		return [
			'primary_color'           => isset( $data['primary_color'] ) ? $this->sanitize_hex_color_no_hash( $data['primary_color'] ) : '',
			'secondary_color'         => isset( $data['secondary_color'] ) ? $this->sanitize_hex_color_no_hash( $data['secondary_color'] ) : '',
			'text_color'              => isset( $data['text_color'] ) ? $this->sanitize_hex_color_no_hash( $data['text_color'] ) : '',
			'link_color'              => isset( $data['link_color'] ) ? $this->sanitize_hex_color_no_hash( $data['link_color'] ) : '',
			'logo_width'              => isset( $data['logo_width'] ) ? absint( $data['logo_width'] ) : '',
			'logo_height'             => isset( $data['logo_height'] ) ? absint( $data['logo_height'] ) : '',
			'disable_logo'            => isset( $data['disable_logo'] ) ? (bool) $data['disable_logo'] : false,
			'custom_logo'             => isset( $data['custom_logo'] ) ? (bool) $data['custom_logo'] : false,
			'logo_image'              => isset( $data['logo_image'] ) ? esc_url_raw( $data['logo_image'] ) : '',
			'enable_transparent_form' => isset( $data['enable_transparent_form'] ) ? (bool) $data['enable_transparent_form'] : false,
			'login_form_background'   => isset( $data['login_form_background'] ) ? $this->sanitize_hex_color_no_hash( $data['login_form_background'] ) : '',
			'login_form_border'       => isset( $data['login_form_border'] ) ? $this->sanitize_hex_color_no_hash( $data['login_form_border'] ) : '',
			'enable_background_image' => isset( $data['enable_background_image'] ) ? (bool) $data['enable_background_image'] : false,
			'background_repeat'       => isset( $data['background_repeat'] ) ? sanitize_text_field( $data['background_repeat'] ) : 'no-repeat',
			'background_position'     => isset( $data['background_position'] ) ? sanitize_text_field( $data['background_position'] ) : 'center',
			'background_size'         => isset( $data['background_size'] ) ? sanitize_text_field( $data['background_size'] ) : 'cover',
			'background_image'        => isset( $data['background_image'] ) ? esc_url_raw( $data['background_image'] ) : '',
			'background_color'        => isset( $data['background_color'] ) ? $this->sanitize_hex_color_no_hash( $data['background_color'] ) : 'f0f0f1',
			'login_url'               => isset( $data['login_url'] ) ? sanitize_title( $data['login_url'] ) : 'login',
			'enable_login_url'        => isset( $data['enable_login_url'] ) ? (bool) $data['enable_login_url'] : false,
			'login_redirect_url'      => isset( $data['login_redirect_url'] ) ? sanitize_text_field( $data['login_redirect_url'] ) : '404',
			// Turnstile settings.
			'enable_turnstile'        => isset( $data['enable_turnstile'] ) ? (bool) $data['enable_turnstile'] : false,
			'turnstile_site_key'      => isset( $data['turnstile_site_key'] ) ? sanitize_text_field( $data['turnstile_site_key'] ) : '',
			'turnstile_secret_key'    => isset( $data['turnstile_secret_key'] ) ? sanitize_text_field( $data['turnstile_secret_key'] ) : '',
			'turnstile_theme'         => isset( $data['turnstile_theme'] ) ? sanitize_text_field( $data['turnstile_theme'] ) : 'auto',
		];
	}

	/**
	 * Sanitize webhook endpoints.
	 *
	 * @param array<string, mixed> $data Settings data.
	 * @return array<string, mixed> Sanitized data.
	 * @since 2.0.0
	 */
	private function sanitize_webhook_endpoints( $data ) {
		return $this->sanitize_array_recursive( $data );
	}

	/**
	 * Recursively sanitize an array.
	 *
	 * @param array<string, mixed> $data Array to sanitize.
	 * @return array<string, mixed> Sanitized array.
	 * @since 2.0.0
	 */
	private function sanitize_array_recursive( $data ) {
		$sanitized = [];
		foreach ( $data as $key => $value ) {
			$key = sanitize_key( $key );
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_array_recursive( $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}
		return $sanitized;
	}
}
