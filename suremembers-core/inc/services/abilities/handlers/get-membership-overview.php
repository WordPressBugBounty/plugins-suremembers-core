<?php
/**
 * Get Membership Overview Ability.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Get_Membership_Overview class.
 *
 * Site-level membership KPIs: access group counts and active member totals.
 *
 * @since 1.1.0
 */
class Get_Membership_Overview extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_id(): string {
		return 'get-membership-overview';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_name(): string {
		return __( 'Get Membership Overview', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_description(): string {
		return __( 'Retrieves site-wide membership KPIs: total access groups (published and draft), the number of members with at least one active membership, and the total active membership grants across all groups.', 'suremembers-core' );
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
		return [];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_returns(): array {
		return [
			'type'       => 'object',
			'properties' => [
				'access_groups'       => [
					'type'        => 'object',
					'description' => __( 'Access group counts by status.', 'suremembers-core' ),
				],
				'active_members'      => [
					'type'        => 'integer',
					'description' => __( 'Distinct users with at least one active membership.', 'suremembers-core' ),
				],
				'total_active_grants' => [
					'type'        => 'integer',
					'description' => __( 'Total active membership grants across all groups.', 'suremembers-core' ),
				],
			],
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
		return 'Use as the first call to understand the overall membership footprint. For per-group breakdowns call list-memberships, and for a single group call get-membership-stats.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		unset( $params );

		global $wpdb;

		$counts    = wp_count_posts( SUREMEMBERS_POST_TYPE );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$draft     = isset( $counts->draft ) ? (int) $counts->draft : 0;

		$meta_key_pattern = $wpdb->esc_like( SUREMEMBERS_USER_META . '_' ) . '%';
		$meta_value_like  = '%' . $wpdb->esc_like( '"active"' ) . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$active_members = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key LIKE %s AND meta_value LIKE %s",
				$meta_key_pattern,
				$meta_value_like
			)
		);

		$total_grants = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key LIKE %s AND meta_value LIKE %s",
				$meta_key_pattern,
				$meta_value_like
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery

		return [
			'success' => true,
			'data'    => [
				'access_groups'       => [
					'published' => $published,
					'draft'     => $draft,
					'total'     => $published + $draft,
				],
				'active_members'      => $active_members,
				'total_active_grants' => $total_grants,
			],
		];
	}
}
