<?php
/**
 * Plugin Compatibility.
 *
 * @package SureMembers
 * @since 2.0.0
 */

namespace SureMembersCore\Compatibility;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin Compatibility Class.
 *
 * Handle compatibility with other plugins during installation and activation.
 *
 * @since 2.0.0
 */
class Plugin_Compatibility {
	use Get_Instance;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		add_action( 'suremembers_after_plugin_activation', [ $this, 'prevent_other_plugin_redirection' ], 10, 2 );
	}

	/**
	 * Prevent other plugin redirection after activation.
	 *
	 * Some plugins redirect users to their own setup/welcome page after activation.
	 * This prevents that behavior when plugins are activated via SureMembers.
	 *
	 * @since 2.0.0
	 *
	 * @param string $plugin_init Plugin init path (e.g., 'plugin-folder/plugin-file.php').
	 * @param string $plugin_slug Plugin slug.
	 * @return void
	 */
	public function prevent_other_plugin_redirection( $plugin_init, $plugin_slug ) {

		switch ( $plugin_init ) {
			case 'suretriggers/suretriggers.php':
				delete_transient( 'st-redirect-after-activation' );
				break;
			case 'suredash/suredash.php':
				update_option( '__suredash_do_redirect', false );
				break;
			case 'suremails/suremails.php':
				update_option( 'suremails_do_redirect', false );
				break;
			default:
				break;
		}
	}
}
