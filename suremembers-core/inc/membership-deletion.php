<?php
/**
 * Membership Deletion.
 *
 * Cleans up member records when a membership (access group) is permanently deleted.
 *
 * Deleting the membership post takes its `suremembers_user_roles` meta with it, so
 * any WordPress role that membership had synced onto its members becomes orphaned
 * the moment the post is gone — nothing left on the site knows which role to strip.
 * This class captures the synced role slugs while the post still exists, then drains
 * the membership's members in batches, routing each one through Access::remove() so
 * roles are un-synced and the usual revoke actions fire.
 *
 * The drain runs on Action Scheduler when available so large member bases are
 * processed in small chunks instead of one long-running delete request; otherwise it
 * falls back to processing inline.
 *
 * @package SureMembersCore
 *
 * @since 1.4.1
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Class Membership_Deletion.
 *
 * @since 1.4.1
 */
class Membership_Deletion {
	use Get_Instance;

	/**
	 * Async batch hook.
	 *
	 * @since 1.4.1
	 */
	public const BATCH_HOOK = 'suremembers_deleted_membership_cleanup';

	/**
	 * Action Scheduler group label.
	 *
	 * @since 1.4.1
	 */
	public const AS_GROUP = 'suremembers-membership-deletion';

	/**
	 * Constructor: register handlers.
	 *
	 * @since 1.4.1
	 */
	public function __construct() {
		// `before_delete_post` — not `deleted_post` — so the role meta is still readable.
		add_action( 'before_delete_post', [ $this, 'handle_delete' ], 10, 2 );
		add_action( self::BATCH_HOOK, [ $this, 'process_batch' ], 10, 2 );
	}

	/**
	 * Revoke every member of a membership that is about to be deleted.
	 *
	 * @param int           $post_id Post ID being deleted.
	 * @param \WP_Post|null $post    Post object, when WordPress passes one.
	 *
	 * @since 1.4.1
	 *
	 * @return void
	 */
	public function handle_delete( $post_id, $post = null ): void {
		$post_id = absint( $post_id );

		if ( empty( $post_id ) ) {
			return;
		}

		$post_type = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );

		if ( $post_type !== SUREMEMBERS_POST_TYPE ) {
			return;
		}

		$roles = Access_Groups::get_selected_user_roles( $post_id );
		$roles = array_values( array_filter( $roles, 'is_string' ) );

		$this->process_batch( $post_id, $roles );
	}

	/**
	 * Remove one batch of members from a deleted membership.
	 *
	 * Access::remove() deletes the record this query matches on, so each pass
	 * drains the queue rather than paginating through it.
	 *
	 * @param int                $access_group_id Access group ID.
	 * @param array<int, string> $roles           Role slugs the membership synced, captured before deletion.
	 *
	 * @since 1.4.1
	 *
	 * @return void
	 */
	public function process_batch( $access_group_id, $roles = [] ): void {
		$access_group_id = absint( $access_group_id );

		if ( empty( $access_group_id ) ) {
			return;
		}

		$roles      = is_array( $roles ) ? array_values( array_filter( $roles, 'is_string' ) ) : [];
		$batch_size = $this->get_batch_size();
		$member_ids = $this->get_member_ids( $access_group_id, $batch_size );

		foreach ( $member_ids as $user_id ) {
			Access::remove( absint( $user_id ), $access_group_id, $roles );
		}

		if ( count( $member_ids ) < $batch_size ) {
			return;
		}

		// A full page means more members remain.
		if ( $this->action_scheduler_ready() ) {
			as_enqueue_async_action( self::BATCH_HOOK, [ $access_group_id, $roles ], self::AS_GROUP );
			return;
		}

		// No Action Scheduler: drain inline. Bounded so a stuck record cannot spin forever.
		$guard     = 0;
		$processed = $batch_size;
		while ( $processed === $batch_size && $guard < 1000 ) {
			$member_ids = $this->get_member_ids( $access_group_id, $batch_size );

			foreach ( $member_ids as $user_id ) {
				Access::remove( absint( $user_id ), $access_group_id, $roles );
			}

			$processed = count( $member_ids );
			$guard++;
		}
	}

	/**
	 * Get a page of user IDs holding a record for the membership.
	 *
	 * Includes revoked records so their now-orphaned meta is cleaned up too.
	 *
	 * @param int $access_group_id Access group ID.
	 * @param int $limit           Maximum members to return.
	 *
	 * @since 1.4.1
	 *
	 * @return array<int, int> Member user IDs.
	 */
	private function get_member_ids( int $access_group_id, int $limit ): array {
		$user_ids = get_users(
			[
				'fields'       => 'ids',
				'meta_key'     => SUREMEMBERS_USER_META . "_{$access_group_id}", // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare' => 'EXISTS',
				'number'       => $limit,
				'orderby'      => 'ID',
				'order'        => 'ASC',
			]
		);

		return is_array( $user_ids ) ? array_map( 'absint', $user_ids ) : [];
	}

	/**
	 * Whether Action Scheduler is loaded and usable.
	 *
	 * @since 1.4.1
	 *
	 * @return bool
	 */
	private function action_scheduler_ready(): bool {
		return function_exists( 'as_enqueue_async_action' );
	}

	/**
	 * Number of members processed per batch.
	 *
	 * @since 1.4.1
	 *
	 * @return int
	 */
	private function get_batch_size(): int {
		/**
		 * Filter the number of members cleaned up per deleted-membership batch.
		 *
		 * @since 1.4.1
		 *
		 * @param int $size Batch size. Default 100.
		 */
		return max( 1, (int) apply_filters( 'suremembers_membership_deletion_batch_size', 100 ) );
	}
}
