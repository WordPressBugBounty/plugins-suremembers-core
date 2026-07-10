<?php
/**
 * Get Members By Status Ability.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Get_Members_By_Status class.
 *
 * Lists members whose grant for a group is in a given state (active or
 * revoked), or who are expired. Optionally scoped to a single group.
 *
 * @since 1.1.0
 */
class Get_Members_By_Status extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_id(): string {
		return 'get-members-by-status';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_name(): string {
		return __( 'Get Members By Status', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_description(): string {
		return __( 'Lists members filtered by their membership status: active, revoked, or expired. Optionally scoped to a single access group.', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_category(): string {
		return 'analytics';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_parameters(): array {
		return [
			'status'        => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'Membership status to filter by.', 'suremembers-core' ),
				'enum'        => [ 'active', 'revoked', 'expired' ],
			],
			'membership_id' => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 0,
				'description' => __( 'Limit to a single access group ID. 0 (default) checks all published groups.', 'suremembers-core' ),
			],
			'limit'         => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 100,
				'description' => __( 'Maximum number of member records to return.', 'suremembers-core' ),
			],
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_returns(): array {
		return [
			'type'        => 'array',
			'description' => __( 'Member records matching the requested status.', 'suremembers-core' ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_annotations(): array {
		return [
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'idempotentHint'  => true,
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_instructions(): string {
		return 'The "expired" status checks each active grant against its expiration date. For renewal lists with exact dates use find-expiring-memberships instead.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$status        = (string) $params['status'];
		$membership_id = (int) $params['membership_id'];
		$limit         = max( 1, (int) $params['limit'] );

		if ( $membership_id > 0 ) {
			$groups = [ $membership_id => get_the_title( $membership_id ) ];
		} else {
			$groups = Access_Groups::get_active();
		}

		// Active grants underlie all three statuses; expired is a refinement of active.
		$match_meta = $status === 'revoked' ? '"revoked"' : '"active"';

		$results = [];

		foreach ( $groups as $group_id => $group_title ) {
			$group_id = (int) $group_id;
			$rows     = $this->get_user_ids_by_meta( $group_id, $match_meta );

			foreach ( $rows as $user_id ) {
				if ( count( $results ) >= $limit ) {
					break 2;
				}

				if ( $status === 'expired' && ! Access_Groups::is_expired( $group_id, $user_id ) ) {
					continue;
				}

				if ( $status === 'active' && Access_Groups::is_expired( $group_id, $user_id ) ) {
					continue;
				}

				$user = get_userdata( $user_id );
				if ( ! $user ) {
					continue;
				}

				$results[] = [
					'user_id'       => $user_id,
					'user_email'    => $user->user_email,
					'user_name'     => $user->display_name,
					'membership_id' => $group_id,
					'membership'    => $group_title,
					'status'        => $status,
				];
			}
		}

		return [
			'success' => true,
			'data'    => [
				'status'  => $status,
				'members' => $results,
				'total'   => count( $results ),
			],
		];
	}

	/**
	 * Get user IDs whose grant meta for a group matches a status token.
	 *
	 * @since 1.1.0
	 *
	 * @param int    $group_id   Access group ID.
	 * @param string $status_str Serialized status token, e.g. '"active"'.
	 * @return array<int, int>
	 */
	private function get_user_ids_by_meta( int $group_id, string $status_str ): array {
		global $wpdb;

		$meta_key   = SUREMEMBERS_USER_META . '_' . $group_id;
		$meta_value = '%' . $wpdb->esc_like( $status_str ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
				$meta_key,
				$meta_value
			)
		);

		return array_map( 'intval', $ids );
	}
}
