<?php
/**
 * Users Router - Handles user-related API endpoints.
 *
 * @package SureMembers\Inc\Routers
 */

namespace SureMembersCore\Inc\Routers;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Class Users Router.
 */
class Users {
	use Get_Instance;

	/**
	 * Get users data with membership information.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 * @since 2.0.0
	 */
	public function get_users_data( $request ) {
		// Verify admin request.
		$auth_check = $this->verify_admin_request( $request );
		if ( $auth_check ) {
			return $auth_check;
		}

		// Get pagination parameters.
		$page        = absint( $request->get_param( 'page' ) ?? 1 );
		$per_page    = absint( $request->get_param( 'per_page' ) ?? 20 );
		$search      = sanitize_text_field( $request->get_param( 'search' ) ?? '' );
		$roles       = $request->get_param( 'roles' ) ?? ''; // Comma-separated role slugs.
		$memberships = $request->get_param( 'memberships' ) ?? ''; // Comma-separated membership IDs.

		// Build user query args.
		// If memberships filter is provided, we need to fetch all users first, then filter.
		// This is because WP_User_Query doesn't support membership filtering directly.
		$include_pagination = empty( $memberships );
		$user_args          = $this->build_user_query_args( $page, $per_page, $search, $roles, $include_pagination );

		// Execute user query.
		$user_query     = new \WP_User_Query( $user_args );
		$users          = $user_query->get_results();
		$total_filtered = null;

		// Filter by memberships if provided.
		if ( ! empty( $memberships ) && ! empty( $users ) ) {
			$filter_result  = $this->filter_users_by_memberships( $users, $memberships, $page, $per_page );
			$users          = $filter_result['users'];
			$total_filtered = $filter_result['total'];
		}

		// Format user data for response.
		$users_data = array_map( [ $this, 'format_user_data' ], $users );

		// Get total count for pagination.
		$total_users = ! empty( $memberships ) && $total_filtered !== null
			? $total_filtered
			: $user_query->get_total();

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'users'      => $users_data,
					'pagination' => [
						'page'        => $page,
						'per_page'    => $per_page,
						'total'       => $total_users,
						'total_pages' => ceil( $total_users / $per_page ),
					],
				],
			],
			200
		);
	}

	/**
	 * Get all available memberships (access groups).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_all_memberships( $request ) {
		// Verify admin request.
		$auth_check = $this->verify_admin_request( $request );
		if ( $auth_check ) {
			return $auth_check;
		}

		$memberships           = \SureMembersCore\Inc\Access_Groups::get_active();
		$formatted_memberships = [];

		foreach ( $memberships as $id => $title ) {
			$formatted_memberships[] = [
				'id'    => $id,
				'title' => $title,
			];
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $formatted_memberships,
			],
			200
		);
	}

	/**
	 * Get user details with memberships.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_user_details( $request ) {
		// Verify admin request.
		$auth_check = $this->verify_admin_request( $request );
		if ( $auth_check ) {
			return $auth_check;
		}

		$user_id = absint( $request->get_param( 'user_id' ) ?? 0 );

		if ( ! $user_id ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User ID is required.', 'suremembers-core' ),
				],
				400
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User not found.', 'suremembers-core' ),
				],
				404
			);
		}

		// Get user memberships.
		$user_memberships = get_user_meta( $user_id, SUREMEMBERS_USER_META, true );
		if ( ! is_array( $user_memberships ) ) {
			$user_memberships = [];
		}

		// Get all memberships with details.
		$all_memberships    = [];
		$active_memberships = [];

		foreach ( $user_memberships as $membership_id ) {
			$membership_id = absint( $membership_id );
			if ( ! $membership_id ) {
				continue;
			}

			// Get membership post.
			$membership_post = get_post( $membership_id );
			$membership_data = $this->format_membership_data( $user_id, $membership_id, $membership_post );

			if ( ! $membership_data ) {
				continue;
			}

			$all_memberships[] = $membership_data;

			// Check if active.
			if ( $membership_data['status'] === 'active' ) {
				$active_memberships[] = $membership_data;
			}
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => [
					'id'                 => $user->ID,
					'name'               => $user->display_name,
					'email'              => $user->user_email,
					'username'           => $user->user_login,
					'avatar'             => get_avatar_url(
						$user->ID,
						[
							'size'    => 96,
							'default' => '404',
						]
					),
					'roles'              => $user->roles,
					'memberships'        => $all_memberships,
					'active_memberships' => $active_memberships,
					'memberships_count'  => count( $all_memberships ),
					'active_count'       => count( $active_memberships ),
				],
			],
			200
		);
	}

	/**
	 * Grant membership to user.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function grant_membership( $request ) {
		// Verify admin request.
		$auth_check = $this->verify_admin_request( $request );
		if ( $auth_check ) {
			return $auth_check;
		}

		$user_id       = absint( $request->get_param( 'user_id' ) ?? 0 );
		$membership_id = absint( $request->get_param( 'membership_id' ) ?? 0 );

		if ( ! $user_id || ! $membership_id ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User ID and Membership ID are required.', 'suremembers-core' ),
				],
				400
			);
		}

		// Block actions on memberships that are not published (e.g. drafts).
		$status_check = $this->verify_membership_published( $membership_id );
		if ( $status_check ) {
			return $status_check;
		}

		\SureMembersCore\Inc\Access::grant( $user_id, $membership_id );

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Membership granted successfully.', 'suremembers-core' ),
			],
			200
		);
	}

	/**
	 * Revoke membership from user.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function revoke_membership( $request ) {
		// Verify admin request.
		$auth_check = $this->verify_admin_request( $request );
		if ( $auth_check ) {
			return $auth_check;
		}

		$user_id       = absint( $request->get_param( 'user_id' ) ?? 0 );
		$membership_id = absint( $request->get_param( 'membership_id' ) ?? 0 );

		if ( ! $user_id || ! $membership_id ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User ID and Membership ID are required.', 'suremembers-core' ),
				],
				400
			);
		}

		// Block actions on memberships that are not published (e.g. drafts).
		$status_check = $this->verify_membership_published( $membership_id );
		if ( $status_check ) {
			return $status_check;
		}

		\SureMembersCore\Inc\Access::revoke( $user_id, $membership_id );

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Membership revoked successfully.', 'suremembers-core' ),
			],
			200
		);
	}

	/**
	 * Update membership expiration date for user.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function update_membership_expiration( $request ) {
		// Verify admin request.
		$auth_check = $this->verify_admin_request( $request );
		if ( $auth_check ) {
			return $auth_check;
		}

		$user_id         = absint( $request->get_param( 'user_id' ) ?? 0 );
		$membership_id   = absint( $request->get_param( 'membership_id' ) ?? 0 );
		$expiration_date = sanitize_text_field( $request->get_param( 'expiration_date' ) ?? '' );

		if ( ! $user_id || ! $membership_id ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User ID and Membership ID are required.', 'suremembers-core' ),
				],
				400
			);
		}

		// Verify user exists.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User not found.', 'suremembers-core' ),
				],
				404
			);
		}

		// Verify membership exists.
		$membership_post = get_post( $membership_id );
		if ( ! $membership_post || $membership_post->post_type !== SUREMEMBERS_POST_TYPE ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Membership not found.', 'suremembers-core' ),
				],
				404
			);
		}

		// Get existing expiration data.
		$user_expiration = get_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, true );
		if ( ! is_array( $user_expiration ) ) {
			$user_expiration = [];
		}

		// Update expiration date for this membership.
		if ( ! empty( $expiration_date ) ) {
			// Validate date format.
			$date_timestamp = strtotime( $expiration_date );
			if ( $date_timestamp === false ) {
				return new \WP_REST_Response(
					[
						'success' => false,
						'message' => __( 'Invalid date format.', 'suremembers-core' ),
					],
					400
				);
			}
			$user_expiration[ $membership_id ] = sanitize_text_field( $expiration_date );
		} else {
			// Remove expiration if empty.
			unset( $user_expiration[ $membership_id ] );
		}

		// Save updated expiration data.
		update_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, $user_expiration );

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Expiration date updated successfully.', 'suremembers-core' ),
			],
			200
		);
	}

	/**
	 * Remove membership from user (completely remove, not just revoke).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function remove_membership( $request ) {
		// Verify admin request.
		$auth_check = $this->verify_admin_request( $request );
		if ( $auth_check ) {
			return $auth_check;
		}

		$user_id       = absint( $request->get_param( 'user_id' ) ?? 0 );
		$membership_id = absint( $request->get_param( 'membership_id' ) ?? 0 );

		if ( ! $user_id || ! $membership_id ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User ID and Membership ID are required.', 'suremembers-core' ),
				],
				400
			);
		}

		// Verify user exists.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'User not found.', 'suremembers-core' ),
				],
				404
			);
		}

		// Verify membership exists.
		$membership_post = get_post( $membership_id );
		if ( ! $membership_post || $membership_post->post_type !== SUREMEMBERS_POST_TYPE ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Membership not found.', 'suremembers-core' ),
				],
				404
			);
		}

		// Get user's access groups.
		$access_groups = get_user_meta( $user_id, SUREMEMBERS_USER_META, true );
		if ( ! is_array( $access_groups ) ) {
			$access_groups = [];
		}

		// Remove membership from access groups array.
		$access_groups = array_filter(
			$access_groups,
			static function ( $ag_id ) use ( $membership_id ) {
				return absint( $ag_id ) !== $membership_id;
			}
		);

		// Update user meta.
		update_user_meta( $user_id, SUREMEMBERS_USER_META, $access_groups );

		// Remove detailed access group meta.
		delete_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$membership_id}" );

		// Remove expiration data if exists.
		$user_expire = get_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, true );
		if ( is_array( $user_expire ) && isset( $user_expire[ $membership_id ] ) ) {
			unset( $user_expire[ $membership_id ] );
			update_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, $user_expire );
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Membership removed successfully.', 'suremembers-core' ),
			],
			200
		);
	}

	/**
	 * Ensure a membership (access group) is published before it can be modified.
	 *
	 * @param int $membership_id Membership ID.
	 * @return \WP_REST_Response|null Error response when not published, null otherwise.
	 * @since 2.0.0
	 */
	private function verify_membership_published( $membership_id ) {
		$membership_post = get_post( $membership_id );
		if ( ! $membership_post || $membership_post->post_type !== SUREMEMBERS_POST_TYPE || $membership_post->post_status !== 'publish' ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'This membership is in draft and cannot be modified.', 'suremembers-core' ),
				],
				400
			);
		}

		return null;
	}

	/**
	 * Verify admin request (nonce and permissions).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|null Returns error response or null if valid.
	 * @since 2.0.0
	 */
	private function verify_admin_request( $request ) {
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

		return null;
	}

	/**
	 * Build user query arguments.
	 *
	 * @param int    $page Current page number.
	 * @param int    $per_page Number of users per page.
	 * @param string $search Search string.
	 * @param string $roles Comma-separated role slugs.
	 * @param bool   $include_pagination Whether to include pagination.
	 * @return array{number: int, offset: int, role__in: array<int|string, string>, search?: string, search_columns?: array<int, string>} User query arguments.
	 * @since 2.0.0
	 */
	private function build_user_query_args( $page, $per_page, $search, $roles, $include_pagination = true ) {
		$user_args = [
			'number'   => $include_pagination ? $per_page : -1,
			'offset'   => $include_pagination ? ( $page - 1 ) * $per_page : 0,
			'role__in' => [],
		];

		if ( ! empty( $search ) ) {
			$user_args['search']         = '*' . $search . '*';
			$user_args['search_columns'] = [ 'user_login', 'user_nicename', 'user_email', 'display_name' ];
		}

		if ( ! empty( $roles ) ) {
			$roles_array           = explode( ',', $roles );
			$user_args['role__in'] = array_map( 'sanitize_key', $roles_array );
		}

		return $user_args;
	}

	/**
	 * Filter users by memberships.
	 *
	 * @param array<int, \WP_User> $users List of users to filter.
	 * @param string               $memberships Comma-separated membership IDs.
	 * @param int                  $page Current page number.
	 * @param int                  $per_page Number of users per page.
	 * @return array{users: array<int, \WP_User>, total: int} Filtered users and total count.
	 * @since 2.0.0
	 */
	private function filter_users_by_memberships( $users, $memberships, $page, $per_page ) {
		$membership_ids = array_map( 'absint', explode( ',', $memberships ) );
		$membership_ids = array_filter( $membership_ids );

		if ( empty( $membership_ids ) ) {
			return [
				'users' => $users,
				'total' => count( $users ),
			];
		}

		$filtered_users = [];

		foreach ( $users as $user ) {
			$user_memberships = get_user_meta( $user->ID, SUREMEMBERS_USER_META, true );
			if ( ! is_array( $user_memberships ) ) {
				$user_memberships = [];
			}

			// Check if user has any of the selected memberships.
			$user_membership_ids = array_map( 'absint', $user_memberships );
			$has_membership      = ! empty( array_intersect( $membership_ids, $user_membership_ids ) );

			if ( $has_membership ) {
				$filtered_users[] = $user;
			}
		}

		// Apply pagination after filtering.
		$total_filtered = count( $filtered_users );
		$offset         = ( $page - 1 ) * $per_page;
		$paginated      = array_slice( $filtered_users, $offset, $per_page );

		return [
			'users' => $paginated,
			'total' => $total_filtered,
		];
	}

	/**
	 * Get membership data for a single user.
	 *
	 * @param int $user_id User ID.
	 * @return array{all_memberships: array<int, array{id: int, name: string, status: string}>, active_memberships: array<int, array{id: int, name: string, status: string}>} Membership data.
	 * @since 2.0.0
	 */
	private function get_user_membership_data( $user_id ) {
		$user_memberships = get_user_meta( $user_id, SUREMEMBERS_USER_META, true );
		if ( ! is_array( $user_memberships ) ) {
			$user_memberships = [];
		}

		$all_memberships    = [];
		$active_memberships = [];

		foreach ( $user_memberships as $membership_id ) {
			$membership_id = absint( $membership_id );
			if ( ! $membership_id ) {
				continue;
			}

			// Get membership post.
			$membership_post = get_post( $membership_id );
			if ( ! $membership_post || $membership_post->post_type !== SUREMEMBERS_POST_TYPE ) {
				continue;
			}

			// Get membership details.
			$membership_detail = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$membership_id}", true );
			$status            = is_array( $membership_detail ) && isset( $membership_detail['status'] )
				? $membership_detail['status']
				: 'unknown';

			$membership_data = [
				'id'     => $membership_id,
				'name'   => $membership_post->post_title,
				'status' => $status,
			];

			$all_memberships[] = $membership_data;

			// Check if active.
			if ( $status === 'active' ) {
				$active_memberships[] = $membership_data;
			}
		}

		return [
			'all_memberships'    => $all_memberships,
			'active_memberships' => $active_memberships,
		];
	}

	/**
	 * Format user data for response.
	 *
	 * @param \WP_User $user WordPress user object.
	 * @return array{id: int, name: string, email: string, username: string, avatar: string, roles: array<int, string>, memberships_count: int, active_count: int, active_memberships: array<int, array{id: int, name: string, status: string}>, all_memberships: array<int, array{id: int, name: string, status: string}>} Formatted user data.
	 * @since 2.0.0
	 */
	private function format_user_data( $user ) {
		$membership_data = $this->get_user_membership_data( $user->ID );
		$user_roles      = $user->roles;
		if ( ! is_array( $user_roles ) ) {
			$user_roles = [];
		}

		return [
			'id'                 => $user->ID,
			'name'               => $user->display_name,
			'email'              => $user->user_email,
			'username'           => $user->user_login,
			'avatar'             => (string) get_avatar_url(
				$user->ID,
				[
					'size'    => 96,
					'default' => '404',
				]
			),
			'roles'              => $user_roles,
			'memberships_count'  => count( $membership_data['all_memberships'] ),
			'active_count'       => count( $membership_data['active_memberships'] ),
			'active_memberships' => $membership_data['active_memberships'],
			'all_memberships'    => $membership_data['all_memberships'],
		];
	}

	/**
	 * Calculate membership expiration date.
	 *
	 * @param int $user_id User ID.
	 * @param int $membership_id Membership ID.
	 * @param int $created Created timestamp.
	 * @param int $modified Modified timestamp.
	 * @return string Expiration date string or empty string.
	 * @since 2.0.0
	 */
	private function calculate_membership_expiration( $user_id, $membership_id, $created, $modified ) {
		$expiration  = get_post_meta( $membership_id, SUREMEMBERS_PLAN_EXPIRATION, true );
		$user_expire = get_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, true );
		$expire_date = '';

		// A user-specific expiration override always takes precedence and must be
		// honored even when the membership plan has no expiration configured. This
		// is the value saved from the Members popup datepicker.
		if ( is_array( $user_expire ) && isset( $user_expire[ $membership_id ] ) && ! empty( trim( (string) $user_expire[ $membership_id ] ) ) ) {
			return sanitize_text_field( strval( $user_expire[ $membership_id ] ) );
		}

		if ( empty( $expiration ) || ! is_array( $expiration ) ) {
			return '';
		}

		// Check if expiration is enabled.
		$expiration_enabled = true;
		if ( isset( $expiration['enable'] ) ) {
			$enable_value       = $expiration['enable'];
			$expiration_enabled = ! ( $enable_value === false ||
				$enable_value === '0' ||
				$enable_value === 0 ||
				strtolower( (string) $enable_value ) === 'off' ||
				strtolower( (string) $enable_value ) === 'false' );
		}

		if ( ! $expiration_enabled ) {
			return '';
		}

		// Determine type.
		$expiration_type = $this->determine_expiration_type( $expiration );

		// Get expire date based on type.
		if ( $expiration_type === 'relative_date' ) {
			$expire_date = $this->calculate_relative_expiration( $expiration, $user_expire, $membership_id, $created, $modified );
		} elseif ( $expiration_type === 'specific_date' ) {
			$expire_date = $this->calculate_specific_expiration( $expiration, $user_expire, $membership_id );
		}

		return $expire_date;
	}

	/**
	 * Determine expiration type from expiration settings.
	 *
	 * @param array<string, mixed> $expiration Expiration settings array.
	 * @return string Expiration type ('relative_date', 'specific_date', or empty).
	 * @since 2.0.0
	 */
	private function determine_expiration_type( $expiration ) {
		if ( isset( $expiration['type'] ) && ! empty( $expiration['type'] ) ) {
			return $expiration['type'];
		}

		if ( isset( $expiration['specific_date'] ) && ! empty( trim( (string) $expiration['specific_date'] ) ) ) {
			return 'specific_date';
		}

		if ( isset( $expiration['delay'] ) && ! empty( trim( (string) $expiration['delay'] ) ) && intval( $expiration['delay'] ) > 0 ) {
			return 'relative_date';
		}

		return '';
	}

	/**
	 * Calculate relative expiration date.
	 *
	 * @param array<string, mixed> $expiration Expiration settings.
	 * @param mixed                $user_expire User expiration data.
	 * @param int                  $membership_id Membership ID.
	 * @param int                  $created Created timestamp.
	 * @param int                  $modified Modified timestamp.
	 * @return string Expiration date or empty string.
	 * @since 2.0.0
	 */
	private function calculate_relative_expiration( $expiration, $user_expire, $membership_id, $created, $modified ) {
		if ( ! isset( $expiration['delay'] ) || empty( trim( (string) $expiration['delay'] ) ) ) {
			return '';
		}

		$delay = intval( $expiration['delay'] );
		if ( $delay <= 0 ) {
			return '';
		}

		$current_date     = $modified > 0 ? $modified : ( $created > 0 ? $created : time() );
		$future_timestamp = strtotime( '+' . $delay . ' days', $current_date );

		if ( $future_timestamp === false ) {
			return '';
		}

		$future_date = date( 'Y-m-d', $future_timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		// Check if user has custom expiration date.
		if ( is_array( $user_expire ) && isset( $user_expire[ $membership_id ] ) && ! empty( trim( (string) $user_expire[ $membership_id ] ) ) ) {
			return sanitize_text_field( strval( $user_expire[ $membership_id ] ) );
		}

		return $future_date;
	}

	/**
	 * Calculate specific expiration date.
	 *
	 * @param array<string, mixed> $expiration Expiration settings.
	 * @param mixed                $user_expire User expiration data.
	 * @param int                  $membership_id Membership ID.
	 * @return string Expiration date or empty string.
	 * @since 2.0.0
	 */
	private function calculate_specific_expiration( $expiration, $user_expire, $membership_id ) {
		if ( ! isset( $expiration['specific_date'] ) || empty( trim( (string) $expiration['specific_date'] ) ) ) {
			return '';
		}

		// Check if user has custom expiration date.
		if ( is_array( $user_expire ) && isset( $user_expire[ $membership_id ] ) && ! empty( trim( (string) $user_expire[ $membership_id ] ) ) ) {
			return sanitize_text_field( strval( $user_expire[ $membership_id ] ) );
		}

		return trim( (string) $expiration['specific_date'] );
	}

	/**
	 * Get integration icon as base64 encoded data URI.
	 *
	 * @param string $integration Integration name.
	 * @return string Base64 encoded SVG data URI or empty string.
	 * @since 2.0.0
	 */
	private function get_integration_icon( $integration ) {
		if ( empty( $integration ) ) {
			return '';
		}

		$logo_url = \SureMembersCore\Inc\Utils::integration_icons( $integration );
		if ( ! is_string( $logo_url ) || empty( $logo_url ) ) {
			return '';
		}

		$logo = file_get_contents( $logo_url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! $logo ) {
			return '';
		}

		return 'data:image/svg+xml;base64,' . base64_encode( $logo ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Format detailed membership data for a user.
	 *
	 * @param int   $user_id User ID.
	 * @param int   $membership_id Membership ID.
	 * @param mixed $membership_post Membership post object.
	 * @return array<string, mixed>|null Formatted membership data or null if invalid.
	 * @since 2.0.0
	 */
	private function format_membership_data( $user_id, $membership_id, $membership_post ) {
		if ( ! $membership_post || $membership_post->post_type !== SUREMEMBERS_POST_TYPE ) {
			return null;
		}

		// Get membership details using Restricted::get_plan_details.
		$plan_details = \SureMembersCore\Inc\Restricted::get_plan_details( $user_id, $membership_id );
		$status       = is_array( $plan_details ) && isset( $plan_details['status'] )
			? $plan_details['status']
			: 'unknown';

		// Get created and modified timestamps.
		$created  = is_array( $plan_details ) && isset( $plan_details['created'] )
			? intval( $plan_details['created'] )
			: 0;
		$modified = is_array( $plan_details ) && isset( $plan_details['modified'] )
			? intval( $plan_details['modified'] )
			: $created;

		// Get integration.
		$integration = is_array( $plan_details ) && isset( $plan_details['integration'] )
			? $plan_details['integration']
			: '';

		// Get expiration date.
		$expire_date = $this->calculate_membership_expiration( $user_id, $membership_id, $created, $modified );

		// Get integration icon.
		$integration_icon = $this->get_integration_icon( $integration );

		return [
			'id'               => $membership_id,
			'name'             => $membership_post->post_title,
			'status'           => $status,
			'post_status'      => $membership_post->post_status,
			'created'          => $created,
			'modified'         => $modified,
			'integration'      => $integration,
			'integration_icon' => $integration_icon,
			'expire_date'      => $expire_date,
			'edit_url'         => \SureMembersCore\Inc\Access_Groups::get_admin_url(
				[
					'page'    => 'suremembers',
					'tab'     => 'memberships',
					'section' => 'edit_membership',
					'id'      => $membership_id,
				]
			),
		];
	}
}
