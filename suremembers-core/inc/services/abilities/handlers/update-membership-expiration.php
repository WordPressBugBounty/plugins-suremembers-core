<?php
/**
 * Update Membership Expiration Ability.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Routers\Users as UsersRouter;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Update_Membership_Expiration class.
 *
 * Sets or clears a per-user expiration date override for a membership.
 *
 * @since 1.1.0
 */
class Update_Membership_Expiration extends Ability {
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
		return 'update-membership-expiration';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_name(): string {
		return __( 'Update Membership Expiration', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_description(): string {
		return __( 'Sets or clears a per-user expiration date for a membership. Pass an empty expiration_date to remove the custom expiration and fall back to the group default.', 'suremembers-core' );
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
			'user_id'         => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The WordPress user ID.', 'suremembers-core' ),
			],
			'membership_id'   => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The access group (membership) ID.', 'suremembers-core' ),
			],
			'expiration_date' => [
				'type'        => 'string',
				'required'    => false,
				'default'     => '',
				'description' => __( 'Expiration date in Y-m-d format. Empty string clears the custom expiration.', 'suremembers-core' ),
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
			'description' => __( 'Result of the expiration update.', 'suremembers-core' ),
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
		return 'This overrides the group\'s default expiration for one user only. Use get-member-details to inspect the current expiration first.';
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
			[ $router, 'update_membership_expiration' ],
			[
				'user_id'         => (int) $params['user_id'],
				'membership_id'   => (int) $params['membership_id'],
				'expiration_date' => sanitize_text_field( (string) $params['expiration_date'] ),
			]
		);
	}
}
