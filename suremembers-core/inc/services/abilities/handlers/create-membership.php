<?php
/**
 * Create Membership Ability.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Create_Membership class.
 *
 * Creates a new access group (membership). Restriction rules, expiration, and
 * roles are configured separately in the admin UI.
 *
 * @since 1.1.0
 */
class Create_Membership extends Ability {
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
		return 'create-membership';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_name(): string {
		return __( 'Create Membership', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_description(): string {
		return __( 'Creates a new access group (membership) with a title and status. Content restriction rules, expiration, and roles are configured afterwards in the admin UI.', 'suremembers-core' );
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
			'title'  => [
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'The name of the membership / access group.', 'suremembers-core' ),
			],
			'status' => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'draft',
				'description' => __( 'Initial status. Drafts are inactive; publish makes the group live.', 'suremembers-core' ),
				'enum'        => [ 'publish', 'draft' ],
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
			'description' => __( 'The created access group ID and status.', 'suremembers-core' ),
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
			'idempotentHint'  => false,
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 */
	public function get_instructions(): string {
		return 'Creates an empty access group in draft status by default. Returns membership_id. The new group has no restrictions until configured in the admin UI.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$title  = sanitize_text_field( (string) $params['title'] );
		$status = in_array( $params['status'], [ 'publish', 'draft' ], true ) ? (string) $params['status'] : 'draft';

		if ( empty( $title ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'A membership title is required.', 'suremembers-core' ) ],
			];
		}

		$post_id = wp_insert_post(
			[
				'post_type'   => SUREMEMBERS_POST_TYPE,
				'post_title'  => $title,
				'post_status' => $status,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => $post_id->get_error_message() ],
			];
		}

		return [
			'success' => true,
			'data'    => [
				'message'       => __( 'Membership created successfully.', 'suremembers-core' ),
				'membership_id' => (int) $post_id,
				'status'        => $status,
			],
		];
	}
}
