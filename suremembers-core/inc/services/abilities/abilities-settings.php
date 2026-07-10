<?php
/**
 * Abilities Settings.
 *
 * Single source of truth for the ability toggles. Values live in one option
 * (the `suremembers_abilities_settings` array) so the settings UI saves them
 * through the shared global-settings flow, while the ability permission
 * callbacks and the WP-CLI command read/write the same store — UI, CLI, and
 * runtime checks always agree.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Abilities_Settings class.
 *
 * @since 1.1.0
 */
class Abilities_Settings {
	/**
	 * Master toggle key. When false, no abilities are registered.
	 *
	 * @since 1.1.0
	 */
	public const OPTION_MASTER = 'suremembers_abilities_api';

	/**
	 * Edit-abilities gate option key (grant, revoke, create, update).
	 *
	 * @since 1.1.0
	 */
	public const OPTION_EDIT = 'suremembers_abilities_api_edit';

	/**
	 * Delete-abilities gate option key (irreversible removals).
	 *
	 * @since 1.1.0
	 */
	public const OPTION_DELETE = 'suremembers_abilities_api_delete';

	/**
	 * MCP server option key. When true (and the MCP Adapter plugin is active),
	 * a dedicated SureMembers MCP endpoint is registered.
	 *
	 * @since 1.1.0
	 */
	public const OPTION_MCP = 'suremembers_mcp_server';

	/**
	 * Get a single ability toggle as a boolean.
	 *
	 * @since 1.1.0
	 *
	 * @param string $key     Toggle key (one of the OPTION_* constants).
	 * @param bool   $default Default value when the toggle is unset.
	 * @return bool
	 */
	public static function get( string $key, bool $default = false ): bool {
		$group = self::get_group();
		return array_key_exists( $key, $group ) ? (bool) $group[ $key ] : $default;
	}

	/**
	 * Get all ability toggles as a normalized boolean array.
	 *
	 * @since 1.1.0
	 *
	 * @return array{suremembers_abilities_api: bool, suremembers_abilities_api_edit: bool, suremembers_abilities_api_delete: bool, suremembers_mcp_server: bool}
	 */
	public static function get_settings(): array {
		return [
			self::OPTION_MASTER => self::get( self::OPTION_MASTER, true ),
			self::OPTION_EDIT   => self::get( self::OPTION_EDIT, false ),
			self::OPTION_DELETE => self::get( self::OPTION_DELETE, false ),
			self::OPTION_MCP    => self::get( self::OPTION_MCP, false ),
		];
	}

	/**
	 * Persist the ability toggles.
	 *
	 * Merges into the existing group so callers (e.g. the CLI) can update a
	 * single flag without clobbering the others. Writes to the same option the
	 * settings UI saves through, keeping every consumer in sync.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, bool> $settings Map of toggle key => boolean value.
	 * @return void
	 */
	public static function save_settings( array $settings ): void {
		$group = self::get_group();

		foreach ( [ self::OPTION_MASTER, self::OPTION_EDIT, self::OPTION_DELETE, self::OPTION_MCP ] as $key ) {
			if ( array_key_exists( $key, $settings ) ) {
				$group[ $key ] = ! empty( $settings[ $key ] );
			}
		}

		update_option( SUREMEMBERS_ABILITIES_SETTINGS, $group );
	}

	/**
	 * Get the raw toggle array from the option store.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	private static function get_group(): array {
		$group = get_option( SUREMEMBERS_ABILITIES_SETTINGS, [] );
		return is_array( $group ) ? $group : [];
	}
}
