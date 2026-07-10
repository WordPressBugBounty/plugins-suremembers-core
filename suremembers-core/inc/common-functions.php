<?php
/**
 * Plugin Common functions.
 *
 * @package SureMembers
 *
 * @since 1.10.11
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SureMembersCore\Inc\Access;

/**
 * Adds a user to the specified access groups.
 *
 * @param int   $user_id current user.
 * @param mixed $access_group_ids array of multiple access groups or single access group can be provided.
 *
 * @since 1.10.11
 */
function suremembers_add_user_to_group( $user_id, $access_group_ids ) {
	Access::grant( $user_id, $access_group_ids );
}

/**
 * Removes a user from the specified access groups.
 *
 * @param int   $user_id current user.
 * @param mixed $access_group_ids array of multiple access groups or single access group can be provided.
 *
 * @since 1.10.11
 */
function suremembers_remove_user_from_group( $user_id, $access_group_ids ) {
	Access::revoke( $user_id, $access_group_ids );
}

/**
 * Returns the Router instance for registering REST API routes.
 *
 * @return \SureMembersCore\Inc\Services\Router
 * @since 1.0.0
 */
function sm_route() {
	return \SureMembersCore\Inc\Services\Router::get_instance();
}
