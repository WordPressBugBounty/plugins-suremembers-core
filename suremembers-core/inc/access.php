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
	 * Convert a stored membership timestamp to a true UTC unix timestamp for display.
	 *
	 * Membership `created`/`modified` values are stored via current_time('timestamp'),
	 * i.e. the real UTC epoch shifted by the site's GMT offset (a local wall-clock
	 * pseudo-timestamp). JavaScript consumers compare against Date.now() (true UTC),
	 * so the stored value must have the offset removed before it is sent to them.
	 *
	 * The conversion assumes the site's current GMT offset. It is exact only when the
	 * timezone has not changed since the record was written — the offset in effect at
	 * write time was never persisted, so it cannot be recovered without a migration.
	 * This matches the behavior of every other timestamp consumer in the plugin, which
	 * all assume the current offset.
	 *
	 * @since 1.3.1
	 * @param int $timestamp Stored timestamp value.
	 * @return int UTC unix timestamp, or 0 when the input is empty/invalid.
	 */
	public static function to_utc_timestamp( $timestamp ) {
		$timestamp = intval( $timestamp );
		if ( $timestamp <= 0 ) {
			return 0;
		}
		return intval( $timestamp - ( floatval( get_option( 'gmt_offset', 0 ) ) * HOUR_IN_SECONDS ) );
	}

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
				continue;
			}

			if ( ! empty( $access_group_data['status'] ) && self::is_access_status_same( $access_group_data['status'], 'revoked' ) ) {
				do_action( 'suremembers_process_aborted_same_status', 'revoke', $access_group_data, $ag_id );
				continue;
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
	 * Completely removes a membership record from a user.
	 *
	 * Unlike revoke(), which keeps the record and flips its status to `revoked`,
	 * this erases the membership from the user altogether. It still performs the
	 * full revoke side effects — un-syncing WordPress roles and emitting the
	 * revoke actions — so role sync, access logging and dependent integrations
	 * (e.g. the Corporate Accounts cascade) behave the same as a revoke.
	 *
	 * @param int   $user_id          Current user.
	 * @param mixed $access_group_ids Array of multiple access groups or a single access group.
	 * @param mixed $synced_roles     Optional. Pre-resolved role slugs keyed by access group ID,
	 *                                or a flat slug array when a single group is passed. Required
	 *                                only when the membership post (and therefore its role meta)
	 *                                is already gone or about to be deleted. Default null.
	 *
	 * @since 1.4.1
	 *
	 * @return void
	 */
	public static function remove( $user_id, $access_group_ids, $synced_roles = null ) {
		$user_id = absint( $user_id );

		if ( empty( $user_id ) || empty( $access_group_ids ) ) {
			return;
		}

		if ( ! is_array( $access_group_ids ) ) {
			$ag_id = intval( $access_group_ids );
			if ( empty( $ag_id ) ) {
				return;
			}
			$access_group_ids = [ $ag_id ];
			// A flat slug array is accepted for the single-group form; normalise it to the keyed shape.
			if ( is_array( $synced_roles ) && ! isset( $synced_roles[ $ag_id ] ) ) {
				$synced_roles = [ $ag_id => $synced_roles ];
			}
		} else {
			$access_group_ids = Utils::sanitize_recursively( 'absint', $access_group_ids );
		}

		$access_group_ids = array_values( array_filter( array_map( 'absint', $access_group_ids ) ) );
		if ( empty( $access_group_ids ) ) {
			return;
		}

		$access_groups = get_user_meta( $user_id, SUREMEMBERS_USER_META, true );
		if ( ! is_array( $access_groups ) ) {
			$access_groups = [];
		}

		$listed_group_ids = array_map( 'absint', $access_groups );

		// Drop the removed groups from the user's membership list.
		$access_groups = array_values(
			array_filter(
				$access_groups,
				static function ( $ag_id ) use ( $access_group_ids ) {
					return ! in_array( absint( $ag_id ), $access_group_ids, true );
				}
			)
		);

		update_user_meta( $user_id, SUREMEMBERS_USER_META, $access_groups );

		$user_expire   = get_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, true );
		$expire_edited = false;

		foreach ( $access_group_ids as $ag_id ) {
			$access_group_data = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$ag_id}", true );
			$has_record        = ! empty( $access_group_data ) && is_array( $access_group_data );

			// Nothing to take away: the user neither has a record for this group nor
			// lists it. Bail before touching roles, so removing a membership a user
			// never held cannot strip a role they hold for another reason.
			if ( ! $has_record && ! in_array( $ag_id, $listed_group_ids, true ) ) {
				continue;
			}

			$roles_override = is_array( $synced_roles ) && isset( $synced_roles[ $ag_id ] ) ? $synced_roles[ $ag_id ] : null;

			// Drop the detail record first so the group cannot count itself as an
			// active holder of its own roles in the shared-role check below.
			delete_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$ag_id}" );

			if ( is_array( $user_expire ) && isset( $user_expire[ $ag_id ] ) ) {
				unset( $user_expire[ $ag_id ] );
				$expire_edited = true;
			}

			self::set_update_required( $ag_id );
			self::update_user_role_revoke_grant_access( $ag_id, $user_id, 'revoke', $roles_override );

			// Announce the revoke only for access the user still actually held, so
			// listeners (access logs, cascades) do not double-record a membership
			// that was already revoked.
			$was_active = $has_record && ! self::is_access_status_same( $access_group_data['status'] ?? '', 'revoked' );

			if ( $was_active ) {
				/**
				 * Fires when a user loses access to a membership.
				 *
				 * Emitted for removals as well as revokes — the access is gone
				 * either way, so dependent logic must run in both cases.
				 *
				 * @since 1.0.0
				 *
				 * @param int             $user_id          User ID.
				 * @param int             $ag_id            Access group ID.
				 * @param array<int, int> $access_group_ids All access group IDs in this operation.
				 */
				do_action( 'suremembers_user_access_group_revoked', $user_id, absint( $ag_id ), $access_group_ids );
				do_action( 'suremembers_after_access_revoke', $user_id, $access_group_ids );
			}

			/**
			 * Fires after a membership record has been erased from a user.
			 *
			 * @since 1.4.1
			 *
			 * @param int             $user_id          User ID.
			 * @param int             $ag_id            Access group ID.
			 * @param array<int, int> $access_group_ids All access group IDs in this operation.
			 */
			do_action( 'suremembers_user_access_group_removed', $user_id, absint( $ag_id ), $access_group_ids );
		}

		if ( $expire_edited ) {
			update_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, $user_expire );
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
	 * On revoke, a role is only taken away when no other membership the user
	 * still holds syncs that same role — otherwise revoking one membership would
	 * strip a role another active membership is entitled to grant.
	 *
	 * @param int    $access_group_id Access group id.
	 * @param int    $user_id User id.
	 * @param string $action Revoke or grant.
	 * @param mixed  $roles Optional. Role slugs to act on, overriding the access group's
	 *                      own meta. Needed when the access group post is being deleted and
	 *                      its role meta can no longer be read. Default null.
	 *
	 * @since 1.1.0
	 */
	public static function update_user_role_revoke_grant_access( $access_group_id, $user_id, $action, $roles = null ) {
		$get_assign_roles = $roles === null ? get_post_meta( $access_group_id, SUREMEMBERS_USER_ROLES, true ) : $roles;
		if ( empty( $get_assign_roles ) || ! is_array( $get_assign_roles ) ) {
			return;
		}
		$get_current_user = new \WP_User( $user_id );
		if ( ! is_array( $get_current_user->roles ) ) {
			return;
		}

		$roles_held_elsewhere = $action === 'grant' ? [] : self::get_roles_synced_by_other_memberships( $user_id, $access_group_id );

		foreach ( $get_assign_roles as $roles_value ) {
			if ( ! is_string( $roles_value ) || $roles_value === '' ) {
				continue;
			}

			if ( $action === 'grant' ) {
				if ( ! in_array( $roles_value, $get_current_user->roles, true ) ) {
					$get_current_user->add_role( $roles_value );
				}
			} else {
				if ( in_array( $roles_value, $roles_held_elsewhere, true ) ) {
					continue;
				}

				if ( in_array( $roles_value, $get_current_user->roles, true ) ) {
					$get_current_user->remove_role( $roles_value );
				}
			}
		}
	}

	/**
	 * Collect the roles a user is still entitled to through their other memberships.
	 *
	 * Only memberships that are currently published, hold an `active` record and
	 * have not expired are counted — anything else no longer grants its roles.
	 *
	 * @param int $user_id         User ID.
	 * @param int $exclude_group_id Access group being revoked/removed, which must not vouch for its own roles.
	 *
	 * @since 1.4.1
	 *
	 * @return array<int, string> Role slugs.
	 */
	public static function get_roles_synced_by_other_memberships( $user_id, $exclude_group_id ) {
		$access_group_ids = get_user_meta( absint( $user_id ), SUREMEMBERS_USER_META, true );

		if ( empty( $access_group_ids ) || ! is_array( $access_group_ids ) ) {
			return [];
		}

		$exclude_group_id = absint( $exclude_group_id );
		$roles            = [];

		foreach ( $access_group_ids as $ag_id ) {
			$ag_id = absint( $ag_id );

			if ( empty( $ag_id ) || $ag_id === $exclude_group_id ) {
				continue;
			}

			$detail = get_user_meta( absint( $user_id ), SUREMEMBERS_USER_META . "_{$ag_id}", true );

			if ( ! is_array( $detail ) || ( $detail['status'] ?? '' ) !== 'active' ) {
				continue;
			}

			if ( ! Access_Groups::is_active_access_group( $ag_id ) ) {
				continue;
			}

			if ( Access_Groups::is_expired( $ag_id, absint( $user_id ) ) ) {
				continue;
			}

			$group_roles = get_post_meta( $ag_id, SUREMEMBERS_USER_ROLES, true );

			if ( ! empty( $group_roles ) && is_array( $group_roles ) ) {
				$roles = array_merge( $roles, $group_roles );
			}
		}

		return array_values( array_unique( array_filter( $roles, 'is_string' ) ) );
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
