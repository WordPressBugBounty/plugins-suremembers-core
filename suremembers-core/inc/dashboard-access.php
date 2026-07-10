<?php
/**
 * Template Redirect.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Template Redirect
 *
 * @since 1.0.0
 */
class Dashboard_Access {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since  1.0.0
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'decline_admin_access' ], 1 );
		add_filter( 'login_redirect', [ $this, 'redirect_user_on_login' ], 10, 3 );

		if ( ! $this->check_user_role_has_access() ) {
			// Ignored as we have already checked admin roles in above line.
			add_filter( 'show_admin_bar', '__return_false' ); //phpcs:ignore WordPressVIPMinimum.UserExperience.AdminBarRemoval.RemovalDetected
		}
	}

	/**
	 * Check id current user has access to admin area.
	 *
	 * @param object $user User object.
	 *
	 * @since 1.0.0
	 */
	public function check_user_role_has_access( $user = null ) {
		$response = true;

		if ( is_wp_error( $user ) ) {
			return $response;
		}

		if ( is_null( $user ) && ! is_user_logged_in() ) {
			return $response;
		}

		if ( current_user_can( 'administrator' ) ) {
			return $response;
		}

		$get_settings = Settings::get_setting( 'suremembers_admin_settings' );
		$roles        = $get_settings['decline_admin_screen'];
		$roles_array  = array_column( $roles, 'value' );

		$current_user      = ! is_null( $user ) ? $user : wp_get_current_user();
		$current_user_role = ! empty( $current_user->roles ) && is_array( $current_user->roles ) ? $current_user->roles : [];
		$in_exclude_array  = array_intersect( $current_user_role, $roles_array );

		if ( ! empty( $in_exclude_array ) ) {
			$response = false;
		}

		return $response;
	}

	/**
	 * Decline access to admin screen for non authorized users.
	 */
	public function decline_admin_access() {
		if ( wp_doing_ajax() ) {
			return;
		}
		if ( ! $this->check_user_role_has_access() ) {
			wp_safe_redirect( home_url( '404' ) );
			exit;
		}
	}

	/**
	 * Redirect unauthorized users to home on login.
	 *
	 * @param string $redirect_to URL to redirect to.
	 * @param string $request URL the user is coming from.
	 * @param object $user Logged user's data.
	 *
	 * @return string Redirect URL.
	 */
	public function redirect_user_on_login( $redirect_to, $request, $user ) {
		if ( ! $this->check_user_role_has_access( $user ) ) {
			return home_url();
		}

		return $redirect_to;
	}
}
