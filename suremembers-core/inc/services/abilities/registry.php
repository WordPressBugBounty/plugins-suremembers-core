<?php
/**
 * Abilities Registry.
 *
 * Central registry for all available abilities. Provides registration,
 * lookup, and WordPress Abilities API integration.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Registry class.
 *
 * @since 1.1.0
 */
class Registry {
	use Get_Instance;

	/**
	 * Registered abilities, keyed by ability ID.
	 *
	 * @var array<string, Ability>
	 */
	private $abilities = [];

	/**
	 * Whether built-in abilities have been registered.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'maybe_init' ], 5 );

		// WordPress Abilities API integration.
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_wp_ability_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_wp_abilities' ] );
	}

	/**
	 * Initialize abilities if not already done.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function maybe_init(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;
		$this->register_built_in_abilities();

		/**
		 * Fires after built-in abilities are registered.
		 *
		 * Use this action to register custom abilities from SureMembers Pro
		 * or third-party plugins via Registry::register().
		 *
		 * @since 1.1.0
		 *
		 * @param Registry $registry The abilities registry instance.
		 */
		do_action( 'suremembers_register_abilities', $this );
	}

	/**
	 * Register a single ability.
	 *
	 * @since 1.1.0
	 *
	 * @param Ability $ability The ability to register.
	 * @return void
	 */
	public function register( Ability $ability ): void {
		$this->abilities[ $ability->get_id() ] = $ability;
	}

	/**
	 * Get an ability by ID.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id The ability ID.
	 * @return Ability|null
	 */
	public function get( string $id ): ?Ability {
		return $this->abilities[ $id ] ?? null;
	}

	/**
	 * Get all registered abilities.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, Ability>
	 */
	public function get_all(): array {
		return $this->abilities;
	}

	/**
	 * Check if an ability is registered.
	 *
	 * @since 1.1.0
	 *
	 * @param string $id The ability ID.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->abilities[ $id ] );
	}

	/**
	 * Register the SureMembers ability category with WordPress Abilities API.
	 *
	 * Hooked to 'wp_abilities_api_categories_init'.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register_wp_ability_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'suremembers',
			[
				'label'       => __( 'SureMembers', 'suremembers-core' ),
				'description' => __( 'Membership management abilities — analytics, access groups, member queries, and grants.', 'suremembers-core' ),
			]
		);
	}

	/**
	 * Register all SureMembers abilities with WordPress Abilities API.
	 *
	 * Hooked to 'wp_abilities_api_init'.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register_wp_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// Master toggle — on by default; don't register any abilities when explicitly disabled.
		if ( ! Abilities_Settings::get( Abilities_Settings::OPTION_MASTER, true ) ) {
			return;
		}

		// Ensure our internal registry is initialized first.
		$this->maybe_init();

		foreach ( $this->abilities as $ability ) {
			// Skip abilities disabled by per-ability gate (edit/delete toggles).
			if ( ! $ability->is_enabled() ) {
				continue;
			}

			$args = [
				'label'               => $ability->get_label(),
				'description'         => $ability->get_wp_description(),
				'category'            => 'suremembers',
				'input_schema'        => $ability->get_input_schema(),
				'execute_callback'    => [ $ability, 'handle_execute' ],
				'permission_callback' => [ $ability, 'check_permission' ],
			];

			$meta = [
				'show_in_rest' => true,
				'mcp'          => [
					'public' => true,
					'type'   => 'tool',
				],
			];

			$annotations = $ability->get_annotations();
			if ( ! empty( $annotations ) ) {
				// Map MCP annotation keys to WP Abilities API keys.
				$wp_annotations = [];
				$key_map        = [
					'readOnlyHint'    => 'readonly',
					'destructiveHint' => 'destructive',
					'idempotentHint'  => 'idempotent',
				];

				foreach ( $annotations as $key => $value ) {
					$wp_annotations[ $key_map[ $key ] ?? $key ] = $value;
				}

				$meta['annotations'] = $wp_annotations;
			}

			$args['meta'] = $meta;

			wp_register_ability( $ability->get_wp_ability_name(), $args );
		}
	}

	/**
	 * Register all built-in (Core) abilities.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	private function register_built_in_abilities(): void {
		$abilities = [
			// Analytics & queries (read-only, free).
			new Handlers\Get_Membership_Overview(),
			new Handlers\List_Memberships(),
			new Handlers\Get_Membership_Stats(),
			new Handlers\List_Members(),
			new Handlers\Get_Member_Details(),
			new Handlers\Get_Members_By_Status(),

			// Membership mutations (gated).
			new Handlers\Grant_Membership(),
			new Handlers\Revoke_Membership(),
			new Handlers\Update_Membership_Expiration(),
			new Handlers\Create_Membership(),
			new Handlers\Delete_Membership(),
		];

		// Note: the Pro-only analytics abilities (find-expiring-memberships,
		// get-integration-breakdown) live in SureMembers Pro and register
		// themselves via the `suremembers_register_abilities` hook below.

		foreach ( $abilities as $ability ) {
			$this->register( $ability );
		}
	}
}
