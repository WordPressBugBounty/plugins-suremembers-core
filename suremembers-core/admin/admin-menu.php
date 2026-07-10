<?php
/**
 * Admin menu.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Admin;

use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Restricted;
use SureMembersCore\Inc\Traits\Get_Instance;
use SureMembersCore\Inc\Utils;
use SureMembersCore\Inc\Utils\Global_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Admin menu
 *
 * @since 0.0.1
 */
class Admin_Menu {
	use Get_Instance;

	/**
	 * Tailwind assets base url
	 *
	 * @since  0.0.1
	 * @var string
	 */
	private $tailwind_assets = SUREMEMBERS_CORE_URL . 'assets/build/';

	/**
	 * Constructor
	 *
	 * @since  0.0.1
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'suremembers_access_group_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'settings_page_scripts' ] );
		add_action( 'admin_init', [ $this, 'check_status_transition' ] );
		add_action( 'admin_init', [ $this, 'redirect_old_urls' ] );
		add_action( 'admin_head', [ $this, 'admin_menu_css' ] );

		// Filters.
		add_filter( 'get_edit_post_link', [ $this, 'edit_post_link' ], 10, 2 );
		add_filter( 'admin_url', [ $this, 'update_add_new_link' ], 100, 2 );
		add_filter( 'suremembers_get_access_groups_data', [ $this, 'get_access_group_data' ] );
		add_filter( 'suremembers_filter_access_group_url_args', [ $this, 'check_iframe_mode' ] );
		add_filter( 'suremembers_get_access_groups_data', [ $this, 'inject_table_data' ] );
	}

	/**
	 * Redirect old URLs to new URL structure
	 *
	 * @since 1.0.0
	 */
	public function redirect_old_urls() {
		// Check if we're on an old URL structure.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_type    = isset( $_GET['post_type'] ) ? sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Redirect old suremembers_settings to new suremembers-settings.
		if ( $current_page === 'suremembers_settings' ) {
			$new_url = admin_url( 'admin.php?page=suremembers-settings' );

			// Preserve tab parameter if exists.
			if ( isset( $_GET['tab'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$tab     = sanitize_text_field( wp_unslash( $_GET['tab'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$new_url = add_query_arg( 'tab', $tab, $new_url );
			}

			wp_safe_redirect( $new_url );
			exit;
		}

		// Redirect old suremembers_rules to new suremembers.
		if ( $current_page === 'suremembers_rules' && $post_type === SUREMEMBERS_POST_TYPE ) {
			$new_url = admin_url( 'admin.php?page=suremembers' );

			// Preserve post_id parameter if exists (convert to membership-id).
			if ( isset( $_GET['post_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$post_id = absint( $_GET['post_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$new_url = add_query_arg( 'membership-id', $post_id, $new_url );
			}

			wp_safe_redirect( $new_url );
			exit;
		}
	}

	/**
	 * Updates edit post link to react app
	 *
	 * @param string $url default edit url.
	 * @param int    $post_id current post id.
	 *
	 * @since 1.0.0
	 */
	public function edit_post_link( $url, $post_id ) {
		// Ignored nonce verification as we are getting post_type from URL.
		if ( empty( $_GET['post_type'] ) || sanitize_text_field( $_GET['post_type'] ) !== SUREMEMBERS_POST_TYPE ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $url;
		}

		$url_args = apply_filters(
			'suremembers_filter_access_group_url_args',
			[
				'page'          => 'suremembers',
				'membership-id' => $post_id,
			]
		);

		$url = add_query_arg(
			$url_args,
			admin_url( 'admin.php' )
		);
		return $url;
	}

	/**
	 * Check if edit link is loaded from iframe.
	 *
	 * @param array<string, mixed> $args URL arguments.
	 *
	 * @return array<string, mixed> $args Updated URL arguments.
	 *
	 * @since 1.0.0
	 */
	public function check_iframe_mode( $args ) {
		// Ignoring this as we are getting the data from URL and using further.
		if ( isset( $_GET['suremembers_view'] ) && $_GET['suremembers_view'] === 'iframe' ) {// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$args['suremembers_view'] = 'iframe';
		}
		return $args;
	}

	/**
	 * Replacing link for add new Access Group
	 *
	 * @param string $url default url.
	 * @param string $path default path.
	 *
	 * @since 1.0.0
	 */
	public function update_add_new_link( $url, $path ) {
		if ( $path === 'post-new.php?post_type=' . SUREMEMBERS_POST_TYPE ) {
			$url = Access_Groups::get_admin_url( [ 'membership-id' => 'new' ] );
		}
		return $url;
	}

	/**
	 * Adds admin menu for settings page
	 *
	 * @since  2.0.0
	 */
	public function suremembers_access_group_page() {
		// Base64 encoded SVG icon for WordPress admin menu (like SureDash).
		$logo = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iOTYiIGhlaWdodD0iOTYiIHZpZXdCb3g9IjAgMCA5NiA5NiIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGcgY2xpcC1wYXRoPSJ1cmwoI2NsaXAwXzg1NF83ODQ5KSI+CjxwYXRoIGQ9Ik0zMyA2NkMzMyA3NC4yODQzIDM5LjcxNTcgODEgNDggODFDNDkuMDI3NCA4MSA1MC4wMzA2IDgwLjg5NiA1MSA4MC42OTkyVjgxSDUxLjAwMVY4MC42OTkyQzUxLjE5OTUgODAuNjU4OSA1MS4zOTYyIDgwLjYxMzUgNTEuNTkxOCA4MC41NjU0QzUxLjYxODUgODAuNTU4OSA1MS42NDUyIDgwLjU1MjYgNTEuNjcxOSA4MC41NDU5QzUxLjg1ODUgODAuNDk4OSA1Mi4wNDM3IDgwLjQ0ODQgNTIuMjI3NSA4MC4zOTQ1QzUyLjI1MyA4MC4zODcxIDUyLjI3ODMgODAuMzc4NyA1Mi4zMDM3IDgwLjM3MTFDNTIuNDkwNCA4MC4zMTUzIDUyLjY3NTggODAuMjU3MiA1Mi44NTk0IDgwLjE5NDNDNTIuODc4NCA4MC4xODc4IDUyLjg5NyA4MC4xODA0IDUyLjkxNiA4MC4xNzM4QzUzLjI4OTkgODAuMDQ0MiA1My42NTY4IDc5Ljg5OTkgNTQuMDE2NiA3OS43NDIyQzU0LjA0ODUgNzkuNzI4MiA1NC4wODA1IDc5LjcxNDQgNTQuMTEyMyA3OS43MDAyQzU0LjI3OSA3OS42MjU3IDU0LjQ0NCA3OS41NDgxIDU0LjYwNzQgNzkuNDY3OEM1NC42MzQ3IDc5LjQ1NDQgNTQuNjYyMiA3OS40NDEzIDU0LjY4OTUgNzkuNDI3N0M1NC44NTM5IDc5LjM0NTYgNTUuMDE2OCA3OS4yNjA4IDU1LjE3NzcgNzkuMTcyOUM1NS4yMTExIDc5LjE1NDYgNTUuMjQ0MSA3OS4xMzU3IDU1LjI3NzMgNzkuMTE3MkM1NS40MjYgNzkuMDM0NSA1NS41NzMyIDc4Ljk0OTggNTUuNzE4OCA3OC44NjIzQzU1Ljc0MDggNzguODQ5MSA1NS43NjMyIDc4LjgzNjYgNTUuNzg1MiA3OC44MjMyQzU1Ljk0NDYgNzguNzI2MiA1Ni4xMDE0IDc4LjYyNTIgNTYuMjU2OCA3OC41MjI1QzU2LjI3NzkgNzguNTA4NiA1Ni4yOTk0IDc4LjQ5NTQgNTYuMzIwMyA3OC40ODE0QzU2LjQ3MDUgNzguMzgxMSA1Ni42MTgzIDc4LjI3NzQgNTYuNzY0NiA3OC4xNzE5QzU2LjgwNTUgNzguMTQyNCA1Ni44NDYyIDc4LjExMjkgNTYuODg2NyA3OC4wODNDNTcuMDE4OCA3Ny45ODU3IDU3LjE0OTYgNzcuODg2NyA1Ny4yNzgzIDc3Ljc4NTJDNTcuMzIzMiA3Ny43NDk3IDU3LjM2NzYgNzcuNzEzNiA1Ny40MTIxIDc3LjY3NzdDNTcuNTMwOSA3Ny41ODE5IDU3LjY0NzkgNzcuNDg0MSA1Ny43NjM3IDc3LjM4NDhDNTcuNzgwOSA3Ny4zNyA1Ny43OTkzIDc3LjM1NjYgNTcuODE2NCA3Ny4zNDE4QzU3Ljk1MjUgNzcuMjIzOSA1OC4wODYgNzcuMTAzMSA1OC4yMTc4IDc2Ljk4MDVDNTguMjUwOCA3Ni45NDk3IDU4LjI4MzYgNzYuOTE4NyA1OC4zMTY0IDc2Ljg4NzdDNTguNDMyOSA3Ni43NzczIDU4LjU0NzMgNzYuNjY0OCA1OC42NjAyIDc2LjU1MDhDNTguNzAzOSA3Ni41MDY2IDU4Ljc0NjggNzYuNDYxNyA1OC43OSA3Ni40MTdDNTguOTAzIDc2LjMgNTkuMDE0OSA3Ni4xODIxIDU5LjEyNCA3Ni4wNjE1QzU5LjE2MTUgNzYuMDIwMiA1OS4xOTg0IDc1Ljk3ODMgNTkuMjM1NCA3NS45MzY1QzU5LjMxNDYgNzUuODQ3IDU5LjM5MjYgNzUuNzU2NCA1OS40Njk3IDc1LjY2NUM1OS41Mzc0IDc1LjU4NDggNTkuNjAzOSA3NS41MDM1IDU5LjY2OTkgNzUuNDIxOUM1OS43NDE2IDc1LjMzMzIgNTkuODEyMiA3NS4yNDM2IDU5Ljg4MTggNzUuMTUzM0M1OS45Mjc4IDc1LjA5MzggNTkuOTc0NSA3NS4wMzQ4IDYwLjAxOTUgNzQuOTc0NkM2MC4xMDU0IDc0Ljg1OTggNjAuMTg4OSA3NC43NDMzIDYwLjI3MTUgNzQuNjI2QzYwLjMxMjEgNzQuNTY4MyA2MC4zNTI3IDc0LjUxMDUgNjAuMzkyNiA3NC40NTIxQzYwLjQ3NTUgNzQuMzMwOSA2MC41NTczIDc0LjIwODggNjAuNjM2NyA3NC4wODVDNjAuNjgyOCA3NC4wMTMxIDYwLjcyNjYgNzMuOTM5OSA2MC43NzE1IDczLjg2NzJDNjAuODIzIDczLjc4MzcgNjAuODczOSA3My42OTk4IDYwLjkyMzggNzMuNjE1MkM2MC45Nzg0IDczLjUyMjkgNjEuMDMxNCA3My40Mjk2IDYxLjA4NCA3My4zMzU5QzYxLjEzOTUgNzMuMjM3MiA2MS4xOTQ3IDczLjEzODIgNjEuMjQ4IDczLjAzODFDNjEuMjgwMiA3Mi45Nzc3IDYxLjMxMjQgNzIuOTE3MyA2MS4zNDM4IDcyLjg1NjRDNjEuNDEwNCA3Mi43MjcgNjEuNDc1MSA3Mi41OTY0IDYxLjUzODEgNzIuNDY0OEM2MS41NzY3IDcyLjM4NDIgNjEuNjE0MiA3Mi4zMDMxIDYxLjY1MTQgNzIuMjIxN0M2MS43MTA3IDcyLjA5MTYgNjEuNzY5NSA3MS45NjExIDYxLjgyNTIgNzEuODI5MUM2MS44NTI5IDcxLjc2MzUgNjEuODc4NSA3MS42OTcgNjEuOTA1MyA3MS42MzA5QzYxLjk0NTcgNzEuNTMxMSA2MS45ODUxIDcxLjQzMDkgNjIuMDIzNCA3MS4zMzAxQzYyLjA2ODcgNzEuMjExIDYyLjExMiA3MS4wOTEyIDYyLjE1NDMgNzAuOTcwN0M2Mi4xODQ3IDcwLjg4NCA2Mi4yMTUzIDcwLjc5NzMgNjIuMjQ0MSA3MC43MUM2Mi4zMDQ5IDcwLjUyNjIgNjIuMzYyMyA3MC4zNDExIDYyLjQxNiA3MC4xNTQzQzYyLjQyMjkgNzAuMTMwMiA2Mi40Mjk3IDcwLjEwNjEgNjIuNDM2NSA3MC4wODJDNjIuNDY1NSA2OS45NzkyIDYyLjQ5MzYgNjkuODc2MSA2Mi41MjA1IDY5Ljc3MjVDNjIuNTU0MiA2OS42NDIyIDYyLjU4NjkgNjkuNTExNSA2Mi42MTcyIDY5LjM3OTlDNjIuNjM1OCA2OS4yOTkxIDYyLjY1MjYgNjkuMjE4IDYyLjY2OTkgNjkuMTM2N0M2Mi42ODgzIDY5LjA1MDQgNjIuNzA1OCA2OC45NjM4IDYyLjcyMjcgNjguODc3QzYyLjc1MjcgNjguNzIyMiA2Mi43ODA0IDY4LjU2NjYgNjIuODA1NyA2OC40MTAyQzYyLjgxNyA2OC4zNDAzIDYyLjgyODUgNjguMjcwNCA2Mi44Mzg5IDY4LjIwMDJDNjIuODUzMSA2OC4xMDM3IDYyLjg2NzUgNjguMDA3MiA2Mi44Nzk5IDY3LjkxMDJDNjIuODk4IDY3Ljc2NzMgNjIuOTEyNiA2Ny42MjM2IDYyLjkyNjggNjcuNDc5NUM2Mi45MzYxIDY3LjM4NDMgNjIuOTQ0NiA2Ny4yODkgNjIuOTUyMSA2Ny4xOTM0QzYyLjk2MzIgNjcuMDUzMiA2Mi45NzMzIDY2LjkxMjcgNjIuOTgwNSA2Ni43NzE1QzYyLjk5MzQgNjYuNTE1OSA2My4wMDIgNjYuMjU4OCA2My4wMDIgNjZWMzNINjlWNjZDNjkgNzQuMjg0MyA3NS43MTU3IDgxIDg0IDgxSDkyLjgwMjdDODguMTE5NCA4OS45MTcgNzguNzcxMSA5NiA2OCA5NkgyOEMxNy4yMjg5IDk2IDcuODgwNTkgODkuOTE3IDMuMTk3MjcgODFIMTJDMTMuMDI3NCA4MSAxNC4wMzA2IDgwLjg5NiAxNSA4MC42OTkyVjgxSDE1LjAwMVY4MC42OTkyQzE1LjE5OTUgODAuNjU4OSAxNS4zOTYyIDgwLjYxMzUgMTUuNTkxOCA4MC41NjU0QzE1LjYxODUgODAuNTU4OSAxNS42NDUyIDgwLjU1MjYgMTUuNjcxOSA4MC41NDU5QzE1Ljg1ODUgODAuNDk4OSAxNi4wNDM3IDgwLjQ0ODQgMTYuMjI3NSA4MC4zOTQ1QzE2LjI1MyA4MC4zODcxIDE2LjI3ODMgODAuMzc4NyAxNi4zMDM3IDgwLjM3MTFDMTYuNDkwNCA4MC4zMTUzIDE2LjY3NTggODAuMjU3MiAxNi44NTk0IDgwLjE5NDNDMTYuODc4NCA4MC4xODc4IDE2Ljg5NyA4MC4xODA0IDE2LjkxNiA4MC4xNzM4QzE3LjI4OTkgODAuMDQ0MiAxNy42NTY4IDc5Ljg5OTkgMTguMDE2NiA3OS43NDIyQzE4LjA0ODUgNzkuNzI4MiAxOC4wODA1IDc5LjcxNDQgMTguMTEyMyA3OS43MDAyQzE4LjI3OSA3OS42MjU3IDE4LjQ0NCA3OS41NDgxIDE4LjYwNzQgNzkuNDY3OEMxOC42MzQ3IDc5LjQ1NDQgMTguNjYyMiA3OS40NDEzIDE4LjY4OTUgNzkuNDI3N0MxOC44NTM5IDc5LjM0NTYgMTkuMDE2OCA3OS4yNjA4IDE5LjE3NzcgNzkuMTcyOUMxOS4yMTExIDc5LjE1NDYgMTkuMjQ0MSA3OS4xMzU3IDE5LjI3NzMgNzkuMTE3MkMxOS40MjYgNzkuMDM0NSAxOS41NzMzIDc4Ljk0OTggMTkuNzE4OCA3OC44NjIzQzE5Ljc0MDggNzguODQ5MSAxOS43NjMyIDc4LjgzNjYgMTkuNzg1MiA3OC44MjMyQzE5Ljk0NDYgNzguNzI2MiAyMC4xMDE0IDc4LjYyNTIgMjAuMjU2OCA3OC41MjI1QzIwLjI3NzkgNzguNTA4NiAyMC4yOTk0IDc4LjQ5NTQgMjAuMzIwMyA3OC40ODE0QzIwLjQ3MDUgNzguMzgxMSAyMC42MTgzIDc4LjI3NzQgMjAuNzY0NiA3OC4xNzE5QzIwLjgwNTUgNzguMTQyNCAyMC44NDYyIDc4LjExMjkgMjAuODg2NyA3OC4wODNDMjEuMDE4OCA3Ny45ODU3IDIxLjE0OTYgNzcuODg2NyAyMS4yNzgzIDc3Ljc4NTJDMjEuMzIzMyA3Ny43NDk3IDIxLjM2NzYgNzcuNzEzNiAyMS40MTIxIDc3LjY3NzdDMjEuNTMwOSA3Ny41ODE5IDIxLjY0NzkgNzcuNDg0MSAyMS43NjM3IDc3LjM4NDhDMjEuNzgwOSA3Ny4zNyAyMS43OTkzIDc3LjM1NjYgMjEuODE2NCA3Ny4zNDE4QzIxLjk1MjUgNzcuMjIzOSAyMi4wODYgNzcuMTAzMSAyMi4yMTc4IDc2Ljk4MDVDMjIuMjUwOCA3Ni45NDk3IDIyLjI4MzYgNzYuOTE4NyAyMi4zMTY0IDc2Ljg4NzdDMjIuNDMyOSA3Ni43NzczIDIyLjU0NzMgNzYuNjY0OCAyMi42NjAyIDc2LjU1MDhDMjIuNzAzOSA3Ni41MDY2IDIyLjc0NjggNzYuNDYxNyAyMi43OSA3Ni40MTdDMjIuOTAzIDc2LjMgMjMuMDE0OSA3Ni4xODIxIDIzLjEyNCA3Ni4wNjE1QzIzLjE2MTUgNzYuMDIwMiAyMy4xOTg0IDc1Ljk3ODMgMjMuMjM1NCA3NS45MzY1QzIzLjMxNDYgNzUuODQ3IDIzLjM5MjYgNzUuNzU2NCAyMy40Njk3IDc1LjY2NUMyMy41Mzc0IDc1LjU4NDggMjMuNjAzOSA3NS41MDM1IDIzLjY2OTkgNzUuNDIxOUMyMy43NDE2IDc1LjMzMzIgMjMuODEyMiA3NS4yNDM2IDIzLjg4MTggNzUuMTUzM0MyMy45Mjc4IDc1LjA5MzggMjMuOTc0NSA3NS4wMzQ4IDI0LjAxOTUgNzQuOTc0NkMyNC4xMDU0IDc0Ljg1OTggMjQuMTg4OSA3NC43NDMzIDI0LjI3MTUgNzQuNjI2QzI0LjMxMjEgNzQuNTY4MyAyNC4zNTI3IDc0LjUxMDUgMjQuMzkyNiA3NC40NTIxQzI0LjQ3NTUgNzQuMzMwOSAyNC41NTczIDc0LjIwODggMjQuNjM2NyA3NC4wODVDMjQuNjgyOCA3NC4wMTMxIDI0LjcyNjYgNzMuOTM5OSAyNC43NzE1IDczLjg2NzJDMjQuODIzIDczLjc4MzcgMjQuODczOSA3My42OTk4IDI0LjkyMzggNzMuNjE1MkMyNC45Nzg0IDczLjUyMjkgMjUuMDMxNCA3My40Mjk2IDI1LjA4NCA3My4zMzU5QzI1LjEzOTUgNzMuMjM3MiAyNS4xOTQ3IDczLjEzODIgMjUuMjQ4IDczLjAzODFDMjUuMjgwMiA3Mi45Nzc3IDI1LjMxMjQgNzIuOTE3MyAyNS4zNDM4IDcyLjg1NjRDMjUuNDEwNCA3Mi43MjcgMjUuNDc1MSA3Mi41OTY0IDI1LjUzODEgNzIuNDY0OEMyNS41NzY3IDcyLjM4NDIgMjUuNjE0MiA3Mi4zMDMxIDI1LjY1MTQgNzIuMjIxN0MyNS43MTA3IDcyLjA5MTYgMjUuNzY5NSA3MS45NjExIDI1LjgyNTIgNzEuODI5MUMyNS44NTI5IDcxLjc2MzUgMjUuODc4NSA3MS42OTcgMjUuOTA1MyA3MS42MzA5QzI1Ljk0NTcgNzEuNTMxMSAyNS45ODUxIDcxLjQzMDkgMjYuMDIzNCA3MS4zMzAxQzI2LjA2ODcgNzEuMjExIDI2LjExMiA3MS4wOTEyIDI2LjE1NDMgNzAuOTcwN0MyNi4xODQ3IDcwLjg4NCAyNi4yMTUzIDcwLjc5NzMgMjYuMjQ0MSA3MC43MUMyNi4zMDQ5IDcwLjUyNjIgMjYuMzYyMyA3MC4zNDExIDI2LjQxNiA3MC4xNTQzQzI2LjQyMjkgNzAuMTMwMiAyNi40Mjk3IDcwLjEwNjEgMjYuNDM2NSA3MC4wODJDMjYuNDY1NSA2OS45NzkyIDI2LjQ5MzYgNjkuODc2MSAyNi41MjA1IDY5Ljc3MjVDMjYuNTU0MiA2OS42NDIyIDI2LjU4NjkgNjkuNTExNSAyNi42MTcyIDY5LjM3OTlDMjYuNjM1OCA2OS4yOTkxIDI2LjY1MjYgNjkuMjE4IDI2LjY2OTkgNjkuMTM2N0MyNi42ODgzIDY5LjA1MDQgMjYuNzA1OCA2OC45NjM4IDI2LjcyMjcgNjguODc3QzI2Ljc1MjcgNjguNzIyMiAyNi43ODA0IDY4LjU2NjYgMjYuODA1NyA2OC40MTAyQzI2LjgxNyA2OC4zNDAzIDI2LjgyODUgNjguMjcwNCAyNi44Mzg5IDY4LjIwMDJDMjYuODUzMSA2OC4xMDM3IDI2Ljg2NzUgNjguMDA3MiAyNi44Nzk5IDY3LjkxMDJDMjYuODk4IDY3Ljc2NzMgMjYuOTEyNiA2Ny42MjM2IDI2LjkyNjggNjcuNDc5NUMyNi45MzYxIDY3LjM4NDMgMjYuOTQ0NiA2Ny4yODkgMjYuOTUyMSA2Ny4xOTM0QzI2Ljk2MzIgNjcuMDUzMiAyNi45NzMzIDY2LjkxMjcgMjYuOTgwNSA2Ni43NzE1QzI2Ljk5MzQgNjYuNTE1OSAyNy4wMDIgNjYuMjU4OCAyNy4wMDIgNjZWMzNIMzNWNjZaTTY4IDBDODMuNDY0IDEuMDMwODJlLTA2IDk2IDEyLjUzNiA5NiAyOFY2OEM5NiA2OC4zMzQ4IDk1Ljk5MjEgNjguNjY4MSA5NS45ODA1IDY5SDgxLjAwMVYzNkM4MS4wMDEgMzUuNzQ2OCA4MC45OTI5IDM1LjQ5NTIgODAuOTgwNSAzNS4yNDUxQzgwLjk3MjQgMzUuMDgyIDgwLjk2MTUgMzQuOTE5NSA4MC45NDgyIDM0Ljc1NzhDODAuOTQyOSAzNC42OTI2IDgwLjkzNjggMzQuNjI3NSA4MC45MzA3IDM0LjU2MjVDODAuOTE0NCAzNC4zOTE3IDgwLjg5NTkgMzQuMjIxOCA4MC44NzQgMzQuMDUyN0M4MC44NjYyIDMzLjk5MjcgODAuODU3MSAzMy45MzI5IDgwLjg0ODYgMzMuODczQzgwLjgzMDMgMzMuNzQzNiA4MC44MTA3IDMzLjYxNDcgODAuNzg5MSAzMy40ODYzQzgwLjc3MzMgMzMuMzkyOSA4MC43NTY3IDMzLjI5OTggODAuNzM5MyAzMy4yMDdDODAuNzEzMyAzMy4wNjkzIDgwLjY4NTkgMzIuOTMyMiA4MC42NTYyIDMyLjc5NTlDODAuNjQ1OCAzMi43NDggODAuNjM1OSAzMi43MDAxIDgwLjYyNSAzMi42NTIzQzgwLjU4ODYgMzIuNDkyNyA4MC41NDgzIDMyLjMzNDQgODAuNTA2OCAzMi4xNzY4QzgwLjQ4NzkgMzIuMTA0NiA4MC40NjgzIDMyLjAzMjcgODAuNDQ4MiAzMS45NjA5QzgwLjQwNzYgMzEuODE1MyA4MC4zNjYyIDMxLjY3MDIgODAuMzIxMyAzMS41MjY0QzgwLjI5NjUgMzEuNDQ2OSA4MC4yNzAyIDMxLjM2OCA4MC4yNDQxIDMxLjI4OTFDODAuMjA2NCAzMS4xNzQ4IDgwLjE2NzQgMzEuMDYxMiA4MC4xMjcgMzAuOTQ4MkM4MC4xMDE0IDMwLjg3NjcgODAuMDc1NSAzMC44MDU0IDgwLjA0ODggMzAuNzM0NEM3OS45OTkyIDMwLjYwMiA3OS45NDc4IDMwLjQ3MDQgNzkuODk0NSAzMC4zMzk4Qzc5Ljg3MzUgMzAuMjg4MyA3OS44NTI2IDMwLjIzNjggNzkuODMxMSAzMC4xODU1Qzc5Ljc2ODQgMzAuMDM2OCA3OS43MDMgMjkuODg5NSA3OS42MzU3IDI5Ljc0MzJDNzkuNjA5MyAyOS42ODU2IDc5LjU4MTkgMjkuNjI4NSA3OS41NTQ3IDI5LjU3MTNDNzkuNDgyIDI5LjQxODMgNzkuNDA3NyAyOS4yNjYzIDc5LjMzMDEgMjkuMTE2MkM3OS4zMTE1IDI5LjA4MDIgNzkuMjkyMyAyOS4wNDQ2IDc5LjI3MzQgMjkuMDA4OEM3OS4yMDg2IDI4Ljg4NTkgNzkuMTQxNCAyOC43NjQ0IDc5LjA3MzIgMjguNjQzNkM3OS4wMjQxIDI4LjU1NjUgNzguOTc0NiAyOC40Njk4IDc4LjkyMzggMjguMzgzOEM3OC44NjE4IDI4LjI3ODggNzguNzk4OCAyOC4xNzQ2IDc4LjczNDQgMjguMDcxM0M3OC43MDc3IDI4LjAyODYgNzguNjgxNCAyNy45ODU4IDc4LjY1NDMgMjcuOTQzNEM3OC41NTY5IDI3Ljc5MDYgNzguNDU2MyAyNy42NDAxIDc4LjM1MzUgMjcuNDkxMkM3OC4zMzggMjcuNDY4NyA3OC4zMjIzIDI3LjQ0NjMgNzguMzA2NiAyNy40MjM4Qzc4LjIwMzUgMjcuMjc2MiA3OC4wOTg0IDI3LjEzIDc3Ljk5MDIgMjYuOTg2M0M3Ny45NjQgMjYuOTUxNSA3Ny45MzY3IDI2LjkxNzQgNzcuOTEwMiAyNi44ODI4Qzc3LjgxNjYgMjYuNzYwOCA3Ny43MjEyIDI2LjY0MDUgNzcuNjI0IDI2LjUyMTVDNzcuNTgyIDI2LjQ3MDEgNzcuNTM5NyAyNi40MTkgNzcuNDk3MSAyNi4zNjgyQzc3LjM5NzcgMjYuMjQ5NiA3Ny4yOTczIDI2LjEzMiA3Ny4xOTQzIDI2LjAxNjZDNzcuMTc2MSAyNS45OTYxIDc3LjE1OCAyNS45NzU0IDc3LjEzOTYgMjUuOTU1MUM3Ny4wMTc2IDI1LjgxOTggNzYuODkyNSAyNS42ODc0IDc2Ljc2NTYgMjUuNTU2NkM3Ni43MzggMjUuNTI4MiA3Ni43MTA0IDI1LjQ5OTkgNzYuNjgyNiAyNS40NzE3Qzc2LjU1NTkgMjUuMzQzMSA3Ni40MjcxIDI1LjIxNjcgNzYuMjk1OSAyNS4wOTI4Qzc2LjI3NzMgMjUuMDc1MiA3Ni4yNTg5IDI1LjA1NzUgNzYuMjQwMiAyNS4wNEM3Ni4wOTg1IDI0LjkwNzUgNzUuOTU0NSAyNC43Nzc0IDc1LjgwNzYgMjQuNjUwNEM3NS43OTY0IDI0LjY0MDcgNzUuNzg0NyAyNC42MzE3IDc1Ljc3MzQgMjQuNjIyMUM3NS42MjQzIDI0LjQ5MzggNzUuNDcyNSAyNC4zNjg1IDc1LjMxODQgMjQuMjQ2MUM3NS4zMTY2IDI0LjI0NDcgNzUuMzE1MyAyNC4yNDI2IDc1LjMxMzUgMjQuMjQxMkM3NS4xNzA5IDI0LjEyODEgNzUuMDI1NyAyNC4wMTgxIDc0Ljg3ODkgMjMuOTEwMkM3NC44NDEgMjMuODgyMyA3NC44MDI4IDIzLjg1NDcgNzQuNzY0NiAyMy44MjcxQzc0LjYxODMgMjMuNzIxNiA3NC40NzA1IDIzLjYxNzkgNzQuMzIwMyAyMy41MTc2Qzc0LjI5OTQgMjMuNTAzNiA3NC4yNzc5IDIzLjQ5MDQgNzQuMjU2OCAyMy40NzY2Qzc0LjA5OTIgMjMuMzcyNCA3My45NDAxIDIzLjI3MDIgNzMuNzc4MyAyMy4xNzE5QzczLjc1ODYgMjMuMTU5OSA3My43Mzg1IDIzLjE0ODYgNzMuNzE4OCAyMy4xMzY3QzczLjU3MzMgMjMuMDQ5MiA3My40MjYgMjIuOTY0NSA3My4yNzczIDIyLjg4MThDNzMuMjQ0MSAyMi44NjM0IDczLjIxMTEgMjIuODQ0NCA3My4xNzc3IDIyLjgyNjJDNzMuMDExIDIyLjczNTEgNzIuODQyMyAyMi42NDczIDcyLjY3MTkgMjIuNTYyNUM3Mi42NTY0IDIyLjU1NDggNzIuNjQwNSAyMi41NDc3IDcyLjYyNSAyMi41NEM3Mi40NTU0IDIyLjQ1NjQgNzIuMjg0NCAyMi4zNzUyIDcyLjExMTMgMjIuMjk3OUM3Mi4wNzk5IDIyLjI4MzggNzIuMDQ4MSAyMi4yNzA3IDcyLjAxNjYgMjIuMjU2OEM3MS42NTY4IDIyLjA5OTEgNzEuMjg5OSAyMS45NTQ4IDcwLjkxNiAyMS44MjUyQzcwLjg5NyAyMS44MTg2IDcwLjg3ODQgMjEuODExMiA3MC44NTk0IDIxLjgwNDdDNzAuNjc1OCAyMS43NDE5IDcwLjQ5MDQgMjEuNjgzNyA3MC4zMDM3IDIxLjYyNzlDNzAuMjc4MyAyMS42MjAzIDcwLjI1MyAyMS42MTIgNzAuMjI3NSAyMS42MDQ1QzcwLjA0MzcgMjEuNTUwNiA2OS44NTg1IDIxLjUwMDEgNjkuNjcxOSAyMS40NTMxQzY5LjY0NTIgMjEuNDQ2NCA2OS42MTg1IDIxLjQ0MDIgNjkuNTkxOCAyMS40MzM2QzY5LjM5NjIgMjEuMzg1NSA2OS4xOTk1IDIxLjM0MDEgNjkuMDAxIDIxLjI5OThWMjFINjlWMjEuMjk5OEM2OC4wMzA3IDIxLjEwMyA2Ny4wMjc0IDIxIDY2IDIxQzU3LjcxNTcgMjEgNTEgMjcuNzE1NyA1MSAzNlY2OUg0NS4wMDJWMzZDNDUuMDAyIDM1LjY5MzcgNDQuOTg4OCAzNS4zODk3IDQ0Ljk3MDcgMzUuMDg3OUM0NC45NjUgMzQuOTkzNiA0NC45NTk1IDM0Ljg5OTUgNDQuOTUyMSAzNC44MDU3QzQ0Ljk0NDYgMzQuNzEgNDQuOTM2MSAzNC42MTQ3IDQ0LjkyNjggMzQuNTE5NUM0NC45MTI2IDM0LjM3NTEgNDQuODk3MSAzNC4yMzExIDQ0Ljg3ODkgMzQuMDg3OUM0NC44NjY2IDMzLjk5MTIgNDQuODUzIDMzLjg5NDkgNDQuODM4OSAzMy43OTg4QzQ0LjgyODUgMzMuNzI4NiA0NC44MTcgMzMuNjU4NyA0NC44MDU3IDMzLjU4ODlDNDQuNzgwNCAzMy40MzI0IDQ0Ljc1MjcgMzMuMjc2OSA0NC43MjI3IDMzLjEyMjFDNDQuNzA1OCAzMy4wMzUyIDQ0LjY4ODMgMzIuOTQ4NiA0NC42Njk5IDMyLjg2MjNDNDQuNjUyMSAzMi43Nzg4IDQ0LjYzNTQgMzIuNjk1MyA0NC42MTYyIDMyLjYxMjNDNDQuNTg2NCAzMi40ODMgNDQuNTUzNyAzMi4zNTQ2IDQ0LjUyMDUgMzIuMjI2NkM0NC40OTM2IDMyLjEyMjkgNDQuNDY1NSAzMi4wMTk4IDQ0LjQzNjUgMzEuOTE3QzQ0LjQwMDggMzEuNzkwNCA0NC4zNjQxIDMxLjY2NDMgNDQuMzI1MiAzMS41MzkxQzQ0LjI5OTEgMzEuNDU1MyA0NC4yNzE2IDMxLjM3MjIgNDQuMjQ0MSAzMS4yODkxQzQ0LjIxNTMgMzEuMjAxNyA0NC4xODQ3IDMxLjExNSA0NC4xNTQzIDMxLjAyODNDNDQuMTEyIDMwLjkwNzkgNDQuMDY4NyAzMC43ODggNDQuMDIzNCAzMC42Njg5QzQzLjk4NTEgMzAuNTY4MSA0My45NDU3IDMwLjQ2NzkgNDMuOTA1MyAzMC4zNjgyQzQzLjg3NzMgMzAuMjk5MSA0My44NTAzIDMwLjIyOTcgNDMuODIxMyAzMC4xNjExQzQzLjc2NjcgMzAuMDMyMSA0My43MDk0IDI5LjkwNDUgNDMuNjUxNCAyOS43NzczQzQzLjYxNDIgMjkuNjk1OSA0My41NzY3IDI5LjYxNDggNDMuNTM4MSAyOS41MzQyQzQzLjQ3NTEgMjkuNDAyNiA0My40MTA0IDI5LjI3MiA0My4zNDM4IDI5LjE0MjZDNDMuMzEyNCAyOS4wODE3IDQzLjI4MDIgMjkuMDIxMyA0My4yNDggMjguOTYwOUM0My4xOTQ3IDI4Ljg2MDggNDMuMTM5NSAyOC43NjE4IDQzLjA4NCAyOC42NjMxQzQzLjAzMTQgMjguNTY5NSA0Mi45Nzg0IDI4LjQ3NjIgNDIuOTIzOCAyOC4zODM4QzQyLjg3MzkgMjguMjk5MiA0Mi44MjMgMjguMjE1MyA0Mi43NzE1IDI4LjEzMThDNDIuNzE0NiAyOC4wMzk4IDQyLjY1NzQgMjcuOTQ4MSA0Mi41OTg2IDI3Ljg1NzRDNDIuNTMxMSAyNy43NTMxIDQyLjQ2MjYgMjcuNjQ5NCA0Mi4zOTI2IDI3LjU0NjlDNDIuMzUyNyAyNy40ODg2IDQyLjMxMjIgMjcuNDMwOCA0Mi4yNzE1IDI3LjM3M0M0Mi4xODYyIDI3LjI1MiA0Mi4wOTk1IDI3LjEzMjEgNDIuMDEwNyAyNy4wMTM3QzQxLjk2ODQgMjYuOTU3MiA0MS45MjUgMjYuOTAxNiA0MS44ODE4IDI2Ljg0NTdDNDEuODEyMSAyNi43NTU0IDQxLjc0MTYgMjYuNjY1OCA0MS42Njk5IDI2LjU3NzFDNDEuNTk4OSAyNi40ODk0IDQxLjUyNyAyNi40MDI1IDQxLjQ1NDEgMjYuMzE2NEM0MS4zODE5IDI2LjIzMTEgNDEuMzA5NCAyNi4xNDYxIDQxLjIzNTQgMjYuMDYyNUM0MS4xOTg0IDI2LjAyMDcgNDEuMTYxNSAyNS45Nzg5IDQxLjEyNCAyNS45Mzc1QzQxLjAxNDkgMjUuODE2OSA0MC45MDMgMjUuNjk5IDQwLjc5IDI1LjU4MkM0MC43NDY4IDI1LjUzNzMgNDAuNzAzOSAyNS40OTI0IDQwLjY2MDIgMjUuNDQ4MkM0MC41NDczIDI1LjMzNDIgNDAuNDMyOSAyNS4yMjE3IDQwLjMxNjQgMjUuMTExM0M0MC4yODMgMjUuMDc5NiA0MC4yNDg2IDI1LjA0OSA0MC4yMTQ4IDI1LjAxNzZDNDAuMDgxIDI0Ljg5MzEgMzkuOTQ1OSAyNC43NyAzOS44MDc2IDI0LjY1MDRDMzkuNzkzMyAyNC42MzggMzkuNzc4MSAyNC42MjY2IDM5Ljc2MzcgMjQuNjE0M0MzOS42NDc5IDI0LjUxNDkgMzkuNTMwOSAyNC40MTcxIDM5LjQxMjEgMjQuMzIxM0MzOS4zNjc2IDI0LjI4NTQgMzkuMzIzMiAyNC4yNDkzIDM5LjI3ODMgMjQuMjEzOUMzOS4xNDk2IDI0LjExMjQgMzkuMDE4OCAyNC4wMTMzIDM4Ljg4NjcgMjMuOTE2QzM4Ljg0NjIgMjMuODg2MiAzOC44MDU1IDIzLjg1NjYgMzguNzY0NiAyMy44MjcxQzM4LjYxODMgMjMuNzIxNiAzOC40NzA1IDIzLjYxNzkgMzguMzIwMyAyMy41MTc2QzM4LjI5OTQgMjMuNTAzNiAzOC4yNzc5IDIzLjQ5MDQgMzguMjU2OCAyMy40NzY2QzM4LjA5OTIgMjMuMzcyNCAzNy45NDAxIDIzLjI3MDIgMzcuNzc4MyAyMy4xNzE5QzM3Ljc1ODYgMjMuMTU5OSAzNy43Mzg1IDIzLjE0ODYgMzcuNzE4OCAyMy4xMzY3QzM3LjU3MzMgMjMuMDQ5MiAzNy40MjYgMjIuOTY0NSAzNy4yNzczIDIyLjg4MThDMzcuMjQ0MSAyMi44NjM0IDM3LjIxMTEgMjIuODQ0NCAzNy4xNzc3IDIyLjgyNjJDMzcuMDE2OCAyMi43MzgzIDM2Ljg1MzkgMjIuNjUzNCAzNi42ODk1IDIyLjU3MTNDMzYuNjYyMiAyMi41NTc3IDM2LjYzNDcgMjIuNTQ0NyAzNi42MDc0IDIyLjUzMTJDMzYuNDQ0IDIyLjQ1MDkgMzYuMjc5IDIyLjM3MzMgMzYuMTEyMyAyMi4yOTg4QzM2LjA4MDUgMjIuMjg0NiAzNi4wNDg1IDIyLjI3MDggMzYuMDE2NiAyMi4yNTY4QzM1LjY1NjggMjIuMDk5MSAzNS4yODk5IDIxLjk1NDggMzQuOTE2IDIxLjgyNTJDMzQuODk3IDIxLjgxODYgMzQuODc4NCAyMS44MTEyIDM0Ljg1OTQgMjEuODA0N0MzNC42NzU5IDIxLjc0MTkgMzQuNDkwNCAyMS42ODM3IDM0LjMwMzcgMjEuNjI3OUMzNC4yNzgzIDIxLjYyMDMgMzQuMjUzIDIxLjYxMiAzNC4yMjc1IDIxLjYwNDVDMzQuMDQzNyAyMS41NTA2IDMzLjg1ODUgMjEuNTAwMSAzMy42NzE5IDIxLjQ1MzFDMzMuNjQ1MiAyMS40NDY0IDMzLjYxODUgMjEuNDQwMiAzMy41OTE4IDIxLjQzMzZDMzMuMzk2MiAyMS4zODU1IDMzLjE5OTUgMjEuMzQwMSAzMy4wMDEgMjEuMjk5OFYyMUgzM1YyMS4yOTk4QzMyLjAzMDcgMjEuMTAzIDMxLjAyNzQgMjEgMzAgMjFDMjEuNzE1NyAyMSAxNSAyNy43MTU3IDE1IDM2VjY5SDAuMDE5NTMxMkMwLjAwNzg3OTI2IDY4LjY2ODEgMCA2OC4zMzQ4IDAgNjhWMjhDMS4wMzA4OGUtMDYgMTIuNTM2IDEyLjUzNiAwIDI4IDBINjhaIiBmaWxsPSJ3aGl0ZSIvPgo8L2c+CjxkZWZzPgo8Y2xpcFBhdGggaWQ9ImNsaXAwXzg1NF83ODQ5Ij4KPHJlY3Qgd2lkdGg9Ijk2IiBoZWlnaHQ9Ijk2IiBmaWxsPSJ3aGl0ZSIvPgo8L2NsaXBQYXRoPgo8L2RlZnM+Cjwvc3ZnPgo=';

		// Register main memberships page as standalone admin page (like SureDash).
		add_menu_page(
			__( 'SureMembers', 'suremembers-core' ),
			__( 'SureMembers', 'suremembers-core' ),
			'manage_options',
			'suremembers',
			[ $this, 'render' ],
			'data:image/svg+xml;base64,' . $logo, //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			30
		);

		// Add submenu for memberships (will replace the auto-generated first item).
		add_submenu_page(
			'suremembers',
			__( 'Dashboard', 'suremembers-core' ),
			__( 'Dashboard', 'suremembers-core' ),
			'manage_options',
			'suremembers',
			[ $this, 'render' ]
		);

		add_submenu_page(
			'suremembers',
			__( 'Memberships', 'suremembers-core' ),
			__( 'Memberships', 'suremembers-core' ),
			'manage_options',
			'suremembers&tab=memberships',
			[ $this, 'render' ]
		);

			// Add users submenu.
		add_submenu_page(
			'suremembers',
			__( 'Members', 'suremembers-core' ),
			__( 'Members', 'suremembers-core' ),
			'manage_options',
			'suremembers&tab=users',
			[ $this, 'render' ]
		);

		// Add analytics submenu.
		add_submenu_page(
			'suremembers',
			__( 'Analytics', 'suremembers-core' ),
			__( 'Analytics', 'suremembers-core' ),
			'manage_options',
			'suremembers&tab=analytics',
			[ $this, 'render' ]
		);

		// Add settings submenu.
		add_submenu_page(
			'suremembers',
			__( 'Settings', 'suremembers-core' ),
			__( 'Settings', 'suremembers-core' ),
			'manage_options',
			'suremembers&tab=settings',
			[ $this, 'render' ]
		);

		// Add settings submenu.
		add_submenu_page(
			'suremembers',
			__( 'SureDash', 'suremembers-core' ),
			__( 'SureDash', 'suremembers-core' ),
			'manage_options',
			'suremembers&tab=suredash',
			[ $this, 'render' ]
		);

		// Add hidden onboarding page (similar to SureDash).
		add_submenu_page(
			'suremembers',
			'SureMembers ' . __( 'Onboarding', 'suremembers-core' ),
			'Onboarding',
			'manage_options',
			'suremembers-onboarding',
			[ $this, 'render_onboarding' ]
		);

		// Hide the old CPT menu completely.
		global $submenu;
		global $menu;

		// Remove all submenus under the CPT parent.
		$parent_slug = 'edit.php?post_type=' . SUREMEMBERS_POST_TYPE;

		if ( isset( $submenu[ $parent_slug ] ) ) {
			unset( $submenu[ $parent_slug ] );
		}

		// Remove the main CPT menu item from sidebar.
		foreach ( $menu as $key => $item ) {
			if ( isset( $item[2] ) && $parent_slug === $item[2] ) {
				unset( $menu[ $key ] );
				break;
			}
		}
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public function render() {
		// Use same container for both pages (unified app approach like SureDash).
		?>
		<div class="suremembers-access-group" id="suremembers-access-group"></div>
		<?php
	}

	/**
	 * Render onboarding page
	 *
	 * @since 1.0.0
	 */
	public function render_onboarding() {
		?>
		<div class="suremembers-onboarding" id="suremembers-onboarding"></div>
		<?php
	}

	/**
	 * Enqueue settings page script and style
	 *
	 * @param string $hook current page hook.
	 *
	 * @since  1.0.0
	 */
	public function settings_page_scripts( $hook ) {
		// Check if we're on SureMembers pages.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Ignoring this as we are getting the data from URL and using further.
		if ( isset( $_GET['suremembers_view'] ) && $_GET['suremembers_view'] === 'iframe' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_style( 'suremembers-admin-frame-popup', SUREMEMBERS_CORE_URL . 'admin/assets/build/adminbarpopup.css', [], SUREMEMBERS_CORE_VER );

			// Add inline JavaScript for iframe mode.
			wp_add_inline_script(
				'jquery',
				'
				jQuery(document).ready(function($) {
					// Hide WordPress admin elements immediately
					$("#wpadminbar, #adminmenumain, #adminmenuwrap, #adminmenu, #adminmenu-back").hide();
					$("#wpcontent").css({"margin-left": "0", "padding-left": "0", "width": "100%"});
					$("#wpbody").css({"margin-left": "0", "padding-left": "0"});
					$("#wpfooter").hide();

					// Handle compact mode
					if (window.location.search.includes("suremembers_compact=1")) {
						// Add compact data attribute for CSS targeting
						$("body").attr("data-compact", "1");

						// Remove unnecessary WordPress elements for faster loading
						$(".notice, .error, .updated").hide();
						$("#wpbody-content").css({"margin-left": "0", "padding-left": "0"});
						$(".wrap").css({"margin": "0", "padding": "20px"});

						// Optimize for faster loading
						$("script[src*=\'admin-bar\']").remove();
						$("script[src*=\'common\']").remove();
						$("script[src*=\'heartbeat\']").remove();
						$("script[src*=\'dashboard\']").remove();
						$("script[src*=\'postbox\']").remove();

						// Add loading optimization
						$("img").each(function() {
							if ($(this).attr("src") && $(this).attr("src").includes("loading")) {
								$(this).attr("loading", "lazy");
							}
						});

						// Disable WordPress heartbeat for faster performance
						if (typeof wp !== "undefined" && wp.heartbeat) {
							wp.heartbeat.interval(0);
						}
					}
				});
			'
			);
		}

		$screen = get_current_screen();

		// Check if we're on one of the SureMembers admin pages.
		$suremembers_pages = [
			'toplevel_page_suremembers',
			'suremembers_page_suremembers-settings',
			'suremembers_page_suremembers-settings', // Fallback for different WordPress versions.
		];

		if (
			is_null( $screen ) ||
			( ! in_array( $screen->id, $suremembers_pages, true ) && $current_page !== 'suremembers-onboarding' )
		) {
			return;
		}

		$page       = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_count = 0; // Initialize with default value.

		// Use unified suremembers app for all pages.
		$app = 'suremembers';

		// Get post count for memberships page.
		if ( in_array( $page, [ 'suremembers', 'suremembers-settings' ], true ) ) {
			$post_count = wp_count_posts( SUREMEMBERS_POST_TYPE )->publish;
		}

		$script_asset_path = SUREMEMBERS_CORE_DIR . 'assets/build/' . $app . '.asset.php';
		$script_info       = file_exists( $script_asset_path )
			? include $script_asset_path
			: [
				'dependencies' => [],
				'version'      => SUREMEMBERS_CORE_VER,
			];

		$external_deps = [ 'updates' ];

		/**
		 * Check for modules dependencies.
		 * `suremembers-modules` is the handle of the script JS generated when
		 * external modules are loaded.
		 */
		if ( wp_script_is( 'suremembers-modules', 'registered' ) ) {
			array_push( $external_deps, 'suremembers-modules' );
		}

		$script_dep = array_merge( $script_info['dependencies'], $external_deps );

		// Enqueue media library for file uploads.
		wp_enqueue_media();

		wp_register_script( 'suremembers_posts', $this->tailwind_assets . $app . '.js', $script_dep, $script_info['version'], true );
		wp_enqueue_script( 'suremembers_posts' );
		$suremembers_post_types = Restricted::get_post_types( 'object' );
		$locations              = $this->get_location_selections();

		$list_url_args = [];
		// Ignoring this as we are getting the data from URL and using further.
		if ( isset( $_GET['suremembers_view'] ) && $_GET['suremembers_view'] === 'iframe' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$list_url_args['suremembers_view'] = 'iframe';
		}

		$list_archive_url = Access_Groups::get_admin_url( $list_url_args );

		$localize_data = [
			'ajax_url'               => admin_url( 'admin-ajax.php' ),
			'post_url'               => admin_url( 'admin.php?page=suremembers' ),
			'add_new_url'            => admin_url( 'admin.php?page=suremembers' ),
			'posts_nonce'            => current_user_can( 'manage_options' ) ? wp_create_nonce( 'suremembers_posts_nonce' ) : '',
			'submit_nonce'           => current_user_can( 'manage_options' ) ? wp_create_nonce( 'suremembers_submit_nonce' ) : '',
			'search_nonce'           => current_user_can( 'edit_posts' ) ? wp_create_nonce( 'suremembers_search_post_nonce' ) : '',
			'uploads_nonce'          => current_user_can( 'edit_posts' ) ? wp_create_nonce( 'suremembers_uploads_nonce' ) : '',
			'status_nonce'           => wp_create_nonce( 'suremembers_status_nonce' ),
			'list_url'               => $list_archive_url,
			'post_types'             => array_combine( array_keys( $suremembers_post_types ), array_column( array_column( $suremembers_post_types, 'labels' ), 'name' ) ),
			'locations'              => $locations,
			'selected_locations'     => $this->get_selected_locations(),
			'specific_locations'     => $this->get_individual_data( 'specific' ),
			'exclude_locations'      => $this->get_individual_data(),
			'restricted_url'         => $this->get_restricted_url_data(),
			'user_roles_choises'     => $this->get_user_roles_with_custom_roles(),
			'user_roles_selected'    => Access_Groups::get_selected_user_roles(),
			'access_group_downloads' => Access_Groups::get_downloads(),
			'access_group_count'     => $post_count,
			/**
			 * Filter to allow third-party plugins to register their settings sections.
			 *
			 * Third-party plugins can add their sections with the following structure:
			 * [
			 *     'section_id' => [
			 *         'title'       => 'Section Title',
			 *         'description' => 'Optional description',
			 *         'icon'        => 'Settings', // Lucide icon name
			 *         'priority'    => 10,
			 *         'component'   => 'MyPluginSettings', // React component name registered via window
			 *     ],
			 * ]
			 *
			 * @since 1.0.0
			 *
			 * @param array<string, array{title: string, description?: string, icon?: string, priority?: int, component?: string}> $sections Registered sections.
			 */
			'third_party_sections'   => apply_filters( 'suremembers_third_party_sections', [] ),
		];

		wp_localize_script(
			'suremembers_posts',
			'suremembers_posts',
			apply_filters( 'suremembers_get_access_groups_data', $localize_data )
		);

		// Add global settings data available across all SureMembers pages.
		// Note: Settings are now fetched via REST API on demand (following SureDash pattern).
		// Only essential non-settings data is passed here.

		// Learn tab visibility state. Show a "new steps" dot only when the tab
		// is dismissed but free (non-Pro) steps remain incomplete.
		$learn_dismissed     = \SureMembersCore\Inc\Modules\Learn\Learn::is_learn_dismissed();
		$learn               = \SureMembersCore\Inc\Modules\Learn\Learn::get_instance();
		$learn_has_new_steps = $learn_dismissed && method_exists( $learn, 'has_incomplete_free_steps' ) && $learn->has_incomplete_free_steps();

		$settings_data = [
			'version'                   => SUREMEMBERS_CORE_VER,
			'post_type'                 => SUREMEMBERS_POST_TYPE,
			'user_roles'                => $this->get_user_roles_with_custom_roles(),
			'ajax_nonce'                => current_user_can( 'manage_options' ) ? wp_create_nonce( 'suremembers_global_settings_nonce' ) : '',
			'redirect_rules'            => $this->get_redirection_rules(),
			'registration_access_group' => $this->get_selected_registration_access_group(),
			'custom_content'            => Global_Settings::get_custom_content_data(),
			'woocommerce_active'        => function_exists( 'WC' ),
			'home_url'                  => home_url( '/' ),
			'username'                  => wp_get_current_user()->display_name,
			'user_login'                => wp_get_current_user()->user_login,
			'sureforms_status'          => $this->get_plugin_status( 'sureforms/sureforms.php' ),
			'surecart_status'           => $this->get_plugin_status( 'surecart/surecart.php' ),
			'ottokit_status'            => $this->get_plugin_status( 'suretriggers/suretriggers.php' ),
			'suredash_status'           => $this->get_plugin_status( 'suredash/suredash.php' ),
			'suremails_status'          => $this->get_plugin_status( 'suremails/suremails.php' ),
			'suredash_icon_url'         => SUREMEMBERS_CORE_URL . 'assets/images/suredash-icon.svg',
			'wp_rest_nonce'             => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
			'is_premium_active'         => $this->get_plugin_status( 'suremembers/suremembers.php' ) === 'active',
			'upgrade_link'              => 'https://suremembers.com/pricing/?utm_source=suremembers-core&utm_medium=dashboard&utm_campaign=upgrade',
			'pro_promo_image'           => SUREMEMBERS_CORE_URL . 'admin/assets/images/onboarding.png',
			'learn_dismissed'           => $learn_dismissed,
			'learn_has_new_steps'       => $learn_has_new_steps,
			'site_url'                  => untrailingslashit( site_url() ),
			'mcp_adapter_status'        => \SureMembersCore\Inc\Modules\MCP\Module::get_adapter_status(),
		];

		wp_localize_script(
			'suremembers_posts',
			'suremembers_data',
			apply_filters( 'suremembers_settings_data', $settings_data )
		);

		// Add onboarding-specific localization if we're on the onboarding page.
		$current_user = wp_get_current_user();
			wp_localize_script(
				'suremembers_posts',
				'suremembers_onboarding_data',
				[
					'ajax_url'                   => admin_url( 'admin-ajax.php' ),
					'nonce'                      => wp_create_nonce( 'suremembers_onboarding_nonce' ),
					'dashboard_url'              => admin_url( 'admin.php?page=suremembers' ),
					'suredash_onboarding_image'  => SUREMEMBERS_CORE_URL . 'assets/images/suredash-onboarding-cropped.svg',
					'surecart_onboarding_image'  => SUREMEMBERS_CORE_URL . 'assets/images/surecart-onboarding.svg',
					'suremails_onboarding_image' => SUREMEMBERS_CORE_URL . 'assets/images/suremails-onboarding.png',
					'current_user'               => [
						'first_name' => $current_user->first_name,
						'last_name'  => $current_user->last_name,
						'email'      => $current_user->user_email,
					],
				]
			);
			// Add admin settings for onboarding page.
			wp_localize_script(
				'suremembers_posts',
				'suremembers_settings',
				[
					'admin_settings' => \SureMembersCore\Inc\Settings::get_setting( 'suremembers_admin_settings' ),
				]
			);

		wp_register_style( 'suremembers_posts', $this->tailwind_assets . $app . '.css', [ 'wp-components' ], $script_info['version'] );
		wp_enqueue_style( 'suremembers_posts' );

		wp_set_script_translations( 'suremembers_posts', 'suremembers-core', SUREMEMBERS_CORE_DIR . 'languages' );
	}

	/**
	 * Get user roles array also available in settings-screen.php.
	 *
	 * @return array<string, mixed> array of user roles.
	 *
	 * @since 1.1.0
	 */
	public function get_user_roles_with_custom_roles() {
		global $wp_roles;

		if ( ! isset( $wp_roles ) ) {
			return [];
		}

		$available_roles_names = $wp_roles->get_names();
		$excluded_roles        = apply_filters( 'suremembers_settings_excluded_roles', [ 'administrator' => esc_html__( 'Administrator', 'suremembers-core' ) ] );
		$included_roles        = array_diff( $available_roles_names, $excluded_roles );
		return Utils::get_react_select_format( $included_roles );
	}

	/**
	 * Add the CSS to design the main side-bar menu of the plugin.
	 *
	 * @since 1.10.0
	 */
	public function admin_menu_css() {

			echo '<style>
				/* Hide SureDash submenu under SureMembers */
				li a[href="admin.php?page=suremembers&tab=suredash"] {
					display: none !important;
				}
				
				/* Add separator lines between menu items (like SureDash) */
				#toplevel_page_suremembers li {
					clear: both;
				}
				#toplevel_page_suremembers li:not(:last-child) a[href^="admin.php?page=suremembers"]:after {
					border-bottom: 1px solid hsla(0,0%,100%,.2);
					display: block;
					float: left;
					margin: 13px -15px 8px;
					content: "";
					width: calc(100% + 26px);
				}
				/* Remove separator after Memberships (grouped with Settings) */
				#toplevel_page_suremembers li:not(:last-child) a[href^="admin.php?page=suremembers&tab=memberships"]:after {
					content: none;
				}
				/* Remove separator after Users (last item) */
				#toplevel_page_suremembers li:not(:last-child) a[href^="admin.php?page=suremembers&tab=settings"]:after {
					content: none;
				}
				li a[href="admin.php?page=suremembers-onboarding"] {
					display: none !important;
				}
			</style>';

		// Hide WordPress admin elements for onboarding page (similar to SureDash).
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $current_page === 'suremembers-onboarding' ) {
			// phpcs:disable WordPressVIPMinimum.UserExperience.AdminBarRemoval.HidingDetected -- Intentional for onboarding page, similar to SureDash.
			echo '<style>
				#adminmenumain,#wpadminbar,#wpfooter {
					display:none;
				}
				#wpcontent{
					margin-left:0;
					padding-left:0px;
				}
				html.wp-toolbar {
					padding-top: 0;
				}
			</style>';
			// phpcs:enable
		}
	}

	/**
	 * Access plan.
	 *
	 * @since 1.0.0
	 */
	public function get_block_restriction_access_groups() {
		$get_access_groups = Access_Groups::get_active();
		if ( empty( $get_access_groups ) ) {
			return false;
		}
		$return = [];
		foreach ( $get_access_groups as $key => $value ) {
			$return[] = [
				'id'    => $key,
				'title' => $value,
			];
		}
		return $return;
	}

	/**
	 * Return data for edit Access Group
	 *
	 * @param array<string, mixed> $data existing data.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function get_access_group_data( $data ) {
		// Ignored as we are using this to localize data.
		if ( empty( $_GET['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $data;
		}
		// Ignored as we are using this to localize data.
		$id = absint( $_GET['post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $id ) ) {
			return $data;
		}

		$post = get_post( $id );

		if ( empty( $post ) || $post->post_type !== SUREMEMBERS_POST_TYPE ) {
			return $data;
		}

		$data['post_data']['title']      = $post->post_title;
		$data['post_id']                 = $id;
		$data['post_status']             = get_post_status( $id );
		$data['post_data']['priority']   = get_post_meta( $id, SUREMEMBERS_PLAN_PRIORITY, true );
		$data['post_data']['expiration'] = get_post_meta( $id, SUREMEMBERS_PLAN_EXPIRATION, true );

		$meta = get_post_meta( $id, SUREMEMBERS_PLAN_RULES, true );
		if ( empty( $meta ) ) {
			return $data;
		}

		$data['post_data']['meta'] = $meta;
		return $data;
	}

	/**
	 * Get data for Access Groups Table.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 2.0.0
	 */
	public function get_access_groups_table_data() {
		$args = [
			'post_type'      => SUREMEMBERS_POST_TYPE,
			'post_status'    => [ 'publish', 'draft', 'pending', 'private', SUREMEMBERS_ARCHIVE ],
			'posts_per_page' => -1,
		];

		$query = new \WP_Query( $args );
		$data  = [];

		if ( ! $query->have_posts() ) {
			return $data;
		}

		foreach ( $query->posts as $post ) {
			$post_id = $post->ID;

			// Fetch include and exclude meta (should be arrays with 'specifics' and 'rules').
			$include = get_post_meta( $post_id, SUREMEMBERS_PLAN_INCLUDE, true );
			$exclude = get_post_meta( $post_id, SUREMEMBERS_PLAN_EXCLUDE, true );

			// Get specifics (post IDs) for include/exclude and remove duplicates.
			$include_specifics = is_array( $include ) && ! empty( $include['specifics'] ) ? array_unique( $include['specifics'] ) : [];
			$exclude_rules     = is_array( $exclude ) && ! empty( $exclude['rules'] ) ? array_unique( $exclude['rules'] ) : [];

			// Fetch post info for include specifics.
			$include_posts = [];
			$processed_ids = []; // Track processed post IDs to avoid duplicates.
			foreach ( $include_specifics as $inc_id ) {
				// Extract numeric post ID from string like 'post-199-|' or 'postchild-199-|'.
				if ( preg_match( '/(post(?:child)?)-(\d+)-\|?/', $inc_id, $matches ) ) {
					$prefix  = $matches[1];
					$real_id = intval( $matches[2] );

					// Skip if already processed.
					if ( in_array( $prefix . '-' . $real_id, $processed_ids, true ) ) {
						continue;
					}

					$inc_post = get_post( $real_id );
					if ( $inc_post ) {
						$processed_ids[] = $prefix . '-' . $real_id;
						$include_posts[] = [
							'id'    => $inc_post->ID,
							'title' => $prefix === 'postchild' ? sprintf( /* translators: %s title. */ __( 'Child of %s', 'suremembers-core' ), get_the_title( $inc_post ) ) : get_the_title( $inc_post ),
							'type'  => get_post_type( $inc_post ),
							'link'  => get_edit_post_link( $inc_post->ID ),
							'value' => $inc_id,
						];
					}
				} elseif ( preg_match( '/^tax-(\d+)-(single|archive)-(.+)$/', $inc_id, $matches ) ) {
					// Handle taxonomy patterns like 'tax-7-single-portal_group' or 'tax-7-archive-portal_group'.
					$term_id  = intval( $matches[1] );
					$taxonomy = $matches[3];

					// Skip if already processed.
					if ( in_array( $inc_id, $processed_ids, true ) ) {
						continue;
					}

					$term = get_term( $term_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$processed_ids[] = $inc_id;
						$include_posts[] = [
							'id'    => $term_id,
							/* translators: %s term name. */
							'title' => sprintf( __( 'All singulars from %s', 'suremembers-core' ), $term->name ),
							'type'  => $taxonomy,
							'link'  => get_edit_term_link( $term_id, $taxonomy ),
							'value' => $inc_id,
						];
					}
				}
			}

			// Fetch post info for exclude rules.
			$exclude_posts         = [];
			$processed_exclude_ids = []; // Track processed post IDs to avoid duplicates.
			foreach ( $exclude_rules as $exc_id ) {
				if ( preg_match( '/(post(?:child)?)-(\d+)-\|?/', $exc_id, $matches ) ) {
					$prefix  = $matches[1];
					$real_id = intval( $matches[2] );

					// Skip if already processed.
					if ( in_array( $prefix . '-' . $real_id, $processed_exclude_ids, true ) ) {
						continue;
					}

					$exc_post = get_post( $real_id );
					if ( $exc_post ) {
						$processed_exclude_ids[] = $prefix . '-' . $real_id;
						$exclude_posts[]         = [
							'id'    => $exc_post->ID,
							'title' => $prefix === 'postchild' ? sprintf( /* translators: %s title. */ __( 'Child of %s', 'suremembers-core' ), get_the_title( $exc_post ) ) : get_the_title( $exc_post ),
							'type'  => get_post_type( $exc_post ),
							'link'  => get_edit_post_link( $exc_post->ID ),
							'value' => $exc_id,
						];
					}
				} elseif ( preg_match( '/^tax-(\d+)-(single|archive)-(.+)$/', $exc_id, $matches ) ) {
					// Handle taxonomy patterns like 'tax-7-single-portal_group' or 'tax-7-archive-portal_group'.
					$term_id  = intval( $matches[1] );
					$taxonomy = $matches[3];

					// Skip if already processed.
					if ( in_array( $exc_id, $processed_exclude_ids, true ) ) {
						continue;
					}

					$term = get_term( $term_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$processed_exclude_ids[] = $exc_id;
						$exclude_posts[]         = [
							'id'    => $term_id,
							/* translators: %s term name. */
							'title' => sprintf( __( 'All singulars from %s', 'suremembers-core' ), $term->name ),
							'type'  => $taxonomy,
							'link'  => get_edit_term_link( $term_id, $taxonomy ),
							'value' => $exc_id,
						];
					}
				}
			}

			// Get user count (matches admin table logic).
			$required_fetch = get_post_meta( $post_id, SUREMEMBERS_REQUIRES_QUERY, true );
			if ( ! empty( $required_fetch ) ) {
				$users_in_access_group = \SureMembersCore\Inc\Access_Groups::get_users_count( $post_id );
			} else {
				$users_in_access_group = absint( get_post_meta( $post_id, SUREMEMBERS_PLAN_ACTIVE_USERS, true ) );
			}

			$data[] = [
				'id'              => $post_id,
				'title'           => $post->post_title,
				'status'          => get_post_status( $post_id ),
				'priority'        => get_post_meta( $post_id, SUREMEMBERS_PLAN_PRIORITY, true ),
				'expiration'      => get_post_meta( $post_id, SUREMEMBERS_PLAN_EXPIRATION, true ),
				'date'            => get_the_date( 'c', $post ),
				'rules'           => get_post_meta( $post_id, SUREMEMBERS_PLAN_RULES, true ),
				'edit_link'       => \SureMembersCore\Inc\Access_Groups::get_admin_url(
					[
						'membership-id' => $post_id,
					]
				),
				'include'         => $include,
				'exclude'         => $exclude, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Array key for data structure, not WP_Query parameter.
				'include_posts'   => $include_posts,
				'exclude_posts'   => $exclude_posts,
				'user_count'      => $users_in_access_group,
				'restricted_url'  => get_post_meta( $post_id, SUREMEMBERS_RESTRICTED_URL, true ),
				'user_roles'      => get_post_meta( $post_id, SUREMEMBERS_USER_ROLES, true ),
				'drips'           => get_post_meta( $post_id, SUREMEMBERS_PLAN_DRIPS, true ),
				'downloads'       => get_post_meta( $post_id, SUREMEMBERS_ACCESS_GROUP_DOWNLOADS, true ),
				'meta'            => [
					'restrict' => get_post_meta( $post_id, SUREMEMBERS_PLAN_INCLUDE, true ) ? get_post_meta( $post_id, SUREMEMBERS_PLAN_INCLUDE, true ) : [
						'preview_button'      => '',
						'redirect_url'        => '',
						'excerpt'             => false,
						'in_content'          => false,
						'enablelogin'         => false,
						'preview_content'     => '',
						'unauthorized_action' => '',
					],
				],
				'user_filter_url' => add_query_arg(
					[
						'suremembers_access_group_top'    => $post_id,
						'suremembers_access_group_bottom' => $post_id,
					],
					admin_url( 'users.php' )
				),
			];
		}

		wp_reset_postdata();
		return $data;
	}

	/**
	 * Injects Access Groups table data into localized script.
	 *
	 * @param array<string, mixed> $data The existing data passed to the JS.
	 */
	public function inject_table_data( $data ) {
		$data['table_data'] = $this->get_access_groups_table_data();
		return $data;
	}

	/**
	 * Get table data for refreshing the access groups list (returns data instead of echoing JSON).
	 *
	 * @return array{success: bool, data?: array<string, mixed>, message?: string} Result array.
	 *
	 * @since 1.10.14
	 */
	public function get_table_data_result() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return [
				'success' => false,
				'message' => __( 'Insufficient permissions.', 'suremembers-core' ),
			];
		}

		$table_data = $this->get_access_groups_table_data();

		return [
			'success' => true,
			'data'    => [ 'table_data' => $table_data ],
		];
	}

	/**
	 * AJAX handler to get table data for refreshing the access groups list (uses get_table_data_result).
	 *
	 * @since 2.0.0
	 */
	public function get_table_data_ajax() {
		$result = $this->get_table_data_result();

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] ?? [] );
		} else {
			wp_send_json_error( [ 'message' => $result['message'] ?? '' ] );
		}
	}

	/**
	 * Get post data for editing access groups (returns data instead of echoing JSON).
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array{success: bool, data?: array<string, mixed>, message?: string} Result array.
	 *
	 * @since 1.10.14
	 */
	public function get_post_data_result( $post_id ) {
		try {
			if ( ! current_user_can( 'manage_options' ) ) {
				return [
					'success' => false,
					'message' => __( 'Insufficient permissions.', 'suremembers-core' ),
				];
			}

			if ( empty( $post_id ) ) {
				return [
					'success' => false,
					'message' => __( 'Post ID is required.', 'suremembers-core' ),
				];
			}

			$post = get_post( $post_id );
			if ( ! $post || $post->post_type !== SUREMEMBERS_POST_TYPE ) {
				return [
					'success' => false,
					'message' => __( 'Post not found.', 'suremembers-core' ),
				];
			}

			// Get all post meta data.
			$include_data        = get_post_meta( $post->ID, SUREMEMBERS_PLAN_INCLUDE, true );
			$rules_data          = get_post_meta( $post->ID, SUREMEMBERS_PLAN_RULES, true );
			$exclude_data        = get_post_meta( $post->ID, SUREMEMBERS_PLAN_EXCLUDE, true );
			$restricted_url_data = get_post_meta( $post->ID, SUREMEMBERS_RESTRICTED_URL, true );
			$drips_data          = get_post_meta( $post->ID, SUREMEMBERS_PLAN_DRIPS, true );
			$expiration_data     = get_post_meta( $post->ID, SUREMEMBERS_PLAN_EXPIRATION, true );
			$priority_data       = get_post_meta( $post->ID, SUREMEMBERS_PLAN_PRIORITY, true );
			$user_roles_data     = get_post_meta( $post->ID, SUREMEMBERS_USER_ROLES, true );
			$downloads_data      = get_post_meta( $post->ID, SUREMEMBERS_ACCESS_GROUP_DOWNLOADS, true );

			// Format specific locations for SpecificSelectField.
			$specific_locations = [];
			if ( ! empty( $include_data['specifics'] ) && is_array( $include_data['specifics'] ) ) {
				foreach ( $include_data['specifics'] as $specific_id ) {
					$specific_post = get_post( $specific_id );
					if ( $specific_post ) {
						$specific_locations[] = [
							'value' => $specific_id,
							'label' => $specific_post->post_title . ' (' . $specific_post->post_type . ')',
						];
					}
				}
			}

			// Get specifics (post IDs) for include/exclude and remove duplicates.
			$include_specifics = is_array( $include_data ) && ! empty( $include_data['specifics'] ) ? array_unique( $include_data['specifics'] ) : [];
			$exclude_rules     = is_array( $exclude_data ) && ! empty( $exclude_data['rules'] ) ? array_unique( $exclude_data['rules'] ) : [];

			// Fetch post info for include specifics.
			$include_posts = [];
			$processed_ids = []; // Track processed post IDs to avoid duplicates.
			foreach ( $include_specifics as $inc_id ) {
				// Extract numeric post ID from string like 'post-199-|' or 'postchild-199-|'.
				if ( preg_match( '/(post(?:child)?)-(\d+)-\|?/', $inc_id, $matches ) ) {
					$prefix  = $matches[1];
					$real_id = intval( $matches[2] );

					// Skip if already processed.
					if ( in_array( $prefix . '-' . $real_id, $processed_ids, true ) ) {
						continue;
					}

					$inc_post = get_post( $real_id );
					if ( $inc_post ) {
						$processed_ids[] = $prefix . '-' . $real_id;
						$include_posts[] = [
							'id'    => $inc_post->ID,
							'title' => $prefix === 'postchild' ? sprintf( /* translators: %s title. */ __( 'Child of %s', 'suremembers-core' ), get_the_title( $inc_post ) ) : get_the_title( $inc_post ),
							'type'  => get_post_type( $inc_post ),
							'link'  => get_edit_post_link( $inc_post->ID ),
							'value' => $inc_id,
						];
					}
				} elseif ( preg_match( '/^tax-(\d+)-(single|archive)-(.+)$/', $inc_id, $matches ) ) {
					// Handle taxonomy patterns like 'tax-7-single-portal_group' or 'tax-7-archive-portal_group'.
					$term_id  = intval( $matches[1] );
					$taxonomy = $matches[3];

					// Skip if already processed.
					if ( in_array( $inc_id, $processed_ids, true ) ) {
						continue;
					}

					$term = get_term( $term_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$processed_ids[] = $inc_id;
						$include_posts[] = [
							'id'    => $term_id,
							/* translators: %s term name. */
							'title' => sprintf( __( 'All singulars from %s', 'suremembers-core' ), $term->name ),
							'type'  => $taxonomy,
							'link'  => get_edit_term_link( $term_id, $taxonomy ),
							'value' => $inc_id,
						];
					}
				}
			}

			// Fetch post info for exclude rules.
			$exclude_posts         = [];
			$processed_exclude_ids = []; // Track processed post IDs to avoid duplicates.
			foreach ( $exclude_rules as $exc_id ) {
				if ( preg_match( '/(post(?:child)?)-(\d+)-\|?/', $exc_id, $matches ) ) {
					$prefix  = $matches[1];
					$real_id = intval( $matches[2] );

					// Skip if already processed.
					if ( in_array( $prefix . '-' . $real_id, $processed_exclude_ids, true ) ) {
						continue;
					}

					$exc_post = get_post( $real_id );
					if ( $exc_post ) {
						$processed_exclude_ids[] = $prefix . '-' . $real_id;
						$exclude_posts[]         = [
							'id'    => $exc_post->ID,
							'title' => $prefix === 'postchild' ? sprintf( /* translators: %s title. */ __( 'Child of %s', 'suremembers-core' ), get_the_title( $exc_post ) ) : get_the_title( $exc_post ),
							'type'  => get_post_type( $exc_post ),
							'link'  => get_edit_post_link( $exc_post->ID ),
							'value' => $exc_id,
						];
					}
				} elseif ( preg_match( '/^tax-(\d+)-(single|archive)-(.+)$/', $exc_id, $matches ) ) {
					// Handle taxonomy patterns like 'tax-7-single-portal_group' or 'tax-7-archive-portal_group'.
					$term_id  = intval( $matches[1] );
					$taxonomy = $matches[3];

					// Skip if already processed.
					if ( in_array( $exc_id, $processed_exclude_ids, true ) ) {
						continue;
					}

					$term = get_term( $term_id, $taxonomy );
					if ( $term && ! is_wp_error( $term ) ) {
						$processed_exclude_ids[] = $exc_id;
						$exclude_posts[]         = [
							'id'    => $term_id,
							/* translators: %s term name. */
							'title' => sprintf( __( 'All singulars from %s', 'suremembers-core' ), $term->name ),
							'type'  => $taxonomy,
							'link'  => get_edit_term_link( $term_id, $taxonomy ),
							'value' => $exc_id,
						];
					}
				}
			}

			// Process drips to fetch post titles for rules (same as include_posts/exclude_posts).
			$processed_drips = [];
			if ( ! empty( $drips_data ) && is_array( $drips_data ) ) {
				foreach ( $drips_data as $drip ) {
					$drip_rules         = [];
					$processed_drip_ids = [];
					if ( ! empty( $drip['rules'] ) && is_array( $drip['rules'] ) ) {
						foreach ( $drip['rules'] as $rule_id ) {
							if ( preg_match( '/(post(?:child)?)-(\d+)-\|?/', $rule_id, $matches ) ) {
								$prefix  = $matches[1];
								$real_id = intval( $matches[2] );

								// Skip if already processed.
								if ( in_array( $prefix . '-' . $real_id, $processed_drip_ids, true ) ) {
									continue;
								}

								$rule_post = get_post( $real_id );
								if ( $rule_post ) {
									$processed_drip_ids[] = $prefix . '-' . $real_id;
									$drip_rules[]         = [
										'id'    => $rule_post->ID,
										'title' => $prefix === 'postchild' ? sprintf( /* translators: %s title. */ __( 'Child of %s', 'suremembers-core' ), get_the_title( $rule_post ) ) : get_the_title( $rule_post ),
										'type'  => get_post_type( $rule_post ),
										'link'  => get_edit_post_link( $rule_post->ID ),
										'value' => $rule_id,
									];
								}
							} elseif ( preg_match( '/^tax-(\d+)-(single|archive)-(.+)$/', $rule_id, $matches ) ) {
								// Handle taxonomy patterns like 'tax-7-single-portal_group'.
								$term_id  = intval( $matches[1] );
								$taxonomy = $matches[3];

								// Skip if already processed.
								if ( in_array( $rule_id, $processed_drip_ids, true ) ) {
									continue;
								}

								$term = get_term( $term_id, $taxonomy );
								if ( $term && ! is_wp_error( $term ) ) {
									$processed_drip_ids[] = $rule_id;
									$drip_rules[]         = [
										'id'    => $term_id,
										/* translators: %s term name. */
										'title' => sprintf( __( 'All singulars from %s', 'suremembers-core' ), $term->name ),
										'type'  => $taxonomy,
										'link'  => get_edit_term_link( $term_id, $taxonomy ),
										'value' => $rule_id,
									];
								}
							}
						}
					}
					$processed_drips[] = array_merge( $drip, [ 'rules' => $drip_rules ] );
				}
			}

			// Process LearnDash courses data to include post titles.
			$learndash_courses      = [];
			$learndash_courses_data = is_array( $include_data ) && ! empty( $include_data['learndash_courses'] ) ? $include_data['learndash_courses'] : [];
			foreach ( $learndash_courses_data as $ld_course ) {
				// Handle 'post-123-|' format.
				if ( preg_match( '/(post(?:child)?)-(\d+)-\|?/', $ld_course, $matches ) ) {
					$prefix   = $matches[1];
					$real_id  = intval( $matches[2] );
					$post_obj = get_post( $real_id );
					if ( $post_obj ) {
						$learndash_courses[] = [
							'id'    => $post_obj->ID,
							'title' => $prefix === 'postchild' ? sprintf( /* translators: %s title. */ __( 'Child of %s', 'suremembers-core' ), get_the_title( $post_obj ) ) : get_the_title( $post_obj ),
							'type'  => get_post_type( $post_obj ),
							'link'  => get_edit_post_link( $post_obj->ID ),
							'value' => $ld_course,
						];
					}
				} elseif ( strpos( $ld_course, '|all' ) !== false ) {
					// Handle 'sfwd-courses|all' format.
					$post_type = str_replace( '|all', '', $ld_course );
					if ( post_type_exists( $post_type ) ) {
						$post_type_obj       = get_post_type_object( $post_type );
						$learndash_courses[] = [
							'value' => $ld_course,
							/* translators: %s post type label. */
							'label' => sprintf( __( 'All %s', 'suremembers-core' ), $post_type_obj->labels->name ?? $post_type ),
						];
					}
				}
			}

			// Process LearnDash groups data to include post titles.
			$learndash_groups      = [];
			$learndash_groups_data = is_array( $include_data ) && ! empty( $include_data['learndash_groups'] ) ? $include_data['learndash_groups'] : [];
			foreach ( $learndash_groups_data as $ld_group ) {
				// Handle 'post-123-|' format.
				if ( preg_match( '/(post(?:child)?)-(\d+)-\|?/', $ld_group, $matches ) ) {
					$prefix   = $matches[1];
					$real_id  = intval( $matches[2] );
					$post_obj = get_post( $real_id );
					if ( $post_obj ) {
						$learndash_groups[] = [
							'id'    => $post_obj->ID,
							'title' => $prefix === 'postchild' ? sprintf( /* translators: %s title. */ __( 'Child of %s', 'suremembers-core' ), get_the_title( $post_obj ) ) : get_the_title( $post_obj ),
							'type'  => get_post_type( $post_obj ),
							'link'  => get_edit_post_link( $post_obj->ID ),
							'value' => $ld_group,
						];
					}
				} elseif ( strpos( $ld_group, '|all' ) !== false ) {
					// Handle 'groups|all' format.
					$post_type = str_replace( '|all', '', $ld_group );
					if ( post_type_exists( $post_type ) ) {
						$post_type_obj      = get_post_type_object( $post_type );
						$learndash_groups[] = [
							'value' => $ld_group,
							/* translators: %s post type label. */
							'label' => sprintf( __( 'All %s', 'suremembers-core' ), $post_type_obj->labels->name ?? $post_type ),
						];
					}
				}
			}

			// Process TutorLMS courses data to include post titles.
			$tutorlms_courses      = [];
			$tutorlms_courses_data = is_array( $include_data ) && ! empty( $include_data['tutorlms_courses'] ) ? $include_data['tutorlms_courses'] : [];
			foreach ( $tutorlms_courses_data as $tutor_course ) {
				// Handle 'post-123-|' format.
				if ( preg_match( '/(post(?:child)?)-(\d+)-\|?/', $tutor_course, $matches ) ) {
					$prefix   = $matches[1];
					$real_id  = intval( $matches[2] );
					$post_obj = get_post( $real_id );
					if ( $post_obj ) {
						$tutorlms_courses[] = [
							'id'    => $post_obj->ID,
							'title' => $prefix === 'postchild' ? sprintf( /* translators: %s title. */ __( 'Child of %s', 'suremembers-core' ), get_the_title( $post_obj ) ) : get_the_title( $post_obj ),
							'type'  => get_post_type( $post_obj ),
							'link'  => get_edit_post_link( $post_obj->ID ),
							'value' => $tutor_course,
						];
					}
				} elseif ( strpos( $tutor_course, '|all' ) !== false ) {
					// Handle 'courses|all' format.
					$post_type = str_replace( '|all', '', $tutor_course );
					if ( post_type_exists( $post_type ) ) {
						$post_type_obj      = get_post_type_object( $post_type );
						$tutorlms_courses[] = [
							'value' => $tutor_course,
							/* translators: %s post type label. */
							'label' => sprintf( __( 'All %s', 'suremembers-core' ), $post_type_obj->labels->name ?? $post_type ),
						];
					}
				}
			}

			// Process TutorLMS lessons data to include post titles.
			$tutorlms_lessons      = [];
			$tutorlms_lessons_data = is_array( $include_data ) && ! empty( $include_data['tutorlms_lessons'] ) ? $include_data['tutorlms_lessons'] : [];
			foreach ( $tutorlms_lessons_data as $tutor_lesson ) {
				// Handle 'post-123-|' format.
				if ( preg_match( '/(post(?:child)?)-(\d+)-\|?/', $tutor_lesson, $matches ) ) {
					$prefix   = $matches[1];
					$real_id  = intval( $matches[2] );
					$post_obj = get_post( $real_id );
					if ( $post_obj ) {
						$tutorlms_lessons[] = [
							'id'    => $post_obj->ID,
							'title' => $prefix === 'postchild' ? sprintf( /* translators: %s title. */ __( 'Child of %s', 'suremembers-core' ), get_the_title( $post_obj ) ) : get_the_title( $post_obj ),
							'type'  => get_post_type( $post_obj ),
							'link'  => get_edit_post_link( $post_obj->ID ),
							'value' => $tutor_lesson,
						];
					}
				} elseif ( strpos( $tutor_lesson, '|all' ) !== false ) {
					// Handle 'lesson|all' format.
					$post_type = str_replace( '|all', '', $tutor_lesson );
					if ( post_type_exists( $post_type ) ) {
						$post_type_obj      = get_post_type_object( $post_type );
						$tutorlms_lessons[] = [
							'value' => $tutor_lesson,
							/* translators: %s post type label. */
							'label' => sprintf( __( 'All %s', 'suremembers-core' ), $post_type_obj->labels->name ?? $post_type ),
						];
					}
				}
			}

			// Get user count (matches admin table logic).
			$required_fetch = get_post_meta( $post_id, SUREMEMBERS_REQUIRES_QUERY, true );
			if ( ! empty( $required_fetch ) ) {
				$users_in_access_group = \SureMembers\Inc\Access_Groups::get_users_count( $post_id );
			} else {
				$users_in_access_group = absint( get_post_meta( $post_id, SUREMEMBERS_PLAN_ACTIVE_USERS, true ) );
			}

			$post_data = [
				'id'                 => $post->ID,
				'title'              => $post->post_title,
				'status'             => $post->post_status,
				'priority'           => $priority_data,
				'expiration'         => $expiration_data,
				'date'               => get_the_date( 'c', $post ),
				'rules'              => $rules_data,
				'edit_link'          => \SureMembersCore\Inc\Access_Groups::get_admin_url(
					[
						'membership-id' => $post_id,
					]
				),
				'include'            => $include_data,
				'exclude'            => $exclude_data, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Array key for data structure, not WP_Query parameter.
				'include_posts'      => $include_posts,
				'exclude_posts'      => $exclude_posts,
				'learndash_courses'  => $learndash_courses,
				'learndash_groups'   => $learndash_groups,
				'tutorlms_courses'   => $tutorlms_courses,
				'tutorlms_lessons'   => $tutorlms_lessons,
				'user_count'         => $users_in_access_group,
				'restricted_url'     => $restricted_url_data,
				'user_roles'         => $user_roles_data,
				'drips'              => $processed_drips,
				'downloads'          => $downloads_data,
				'meta'               => [
					'restrict' => $rules_data,
				],
				'user_filter_url'    => add_query_arg(
					[
						'suremembers_access_group_top'    => $post_id,
						'suremembers_access_group_bottom' => $post_id,
					],
					admin_url( 'users.php' )
				),
				'specific_locations' => $specific_locations,
			];

			/**
			 * Filter membership post data before returning to the frontend.
			 *
			 * Third-party plugins can use this filter to add their saved data
			 * when a membership is loaded for editing.
			 *
			 * @since 1.0.0
			 *
			 * @param array<string, mixed> $post_data Membership data being returned.
			 * @param int                  $post_id   Membership post ID.
			 */
			$post_data = apply_filters( 'suremembers_get_membership_data', $post_data, $post_id );

			return [
				'success' => true,
				'data'    => $post_data,
			];
		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * AJAX handler to get post data for editing access groups (uses get_post_data_result).
	 *
	 * @since 2.0.0
	 */
	public function get_post_data_ajax() {
		$post_id = isset( $_POST['post_id'] ) ? absint( sanitize_text_field( $_POST['post_id'] ) ) : 0; //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$result  = $this->get_post_data_result( $post_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] ?? [] );
		} else {
			wp_send_json_error( [ 'message' => $result['message'] ?? '' ] );
		}
	}

	/**
	 * Update access group status (returns data instead of echoing JSON).
	 *
	 * @param int    $id Access group post ID.
	 * @param string $status New status ('publish', 'archive', or 'delete').
	 *
	 * @return array{success: bool, data?: array<string, mixed>, message?: string} Result array.
	 *
	 * @since 1.10.14
	 */
	public function update_access_group_status_data( $id, $status ) {
		if ( empty( $id ) ) {
			return [
				'success' => false,
				'message' => __( 'Invalid post id.', 'suremembers-core' ),
			];
		}

		if ( empty( $status ) ) {
			return [
				'success' => false,
				'message' => __( 'Invalid data.', 'suremembers-core' ),
			];
		}

		$message  = '';
		$response = false;

		switch ( $status ) {
			case 'publish':
				wp_publish_post( $id );
				$response = true;
				$message  = __( 'Published successfully', 'suremembers-core' );
				break;
			case 'archive':
				$response = wp_update_post(
					[
						'ID'          => $id,
						'post_status' => SUREMEMBERS_ARCHIVE,
					]
				);
				$status   = 'suremembers_archive';
				if ( ! is_int( $response ) ) {
					$message = __( 'Updating failed', 'suremembers-core' );
				} else {
					$message  = __( 'Archived successfully', 'suremembers-core' );
					$response = true;
				}
				break;
			case 'delete':
				$response = wp_delete_post( $id );
				if ( $response ) {
					$message = __( 'Deleted successfully', 'suremembers-core' );
				} else {
					$message = __( 'Delete operation failed', 'suremembers-core' );
				}
				break;
			default:
				$response = false;
				$message  = __( 'Invalid status.', 'suremembers-core' );
				break;
		}

		if ( $response ) {
			return [
				'success' => true,
				'data'    => [
					'message' => $message,
					'action'  => $status,
				],
			];
		}

		return [
			'success' => false,
			'message' => __( 'Status updating failed', 'suremembers-core' ),
			'action'  => $status,
		];
	}

	/**
	 * Updates status of access group (AJAX handler - uses update_access_group_status_data).
	 *
	 * @since 1.0.0
	 */
	public function update_access_group_status() {
		if ( ! isset( $_POST['id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error( [ 'message' => __( 'Missing Post ID.', 'suremembers-core' ) ] );
		}

		if ( ! isset( $_POST['status'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error( [ 'message' => __( 'Missing Post status data.', 'suremembers-core' ) ] );
		}

		$id     = intval( sanitize_text_field( $_POST['id'] ) ); //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$status = sanitize_text_field( $_POST['status'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing

		$result = $this->update_access_group_status_data( $id, $status );

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] ?? [] );
		} else {
			wp_send_json_error(
				[
					'message' => $result['message'] ?? '',
					'action'  => $result['action'] ?? $status,
				]
			);
		}
	}

	/**
	 * Fetches all the posts belonging to selected post time in ( Rules Engine)
	 *
	 * @since 1.0.0
	 */
	public function fetch_posts() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Current user does not have required permission.', 'suremembers-core' ) ] );
		}

		if ( empty( $_POST['postType'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error( [ 'message' => __( 'Invalid data.', 'suremembers-core' ) ] );
		}

		$post_type = sanitize_text_field( $_POST['postType'] ); //phpcs:ignore WordPress.Security.NonceVerification.Missing

		$args = [
			'post_type'   => $post_type,
			'post_status' => 'publish',
		];

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			wp_send_json_error( [ 'message' => __( 'No post available for this post type.', 'suremembers-core' ) ] );
		}

		$response = [];
		foreach ( $posts as $post ) {
			$temp          = [];
			$temp['value'] = $post->ID;
			$temp['label'] = $post->post_title;
			$response[]    = $temp;
		}

		wp_send_json_success( [ 'posts' => $response ] );
	}

	/**
	 * Save access group form data (returns data instead of echoing JSON).
	 *
	 * @param array<string, mixed> $suremembers_post Form data array.
	 * @param int                  $suremembers_post_id Post ID (0 for new post).
	 * @param string               $mode Mode type ('admin' or 'iframe').
	 *
	 * @return array{success: bool, data?: array<string, mixed>, message?: string} Result array.
	 *
	 * @since 1.10.14
	 */
	public function submit_form_data( $suremembers_post, $suremembers_post_id = 0, $mode = 'admin' ) {
		if ( empty( $suremembers_post ) ) {
			return [
				'success' => false,
				'message' => __( 'No data received.', 'suremembers-core' ),
			];
		}

		// Preserve HTML markup for preview content.
		$restrict_preview_content = isset( $suremembers_post['restrict']['preview_content'] ) ? wp_filter_post_kses( $suremembers_post['restrict']['preview_content'] ) : '';

		// Preserve HTML markup for restricted URL text.
		$redirect_url_text = isset( $suremembers_post['restricted_url'] ) ? wp_filter_post_kses( $suremembers_post['restricted_url'] ) : '';

		// Decode and sanitize redirect URL if present.
		if ( isset( $suremembers_post['restrict']['redirect_url'] ) ) {
			$suremembers_post['restrict']['redirect_url'] = esc_url_raw( urldecode( $suremembers_post['restrict']['redirect_url'] ) );
		}

		// Save switch values BEFORE remove_blank_array (which removes empty strings and '0' values).
		$excerpt_to_save     = isset( $suremembers_post['restrict']['excerpt'] ) ? sanitize_text_field( $suremembers_post['restrict']['excerpt'] ) : '0';
		$in_content_to_save  = isset( $suremembers_post['restrict']['in_content'] ) ? sanitize_text_field( $suremembers_post['restrict']['in_content'] ) : '0';
		$enablelogin_to_save = isset( $suremembers_post['restrict']['enablelogin'] ) ? sanitize_text_field( $suremembers_post['restrict']['enablelogin'] ) : '0';

		// Sanitize all data recursively.
		$post_data = Utils::sanitize_recursively( 'sanitize_text_field', $suremembers_post );

		// Save download_files BEFORE remove_blank_array (which removes empty strings).
		$download_files_to_save = $post_data['download_files'] ?? '';

		$post_data = Utils::remove_blank_array( $post_data );

		if ( empty( $post_data['title'] ) ) {
			return [
				'success' => false,
				'message' => __( 'Title can\'t be empty.', 'suremembers-core' ),
			];
		}

		if ( empty( $suremembers_post_id ) ) {
			// Check if a membership with the same title exists and append " copy N" if so.
			$original_title = $post_data['title'];
			$final_title    = $original_title;
			$copy_number    = 0;

			// Keep checking until we find a unique title.
			while ( $this->membership_title_exists( $final_title ) ) {
				++$copy_number;
				$final_title = $original_title . ' copy ' . $copy_number;
			}

			$new_post = [
				'post_title'   => $final_title,
				'post_content' => '',
				'post_status'  => isset( $post_data['status'] ) && $post_data['status'] === 'suremembers_archive' ? 'suremembers_archive' : 'publish',
				'post_type'    => SUREMEMBERS_POST_TYPE,
			];

			// Insert the post into the database.
			$suremembers_post_id = wp_insert_post( $new_post );
		} else {
			// Check if another membership with the same title exists and append " copy N" if so.
			$original_title = $post_data['title'];
			$final_title    = $original_title;
			$copy_number    = 0;

			while ( $this->membership_title_exists( $final_title, (int) $suremembers_post_id ) ) {
				++$copy_number;
				$final_title = $original_title . ' copy ' . $copy_number;
			}

			$post_array          = [
				'post_title' => $final_title,
				'ID'         => $suremembers_post_id,
			];
			$suremembers_post_id = wp_update_post( $post_array );
		}

		if ( is_wp_error( $suremembers_post_id ) ) {
			return [
				'success' => false,
				'message' => $suremembers_post_id->get_error_message(),
			];
		}

		if ( ! is_int( $suremembers_post_id ) ) {
			return [
				'success' => false,
				'message' => __( 'Invalid post ID returned.', 'suremembers-core' ),
			];
		}

		unset( $post_data['title'] );

		$include = [];
		if ( ! empty( $post_data['rules'] ) ) {
			$include['rules'] = $post_data['rules'];
			unset( $post_data['rules'] );
		}

		if ( ! empty( $post_data['specifics'] ) ) {
			$include['specifics'] = $post_data['specifics'];
			unset( $post_data['specifics'] );
		}

		$include = apply_filters( 'suremembers_access_group_edit_metadata', $include, $post_data );

		update_post_meta( $suremembers_post_id, SUREMEMBERS_PLAN_INCLUDE, $include );

		// Add restriction url.
		$save_restrict_url = [];
		if ( ! empty( $post_data['restricted_url'] ) ) {
			$redirect_url_text                   = str_replace( ',', "\n", $redirect_url_text );
			$save_restrict_url['restricted_url'] = $redirect_url_text;
			if ( ! empty( $post_data['restricted_url_reg_exp'] ) ) {
				$save_restrict_url['regex'] = sanitize_text_field( $post_data['restricted_url_reg_exp'] );
			}
			update_post_meta( $suremembers_post_id, SUREMEMBERS_RESTRICTED_URL, $save_restrict_url );
		} else {
			update_post_meta( $suremembers_post_id, SUREMEMBERS_RESTRICTED_URL, '' );
		}

		$exclude = [];
		if ( ! empty( $post_data['exclude'] ) ) {
			$exclude['rules'] = $post_data['exclude'];
			unset( $post_data['exclude'] );
		}
		update_post_meta( $suremembers_post_id, SUREMEMBERS_PLAN_EXCLUDE, $exclude );

		$priority = ! empty( $post_data['priority'] ) ? $post_data['priority'] : '';
		unset( $post_data['priority'] );
		update_post_meta( $suremembers_post_id, SUREMEMBERS_PLAN_PRIORITY, $priority );

		// Update expiration.
		$expiration = ! empty( $post_data['expiration'] ) ? Utils::sanitize_recursively( 'sanitize_text_field', $post_data['expiration'] ) : '';
		unset( $post_data['expiration'] );
		update_post_meta( $suremembers_post_id, SUREMEMBERS_PLAN_EXPIRATION, $expiration );

		$drips = ! empty( $post_data['drips'] ) ? Utils::sanitize_recursively( 'sanitize_text_field', $post_data['drips'] ) : '';
		unset( $post_data['drips'] );
		update_post_meta( $suremembers_post_id, SUREMEMBERS_PLAN_DRIPS, $drips );

		// Save user roles.
		$roles = ! empty( $post_data['suremembers_user_roles'] ) ? $post_data['suremembers_user_roles'] : '';
		unset( $post_data['suremembers_user_roles'] );
		update_post_meta( $suremembers_post_id, SUREMEMBERS_USER_ROLES, $roles );

		// save download files - use the pre-saved value (before remove_blank_array).
		$downloads = ! empty( $download_files_to_save ) ? $download_files_to_save : '';

		unset( $post_data['download_files'] );
		update_post_meta( $suremembers_post_id, SUREMEMBERS_ACCESS_GROUP_DOWNLOADS, $downloads );

		// Add back the fields that were saved before remove_blank_array.
		$post_data['restrict']['preview_content'] = $restrict_preview_content;
		$post_data['restrict']['excerpt']         = $excerpt_to_save;
		$post_data['restrict']['in_content']      = $in_content_to_save;
		$post_data['restrict']['enablelogin']     = $enablelogin_to_save;

		update_post_meta( $suremembers_post_id, SUREMEMBERS_PLAN_RULES, $post_data );

		/**
		 * Fires after membership form is submitted and core data is saved.
		 *
		 * Third-party plugins can use this action to save their custom field data.
		 * The raw POST data is passed as the second parameter for plugins to extract their data.
		 *
		 * @since 1.0.0
		 * @since 1.0.0 Added $suremembers_post parameter with raw POST data.
		 *
		 * @param int                  $suremembers_post_id Membership post ID.
		 * @param array<string, mixed> $suremembers_post    Raw POST data from the form submission.
		 */
		do_action( 'suremembers_after_submit_form', $suremembers_post_id, $suremembers_post );

		return [
			'success' => true,
			'data'    => [
				'message' => __( 'Rule saved successfully.', 'suremembers-core' ),
				'id'      => $suremembers_post_id,
				'mode'    => $mode,
			],
		];
	}

	/**
	 * Saves Sure Members rules and updates meta accordingly (AJAX handler - uses submit_form_data).
	 *
	 * @since 1.0.0
	 */
	public function submit_form() {
		if ( empty( $_POST['suremembers_post'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error( [ 'message' => __( 'No data received.', 'suremembers-core' ) ] );
		}

		// Get parameters from $_POST.
		$suremembers_post    = $_POST['suremembers_post']; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Missing
		$suremembers_post_id = isset( $_POST['suremembers_post_id'] ) ? absint( sanitize_text_field( $_POST['suremembers_post_id'] ) ) : 0; //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$mode                = isset( $_POST['mode'] ) && $_POST['mode'] === 'iframe' ? 'iframe' : 'admin'; //phpcs:ignore WordPress.Security.NonceVerification.Missing

		// Call the refactored method that returns data instead of echoing JSON.
		$result = $this->submit_form_data( $suremembers_post, $suremembers_post_id, $mode );

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] ?? [] );
		} else {
			wp_send_json_error( [ 'message' => $result['message'] ?? '' ] );
		}
	}

	/**
	 * Get location selection options.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function get_location_selections() {
		$post_types = Restricted::get_post_types( 'object' );

		$selection_options = [
			'basic' => [
				'label' => __( 'Basic', 'suremembers-core' ),
				'value' => [
					'basic-global' => __( 'Entire Website', 'suremembers-core' ),
				],
			],
		];

		$args = [
			'public' => true,
		];

		$taxonomies = get_taxonomies( $args, 'objects' );
		unset( $taxonomies['post_format'] );

		if ( ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$attached_post_types = $this->get_post_types_by_taxonomy( $taxonomy->name );

				foreach ( $post_types as $post_type ) {
					$post_opt = $this->get_post_target_rule_options( $post_type, $taxonomy, $attached_post_types );

					if ( isset( $selection_options[ $post_opt['post_key'] ] ) ) {
						if ( ! empty( $post_opt['value'] ) && is_array( $post_opt['value'] ) ) {
							foreach ( $post_opt['value'] as $key => $value ) {
								if ( ! in_array( $value, $selection_options[ $post_opt['post_key'] ]['value'], true ) ) {
									$selection_options[ $post_opt['post_key'] ]['value'][ $key ] = $value;
								}
							}
						}
					} else {
						$selection_options[ $post_opt['post_key'] ] = [
							'label' => $post_opt['label'],
							'value' => $post_opt['value'],
						];
					}
				}
			}
		}

		$selection_options['specific-target'] = [
			'label' => __( 'Specific Target', 'suremembers-core' ),
			'value' => [
				'specifics' => __( 'Specific Pages / Posts / Taxonomies, etc.', 'suremembers-core' ),
			],
		];

		// Restrict by specific Url.
		$selection_options['specific-url'] = [
			'label' => __( 'Specific Url', 'suremembers-core' ),
			'value' => [
				'restricted_url' => __( 'URL matching', 'suremembers-core' ),
			],
		];

		return apply_filters( 'suremembers_location_selection_options', $selection_options );
	}

	/**
	 * Get post type by taxonomy
	 *
	 * @param string $taxonomy taxonomy slug.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function get_post_types_by_taxonomy( $taxonomy = '' ) {
		global $wp_taxonomies;
		if ( isset( $wp_taxonomies[ $taxonomy ] ) ) {
			return $wp_taxonomies[ $taxonomy ]->object_type;
		}
		return [];
	}

	/**
	 * Fetches posts related options for select array
	 *
	 * @param object               $post_type post object.
	 * @param object               $taxonomy taxonomy object.
	 * @param array<string, mixed> $attached_post_types posts attached to current taxonomy.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function get_post_target_rule_options( $post_type, $taxonomy, $attached_post_types ) {
		$label       = $post_type->label ?? '';
		$post_key    = str_replace( ' ', '-', strtolower( $label ) );
		$post_label  = ucwords( $label );
		$post_name   = $post_type->name ?? '';
		$post_option = [];

		/* translators: %s post label */
		$all_posts                          = sprintf( __( 'All %s', 'suremembers-core' ), $post_label );
		$post_option[ $post_name . '|all' ] = $all_posts;

		if ( in_array( $post_name, $attached_post_types, true ) ) {
			$tax_label = ! empty( $taxonomy->label ) ? ucwords( $taxonomy->label ) : '';
			$tax_name  = ! empty( $taxonomy->name ) ? $taxonomy->name : '';

			/* translators: %s taxonomy label */
			$tax_archive = sprintf( __( 'All %s Archive', 'suremembers-core' ), $tax_label );

			$post_option[ $post_name . '|all|taxarchive|' . $tax_name ] = $tax_archive;
		}

		return [
			'post_key' => $post_key,
			'label'    => $post_label,
			'value'    => $post_option,
		];
	}

	/**
	 * Get posts by search query (returns data instead of echoing JSON).
	 *
	 * @param string $search_string Search query string.
	 * @param string $include Comma-separated list of post types/taxonomies to include.
	 * @param string $context Context for the search ('search' by default).
	 *
	 * @return array{success: bool, data?: array<string, mixed>, message?: string} Result array.
	 *
	 * @since 1.10.14
	 */
	public function get_posts_by_query_data( $search_string = '', $include = '', $context = 'search' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return [
				'success' => false,
				'message' => __( 'Current user does not have required permission.', 'suremembers-core' ),
			];
		}

		$data               = [];
		$result             = [];
		$includes_array     = explode( ',', $include );
		$post_types         = [];
		$include_taxonomies = [];
		if ( ! empty( $includes_array ) ) {
			foreach ( $includes_array as $rules ) {
				/**
				 * Added filter for learndash legacy value of `learndash-courses` and `learndash-groups`.
				 * Using this filter is discouraged, it is used for backward compatibilty and will be removed in future
				 *
				 * @since 1.4.0
				 */
				$rules  = apply_filters( 'suremembers_before_search_rules', $rules );
				$option = explode( '|', $rules );
				if ( count( $option ) > 1 ) {
					$temp                     = get_post_type_object( $option[0] );
					$post_types[ $option[0] ] = [ 'label' => $temp->label ?? '' ];
				}
				if ( count( $option ) === 4 ) {
					$post_types[ $option[0] ]['taxonomy'][] = $option[3];
					$include_taxonomies[]                   = $option[3];
				}
			}
		}

		if ( empty( $post_types ) ) {
			$post_types = Restricted::get_post_types( 'object', $context );
		}

		foreach ( $post_types as $key => $post_type ) {
			$data       = [];
			$child_data = [];

			add_filter( 'posts_search', [ $this, 'search_only_titles' ], 10, 2 );

			$query = new \WP_Query(
				[
					's'              => $search_string,
					'post_type'      => $key,
					'posts_per_page' => -1,
				]
			);

			if ( $query->have_posts() ) {
				// Check post is hierarchical or not.
				$check_hierarchical = is_post_type_hierarchical( $key );
				while ( $query->have_posts() ) {
					$query->the_post();
					$title  = get_the_title();
					$id     = get_the_id();
					$data[] = [
						'value' => 'post-' . $id . '-|',
						'label' => $title,
					];

					if ( $check_hierarchical ) {
						/* translators: %s title. */
						$children_title = sprintf( __( 'Child of %s', 'suremembers-core' ), $title );
						$child_data[]   = [
							'value' => 'postchild-' . $id . '-|',
							'label' => $children_title,
						];
					}
				}
			}

			$post_type = (array) $post_type;

			if ( ! empty( $data ) ) {
				$result[] = [
					'label'   => $post_type['label'],
					'options' => $data,
				];
			}
			if ( ! empty( $child_data ) ) {
				$result[] = [
					/* translators: %s label. */
					'label'   => sprintf( __( 'Child of %s', 'suremembers-core' ), $post_type['label'] ),
					'options' => $child_data,
				];
			}
		}

		$data = [];

		wp_reset_postdata();

		$args = [
			'public' => true,
		];

		$output     = 'objects'; // names or objects, note names is the default.
		$operator   = 'and';
		$taxonomies = get_taxonomies( $args, $output, $operator );

		foreach ( $taxonomies as $tax => $taxonomy ) {
			if ( ! empty( $include_taxonomies ) && ! in_array( $tax, $include_taxonomies, true ) ) {
				continue;
			}

			if ( empty( $include_taxonomies ) ) {
				$attached_post_types = $this->get_post_types_by_taxonomy( $taxonomy->name );

				if ( empty( array_intersect( $attached_post_types, array_keys( $post_types ) ) ) ) {
					continue;
				}
			}

			$terms = get_terms(
				[
					'taxonomy'   => $taxonomy->name,
					'orderby'    => 'count',
					'hide_empty' => 0,
					'name__like' => $search_string,
				]
			);

			$data = [];

			$label = ucwords( $taxonomy->label );

			if ( ! empty( $terms ) && is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$data[] = [
						'value' => 'tax-' . $term->term_id . '-single-' . $taxonomy->name,
						'label' => 'All singulars from ' . $term->name,
					];
				}
			}

			if ( is_array( $data ) && ! empty( $data ) ) {
				$result[] = [
					'label'   => $label,
					'options' => $data,
				];
			}
		}
		// Return the result as array.
		return [
			'success' => true,
			'data'    => $result,
		];
	}

	/**
	 * Returns content as per search string (AJAX handler - uses get_posts_by_query_data).
	 *
	 * @since 1.0.0
	 */
	public function get_posts_by_query() {
		$search_string = isset( $_POST['q'] ) ? sanitize_text_field( $_POST['q'] ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$include       = ! empty( $_POST['include'] ) ? sanitize_text_field( $_POST['include'] ) : ''; //phpcs:ignore WordPress.Security.NonceVerification.Missing
		$context       = ! empty( $_POST['context'] ) ? sanitize_text_field( $_POST['context'] ) : 'search'; //phpcs:ignore WordPress.Security.NonceVerification.Missing

		$result = $this->get_posts_by_query_data( $search_string, $include, $context );

		if ( $result['success'] ) {
			wp_send_json_success( $result['data'] ?? [] );
		} else {
			wp_send_json_error( [ 'message' => $result['message'] ?? '' ] );
		}
	}

	/**
	 * Modifies search query to search only title
	 *
	 * @param string $search search string.
	 * @param object $wp_query WP_QUERY object.
	 *
	 * @since 1.0.0
	 */
	public function search_only_titles( $search, $wp_query ) {
		if ( ! empty( $search ) && ! empty( $wp_query->query_vars['search_terms'] ) ) {
			global $wpdb;

			$q = $wp_query->query_vars;
			$n = ! empty( $q['exact'] ) ? '' : '%';

			$search = [];

			foreach ( $q['search_terms'] as $term ) {
				$search[] = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $n . $wpdb->esc_like( $term ) . $n );
			}

			if ( ! is_user_logged_in() ) {
				$search[] = "{$wpdb->posts}.post_password = ''";
			}

			$search = ' AND ' . implode( ' AND ', $search );
		}

		return $search;
	}

	/**
	 * Get user selected values
	 *
	 * @param mixed $post_id post id to retrieve data from.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function get_selected_locations( $post_id = false ) {
		if ( empty( $post_id ) ) {
			// Ignored nonce verification as we are getting post_id from URL.
			if ( empty( $_GET['post_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return [];
			}
			// Ignored nonce verification as we are getting post_id from URL.
			$post_id = absint( $_GET['post_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$id = absint( $post_id );

		$includes = get_post_meta( $id, SUREMEMBERS_PLAN_INCLUDE, true );
		$rules    = is_array( $includes ) && ! empty( $includes['rules'] ) ? Utils::sanitize_recursively( 'sanitize_text_field', $includes['rules'] ) : [];

		/**
		 * Added filter for learndash legacy value of `learndash-courses` and `learndash-groups`.
		 * Using this filter is discouraged, it is used for backward compatibilty and will be removed in future
		 *
		 * @since 1.4.0
		 */
		$rules = apply_filters( 'suremembers_before_search_rules', $rules );
		return $rules;
	}

	/**
	 * Returns data of specific location i.e. specific, excluded.
	 *
	 * @param string $type type of data to be retrieved 'specific | excluded'.
	 * @param mixed  $post_id post id to retrieve data for.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function get_individual_data( $type = 'exclude', $post_id = false ) {
		if ( empty( $post_id ) ) {
			// Ignored nonce verification as we are getting post_id from URL.
			if ( empty( $_GET['post_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return [];
			}

			// Ignored nonce verification as we are getting post_id from URL.
			$post_id = absint( $_GET['post_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$id = absint( $post_id );

		switch ( $type ) {
			case 'specific':
				$includes = get_post_meta( $id, SUREMEMBERS_PLAN_INCLUDE, true );
				$data     = is_array( $includes ) && ! empty( $includes['specifics'] ) ? Utils::sanitize_recursively( 'sanitize_text_field', $includes['specifics'] ) : [];
				break;

			default:
				$exclude = get_post_meta( $id, SUREMEMBERS_PLAN_EXCLUDE, true );
				$data    = is_array( $exclude ) && ! empty( $exclude['rules'] ) ? Utils::sanitize_recursively( 'sanitize_text_field', $exclude['rules'] ) : [];
				break;
		}

		if ( empty( $data ) ) {
			return $data;
		}
		return Utils::convert_slug_to_text( $data );
	}

	/**
	 * Check for post status change
	 *
	 * @since 1.0.0
	 */
	public function check_status_transition() {
		if ( ! empty( $_GET['post'] ) && ! empty( $_GET['action'] ) && in_array( sanitize_text_field( $_GET['action'] ), [ SUREMEMBERS_ARCHIVE, SUREMEMBERS_ARCHIVE . '_revert' ], true ) && ! empty( $_GET['_wpnonce'] ) ) {
			$post_id = absint( $_GET['post'] );
			$action  = sanitize_text_field( $_GET['action'] );

			if ( ! wp_verify_nonce( sanitize_text_field( $_GET['_wpnonce'] ), sanitize_text_field( $action ) ) ) {
				return;
			}

			if ( SUREMEMBERS_ARCHIVE . '_revert' === $action ) {
				$status = 'publish';
			} else {
				$status = SUREMEMBERS_ARCHIVE;
			}

			$response = wp_update_post(
				[
					'ID'          => $post_id,
					'post_status' => $status,
				]
			);

			$query_args = [ 'post_type' => SUREMEMBERS_POST_TYPE ];

			if ( isset( $_GET['suremembers_view'] ) && $_GET['suremembers_view'] === 'iframe' ) {
				$query_args['suremembers_view'] = 'iframe';
			}

			if ( $response ) {
				wp_safe_redirect( add_query_arg( $query_args, admin_url( 'edit.php' ) ) );
				exit;
			}
		}
	}

	/**
	 * Url restriction data.
	 *
	 * @param int $post_id Current post id.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.1.0
	 */
	public function get_restricted_url_data( $post_id = 0 ) {
		if ( empty( $post_id ) ) {
			// Ignored nonce verification as we are getting post_id from URL.
			if ( empty( $_GET['post_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return [];
			}

			// Ignored nonce verification as we are getting post_id from URL.
			$post_id = absint( $_GET['post_id'] ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$get_restricted_url = get_post_meta( $post_id, SUREMEMBERS_RESTRICTED_URL, true );
		$result             = [];
		if ( is_array( $get_restricted_url ) && ! empty( $get_restricted_url['restricted_url'] ) ) {
			$result['restricted_url'] = wp_kses_post( $get_restricted_url['restricted_url'] );
			if ( ! empty( $get_restricted_url['regex'] ) ) {
				$result['restricted_url_reg_exp'] = true;
			}
		}
		return $result;
	}

	/**
	 * Check if a membership with the given title already exists.
	 *
	 * @param string $title      Title to check.
	 * @param int    $exclude_id Post ID to exclude from the check.
	 *
	 * @return bool True if a membership with this title exists.
	 *
	 * @since 1.0.0
	 */
	private function membership_title_exists( $title, $exclude_id = 0 ) {
		$existing = get_posts(
			[
				'post_type'      => SUREMEMBERS_POST_TYPE,
				'title'          => $title,
				'post_status'    => [ 'publish', 'suremembers_archive' ],
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);

		if ( empty( $existing ) ) {
			return false;
		}

		if ( $exclude_id && (int) $existing[0] === $exclude_id ) {
			return false;
		}

		return true;
	}

	/**
	 * Return redirection rules
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.3.0
	 */
	private function get_redirection_rules() {
		return \SureMembersCore\Inc\Settings::get_setting( SUREMEMBERS_REDIRECT_RULES );
	}

	/**
	 * Return selected access group in format required for react select.
	 *
	 * @since 1.4.0
	 */
	private function get_selected_registration_access_group() {
		$settings = \SureMembersCore\Inc\Settings::get_setting( SUREMEMBERS_ADMIN_SETTINGS );

		$access_group_ids = isset( $settings['registration_access_group'] ) ? Utils::sanitize_recursively( 'absint', $settings['registration_access_group'] ) : [];
		if ( empty( $access_group_ids ) ) {
			return [];
		}

		$access_groups = Access_Groups::get_active( [ 'post__in' => $access_group_ids ] );
		if ( empty( $access_groups ) ) {
			return [];
		}

		return Utils::get_react_select_format( $access_groups );
	}

	/**
	 * Get plugin status.
	 *
	 * @since 1.0.0
	 *
	 * @param  string $plugin_init_file Plugin init file.
	 * @return string Plugin status (active|inactive|not-installed).
	 */
	private function get_plugin_status( $plugin_init_file ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$installed_plugins = get_plugins();

		if ( ! isset( $installed_plugins[ $plugin_init_file ] ) ) {
			return 'not-installed';
		}

		if ( is_plugin_active( $plugin_init_file ) ) {
			return 'active';
		}

		return 'inactive';
	}
}
