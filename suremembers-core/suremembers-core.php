<?php
/**
 * Plugin Name: SureMembers Core
 * Plugin URI: https://suremembers.com
 * Description: A simple yet powerful way to add content restriction to your website.
 * Version: 1.2.2
 * Author: Brainstorm Force
 * Author URI: https://www.brainstormforce.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: suremembers-core
 * Domain Path: /languages
 * Requires at least: 6.6
 * Tested up to: 7.0
 * Requires PHP: 7.4
 *
 * @package SureMembers Core
 */

/**
 * Prevent loading if the old standalone SureMembers plugin is active.
 *
 * The old standalone (pre-split) suremembers/suremembers.php defines all constants
 * without if(!defined()) guards and duplicates all Core functionality. Running both
 * causes PHP warnings, duplicate REST routes, double content restriction, etc.
 *
 * This check MUST run before any define() calls — otherwise Core would define the
 * shared constants first, and the unguarded defines in the old standalone would then
 * trigger "Constant already defined" warnings.
 *
 * Detection: The new SureMembers Pro has "Requires Plugins: suremembers-core" in its
 * plugin header. The old standalone does not. If suremembers/suremembers.php is active
 * and lacks that header, it is the old standalone — bail out with a notice.
 *
 * @since 1.0.0
 */
$suremembers_core_active_plugins = (array) get_option( 'active_plugins', [] );
if ( in_array( 'suremembers/suremembers.php', $suremembers_core_active_plugins, true ) ) {
	$suremembers_standalone_file = WP_PLUGIN_DIR . '/suremembers/suremembers.php';
	if ( file_exists( $suremembers_standalone_file ) ) {
		$suremembers_headers = get_file_data( $suremembers_standalone_file, [ 'RequiresPlugins' => 'Requires Plugins' ] );
		if ( empty( $suremembers_headers['RequiresPlugins'] ) || strpos( $suremembers_headers['RequiresPlugins'], 'suremembers-core' ) === false ) {
			add_action(
				'admin_notices',
				static function () {
					?>
					<div class="notice notice-error">
						<p>
							<strong><?php esc_html_e( 'SureMembers Core — Plugin Conflict', 'suremembers-core' ); ?></strong>
						</p>
						<p>
							<?php esc_html_e( 'The standalone SureMembers plugin is currently active. SureMembers Core cannot run alongside the standalone version as both manage the same functionality.', 'suremembers-core' ); ?>
						</p>
						<p>
							<?php esc_html_e( 'Please deactivate the standalone SureMembers plugin and update it to the latest SureMembers Pro, which works as a premium add-on to SureMembers Core.', 'suremembers-core' ); ?>
						</p>
					</div>
					<?php
				}
			);
			return;
		}
	}
}
unset( $suremembers_core_active_plugins, $suremembers_standalone_file, $suremembers_headers );

/**
 * Set constants
 */
define( 'SUREMEMBERS_CORE_FILE', __FILE__ );
define( 'SUREMEMBERS_CORE_BASE', plugin_basename( SUREMEMBERS_CORE_FILE ) );
define( 'SUREMEMBERS_CORE_DIR', plugin_dir_path( SUREMEMBERS_CORE_FILE ) );
define( 'SUREMEMBERS_CORE_URL', plugins_url( '/', SUREMEMBERS_CORE_FILE ) );
if ( ! defined( 'SUREMEMBERS_POST_TYPE' ) ) {
	define( 'SUREMEMBERS_POST_TYPE', 'wsm_access_group' );
}
if ( ! defined( 'SUREMEMBERS_POST_META' ) ) {
	define( 'SUREMEMBERS_POST_META', 'suremembers_post_access_group' );
}
if ( ! defined( 'SUREMEMBERS_USER_META' ) ) {
	define( 'SUREMEMBERS_USER_META', 'suremembers_user_access_group' );
}
if ( ! defined( 'SUREMEMBERS_USER_EXPIRATION' ) ) {
	define( 'SUREMEMBERS_USER_EXPIRATION', 'suremembers_user_expiration' );
}
if ( ! defined( 'SUREMEMBERS_PLAN_PRIORITY' ) ) {
	define( 'SUREMEMBERS_PLAN_PRIORITY', 'suremembers_plan_priority' );
}
if ( ! defined( 'SUREMEMBERS_PLAN_EXPIRATION' ) ) {
	define( 'SUREMEMBERS_PLAN_EXPIRATION', 'suremembers_plan_expiration' );
}
if ( ! defined( 'SUREMEMBERS_PLAN_ACTIVE_USERS' ) ) {
	define( 'SUREMEMBERS_PLAN_ACTIVE_USERS', 'suremembers_plan_active_users' );
}
if ( ! defined( 'SUREMEMBERS_REQUIRES_QUERY' ) ) {
	define( 'SUREMEMBERS_REQUIRES_QUERY', 'suremembers_requires_users_fetch_query' );
}
if ( ! defined( 'SUREMEMBERS_PLAN_RULES' ) ) {
	define( 'SUREMEMBERS_PLAN_RULES', 'suremembers_plan_rules' );
}
if ( ! defined( 'SUREMEMBERS_PLAN_INCLUDE' ) ) {
	define( 'SUREMEMBERS_PLAN_INCLUDE', 'suremembers_plan_include' );
}
if ( ! defined( 'SUREMEMBERS_PLAN_EXCLUDE' ) ) {
	define( 'SUREMEMBERS_PLAN_EXCLUDE', 'suremembers_plan_exclude' );
}
if ( ! defined( 'SUREMEMBERS_PLAN_DRIPS' ) ) {
	define( 'SUREMEMBERS_PLAN_DRIPS', 'suremembers_plan_drips' );
}
if ( ! defined( 'SUREMEMBERS_ACCESS_GROUPS' ) ) {
	define( 'SUREMEMBERS_ACCESS_GROUPS', 'suremembers_access_groups' );
}
if ( ! defined( 'SUREMEMBERS_MENU_USER_CONDITION' ) ) {
	define( 'SUREMEMBERS_MENU_USER_CONDITION', 'suremembers_menu_user_condition' );
}
if ( ! defined( 'SUREMEMBERS_ARCHIVE' ) ) {
	define( 'SUREMEMBERS_ARCHIVE', 'suremembers_archive' );
}
if ( ! defined( 'SUREMEMBERS_RESTRICTED_URL' ) ) {
	define( 'SUREMEMBERS_RESTRICTED_URL', 'suremembers_restricted_url' );
}
if ( ! defined( 'SUREMEMBERS_USER_ROLES' ) ) {
	define( 'SUREMEMBERS_USER_ROLES', 'suremembers_user_roles' );
}
if ( ! defined( 'SUREMEMBERS_REDIRECT_RULES' ) ) {
	define( 'SUREMEMBERS_REDIRECT_RULES', 'suremembers_redirect_rules' );
}
if ( ! defined( 'SUREMEMBERS_LOGIN_FORM_SETTINGS' ) ) {
	define( 'SUREMEMBERS_LOGIN_FORM_SETTINGS', 'suremembers_login_form_settings' );
}
if ( ! defined( 'SUREMEMBERS_LOGIN_RESTRICTIONS_SETTINGS' ) ) {
	define( 'SUREMEMBERS_LOGIN_RESTRICTIONS_SETTINGS', 'suremembers_login_restrictions_settings' );
}
if ( ! defined( 'SUREMEMBERS_CUSTOM_CONTENT' ) ) {
	define( 'SUREMEMBERS_CUSTOM_CONTENT', 'suremembers_custom_content' );
}
if ( ! defined( 'SUREMEMBERS_ACCESS_GROUP_DOWNLOADS' ) ) {
	define( 'SUREMEMBERS_ACCESS_GROUP_DOWNLOADS', 'suremembers_access_group_downloads' );
}
if ( ! defined( 'SUREMEMBERS_ADMIN_SETTINGS' ) ) {
	define( 'SUREMEMBERS_ADMIN_SETTINGS', 'suremembers_admin_settings' );
}
if ( ! defined( 'SUREMEMBERS_ABILITIES_SETTINGS' ) ) {
	define( 'SUREMEMBERS_ABILITIES_SETTINGS', 'suremembers_abilities_settings' );
}
if ( ! defined( 'SUREMEMBERS_WEBHOOK_ENDPOINTS' ) ) {
	define( 'SUREMEMBERS_WEBHOOK_ENDPOINTS', 'suremembers_webhook_endpoints' );
}
if ( ! defined( 'SUREMEMBERS_EMAIL_TEMPLATE_SETTINGS' ) ) {
	define( 'SUREMEMBERS_EMAIL_TEMPLATE_SETTINGS', 'suremembers_email_template_settings' );
}

define( 'SUREMEMBERS_CORE_VER', '1.2.2' );

// Minimum SureMembers (Pro) version compatible with this Core build.
if ( ! defined( 'SUREMEMBERS_PRO_MINIMUM_VER' ) ) {
	define( 'SUREMEMBERS_PRO_MINIMUM_VER', '3.0.0' );
}

/**
 * Warn when an outdated SureMembers (Pro) add-on is active alongside this Core.
 *
 * Pro defines SUREMEMBERS_VER at include time (before plugins_loaded), so it is
 * safe to read here. Core keeps working regardless — this is only a heads-up.
 *
 * @since 1.0.0
 * @return void
 */
function suremembers_core_pro_minimum_version_notice(): void {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	if ( ! defined( 'SUREMEMBERS_VER' ) ) {
		return;
	}

	if ( version_compare( SUREMEMBERS_VER, SUREMEMBERS_PRO_MINIMUM_VER, '>=' ) ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p style="font-size: 14px;">
			<strong><?php esc_html_e( 'SureMembers — Update Required', 'suremembers-core' ); ?></strong>
		</p>
		<p>
			<?php
			printf(
				/* translators: 1: SureMembers Core plugin name, 2: SureMembers Pro plugin name, 3: minimum required Pro version. */
				esc_html__( '%1$s requires %2$s version %3$s or higher. Please update the SureMembers add-on to keep all features working.', 'suremembers-core' ),
				'<strong>SureMembers Core</strong>',
				'<strong>SureMembers</strong>',
				esc_html( SUREMEMBERS_PRO_MINIMUM_VER )
			);
			?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'suremembers_core_pro_minimum_version_notice' );

require_once 'inc/backward-compat.php';
require_once 'plugin-loader.php';
