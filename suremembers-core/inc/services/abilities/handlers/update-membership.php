<?php
/**
 * Update Membership Ability.
 *
 * @package SureMembersCore
 * @since 1.4.1
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Access_Group_Config;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Update_Membership class.
 *
 * Renames a membership and/or changes its status (draft/publish/archive),
 * completing CRUD support for memberships.
 *
 * @since 1.4.1
 */
class Update_Membership extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 * @var string
	 */
	protected string $gated = 'suremembers_abilities_api_edit';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_id(): string {
		return 'update-membership';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_name(): string {
		return __( 'Update Membership', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_description(): string {
		return __( 'Updates a membership: rename it and/or change its status (draft, publish or archive). Provide only the fields you want to change.', 'suremembers-core' );
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
				'description' => __( 'The access group (membership) ID to update.', 'suremembers-core' ),
			],
			'title'         => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'New membership title. Omit to leave unchanged.', 'suremembers-core' ),
			],
			'status'        => [
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'New status. Omit to leave unchanged.', 'suremembers-core' ),
				'enum'        => [ 'publish', 'draft', 'suremembers_archive' ],
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
			'description' => __( 'The updated membership ID, title and status.', 'suremembers-core' ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
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
	 * @since 1.4.1
	 */
	public function get_instructions(): string {
		return 'Only supplied fields change. Setting status to "publish" makes the membership live; "draft" or "suremembers_archive" takes it offline.';
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
		$title         = isset( $params['title'] ) ? (string) $params['title'] : null;
		$status        = isset( $params['status'] ) ? (string) $params['status'] : null;

		$result = Access_Group_Config::update_details( $membership_id, $title, $status );

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => $result->get_error_message() ],
			];
		}

		return [
			'success' => true,
			'data'    => [
				'message'       => __( 'Membership updated successfully.', 'suremembers-core' ),
				'membership_id' => $membership_id,
				'title'         => get_the_title( $membership_id ),
				'status'        => (string) get_post_status( $membership_id ),
			],
		];
	}
}
