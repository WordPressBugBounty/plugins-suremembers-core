<?php
/**
 * Manage Protected Content Ability.
 *
 * @package SureMembersCore
 * @since x.x.x
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Access_Group_Config;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Manage_Protected_Content class.
 *
 * Adds, removes, or replaces the content items on a membership's Protected
 * Content list — enabling fully automated membership setup without the admin UI.
 *
 * @since x.x.x
 */
class Manage_Protected_Content extends Ability {
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
		return 'manage-protected-content';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 */
	public function get_name(): string {
		return __( 'Manage Protected Content', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 */
	public function get_description(): string {
		return __( 'Adds, removes, or replaces the content items protected by a membership. Content items are posts, pages, custom post types (by post ID) or taxonomy term scopes.', 'suremembers-core' );
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
			'membership_id' => [
				'type'        => 'integer',
				'required'    => true,
				'description' => __( 'The access group (membership) ID to modify.', 'suremembers-core' ),
			],
			'operation'     => [
				'type'        => 'string',
				'required'    => false,
				'default'     => 'add',
				'description' => __( 'How to apply the items: "add" appends, "remove" deletes, "set" replaces the entire list.', 'suremembers-core' ),
				'enum'        => [ 'add', 'remove', 'set' ],
			],
			'items'         => [
				'type'        => 'array',
				'required'    => true,
				'description' => __( 'Array of content items. Each item is an object like { "type": "post", "id": 42 } for a specific post/page/CPT, or { "type": "taxonomy", "id": 7, "taxonomy": "category", "scope": "single" }. A raw identifier string (e.g. "post-42-|") is also accepted.', 'suremembers-core' ),
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
			'description' => __( 'The updated protected-content list and total count.', 'suremembers-core' ),
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
		return 'Use "add" to append content, "remove" to detach it, or "set" to replace the whole list. For specific posts/pages use { type: "post", id: <postID> }. Call get-protected-content first to see current items and their identifiers.';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since x.x.x
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 */
	public function execute( array $params ): array {
		$membership_id = (int) $params['membership_id'];
		$operation     = in_array( $params['operation'] ?? 'add', [ 'add', 'remove', 'set' ], true ) ? (string) $params['operation'] : 'add';
		$items         = is_array( $params['items'] ?? null ) ? $params['items'] : [];

		if ( empty( $items ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => __( 'Provide at least one content item.', 'suremembers-core' ) ],
			];
		}

		$result = Access_Group_Config::modify_protected_content( $membership_id, $items, $operation );

		if ( is_wp_error( $result ) ) {
			return [
				'success' => false,
				'data'    => [ 'message' => $result->get_error_message() ],
			];
		}

		return [
			'success' => true,
			'data'    => array_merge(
				[
					'message'       => __( 'Protected content updated successfully.', 'suremembers-core' ),
					'membership_id' => $membership_id,
					'operation'     => $operation,
				],
				$result
			),
		];
	}
}
