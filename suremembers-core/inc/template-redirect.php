<?php
/**
 * Template Redirect.
 *
 * @package suremembers
 * @since 1.0.0
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Template Redirect
 *
 * @since 0.0.1
 */
class Template_Redirect {
	use Get_Instance;

	/**
	 * Stores restricted data
	 *
	 * @var array<string, mixed>
	 * @since 1.2.0
	 */
	public $restriction_data = [];

	/**
	 * Stores drip string
	 *
	 * @var string
	 * @since 1.4.0
	 */
	public $drip_string = '';

	/**
	 * Constructor
	 *
	 * @since  0.0.1
	 */
	public function __construct() {
		add_action( 'template_redirect', [ $this, 'processed_content' ], 999 ); // Priority modified to execute at the very last.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'suremembers_before_check_user_access', [ $this, 'handle_access_group_expiration' ], 10, 2 );
	}

	/**
	 * Filter access groups rules and redirect if need.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function processed_content() {
		// un-restricting everything for site admins.
		if ( is_user_logged_in() && current_user_can( 'administrator' ) ) {
			return;
		}

		// un-restricting for elementor edit post.
		if ( isset( $_GET['elementor-preview'] ) && current_user_can( 'edit_posts' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// breaks redirection loop.
		if ( ! empty( $_COOKIE['suremembers_timestamp'] ) ) {
			$time = sanitize_text_field( $_COOKIE['suremembers_timestamp'] ); //phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			// $urls is a map of redirect_url => repeat count for this 10s window
			// ( first hit is stored as 0, each repeat adds 1 ).
			$urls = get_transient( 'suremembers_redirection_' . $time );

			$current_url = home_url();
			if ( isset( $_SERVER['REQUEST_URI'] ) ) {
				$current_url = home_url( esc_url_raw( $_SERVER['REQUEST_URI'] ) );
			}

			// Count how many redirects have happened in total.
			$total_redirects = is_array( $urls ) ? array_sum( $urls ) + count( $urls ) : 0;

			// Stop the redirect if we hit the same URL again, or if there have been
			// too many redirects overall ( catches loops where the URL keeps changing ).
			if ( ! empty( $urls ) && is_array( $urls ) && (
				$total_redirects >= (int) apply_filters( 'suremembers_max_redirects', 5 )
				|| ( in_array( $current_url, array_keys( $urls ), true ) && $urls[ $current_url ] >= 1 )
			) ) {
				// Break the loop: clear the tracking cookie + transient and bail out
				// so this request renders instead of redirecting again.
				setcookie( 'suremembers_timestamp', '', time() - 60, COOKIEPATH, COOKIE_DOMAIN ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
				delete_transient( 'suremembers_redirection_' . $time );
				return;
			}
		}

		global $user_ID, $post;
		$post_id = isset( $post->ID ) ? intval( $post->ID ) : false;

		/**
		 * Added filter to handle special cases where $post_id is not required.
		 *
		 * @filter suremembers_filter_template_control_requires_post_id
		 * @hooked BuddyBoss/filter_required_post_id
		 * @since 1.8.0
		 */
		$requires_post_id = apply_filters( 'suremembers_filter_template_control_requires_post_id', true );

		if ( $requires_post_id && ! $post_id ) {
			return;
		}

		$post_id = apply_filters( 'suremembers_filter_redirection_post_id', $post_id );

		$this->check_rules_engine( $user_ID, $post_id );
	}

	/**
	 * Check Rules Engine for current content
	 *
	 * @param int $user_id current user id.
	 * @param int $post_id current post id.
	 * @return bool
	 * @since 1.0.0
	 */
	public function check_rules_engine( $user_id, $post_id ) {
		global $post;
		$option = [
			'include'         => SUREMEMBERS_PLAN_INCLUDE,
			'exclusion'       => SUREMEMBERS_PLAN_EXCLUDE,
			'priority'        => SUREMEMBERS_PLAN_PRIORITY,
			'current_post_id' => $post_id,
		];

		$restricting_rules = Restricted::by_access_groups( SUREMEMBERS_POST_TYPE, $option );

		// Fetch the access groups in which the user is already added.
		$user_ags = get_user_meta( $user_id, SUREMEMBERS_USER_META, true );

		// Check user role access.
		if ( empty( $user_ags ) && $this->provide_access_by_user_role( $user_id, $restricting_rules ) ) {
			return true;
		}

		$rule = false;

		if ( empty( $restricting_rules[ SUREMEMBERS_POST_TYPE ] ) ) {
			return true;
		}

		/**
		 * Handle actions before user access is determined.
		 *
		 * @hooked $this->handle_access_group_expiration()
		 * @param int $user_id Current User ID.
		 * @param array<string, mixed> $restricting_rules Current content's restricting rules.
		 * @since 1.6.0
		 */
		do_action( 'suremembers_before_check_user_access', $user_id, $restricting_rules[ SUREMEMBERS_POST_TYPE ] );

		if ( empty( $user_ags ) ) {
			$rule = Access_Groups::get_priority_id( array_keys( $restricting_rules[ SUREMEMBERS_POST_TYPE ] ) );
		} else {
			$rules_keys      = array_keys( $restricting_rules[ SUREMEMBERS_POST_TYPE ] );
			$connecting_rule = is_array( $user_ags ) ? array_intersect( $rules_keys, $user_ags ) : $rules_keys;

			if ( empty( $connecting_rule ) ) {
				$rule = Access_Groups::get_priority_id( $rules_keys );
			} else {
				foreach ( $connecting_rule as $id ) {
					$access_group_detail = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$id}", true );
					$drip_data           = get_post_meta( absint( $id ), SUREMEMBERS_PLAN_DRIPS, true );

					if ( is_array( $access_group_detail ) && $access_group_detail['status'] === 'active' ) {
						// Process drip content via filter (Pro feature).
						if ( ! empty( $drip_data ) && is_array( $drip_data ) ) {
							$delay = $this->verify_user_drip_rules( $drip_data );

							if ( $delay !== true && is_array( $delay ) ) {
								/**
								 * Filter to process drip content restriction.
								 *
								 * Pro module hooks into this to handle drip display logic.
								 *
								 * @param bool|null $result Null to continue, true if drip applied.
								 * @param array $delay Delay data from drip rules.
								 * @param array $access_group_detail User's access group detail.
								 * @param Template_Redirect $this Template_Redirect instance.
								 * @param \WP_Post $post Current post object.
								 *
								 * @since 1.0.0
								 */
								$drip_result = apply_filters(
									'suremembers_process_drip_content',
									null,
									$delay,
									$access_group_detail,
									$post
								);

								if ( $drip_result === true ) {
									return true;
								}
							}
						}

						return true;
					}
				}
				$rule = array_shift( $connecting_rule );
			}
		}

		$action              = ! is_string( $rule ) ? get_post_meta( $rule, SUREMEMBERS_PLAN_RULES, true ) : [];
		$restrict            = is_array( $action ) && isset( $action['restrict'] ) ? $action['restrict'] : [];
		$unauthorized_action = ! empty( $restrict['unauthorized_action'] ) ? $restrict['unauthorized_action'] : '';

		if ( apply_filters( 'suremembers_only_process_redirection', false ) ) {
			if ( $unauthorized_action === 'redirect' ) {
				$redirect_url = Utils::maybe_append_url_params( esc_url( trim( $restrict['redirect_url'] ) ) );
				if ( ! empty( $redirect_url ) ) {
					Utils::stop_infinite_redirect( $redirect_url );
					// Using wp_redirect as redirect URL can be external (admin-configured).
					wp_redirect( $redirect_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
					exit;
				}
			}
			return true;
		}

		switch ( $restrict['unauthorized_action'] ) {
			case 'redirect':
				$redirect_url = Utils::maybe_append_url_params( esc_url( trim( $restrict['redirect_url'] ) ) );
				if ( ! empty( $redirect_url ) ) {
					Utils::stop_infinite_redirect( $redirect_url );
					// Using wp_redirect as redirect URL can be external (admin-configured).
					wp_redirect( $redirect_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
					exit;
				}
				$post->post_content = '<p>' . esc_html__( 'This content is restricted', 'suremembers-core' ) . '</p>';
				add_filter( 'comments_open', '__return_false' );
				add_filter( 'get_comments_number', '__return_false' );
				break;
			case 'preview':
				$this->restriction_data = $restrict;
				if ( $this->redirect_to_custom_template() || empty( $restrict['in_content'] ) ) {
					$this->execute_template_filters();
					return true;
				}
				$post->post_content                   = Restricted::get_unauthorized_message( $restrict );
				$post->suremembers_content_restricted = 1;
				$post->restriction_data               = $restrict;
				$allow_featured_image                 = apply_filters( 'suremembers_allow_restricted_post_featured_image', false );

				if ( ! $allow_featured_image ) {
					add_filter( 'post_thumbnail_id', '__return_false' );
				}
				add_filter( 'comments_open', '__return_false' );
				add_filter( 'get_comments_number', '__return_false' );
				add_filter( 'the_content', [ $this, 'restricted_content' ], 99 );
				break;
			case 'page_post':
				// Extract post ID from the value (format: post-123-|).
				$restrict_page_post_value = $restrict['restrict_page_post'] ?? '';
				if ( ! empty( $restrict_page_post_value ) && preg_match( '/post-(\d+)-/', $restrict_page_post_value, $matches ) ) {
					$page_post_id = intval( $matches[1] );
					$page_post    = get_post( $page_post_id );

					if ( $page_post && $page_post->post_status === 'publish' ) {
						// Check if Elementor is active and the page uses Elementor.
						$use_elementor = false;
						if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) ) {
							$elementor_instance = \Elementor\Plugin::instance();
							if ( $elementor_instance->db->is_built_with_elementor( $page_post_id ) ) {
								$use_elementor = true;
								// Get Elementor content directly.
								$post->post_content = $elementor_instance->frontend->get_builder_content_for_display( $page_post_id );
							}
						}

						// If not using Elementor, use regular content.
						if ( ! $use_elementor ) {
							$post->post_content = $page_post->post_content;

							// Enqueue block assets for Gutenberg/Spectra blocks.
							if ( has_blocks( $page_post->post_content ) ) {
								// Process blocks to enqueue their assets.
								$blocks = parse_blocks( $page_post->post_content );
								$this->enqueue_block_assets( $blocks, $page_post_id );

								// Apply block rendering filters to ensure dynamic blocks work.
								add_filter( 'the_content', 'do_blocks', 9 );

								// Ensure inline styles are included.
								if ( function_exists( 'wp_enqueue_block_style' ) ) {
									wp_enqueue_block_style( '*', [ 'path' => '' ] );
								}
							}
						}

						$post->post_title = $page_post->post_title;

						// Mark as restricted for potential future use.
						$post->suremembers_content_restricted = 1;
						$post->restriction_data               = $restrict;

						// Disable comments for restricted content.
						add_filter( 'comments_open', '__return_false' );
						add_filter( 'get_comments_number', '__return_false' );
					} else {
						$post->post_content = '<p>' . esc_html__( 'This content is restricted', 'suremembers-core' ) . '</p>';
					}
				} else {
					$post->post_content = '<p>' . esc_html__( 'This content is restricted', 'suremembers-core' ) . '</p>';
				}
				add_filter( 'comments_open', '__return_false' );
				add_filter( 'get_comments_number', '__return_false' );
				break;
			default:
				break;
		}

		return true;
	}

	/**
	 * Loads template for restricted content
	 *
	 * @param string $template current template.
	 * @return string|void
	 * @since 1.2.0
	 */
	public function restricted_page_template( $template ) { //phpcs:ignore WordPressVIPMinimum.Hooks.AlwaysReturnInFilter.VoidReturn
		$path = SUREMEMBERS_CORE_DIR . 'inc/restricted-template.php';
		if ( file_exists( $path ) ) {
			load_template( $path, true, $this->restriction_data );
			return;
		}
		return $template;
	}

	/**
	 * Get user drip rules.
	 *
	 * Delegates to Pro module via filter if available.
	 *
	 * @param array|mixed $drips array of drip from user meta.
	 * @return bool|array
	 * @since 1.10.8
	 */
	public function verify_user_drip_rules( $drips ) {
		/**
		 * Filter to verify user drip rules.
		 *
		 * Pro module hooks into this to provide drip verification.
		 *
		 * @param bool|array $result Default result (true = no restriction).
		 * @param array|mixed $drips Drip rules from post meta.
		 *
		 * @since 1.0.0
		 */
		return apply_filters( 'suremembers_verify_user_drip_rules', true, $drips );
	}

	/**
	 * Set drip string for display.
	 *
	 * @param string $drip_string Drip message string.
	 *
	 * @since 1.0.0
	 */
	public function set_drip_string( $drip_string ): void {
		$this->drip_string                     = $drip_string;
		$this->restriction_data['drip_string'] = $drip_string;
	}

	/**
	 * Shows drip time in readable format.
	 *
	 * @param int $time_diff difference in current time and access time.
	 * @return string
	 * @since 1.0.0
	 */
	public function display_readable_time( $time_diff ) {

		$params = [
			__( 'Day', 'suremembers-core' )    => 86400,
			__( 'Hour', 'suremembers-core' )   => 3600,
			__( 'Minute', 'suremembers-core' ) => 60,
		];

		$time_string = '';

		foreach ( $params as $key => $param ) {
			$count      = floor( $time_diff / $param );
			$time_diff %= $param;
			if ( $count ) {
				$time_string .= ' ' . $count . ' ' . $key;
			}
			if ( $count > 1 ) {
				$time_string .= 's';
			}
		}

		return $time_string;
	}

	/**
	 * Login form scripts.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function enqueue_scripts() {

		// Load the script for logged in and logged out user.
		wp_register_script( 'suremembers-front-script', SUREMEMBERS_CORE_URL . 'assets/js/script.js', [ 'jquery' ], SUREMEMBERS_CORE_VER, true );
		wp_localize_script(
			'suremembers-front-script',
			'suremembers_login',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			]
		);

		wp_enqueue_script( 'suremembers-front-script' );

		if ( ! is_user_logged_in() ) {
			wp_enqueue_style( 'suremembers-front-style', SUREMEMBERS_CORE_URL . 'assets/css/style.css', [ 'dashicons' ], SUREMEMBERS_CORE_VER );

			// Enqueue Turnstile script if enabled.
			$login_page_settings = Settings::get_setting( SUREMEMBERS_LOGIN_FORM_SETTINGS );
			if ( isset( $login_page_settings['enable_turnstile'] ) && $login_page_settings['enable_turnstile'] ) {
				// Check if Simple Turnstile plugin is active.
				if ( ! function_exists( 'is_plugin_active' ) ) {
					include_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				// Only enqueue our scripts if Simple Turnstile is NOT active.
				if ( ! is_plugin_active( 'simple-cloudflare-turnstile/simple-cloudflare-turnstile.php' ) ) {
					wp_enqueue_script( 'cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileLoad', [], SUREMEMBERS_CORE_VER, true );

					// Add inline script to handle onload.
					wp_add_inline_script(
						'cloudflare-turnstile',
						'
						window.onTurnstileLoad = function() {
							// Turnstile is ready
							window.turnstileReady = true;
						};
					',
						'before'
					);
				}
			}
		}

		wp_enqueue_script( 'suremembers-restricted-template' );
		wp_enqueue_style( 'suremembers-restricted-template-style', SUREMEMBERS_CORE_URL . 'assets/css/restricted-template.css', [], SUREMEMBERS_CORE_VER );
	}

	/**
	 * Check user role access.
	 *
	 * @param int                  $user_id Current user id.
	 * @param array<string, mixed> $rules Access rules array.
	 * @since 1.1.0
	 * @return bool
	 */
	public function provide_access_by_user_role( $user_id, $rules ) {
		$return = false;

		if ( empty( $user_id ) || empty( $rules[ SUREMEMBERS_POST_TYPE ] ) ) {
			return $return;
		}

		$user_meta = get_userdata( $user_id );
		if ( empty( $user_meta->roles ) ) {
			return $return;
		}

		foreach ( $rules[ SUREMEMBERS_POST_TYPE ] as $restricting_rule_value ) {
			$access_group_id = ! empty( $restricting_rule_value['id'] ) ? intval( $restricting_rule_value['id'] ) : false;

			if ( ! $access_group_id ) {
				continue;
			}

			$get_user_role = Access_Groups::get_selected_user_roles( $access_group_id );

			if ( ! empty( $get_user_role ) && ! empty( array_intersect( $get_user_role, $user_meta->roles ) ) ) {
				$return = true;
				break;
			}
		}
		return $return;
	}

	/**
	 * Returns updated restricted content in post content
	 *
	 * @return string
	 * @since 1.3.1
	 */
	public function restricted_content() {
		return wpautop( Restricted::get_unauthorized_message( $this->restriction_data ) );
	}

	/**
	 * Undocumented function
	 *
	 * @return string
	 * @since 1.4.0
	 */
	public function drip_content() {
		return wpautop( $this->drip_string );
	}

	/**
	 * Checks whether current page should be redirected to custom template or not
	 *
	 * @return bool
	 * @since 1.3.1
	 */
	public function redirect_to_custom_template() {
		global $post;
		if ( apply_filters( 'suremembers_should_redirect_to_custom_template', is_archive() || is_home() || is_front_page() || empty( $post->post_type ) || $post->post_type !== 'post' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Handles the access group expiration status by access group id.
	 *
	 * @param int                  $user_id Current user ID.
	 * @param array<string, mixed> $restricting_rules Restricting rules array.
	 * @return void
	 * @since 1.6.0
	 */
	public function handle_access_group_expiration( $user_id, $restricting_rules ) {
		if ( empty( $restricting_rules ) || ! is_array( $restricting_rules ) ) {
			return;
		}

		foreach ( $restricting_rules as $id => $rule ) {
			// Check if access group is expired.
			if ( Access_Groups::is_expired( $id, $user_id ) ) {
				Access::revoke( $user_id, $id );
			}
		}
	}

	/**
	 * Get Restricted page template action priority
	 *
	 * @return int
	 */
	public function get_restricted_page_template_action_priority() {
		return intval( apply_filters( 'suremembers_restricted_page_template_action_priority', 999 ) );
	}

	/**
	 * Function to group the template include or template override actions and filters.
	 * From this function, a filter is used to include the restricted content message template.
	 *
	 * @since 1.10.1
	 * @return void
	 */
	public function execute_template_filters() {

		/**
		 * Disable the Astra's site editor template include to solve the multiple template display on one page.
		 */
		add_filter( 'astra_addon_render_custom_template_content', '__return_false' );

		/**
		 * Load the custom template to display the restricted message and required data.
		 * Such as: Drip countdown and restricted message, header and footer.
		 */
		if ( apply_filters( 'suremembers_load_restricted_page_template', true ) ) {
			add_filter( 'template_include', [ $this, 'restricted_page_template' ], $this->get_restricted_page_template_action_priority() );
		}
	}

	/**
	 * Recursively enqueue block assets for Gutenberg/Spectra blocks.
	 *
	 * @param array<string, mixed> $blocks Array of parsed blocks.
	 * @param int                  $post_id The post ID containing the blocks.
	 *
	 * @return bool Whether Spectra blocks were found.
	 *
	 * @since 2.0.0
	 */
	private function enqueue_block_assets( $blocks, $post_id ) {
		if ( empty( $blocks ) || ! is_array( $blocks ) ) {
			return false;
		}

		$has_spectra_blocks = false;

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			$block_name = $block['blockName'];
			$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( $block_name );

			if ( $block_type ) {
				// Enqueue block type styles.
				if ( ! empty( $block_type->style ) ) {
					wp_enqueue_style( $block_type->style );
				}

				// Enqueue block type scripts.
				if ( ! empty( $block_type->script ) ) {
					wp_enqueue_script( $block_type->script );
				}

				// Enqueue block editor styles (some blocks use these on frontend too).
				if ( ! empty( $block_type->editor_style ) ) {
					wp_enqueue_style( $block_type->editor_style );
				}
			}

			// Check if this is a Spectra block.
			if ( strpos( $block_name, 'uagb/' ) === 0 ) {
				$has_spectra_blocks = true;
			}

			// Recursively process inner blocks.
			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$inner_has_spectra = $this->enqueue_block_assets( $block['innerBlocks'], $post_id );
				if ( $inner_has_spectra ) {
					$has_spectra_blocks = true;
				}
			}
		}

		// If Spectra blocks detected, enqueue all Spectra assets properly (only once at top level).
		if ( $has_spectra_blocks ) {
			static $spectra_enqueued = [];
			if ( ! isset( $spectra_enqueued[ $post_id ] ) ) {
				$this->enqueue_spectra_assets( $post_id );
				$spectra_enqueued[ $post_id ] = true;
			}
		}

		return $has_spectra_blocks;
	}

	/**
	 * Enqueue Spectra (UAG) specific assets for a given post.
	 *
	 * @param int $post_id The post ID containing Spectra blocks.
	 *
	 * @since 2.0.0
	 */
	private function enqueue_spectra_assets( $post_id ) {
		// Check if UAGB (Spectra) plugin is active.
		if ( ! class_exists( 'UAGB_Post_Assets' ) ) {
			return;
		}

		// Create a UAGB_Post_Assets instance for the given post.
		// This properly handles all Spectra block assets including CSS, JS, and fonts.
		try {
			$spectra_assets = new \UAGB_Post_Assets( $post_id );

			// Enqueue all Spectra scripts and styles for this post.
			if ( method_exists( $spectra_assets, 'enqueue_scripts' ) ) {
				$spectra_assets->enqueue_scripts();
			}
		} catch ( \Exception $e ) {
			// Silently fail if there's any issue with Spectra asset loading.
			// This prevents breaking the page if Spectra has any issues.
			unset( $e ); // Mark as intentionally unused.
		}
	}
}
