<?php
/**
 * Utils.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Inc;

defined( 'ABSPATH' ) || exit;

/**
 * Utils
 *
 * @since 0.0.1
 */
class Utils {
	/**
	 * This function performs array_map for multi dimensional array
	 *
	 * @param string               $function function name to be applied on each element on array.
	 * @param array<string, mixed> $data_array array on which function needs to be performed.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public static function sanitize_recursively( $function, $data_array ) {
		$response = [];
		foreach ( $data_array as $key => $data ) {
			$val              = is_array( $data ) ? self::sanitize_recursively( $function, $data ) : $function( $data );
			$response[ $key ] = $val;
		}

		return $response;
	}

	/**
	 * Returns array in format required by select2 dropdown
	 * passed array should have id in key and label in value.
	 *
	 * @param array<string, mixed> $access_groups array to be converted in select2 input array format.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public static function get_select2_format( $access_groups = [] ) {
		$response = [];
		if ( empty( $access_groups ) ) {
			return $response;
		}

		foreach ( $access_groups as $id => $title ) {
			$response[] = [
				'id'   => $id,
				'text' => $title,
			];
		}

		return $response;
	}

	/**
	 * Returns array in format required by React Select dropdown
	 * passed array should have id in key and label in value.
	 *
	 * @param array<string, mixed> $data_array The data array to be converted to React Select format.
	 */
	public static function get_react_select_format( $data_array = [] ) {
		$response = [];
		if ( empty( $data_array ) ) {
			return $response;
		}

		foreach ( $data_array as $id => $title ) {
			$response[] = [
				'label' => $title,
				'value' => $id,
			];
		}

		return $response;
	}

	/**
	 * Remove blank array.
	 *
	 * @param array<string, mixed> $array It is important to variable should be array.
	 *
	 * @since  1.1.0
	 */
	public static function remove_blank_array( $array ) {
		if ( ! is_array( $array ) ) {
			return $array;
		}
		foreach ( $array as $key => &$value ) {
			if ( is_array( $value ) ) {
				if ( ! empty( $value ) ) {
					$value = self::remove_blank_array( $value );
					if ( empty( $value ) ) {
						unset( $array[ $key ] );
					}
				} else {
					unset( $array[ $key ] );
				}
			} elseif ( is_string( $value ) && trim( $value ) === '' ) {
				unset( $array[ $key ] );
			}
		}
		return $array;
	}

	/**
	 * Converts content metadata slug to text
	 *
	 * @param array<string, mixed> $data array to convert data from.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.1.0
	 */
	public static function convert_slug_to_text( $data ) {
		$response = [];
		foreach ( $data as $option ) {
			if ( strpos( $option, '|all' ) !== false ) {
				$post_type = str_replace( '|all', '', $option );

				if ( ! post_type_exists( $post_type ) ) {
					continue;
				}

				$post_label = esc_html( get_post_type_object( $post_type )->labels->name ?? '' );
				$post_label = str_replace( ' ', '-', strtolower( $post_label ) );
				$post_label = ucwords( $post_label );

				$temp          = [];
				$temp['label'] = sprintf( /* translators: %1$s: Post type name. */ __( 'All %s', 'suremembers-core' ), $post_label );
				$temp['value'] = $option;
				$response[]    = $temp;
				continue;
			}

			$params = explode( '-', $option );

			if ( count( $params ) <= 1 ) {
				return [];
			}

			switch ( $params[0] ) {
				case 'tax':
					$temp = [];
					$term = get_term( intval( $params[1] ) );
					if ( ! empty( $term->name ) ) {
						/* translators: %s term name. */
						$temp['label'] = sprintf( __( 'All singulars from %s', 'suremembers-core' ), $term->name );
						$temp['value'] = $option;
						$response[]    = $temp;
					}
					break;
				case 'postchild':
					$temp  = [];
					$title = get_the_title( intval( $params[1] ) );
					if ( ! empty( $title ) ) {
						/* translators: %s title. */
						$temp['label'] = sprintf( __( 'Child of %s', 'suremembers-core' ), $title );
						$temp['value'] = $option;
						$response[]    = $temp;
					}
					break;
				case 'post':
				default:
					$temp  = [];
					$title = get_the_title( intval( $params[1] ) );
					if ( ! empty( $title ) ) {
						$temp['label'] = $title;
						$temp['value'] = $option;
						$response[]    = $temp;
					}
					break;
			}
		}

		return $response;
	}

	/**
	 * Returns integration icons
	 *
	 * @param string $integration integration slug.
	 *
	 * @since 1.1.0
	 */
	public static function integration_icons( $integration = '' ) {
		$icons_list = [
			'buddyboss'    => SUREMEMBERS_CORE_DIR . 'admin/assets/images/integrations/buddyboss.svg',
			'suremembers'  => SUREMEMBERS_CORE_DIR . 'admin/assets/images/icon.svg',
			'surecart'     => SUREMEMBERS_CORE_DIR . 'admin/assets/images/integrations/surecart.svg',
			'suretriggers' => SUREMEMBERS_CORE_DIR . 'admin/assets/images/integrations/suretriggers.svg',
			'woocommerce'  => SUREMEMBERS_CORE_DIR . 'admin/assets/images/integrations/woocommerce.svg',
			'webhook'      => SUREMEMBERS_CORE_DIR . 'admin/assets/images/integrations/webhook.svg',
		];

		if ( empty( $integration ) ) {
			return $icons_list;
		}

		if ( isset( $icons_list[ $integration ] ) ) {
			return $icons_list[ $integration ];
		}

		return [];
	}

	/**
	 * Append the URL params to the redirect URL before redirecting.
	 *
	 * @param string $url URL to redirect.
	 *
	 * @return string $url URL to redirect with URL params.
	 *
	 * @since 1.9.4
	 */
	public static function maybe_append_url_params( $url ) {
		/**
		 * A filter to enable/disable the appending the URL params feature.
		 *
		 * @since 1.9.4
		 */
		if ( ! apply_filters( 'suremembers_maybe_append_url_params', true ) ) {
			return $url;
		}

		if ( empty( $_SERVER['HTTP_REFERER'] ) ) {
			$current_page_url = self::prepare_page_url();
		} else {
			$current_page_url = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}

		if ( empty( $current_page_url ) ) {
			return $url;
		}

		// Get the current page URL and parse it to explode the URL in different URL components.
		$url_params_components = wp_parse_url( esc_url_raw( wp_unslash( $current_page_url ) ) );

		// Process only if the URL components is not empty and query i:e query strings are not empty.
		if ( is_array( $url_params_components ) && ! empty( $url_params_components['query'] ) ) {
			// Convert the string query from string to array format.
			parse_str( $url_params_components['query'], $parsed_query_string );

			// Merge the new and already existing query strings.
			$url = add_query_arg( $parsed_query_string, $url );
		}

		return $url;
	}

	/**
	 * Function to prepare the current page URL.
	 *
	 * @return string $url Current page URL.
	 *
	 * @since 1.9.4
	 */
	public static function prepare_page_url() {
		$url = '';

		// Check if HTTP_REFERER is set and fetch its query strings.
		if ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] === 'on' ) {
			$url .= 'https://';
		} else {
			$url .= 'http://';
		}

		// Append the host(domain name, ip) to the URL.
		$url .= ! empty( $_SERVER['HTTP_HOST'] ) ? esc_url_raw( $_SERVER['HTTP_HOST'] ) : '';

		// Append the requested resource location to the URL.
		$url .= ! empty( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( $_SERVER['REQUEST_URI'] ) : '';

		return $url;
	}

	/**
	 * Function to stop the infinite redirect to the URL added in the access group for restriction.
	 *
	 * @param string $redirect_url The URL to redirect if the restriction is set.
	 *
	 * @since 1.10.3
	 */
	public static function stop_infinite_redirect( $redirect_url ) {
		// Return if redirect URL is empty.
		if ( empty( $redirect_url ) ) {
			return;
		}

		// Check the redirect URL and the current home URL is same.
		$pos = strpos( $redirect_url, home_url() );

		if ( $pos === 0 ) {
			$time = isset( $_COOKIE['suremembers_timestamp'] ) ? sanitize_text_field( $_COOKIE['suremembers_timestamp'] ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
			if ( empty( $time ) ) {
				$time = time();
				setcookie( 'suremembers_timestamp', $time, time() + 10, COOKIEPATH, COOKIE_DOMAIN ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
			}
			$urls = get_transient( 'suremembers_redirection_' . $time );
			if ( empty( $urls ) || ! is_array( $urls ) ) {
				$urls = [];
			}

			if ( isset( $urls[ $redirect_url ] ) ) {
				$count                 = $urls[ $redirect_url ] + 1;
				$urls[ $redirect_url ] = $count;
			} else {
				$urls[ $redirect_url ] = 0;
			}

			set_transient( 'suremembers_redirection_' . $time, $urls, time() + 10 );
		} elseif ( $pos === false ) {
			setcookie( 'suremembers_timestamp', '', time() - 60, COOKIEPATH, COOKIE_DOMAIN ); //phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		}
	}
}
