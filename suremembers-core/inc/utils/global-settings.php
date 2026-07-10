<?php
/**
 * Global Settings utility class.
 * Manages all plugin settings with schema validation and caching.
 *
 * @package suremembers
 * @since 2.0.0
 */

namespace SureMembersCore\Inc\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Global Settings helper class.
 * Follows SureDash pattern for settings management.
 *
 * @since 2.0.0
 */
class Global_Settings {
	/**
	 * Cached settings data.
	 *
	 * @var array<string, mixed>
	 * @since 2.0.0
	 */
	private static $cached_settings = [];

	/**
	 * Get all settings with defaults and caching.
	 *
	 * @param bool $use_cache Whether to use cached data.
	 * @return array<string, mixed> All settings merged with defaults.
	 * @since 2.0.0
	 */
	public static function get_all_settings( $use_cache = true ) {
		// Return cached settings if available.
		if ( $use_cache && ! empty( self::$cached_settings ) ) {
			return self::$cached_settings;
		}

		$settings = [];
		$defaults = self::get_settings_defaults();

		// Get each setting from database and merge with defaults.
		foreach ( $defaults as $key => $default_values ) {
			$db_value         = get_option( $key, [] );
			$settings[ $key ] = self::parse_args( $db_value, $default_values );
		}

		// Decrypt sensitive keys.
		$settings = self::decrypt_keys( $settings );

		// Apply filter for customization.
		$settings = apply_filters( 'suremembers_global_settings', $settings );

		// Cache the settings.
		self::$cached_settings = $settings;

		return $settings;
	}

	/**
	 * Get a specific setting by key.
	 *
	 * @param string $key Setting key (option name).
	 * @return array<string, mixed> Setting value merged with defaults.
	 * @since 2.0.0
	 */
	public static function get_setting( $key ) {
		$defaults = self::get_settings_defaults();

		if ( ! isset( $defaults[ $key ] ) ) {
			return [];
		}

		$db_value = get_option( $key, [] );
		return self::parse_args( $db_value, $defaults[ $key ] );
	}

	/**
	 * Update a specific setting.
	 *
	 * @param string               $key Setting key (option name).
	 * @param array<string, mixed> $data Setting data to save.
	 * @return void
	 * @since 2.0.0
	 */
	public static function update_setting( $key, $data ) {
		update_option( $key, $data );

		// Clear cache.
		self::$cached_settings = [];

		// Fire action after settings update.
		do_action( 'suremembers_settings_updated', $key, $data );
	}

	/**
	 * Update multiple settings at once.
	 *
	 * @param array<string, mixed> $settings Array of settings to update [key => data].
	 * @return void
	 * @since 2.0.0
	 */
	public static function update_settings( $settings ) {
		foreach ( $settings as $key => $data ) {
			if ( is_array( $data ) ) {
				self::update_setting( $key, $data );
			}
		}

		// Clear cache once at the end.
		self::$cached_settings = [];
	}

	/**
	 * Get settings schema/defaults for all settings.
	 *
	 * @return array<string, mixed> Settings defaults organized by option key.
	 * @since 2.0.0
	 */
	public static function get_settings_defaults() {
		return apply_filters(
			'suremembers_settings_defaults',
			[
				SUREMEMBERS_ADMIN_SETTINGS              => [
					'enable_gutenberg_icon'     => true,
					'decline_admin_screen'      => [],
					'registration_access_group' => [],
					'enable_search_restriction' => false,
					'hide_woocommerce_coupon'   => false,
					'usage_tracking'            => false,
				],
				SUREMEMBERS_REDIRECT_RULES              => [
					'login_redirect'  => '',
					'logout_redirect' => '',
				],
				SUREMEMBERS_CUSTOM_CONTENT              => [
					'login_link'              => 'Login',
					'custom_template_heading' => 'This content is restricted',
					'loop_content'            => 'Restricted content',
					'login_popup_title'       => 'Login',
					'login_popup_username'    => 'Email or Username',
					'login_popup_password'    => 'Password',
					'login_popup_remember'    => 'Remember Me',
					'login_popup_forgot'      => 'Forgot password?',
					'login_popup_submit'      => 'Login',
					'login_limit_exceeded'    => 'Maximum number of allowed active logins has been exceeded for your account. Please logout from another device to continue.',
					'login_limit_reset'       => 'Click here to Logout from other devices.',
				],
				SUREMEMBERS_LOGIN_FORM_SETTINGS         => [
					'primary_color'           => '',
					'secondary_color'         => '',
					'text_color'              => '',
					'link_color'              => '',
					'logo_width'              => '',
					'logo_height'             => '',
					'disable_logo'            => false,
					'custom_logo'             => false,
					'logo_image'              => '',
					'enable_transparent_form' => false,
					'login_form_background'   => '',
					'login_form_border'       => '',
					'enable_background_image' => false,
					'background_repeat'       => 'no-repeat',
					'background_position'     => 'center',
					'background_size'         => 'cover',
					'background_image'        => '',
					'background_color'        => 'f0f0f1',
					'login_url'               => 'login',
					'enable_login_url'        => false,
					'login_redirect_url'      => '404',
				],
				SUREMEMBERS_EMAIL_TEMPLATE_SETTINGS     => [
					'form_name'                        => get_bloginfo( 'name' ),
					'from_email'                       => get_bloginfo( 'admin_email' ),
					// Reset password notifications settings options.
					'enable_reset_password'            => false,
					'reset_email_use_woo_template'     => false,
					'reset_email_subject'              => 'Password Reset Request',
					'reset_email_content'              => '<p>Hello {$user_display_name}, </p><p>You requested a password reset. Please click the following link to reset your password: </p><p>{$reset_password_link}</p>',
					// New user registration/onboarding settings options.
					'enable_user_onboarding'           => false,
					'user_onboarding_use_woo_template' => false,
					'user_onboarding_subject'          => 'Welcome to our Site!',
					'user_onboarding_content'          => '<p>Hello {$user_display_name},</p> <p>Welcome to our site! We are excited to have you on board.</p><p>Best regards,<br/>{$site_name}</p>',
					// Access expiration notification settings options.
					'enable_access_exp'                => false,
					'access_exp_use_woo_template'      => false,
					'access_exp_subject'               => __( 'Congratulation!! You have been added to the Membership.', 'suremembers-core' ),
					'access_exp_content'               => '<p>Hello {$user_display_name},</p><p>You have been added to a site access group. Your access will expire on {$sm_access_group_expiration}.</p><p>Best regards,<br/>{$site_name}</p>',
				],
				SUREMEMBERS_LOGIN_RESTRICTIONS_SETTINGS => [],
				SUREMEMBERS_WEBHOOK_ENDPOINTS           => [],
				SUREMEMBERS_ABILITIES_SETTINGS          => [
					'suremembers_abilities_api'        => true,
					'suremembers_abilities_api_edit'   => false,
					'suremembers_abilities_api_delete' => false,
					'suremembers_mcp_server'           => false,
				],
			]
		);
	}

	/**
	 * Deep merge arrays like wp_parse_args but for multi-dimensional arrays.
	 *
	 * @param array<string, mixed> $args     Values to merge.
	 * @param array<string, mixed> $defaults Default values.
	 * @return array<string, mixed> Merged array.
	 * @since 2.0.0
	 */
	public static function parse_args( $args, $defaults = [] ) {
		$args     = (array) $args;
		$defaults = (array) $defaults;
		$result   = $defaults;

		foreach ( $args as $key => $value ) {
			if ( is_array( $value ) && ! empty( $value ) && isset( $result[ $key ] ) && is_array( $result[ $key ] ) ) {
				$result[ $key ] = self::parse_args( $value, $result[ $key ] );
			} else {
				$result[ $key ] = $value;
			}
		}

		return $result;
	}

	/**
	 * Clear settings cache.
	 *
	 * @return void
	 * @since 2.0.0
	 */
	public static function clear_cache() {
		self::$cached_settings = [];
	}

	/**
	 * Get custom content data with labels.
	 *
	 * @param string $key Optional specific key to retrieve.
	 * @return array<string, mixed> Custom content data with labels and defaults.
	 * @since 2.0.0
	 */
	public static function get_custom_content_data( $key = '' ) {
		$values   = self::get_setting( SUREMEMBERS_CUSTOM_CONTENT );
		$defaults = self::get_settings_defaults()[ SUREMEMBERS_CUSTOM_CONTENT ];

		$default_data = [
			'login_link'              => [
				'label' => __( 'Login link', 'suremembers-core' ),
			],
			'custom_template_heading' => [
				'label' => __( 'Custom template heading', 'suremembers-core' ),
			],
			'loop_content'            => [
				'label' => __( 'Content for Archive Page / Search Result', 'suremembers-core' ),
			],
			'login_popup_title'       => [
				'label' => __( 'Login popup', 'suremembers-core' ),
			],
			'login_popup_username'    => [
				'label' => __( 'Login popup username', 'suremembers-core' ),
			],
			'login_popup_password'    => [
				'label' => __( 'Login popup password', 'suremembers-core' ),
			],
			'login_popup_remember'    => [
				'label' => __( 'Login popup remember me', 'suremembers-core' ),
			],
			'login_popup_forgot'      => [
				'label' => __( 'Login popup forgot password', 'suremembers-core' ),
			],
			'login_popup_submit'      => [
				'label' => __( 'Login popup submit button', 'suremembers-core' ),
			],
			'login_limit_exceeded'    => [
				'label' => __( 'Login limit exceeded message', 'suremembers-core' ),
			],
			'login_limit_reset'       => [
				'label' => __( 'Login limit reset message', 'suremembers-core' ),
			],
		];

		foreach ( $defaults as $index => $default ) {
			$default_data[ $index ]['value']   = $values[ $index ] ?? $default;
			$default_data[ $index ]['default'] = $default;
		}

		if ( empty( $key ) ) {
			return $default_data;
		}

		if ( isset( $default_data[ $key ] ) ) {
			return $default_data[ $key ];
		}

		return $default_data;
	}

	/**
	 * Encrypt sensitive values before storing.
	 *
	 * @param string $value Value to encrypt.
	 * @return string Encrypted value.
	 * @since 2.0.0
	 */
	private static function encrypt_value( $value ) {
		if ( empty( $value ) ) {
			return $value;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		return base64_encode( $value );
	}

	/**
	 * Decrypt sensitive values after retrieving.
	 *
	 * @param string $value Value to decrypt.
	 * @return string Decrypted value.
	 * @since 2.0.0
	 */
	private static function decrypt_value( $value ) {
		if ( empty( $value ) ) {
			return $value;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		return base64_decode( $value );
	}

	/**
	 * Decrypt all sensitive keys in settings array.
	 *
	 * @param array<string, mixed> $settings Settings array.
	 * @return array<string, mixed> Settings with decrypted values.
	 * @since 2.0.0
	 */
	private static function decrypt_keys( $settings ) {
		// Add decryption for sensitive keys if needed in future.
		return $settings;
	}
}
