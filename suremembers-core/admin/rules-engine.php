<?php
/**
 * Rules Engine.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Admin;

use SureMembersCore\Inc\Access;
use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Restricted;
use SureMembersCore\Inc\Settings;
use SureMembersCore\Inc\Traits\Get_Instance;
use SureMembersCore\Inc\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Rules Engine
 *
 * @since 0.0.1
 */
class Rules_Engine {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since  0.0.1
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_suremembers_post_type' ] );
		add_action( 'wp_ajax_nopriv_suremembers_user_log', [ $this, 'suremembers_user_login' ] );
		add_action( 'wp_ajax_suremembers_user_logout', [ $this, 'suremembers_user_logout' ] );
		add_filter( 'posts_results', [ $this, 'search_result' ], 10, 1 );
		add_action( 'wp_logout', [ $this, 'update_logout_url' ], 1, 1 );
		add_action( 'register_new_user', [ $this, 'grant_access_group' ] );
		add_action( 'user_register', [ $this, 'grant_access_group' ] );

		add_filter( 'login_redirect', [ $this, 'update_login_url' ], 9999999, 3 );
	}

	/**
	 * Registers custom post 'suremembers_access_groups'
	 *
	 * @since 1.0.0
	 */
	public function register_suremembers_post_type() {
		$labels = [
			'name'               => esc_html_x( 'Memberships', 'plan general name', 'suremembers-core' ),
			'menu_name'          => esc_html_x( 'SureMembers', 'plan general name', 'suremembers-core' ),
			'singular_name'      => esc_html_x( 'Membership', 'plan singular name', 'suremembers-core' ),
			'search_items'       => esc_html__( 'Search Memberships', 'suremembers-core' ),
			'all_items'          => esc_html__( 'Memberships', 'suremembers-core' ),
			'edit_item'          => esc_html__( 'Edit Membership', 'suremembers-core' ),
			'view_item'          => esc_html__( 'View Membership', 'suremembers-core' ),
			'add_new'            => esc_html__( 'Add New', 'suremembers-core' ),
			'update_item'        => esc_html__( 'Update Membership', 'suremembers-core' ),
			'add_new_item'       => esc_html__( 'Add New', 'suremembers-core' ),
			'new_item_name'      => esc_html__( 'New Membership Name', 'suremembers-core' ),
			'not_found'          => esc_html__( 'No Membership found', 'suremembers-core' ),
			'not_found_in_trash' => esc_html__( 'No Membership found', 'suremembers-core' ),
		];
		// Ignored in favor of functionality.
		$logo = file_get_contents( SUREMEMBERS_CORE_DIR . 'admin/assets/images/admin-icon.svg' ); //phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$args = [
			'labels'              => $labels,
			'show_in_menu'        => true,
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'query_var'           => true,
			'can_export'          => true,
			'show_in_admin_bar'   => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'supports'            => [ 'title', 'thumbnail', 'slug' ],
			'capability_type'     => 'post',
			'menu_position'       => 31,
		];

		if ( $logo ) {
			// Ignored in the favor of functionality.
			$args['menu_icon'] = 'data:image/svg+xml;base64,' . base64_encode( $logo ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		register_post_type( SUREMEMBERS_POST_TYPE, $args );

		$this->register_suremembers_post_status();
	}
	/**
	 * User login action.
	 *
	 * @since 1.1.0
	 */
	public function suremembers_user_login() {
		if ( ! isset( $_POST['login-nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['login-nonce'] ), 'suremembers_user_login' ) ) {
			$message = [
				'message' => __( 'Something wrong is happening..', 'suremembers-core' ),
			];
			wp_send_json_error( [ 'result' => $message ] );
		}

		// Check if Simple Turnstile plugin is active.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$simple_turnstile_active = is_plugin_active( 'simple-cloudflare-turnstile/simple-cloudflare-turnstile.php' );

		// Only verify our Turnstile if Simple Turnstile is NOT active.
		$login_page_settings = Settings::get_setting( SUREMEMBERS_LOGIN_FORM_SETTINGS );
		if ( isset( $login_page_settings['enable_turnstile'] ) && $login_page_settings['enable_turnstile'] && ! $simple_turnstile_active ) {
			$secret_key = $login_page_settings['turnstile_secret_key'] ?? '';

			if ( ! empty( $secret_key ) ) {
				$turnstile_response = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( $_POST['cf-turnstile-response'] ) : '';

				if ( empty( $turnstile_response ) ) {
					$message['message'] = __( 'Please complete the Turnstile verification.', 'suremembers-core' );
					wp_send_json_error( [ 'result' => $message ] );
				}

				// Verify with Cloudflare.
				$remote_ip = '';
                // phpcs:disable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__
				if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
					$remote_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
					// Validate IP address format.
					if ( ! filter_var( $remote_ip, FILTER_VALIDATE_IP ) ) {
						$remote_ip = '';
					}
				}
                // phpcs:enable WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__

				$response = wp_remote_post(
					'https://challenges.cloudflare.com/turnstile/v0/siteverify',
					[
						'body' => [
							'secret'   => $secret_key,
							'response' => $turnstile_response,
							'remoteip' => $remote_ip,
						],
					]
				);

				if ( is_wp_error( $response ) ) {
					$message['message'] = __( 'Unable to verify Turnstile. Please try again.', 'suremembers-core' );
					wp_send_json_error( [ 'result' => $message ] );
				}

				$result = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( ! isset( $result['success'] ) || ! $result['success'] ) {
					$message['message'] = __( 'Turnstile verification failed. Please try again.', 'suremembers-core' );
					wp_send_json_error( [ 'result' => $message ] );
				}
			}
		}

		// When empty username or pwd.
		if ( empty( $_POST['pwd'] ) || empty( $_POST['user_name'] ) ) {
			$message['message'] = __( 'Something wrong is happening..', 'suremembers-core' );
			if ( empty( $_POST['pwd'] ) ) {
				$message['pwd'] = __( 'The password field is empty.', 'suremembers-core' );
			}
			if ( empty( $_POST['user_name'] ) ) {
				$message['user_name'] = __( 'The username field is empty.', 'suremembers-core' );
			}
			wp_send_json_error( [ 'result' => $message ] );
		}

		$username = sanitize_text_field( $_POST['user_name'] );
		// Passwords must not be passed through sanitize_text_field(); doing so strips valid characters (whitespace, control chars). wp_unslash() is sufficient before handing to wp_signon().
		$password   = isset( $_POST['pwd'] ) ? wp_unslash( $_POST['pwd'] ) : ''; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$rememberme = ! empty( $_POST['rememberme'] ) ? sanitize_text_field( $_POST['rememberme'] ) : '';

		$user_data = wp_signon(
			[
				'user_login'    => $username,
				'user_password' => $password,
				'remember'      => $rememberme === 'forever' ? true : false,
			],
			is_ssl()
		);

		if ( is_wp_error( $user_data ) ) {
			$message = '';
			if ( isset( $user_data->errors['invalid_email'][0] ) || isset( $user_data->errors['invalid_username'][0] ) ) {
				// translators: %s will be entered user's name.
				$message = sprintf( __( 'The username <strong>%s</strong> is not registered on this site. If you are unsure of your username, try your email address instead.', 'suremembers-core' ), $username );
			} elseif ( isset( $user_data->errors['incorrect_password'][0] ) ) {
				// translators: %s will be entered user's name.
				$message = sprintf( __( 'The password you entered for the username <strong>%s</strong> is incorrect.', 'suremembers-core' ), $username );
			} else {
				$message = $user_data->get_error_message();
			}
			wp_send_json_error( [ 'result' => [ 'message' => $message ] ] );
		} else {
			wp_set_current_user( $user_data->ID, $username );
			do_action( 'wp_login', $user_data->user_login, $user_data );
			wp_send_json_success();
		}
	}

	/**
	 * User Logout action.
	 *
	 * @since 1.9.4
	 */
	public function suremembers_user_logout() {
		// Verify the nonce for security purpose.
		if ( ! isset( $_POST['logout_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['logout_nonce'] ), 'suremembers_user_logout' ) ) {
			$message = [
				'message' => __( 'Something wrong is happening..', 'suremembers-core' ),
			];
			wp_send_json_error( [ 'result' => $message ] );
		}

		$is_logout_success = wp_logout();

		if ( is_wp_error( $is_logout_success ) ) {
			wp_send_json_error( [ 'result' => [ 'message' => __( 'Something went wrong. Reload the page and try again.', 'suremembers-core' ) ] ] );
		} else {
			wp_send_json_success();
		}
	}

	/**
	 * Returns filtered posts for search results
	 *
	 * @param array<string, mixed> $posts list of posts.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.2.0
	 */
	public function search_result( $posts ) {
		if ( function_exists( 'is_user_logged_in' ) ) {
			// un-restricting everything for site admins.
			if ( is_user_logged_in() && current_user_can( 'administrator' ) ) {
				return $posts;
			}
		}

		if ( apply_filters( 'suremembers_restrict_post_in_loop', $this->should_restrict_post_in_loop() ) ) {
			return $this->unrestricted_posts( $posts );
		}

		return $posts;
	}

	/**
	 * Should restrict the post content in loop
	 *
	 * @since 1.5.0
	 */
	public function should_restrict_post_in_loop() {
		$admin_settings = Settings::get_setting( SUREMEMBERS_ADMIN_SETTINGS );
		if ( ! empty( $admin_settings['enable_search_restriction'] ) && is_search() ) {
			return true;
		}
		return false;
	}

	/**
	 * Returns unrestricted posts for current user.
	 *
	 * @param array<string, mixed> $posts list of posts.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.2.0
	 */
	public function unrestricted_posts( $posts ) {
		if ( empty( $posts ) || ! is_array( $posts ) ) {
			return $posts;
		}

		$response = [];

		foreach ( $posts as $post ) {
			$option = [
				'include'           => SUREMEMBERS_PLAN_INCLUDE,
				'exclusion'         => SUREMEMBERS_PLAN_EXCLUDE,
				'priority'          => SUREMEMBERS_PLAN_PRIORITY,
				'current_post_id'   => absint( $post->ID ),
				'current_post_type' => $post->post_type,
				'current_page_type' => 'is_singular',
			];

			$access_groups = Restricted::by_access_groups( SUREMEMBERS_POST_TYPE, $option );
			if ( empty( $access_groups ) || empty( $access_groups[ SUREMEMBERS_POST_TYPE ] ) ) {
				$response[] = $post;
				continue;
			}

			$check_user_has_access = Access_Groups::check_if_user_has_access( array_keys( $access_groups[ SUREMEMBERS_POST_TYPE ] ) );

			if ( ! $check_user_has_access ) {
				if ( apply_filters( 'suremembers_show_restricted_post_in_loop', true ) ) {
					$loop_content       = Settings::get_custom_content_data( 'loop_content' );
					$post->post_content = ! empty( $loop_content['value'] ) ? sanitize_text_field( $loop_content['value'] ) : $loop_content['default'];
					$post->post_excerpt = '';

					$response[] = $post;
				}
			} else {
				$response[] = $post;
			}
		}

		return $response;
	}

	/**
	 * Redirect to login url
	 *
	 * @param string $login_url current login url.
	 * @param string $requested_to requested to url.
	 * @param object $user logged in user.
	 *
	 * @since 1.3.0
	 */
	public function update_login_url( $login_url, $requested_to, $user ) {
		if ( ! is_wp_error( $user ) ) {
			if ( ! empty( $user->roles ) && is_array( $user->roles ) && in_array( 'administrator', $user->roles, true ) ) {
				return admin_url();
			}
		}

		$rules = Settings::get_setting( SUREMEMBERS_REDIRECT_RULES );
		if ( ! empty( $rules['login_redirect'] ) && apply_filters( 'suremembers_restrict_login_redirect', true, $requested_to ) ) {
			$login_url = esc_url( $rules['login_redirect'] );
		}

		return esc_url( apply_filters( 'suremembers_restrict_login_redirect_url', $login_url, $requested_to ) );
	}

	/**
	 * Updates logout url
	 *
	 * @param int $user_id logged out user id.
	 *
	 * @since 1.3.0
	 */
	public function update_logout_url( $user_id ) {
		$user = get_user_by( 'ID', $user_id );
		if ( ! empty( $user->roles ) && is_array( $user->roles ) && in_array( 'administrator', $user->roles, true ) ) {
			return;
		}
		$rules = Settings::get_setting( SUREMEMBERS_REDIRECT_RULES );
		if ( ! empty( $rules['logout_redirect'] ) ) {
			$logout_url = esc_url( $rules['logout_redirect'] );
			// Ignored in the favor of functionality as we might get data from user that mighnt not pass through wp_safe_redirect.
			wp_redirect( $logout_url ); //phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
	}

	/**
	 * Adds assigned roles to new registered member
	 *
	 * @param int $user_id new registered user id.
	 *
	 * @since 1.4.0
	 */
	public function grant_access_group( $user_id ) {
		if ( empty( $user_id ) ) {
			return;
		}

		$settings = Settings::get_setting( SUREMEMBERS_ADMIN_SETTINGS );
		if ( empty( $settings['registration_access_group'] ) ) {
			return;
		}

		$access_group_ids = Utils::sanitize_recursively( 'absint', $settings['registration_access_group'] );
		if ( empty( $access_group_ids ) ) {
			return;
		}

		Access::grant( $user_id, $access_group_ids );
	}

	/**
	 * Registers a custom post status for archived suremembers posts
	 *
	 * @since 1.0.0
	 */
	private function register_suremembers_post_status() {
		$args = [
			'label'                     => esc_html_x( 'Archive', 'suremembers archive post label', 'suremembers-core' ),
			/* Translators: post count*/
			'label_count'               => _n_noop( 'Archive <span class="count">(%s)</span>', 'Archive <span class="count">(%s)</span>', 'suremembers-core' ),
			'public'                    => true,
			'show_in_admin_status_list' => true,

		];

		register_post_status( SUREMEMBERS_ARCHIVE, $args );
	}
}
