<?php
/**
 * Login Redirect.
 *
 * @package suremembers
 *
 * @since 1.9.0
 */

namespace SureMembersCore\Admin;

use SureMembersCore\Inc\Settings;
use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Login Redirect
 *
 * @since 1.9.0
 */
class Login_Redirect {
	use Get_Instance;

	/**
	 * Flag indicating whether wp-login.php is being used.
	 *
	 * @var bool
	 * @since 1.9.0
	 */
	private $wp_login_php = false;

	/**
	 * Constructor
	 *
	 * @since 1.9.0
	 */
	public function __construct() {
		$rules = Settings::get_setting( 'suremembers_login_form_settings' );

		if ( empty( $rules ) ) {
			return;
		}

		if ( isset( $rules['enable_login_url'] ) && $rules['enable_login_url'] ) {
			add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ], 1 );
			add_action( 'wp_loaded', [ $this, 'wp_loaded' ] );
			add_filter( 'site_url', [ $this, 'site_url' ], 10, 4 );
			add_filter( 'network_site_url', [ $this, 'network_site_url' ], 10, 3 );
			add_filter( 'wp_redirect', [ $this, 'wp_redirect' ], 10, 1 );
		}
	}

	/**
	 * Generate the new login URL
	 *
	 * @param string|null $url The URL scheme.
	 *
	 * @return string Modified login URL.
	 *
	 * @since 1.9.0
	 */
	public function sm_login_redirect( $url = null ) {
		if ( get_option( 'permalink_structure' ) ) {
			return $this->check_permalink_format( home_url( '/', $url ) . $this->sm_login_slug() );
		}
		return home_url( '/', $url ) . '?' . $this->sm_login_slug();
	}

	/**
	 * Handle the plugins_loaded action.
	 *
	 * @return void Nothing.
	 *
	 * @since 1.9.0
	 */
	public function plugins_loaded() {
		global $pagenow;

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( ! is_multisite() && ( strpos( rawurldecode( $request_uri ), 'wp-signup' ) !== false || strpos( rawurldecode( $request_uri ), 'wp-activate' ) !== false ) ) {
			wp_die( esc_html__( 'This functionality has been deactivated due to the URL redirection settings of SureMembers.', 'suremembers-core' ) );
		}

		$request = wp_parse_url( rawurldecode( $request_uri ) );

		if ( ( strpos( rawurldecode( $request_uri ), 'wp-login.php' ) !== false || ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-login', 'relative' ) ) ) && ! is_admin() ) {
			$this->wp_login_php = true;

			$_SERVER['REQUEST_URI'] = $this->check_permalink_format( '/' . str_repeat( '-/', 10 ) );

			$pagenow = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		} elseif ( ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === home_url( $this->sm_login_slug(), 'relative' ) ) || ( ! get_option( 'permalink_structure' ) && isset( $_GET[ $this->sm_login_slug() ] ) && empty( $_GET[ $this->sm_login_slug() ] ) ) ) {  // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$pagenow = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

			$this->wp_login_php = false;
		} elseif ( ( strpos( rawurldecode( $request_uri ), 'wp-register.php' ) !== false || ( isset( $request['path'] ) && untrailingslashit( $request['path'] ) === site_url( 'wp-register', 'relative' ) ) ) && ! is_admin() ) {
			$this->wp_login_php = true;

			$_SERVER['REQUEST_URI'] = $this->check_permalink_format( '/' . str_repeat( '-/', 10 ) );

			$pagenow = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}
	}

	/**
	 * Handle the wp_loaded action.
	 *
	 * @return void Nothing.
	 *
	 * @since 1.9.0
	 */
	public function wp_loaded() {
		global $pagenow;

		$request = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : '';

		if ( $this->allow_login_redirect_customization( $pagenow, $request ) ) {
			$rules = Settings::get_setting( 'suremembers_login_form_settings' );

			if ( isset( $rules['login_redirect_url'] ) ) {
				wp_safe_redirect( '/' . $rules['login_redirect_url'] );
				exit;
			}
			wp_safe_redirect( '/' );
			exit;
		}

		if ( $pagenow === 'wp-login.php' && isset( $request['path'] ) && $this->check_permalink_format( $request['path'] ) !== $request['path'] && get_option( 'permalink_structure' ) && $this->wp_login_php ) {
			wp_safe_redirect( home_url( '/404' ) );
			die;
		}
		if ( $this->wp_login_php ) {
			$referer = wp_get_referer();
			if ( $referer && strpos( $referer, 'wp-activate.php' ) !== false ) {
				$referer = wp_parse_url( $referer );

				if ( ! empty( $referer['query'] ) ) {
					parse_str( $referer['query'], $referer_query );

					if ( ! empty( $referer_query['key'] ) ) {
						// Ensure key is a string (parse_str can create arrays for duplicate keys).
						$activation_key = is_array( $referer_query['key'] ) ? (string) $referer_query['key'][0] : (string) $referer_query['key'];
						$result         = wpmu_activate_signup( $activation_key );

						if ( is_wp_error( $result ) && ( $result->get_error_code() === 'already_active' || $result->get_error_code() === 'blog_taken' ) ) {
							$query_string = ! empty( $_SERVER['QUERY_STRING'] ) ? '?' . esc_url_raw( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
							wp_safe_redirect( $this->sm_login_redirect() . $query_string );
							exit;
						}
					}
				}
			}
			$this->wp_template_loader();
		} elseif ( $pagenow === 'wp-login.php' ) {
			global $error, $interim_login, $action, $user_login;

			$login_file = ABSPATH . 'wp-login.php';

			if ( file_exists( $login_file ) ) {
				require_once $login_file;
			}

			die;
		}
	}

	/**
	 * Filters the site URL to apply login redirection if needed.
	 *
	 * @param string      $url      The complete URL including scheme and path.
	 * @param string      $path     The requested path after the site URL.
	 * @param string|null $scheme   The scheme to use in the URL.
	 * @param int|null    $blog_id  The site's unique ID.
	 *
	 * @return string The modified URL.
	 *
	 * @since 1.9.0
	 */
	public function site_url( $url, $path, $scheme, $blog_id ) {
		return $this->filter_wp_login_php( $url, $scheme );
	}

	/**
	 * Filters the network site URL to apply login redirection if needed.
	 *
	 * @param string      $url      The complete URL including scheme and path.
	 * @param string      $path     The requested path after the site URL.
	 * @param string|null $scheme   The scheme to use in the URL.
	 *
	 * @return string The modified URL.
	 *
	 * @since 1.9.0
	 */
	public function network_site_url( $url, $path, $scheme ) {
		return $this->filter_wp_login_php( $url, $scheme );
	}

	/**
	 * Filters the wp_redirect function to apply login redirection if needed.
	 *
	 * @param string $location The URL to redirect to.
	 *
	 * @return string The modified URL.
	 *
	 * @since 1.9.0
	 */
	public function wp_redirect( $location ) { // phpcs:ignore WordPressVIPMinimum.Security.ExitAfterRedirect.NoExitInConditional -- Method returns string, exit handled by caller.
		/**
		 * Commenting below code as it is not require and will result in false always.
		 *
		 * If ( strpos( $location, 'https://wordpress.com/wp-login.php' ) !== false ) {
		 *  return $location;
		 * }
		 */

		return $this->filter_wp_login_php( $location );
	}

	/**
	 * Applies login redirection to a given URL if needed.
	 *
	 * @param string      $url      The URL to potentially modify.
	 * @param string|null $scheme   The scheme to use in the URL.
	 *
	 * @return string The modified URL.
	 *
	 * @since 1.9.0
	 */
	public function filter_wp_login_php( $url, $scheme = null ) {
		if ( strpos( $url, 'wp-login.php?action=postpass' ) !== false ) {
			return $url;
		}

		// If Google Site Kit is active then skip the http referer check, it exhausts memory.
		if ( defined( 'GOOGLESITEKIT_VERSION' ) ) {
			$http_referer = '';
		} else {
			$http_referer = wp_get_referer();
			$http_referer = is_string( $http_referer ) ? $http_referer : '';
		}

		if ( strpos( $url, 'wp-login.php' ) !== false && strpos( $http_referer, 'wp-login.php' ) === false ) {
			if ( is_ssl() ) {
				$scheme = 'https';
			}

			$args = explode( '?', $url );

			if ( isset( $args[1] ) ) {
				wp_parse_str( $args[1], $args );

				if ( isset( $args['login'] ) ) {
					$args['login'] = rawurlencode( $args['login'] );
				}

				$url = add_query_arg( $args, $this->sm_login_redirect( $scheme ) );
			} else {
				$url = $this->sm_login_redirect( $scheme );
			}
		}

		return $url;
	}

	/**
	 * Checks all possible conditions to see if we should redirect to the new login page instead of original wp-admin login page.
	 *
	 * @param string      $pagenow  The current page ID/URL/Name.
	 * @param array|mixed $request  The array of the request. It could be ajax or webhook.
	 */
	public function allow_login_redirect_customization( $pagenow, $request ) {
		$request_path = is_array( $request ) && ! empty( $request['path'] ) ? sanitize_text_field( $request['path'] ) : '';

		if ( is_admin() && ! is_user_logged_in() && ! defined( 'DOING_AJAX' ) && ! defined( 'DOING_CRON' ) && $pagenow !== 'admin-post.php' && $request_path !== '/wp-admin/options.php' ) {
			return true;
		}

		return false;
	}

	/**
	 * Determine whether trailing slashes are used in permalinks
	 *
	 * @since 1.9.0
	 */
	private function slashes_in_permalink() {
		$permalink_structure = get_option( 'permalink_structure' );
		return is_string( $permalink_structure ) && substr( $permalink_structure, -1, 1 ) === '/';
	}

	/**
	 * Add or remove trailing slash based on permalink structure.
	 *
	 * @param string $string The input string.
	 *
	 * @return string Modified string.
	 *
	 * @since 1.9.0
	 */
	private function check_permalink_format( $string ) {
		return $this->slashes_in_permalink() ? trailingslashit( $string ) : untrailingslashit( $string );
	}

	/**
	 * Load the WordPress template.
	 *
	 * @return void Nothing.
	 *
	 * @since 1.9.0
	 */
	private function wp_template_loader() {
		global $pagenow;

		$pagenow = 'index.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		if ( ! defined( 'WP_USE_THEMES' ) ) {
			define( 'WP_USE_THEMES', true );
		}

		wp();

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( $request_uri === $this->check_permalink_format( str_repeat( '-/', 10 ) ) ) {
			$_SERVER['REQUEST_URI'] = $this->check_permalink_format( '/wp-login-php/' );
		}

		require_once ABSPATH . 'wp-includes/template-loader.php';

		die;
	}

	/**
	 * Get the new login slug.
	 *
	 * @since 1.9.0
	 */
	private function sm_login_slug() {
		$rules = Settings::get_setting( 'suremembers_login_form_settings' );
		if ( ! empty( $rules['login_url'] ) ) {
			return $rules['login_url'];
		}
		return 'wp-login-php';
	}
}
