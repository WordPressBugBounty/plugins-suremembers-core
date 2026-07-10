<?php
/**
 * SureDash integration.
 *
 * Bridges SureMembers access-group restrictions into SureDash's own protection
 * surfaces — the things the generic REST guard ({@see \SureMembersCore\Inc\Rest_Restriction})
 * cannot reach because they are SureDash-specific:
 *
 *  1. SureDash's protection model (`suredash_is_post_protected()`), which powers
 *     single-content rendering, `/wp/v2/search`, and `/suredash/v1/search`.
 *     SureDash asks third-party plugins to participate via the
 *     `suredash_post_restriction_ruleset` filter — we hook it here so all those
 *     surfaces honor access groups in one shot.
 *  2. People / user search (`/suredash/v1/search?type=people` and
 *     `/suredash/v1/search-user`), which expose the user directory. We block
 *     anonymous enumeration by default.
 *
 * The default WordPress REST controllers for the SureDash CPTs
 * (`/wp/v2/community-content`, `/wp/v2/community-post`, `/wp/v2/portal`) are
 * covered by the generic {@see \SureMembersCore\Inc\Rest_Restriction} guard,
 * since those CPTs are public and restrictable like any other.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Integrations;

use SureMembersCore\Inc\Restricted;
use SureMembersCore\Inc\Settings;
use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * SureDash integration.
 *
 * @since 1.1.0
 */
class Suredash {
	use Get_Instance;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		// Bridge into SureDash's protection model. A single hook makes SureDash
		// honor access groups everywhere it already checks protection (single
		// content rendering, /wp/v2/search, /suredash/v1/search).
		add_filter( 'suredash_post_restriction_ruleset', [ $this, 'apply_access_group_restriction' ], 10, 2 );

		// People / user search — block anonymous enumeration by default.
		add_filter( 'suredash_search_people_query_args', [ $this, 'restrict_user_search_args' ], 10, 2 );
		add_filter( 'suredash_search_user_query_args', [ $this, 'restrict_user_search_args' ], 10, 2 );
	}

	/**
	 * Bridge callback — declare a SureDash post restricted when the current
	 * user lacks access to it via SureMembers access groups.
	 *
	 * @since 1.1.0
	 * @param array<string, mixed>|mixed $ruleset SureDash restriction ruleset ({ status: bool, content: string }).
	 * @param int                        $post_id Post being evaluated.
	 * @return array<string, mixed>
	 */
	public function apply_access_group_restriction( $ruleset, $post_id ): array {
		$ruleset = is_array( $ruleset ) ? $ruleset : [];

		// Respect an existing restriction declared by SureDash or another plugin.
		if ( ! empty( $ruleset['status'] ) ) {
			return $ruleset;
		}

		if ( ! $this->is_restricted( absint( $post_id ) ) ) {
			return $ruleset;
		}

		$ruleset['status'] = true;

		if ( empty( $ruleset['content'] ) ) {
			$loop_content = Settings::get_custom_content_data( 'loop_content' );
			$content      = ! empty( $loop_content['value'] ) ? $loop_content['value'] : ( $loop_content['default'] ?? '' );
			if ( ! empty( $content ) ) {
				$ruleset['content'] = $content;
			}
		}

		return $ruleset;
	}

	/**
	 * Block anonymous user enumeration through SureDash people / user search.
	 *
	 * Logged-in users are unaffected (finding other members is normal community
	 * behavior). The policy is filterable so it can be tightened — e.g. to
	 * members-only — without editing this integration.
	 *
	 * @since 1.1.0
	 * @param array<string, mixed>|mixed $query_args WP_User_Query args.
	 * @param array<string, mixed>       $args       Full search args (unused by default).
	 * @return array<string, mixed>|mixed
	 */
	public function restrict_user_search_args( $query_args, $args = [] ) {
		unset( $args );

		/**
		 * Filter whether the current request may enumerate users via SureDash
		 * search. Defaults to logged-in users only.
		 *
		 * @since 1.1.0
		 * @param bool $can_search Whether user search is allowed for this request.
		 */
		$can_search = apply_filters( 'suremembers_allow_suredash_user_search', is_user_logged_in() );

		if ( ! $can_search && is_array( $query_args ) ) {
			// Force an empty result set without short-circuiting SureDash.
			$query_args['include'] = [ 0 ];
		}

		return $query_args;
	}

	/**
	 * Whether the current user is restricted from the given post (with a
	 * manager bypass).
	 *
	 * @since 1.1.0
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	private function is_restricted( int $post_id ): bool {
		if ( ! $post_id || current_user_can( 'manage_options' ) ) {
			return false;
		}
		return Restricted::is_restricted_for_user( $post_id );
	}
}
