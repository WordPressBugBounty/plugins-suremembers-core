<?php
/**
 * Suremembers Activator.
 *
 * @package Suremembers.
 */

namespace SureMembersCore\Inc;

defined( 'ABSPATH' ) || exit;

/**
 * Activation Class.
 *
 * @since 1.3.0
 */
class Activator {
	/**
	 * Activation handler function.
	 */
	public static function activate() {
		// Add support for downloads.
		$downloads = new Downloads();
		$downloads->add_private_folder();

		// Create the access logs table and flag the history backfill (no-ops if already installed).
		Access_Logs::install();

		/**
		 * Reset rewrite rules to avoid go to permalinks page
		 * through deleting the database options to force WP to do it
		 * because of on activation not work well flush_rewrite_rules()
		 */
		delete_option( 'rewrite_rules' );

		// Set redirect flag for first-time activation (similar to SureDash).
		update_option( '__suremembers_do_redirect', true );

		// Record install timestamp for time-to-value analytics (set once, never overwritten).
		if ( ! get_option( 'suremembers_usage_installed_time' ) ) {
			update_option( 'suremembers_usage_installed_time', time() );
		}
	}
}
