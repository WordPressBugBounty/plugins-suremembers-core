<?php
/**
 * MCP Module.
 *
 * Manages the WordPress Abilities / MCP (Model Context Protocol) settings for
 * SureMembers — the REST endpoint the admin UI reads/writes, and registration
 * of a dedicated MCP server with the MCP Adapter plugin when enabled.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Modules\MCP;

use SureMembersCore\Inc\Services\Abilities\Abilities_Settings;
use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Module class.
 *
 * @since 1.1.0
 */
class Module {
	use Get_Instance;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// Register MCP server with the MCP Adapter plugin when enabled.
		if ( self::mcp_adapter_enabled() ) {
			add_action( 'mcp_adapter_init', [ $this, 'register_mcp_server' ] );
		}
	}

	/**
	 * Check if the MCP Adapter is available and the MCP server toggle is on.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public static function mcp_adapter_enabled(): bool {
		return function_exists( 'wp_register_ability' )
			&& class_exists( 'WP\MCP\Plugin' )
			&& Abilities_Settings::get( Abilities_Settings::OPTION_MCP, false );
	}

	/**
	 * Get the MCP Adapter plugin installation status.
	 *
	 * @since 1.1.0
	 *
	 * @return string 'active', 'installed', or 'not_installed'.
	 */
	public static function get_adapter_status(): string {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_file = 'mcp-adapter/mcp-adapter.php';

		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {
			return 'not_installed';
		}

		return is_plugin_active( $plugin_file ) ? 'active' : 'installed';
	}

	/**
	 * Get the current ability/MCP settings.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, bool>
	 */
	public static function get_settings(): array {
		return Abilities_Settings::get_settings();
	}

	/**
	 * Persist the ability/MCP settings.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $settings Settings to save.
	 * @return void
	 */
	public static function save_settings( array $settings ): void {
		Abilities_Settings::save_settings(
			[
				Abilities_Settings::OPTION_MASTER => ! empty( $settings[ Abilities_Settings::OPTION_MASTER ] ),
				Abilities_Settings::OPTION_EDIT   => ! empty( $settings[ Abilities_Settings::OPTION_EDIT ] ),
				Abilities_Settings::OPTION_DELETE => ! empty( $settings[ Abilities_Settings::OPTION_DELETE ] ),
				Abilities_Settings::OPTION_MCP    => ! empty( $settings[ Abilities_Settings::OPTION_MCP ] ),
			]
		);
	}

	/**
	 * Register REST routes for the ability/MCP settings.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'suremembers/v1',
			'/mcp-settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings_endpoint' ],
					'permission_callback' => [ $this, 'admin_permission_check' ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'save_settings_endpoint' ],
					'permission_callback' => [ $this, 'admin_permission_check' ],
				],
			]
		);
	}

	/**
	 * Permission check — require manage_options.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function admin_permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET endpoint — return the ability/MCP settings plus adapter status.
	 *
	 * @since 1.1.0
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings_endpoint(): \WP_REST_Response {
		$data                       = self::get_settings();
		$data['mcp_adapter_status'] = self::get_adapter_status();

		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			200
		);
	}

	/**
	 * POST endpoint — save the ability/MCP settings.
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function save_settings_endpoint( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = $request->get_json_params();

		if ( ! is_array( $settings ) ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => __( 'Invalid settings data.', 'suremembers-core' ),
				],
				400
			);
		}

		self::save_settings( $settings );

		$data                       = self::get_settings();
		$data['mcp_adapter_status'] = self::get_adapter_status();

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'AI settings saved successfully.', 'suremembers-core' ),
				'data'    => $data,
			],
			200
		);
	}

	/**
	 * Register the SureMembers MCP server with the MCP Adapter plugin.
	 *
	 * Hooked to 'mcp_adapter_init'. Collects all suremembers/* abilities and
	 * exposes them through an HTTP-based MCP server endpoint.
	 *
	 * @since 1.1.0
	 *
	 * @param object $adapter The MCP Adapter instance.
	 * @return void
	 */
	public function register_mcp_server( $adapter ): void {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return;
		}

		$abilities = wp_get_abilities();
		$tools     = [];

		foreach ( $abilities as $ability ) {
			if ( str_starts_with( $ability->get_name(), 'suremembers/' ) ) {
				$tools[] = $ability->get_name();
			}
		}

		if ( empty( $tools ) ) {
			return;
		}

		$transport_class = class_exists( '\WP\MCP\Transport\HttpTransport' )
			? 'WP\MCP\Transport\HttpTransport'
			: 'WP\MCP\Transport\Http\RestTransport';

		// @phpstan-ignore-next-line — $adapter is provided by the MCP Adapter plugin at runtime.
		$adapter->create_server(
			'suremembers',
			'suremembers/v1',
			'mcp',
			__( 'SureMembers MCP Server', 'suremembers-core' ),
			__( 'SureMembers MCP Server for membership management — analytics, access groups, member queries, and grants.', 'suremembers-core' ),
			defined( 'SUREMEMBERS_CORE_VER' ) ? SUREMEMBERS_CORE_VER : '1.0.0',
			[ $transport_class ],
			'WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler',
			'WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler',
			$tools,
			[],
			[]
		);
	}
}
