<?php
/**
 * Helper.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Inc;

defined( 'ABSPATH' ) || exit;

/**
 * Helper
 *
 * @since 0.0.1
 */
class Helper {
	/**
	 * Check weather the WooCommerce is active or not.
	 *
	 * @since 1.10.0
	 */
	public static function is_woocommerce_active() {
		return function_exists( 'WC' );
	}

	/**
	 * Grants access to provided access groups
	 *
	 * @param int   $user_id current user.
	 * @param mixed $access_group_ids array of multiple access groups or single access group can be provided.
	 *
	 * @since 1.0.0
	 */
	public function grant_access( $user_id, $access_group_ids ) {
		Access::grant( $user_id, $access_group_ids, 'suretrigger' );
	}

	/**
	 * Revokes user access from provided access groups
	 *
	 * @param int   $user_id current user.
	 * @param mixed $access_group_ids array of multiple access groups or single access group can be provided.
	 *
	 * @since 1.0.0
	 */
	public function revoke_access( $user_id, $access_group_ids ) {
		Access::revoke( $user_id, $access_group_ids );
	}
}
