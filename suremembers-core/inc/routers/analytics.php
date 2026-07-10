<?php
/**
 * Analytics Router - Handles membership analytics API endpoints.
 *
 * @package SureMembers\Inc\Routers
 *
 * @since x.x.x
 */

namespace SureMembersCore\Inc\Routers;

use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Access_Logs;
use SureMembersCore\Inc\Services\Abilities\Registry;
use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Class Analytics Router.
 *
 * @since x.x.x
 */
class Analytics {
	use Get_Instance;

	/**
	 * Get membership analytics.
	 * Returns grant/revoke series for the requested timespan plus totals,
	 * per-source breakdown and current status counts per access group.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 * @since x.x.x
	 */
	public function get_analytics( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		$premium_gate = $this->require_premium();
		if ( $premium_gate instanceof \WP_REST_Response ) {
			return $premium_gate;
		}

		$access_group_id = absint( $request->get_param( 'access_group_id' ) );
		$interval        = sanitize_key( (string) $request->get_param( 'interval' ) );
		$from            = sanitize_text_field( (string) $request->get_param( 'from' ) );
		$to              = sanitize_text_field( (string) $request->get_param( 'to' ) );

		if ( ! in_array( $interval, [ 'day', 'week', 'month' ], true ) ) {
			$interval = 'day';
		}

		$date_pattern = '/^\d{4}-\d{2}-\d{2}$/';
		if ( ! preg_match( $date_pattern, $from ) ) {
			$from = gmdate( 'Y-m-d', strtotime( '-29 days', (int) current_time( 'timestamp' ) ) ); //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		}
		if ( ! preg_match( $date_pattern, $to ) ) {
			$to = gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ); //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		}

		$query_args = [
			'access_group_id' => $access_group_id,
			'from'            => $from,
			'to'              => $to,
			'interval'        => $interval,
		];

		$groups        = Access_Groups::get_active();
		$status_counts = [];
		if ( ! empty( $access_group_id ) ) {
			$status_counts[ $access_group_id ] = [
				'active'  => Access_Logs::get_status_count( $access_group_id, 'active' ),
				'revoked' => Access_Logs::get_status_count( $access_group_id, 'revoked' ),
			];
		} else {
			foreach ( array_keys( $groups ) as $group_id ) {
				$status_counts[ $group_id ] = [
					'active'  => Access_Logs::get_status_count( (int) $group_id, 'active' ),
					'revoked' => Access_Logs::get_status_count( (int) $group_id, 'revoked' ),
				];
			}
		}

		$by_group = array_map(
			static function ( $row ) use ( $groups ) {
				$row['label'] = $groups[ $row['access_group_id'] ] ?? get_the_title( $row['access_group_id'] );
				if ( empty( $row['label'] ) ) {
					/* translators: %d: access group ID. */
					$row['label'] = sprintf( __( 'Membership #%d', 'suremembers-core' ), $row['access_group_id'] );
				}
				return $row;
			},
			Access_Logs::get_group_breakdown( $query_args )
		);

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'series'        => Access_Logs::get_stats( $query_args ),
					'by_group'      => $by_group,
					'totals'        => Access_Logs::get_totals( $query_args ),
					'status_counts' => $status_counts,
					'groups'        => $groups,
					'backfill'      => get_option( Access_Logs::BACKFILL_OPTION, '' ),
					'from'          => $from,
					'to'            => $to,
					'interval'      => $interval,
				],
			],
			200
		);
	}

	/**
	 * Get the recent membership activity feed (latest grant/revoke events).
	 * Independent of the analytics date range.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 * @since x.x.x
	 */
	public function get_recent_activity( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		$limit = absint( $request->get_param( 'limit' ) );
		$limit = $limit > 0 ? min( $limit, 50 ) : 15;

		$groups = Access_Groups::get_active();

		$activity = array_map(
			static function ( $row ) use ( $groups ) {
				$row['membership'] = $groups[ $row['access_group_id'] ] ?? get_the_title( $row['access_group_id'] );
				if ( empty( $row['membership'] ) ) {
					/* translators: %d: access group ID. */
					$row['membership'] = sprintf( __( 'Membership #%d', 'suremembers-core' ), $row['access_group_id'] );
				}
				return $row;
			},
			Access_Logs::get_recent_activity( $limit )
		);

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $activity,
			],
			200
		);
	}

	/**
	 * Get memberships expiring within a look-ahead window.
	 *
	 * The expiration logic lives in the SureMembers Pro `find-expiring-memberships`
	 * ability, resolved here through the Core abilities registry so Core carries
	 * no hard dependency on the Pro class. The premium gate above guarantees Pro
	 * is active (and the ability registered) by the time we reach this point.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 * @since x.x.x
	 */
	public function get_expiring_memberships( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		$premium_gate = $this->require_premium();
		if ( $premium_gate instanceof \WP_REST_Response ) {
			return $premium_gate;
		}

		$days = absint( $request->get_param( 'days' ) );
		$days = $days > 0 ? min( $days, 365 ) : 30;

		/**
		 * Abilities registry instance.
		 *
		 * Get_Instance::get_instance() is typed as object, so hint the concrete type.
		 *
		 * @var Registry $registry
		 */
		$registry = Registry::get_instance();
		$registry->maybe_init();
		$ability = $registry->get( 'find-expiring-memberships' );
		if ( $ability === null ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'This feature requires SureMembers Pro.', 'suremembers-core' ),
				],
				403
			);
		}

		$result = $ability->execute(
			[
				'days'          => $days,
				'membership_id' => 0,
			]
		);

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $result['data'] ?? [
					'expiring' => [],
					'total'    => 0,
				],
			],
			200
		);
	}

	/**
	 * Premium gate for analytics endpoints that surface historical and
	 * expiration intelligence. Recent Activity and Membership Overview remain
	 * free; these endpoints are SureMembers Pro only so the data cannot be
	 * pulled for free (including via the WP Abilities / MCP surface).
	 *
	 * @return \WP_REST_Response|null Error response when Pro is inactive, null otherwise.
	 * @since x.x.x
	 */
	private function require_premium() {
		if ( apply_filters( 'suremembers_is_premium_active', false ) ) {
			return null;
		}

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => __( 'This feature requires SureMembers Pro.', 'suremembers-core' ),
			],
			403
		);
	}
}
