<?php
/**
 * Grant Membership Ability.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Access;
use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Grant_Membership class.
 *
 * Grants a user access to one or more access groups.
 *
 * @since 1.1.0
 */
class Grant_Membership extends Ability {
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
		return 'grant-membership';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_name(): string {
		return __( 'Grant Membership', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_description(): string {
		return __( 'Grants a user access to a membership (access group). The user immediately gains the access and any roles configured on the group.', 'suremembers-core' );
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
				'description' => __( 'The WordPress user ID to grant access to.', 'suremembers-core' ),
			],
			'membership_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The access group (membership) ID to grant. Use list-memberships to find it.', 'suremembers-core' ),
			],
			'send_email'    => [
				'type'        => 'boolean',
				'required'    => false,
				'default'     => true,
				'description' => __( 'Whether to send the access notification email to the user.', 'suremembers-core' ),
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
			'description' => __( 'Result of the grant operation.', 'suremembers-core' ),
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
		return 'Confirm the user_id and membership_id with the user before granting. Granting an already-active membership is a no-op.';
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
		$send_email    = ! empty( $params['send_email'] );

		if ( ! get_userdata( $user_id ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'User not found.', 'suremembers-core' ) ],
			];
		}

		if ( ! Access_Groups::is_active_access_group( $membership_id ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'Membership not found or not published.', 'suremembers-core' ) ],
			];
		}

		Access::grant( $user_id, $membership_id, 'default', [], $send_email );

		return [
			'success' => true,
			'data'    => [
				'message'       => __( 'Membership granted successfully.', 'suremembers-core' ),
				'user_id'       => $user_id,
				'membership_id' => $membership_id,
			],
		];
	}
}
