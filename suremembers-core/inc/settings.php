<?php
/**
 * Settings helpers.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Inc;

defined( 'ABSPATH' ) || exit;

/**
 * Settings helper class.
 *
 * @since 1.0.0
 */
class Settings {
	/**
	 * Get Settings option.
	 *
	 * @return mixed Settings array or JSON string.
	 *
	 * @since 1.0.0
	 */
	public static function get_settings() {
		$response = [];
		foreach ( self::global_defaults() as $key => $default_data ) {
			$response[ $key ] = self::get_setting( $key );
		}
		return apply_filters( 'suremembers_global_settings', $response );
	}

	/**
	 * Get value of setting option
	 *
	 * @param string $key value of global setting.
	 *
	 * @since 1.0.0
	 */
	public static function get_setting( $key ) {
		$db_data         = get_option( $key, [] );
		$global_defaults = self::global_defaults();

		$setting = self::parse_args( $db_data, $global_defaults[ $key ] );
		return ! empty( $setting ) ? $setting : [];
	}

	/**
	 * Merge user defined arguments into defaults array.
	 * Similar to wp_parse_args() just a bit extended to work with multidimensional arrays.
	 *
	 * @param array<string, mixed> $args      (Required) Value to merge with $defaults.
	 * @param array<string, mixed> $defaults  Array that serves as the defaults. Default value: ''.
	 *
	 * @return array<string, mixed> Array of parsed values.
	 *
	 * @since 1.5.0
	 */
	public static function parse_args( array &$args, $defaults = [] ) {
		$args     = (array) $args;
		$defaults = (array) $defaults;
		$result   = $defaults;

		foreach ( $args as $key => &$value ) {
			if ( is_array( $value ) && ! empty( $value ) && isset( $result[ $key ] ) ) {
				$result[ $key ] = self::parse_args( $value, $result[ $key ] );
			} else {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Update setting by key.
	 *
	 * @param string               $key Option key to update.
	 * @param array<string, mixed> $data Array of options data to update.
	 *
	 * @since 1.0.0
	 */
	public static function update_setting( $key, $data ) {
		update_option( $key, $data );
	}

	/**
	 * Returns data for custom content tab
	 *
	 * @param string $key Option key to retrieve values.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.4.0
	 */
	public static function get_custom_content_data( $key = '' ) {
		$values          = self::get_setting( SUREMEMBERS_CUSTOM_CONTENT );
		$global_defaults = self::global_defaults();
		$defaults        = $global_defaults[ SUREMEMBERS_CUSTOM_CONTENT ];
		$default_data    = [
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
	 * Returns default value for global settings.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.4.0
	 */
	private static function global_defaults() {
		return [
			'suremembers_admin_settings'              => [
				'enable_gutenberg_icon'     => true,
				'decline_admin_screen'      => [],
				'registration_access_group' => [],
				'enable_search_restriction' => false,
				'hide_woocommerce_coupon'   => false,
				'usage_tracking'            => get_option( 'suremembers_usage_optin', 'no' ) === 'yes',
			],
			'suremembers_redirect_rules'              => [
				'login_redirect'  => '',
				'logout_redirect' => '',
			],
			'suremembers_custom_content'              => [
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
			'suremembers_login_form_settings'         => [
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
			'suremembers_email_template_settings'     => [
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
			'suremembers_login_restrictions_settings' => [],
			'suremembers_webhook_endpoints'           => [],
		];
	}
}
