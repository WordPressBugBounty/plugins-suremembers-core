<?php
/**
 * Access Group Configuration model.
 *
 * A single read/write surface for an access group's (membership's) full
 * configuration — protected content, redirect, expiration, drip, downloads,
 * roles, priority and status. Previously this logic lived only inline in the
 * admin save handler (admin/admin-menu.php); it is extracted here so the
 * Abilities API (and any other consumer) can audit, replicate and mutate a
 * membership programmatically without going through the admin UI.
 *
 * @package SureMembersCore
 * @since 1.4.1
 */

namespace SureMembersCore\Inc;

defined( 'ABSPATH' ) || exit;

/**
 * Access_Group_Config class.
 *
 * @since 1.4.1
 */
class Access_Group_Config {
	/**
	 * Post statuses an access group may hold.
	 *
	 * @since 1.4.1
	 * @var array<int, string>
	 */
	public const ALLOWED_STATUSES = [ 'publish', 'draft', SUREMEMBERS_ARCHIVE ];

	/**
	 * Check whether an ID refers to an existing access group post.
	 *
	 * @since 1.4.1
	 *
	 * @param int $group_id Access group (membership) post ID.
	 * @return bool
	 */
	public static function exists( int $group_id ): bool {
		return $group_id > 0 && get_post_type( $group_id ) === SUREMEMBERS_POST_TYPE;
	}

	/**
	 * Post meta keys that make up an access group's configuration.
	 *
	 * These are the keys copied on duplication. Pro features (variations,
	 * corporate accounts, native payments) can register their own keys via the
	 * `suremembers_access_group_config_meta_keys` filter so a duplicated group
	 * carries their settings too.
	 *
	 * @since 1.4.1
	 *
	 * @return array<int, string>
	 */
	public static function config_meta_keys(): array {
		$keys = [
			SUREMEMBERS_PLAN_INCLUDE,
			SUREMEMBERS_PLAN_EXCLUDE,
			SUREMEMBERS_PLAN_RULES,
			SUREMEMBERS_RESTRICTED_URL,
			SUREMEMBERS_PLAN_EXPIRATION,
			SUREMEMBERS_PLAN_DRIPS,
			SUREMEMBERS_PLAN_PRIORITY,
			SUREMEMBERS_USER_ROLES,
			SUREMEMBERS_ACCESS_GROUP_DOWNLOADS,
		];

		/**
		 * Filters the access group config meta keys.
		 *
		 * @since 1.4.1
		 *
		 * @param array<int, string> $keys Meta keys that constitute an access group's configuration.
		 */
		return apply_filters( 'suremembers_access_group_config_meta_keys', $keys );
	}

	/**
	 * Read the full, normalized configuration of an access group.
	 *
	 * @since 1.4.1
	 *
	 * @param int $group_id Access group (membership) post ID.
	 * @return array<string, mixed>|\WP_Error Normalized config, or WP_Error when the group is missing.
	 */
	public static function read( int $group_id ) {
		if ( ! self::exists( $group_id ) ) {
			return new \WP_Error(
				'suremembers_group_not_found',
				__( 'No membership found for the given ID.', 'suremembers-core' )
			);
		}

		$include    = get_post_meta( $group_id, SUREMEMBERS_PLAN_INCLUDE, true );
		$exclude    = get_post_meta( $group_id, SUREMEMBERS_PLAN_EXCLUDE, true );
		$restrict   = get_post_meta( $group_id, SUREMEMBERS_PLAN_RULES, true );
		$redirect   = get_post_meta( $group_id, SUREMEMBERS_RESTRICTED_URL, true );
		$expiration = get_post_meta( $group_id, SUREMEMBERS_PLAN_EXPIRATION, true );
		$drips      = get_post_meta( $group_id, SUREMEMBERS_PLAN_DRIPS, true );
		$downloads  = get_post_meta( $group_id, SUREMEMBERS_ACCESS_GROUP_DOWNLOADS, true );
		$roles      = get_post_meta( $group_id, SUREMEMBERS_USER_ROLES, true );
		$priority   = get_post_meta( $group_id, SUREMEMBERS_PLAN_PRIORITY, true );

		$config = [
			'id'                => $group_id,
			'title'             => get_the_title( $group_id ),
			'status'            => (string) get_post_status( $group_id ),
			'active_members'    => Access_Groups::get_users_count( $group_id ),
			'protected_content' => self::describe_content( is_array( $include ) && ! empty( $include['rules'] ) ? $include['rules'] : [] ),
			'excluded_content'  => self::describe_content( is_array( $exclude ) && ! empty( $exclude['rules'] ) ? $exclude['rules'] : [] ),
			'redirect'          => is_array( $redirect ) ? $redirect : [],
			'expiration'        => is_array( $expiration ) ? $expiration : [],
			'drip'              => is_array( $drips ) ? $drips : [],
			'downloads'         => is_string( $downloads ) && '' !== $downloads ? array_values( array_filter( array_map( 'trim', explode( ',', $downloads ) ) ) ) : [],
			'user_roles'        => is_array( $roles ) ? array_values( $roles ) : [],
			'priority'          => '' !== $priority ? (int) $priority : null,
			'display_behavior'  => is_array( $restrict ) && isset( $restrict['restrict'] ) ? $restrict['restrict'] : [],
			'has_restrictions'  => is_array( $include ) && ! empty( $include['rules'] ),
		];

		/**
		 * Filters the normalized access group configuration.
		 *
		 * Lets Pro features append their own config sections (e.g. variation,
		 * corporate/team, native payments) to the read payload.
		 *
		 * @since 1.4.1
		 *
		 * @param array<string, mixed> $config   Normalized config.
		 * @param int                  $group_id Access group post ID.
		 */
		return apply_filters( 'suremembers_access_group_config_read', $config, $group_id );
	}

	/**
	 * Update an access group's title and/or status.
	 *
	 * Only the provided fields are changed; pass null to leave a field as-is.
	 *
	 * @since 1.4.1
	 *
	 * @param int         $group_id Access group post ID.
	 * @param string|null $title    New title, or null to keep the current one.
	 * @param string|null $status   New status (publish|draft|suremembers_archive), or null to keep.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function update_details( int $group_id, ?string $title = null, ?string $status = null ) {
		if ( ! self::exists( $group_id ) ) {
			return new \WP_Error(
				'suremembers_group_not_found',
				__( 'No membership found for the given ID.', 'suremembers-core' )
			);
		}

		$post_array = [ 'ID' => $group_id ];

		if ( null !== $title ) {
			$title = sanitize_text_field( $title );
			if ( '' === $title ) {
				return new \WP_Error(
					'suremembers_invalid_title',
					__( 'A membership title cannot be empty.', 'suremembers-core' )
				);
			}
			$post_array['post_title'] = $title;
		}

		if ( null !== $status ) {
			if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
				return new \WP_Error(
					'suremembers_invalid_status',
					sprintf(
						/* translators: %s: comma-separated list of allowed statuses. */
						__( 'Invalid status. Allowed values: %s', 'suremembers-core' ),
						implode( ', ', self::ALLOWED_STATUSES )
					)
				);
			}
			$post_array['post_status'] = $status;
		}

		if ( count( $post_array ) === 1 ) {
			return new \WP_Error(
				'suremembers_no_changes',
				__( 'Nothing to update: provide a title and/or status.', 'suremembers-core' )
			);
		}

		$result = wp_update_post( $post_array, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Duplicate an access group, copying all configuration meta.
	 *
	 * Member grants are user meta and are intentionally NOT copied — the clone
	 * starts with no members. Pro data is copied via the config meta-keys
	 * filter and the `suremembers_access_group_duplicated` action.
	 *
	 * @since 1.4.1
	 *
	 * @param int    $source_id Access group to clone.
	 * @param string $title     Title for the new group. Empty uses "<source> copy".
	 * @param string $status    Status for the new group (default 'draft').
	 * @return int|\WP_Error New access group ID, or WP_Error on failure.
	 */
	public static function duplicate( int $source_id, string $title = '', string $status = 'draft' ) {
		if ( ! self::exists( $source_id ) ) {
			return new \WP_Error(
				'suremembers_group_not_found',
				__( 'No membership found for the given source ID.', 'suremembers-core' )
			);
		}

		if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			$status = 'draft';
		}

		$title = sanitize_text_field( $title );
		if ( '' === $title ) {
			/* translators: %s: source membership title. */
			$title = sprintf( __( '%s copy', 'suremembers-core' ), get_the_title( $source_id ) );
		}

		$title = self::unique_title( $title );

		$new_id = wp_insert_post(
			[
				'post_type'    => SUREMEMBERS_POST_TYPE,
				'post_title'   => $title,
				'post_status'  => $status,
				'post_content' => '',
			],
			true
		);

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		$new_id = (int) $new_id;

		foreach ( self::config_meta_keys() as $meta_key ) {
			$value = get_post_meta( $source_id, $meta_key, true );
			if ( '' !== $value ) {
				update_post_meta( $new_id, $meta_key, $value );
			}
		}

		/**
		 * Fires after an access group has been duplicated.
		 *
		 * Lets Pro features copy any additional data keyed by group ID that
		 * isn't captured by the config meta keys.
		 *
		 * @since 1.4.1
		 *
		 * @param int $new_id    The newly created access group ID.
		 * @param int $source_id The access group that was cloned.
		 */
		do_action( 'suremembers_access_group_duplicated', $new_id, $source_id );

		return $new_id;
	}

	/**
	 * Add, remove, or replace protected content items on an access group.
	 *
	 * @since 1.4.1
	 *
	 * @param int                                     $group_id  Access group post ID.
	 * @param array<int, array<string, mixed>|string> $items     Content items — each an object
	 *                                                           { type, id, taxonomy?, scope? } or a raw identifier string.
	 * @param string                                  $operation One of 'add', 'remove', 'set'.
	 * @return array<string, mixed>|\WP_Error Updated protected-content summary, or WP_Error.
	 */
	public static function modify_protected_content( int $group_id, array $items, string $operation = 'add' ) {
		if ( ! self::exists( $group_id ) ) {
			return new \WP_Error(
				'suremembers_group_not_found',
				__( 'No membership found for the given ID.', 'suremembers-core' )
			);
		}

		if ( ! in_array( $operation, [ 'add', 'remove', 'set' ], true ) ) {
			return new \WP_Error(
				'suremembers_invalid_operation',
				__( 'Operation must be one of: add, remove, set.', 'suremembers-core' )
			);
		}

		$identifiers = [];
		foreach ( $items as $item ) {
			$identifier = self::build_identifier( $item );
			if ( is_wp_error( $identifier ) ) {
				return $identifier;
			}
			$identifiers[] = $identifier;
		}
		$identifiers = array_values( array_unique( $identifiers ) );

		$include = get_post_meta( $group_id, SUREMEMBERS_PLAN_INCLUDE, true );
		$include = is_array( $include ) ? $include : [];
		$rules   = isset( $include['rules'] ) && is_array( $include['rules'] ) ? array_values( $include['rules'] ) : [];
		// Specifics track the singular post/page subset the admin UI renders as chips.
		$specifics = isset( $include['specifics'] ) && is_array( $include['specifics'] ) ? array_values( $include['specifics'] ) : [];

		$post_identifiers = array_values(
			array_filter(
				$identifiers,
				static function ( $id ) {
					return strpos( $id, 'post-' ) === 0 || strpos( $id, 'postchild-' ) === 0;
				}
			)
		);

		switch ( $operation ) {
			case 'set':
				$rules     = $identifiers;
				$specifics = $post_identifiers;
				break;
			case 'remove':
				$rules     = array_values( array_diff( $rules, $identifiers ) );
				$specifics = array_values( array_diff( $specifics, $identifiers ) );
				break;
			case 'add':
			default:
				$rules     = array_values( array_unique( array_merge( $rules, $identifiers ) ) );
				$specifics = array_values( array_unique( array_merge( $specifics, $post_identifiers ) ) );
				break;
		}

		$include['rules']     = $rules;
		$include['specifics'] = $specifics;

		update_post_meta( $group_id, SUREMEMBERS_PLAN_INCLUDE, $include );

		return [
			'protected_content' => self::describe_content( $rules ),
			'total'             => count( $rules ),
		];
	}

	/**
	 * Build a SureMembers content identifier from a friendly item definition.
	 *
	 * Accepts either a raw identifier string (returned as-is after validation)
	 * or an array: { type: post|postchild|taxonomy, id: int, taxonomy?: string, scope?: single|archive }.
	 *
	 * @since 1.4.1
	 *
	 * @param array<string, mixed>|string $item Item definition or raw identifier.
	 * @return string|\WP_Error Identifier such as "post-42-|" or "tax-7-single-category".
	 */
	public static function build_identifier( $item ) {
		if ( is_string( $item ) ) {
			$item = trim( $item );
			if ( '' === $item ) {
				return new \WP_Error( 'suremembers_invalid_content', __( 'Empty content identifier.', 'suremembers-core' ) );
			}
			return $item;
		}

		if ( ! is_array( $item ) ) {
			return new \WP_Error( 'suremembers_invalid_content', __( 'A content item must be an object or identifier string.', 'suremembers-core' ) );
		}

		$type = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : 'post';
		$id   = isset( $item['id'] ) ? absint( $item['id'] ) : 0;

		if ( $id <= 0 ) {
			return new \WP_Error( 'suremembers_invalid_content', __( 'A content item requires a positive integer "id".', 'suremembers-core' ) );
		}

		switch ( $type ) {
			case 'postchild':
				return "postchild-{$id}-|";
			case 'taxonomy':
			case 'tax':
				$taxonomy = isset( $item['taxonomy'] ) ? sanitize_key( (string) $item['taxonomy'] ) : '';
				if ( '' === $taxonomy ) {
					return new \WP_Error( 'suremembers_invalid_content', __( 'A taxonomy content item requires a "taxonomy".', 'suremembers-core' ) );
				}
				$scope = isset( $item['scope'] ) && 'archive' === $item['scope'] ? 'archive' : 'single';
				return "tax-{$id}-{$scope}-{$taxonomy}";
			case 'post':
			default:
				return "post-{$id}-|";
		}
	}

	/**
	 * Resolve a list of content identifiers into human-readable items.
	 *
	 * Mirrors the admin table's include/exclude resolution so the shapes match
	 * what the UI shows.
	 *
	 * @since 1.4.1
	 *
	 * @param array<int, string> $identifiers Content identifiers.
	 * @return array<int, array<string, mixed>>
	 */
	public static function describe_content( array $identifiers ): array {
		$items     = [];
		$processed = [];

		foreach ( $identifiers as $identifier ) {
			if ( ! is_string( $identifier ) || '' === $identifier ) {
				continue;
			}

			if ( preg_match( '/(post(?:child)?)-(\d+)-\|?/', $identifier, $matches ) ) {
				$prefix  = $matches[1];
				$real_id = (int) $matches[2];
				$key     = $prefix . '-' . $real_id;
				if ( in_array( $key, $processed, true ) ) {
					continue;
				}
				$post = get_post( $real_id );
				if ( ! $post ) {
					continue;
				}
				$processed[] = $key;
				$items[]     = [
					'id'         => $post->ID,
					'title'      => 'postchild' === $prefix
						/* translators: %s: parent content title. */
						? sprintf( __( 'Child of %s', 'suremembers-core' ), get_the_title( $post ) )
						: get_the_title( $post ),
					'type'       => get_post_type( $post ),
					'identifier' => $identifier,
				];
			} elseif ( preg_match( '/^tax-(\d+)-(single|archive)-(.+)$/', $identifier, $matches ) ) {
				$term_id  = (int) $matches[1];
				$taxonomy = $matches[3];
				if ( in_array( $identifier, $processed, true ) ) {
					continue;
				}
				$term = get_term( $term_id, $taxonomy );
				if ( ! $term || is_wp_error( $term ) ) {
					continue;
				}
				$processed[] = $identifier;
				$items[]     = [
					'id'         => $term_id,
					/* translators: %s: taxonomy term name. */
					'title'      => sprintf( __( 'All singulars from %s', 'suremembers-core' ), $term->name ),
					'type'       => $taxonomy,
					'identifier' => $identifier,
				];
			} else {
				// Broad rule (e.g. basic-global, post|all) — surface it verbatim.
				$items[] = [
					'id'         => 0,
					'title'      => $identifier,
					'type'       => 'rule',
					'identifier' => $identifier,
				];
			}
		}

		return $items;
	}

	/**
	 * Produce a title that does not collide with an existing membership,
	 * appending " copy N" as needed (matching the admin save behavior).
	 *
	 * @since 1.4.1
	 *
	 * @param string $title Desired title.
	 * @return string Unique title.
	 */
	private static function unique_title( string $title ): string {
		$final  = $title;
		$number = 0;

		while ( self::title_exists( $final ) ) {
			++$number;
			$final = $title . ' copy ' . $number;
		}

		return $final;
	}

	/**
	 * Whether an access group with the exact given title already exists.
	 *
	 * @since 1.4.1
	 *
	 * @param string $title Title to check.
	 * @return bool
	 */
	private static function title_exists( string $title ): bool {
		$existing = get_posts(
			[
				'post_type'              => SUREMEMBERS_POST_TYPE,
				'post_status'            => [ 'publish', 'draft', SUREMEMBERS_ARCHIVE ],
				'title'                  => $title,
				'numberposts'            => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		return ! empty( $existing );
	}
}
