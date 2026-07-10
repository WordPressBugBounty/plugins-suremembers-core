<?php
/**
 * Block Restrictions Admin.
 *
 * Handles the block restriction UI in the Gutenberg editor.
 * Shows Pro upgrade banner when SureMembers Pro is not active.
 *
 * @package SureMembersCore
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Admin;

use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Block Restrictions Admin Class.
 *
 * @since 1.0.0
 */
class Restrictions {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'block_restriction_enqueue_scripts' ] );
		add_action( 'wp_ajax_suremembers_postmeta_search', [ $this, 'get_access_group_search' ] );
	}

	/**
	 * Check if SureMembers Pro is active.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public static function is_premium_active(): bool {
		return defined( 'SUREMEMBERS_FILE' );
	}

	/**
	 * Register the block restriction attributes on the PHP side for every block.
	 *
	 * The editor script (`restrict_block.js`) adds `sureMemberRestrictions` and
	 * `sureMemberShowOnRestriction` to every block via the JS
	 * `blocks.registerBlockType` filter. Server-side rendered blocks (e.g.
	 * Gravity Forms) are previewed through the `/wp/v2/block-renderer/` REST
	 * endpoint, whose controller validates the posted `attributes` against the
	 * block's PHP-registered attribute schema and rejects any unknown attribute
	 * with "Invalid parameter(s): attributes". Declaring the same attributes
	 * here keeps that schema in sync so the preview request validates.
	 *
	 * Hooked on `register_block_type_args`, so it applies to every block type
	 * regardless of how it is registered (block.json metadata or direct PHP
	 * registration).
	 *
	 * @param array<string, mixed> $args Block type registration arguments.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.1.0
	 */
	public static function register_block_attributes( array $args ): array {
		// Mirror the JS schema: only extend blocks that declare attributes,
		// matching the editor's `if ( settings.attributes )` guard.
		if ( ! isset( $args['attributes'] ) || ! is_array( $args['attributes'] ) ) {
			return $args;
		}

		$args['attributes']['sureMemberRestrictions'] = [
			'type'    => 'array',
			'default' => [],
		];

		$args['attributes']['sureMemberShowOnRestriction'] = [
			'type'    => 'string',
			'default' => 'is_in',
		];

		return $args;
	}

	/**
	 * Enqueue scripts and style admin editor.
	 *
	 * @since 1.0.0
	 */
	public function block_restriction_enqueue_scripts(): void {
		$screen          = get_current_screen();
		$allowed_screens = apply_filters( 'suremembers_blocks_restriction_allowed_screens', [ 'post', 'widgets', 'customize', 'site-editor' ] );
		if ( ! isset( $screen->base ) || ! \in_array( $screen->base, $allowed_screens, true ) ) {
			return;
		}

		$script_dep_path = SUREMEMBERS_CORE_DIR . 'admin/assets/build/restrict_block.asset.php';
		$script_info     = file_exists( $script_dep_path ) ? include $script_dep_path : [
			'dependencies' => [],
			'version'      => SUREMEMBERS_CORE_VER,
		];

		// Ensure $script_info is an array and has dependencies key.
		if ( ! is_array( $script_info ) ) {
			$script_info = [
				'dependencies' => [],
				'version'      => SUREMEMBERS_CORE_VER,
			];
		}
		if ( ! isset( $script_info['dependencies'] ) || ! is_array( $script_info['dependencies'] ) ) {
			$script_info['dependencies'] = [];
		}

		wp_register_script(
			'suremembers-block-restriction-script',
			SUREMEMBERS_CORE_URL . 'admin/assets/build/restrict_block.js',
			array_merge(
				$script_info['dependencies'],
				[
					'wp-blocks',
					'wp-element',
					'wp-editor',
					'wp-components',
					'wp-data',
					'wp-i18n',
				]
			),
			SUREMEMBERS_CORE_VER,
			true
		);

		wp_register_style(
			'suremembers-block-restriction-style',
			SUREMEMBERS_CORE_URL . 'admin/assets/build/style-restrict_block.css',
			[],
			SUREMEMBERS_CORE_VER
		);
		wp_enqueue_style( 'suremembers-block-restriction-style' );

		wp_localize_script(
			'suremembers-block-restriction-script',
			'suremembers_global',
			$this->localize_data()
		);
		wp_enqueue_script( 'suremembers-block-restriction-script' );
	}

	/**
	 * Get localization data for the block restriction script.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function localize_data(): array {
		$is_premium_active = self::is_premium_active();
		$get_access_groups = Access_Groups::get_active();
		$return            = [];

		foreach ( $get_access_groups as $key => $value ) {
			$return[] = [
				'id'    => $key,
				'title' => $value,
			];
		}

		// Base data with premium status.
		$data = [
			'is_premium_active' => $is_premium_active,
			'upgrade_url'       => 'https://suremembers.com/pricing/?utm_source=suremembers-core&utm_medium=post-editor&utm_campaign=upgrade',
		];

		if ( ! empty( $return ) ) {
			return array_merge(
				$data,
				[
					'ajax_url'                      => admin_url( 'admin-ajax.php' ),
					'sure_member_access_groups'     => $return,
					'suremembers_postmeta_security' => current_user_can( 'edit_posts' ) ? wp_create_nonce( 'suremembers_postmeta_security' ) : '',
				]
			);
		}

		return array_merge(
			$data,
			[
				'sure_member_create_group' => Access_Groups::get_admin_url( [ 'page' => 'suremembers_rules' ] ),
			]
		);
	}

	/**
	 * AJAX handler for searching access groups.
	 *
	 * @since 1.0.0
	 */
	public function get_access_group_search(): void {
		$check_request_elementor = isset( $_POST['elementor_security'] );

		if ( $check_request_elementor ) {
			check_ajax_referer( 'suremembers_erb_security', 'elementor_security' );
		} else {
			check_ajax_referer( 'suremembers_postmeta_security', 'security' );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Current user does not have required permission.', 'suremembers-core' ) ] );
		}

		$access_group_args = [
			'numberposts' => -1, // Get all access groups.
		];

		if ( ! empty( $_POST['selected_ids'] ) ) {
			$exclude_ids = explode( ',', sanitize_text_field( wp_unslash( $_POST['selected_ids'] ) ) );
			// Ignored in favor of functionality.
			$access_group_args['exclude'] = $exclude_ids; //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
		}

		// For search title.
		if ( ! empty( $_POST['search_title'] ) ) {
			$access_group_args['s'] = sanitize_text_field( wp_unslash( $_POST['search_title'] ) );
		}

		$access_group_array = [];

		if ( ! empty( $_POST['include_ids'] ) ) {
			$include_ids         = explode( ',', sanitize_text_field( wp_unslash( $_POST['include_ids'] ) ) );
			$args                = [ 'include' => $include_ids ];
			$get_selected_groups = $this->get_queried_access_groups( $args );

			if ( is_array( $get_selected_groups ) && ! empty( $get_selected_groups ) ) {
				$access_group_array = $get_selected_groups;
			}

			if ( ! empty( $access_group_args['exclude'] ) ) {
				// Ignored in favor of functionality.
				$access_group_args['exclude'] = array_merge( $include_ids, $access_group_args['exclude'] ); //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
			} else {
				// Ignored in favor of functionality.
				$access_group_args['exclude'] = $include_ids; //phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
			}
		}

		$access_groups = $this->get_queried_access_groups( $access_group_args );

		if ( ! is_array( $access_groups ) || empty( $access_groups ) ) {
			if ( ! empty( $access_group_array ) ) {
				wp_send_json_success( $access_group_array );
			}
			$message = isset( $access_group_args['s'] )
				? __( 'No post available for this keyword.', 'suremembers-core' )
				: __( 'No membership available.', 'suremembers-core' );
			wp_send_json_error( [ 'message' => $message ] );
		}

		wp_send_json_success( array_merge( $access_groups, $access_group_array ) );
	}

	/**
	 * Get selected group title and id.
	 *
	 * @param array<string, mixed> $args Get post query.
	 *
	 * @return array<int, array<string, mixed>>|false
	 *
	 * @since 1.0.0
	 */
	public function get_queried_access_groups( array $args ) {
		$post_types       = Access_Groups::get_active( $args );
		$return_ids_title = [];

		if ( empty( $post_types ) ) {
			return false;
		}

		foreach ( $post_types as $key => $value ) {
			$return_ids_title[] = [
				'id'    => $key,
				'title' => $value,
			];
		}

		return $return_ids_title;
	}
}
