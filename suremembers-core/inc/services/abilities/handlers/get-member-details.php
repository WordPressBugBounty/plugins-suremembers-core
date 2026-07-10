<?php
/**
 * Get Member Details Ability.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Routers\Users as UsersRouter;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Get_Member_Details class.
 *
 * Full membership profile for a single user: all memberships with status,
 * grant/modified dates, integration source, and expiration dates.
 *
 * @since 1.1.0
 */
class Get_Member_Details extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_id(): string {
		return 'get-member-details';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_name(): string {
		return __( 'Get Member Details', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_description(): string {
		return __( 'Retrieves a single user\'s full membership profile: all access groups with status, grant and expiration dates, and the integration that granted access.', 'suremembers-core' );
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
			'user_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The WordPress user ID. Use list-members to find user IDs.', 'suremembers-core' ),
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
			'description' => __( 'User profile with all and active memberships.', 'suremembers-core' ),
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
		return 'Requires a user_id (from list-members). Returns both all_memberships and active_memberships for the user.';
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
			[ $router, 'get_user_details' ],
			[ 'user_id' => (int) $params['user_id'] ]
		);
	}
}
