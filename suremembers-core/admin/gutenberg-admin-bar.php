<?php
/**
 * Gutenberg integrations class.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Admin;

use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Restricted;
use SureMembersCore\Inc\Settings;
use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Gutenberg integration handler class.
 *
 * @since 1.0.0
 */
class Gutenberg_Admin_Bar {
	use Get_Instance;

	/**
	 * Class Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'editor_bar_scripts' ] );
		// Ajax calls for admin bar.
		add_action( 'wp_ajax_suremembers_edit_get_active_access_groups', [ $this, 'get_active_access_groups' ] );
	}

	/**
	 * Add JS for edit bar Gutenberg.
	 *
	 * @since 1.0.0
	 */
	public function editor_bar_scripts() {
		global $post;

		// Check if current user can create access groups.
		if ( ! current_user_can( 'administrator' ) ) {
			return;
		}

		if ( ! $this->is_icon_enabled() ) {
			return;
		}

		if ( ! isset( $post->ID ) ) {
			return;
		}
		$script_name       = 'suremembers-blockedit';
		$script_asset_path = SUREMEMBERS_CORE_DIR . 'admin/assets/build/' . $script_name . '.asset.php';
		$script_info       = file_exists( $script_asset_path )
			? include $script_asset_path
			: [
				'dependencies' => [],
				'version'      => SUREMEMBERS_CORE_VER,
			];
		wp_register_script( $script_name, SUREMEMBERS_CORE_URL . 'admin/assets/build/blockedit.js', $script_info['dependencies'], SUREMEMBERS_CORE_VER, true );
		wp_enqueue_style( 'suremembers-blockedit-css', SUREMEMBERS_CORE_URL . 'admin/assets/build/blockedit.css', [ 'wp-components' ], SUREMEMBERS_CORE_VER );

		wp_localize_script(
			$script_name,
			'suremembers_edit',
			array_merge(
				[
					'include'         => SUREMEMBERS_PLAN_INCLUDE,
					'exclusion'       => SUREMEMBERS_PLAN_EXCLUDE,
					'priority'        => SUREMEMBERS_PLAN_PRIORITY,
					'current_post_id' => $post->ID,
				],
				[
					'ajax_url'       => admin_url( 'admin-ajax.php' ),
					'all_access_url' => Access_Groups::get_admin_url(
						[
							'tab'              => 'memberships',
							'suremembers_view' => 'iframe',
						]
					),
					'new_access_url' => Access_Groups::get_admin_url(
						[
							'tab'     => 'memberships',
							'section' => 'new_membership',
						]
					),
					'nonce'          => current_user_can( 'manage_options' ) ? wp_create_nonce( 'suremembers_edit_get_access_groups' ) : '',
				]
			)
		);
		wp_enqueue_script( $script_name );
		wp_enqueue_style( 'suremembers-admin-bar-script', SUREMEMBERS_CORE_URL . 'admin/assets/build/adminbar.css', [ 'wp-components' ], SUREMEMBERS_CORE_VER );
		wp_set_script_translations( 'suremembers-blockedit', 'suremembers-core', SUREMEMBERS_CORE_DIR . 'languages' );
	}

	/**
	 * Get active access groups
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function get_active_access_groups() {
		check_ajax_referer( 'suremembers_edit_get_access_groups', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Current user does not have required permission.', 'suremembers-core' ) ] );
		}

		$post_id           = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : null;
		$current_post_type = isset( $_POST['current_post_type'] ) ? sanitize_text_field( $_POST['current_post_type'] ) : null;

		if ( ! $post_id ) {
			wp_send_json_error( [ 'message' => __( 'Post ID missing', 'suremembers-core' ) ] );
		}

		if ( ! $current_post_type ) {
			wp_send_json_error( [ 'message' => __( 'Post Type missing', 'suremembers-core' ) ] );
		}

		$option = [
			'include'           => SUREMEMBERS_PLAN_INCLUDE,
			'exclusion'         => SUREMEMBERS_PLAN_EXCLUDE,
			'priority'          => SUREMEMBERS_PLAN_PRIORITY,
			'current_post_id'   => absint( $post_id ),
			'current_post_type' => $current_post_type,
			'current_page_type' => 'is_singular',
		];

		$access_groups        = Restricted::by_access_groups( SUREMEMBERS_POST_TYPE, $option );
		$active_access_groups = [];

		if ( ! empty( $access_groups && is_array( $access_groups ) ) ) {
			if ( isset( $access_groups['wsm_access_group'] ) && ! empty( $access_groups['wsm_access_group'] ) ) {
				foreach ( $access_groups['wsm_access_group'] as $id => $plan ) {
					$access_group           = get_post( $id );
					$post_title             = $access_group->post_title ?? '';
					$active_access_groups[] = [
						'id'    => $id,
						'title' => $post_title,
						'href'  => Access_Groups::get_admin_url(
							[
								'page'    => 'suremembers',
								'tab'     => 'memberships',
								'section' => 'edit_membership',
								'id'      => $id,
							]
						),
						'meta'  => [
							'title' => __( 'Edit Access Group ', 'suremembers-core' ) . $post_title,
							'class' => 'suremembers_adbar_itm',
						],
					];
				}
			}
		}

		wp_send_json_success(
			[
				'message' => __( 'Access groups found', 'suremembers-core' ),
				'data'    => $active_access_groups,
			]
		);
	}

	/**
	 * Check if icon display is enabled in settings page.
	 */
	public function is_icon_enabled() {
		$get_settings = Settings::get_setting( 'suremembers_admin_settings' );
		return isset( $get_settings['enable_gutenberg_icon'] ) && $get_settings['enable_gutenberg_icon'];
	}
}
