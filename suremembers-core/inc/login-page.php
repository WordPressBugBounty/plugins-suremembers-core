<?php
/**
 * Handles Login page customizations options.
 *
 * @package Suremembers.
 *
 * @since 1.5.0
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Login Page Customizations Class
 */
class Login_Page {
	use Get_Instance;

	/**
	 * Class Constructor.
	 */
	public function __construct() {
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_styles' ] );
		add_filter( 'login_headerurl', [ $this, 'filter_logo_link' ] );

		// Turnstile integration.
		add_action( 'login_form', [ $this, 'add_turnstile_field' ] );

		$login_page_settings = Settings::get_setting( SUREMEMBERS_LOGIN_FORM_SETTINGS );
		if ( isset( $login_page_settings['enable_turnstile'] ) && $login_page_settings['enable_turnstile'] ) {
			// Check if Simple Turnstile plugin is active.
			if ( ! function_exists( 'is_plugin_active' ) ) {
				include_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			// Only enqueue scripts and add verification if Simple Turnstile is NOT active.
			if ( ! is_plugin_active( 'simple-cloudflare-turnstile/simple-cloudflare-turnstile.php' ) ) {
				add_action( 'login_enqueue_scripts', [ $this, 'enqueue_turnstile_scripts' ] );
				add_filter( 'authenticate', [ $this, 'verify_turnstile' ], 30, 3 );
			}
		}
	}

	/**
	 * Enqueue Styles.
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'suremembers-login-page-style', SUREMEMBERS_CORE_URL . 'assets/css/login-page.css', [], SUREMEMBERS_CORE_VER );

		$login_page_settings = Settings::get_setting( SUREMEMBERS_LOGIN_FORM_SETTINGS );
		$logo_image          = isset( $login_page_settings['logo_image'] ) ? esc_url( $login_page_settings['logo_image'] ) : '';
		$custom_css          = '';

		if ( ! empty( $login_page_settings['primary_color'] ) ) {
			$primary_color = esc_attr( $login_page_settings['primary_color'] );
			$custom_css   .= ".wp-core-ui .button-primary, .wp-core-ui .button-primary:hover, .wp-core-ui .button-primary.active, .wp-core-ui .button-primary.active:focus, .wp-core-ui .button-primary.active:hover, .wp-core-ui .button-primary:active {
				background: #{$primary_color};
				border-color: #{$primary_color};
			}
			input[type=checkbox]:focus, input[type=color]:focus, input[type=date]:focus, input[type=datetime-local]:focus, input[type=datetime]:focus, input[type=email]:focus, input[type=month]:focus, input[type=number]:focus, input[type=password]:focus, input[type=radio]:focus, input[type=search]:focus, input[type=tel]:focus, input[type=text]:focus, input[type=time]:focus, input[type=url]:focus, input[type=week]:focus, select:focus, textarea:focus {
				border-color: #{$primary_color};
				box-shadow: 0 0 0 1px #{$primary_color};
			}";

			$custom_css .= "body.login a:hover, .login #nav a:hover, .login #backtoblog a:hover {
				color: #{$primary_color};
			}
			.login .message {
				border-left: 4px solid #{$primary_color};
			}";
		}

		if ( ! empty( $login_page_settings['secondary_color'] ) ) {
			$secondary_color = esc_attr( $login_page_settings['secondary_color'] );
			$svg_check       = '<svg xmlns="http://www.w3.org/2000/svg" fill="#' . esc_attr( $secondary_color ) . '" viewBox="0 0 20 20"><path d="M14.83 4.89l1.34.94-5.81 8.38H9.02L5.78 9.67l1.34-1.25 2.57 2.4z" fill="P0000c4"/></svg>';
			$svg_check_url   = 'data:image/svg+xml;base64,' . base64_encode( $svg_check ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

			$custom_css .= ".wp-core-ui .button-secondary {
				color: #{$secondary_color};
				border-color: #{$secondary_color};
			}
			.wp-core-ui .button-secondary:hover, .wp-core-ui .button:focus {
				color: #{$secondary_color};
			}
			.login .button.wp-hide-pw:focus {
				border-color: #{$secondary_color};
				box-shadow: 0 0 0 1px #{$secondary_color};
			}
			input[type=checkbox]:focus {
				border-color: #{$secondary_color};
				box-shadow: 0 0 0 1px #{$secondary_color};
			}
			input[type=checkbox]:checked::before {
				content: url('{$svg_check_url}')
			}";
		}

		if ( ! empty( $login_page_settings['text_color'] ) ) {
			$text_color  = esc_attr( $login_page_settings['text_color'] );
			$custom_css .= "body.login {
				color: #{$text_color};
			}";
		}

		if ( ! empty( $login_page_settings['link_color'] ) ) {
			$link_color  = esc_attr( $login_page_settings['link_color'] );
			$custom_css .= "body.login a, .login #nav a, .login #backtoblog a {
				color: #{$link_color};
			}";
		}

		if ( $login_page_settings['disable_logo'] ) {
			$custom_css .= '.login h1 {
				display: none;
			}';
		}

		if ( $login_page_settings['enable_transparent_form'] ) {
			$custom_css .= '.login form {
				background: transparent;
				border: transparent;
				box-shadow: none;
			}';
		} else {
			if ( ! empty( $login_page_settings['login_form_background'] ) ) {
				$login_form_background = esc_attr( $login_page_settings['login_form_background'] );
				$custom_css           .= ".login form {
					background: #{$login_form_background};
				}";
			}

			if ( ! empty( $login_page_settings['login_form_border'] ) ) {
				$login_form_border = esc_attr( $login_page_settings['login_form_border'] );
				$custom_css       .= ".login form {
					border: 1px solid #{$login_form_border};
				}";
			}
		}

		if ( $login_page_settings['custom_logo'] ) {
			if ( ! empty( $logo_image ) ) {
				$custom_css .= ".login h1 a, .login .wp-login-logo a {
					background-image: url({$logo_image});
					background-size: contain;
				}";

				if ( $login_page_settings['logo_width'] ) {
					$logo_width  = esc_attr( $login_page_settings['logo_width'] );
					$custom_css .= ".login h1 a, .login .wp-login-logo a {
						width: {$logo_width}px;
					}";
				}

				if ( $login_page_settings['logo_height'] ) {
					$logo_height = esc_attr( $login_page_settings['logo_height'] );
					$custom_css .= ".login h1 a, .login .wp-login-logo a {
						height: {$logo_height}px;
					}";
				}
			}
		}

		if ( ! empty( $login_page_settings['background_color'] ) ) {
			$bg_color    = esc_attr( $login_page_settings['background_color'] );
			$custom_css .= "body.login {
				background: #{$bg_color};
			}";
		}

		if ( $login_page_settings['enable_background_image'] ) {
			if ( ! empty( $login_page_settings['background_image'] ) ) {
				$bg_image    = esc_url( $login_page_settings['background_image'] );
				$bg_repeat   = esc_attr( $login_page_settings['background_repeat'] );
				$bg_position = esc_attr( $login_page_settings['background_position'] );
				$bg_position = str_replace( '-', ' ', $bg_position );
				$bg_size     = esc_attr( $login_page_settings['background_size'] );

				$custom_css .= "body.login {
					background: url('{$bg_image}');
					background-repeat: {$bg_repeat};
					background-position: {$bg_position};
					background-size: {$bg_size};
				}";
			}
		}

		// Add Turnstile styles.
		if ( isset( $login_page_settings['enable_turnstile'] ) && $login_page_settings['enable_turnstile'] ) {
			$custom_css .= '
			/* Expand login form width to accommodate Turnstile. */
			.login form .turnstile-widget {
				margin-left: -15px;
				margin-bottom: 16px;
			}
			';
		}

		wp_add_inline_style( 'suremembers-login-page-style', $custom_css );
	}

	/**
	 * Filter Login page logo link.
	 *
	 * @param string $link Default URL.
	 *
	 * @return string Modified URL.
	 *
	 * @since 1.5.1
	 */
	public function filter_logo_link( $link ) {
		$login_page_settings = Settings::get_setting( SUREMEMBERS_LOGIN_FORM_SETTINGS );
		return $login_page_settings['custom_logo'] ? esc_url( home_url( '/' ) ) : $link;
	}

	/**
	 * Enqueue Turnstile scripts.
	 *
	 * @since 1.10.11
	 */
	public function enqueue_turnstile_scripts() {
		wp_enqueue_script( 'cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=onTurnstileLoad', [], SUREMEMBERS_CORE_VER, true );

		// Add inline script to handle explicit rendering.
		wp_add_inline_script(
			'cloudflare-turnstile',
			'
			window.onTurnstileLoad = function() {
				var turnstileElements = document.querySelectorAll(".turnstile-widget");
				turnstileElements.forEach(function(element) {
					if (element.dataset.sitekey) {
						element.innerHTML = "";
						turnstile.render(element, {
							sitekey: element.dataset.sitekey,
							theme: element.dataset.theme || "light"
						});
					}
				});
			};
		',
			'before'
		);
	}

	/**
	 * Add Turnstile field to login form.
	 *
	 * @since 1.10.11
	 */
	public function add_turnstile_field() {
		$login_page_settings = Settings::get_setting( SUREMEMBERS_LOGIN_FORM_SETTINGS );
		$is_enabled          = isset( $login_page_settings['enable_turnstile'] ) && $login_page_settings['enable_turnstile'];
		$site_key            = isset( $login_page_settings['turnstile_site_key'] ) ? esc_attr( $login_page_settings['turnstile_site_key'] ) : '';
		$theme               = isset( $login_page_settings['turnstile_theme'] ) ? esc_attr( $login_page_settings['turnstile_theme'] ) : 'auto';

		// Check if Simple Turnstile plugin is active.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$simple_turnstile_active = is_plugin_active( 'simple-cloudflare-turnstile/simple-cloudflare-turnstile.php' );

		// Only add our widget if Simple Turnstile is NOT active.
		if ( $is_enabled && ! empty( $site_key ) && ! $simple_turnstile_active ) {
			?>
			<input type="hidden" name="suremembers-login-form" value="1" />
			<div class="turnstile-widget" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-theme="<?php echo esc_attr( $theme ); ?>"></div>
			<?php
		}
	}

	/**
	 * Verify Turnstile response.
	 *
	 * @param \WP_User|\WP_Error|null $user     WP_User if the user is authenticated. WP_Error or null otherwise.
	 * @param string                  $username Username or email address.
	 * @param string                  $password User password.
	 *
	 * @since 1.10.11
	 */
	public function verify_turnstile( $user, $username, $password ) {
		// Check request coming from the login form.
		if ( ! absint( $_POST['suremembers-login-form'] ?? 0 ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This runs on wp_authenticate filter; WP login form doesn't provide a nonce at this stage.
			return $user;
		}

		// Don't verify if already failed.
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		// Check if Simple Turnstile plugin is active - if so, let it handle verification.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( 'simple-cloudflare-turnstile/simple-cloudflare-turnstile.php' ) ) {
			return $user;
		}

		// Don't verify for empty username/password.
		if ( empty( $username ) || empty( $password ) ) {
			return $user;
		}

		$login_page_settings = Settings::get_setting( SUREMEMBERS_LOGIN_FORM_SETTINGS );
		$secret_key          = $login_page_settings['turnstile_secret_key'] ?? '';

		if ( empty( $secret_key ) ) {
			return $user;
		}

		// Get Turnstile response.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is a WordPress login filter, nonce is handled by core.
		$turnstile_response = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( $_POST['cf-turnstile-response'] ) : '';

		if ( empty( $turnstile_response ) ) {
			return new \WP_Error( 'turnstile_error', __( 'Please complete the Turnstile verification.', 'suremembers-core' ) );
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
			return new \WP_Error( 'turnstile_error', __( 'Unable to verify Turnstile. Please try again.', 'suremembers-core' ) );
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $result['success'] ) || ! $result['success'] ) {
			return new \WP_Error( 'turnstile_error', __( 'Turnstile verification failed. Please try again.', 'suremembers-core' ) );
		}

		return $user;
	}
}
