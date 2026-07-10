<?php
/**
 * Menu Restriction.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Admin;

use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Traits\Get_Instance;
use SureMembersCore\Inc\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Menu Restriction
 *
 * @since 1.0.0
 */
class Menu_Restriction {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_update_nav_menu_item', [ $this, 'update_restriction_rules' ], 10, 2 );
		add_action( 'wp_nav_menu_item_custom_fields', [ $this, 'select_access_groups' ], 10, 2 );
		add_action( 'wp_ajax_queried_access_groups', [ $this, 'fetch_queried_access_groups' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'menu_list_scripts' ] );
	}

	/**
	 * Generates html for access group restriction in menu tab
	 *
	 * @param int    $id nav post id.
	 * @param object $post nav post object.
	 *
	 * @since 1.0.0
	 */
	public function select_access_groups( $id, $post ) {
		$pid = ! empty( $post->id ) ? $post->id : 0;
		$id  = empty( $id ) ? $pid : intval( $id );
		Templates::menu_restriction_markup( $id );
	}

	/**
	 * Updates access group ids for current nav menu
	 *
	 * @param int $index nav index id.
	 * @param int $item_id nav post id.
	 *
	 * @since 1.0.0
	 */
	public function update_restriction_rules( $index, $item_id ) {
		// Ignored as nonce verification is done in the same line.
		if ( empty( $_POST[ 'menu-item-suremembers-access-groups-' . $item_id ] ) || ! wp_verify_nonce( sanitize_text_field( $_POST[ 'menu-item-suremembers-access-groups-' . $item_id ] ), 'menu-item-suremembers-access-groups-' . $item_id ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( empty( $_POST['menu-item-suremembers-access-groups'] ) ) {
			update_post_meta( $item_id, SUREMEMBERS_ACCESS_GROUPS, [] );
			return;
		}

		// Ignored as we have used recursive sanitization and nonce is verified already here.
		$item_data = Utils::sanitize_recursively( 'intval', $_POST['menu-item-suremembers-access-groups'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( empty( $item_data[ $item_id ] ) ) {
			update_post_meta( $item_id, SUREMEMBERS_ACCESS_GROUPS, [] );
			return;
		}

		if ( isset( $_POST['menu-item-suremembers-access-groups-condition'][ $item_id ] ) && ! empty( $_POST['menu-item-suremembers-access-groups-condition'][ $item_id ] ) ) {
			$user_condition = sanitize_text_field( $_POST['menu-item-suremembers-access-groups-condition'][ $item_id ] );

			update_post_meta( $item_id, SUREMEMBERS_MENU_USER_CONDITION, $user_condition );
		}

		unset( $item_data[ $item_id ][''] );

		update_post_meta( $item_id, SUREMEMBERS_ACCESS_GROUPS, $item_data[ $item_id ] );
	}

	/**
	 * Enqueue scripts required for nav-menu page
	 *
	 * @param string $hook current page hook.
	 *
	 * @since 1.0.0
	 */
	public function menu_list_scripts( $hook ) {
		if ( $hook !== 'nav-menus.php' ) {
			return;
		}

		wp_register_script( 'suremembers-select-2', SUREMEMBERS_CORE_URL . 'admin/assets/js/select2.min.js', [ 'jquery' ], SUREMEMBERS_CORE_VER, true );
		wp_enqueue_script( 'suremembers-select-2' );

		wp_register_script( 'suremembers-menu-items', SUREMEMBERS_CORE_URL . 'admin/assets/js/menu-items.js', [ 'suremembers-select-2' ], SUREMEMBERS_CORE_VER, true );
		wp_enqueue_script( 'suremembers-menu-items' );

		wp_register_style( 'suremembers-select-2', SUREMEMBERS_CORE_URL . 'admin/assets/css/select2.min.css', [], SUREMEMBERS_CORE_VER, 'all' );
		wp_enqueue_style( 'suremembers-select-2' );

		wp_localize_script(
			'suremembers-menu-items',
			'suremembers_menu_items',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'security' => current_user_can( 'manage_options' ) ? wp_create_nonce( 'suremembers_queried_access_groups' ) : '',
			]
		);
	}

	/**
	 * Returns searched access groups
	 *
	 * @since 1.0.0
	 */
	public function fetch_queried_access_groups() {
		check_ajax_referer( 'suremembers_queried_access_groups', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Current user does not have required permission.', 'suremembers-core' ) ] );
		}

		$post = $_POST;

		if ( empty( $post['search'] ) ) {
			wp_send_json_error( [ 'message' => __( 'No search result found', 'suremembers-core' ) ] );
		}

		$filter_args = [
			's' => sanitize_text_field( $post['search'] ),
		];

		if ( isset( $post['exclude'] ) && ! empty( $post['exclude'] ) ) {
			// Used exclude in favor of functionality.
			$filter_args['exclude'] = Utils::sanitize_recursively( 'absint', $post['exclude'] ); //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
		}

		$access_groups = Access_Groups::get_active( $filter_args );
		$access_groups = Utils::get_select2_format( $access_groups );
		wp_send_json_success( $access_groups );
	}
}
