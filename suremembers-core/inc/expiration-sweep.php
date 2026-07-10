<?php
/**
 * Expiration Sweep.
 *
 * Proactively revokes expired access-group memberships on a schedule.
 *
 * Expiration is otherwise only evaluated lazily, when the affected user visits a
 * protected page (see Template_Redirect::handle_access_group_expiration). That
 * means an owner whose access expires but who never returns keeps their access
 * "active" indefinitely — and any dependent logic hooked to
 * `suremembers_user_access_group_revoked` (e.g. the Corporate Accounts cascade
 * in SureMembers Pro) never fires. This scheduled sweep closes that gap by
 * routing expirations through Access::revoke(), which emits the revoke action.
 *
 * The sweep runs on Action Scheduler: a recurring action fans the work out into
 * batched, per-group async actions so large member bases are processed in small
 * chunks instead of a single long-running request. If Action Scheduler is not
 * available the sweep falls back to a WP-Cron event.
 *
 * @package SureMembersCore
 *
 * @since x.x.x
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Class Expiration_Sweep.
 *
 * @since x.x.x
 */
class Expiration_Sweep {
	use Get_Instance;

	/**
	 * Recurring sweep hook. Fans work out into per-group batches.
	 *
	 * @since x.x.x
	 */
	public const CRON_HOOK = 'suremembers_expiration_check';

	/**
	 * Async per-group batch hook.
	 *
	 * @since x.x.x
	 */
	public const GROUP_HOOK = 'suremembers_expiration_check_group';

	/**
	 * Action Scheduler group label.
	 *
	 * @since x.x.x
	 */
	public const AS_GROUP = 'suremembers-expiration';

	/**
	 * Constructor: register handlers and ensure the sweep is scheduled.
	 *
	 * @since x.x.x
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run_check' ] );
		add_action( self::GROUP_HOOK, [ $this, 'process_group' ], 10, 2 );

		// Schedule on `init` so Action Scheduler's data store is ready. Self-healing:
		// covers fresh installs, reactivations, and sites that pre-date this feature
		// without relying on the activation hook.
		add_action( 'init', [ $this, 'maybe_schedule' ], 20 );

		register_deactivation_hook( SUREMEMBERS_CORE_FILE, [ $this, 'clear_schedule' ] );
	}

	/**
	 * Schedule the recurring expiration check if it is not already scheduled.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function maybe_schedule(): void {
		if ( $this->action_scheduler_ready() ) {
			if ( ! as_has_scheduled_action( self::CRON_HOOK, [], self::AS_GROUP ) ) {
				/**
				 * Filter the interval (in seconds) between expiration sweeps.
				 *
				 * @since x.x.x
				 *
				 * @param int $interval Interval in seconds. Default 12 hours.
				 */
				$interval = (int) apply_filters( 'suremembers_expiration_check_interval', 12 * HOUR_IN_SECONDS );

				as_schedule_recurring_action( time(), max( 1, $interval ), self::CRON_HOOK, [], self::AS_GROUP );
			}

			// Drop any legacy WP-Cron event left by an earlier fallback run.
			if ( wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}

			return;
		}

		// Fallback: WP-Cron when Action Scheduler is unavailable.
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			/**
			 * Filter the recurrence of the WP-Cron fallback sweep.
			 *
			 * Must be a valid registered cron schedule (see wp_get_schedules()).
			 *
			 * @since x.x.x
			 *
			 * @param string $recurrence Cron schedule slug. Default 'twicedaily'.
			 */
			$recurrence = apply_filters( 'suremembers_expiration_check_recurrence', 'twicedaily' );

			wp_schedule_event( time(), $recurrence, self::CRON_HOOK );
		}
	}

	/**
	 * Clear all scheduled expiration work (plugin deactivation).
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function clear_schedule(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::CRON_HOOK, [], self::AS_GROUP );
			as_unschedule_all_actions( self::GROUP_HOOK, [], self::AS_GROUP );
		}

		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Recurring entry point: fan the sweep out into per-group batches.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function run_check(): void {
		$group_ids = $this->get_expiration_enabled_groups();

		if ( $this->action_scheduler_ready() ) {
			foreach ( $group_ids as $ag_id ) {
				// Avoid piling up duplicate batches if a prior sweep is still running.
				if ( ! as_has_scheduled_action( self::GROUP_HOOK, [ $ag_id, 0 ], self::AS_GROUP ) ) {
					as_enqueue_async_action( self::GROUP_HOOK, [ $ag_id, 0 ], self::AS_GROUP );
				}
			}
		} else {
			// Synchronous fallback: walk every batch inline.
			foreach ( $group_ids as $ag_id ) {
				$offset = 0;
				do {
					$processed = $this->process_group_batch( (int) $ag_id, $offset );
					$offset   += $this->get_batch_size();
				} while ( $processed === $this->get_batch_size() );
			}
		}

		/**
		 * Fires after an expiration sweep has been dispatched.
		 *
		 * @since x.x.x
		 *
		 * @param array<int, int> $group_ids Access group IDs that were swept.
		 */
		do_action( 'suremembers_expiration_check_complete', $group_ids );
	}

	/**
	 * Process a single batch of members for one access group.
	 *
	 * Revokes any member of the batch whose membership has expired, then queues
	 * the next batch when the page was full (Action Scheduler path only).
	 *
	 * @since x.x.x
	 *
	 * @param int $ag_id  Access group ID.
	 * @param int $offset Member query offset.
	 *
	 * @return void
	 */
	public function process_group( $ag_id, $offset = 0 ): void {
		$ag_id  = absint( $ag_id );
		$offset = absint( $offset );
		$batch  = $this->get_batch_size();

		$inspected = $this->process_group_batch( $ag_id, $offset );

		// A full page means there may be more members; queue the next batch.
		if ( $inspected === $batch && $this->action_scheduler_ready() ) {
			as_enqueue_async_action( self::GROUP_HOOK, [ $ag_id, $offset + $batch ], self::AS_GROUP );
		}
	}

	/**
	 * Whether Action Scheduler is loaded and usable.
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	private function action_scheduler_ready(): bool {
		return function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_enqueue_async_action' )
			&& function_exists( 'as_has_scheduled_action' );
	}

	/**
	 * Revoke any expired members in a single batch.
	 *
	 * @since x.x.x
	 *
	 * @param int $ag_id  Access group ID.
	 * @param int $offset Member query offset.
	 *
	 * @return int Number of members inspected in this batch.
	 */
	private function process_group_batch( int $ag_id, int $offset ): int {
		$member_ids = $this->get_member_ids( $ag_id, $this->get_batch_size(), $offset );

		foreach ( $member_ids as $user_id ) {
			$data = get_user_meta( absint( $user_id ), SUREMEMBERS_USER_META . "_{$ag_id}", true );

			if ( ! is_array( $data ) || ( $data['status'] ?? '' ) !== 'active' ) {
				continue;
			}

			if ( Access_Groups::is_expired( $ag_id, $user_id ) ) {
				Access::revoke( $user_id, $ag_id );
			}
		}

		return count( $member_ids );
	}

	/**
	 * Number of members processed per batch.
	 *
	 * @since x.x.x
	 *
	 * @return int
	 */
	private function get_batch_size(): int {
		/**
		 * Filter the number of members inspected per expiration batch.
		 *
		 * @since x.x.x
		 *
		 * @param int $size Batch size. Default 100.
		 */
		return max( 1, (int) apply_filters( 'suremembers_expiration_batch_size', 100 ) );
	}

	/**
	 * Get the IDs of published access groups that have expiration enabled.
	 *
	 * @since x.x.x
	 *
	 * @return array<int, int> Access group post IDs.
	 */
	private function get_expiration_enabled_groups(): array {
		$group_ids = get_posts(
			[
				'post_type'   => SUREMEMBERS_POST_TYPE,
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => SUREMEMBERS_PLAN_EXPIRATION, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			]
		);

		$enabled = [];

		foreach ( $group_ids as $group_id ) {
			$expiration = get_post_meta( absint( $group_id ), SUREMEMBERS_PLAN_EXPIRATION, true );

			if ( is_array( $expiration ) && filter_var( $expiration['enable'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
				$enabled[] = absint( $group_id );
			}
		}

		return $enabled;
	}

	/**
	 * Get a page of member IDs that hold access to a group.
	 *
	 * Pagination is on the raw membership query; the active-status check happens
	 * in process_group() so the page size stays predictable for batch chaining.
	 *
	 * @since x.x.x
	 *
	 * @param int $ag_id  Access group ID.
	 * @param int $limit  Maximum members to return.
	 * @param int $offset Query offset.
	 *
	 * @return array<int, int> Member user IDs.
	 */
	private function get_member_ids( int $ag_id, int $limit, int $offset ): array {
		return get_users(
			[
				'fields'       => 'ids',
				'meta_key'     => SUREMEMBERS_USER_META . "_{$ag_id}", // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare' => 'EXISTS',
				'number'       => $limit,
				'offset'       => $offset,
				'orderby'      => 'ID',
				'order'        => 'ASC',
			]
		);
	}
}
