<?php
/**
 * Duplicate Membership Ability.
 *
 * @package SureMembersCore
 * @since x.x.x
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Access_Group_Config;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Duplicate_Membership class.
 *
 * Clones an existing membership — all configuration and content restrictions —
 * into a new one. Members are not copied. Useful for creating variations
 * (e.g. Monthly to Lifetime) from a proven configuration.
 *
 * @since x.x.x
 */
class Duplicate_Membership extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 * @var string
	 */
	protected string $gated = 'suremembers_abilities_api_edit';

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 */
	public function get_id(): string {
		return 'duplicate-membership';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 */
	public function get_name(): string {
		return __( 'Duplicate Membership', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 */
	public function get_description(): string {
		return __( 'Creates a new membership by cloning an existing one, preserving all settings and content restrictions. Members are not copied; the clone starts empty and in draft status by default.', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 */
	public function get_category(): string {
		return 'memberships';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 */
	public function get_parameters(): array {
		return [
			'source_membership_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The access group (membership) ID to clone.', 'suremembers-core' ),
			],
			'title'                => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Title for the new membership. If omitted, "<source title> copy" is used. Colliding titles get a " copy N" suffix.', 'suremembers-core' ),
			],
			'status'               => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'draft',
				'description' => __( 'Status for the new membership.', 'suremembers-core' ),
				'enum'        => [ 'publish', 'draft', 'suremembers_archive' ],
			],
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 */
	public function get_returns(): array {
		return [
			'type'        => 'object',
			'description' => __( 'The new membership ID, title and status.', 'suremembers-core' ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 */
	public function get_annotations(): array {
		return [
			'readOnlyHint'    => false,
			'destructiveHint' => false,
			'idempotentHint'  => false,
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 */
	public function get_instructions(): string {
		return 'Returns the new membership_id. The clone is created in draft by default so you can review it before publishing with update-membership.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$source_id = (int) $params['source_membership_id'];
		$title     = isset( $params['title'] ) ? (string) $params['title'] : '';
		$status    = in_array( $params['status'] ?? 'draft', [ 'publish', 'draft', 'suremembers_archive' ], true ) ? (string) $params['status'] : 'draft';

		$new_id = Access_Group_Config::duplicate( $source_id, $title, $status );

		if ( is_wp_error( $new_id ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => $new_id->get_error_message() ],
			];
		}

		return [
			'success' => true,
			'data'    => [
				'message'       => __( 'Membership duplicated successfully.', 'suremembers-core' ),
				'membership_id' => $new_id,
				'source_id'     => $source_id,
				'title'         => get_the_title( $new_id ),
				'status'        => (string) get_post_status( $new_id ),
			],
		];
	}
}
