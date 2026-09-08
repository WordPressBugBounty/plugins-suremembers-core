<?php
/**
 * Get Protected Content Ability.
 *
 * @package SureMembersCore
 * @since 1.4.1
 */

namespace SureMembersCore\Inc\Services\Abilities\Handlers;

use SureMembersCore\Inc\Access_Group_Config;
use SureMembersCore\Inc\Services\Abilities\Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Get_Protected_Content class.
 *
 * Lists the content items (posts, pages, custom post types and taxonomy
 * scopes) a membership protects, resolved to titles and types.
 *
 * @since 1.4.1
 */
class Get_Protected_Content extends Ability {
	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_id(): string {
		return 'get-protected-content';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_name(): string {
		return __( 'Get Protected Content', 'suremembers-core' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.4.1
	 */
	public function get_description(): string {
		return __( 'Lists the content items attached to a membership\'s Protected Content list, resolved to their titles and types, along with the excluded content items.', 'suremembers-core' );
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
				'description' => __( 'The access group (membership) ID whose protected content to read.', 'suremembers-core' ),
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
			'description' => __( 'Protected and excluded content items with identifiers.', 'suremembers-core' ),
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
		return 'Read-only. Each item includes an "identifier" you can pass back to manage-protected-content to remove it.';
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
			'data'    => [
				'membership_id'     => $membership_id,
				'protected_content' => $config['protected_content'],
				'excluded_content'  => $config['excluded_content'],
				'total'             => count( $config['protected_content'] ),
			],
		];
	}
}
