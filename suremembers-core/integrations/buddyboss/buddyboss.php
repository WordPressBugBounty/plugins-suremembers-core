<?php
/**
 * BuddyBoss Integration.
 *
 * @package suremembers
 *
 * @since 1.4.0
 */

namespace SureMembersCore\Integrations\Buddyboss;

use SureMembersCore\Inc\Access;
use SureMembersCore\Inc\Settings;
use SureMembersCore\Inc\Traits\Get_Instance;
use SureMembersCore\Inc\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * BuddyBoss Integration
 *
 * @since 1.4.0
 */
class Buddyboss {
	use Get_Instance;

	/**
	 * Constructor function
	 *
	 * @since 1.4.0
	 */
	public function __construct() {
		add_filter( 'bb_get_access_control_plugins_lists', [ $this, 'add_suremembers_option' ] );
		add_action( 'bp_core_activated_user', [ $this, 'add_access_group_on_activation' ], 1 );
		add_filter( 'suremembers_filter_template_control_requires_post_id', [ $this, 'filter_required_post_id' ] );
	}

	/**
	 * Filter is post_id required based on buddyboss page.
	 *
	 * @param bool $required Is Post ID Required.
	 *
	 * @since 1.8.0
	 *
	 * @return bool Updated value.
	 */
	public function filter_required_post_id( $required ) {
		return function_exists( 'is_buddypress' ) && is_buddypress() ? false : $required;
	}

	/**
	 * Add SureMembers in the buddyboss list.
	 *
	 * @param array<string, mixed> $plugins Plugins array list.
	 *
	 * @return array<string, mixed> Array of plugins.
	 */
	public function add_suremembers_option( $plugins ) {
		$plugins['suremembers'] = [
			'label'      => __( 'SureMembers', 'suremembers-core' ),
			'is_enabled' => true,
			'class'      => Access_Control::class,
		];

		return $plugins;
	}

	/**
	 * Add user to access group on Activation
	 *
	 * @param int $user_id The current user ID.
	 *
	 * @since 1.7.1
	 */
	public function add_access_group_on_activation( $user_id ) {
		if ( ! $user_id ) {
			return;
		}

		$settings = Settings::get_setting( SUREMEMBERS_ADMIN_SETTINGS );
		if ( empty( $settings['registration_access_group'] ) ) {
			return;
		}

		$access_group_ids = Utils::sanitize_recursively( 'absint', $settings['registration_access_group'] );
		if ( empty( $access_group_ids ) ) {
			return;
		}

		Access::grant( $user_id, $access_group_ids, 'buddyboss' );
	}
}
