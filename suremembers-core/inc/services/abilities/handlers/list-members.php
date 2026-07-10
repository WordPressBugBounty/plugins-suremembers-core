<?php
/**
 * List Members Ability.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Routers\Users as UsersRouter;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * List_Members class.
 *
 * Paginated list of users with their membership data. Wraps the existing
 * Users router so the response matches the admin Members table.
 *
 * @since 1.1.0
 */
class List_Members extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_id(): string {
		return 'list-members';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_name(): string {
		return __( 'List Members', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_description(): string {
		return __( 'Lists site users with their membership data, paginated. Supports searching by name/email and filtering by user role or by specific membership (access group) IDs.', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_category(): string {
		return 'members';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_parameters(): array {
		return [
			'page'        => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 1,
				'description' => __( 'Page number for pagination.', 'suremembers-core' ),
			],
			'per_page'    => [
				'type'        => 'integer',
				'required'    => false,
				'default'     => 20,
				'description' => __( 'Number of members per page.', 'suremembers-core' ),
			],
			'search'      => [
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
				'description' => __( 'Search term matched against username, email, and display name.', 'suremembers-core' ),
			],
			'roles'       => [
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
				'description' => __( 'Comma-separated role slugs to filter by (e.g. "subscriber,customer").', 'suremembers-core' ),
			],
			'memberships' => [
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
				'description' => __( 'Comma-separated access group IDs to filter members by.', 'suremembers-core' ),
			],
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_returns(): array {
		return [
			'type'        => 'object',
			'description' => __( 'Paginated members list with membership counts per user.', 'suremembers-core' ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_annotations(): array {
		return [
			'readOnlyHint'    => true,
			'destructiveHint' => false,
			'idempotentHint'  => true,
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_instructions(): string {
		return 'Use the memberships filter (IDs from list-memberships) to find who belongs to a group. For a single user\'s full profile use get-member-details.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		/**
		 * Users router instance.
		 *
		 * @var UsersRouter $router
		 */
		$router = UsersRouter::get_instance();

		return $this->call_rest_handler(
			[ $router, 'get_users_data' ],
			[
				'page'        => (int) $params['page'],
				'per_page'    => (int) $params['per_page'],
				'search'      => sanitize_text_field( (string) $params['search'] ),
				'roles'       => sanitize_text_field( (string) $params['roles'] ),
				'memberships' => sanitize_text_field( (string) $params['memberships'] ),
			]
		);
	}
}
