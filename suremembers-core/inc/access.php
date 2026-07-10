<?php
/**
 * Access.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Inc;

defined( 'ABSPATH' ) || exit;

/**
 * Access
 *
 * @since 0.0.1
 */
class Access {
	/**
	 * Grants access to provided access groups
	 *
	 * @param int                  $user_id current user.
	 * @param mixed                $access_group_ids array of multiple access groups or single access group can be provided.
	 * @param string               $integration integration slug.
	 * @param array<string, mixed> $expiration expiration date.
	 * @param bool                 $send_email Flat to choose weather to send the email notification or not.
	 *
	 * @since 1.1.0
	 */
	public static function grant( $user_id, $access_group_ids, $integration = 'default', $expiration = [], $send_email = true ) {
		if ( empty( $user_id ) || empty( $access_group_ids ) ) {
			return;
		}

		if ( ! is_array( $access_group_ids ) ) {
			$ag_id = intval( $access_group_ids );
			if ( empty( $ag_id ) ) {
				return;
			}
			$access_group_ids = [ $ag_id ];
		} else {
			$access_group_ids = Utils::sanitize_recursively( 'absint', $access_group_ids );
		}

		$access_groups = get_user_meta( $user_id, SUREMEMBERS_USER_META, true );
		if ( empty( $access_groups ) || ! is_array( $access_groups ) ) {
			$access_groups = [];
		}

		$access_groups = array_unique( array_merge( $access_groups, $access_group_ids ) );

		if ( ! empty( $expiration ) ) {
			update_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, $expiration );
		}

		update_user_meta( $user_id, SUREMEMBERS_USER_META, $access_groups );

		foreach ( $access_group_ids as $ag_id ) {
			$access_group_data = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$ag_id}", true );

			if ( empty( $access_group_data ) || ! is_array( $access_group_data ) ) {
				$access_group_data = [];
			}

			if ( ! empty( $access_group_data['status'] ) && self::is_access_status_same( $access_group_data['status'], 'active' ) ) {
				do_action( 'suremembers_process_aborted_same_status', 'grant', $access_group_data, $ag_id );
				continue;
			}

			if ( ! isset( $access_group_data['created'] ) ) {
				$access_group_data['created'] = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			}

			$access_group_data['modified'] = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

			$access_group_data = apply_filters(
				'suremembers_grant_creation_data',
				array_merge(
					$access_group_data,
					[
						'integration' => $integration,
						'status'      => 'active',
					]
				)
			);

			update_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$ag_id}", $access_group_data );

			self::set_update_required( $ag_id );
			self::update_user_role_revoke_grant_access( $ag_id, $user_id, 'grant' );

				do_action( 'suremembers_user_access_group_granted', $user_id, absint( $ag_id ), $access_group_ids );
			/**
			 * Action fired after access is granted to a user.
			 *
			 * @since 1.0.0
			 * @deprecated 1.0.0 Use 'suremembers_after_access_granted' instead.
			 *
			 * @param int   $user_id          User ID.
			 * @param array $access_group_ids Access group IDs.
			 */
			do_action( 'suremembers_after_access_grant', $user_id, $access_group_ids );

			/**
			 * Action fired after access is granted to a user (extended version).
			 *
			 * Pro module hooks into this action to send access expiration email notifications.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $user_id          User ID.
			 * @param array $access_group_ids Access group IDs.
			 * @param int   $ag_id            Current access group ID.
			 * @param bool  $send_email       Whether to send email notification.
			 */
			do_action( 'suremembers_after_access_granted', $user_id, $access_group_ids, $ag_id, $send_email );
		}
	}

	/**
	 * Revokes user access from provided access groups
	 *
	 * @param int   $user_id current user.
	 * @param mixed $access_group_ids array of multiple access groups or single access group can be provided.
	 *
	 * @since 1.0.0
	 */
	public static function revoke( $user_id, $access_group_ids ) {
		if ( empty( $user_id ) || empty( $access_group_ids ) ) {
			return;
		}

		if ( ! is_array( $access_group_ids ) ) {
			$ag_id = intval( $access_group_ids );
			if ( empty( $ag_id ) ) {
				return;
			}
			$access_group_ids = [ $ag_id ];
		} else {
			$access_group_ids = Utils::sanitize_recursively( 'absint', $access_group_ids );
		}

		foreach ( $access_group_ids as $ag_id ) {
			$access_group_data = get_user_meta( $user_id, SUREMEMBERS_USER_META . '_' . $ag_id, true );

			if ( empty( $access_group_data ) || ! is_array( $access_group_data ) || empty( $ag_id ) ) {
				return;
			}

			if ( ! empty( $access_group_data['status'] ) && self::is_access_status_same( $access_group_data['status'], 'revoked' ) ) {
				do_action( 'suremembers_process_aborted_same_status', 'revoke', $access_group_data, $ag_id );
				return;
			}

			$access_group_data = array_merge(
				$access_group_data,
				[
					'modified' => current_time( 'timestamp' ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
					'status'   => 'revoked',
				]
			);

			$access_group_data = self::unset_integration_metadata( $access_group_data );

			update_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$ag_id}", $access_group_data );

			self::set_update_required( $ag_id );
			self::update_user_role_revoke_grant_access( $ag_id, $user_id, 'revoke' );
			do_action( 'suremembers_user_access_group_revoked', $user_id, absint( $ag_id ), $access_group_ids );
			do_action( 'suremembers_after_access_revoke', $user_id, $access_group_ids );
		}
	}

	/**
	 * Checks whether the access status is same as before
	 *
	 * @param string $current_status current status of action.
	 * @param string $status_to_check status to be checked with.
	 *
	 * @since 1.1.0
	 */
	public static function is_access_status_same( $current_status, $status_to_check ) {
		$response = false;
		if ( ! empty( $current_status ) && ! empty( $status_to_check ) && $status_to_check === $current_status ) {
			$response = true;
		}

		return apply_filters( 'suremembers_is_access_status_same', $response, $current_status, $status_to_check );
	}

	/**
	 * Update users user role associated with access group.
	 *
	 * @param int    $access_group_id Access group id.
	 * @param int    $user_id User id.
	 * @param string $action Revoke or grant.
	 *
	 * @since 1.1.0
	 */
	public static function update_user_role_revoke_grant_access( $access_group_id, $user_id, $action ) {
		$get_assign_roles = get_post_meta( $access_group_id, SUREMEMBERS_USER_ROLES, true );
		if ( empty( $get_assign_roles ) || ! is_array( $get_assign_roles ) ) {
			return;
		}
		$get_current_user = new \WP_User( $user_id );
		if ( ! is_array( $get_current_user->roles ) ) {
			return;
		}
		foreach ( $get_assign_roles as $roles_value ) {
			if ( $action === 'grant' ) {
				if ( ! in_array( $roles_value, $get_current_user->roles, true ) ) {
					$get_current_user->add_role( $roles_value );
				}
			} else {
				if ( in_array( $roles_value, $get_current_user->roles, true ) ) {
					$get_current_user->remove_role( $roles_value );
				}
			}
		}
	}

	/**
	 * Function to get the access group's expiry data.
	 *
	 * @param int|string $access_group_id The ID of the access group of which the data has to be fetched.
	 * @param string     $key The key that needs to be check in the data and return it's value.
	 *
	 * @return mixed $access_group_expiry_data The Expiry of the access group.
	 *
	 * @since 1.10.2
	 */
	public static function get_access_group_expiry_data( $access_group_id, $key = '' ) {
		$access_group_expiry_data = get_post_meta( intval( $access_group_id ), SUREMEMBERS_PLAN_EXPIRATION, true );

		// If no expiration date set then return an empty array.
		if ( empty( $access_group_expiry_data ) && ! is_array( $access_group_expiry_data ) ) {
			return [];
		}

		// Search for the specific key in the expiry data of the access group.
		if ( ! empty( $key ) && is_array( $access_group_expiry_data ) ) {
			return $access_group_expiry_data[ $key ] ?? $access_group_expiry_data;
		}

		return $access_group_expiry_data;
	}

	/**
	 * Set access group update required key.
	 *
	 * @param int $access_group_id Access Group ID.
	 *
	 * @since 1.0.0
	 */
	private static function set_update_required( $access_group_id ) {
		if ( empty( $access_group_id ) ) {
			return;
		}

		update_post_meta( $access_group_id, SUREMEMBERS_REQUIRES_QUERY, 'true' );
	}

	/**
	 * Removes extra metadata on revoke
	 *
	 * @param array<string, mixed> $access_group_data access group meta data.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.1.0
	 */
	private static function unset_integration_metadata( $access_group_data ) {
		if ( isset( $access_group_data['wc_order_ids'] ) ) {
			unset( $access_group_data['wc_order_ids'] );
		}

		return $access_group_data;
	}
}
