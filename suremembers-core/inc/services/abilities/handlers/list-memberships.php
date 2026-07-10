<?php
/**
 * List Memberships Ability.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * List_Memberships class.
 *
 * Lists every published access group with active member count, expiration
 * configuration, and a restriction summary.
 *
 * @since 1.1.0
 */
class List_Memberships extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_id(): string {
		return 'list-memberships';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_name(): string {
		return __( 'List Memberships', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_description(): string {
		return __( 'Lists all published access groups (memberships) with their active member count, expiration configuration, and whether content restriction rules are configured.', 'suremembers-core' );
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
			'include_drafts' => [
				'type'        => 'boolean',
				'required'    => false,
				'default'     => false,
				'description' => __( 'Include draft (inactive) access groups in the list.', 'suremembers-core' ),
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
			'type'        => 'array',
			'description' => __( 'List of access groups with member counts and settings.', 'suremembers-core' ),
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
		return 'Returns the membership_id needed by get-membership-stats, grant-membership, and other per-group abilities. Active member counts are point-in-time.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$include_drafts = ! empty( $params['include_drafts'] );

		$args = [];
		if ( $include_drafts ) {
			$args['post_status'] = [ 'publish', 'draft' ];
		}

		$groups = Access_Groups::get_active( $args );

		$memberships = [];
		foreach ( $groups as $id => $title ) {
			$id         = (int) $id;
			$expiration = get_post_meta( $id, SUREMEMBERS_PLAN_EXPIRATION, true );
			$rules      = get_post_meta( $id, SUREMEMBERS_PLAN_INCLUDE, true );

			$memberships[] = [
				'id'                 => $id,
				'title'              => $title,
				'status'             => get_post_status( $id ),
				'active_members'     => Access_Groups::get_users_count( $id ),
				'expiration_enabled' => is_array( $expiration ) && ! empty( $expiration['enable'] ),
				'expiration_type'    => is_array( $expiration ) ? ( $expiration['type'] ?? '' ) : '',
				'has_restrictions'   => ! empty( $rules ),
			];
		}

		return [
			'success' => true,
			'data'    => [
				'memberships' => $memberships,
				'total'       => count( $memberships ),
			],
		];
	}
}
