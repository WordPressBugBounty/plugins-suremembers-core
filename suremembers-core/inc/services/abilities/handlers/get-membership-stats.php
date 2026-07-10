<?php
/**
 * Get Membership Stats Ability.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Get_Membership_Stats class.
 *
 * Per-access-group analytics: active/revoked member counts and a breakdown of
 * the integration sources that granted access.
 *
 * @since 1.1.0
 */
class Get_Membership_Stats extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_id(): string {
		return 'get-membership-stats';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_name(): string {
		return __( 'Get Membership Stats', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_description(): string {
		return __( 'Retrieves detailed statistics for a single access group: active and revoked member counts plus a breakdown of how access was granted (default, woocommerce, surecart, etc.).', 'suremembers-core' );
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
			'membership_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The access group (membership) ID. Call list-memberships first to find it.', 'suremembers-core' ),
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
			'type'        => 'object',
			'description' => __( 'Member counts and integration breakdown for the access group.', 'suremembers-core' ),
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
		return 'Requires a membership_id from list-memberships. Counts are derived from per-user access records, so they reflect the current state including revoked grants.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$membership_id = (int) $params['membership_id'];

		$post = get_post( $membership_id );
		if ( ! $post || $post->post_type !== SUREMEMBERS_POST_TYPE ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'Membership not found.', 'suremembers-core' ) ],
			];
		}

		global $wpdb;

		$meta_key = SUREMEMBERS_USER_META . '_' . $membership_id;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_col(
			$wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $meta_key )
		);

		$active       = 0;
		$revoked      = 0;
		$integrations = [];

		foreach ( $rows as $row ) {
			$detail = maybe_unserialize( $row );
			if ( ! is_array( $detail ) ) {
				continue;
			}

			$status = $detail['status'] ?? '';
			if ( $status === 'active' ) {
				$active++;
			} elseif ( $status === 'revoked' ) {
				$revoked++;
			}

			$integration                  = ! empty( $detail['integration'] ) ? (string) $detail['integration'] : 'default';
			$integrations[ $integration ] = ( $integrations[ $integration ] ?? 0 ) + 1;
		}

		return [
			'success' => true,
			'data'    => [
				'id'                    => $membership_id,
				'title'                 => $post->post_title,
				'status'                => $post->post_status,
				'active_members'        => $active,
				'revoked_members'       => $revoked,
				'total_grants'          => count( $rows ),
				'integration_breakdown' => $integrations,
			],
		];
	}
}
