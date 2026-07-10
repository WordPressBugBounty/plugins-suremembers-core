<?php
/**
 * WP-CLI command to manage SureMembers AI abilities.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\CLI;

use SureMembersCore\Inc\Services\Abilities\Abilities_Settings;
use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Manage SureMembers AI abilities from the command line.
 *
 * Writes go through Abilities_Settings so the same options the ability
 * permission callbacks read are updated.
 *
 * @since 1.1.0
 */
class Abilities_Command {
	/**
	 * Enable SureMembers abilities for AI agents.
	 *
	 * Turns on the master "Enable Abilities" toggle. By default only read
	 * abilities become available — pass --with-edit and/or --with-delete to
	 * also enable the mutating ability groups. Flags you don't pass keep
	 * their existing value.
	 *
	 * ## OPTIONS
	 *
	 * [--with-edit]
	 * : Also enable edit abilities (grant, revoke, create, update expiration).
	 *
	 * [--with-delete]
	 * : Also enable delete abilities (irreversible removals).
	 *
	 * ## EXAMPLES
	 *
	 *     # Enable read-only abilities.
	 *     $ wp suremembers abilities enable
	 *
	 *     # Enable read + edit abilities.
	 *     $ wp suremembers abilities enable --with-edit
	 *
	 *     # Enable everything.
	 *     $ wp suremembers abilities enable --with-edit --with-delete
	 *
	 * @when after_wp_load
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Associative flags.
	 * @return void
	 */
	public function enable( array $args, array $assoc_args ): void {
		unset( $args );

		$with_edit   = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'with-edit', false );
		$with_delete = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'with-delete', false );

		$settings = [ Abilities_Settings::OPTION_MASTER => true ];

		if ( $with_edit ) {
			$settings[ Abilities_Settings::OPTION_EDIT ] = true;
		}

		if ( $with_delete ) {
			$settings[ Abilities_Settings::OPTION_DELETE ] = true;
		}

		Abilities_Settings::save_settings( $settings );

		$this->print_status();
		WP_CLI::success( 'SureMembers abilities enabled.' );
	}

	/**
	 * Disable all SureMembers abilities.
	 *
	 * Turns off the master toggle. The edit/delete gate values are preserved
	 * so re-enabling restores the previous configuration.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp suremembers abilities disable
	 *
	 * @when after_wp_load
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Associative flags (unused).
	 * @return void
	 */
	public function disable( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		Abilities_Settings::save_settings( [ Abilities_Settings::OPTION_MASTER => false ] );

		$this->print_status();
		WP_CLI::success( 'SureMembers abilities disabled.' );
	}

	/**
	 * Show the current state of the SureMembers ability toggles.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp suremembers abilities status
	 *
	 * @when after_wp_load
	 *
	 * @since 1.1.0
	 *
	 * @param array<int, string>         $args       Positional arguments (unused).
	 * @param array<string, string|bool> $assoc_args Associative flags (unused).
	 * @return void
	 */
	public function status( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		$this->print_status();
	}

	/**
	 * Print the three toggle values as a human-readable list.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function print_status(): void {
		$settings = Abilities_Settings::get_settings();

		WP_CLI::log( sprintf( '  Enable Abilities:        %s', $this->yes_no( $settings[ Abilities_Settings::OPTION_MASTER ] ) ) );
		WP_CLI::log( sprintf( '  Enable Edit Abilities:   %s', $this->yes_no( $settings[ Abilities_Settings::OPTION_EDIT ] ) ) );
		WP_CLI::log( sprintf( '  Enable Delete Abilities: %s', $this->yes_no( $settings[ Abilities_Settings::OPTION_DELETE ] ) ) );
	}

	/**
	 * Format a boolean as a human-readable yes/no for log output.
	 *
	 * @since 1.1.0
	 *
	 * @param bool $value Boolean to format.
	 * @return string
	 */
	private function yes_no( bool $value ): string {
		return $value ? 'yes' : 'no';
	}
}
