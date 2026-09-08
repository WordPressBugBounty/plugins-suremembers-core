<?php
/**
 * Get Membership Config Ability.
 *
 * @package SureMembersCore
 * @since 1.4.1
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Access_Group_Config;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Get_Membership_Config class.
 *
 * Returns the complete configuration of a single access group (membership):
 * protected content, redirect, expiration, drip, downloads, roles, priority
 * and status — so a membership can be audited or replicated programmatically.
 *
 * @since 1.4.1
 */
class Get_Membership_Config extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_id(): string {
		return 'get-membership-config';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_name(): string {
		return __( 'Get Membership Config', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_description(): string {
		return __( 'Returns the complete configuration of a membership (access group): protected content rules, excluded content, redirect/unauthorized-access settings, expiration, drip, downloads, synced roles, priority, status and active member count.', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_category(): string {
		return 'memberships';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_parameters(): array {
		return [
			'membership_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The access group (membership) ID to read. Use list-memberships to discover IDs.', 'suremembers-core' ),
			],
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_returns(): array {
		return [
			'type'        => 'object',
			'description' => __( 'The full membership configuration.', 'suremembers-core' ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
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
	 * @since 1.4.1
	 */
	public function get_instructions(): string {
		return 'Read-only. Use this to audit a membership before duplicating or editing it. Pair with manage-protected-content and update-membership to change it.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$membership_id = (int) $params['membership_id'];

		$config = Access_Group_Config::read( $membership_id );

		if ( is_wp_error( $config ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => $config->get_error_message() ],
			];
		}

		return [
			'success' => true,
			'data'    => $config,
		];
	}
}
