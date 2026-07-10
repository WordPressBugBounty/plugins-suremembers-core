<?php
/**
 * Plugin Loader.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore;

use SureMembersCore\Admin\Admin_Menu;
use SureMembersCore\Admin\Gutenberg_Admin_Bar;
use SureMembersCore\Admin\Login_Redirect;
use SureMembersCore\Admin\Menu_Restriction;
use SureMembersCore\Admin\Restrictions;
use SureMembersCore\Admin\Rules_Engine;
use SureMembersCore\Admin\Settings_Screen;
use SureMembersCore\Admin\User_Access;
// Compatibility Classes.
use SureMembersCore\Compatibility\Jetpack_Compatibility;
use SureMembersCore\Compatibility\Plugin_Compatibility;
use SureMembersCore\Inc\Access_Logs;
use SureMembersCore\Inc\Activator;
use SureMembersCore\Inc\Admin_Bar;
use SureMembersCore\Inc\Analytics;
use SureMembersCore\Inc\Content_Restriction;
use SureMembersCore\Inc\Dashboard_Access;
use SureMembersCore\Inc\Downloads;
use SureMembersCore\Inc\Expiration_Sweep;
use SureMembersCore\Inc\Login_Page;
use SureMembersCore\Inc\Member_List;
use SureMembersCore\Inc\Menu_Items;
use SureMembersCore\Inc\Modules\Learn\Learn;
use SureMembersCore\Inc\Modules\MCP\Module as MCP_Module;
use SureMembersCore\Inc\Onboarding_Router;
use SureMembersCore\Inc\Rest_Restriction;
use SureMembersCore\Inc\Routes;
use SureMembersCore\Inc\Services\Abilities\Registry as Abilities_Registry;
use SureMembersCore\Inc\Services\CLI\Abilities_Command;
use SureMembersCore\Inc\Template_Redirect;
use SureMembersCore\Inc\Updates;
use SureMembersCore\Integrations\Buddyboss\Buddyboss;
use SureMembersCore\Integrations\Surecart_Integration;
use SureMembersCore\Integrations\Suredash;
use SureMembersCore\Integrations\Woocommerce;
use SureMembersCore\Modules\Learndash\Learndash;
use SureMembersCore\Modules\Tutorlms\Tutorlms;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin_Loader
 *
 * @since 0.0.1
 */
class Plugin_Loader {
	/**
	 * Instance
	 *
	 * @access private
	 *
	 * @var object|null Class Instance.
	 *
	 * @since 0.0.1
	 */
	private static $instance = null;

	/**
	 * Constructor
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		// Remove this after the translation error is fixed.
		add_filter( 'doing_it_wrong_trigger_error', [ $this, 'suppress_translation_error' ], 10, 4 );

		// Prevent Query Monitor from collecting the error.
		add_action( 'doing_it_wrong_run', [ $this, 'prevent_qm_collection' ], 5, 3 );

		spl_autoload_register( [ $this, 'autoload' ] );
		add_action( 'init', [ $this, 'load_classes' ] );
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_filter( 'wp_untrash_post_status', [ $this, 'change_untrash_post_status' ], 10, 2 );

		// Register the block restriction attributes (sureMemberRestrictions,
		// sureMemberShowOnRestriction) on the PHP side for every block. The
		// editor adds these via JS to all blocks; declaring them here keeps the
		// block-renderer REST schema in sync so server-side rendered blocks
		// (e.g. Gravity Forms) preview without an "Invalid parameter(s):
		// attributes" error. Hooked here, before `init`, so it applies to every
		// block registered on `init`.
		add_filter( 'register_block_type_args', [ Restrictions::class, 'register_block_attributes' ] );

		add_action( 'profile_update', [ User_Access::class, 'ensure_user_roles_after_profile_save' ], 10, 3 );
		Rules_Engine::get_instance();
		Login_Redirect::get_Instance();

		if ( ! class_exists( 'BSF_Admin_Notices' ) ) {
			require_once SUREMEMBERS_CORE_DIR . 'lib/astra-notices/class-bsf-admin-notices.php';
		}

		// Load Action Scheduler for background processing (handles its own version negotiation across plugins).
		require_once SUREMEMBERS_CORE_DIR . 'lib/action-scheduler/action-scheduler.php';

		/**
		 * The code that runs during plugin activation
		 */
		register_activation_hook(
			SUREMEMBERS_CORE_FILE,
			static function () {
				Activator::activate();
			}
		);

		// Include required functions.
		require_once 'inc/common-functions.php';

		require_once 'lib/suremembers-nps-survey.php';

		// Load NPS class.
		require_once SUREMEMBERS_CORE_DIR . 'lib/suremembers-nps.php';

		// Initialize NPS and Analytics.
		Lib\SureMembers_Nps::get_instance();

		// Bootstrap BSF Analytics telemetry.
		Analytics::get_instance();

		// Register the WordPress Abilities API layer (AI/MCP-discoverable abilities).
		Abilities_Registry::get_instance();

		// Register the MCP module (ability/MCP settings REST endpoint + MCP server).
		MCP_Module::get_instance();

		// Register WP-CLI commands.
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::add_command( 'suremembers abilities', Abilities_Command::class );
		}
	}

	/**
	 * Initiator
	 *
	 * @since 0.0.1
	 *
	 * @return object initialized object of class.
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Autoload classes.
	 *
	 * @param string $class class name.
	 */
	public function autoload( $class ) {
		if ( strpos( $class, __NAMESPACE__ ) !== 0 ) {
			return;
		}

		$filename = preg_replace(
			[ '/^' . __NAMESPACE__ . '\\\/', '/([a-z])([A-Z])/', '/_/', '/\\\/' ],
			[ '', '$1-$2', '-', DIRECTORY_SEPARATOR ],
			$class
		);

		if ( is_string( $filename ) ) {
			$filename = strtolower( $filename );

			$file = SUREMEMBERS_CORE_DIR . $filename . '.php';

			// if the file is readable, include it.
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	}

	/**
	 * Load Plugin Text Domain.
	 * This will load the translation textdomain depending on the file priorities.
	 *      1. Global Languages /wp-content/languages/suremembers-core/ folder
	 *      2. Local directory /wp-content/plugins/suremembers-core/languages/ folder
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		// Default languages directory.
		$lang_dir = SUREMEMBERS_CORE_DIR . 'languages/';

		/**
		 * Filters the languages directory path to use for plugin.
		 *
		 * @param string $lang_dir The languages directory path.
		 */
		$lang_dir = apply_filters( 'suremembers_languages_directory', $lang_dir );

		// Traditional WordPress plugin locale filter.
		global $wp_version;

		$get_locale = get_locale();

		/**
		 * Language Locale for plugin
		 * Uses get_user_locale()` in WordPress 4.7 or greater,
		 * otherwise uses `get_locale()`.
		 */
		if ( $wp_version >= 4.7 ) {
			$get_locale = get_user_locale();
		} else {
			$get_locale = get_locale();
		}

		$locale = apply_filters( 'plugin_locale', $get_locale, 'suremembers-core' );
		$mofile = sprintf( '%1$s-%2$s.mo', 'suremembers-core', $locale );

		// Setup paths to current locale file.
		$mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;
		$mofile_local  = $lang_dir . $mofile;

		if ( file_exists( $mofile_global ) ) {
			// Look in global /wp-content/languages/suremembers-core/ folder.
			load_textdomain( 'suremembers-core', $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			// Look in local /wp-content/plugins/suremembers-core/languages/ folder.
			load_textdomain( 'suremembers-core', $mofile_local );
		} else {
			// Load the default language files.
			load_plugin_textdomain( 'suremembers-core', false, $lang_dir );
		}
	}

	/**
	 * Provides untrash posts publish status, only for SUREMEMBERS_POST_TYPE
	 *
	 * @param string $status current status to be applied for untrashed post 'draft' generally.
	 * @param int    $id post id to be untrashed.
	 *
	 * @since 1.0.0
	 */
	public function change_untrash_post_status( $status, $id ) {
		$post_id = intval( $id );
		if ( ! empty( $post_id ) && get_post_type( $post_id ) === SUREMEMBERS_POST_TYPE ) {
			$status = 'publish';
		}
		return $status;
	}

	/**
	 * Loads plugin classes as per requirement.
	 *
	 * @since  0.0.1
	 */
	public function load_classes() {
		// Handle onboarding redirect (similar to SureDash).
		if ( is_admin() && get_option( '__suremembers_do_redirect', false ) ) {
			update_option( '__suremembers_do_redirect', false );

			if ( ! is_multisite() ) {
				$is_onboarding_completed = get_option( 'suremembers_onboarding_completed' ) === 'yes' || get_option( 'suremembers_onboarding_skipped' ) === 'yes';

				if ( ! $is_onboarding_completed ) {
					// Redirect to onboarding page.
					wp_safe_redirect(
						add_query_arg(
							[
								'page'                   => 'suremembers-onboarding',
								'sm-activation-redirect' => true,
							],
							admin_url( 'admin.php' )
						)
					);
					exit;
				}
			}
		}

		if ( is_admin() ) {
			Admin_Menu::get_instance();
			Menu_Restriction::get_instance();
			Gutenberg_Admin_Bar::get_instance();
			User_Access::get_instance();
			Settings_Screen::get_instance();
			Restrictions::get_instance();
		} else {
			Template_Redirect::get_instance();
			Content_Restriction::get_instance();
			Member_List::get_instance();
			Menu_Items::get_instance();
			Login_page::get_instance();
		}
		Admin_Bar::get_instance();
		Dashboard_Access::get_instance();
		Access_Logs::get_instance();
		Routes::get_instance();
		Learn::get_instance();
		Rest_Restriction::get_instance();
		Expiration_Sweep::get_instance();
		if ( defined( 'SURECART_APP_URL' ) ) {
			( new Surecart_Integration() )->bootstrap();
		}

		if ( defined( 'SUREDASHBOARD_VER' ) ) {
			Suredash::get_instance();
		}

		if ( class_exists( 'SFWD_LMS' ) ) {
			Learndash::get_instance();
		}

		if ( is_plugin_active( 'buddyboss-platform/bp-loader.php' ) ) {
			Buddyboss::get_instance();
		}

		if ( function_exists( 'WC' ) ) {
			Woocommerce::get_instance();
		}

		if ( function_exists( 'tutor_lms' ) ) {
			Tutorlms::get_instance();
		}
		// JetPack Compatibility Class.
		if ( class_exists( 'Jetpack' ) ) {
			Jetpack_Compatibility::get_instance();
		}
		Downloads::get_instance();
		Updates::get_instance();
		Onboarding_Router::get_instance();
		Plugin_Compatibility::get_instance();
	}

	/**
	 * Suppress translation error.
	 *
	 * @param bool   $status       Status.
	 * @param string $function_name Function name.
	 * @param string $message      Message.
	 * @param string $version      Version.
	 */
	public function suppress_translation_error( $status, $function_name, $message, $version ) {
		if ( $function_name === '_load_textdomain_just_in_time' && strpos( $message, 'suremembers-core' ) !== false ) {
			return false;
		}
		return $status;
	}

	/**
	 * Prevent Query Monitor from collecting textdomain errors.
	 *
	 * @param string $function_name The function that was called.
	 * @param string $message The error message.
	 * @param string $version The version.
	 *
	 * @since 1.10.11
	 */
	public function prevent_qm_collection( $function_name, $message, $version ) {
		if ( $function_name === '_load_textdomain_just_in_time' && strpos( $message, 'suremembers-core' ) !== false ) {
			// Remove Query Monitor's action temporarily.
			if ( class_exists( '\QM_Collectors' ) ) {
				$collector = \QM_Collectors::get( 'doing_it_wrong' );
				if ( $collector && is_object( $collector ) ) {
					remove_action( 'doing_it_wrong_run', [ $collector, 'action_doing_it_wrong_run' ], 10 );

					// Re-add it after this specific error.
					add_action(
						'shutdown',
						static function () use ( $collector ) {
							if ( is_object( $collector ) && ! has_action( 'doing_it_wrong_run', [ $collector, 'action_doing_it_wrong_run' ] ) ) {
								add_action( 'doing_it_wrong_run', [ $collector, 'action_doing_it_wrong_run' ], 10, 3 );
							}
						},
						-1
					);
				}
			}
		}
	}
}

/**
 * Kicking this off by calling 'get_instance()' method
 */
Plugin_Loader::get_instance();
