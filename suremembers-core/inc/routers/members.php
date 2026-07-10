<?php
/**
 * Members Router - Handles membership-related API endpoints.
 *
 * @package SureMembers\Inc\Routers
 */

namespace SureMembersCore\Inc\Routers;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Class Members Router.
 */
class Members {
	use Get_Instance;

	/**
	 * Fetch posts by post type.
	 * Converts the old wp_ajax_suremembers_fetch_posts to REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function fetch_posts( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce validation failed', 'suremembers-core' ) ] );
		}

		// Get post type from request.
		$post_type = $request->get_param( 'postType' );

		if ( empty( $post_type ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid data.', 'suremembers-core' ),
				],
				400
			);
		}

		$post_type = sanitize_text_field( $post_type );

		$args = [
			'post_type'   => $post_type,
			'post_status' => 'publish',
		];

		$posts = get_posts( $args );

		if ( empty( $posts ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'No post available for this post type.', 'suremembers-core' ),
				],
				404
			);
		}

		$response = [];
		foreach ( $posts as $post ) {
			$temp          = [];
			$temp['value'] = $post->ID;
			$temp['label'] = $post->post_title;
			$response[]    = $temp;
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => [ 'posts' => $response ],
			],
			200
		);
	}

	/**
	 * Fetch admin bar access groups.
	 * Converts the old wp_ajax_suremembers_fetch_adminbar_groups to REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function fetch_adminbar_groups( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			wp_send_json_error( [ 'message' => __( 'Nonce validation failed', 'suremembers-core' ) ] );
		}

		$options_array     = [];
		$current_post_id   = $request->get_param( 'current_post_id' );
		$current_page_type = $request->get_param( 'current_page_type' );

		if ( $current_post_id ) {
			$current_post_id                    = absint( $current_post_id );
			$options_array['current_post_id']   = $current_post_id;
			$options_array['current_post_type'] = get_post_type( $current_post_id );
		}
		if ( $current_page_type ) {
			$options_array['current_page_type'] = sanitize_text_field( $current_page_type );
		}

		// Get active access groups using the Admin_Bar method.
		if ( class_exists( '\SureMembersCore\Inc\Admin_Bar' ) ) {
			$admin_bar     = \SureMembersCore\Inc\Admin_Bar::get_instance();
			$access_groups = $admin_bar->get_active_access_groups( $options_array );
		} else {
			$access_groups = [];
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => [ 'access_groups' => $access_groups ],
			],
			200
		);
	}

	/**
	 * Search posts by query.
	 * Converts the old wp_ajax_suremembers_search_post_by_query to REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function search_posts_by_query( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Current user does not have required permission.', 'suremembers-core' ),
				],
				403
			);
		}

		// Get Admin_Menu instance to access helper methods.
		if ( ! class_exists( '\SureMembersCore\Admin\Admin_Menu' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Admin_Menu class not found.', 'suremembers-core' ),
				],
				500
			);
		}

		$admin_menu    = \SureMembersCore\Admin\Admin_Menu::get_instance();
		$search_string = $request->get_param( 'q' ) ?? '';
		$include       = $request->get_param( 'include' ) ?? '';
		$context       = $request->get_param( 'context' ) ?? 'search';

		// Call the refactored method that returns data instead of echoing JSON.
		$result = $admin_menu->get_posts_by_query_data( $search_string, $include, $context );

		if ( $result['success'] ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'data'    => $result['data'],
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $result['message'] ?? __( 'Search failed.', 'suremembers-core' ),
			],
			400
		);
	}

	/**
	 * Update access group status.
	 * Converts the old wp_ajax_suremembers_update_status to REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function update_access_group_status( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		// Get Admin_Menu instance to access helper methods.
		if ( ! class_exists( '\SureMembersCore\Admin\Admin_Menu' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Admin_Menu class not found.', 'suremembers-core' ),
				],
				500
			);
		}

		$admin_menu = \SureMembersCore\Admin\Admin_Menu::get_instance();
		$id         = absint( $request->get_param( 'id' ) ?? 0 );
		$status     = sanitize_text_field( $request->get_param( 'status' ) ?? '' );

		// Call the refactored method that returns data instead of echoing JSON.
		$result = $admin_menu->update_access_group_status_data( $id, $status );

		if ( $result['success'] ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'data'    => $result['data'],
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $result['message'] ?? __( 'Status update failed.', 'suremembers-core' ),
			],
			400
		);
	}

	/**
	 * Submit form (create/update access group).
	 * Converts the old wp_ajax_suremembers_submit_form to REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function submit_form( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		// Get Admin_Menu instance to access helper methods.
		if ( ! class_exists( '\SureMembersCore\Admin\Admin_Menu' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Admin_Menu class not found.', 'suremembers-core' ),
				],
				500
			);
		}

		$admin_menu          = \SureMembersCore\Admin\Admin_Menu::get_instance();
		$suremembers_post    = $request->get_param( 'suremembers_post' ) ?? [];
		$suremembers_post_id = absint( $request->get_param( 'suremembers_post_id' ) ?? 0 );
		$mode                = sanitize_text_field( $request->get_param( 'mode' ) ?? 'admin' );

		// Call the refactored method that returns data instead of echoing JSON.
		$result = $admin_menu->submit_form_data( $suremembers_post, $suremembers_post_id, $mode );

		if ( $result['success'] ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'data'    => $result['data'],
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $result['message'] ?? __( 'Form submission failed.', 'suremembers-core' ),
			],
			400
		);
	}

	/**
	 * Get table data.
	 * Converts the old wp_ajax_suremembers_get_table_data to REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_table_data( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		// Get Admin_Menu instance to access helper methods.
		if ( ! class_exists( '\SureMembersCore\Admin\Admin_Menu' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Admin_Menu class not found.', 'suremembers-core' ),
				],
				500
			);
		}

		$admin_menu = \SureMembersCore\Admin\Admin_Menu::get_instance();

		// Call the refactored method that returns data instead of echoing JSON.
		$result = $admin_menu->get_table_data_result();

		if ( $result['success'] ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'data'    => $result['data'],
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $result['message'] ?? __( 'Get table data failed.', 'suremembers-core' ),
			],
			400
		);
	}

	/**
	 * Get post data.
	 * Converts the old wp_ajax_suremembers_get_post_data to REST API.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_post_data( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		// Get Admin_Menu instance to access helper methods.
		if ( ! class_exists( '\SureMembersCore\Admin\Admin_Menu' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Admin_Menu class not found.', 'suremembers-core' ),
				],
				500
			);
		}

		$admin_menu = \SureMembersCore\Admin\Admin_Menu::get_instance();
		$post_id    = absint( $request->get_param( 'post_id' ) ?? 0 );

		// Call the refactored method that returns data instead of echoing JSON.
		$result = $admin_menu->get_post_data_result( $post_id );

		if ( $result['success'] ) {
			return new \WP_REST_Response(
				[
					'success' => true,
					'data'    => $result['data'],
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $result['message'] ?? __( 'Get post data failed.', 'suremembers-core' ),
			],
			400
		);
	}

	/**
	 * Delete membership(s).
	 * Handles both single and bulk delete operations.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 * @since 1.10.14
	 */
	public function delete_membership( $request ) {
		// Nonce verification.
		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wp_rest' ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Nonce validation failed', 'suremembers-core' ),
				],
				403
			);
		}

		// Get membership IDs from request (supports both single ID and array of IDs).
		$ids = $request->get_param( 'ids' );

		if ( empty( $ids ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'No membership IDs provided', 'suremembers-core' ),
				],
				400
			);
		}

		// Ensure IDs is an array.
		if ( ! is_array( $ids ) ) {
			$ids = [ $ids ];
		}

		// Sanitize IDs.
		$ids = array_map( 'absint', $ids );
		$ids = array_filter( $ids );

		if ( empty( $ids ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid membership IDs', 'suremembers-core' ),
				],
				400
			);
		}

		$deleted_count = 0;
		$failed_count  = 0;
		$failed_ids    = [];

		foreach ( $ids as $id ) {
			// Verify the post exists and is a membership.
			$post = get_post( $id );

			if ( ! $post || $post->post_type !== SUREMEMBERS_POST_TYPE ) {
				$failed_count++;
				$failed_ids[] = $id;
				continue;
			}

			// Delete the post.
			$deleted = wp_delete_post( $id, true ); // true = force delete (skip trash).

			if ( $deleted ) {
				$deleted_count++;
			} else {
				$failed_count++;
				$failed_ids[] = $id;
			}
		}

		// Prepare response.
		$total_count = count( $ids );

		if ( $deleted_count === $total_count ) {
			// All deletions successful.
			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => sprintf(
						/* translators: %d: number of memberships */
						_n(
							'%d membership deleted successfully',
							'%d memberships deleted successfully',
							$deleted_count,
							'suremembers-core'
						),
						$deleted_count
					),
					'data'    => [
						'deleted_count' => $deleted_count,
						'total_count'   => $total_count,
					],
				],
				200
			);
		}
		if ( $deleted_count > 0 ) {
			// Partial success.
			return new \WP_REST_Response(
				[
					'success' => true,
					'message' => sprintf(
						/* translators: 1: deleted count, 2: failed count */
						__( '%1$d membership(s) deleted successfully, %2$d failed', 'suremembers-core' ),
						$deleted_count,
						$failed_count
					),
					'data'    => [
						'deleted_count' => $deleted_count,
						'failed_count'  => $failed_count,
						'failed_ids'    => $failed_ids,
						'total_count'   => $total_count,
					],
				],
				200
			);
		}
			// All deletions failed.
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Failed to delete membership(s)', 'suremembers-core' ),
					'data'    => [
						'failed_count' => $failed_count,
						'failed_ids'   => $failed_ids,
						'total_count'  => $total_count,
					],
				],
				500
			);
	}
}
