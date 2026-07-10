<?php
/**
 * Restricted.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Inc;

defined( 'ABSPATH' ) || exit;

/**
 * Restricted
 *
 * @since 0.0.1
 */
class Restricted {
	/**
	 * Return post types applicable for restriction
	 *
	 * @param string $output type of output to be retrieved.
	 * @param string $context Get post types context.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public static function get_post_types( $output = 'names', $context = 'default' ) {
		$args       = apply_filters(
			'suremembers_restricted_post_types',
			[
				'public'  => true,
				'show_ui' => true,
			]
		);
		$post_types = get_post_types( $args, $output );

		if ( empty( $post_types ) ) {
			return [];
		}
		$exclude_post_types = apply_filters( 'suremembers_get_post_types_excludes', [ SUREMEMBERS_POST_TYPE, 'attachment' ], $context );

		$exclude_post_types = array_combine( $exclude_post_types, $exclude_post_types );

		return array_diff_key( $post_types, $exclude_post_types );
	}

	/**
	 * Get type of current visited content
	 *
	 * @since 1.0.0
	 */
	public static function get_current_content_type() {
		$page_type = '';
		if ( is_archive() ) {
			$page_type = 'is_archive';

			if ( is_category() || is_tag() || is_tax() ) {
				$page_type = 'is_tax';
			} elseif ( is_date() ) {
				$page_type = 'is_date';
			} elseif ( is_author() ) {
				$page_type = 'is_author';
			} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
				$page_type = 'is_woo_shop_page';
			}
		} elseif ( is_home() ) {
			$page_type = 'is_home';
		} elseif ( is_front_page() ) {
			$page_type = 'is_front_page';
		} elseif ( is_singular() ) {
			$page_type = 'is_singular';
		} elseif ( is_admin() ) {
			$screen = get_current_screen();
			if ( isset( $screen->base ) && ( $screen->base === 'post' ) ) {
				$page_type = 'is_singular';
			}
		}

		return $page_type;
	}

	/**
	 * Get Access Groups restricting current content
	 *
	 * @param string               $post_type SUREMEMBERS_POST_TYPE.
	 * @param array<string, mixed> $option meta array.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public static function by_access_groups( $post_type, $option ) {
		global $wpdb;
		global $post;

		// Ensure post_type is always a string.
		$post_type_raw   = $post_type ? $post_type : ( $post->post_type ?? '' );
		$post_type       = is_string( $post_type_raw ) ? esc_sql( sanitize_text_field( $post_type_raw ) ) : '';
		$current_post_id = ! empty( $option['current_post_id'] ) ? intval( $option['current_post_id'] ) : 0;
		$get_post_type   = get_post_type();
		if ( empty( $get_post_type ) ) {
			$get_post_type = '';
		}
		$current_post_type = isset( $option['current_post_type'] ) ? esc_sql( sanitize_text_field( $option['current_post_type'] ) ) : esc_sql( $get_post_type );

		$include  = isset( $option['include'] ) ? esc_sql( sanitize_text_field( $option['include'] ) ) : '';
		$priority = isset( $option['priority'] ) ? esc_sql( sanitize_text_field( $option['priority'] ) ) : '';

		$query = "SELECT p.ID, p.post_name, pm.meta_value FROM {$wpdb->postmeta} as pm
					INNER JOIN {$wpdb->posts} as p ON pm.post_id = p.ID
					INNER JOIN {$wpdb->postmeta} as priority ON  p.ID = priority.post_id
					WHERE pm.meta_key = '{$include}'
					AND priority.meta_key = '{$priority}'
					AND p.post_type = '{$post_type}'
					AND p.post_status = 'publish'";

		$exclude = isset( $option['exclusion'] ) ? esc_sql( sanitize_text_field( $option['exclusion'] ) ) : '';

		$exclude_query = "SELECT p.ID FROM {$wpdb->postmeta} as pm
					INNER JOIN {$wpdb->posts} as p ON pm.post_id = p.ID
					WHERE pm.meta_key = '{$exclude}'
					AND p.post_type = '{$post_type}'
					AND p.post_status = 'publish'";

		$orderby = ' ORDER BY convert(priority.meta_value, decimal) DESC, p.post_date DESC';

		/* Entire Website */
		$meta_args = "pm.meta_value LIKE '%\"basic-global\"%'";

		$content_meta = self::get_content_meta_values( $option );

		if ( ! empty( $content_meta ) ) {
			foreach ( $content_meta as $meta ) {
				$meta_args .= " OR pm.meta_value LIKE '%\"" . $wpdb->esc_like( $meta ) . "\"%'";
			}
		}

		$exclude_query .= ' AND (' . $meta_args . ') ';
		$query         .= ' AND (' . $meta_args . ') AND p.ID NOT IN ( ' . $exclude_query . ' ) ' . $orderby;
		$posts          = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$post_id = 0;
		if ( in_array( $current_post_type, [ 'is_singular', 'is_front_page' ], true ) ) {
			$post_id = $current_post_id;
		}

		$restricted_url = self::get_rules_by_url_restriction( $post_id, $exclude_query );
		if ( ! empty( $restricted_url ) ) {
			$posts = array_merge( $restricted_url, $posts );
		}

		$response = [];
		foreach ( $posts as $local_post ) {
			$response[ $post_type ][ $local_post->ID ] = [
				'id'        => $local_post->ID,
				'post_name' => $local_post->post_name,
				'include'   => ! empty( $local_post->meta_value ) ? maybe_unserialize( $local_post->meta_value ) : [],
			];
		}

		return $response;
	}

	/**
	 * Determine whether a given post is restricted for a user by access groups.
	 *
	 * This is the canonical "does this user lack access to this post" check. It
	 * mirrors the evaluation used for the frontend loop ({@see \SureMembersCore\Admin\Rules_Engine::unrestricted_posts()})
	 * and the REST single-item filter, so every surface stays consistent.
	 *
	 * Note: administrators/managers are NOT bypassed here — callers that need a
	 * manager bypass should check capabilities themselves so this method remains
	 * a pure access-group evaluation.
	 *
	 * @param int $post_id Post ID to evaluate.
	 * @param int $user_id User ID to check. Defaults to the current user.
	 *
	 * @return bool True when access groups restrict the post and the user lacks access.
	 *
	 * @since 1.1.0
	 */
	public static function is_restricted_for_user( int $post_id, int $user_id = 0 ): bool {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return false;
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$option = [
			'include'           => SUREMEMBERS_PLAN_INCLUDE,
			'exclusion'         => SUREMEMBERS_PLAN_EXCLUDE,
			'priority'          => SUREMEMBERS_PLAN_PRIORITY,
			'current_post_id'   => $post_id,
			'current_post_type' => $post->post_type,
			'current_page_type' => 'is_singular',
		];

		$access_groups = self::by_access_groups( SUREMEMBERS_POST_TYPE, $option );
		if ( empty( $access_groups ) || empty( $access_groups[ SUREMEMBERS_POST_TYPE ] ) ) {
			return false;
		}

		return ! Access_Groups::check_if_user_has_access( array_keys( $access_groups[ SUREMEMBERS_POST_TYPE ] ), $user_id );
	}

	/**
	 * Get meta values of current content. Required for drip integration
	 *
	 * @param array<string, mixed> $option Options for current query.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public static function get_content_meta_values( $option = [] ) {
		$option            = apply_filters( 'suremembers_get_content_meta_values_option', $option );
		$current_page_type = isset( $option['current_page_type'] ) ? sanitize_text_field( $option['current_page_type'] ) : self::get_current_content_type();
		$post_type         = get_post_type();
		if ( empty( $post_type ) ) {
			$post_type = '';
		}
		$current_post_type = isset( $option['current_post_type'] ) ? esc_sql( sanitize_text_field( $option['current_post_type'] ) ) : esc_sql( $post_type );
		$q_obj             = isset( $option['current_post_id'] ) && $current_page_type === 'is_singular' ? get_post( absint( $option['current_post_id'] ) ) : get_queried_object();
		$meta_args         = [];

		switch ( $current_page_type ) {
			case 'is_404':
				$meta_args[] = 'special-404';
				break;
			case 'is_search':
				$meta_args[] = 'special-search';
				break;
			case 'is_archive':
			case 'is_tax':
			case 'is_date':
			case 'is_author':
				$meta_args[] = 'basic-archives';
				$meta_args[] = "{$current_post_type}|all|archive";

				if ( $current_page_type === 'is_tax' && ( is_category() || is_tag() || is_tax() ) ) {
					if ( is_object( $q_obj ) && ! empty( $q_obj->taxonomy ) && ! empty( $q_obj->term_id ) ) {
						$meta_args[] = "{$current_post_type}|all|taxarchive|{$q_obj->taxonomy}";
					}
				} elseif ( $current_page_type === 'is_date' ) {
					$meta_args[] = 'special-date';
				} elseif ( $current_page_type === 'is_author' ) {
					$meta_args[] = 'special-author';
				}
				break;
			case 'is_home':
				global $wp_query;

				// Get the default current page ID.
				$current_id = isset( $option['current_post_id'] ) ? intval( $option['current_post_id'] ) : get_the_id();

				// Get the queried object from WP_query.
				$queried_object = ! empty( $wp_query->queried_object ) ? $wp_query->queried_object : 0;

				// Retrieve the post ID from the queried object.
				$queried_post_id = ! empty( $queried_object ) ? $queried_object->ID : get_the_id();

				/**
				 * Get the queried page ID i:e parent page ID if the page is set as front or home page. Such as for blog page.
				 *
				 * Use-case: Blog page was not getting restricted if specifically selected in the access group.
				 * Solution applied: WP returns the first Post ID if you are accessing the "Posts page" which is set as home page in Settings -> Reading.
				 * If the current page is set as home page and the queried page ID and the Post Page ID is same then set $current_id as $queried_post_id.
				 */
				if ( get_option( 'show_on_front' ) === 'page' && isset( $queried_post_id ) && get_option( 'page_for_posts' ) === $queried_post_id ) {
					$current_id = $queried_post_id;
				}

				$meta_args[] = 'special-blog';
				$meta_args[] = "{$current_post_type}|all";
				if ( ! empty( $current_id ) ) {
					$meta_args[] = "post-{$current_id}-|";
					// Check parent.
					$parent_id = self::get_parent_id( $current_id );
					if ( $parent_id ) {
						$meta_args[] = "postchild-{$parent_id}-|";
					}
				}
				break;
			case 'is_front_page':
				$current_id  = isset( $option['current_post_id'] ) ? intval( $option['current_post_id'] ) : get_the_id();
				$meta_args[] = 'special-front';
				$meta_args[] = "{$current_post_type}|all";
				if ( ! empty( $current_id ) ) {
					$meta_args[] = "post-{$current_id}-|";
					// Check parent.
					$parent_id = self::get_parent_id( $current_id );
					if ( $parent_id ) {
						$meta_args[] = "postchild-{$parent_id}-|";
					}
				}
				break;
			case 'is_singular':
				$current_id  = isset( $option['current_post_id'] ) ? intval( $option['current_post_id'] ) : get_the_id();
				$meta_args[] = 'basic-singulars';
				$meta_args[] = "{$current_post_type}|all";
				if ( ! empty( $current_id ) ) {
					$meta_args[] = "post-{$current_id}-|";
					// Check parent.
					$parent_id = self::get_parent_id( $current_id );
					if ( $parent_id ) {
						$meta_args[] = "postchild-{$parent_id}-|";
					}
				}

				$taxonomies = ! empty( $q_obj->post_type ) ? get_object_taxonomies( $q_obj->post_type ) : [];
				$post_id    = $q_obj->ID ?? 0;
				$terms      = wp_get_post_terms( $post_id, $taxonomies );

				if ( ! empty( $terms ) && is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$meta_args[] = "tax-{$term->term_id}-single-{$term->taxonomy}";
					}
				}
				break;
			case 'is_woo_shop_page':
				$meta_args[] = 'special-woo-shop';
				break;
			case '':
				break;
		}

		return apply_filters( 'suremembers_get_content_meta_values', $meta_args, $q_obj );
	}

	/**
	 * Get parent post id.
	 *
	 * @param int $id Current post id.
	 *
	 * @return int Return parent post id.
	 *
	 * @since 1.0.1
	 */
	public static function get_parent_id( $id ) {
		$parent_id = wp_get_post_parent_id( $id );
		return ! empty( $parent_id ) ? $parent_id : 0;
	}

	/**
	 * Get Plan details by user ID and access ID.
	 *
	 * @param int $user_id user ID.
	 * @param int $access_id access ID.
	 *
	 * @return mixed Plan details or false.
	 *
	 * @since 1.0.0
	 */
	public static function get_plan_details( $user_id, $access_id ) {
		$plan_details = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$access_id}", true );

		if ( empty( $plan_details ) ) {
			return false;
		}

		return $plan_details;
	}

	/**
	 * Get url restriction rules.
	 *
	 * @param int    $current_post_id Current post id.
	 * @param string $exclude_query Post Exclude query.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.1.0
	 */
	public static function get_rules_by_url_restriction( $current_post_id, $exclude_query ) {
		if ( empty( $current_post_id ) ) {
			$current_search_url = '';
			$http_protocol      = isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';

			if ( isset( $_SERVER['HTTP_HOST'] ) && isset( $_SERVER['REQUEST_URI'] ) ) {
				$current_search_url = esc_url_raw( $http_protocol . $_SERVER['HTTP_HOST'] ) . esc_url_raw( $_SERVER['REQUEST_URI'] );
			}
		} else {
			$current_search_url = get_permalink( $current_post_id );
		}

		$current_search_url = is_string( $current_search_url ) ? $current_search_url : '';

		global $wpdb;
		$query = "SELECT p.ID, p.post_name, pm.meta_value, pm.meta_key FROM {$wpdb->postmeta} as pm
						INNER JOIN {$wpdb->posts} as p ON pm.post_id = p.ID
						INNER JOIN {$wpdb->postmeta} as priority ON  p.ID = priority.post_id
						AND priority.meta_key = '" . SUREMEMBERS_PLAN_PRIORITY . "'
						AND p.post_type = '" . SUREMEMBERS_POST_TYPE . "'
						AND p.post_status = 'publish'
					AND pm.meta_value != ''
					AND pm.meta_key = '" . SUREMEMBERS_RESTRICTED_URL . "'
					AND p.ID NOT IN ( {$exclude_query} )
					ORDER BY convert(priority.meta_value, decimal) DESC, p.post_date DESC";

		$posts = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching

		$response_post = [];
		foreach ( $posts as $local_post ) {
			$meta_value = maybe_unserialize( $local_post->meta_value );
			if ( ! is_array( $meta_value ) || empty( $meta_value['restricted_url'] ) ) {
				continue;
			}

			$restricted_url    = $meta_value['restricted_url'];
			$regex             = ! empty( $meta_value['regex'] ) ? $meta_value['regex'] : false;
			$is_restricted_url = false;
			$keywords          = preg_split( '/\r\n|\r|\n|,/', $restricted_url );
			if ( ! empty( $keywords ) && is_array( $keywords ) ) {
				if ( ! $regex ) {
					foreach ( $keywords as $value ) {
						if ( ! empty( $value ) && strpos( $current_search_url, trim( $value ) ) !== false ) {
							$is_restricted_url = true;
							break;
						}
					}
				} else {
					foreach ( $keywords as $value ) {
						$create_reg = wp_json_encode( trim( $value ) );
						if ( is_string( $create_reg ) && ! empty( $create_reg ) ) {
							preg_match( $create_reg, $current_search_url, $get_matched );
							if ( ! empty( $get_matched ) ) {
								$is_restricted_url = true;
								break;
							}
						}
					}
				}
			}

			if ( $is_restricted_url ) {
				$response_post[] = $local_post;
			}
		}
		return $response_post;
	}

	/**
	 * Get Login CTA.
	 *
	 * @param string $button_extra_classes Extra classes for button.
	 *
	 * @since 1.10.8
	 */
	public static function get_login_cta( $button_extra_classes = '' ) {
		$html_markup = '';
		if ( empty( is_user_logged_in() ) ) {
			$login_link   = Settings::get_custom_content_data( 'login_link' );
			$login_label  = ! empty( $login_link['value'] ) ? sanitize_text_field( $login_link['value'] ) : sanitize_text_field( $login_link['default'] );
			$html_markup .= "<a class='suremembers-open-login-popup " . esc_attr( $button_extra_classes ) . "' href='#'>" . esc_html( $login_label ) . '</a>';
			$html_markup .= self::add_login_popup();
		} else {
			// If the user is logged in but the access is expired/ended/revoked then show the logout button.
			$html_markup .= self::add_logout_html( $button_extra_classes );
		}

		return $html_markup;
	}

	/**
	 * Get Placeholder content when page is restricted.
	 *
	 * @param array<string, mixed> $restriction Restriction Rule.
	 *
	 * @return string $post_content Modified Content.
	 */
	public static function get_unauthorized_message( $restriction ) {
		global $post;
		$post_content = '';
		/**
		 * Check added for `is_singular()` for excerpt in unauthorized message.
		 *
		 * @since 1.6.0
		 */
		if ( is_singular() && ! empty( $restriction['excerpt'] ) && boolval( $restriction['excerpt'] ) ) {
			$post_content = wpautop( $post->post_excerpt );
		}
		$post_content .= '<p>' . wp_kses_post( $restriction['preview_content'] ) . '</p>';

		$suremembers_redirect_button = ! empty( $restriction['preview_button'] ) ? $restriction['preview_button'] : __( 'This content is restricted', 'suremembers-core' );

		if ( ! empty( trim( $restriction['redirect_url'] ) ) ) {
			$post_content .= "<a class='button suremembers-button' target='_blank' href='" . esc_url( $restriction['redirect_url'] ) . "'>" . esc_html( $suremembers_redirect_button ) . '</a>';
		}

		// Check is the login is enabled.
		if ( ! empty( $restriction['enablelogin'] ) ) {
			$post_content .= self::get_login_cta();
		}

		return apply_filters( 'suremembers_restricted_unauthorized_message', $post_content, $restriction );
	}

	/**
	 * Login form.
	 *
	 * @since 1.1.0
	 */
	public static function add_login_popup() {
		$wrapper_extra_classes = apply_filters( 'suremembers_login_wrapper_class', '' );

		$settings = Settings::get_custom_content_data();
		$title    = ! empty( $settings['login_popup_title']['value'] ) ? sanitize_text_field( $settings['login_popup_title']['value'] ) : sanitize_text_field( $settings['login_popup_title']['default'] );
		$username = ! empty( $settings['login_popup_username']['value'] ) ? sanitize_text_field( $settings['login_popup_username']['value'] ) : sanitize_text_field( $settings['login_popup_username']['default'] );
		$password = ! empty( $settings['login_popup_password']['value'] ) ? sanitize_text_field( $settings['login_popup_password']['value'] ) : sanitize_text_field( $settings['login_popup_password']['default'] );
		$remember = ! empty( $settings['login_popup_remember']['value'] ) ? sanitize_text_field( $settings['login_popup_remember']['value'] ) : sanitize_text_field( $settings['login_popup_remember']['default'] );
		$forgot   = ! empty( $settings['login_popup_forgot']['value'] ) ? sanitize_text_field( $settings['login_popup_forgot']['value'] ) : sanitize_text_field( $settings['login_popup_forgot']['default'] );
		$submit   = ! empty( $settings['login_popup_submit']['value'] ) ? sanitize_text_field( $settings['login_popup_submit']['value'] ) : sanitize_text_field( $settings['login_popup_submit']['default'] );
		ob_start();
		?>
		<div class="suremember-login-container-popup <?php echo esc_attr( $wrapper_extra_classes ); ?>">
			<div class="suremember-login-wrapper">
				<span class="dashicons dashicons-no-alt suremember-login-wrapper-close"></span>
				<div class="suremember-login-form-container">
					<h2 class="suremember-login-heading"><?php echo esc_html( $title ); ?></h2>
					<form class="suremember-user-login-form">
						<div>
							<label for="user_login"><?php echo esc_html( $username ); ?></label>
							<input type="text" name="user_name" id="user_login" class="input" value="" size="20" autocapitalize="off" autocomplete="username">
						</div>
						<div class="suremember-login-pass-wrap">
							<label for="user_pass"><?php echo esc_html( $password ); ?></label>
							<div class="suremember-login-wp-pwd">
								<input type="password" name="pwd" class="password-input" value="" size="20" autocomplete="current-password">
								<button type="button" class="button button-secondary suremembers-hide-if-no-js" data-toggle="0" aria-label="<?php echo esc_attr__( 'Hide password', 'suremembers-core' ); ?>">
									<span class="dashicons dashicons-visibility "></span>
								</button>
							</div>
						</div>
						<div class="remember-me-wrap">
							<div class="remember-me">
								<input name="rememberme" type="checkbox" id="rememberme" value="forever">
								<label for="rememberme"><?php echo esc_html( $remember ); ?></label>
							</div>
							<div><a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php echo esc_html( $forgot ); ?></a></div>
						</div>
						<?php
						// Add Turnstile widget.
						$login_page_settings = Settings::get_setting( SUREMEMBERS_LOGIN_FORM_SETTINGS );
						if ( isset( $login_page_settings['enable_turnstile'] ) && $login_page_settings['enable_turnstile'] ) {
							// Check if Simple Turnstile plugin is active.
							if ( ! function_exists( 'is_plugin_active' ) ) {
								include_once ABSPATH . 'wp-admin/includes/plugin.php';
							}

							$simple_turnstile_active = is_plugin_active( 'simple-cloudflare-turnstile/simple-cloudflare-turnstile.php' );

							if ( $simple_turnstile_active ) {
								// Use Simple Turnstile's function to add the widget.
								if ( function_exists( 'cfturnstile_field_show' ) ) {
									?>
									<div class="suremember-turnstile-wrap">
										<?php cfturnstile_field_show(); ?>
									</div>
									<?php
								}
							} else {
								// namespace SureMembersCore Turnstile.
								$site_key = isset( $login_page_settings['turnstile_site_key'] ) ? esc_attr( $login_page_settings['turnstile_site_key'] ) : '';
								$theme    = isset( $login_page_settings['turnstile_theme'] ) ? esc_attr( $login_page_settings['turnstile_theme'] ) : 'auto';

								if ( ! empty( $site_key ) ) {
									?>
									<div class="suremember-turnstile-wrap">
										<input type="hidden" name="suremembers-login-form" value="1" />
										<div class="cf-turnstile-manual" data-sitekey="<?php echo esc_attr( $site_key ); ?>" data-theme="<?php echo esc_attr( $theme ); ?>" data-action="managed"></div>
									</div>
									<?php
								}
							}
						}
						?>
						<div>
							<button type="submit" class="button button-primary button-large suremember-user-form-submit"><?php echo esc_html( $submit ); ?></button>
							<input type="hidden" name="login-nonce" value="<?php echo esc_attr( wp_create_nonce( 'suremembers_user_login' ) ); ?>">
							<input type="hidden" name="action" value="suremembers_user_log">
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
		$html = ob_get_clean();
		if ( $html === false ) {
			$html = '';
		}

		return $html;
	}

	/**
	 * Add Logout HTML for restricted content.
	 *
	 * @param string $button_extra_classes Extra classes for button.
	 *
	 * @since 1.9.4
	 */
	public static function add_logout_html( $button_extra_classes = '' ) {
		$logout_strings = apply_filters(
			'suremembers_logout_strings',
			[
				'default'    => __( 'Log Out', 'suremembers-core' ),
				'processing' => __( 'Logging out', 'suremembers-core' ),
			]
		);
		ob_start();
		?>
		<a
			class="suremembers-logout-button <?php echo esc_attr( $button_extra_classes ); ?>"
			data-nonce="<?php echo esc_attr( wp_create_nonce( 'suremembers_user_logout' ) ); ?>"
			data-processing="<?php echo esc_attr( $logout_strings['processing'] ); ?>"
		><?php echo esc_html( $logout_strings['default'] ); ?></a>
		<?php
		$html = ob_get_clean();
		if ( $html === false ) {
			$html = '';
		}

		return $html;
	}
}
