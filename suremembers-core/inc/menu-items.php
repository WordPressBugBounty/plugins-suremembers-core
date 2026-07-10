<?php
/**
 * Menu Items.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Menu Items
 *
 * @since 1.0.0
 */
class Menu_Items {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'wp_get_nav_menu_items', [ $this, 'allowed_nav_menu_items' ], 10, 1 );
	}

	/**
	 * Returns allowed menu items
	 *
	 * @param array<string, mixed> $items array of menu items.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function allowed_nav_menu_items( $items ) {
		/**
		 * Check to allow all menu access to Admins.
		 *
		 * @since 1.6.0
		 */
		if ( \current_user_can( 'administrator' ) ) {
			return $items;
		}

		$new_items = [];
		foreach ( $items as $item ) {
			$item_meta           = get_post_meta( $item->ID, SUREMEMBERS_ACCESS_GROUPS, true );
			$menu_user_condition = get_post_meta( $item->ID, SUREMEMBERS_MENU_USER_CONDITION, true );

			if ( empty( $item_meta ) || ! is_array( $item_meta ) ) {
				$new_items[] = $item;
				continue;
			}

			$check_user_has_access = Access_Groups::check_if_user_has_access( $item_meta );

			if ( $menu_user_condition === 'is_in' && $check_user_has_access ) {
				$new_items[] = $item;
			}

			// Handling the not_in condition.
			if ( $menu_user_condition === 'is_not_in' && ! $check_user_has_access ) {
				$new_items[] = $item;
			}
		}
		return $new_items;
	}
}
