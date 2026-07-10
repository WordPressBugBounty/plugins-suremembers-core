<?php
/**
 * Revoke Membership Ability.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Access;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Revoke_Membership class.
 *
 * Revokes a user's access to a membership without deleting the grant history.
 *
 * @since 1.1.0
 */
class Revoke_Membership extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 * @var string
	 */
	protected string $gated = 'suremembers_abilities_api_edit';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_id(): string {
		return 'revoke-membership';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_name(): string {
		return __( 'Revoke Membership', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_description(): string {
		return __( 'Revokes a user\'s access to a membership (access group). The grant is marked revoked but its history is preserved, so it can be granted again later.', 'suremembers-core' );
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
			'user_id'       => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The WordPress user ID to revoke access from.', 'suremembers-core' ),
			],
			'membership_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The access group (membership) ID to revoke.', 'suremembers-core' ),
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
			'description' => __( 'Result of the revoke operation.', 'suremembers-core' ),
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
		return 'Revoking keeps history (use delete-membership only to remove the access group entirely). Confirm user_id and membership_id before revoking.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$user_id       = (int) $params['user_id'];
		$membership_id = (int) $params['membership_id'];

		if ( ! get_userdata( $user_id ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'User not found.', 'suremembers-core' ) ],
			];
		}

		Access::revoke( $user_id, $membership_id );

		return [
			'success' => true,
			'data'    => [
				'message'       => __( 'Membership revoked successfully.', 'suremembers-core' ),
				'user_id'       => $user_id,
				'membership_id' => $membership_id,
			],
		];
	}
}
