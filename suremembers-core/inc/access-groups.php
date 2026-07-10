<?php
/**
 * Access Groups.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Inc;

defined( 'ABSPATH' ) || exit;

/**
 * Access Groups
 *
 * @since 0.0.1
 */
class Access_Groups {
	/**
	 * Gets all the published access groups available on this website.
	 *
	 * @param array<string, mixed> $args extra params to be passed to get_post query.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public static function get_active( $args = [] ) {
		$plans_array = [];
		$plans_args  = apply_filters(
			'suremembers_get_access_groups',
			[
				'post_type'   => SUREMEMBERS_POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'order'       => 'ASC',
			]
		);
		if ( is_array( $args ) && ! empty( $args ) ) {
			$args       = Utils::sanitize_recursively( 'sanitize_text_field', $args );
			$plans_args = array_merge( $plans_args, $args );
		}

		$plans = get_posts( $plans_args );

		// If no plans found returns empty array.
		if ( empty( $plans ) ) {
			return $plans_array;
		}

		foreach ( $plans as $plan ) {
			if ( empty( $plan->ID ) || empty( $plan->post_title ) ) {
				continue;
			}
			$plans_array[ $plan->ID ] = $plan->post_title;
		}

		return $plans_array;
	}

	/**
	 * Get access groups URL.
	 *
	 * @param array<string, mixed> $args URL arguments.
	 *
	 * @return string URL of the access groups as per $args.
	 *
	 * @since 1.0.0
	 */
	public static function get_admin_url( $args = [] ) {
		// Default to memberships page.
		$defaults = [
			'page' => 'suremembers',
		];

		$url_args = wp_parse_args( $args, $defaults );

		// Remove post_type from args as we're using admin.php structure now.
		unset( $url_args['post_type'] );

		return add_query_arg(
			$url_args,
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Check user's access of membership by his id.
	 *
	 * @param int   $user_id          User id.
	 * @param array $access_group_ids access group ids to be checked.
	 * @return bool
	 * @since 1.10.14
	 */
	public static function check_user_access_by_id( $user_id, $access_group_ids ) {
		return self::check_if_user_has_access( $access_group_ids, $user_id );
	}

	/**
	 * Check block restriction.
	 *
	 * @param array $access_group_ids access group ids to be checked.
	 * @param int   $user_id          User id.
	 * @return bool
	 */
	public static function check_if_user_has_access( $access_group_ids, $user_id = 0 ) {
		$access_group_ids = self::filter_active_access_groups( $access_group_ids );
		if ( empty( $access_group_ids ) ) {
			return true;
		}
		if ( ! $user_id ) {
			$user_id = intval( get_current_user_id() );
		}
		if ( ! $user_id ) {
			return false;
		}
		$user_plan = get_user_meta( $user_id, SUREMEMBERS_USER_META, true );
		if ( empty( $user_plan ) || ! is_array( $user_plan ) ) {
			return false;
		}
		$array_intersect_common_id = array_intersect( $user_plan, $access_group_ids );
		if ( empty( $array_intersect_common_id ) ) {
			return false;
		}
		return self::check_plan_active( $user_id, $array_intersect_common_id );
	}

	/**
	 * Check plan status.
	 *
	 * @param int       $user_id user id.
	 * @param array|int $plan_ids Array of plan ids or single plan id can also be provided.
	 */
	public static function check_plan_active( $user_id, $plan_ids ) {
		if ( empty( $plan_ids ) ) {
			return false;
		}
		$unrestrict_block = false;
		if ( is_array( $plan_ids ) ) {
			foreach ( $plan_ids as $value ) {
				$value = intval( $value );
				if ( ! $value ) {
					continue;
				}
				$check_plan_validity = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$value}", true );
				if ( is_array( $check_plan_validity ) && isset( $check_plan_validity['status'] ) && $check_plan_validity['status'] === 'active' ) {
					$unrestrict_block = true;
					break;
				}
			}
		} else {
			$check_plan_validity = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$plan_ids}", true );
			if ( is_array( $check_plan_validity ) && isset( $check_plan_validity['status'] ) && $check_plan_validity['status'] === 'active' ) {
				$unrestrict_block = true;
			}
		}
		return $unrestrict_block;
	}

	/**
	 * Get top priority access group's id.
	 *
	 * @param array<string, mixed> $access_group_ids access group ids.
	 */
	public static function get_priority_id( $access_group_ids ) {
		if ( count( $access_group_ids ) === 1 ) {
			$valid_access_id = intval( $access_group_ids[0] );
			if ( ! $valid_access_id ) {
				return 0;
			}
			$access_group_id = self::is_active_access_group( $valid_access_id );
			if ( ! is_int( $access_group_id ) ) {
				$access_group_id = 0;
			}
			return $access_group_id;
		}
		$sort_ids_as_prior = [];
		foreach ( $access_group_ids as $value ) {
			$value = intval( $value );
			if ( ! $value ) {
				continue;
			}
			$check_status = self::is_active_access_group( $value );
			if ( ! $check_status ) {
				continue;
			}
			$priority            = intval( get_post_meta( $value, SUREMEMBERS_PLAN_PRIORITY, true ) );
			$sort_ids_as_prior[] = [
				'id'       => $value,
				'priority' => $priority,
			];
		}
		usort(
			$sort_ids_as_prior,
			static function ( $a, $b ) {
				return $b['priority'] - $a['priority'];
			}
		);
		return empty( $sort_ids_as_prior ) ? 0 : $sort_ids_as_prior[0]['id'];
	}

	/**
	 * Checks whether provided access group is having status 'publish' or not
	 *
	 * @param int $access_group_id current access group id.
	 *
	 * @since 1.0.0
	 */
	public static function is_active_access_group( $access_group_id ) {
		$status = get_post_status( $access_group_id );
		if ( $status === 'publish' ) {
			return intval( $access_group_id );
		}
		return false;
	}

	/**
	 * Returns active access groups
	 *
	 * @param array<string, mixed> $access_group_ids array of access group ids.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public static function filter_active_access_groups( $access_group_ids ) {
		$result = [];
		if ( empty( $access_group_ids ) || ! is_array( $access_group_ids ) ) {
			return $result;
		}
		foreach ( $access_group_ids as $id ) {
			$active_id = self::is_active_access_group( $id );
			if ( $active_id ) {
				$result[] = $active_id;
			}
		}
		return $result;
	}

	/**
	 * Returns active access groups which are not expired.
	 *
	 * @param array<string, mixed> $access_group_ids array of access group ids.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.10.8
	 */
	public static function filter_not_expired_groups( $access_group_ids ) {
		$result = [];
		if ( empty( $access_group_ids ) || ! is_array( $access_group_ids ) ) {
			return $result;
		}
		foreach ( $access_group_ids as $id ) {
			$is_expired = self::is_expired( $id, get_current_user_id() );
			if ( ! $is_expired ) {
				$result[] = $id;
			}
		}
		return $result;
	}

	/**
	 * Get the count of users in a access group.
	 *
	 * @param int $access_group_id Access Group ID.
	 *
	 * @return int Number of users.
	 *
	 * @since 1.0.0
	 */
	public static function get_users_count( $access_group_id ) {
		global $wpdb;
		$meta_key = SUREMEMBERS_USER_META . '_' . $access_group_id;

		$meta_value = '%active%';
		$results    = wp_cache_get( 'suremembers_active_users_var_' . $access_group_id );

		if ( $results === false ) {
			// Ignored due to functionality requirements.
			$results = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}usermeta as um WHERE um.meta_key = %s AND um.meta_value LIKE %s", $meta_key, $meta_value ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_cache_set( 'suremembers_active_users_var_' . $access_group_id, $results );
		}

		if ( is_null( $results ) ) {
			$results = get_post_meta( $access_group_id, SUREMEMBERS_PLAN_ACTIVE_USERS, true );
			if ( ! is_int( $results ) ) {
				$results = 0;
			}
		}

		$results = absint( $results );
		update_post_meta( $access_group_id, SUREMEMBERS_PLAN_ACTIVE_USERS, $results );
		delete_post_meta( $access_group_id, SUREMEMBERS_REQUIRES_QUERY );

		return $results;
	}

	/**
	 * Get the active users belonging to an access group.
	 *
	 * Mirrors get_users_count() but returns the matching WP_User objects
	 * instead of a count. Acts as the single source of truth for listing
	 * members of an access group (shortcode, block, etc.).
	 *
	 * @param int $access_group_id Access Group ID.
	 * @param int $limit           Maximum number of users to return. Use 0 (or a negative value) for no limit. Default 0.
	 * @param int $offset          Number of users to skip (for pagination). Default 0.
	 *
	 * @return array<int, \WP_User> Array of WP_User objects.
	 *
	 * @since 1.2.0
	 */
	public static function get_users_by_group( $access_group_id, $limit = 0, $offset = 0 ) {
		global $wpdb;

		$access_group_id = absint( $access_group_id );
		if ( empty( $access_group_id ) ) {
			return [];
		}

		$limit  = intval( $limit );
		$offset = max( 0, intval( $offset ) );

		$meta_key   = SUREMEMBERS_USER_META . '_' . $access_group_id;
		$meta_value = '%active%';

		$cache_key = 'suremembers_active_user_ids_' . $access_group_id . '_' . $limit . '_' . $offset;
		$user_ids  = wp_cache_get( $cache_key );

		if ( $user_ids === false ) {
			if ( $limit > 0 ) {
				// Ignored due to functionality requirements.
				$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT um.user_id FROM {$wpdb->prefix}usermeta as um WHERE um.meta_key = %s AND um.meta_value LIKE %s ORDER BY um.user_id ASC LIMIT %d OFFSET %d", $meta_key, $meta_value, $limit, $offset ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			} else {
				// Ignored due to functionality requirements.
				$user_ids = $wpdb->get_col( $wpdb->prepare( "SELECT um.user_id FROM {$wpdb->prefix}usermeta as um WHERE um.meta_key = %s AND um.meta_value LIKE %s ORDER BY um.user_id ASC", $meta_key, $meta_value ) ); //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			}
			wp_cache_set( $cache_key, $user_ids );
		}

		if ( empty( $user_ids ) || ! is_array( $user_ids ) ) {
			return [];
		}

		$users = get_users(
			[
				'include' => array_map( 'absint', $user_ids ),
				'orderby' => 'include',
			]
		);

		return is_array( $users ) ? $users : [];
	}

	/**
	 * Get the timestamp at which a user was added to an access group.
	 *
	 * @param int $access_group_id Access Group ID.
	 * @param int $user_id         User ID.
	 *
	 * @return int Unix timestamp, or 0 when unavailable.
	 *
	 * @since 1.2.0
	 */
	public static function get_user_join_timestamp( $access_group_id, $user_id ) {
		$detail = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$access_group_id}", true );

		if ( ! is_array( $detail ) || empty( $detail['created'] ) ) {
			return 0;
		}

		return intval( $detail['created'] );
	}

	/**
	 * Get the expiration timestamp of a user's access to an access group.
	 *
	 * Mirrors the calculation used by is_expired(): returns 0 when the group
	 * has no expiration enabled (lifetime access) or when the data needed to
	 * compute it is missing.
	 *
	 * @param int $access_group_id Access Group ID.
	 * @param int $user_id         User ID.
	 *
	 * @return int Unix timestamp of expiry, or 0 for lifetime / unknown.
	 *
	 * @since 1.2.0
	 */
	public static function get_user_expiration_timestamp( $access_group_id, $user_id ) {
		$expiration = get_post_meta( $access_group_id, SUREMEMBERS_PLAN_EXPIRATION, true );

		if ( ! is_array( $expiration ) || empty( $expiration ) ) {
			return 0;
		}

		$is_expiration_enabled = filter_var( $expiration['enable'] ?? false, FILTER_VALIDATE_BOOLEAN );
		if ( ! $is_expiration_enabled ) {
			return 0;
		}

		$type = $expiration['type'] ?? '';

		if ( $type === 'relative_date' ) {
			if ( empty( $expiration['delay'] ) ) {
				return 0;
			}

			$access_group_detail     = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$access_group_id}", true );
			$user_expiration_details = get_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, true );

			if ( ! is_array( $access_group_detail ) || ! isset( $access_group_detail['created'] ) ) {
				return 0;
			}

			$access_group_date = $access_group_detail['modified'] ?? $access_group_detail['created'];

			if ( is_array( $user_expiration_details ) && isset( $user_expiration_details[ $access_group_id ] ) ) {
				return intval( strtotime( $user_expiration_details[ $access_group_id ], intval( $access_group_date ) ) );
			}

			$date = '+' . intval( $expiration['delay'] ) . ' day';
			return intval( strtotime( $date, intval( $access_group_date ) ) );
		}

		if ( $type === 'specific_date' ) {
			if ( empty( $expiration['specific_date'] ) ) {
				return 0;
			}
			return intval( strtotime( $expiration['specific_date'] ) );
		}

		return 0;
	}

	/**
	 * Get saved user roles
	 *
	 * @param mixed $post_id post id to retrieve data from.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.1.0
	 */
	public static function get_selected_user_roles( $post_id = false ) {
		if ( empty( $post_id ) ) {
			if ( empty( $_GET['post_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return [];
			}
			$post_id = absint( $_GET['post_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$id = absint( $post_id );

		$roles = get_post_meta( $id, SUREMEMBERS_USER_ROLES, true );
		$roles = ! empty( $roles ) && is_array( $roles ) ? $roles : [];
		return $roles;
	}

	/**
	 * Get downloads associated with access group.
	 *
	 * @param bool $post_id Access Group ID.
	 *
	 * @return string Download IDs or empty string if no downloads are available.
	 *
	 * @since 1.3.0
	 */
	public static function get_downloads( $post_id = false ) {
		if ( empty( $post_id ) ) {
			// Ignored as we are getting values from URL.
			if ( empty( $_GET['post_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return '';
			}
			$post_id = absint( $_GET['post_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$id = absint( $post_id );

		$downloads = get_post_meta( $id, SUREMEMBERS_ACCESS_GROUP_DOWNLOADS, true );

		$downloads = is_string( $downloads ) && ! empty( $downloads ) ? $downloads : '';
		return $downloads;
	}

	/**
	 * Get Access Groups by Download ID.
	 *
	 * @param int $download_id Download ID to get access groups.
	 *
	 * @return array<string, mixed> Array of Access Groups matching the Download ID.
	 *
	 * @since 1.3.0
	 */
	public static function by_download_id( $download_id ) {
		global $wpdb;

		$access_groups = [];
		$meta_value    = '%' . $download_id . '%';
		$meta_key      = SUREMEMBERS_ACCESS_GROUP_DOWNLOADS;

		$results = wp_cache_get( 'suremembers_access_groups_by_download_id_' . $download_id );
		if ( $results === false ) {
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->prefix}postmeta as pm INNER JOIN {$wpdb->prefix}posts as p ON pm.post_id = p.ID WHERE pm.meta_key = %s AND pm.meta_value LIKE %s", $meta_key, $meta_value ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			wp_cache_set( 'suremembers_access_groups_by_download_id_' . $download_id, $results );
		}

		if ( ! empty( $results ) ) {
			$access_groups = array_column( $results, 'ID' );
		}

		return $access_groups;
	}

	/**
	 * Check is access group is expired.
	 *
	 * @param int $access_group_id Access Group ID.
	 * @param int $user_id Current user ID.
	 *
	 * @since 1.6.0
	 */
	public static function is_expired( $access_group_id, $user_id ) {
		$expiration = get_post_meta( $access_group_id, SUREMEMBERS_PLAN_EXPIRATION, true );
		if ( ! is_array( $expiration ) || empty( $expiration ) ) {
			return false;
		}

		$is_expiration_enabled = filter_var( $expiration['enable'] ?? false, FILTER_VALIDATE_BOOLEAN );
		if ( ! $is_expiration_enabled ) {
			return false;
		}

		if ( $expiration['type'] === 'relative_date' ) {
			if ( empty( $expiration['delay'] ) ) {
				return false;
			}

			$access_group_detail     = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$access_group_id}", true );
			$user_expiration_details = get_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, true );

			if ( ! is_array( $access_group_detail ) || ! isset( $access_group_detail['created'] ) ) {
				return false;
			}

			$current_time    = intval( current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$expiration_date = '';

			if ( is_array( $user_expiration_details ) && isset( $user_expiration_details[ $access_group_id ] ) ) {
				$expiration_date = $user_expiration_details[ $access_group_id ];

				// Get modified access group modified date timestamp.
				$access_group_date = $access_group_detail['modified'] ?? $access_group_detail['created'];

				// convert it into the date to time to compare.
				$expiration_date = strtotime( $expiration_date, intval( $access_group_date ) );
			} else {
				// Get updated date if available.
				$access_group_date = $access_group_detail['modified'] ?? $access_group_detail['created'];
				$date              = '+' . intval( $expiration['delay'] ) . ' day';
				$expiration_date   = strtotime( $date, intval( $access_group_date ) );
			}

			if ( $current_time > $expiration_date ) {
				return true;
			}
		}

		if ( $expiration['type'] === 'specific_date' ) {
			if ( empty( $expiration['specific_date'] ) ) {
				return false;
			}
			$current_time    = intval( current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$expiration_time = strtotime( $expiration['specific_date'] );

			if ( $current_time > $expiration_time ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if user has access to the post.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $access_groups Access Groups.
	 * @param int                  $_user_id User ID (unused, kept for backward compatibility).
	 *
	 * @since 1.10.8
	 */
	public static function check_user_has_post_access( $post_id, $access_groups, $_user_id = 0 ) {
		$access_group_ids = array_keys( $access_groups[ SUREMEMBERS_POST_TYPE ] );
		$access_group_ids = self::filter_not_expired_groups( $access_group_ids );
		$access_group_ids = array_map( 'intval', $access_group_ids );
		if ( ! is_array( $access_group_ids ) || empty( $access_group_ids ) ) {
			return false;
		}

		if ( self::check_if_user_has_access( $access_group_ids ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get restriction detail of a specific access group.
	 *
	 * @param int $access_group Access Group ID.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.10.8
	 */
	public static function get_restriction_detail( $access_group ) {
		$action = get_post_meta( $access_group, SUREMEMBERS_PLAN_RULES, true );
		return is_array( $action ) && isset( $action['restrict'] ) ? $action['restrict'] : [];
	}

	/**
	 * Check if post is about to drip.
	 *
	 * Core returns default (no dripping). Pro provides full implementation
	 * via SureMembers\Inc\Access_Groups class.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $access_groups Access Groups.
	 * @param int                  $user_id User ID.
	 *
	 * @return array{status: bool, time: string} Drip status array.
	 *
	 * @since 1.10.8
	 */
	public static function check_is_post_is_dripping( $post_id, $access_groups, $user_id = 0 ): array {
		return [
			'status' => false,
			'time'   => '',
		];
	}
}
