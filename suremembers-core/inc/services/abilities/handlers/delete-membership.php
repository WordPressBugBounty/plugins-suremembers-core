<?php
/**
 * Delete Membership Ability.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Routers\Members as MembersRouter;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Delete_Membership class.
 *
 * Permanently deletes an access group (membership). Gated behind the delete
 * toggle because it is irreversible.
 *
 * @since 1.1.0
 */
class Delete_Membership extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 * @var string
	 */
	protected string $gated = 'suremembers_abilities_api_delete';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_id(): string {
		return 'delete-membership';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_name(): string {
		return __( 'Delete Membership', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_description(): string {
		return __( 'Permanently deletes an access group (membership). This removes the group and its restriction configuration. Members lose the access this group provided.', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_category(): string {
		return 'memberships';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_parameters(): array {
		return [
			'membership_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The access group (membership) ID to delete. Verify with list-memberships first.', 'suremembers-core' ),
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
			'description' => __( 'Result of the delete operation.', 'suremembers-core' ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_annotations(): array {
		return [
			'readOnlyHint'    => false,
			'destructiveHint' => true,
			'idempotentHint'  => false,
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_instructions(): string {
		return 'DESTRUCTIVE and irreversible. Verify the membership_id with list-memberships and confirm with the user before deleting. To temporarily disable a group, set it to draft instead.';
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
		 * Members router instance.
		 *
		 * @var MembersRouter $router
		 */
		$router = MembersRouter::get_instance();

		return $this->call_rest_handler(
			[ $router, 'delete_membership' ],
			[ 'ids' => [ (int) $params['membership_id'] ] ]
		);
	}
}
