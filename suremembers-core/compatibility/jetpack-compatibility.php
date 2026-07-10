<?php
/**
 * Compatibility with Jetpack Plugin.
 *
 * @package suremembers
 *
 * @since 1.7.1
 */

namespace SureMembersCore\Compatibility;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Jetpack Compatibility Class.
 *
 * @since 1.7.1
 */
class Jetpack_Compatibility {
	use Get_Instance;

	/**
	 * Class constructor.
	 *
	 * @since 1.7.1
	 */
	public function __construct() {
		/**
		 * Fix for the Jetpack notification shortcut bug.
		 *
		 * Dequeueing the JetPack's notification shortcut trigger as it was appearing in the SureMembers Membership and settings pages when typing on custom input components.
		 */
		add_action( 'admin_head', [ $this, 'dequeue_jetpack_scripts' ], 200 );
	}

	/**
	 * Dequeue Jetpack script in SureMembers settings screen.
	 *
	 * @since 1.7.1
	 */
	public function dequeue_jetpack_scripts() {
		$screen = get_current_screen();

		if ( is_null( $screen ) || ! in_array( $screen->id, [ SUREMEMBERS_POST_TYPE . '_page_suremembers_rules', SUREMEMBERS_POST_TYPE . '_page_suremembers_settings' ], true ) ) {
			return;
		}

		wp_dequeue_script( 'wpcom-notes-common' );
		wp_dequeue_script( 'wpcom-notes-admin-bar' );
		wp_dequeue_style( 'wpcom-notes-admin-bar' );
		wp_dequeue_style( 'noticons' );
	}
}
