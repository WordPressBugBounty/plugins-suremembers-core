<?php
/**
 * Abstract Ability Base Class.
 *
 * Provides the schema and execution interface for AI-consumable abilities.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Services\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Ability class.
 *
 * Each ability wraps an existing SureMembers operation with a self-documenting
 * schema that AI agents can discover and invoke.
 *
 * @since 1.1.0
 */
abstract class Ability {
	/**
	 * Option gate key.
	 *
	 * When non-empty, the ability is disabled if the option is explicitly
	 * false/0. Read abilities leave this empty (always available once the
	 * master toggle is on); edit/delete abilities set it to the relevant gate.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	protected string $gated = '';

	/**
	 * Get the unique identifier for this ability.
	 *
	 * @since 1.1.0
	 *
	 * @return string Kebab-case ID (e.g., 'get-membership-overview').
	 */
	abstract public function get_id(): string;

	/**
	 * Get the human-readable name.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Get a detailed description of what this ability does.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	abstract public function get_description(): string;

	/**
	 * Get the category this ability belongs to.
	 *
	 * @since 1.1.0
	 *
	 * @return string One of: analytics, memberships, members.
	 */
	abstract public function get_category(): string;

	/**
	 * Get the parameter schema for this ability.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, array<string, mixed>> Associative array of parameter definitions.
	 */
	abstract public function get_parameters(): array;

	/**
	 * Execute the ability with the given parameters.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Validated parameters.
	 * @return array<string, mixed> Result array with 'success' and 'data' keys.
	 */
	abstract public function execute( array $params ): array;

	/**
	 * Get the permission level required.
	 *
	 * @since 1.1.0
	 *
	 * @return string Default 'admin'.
	 */
	public function get_permission(): string {
		return 'admin';
	}

	/**
	 * Check if this ability is enabled based on its gate option.
	 *
	 * If the ability has a $gated option key set, it checks that option.
	 * Read abilities default to enabled — only gated off when an admin
	 * explicitly disables the edit/delete groups.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		if ( ! empty( $this->gated ) && ! Abilities_Settings::get( $this->gated, false ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the return value schema.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_returns(): array {
		return [];
	}

	/**
	 * Get MCP tool annotations describing behavioral characteristics.
	 *
	 * Keys use MCP protocol naming: readOnlyHint, destructiveHint, idempotentHint.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, bool>
	 */
	public function get_annotations(): array {
		return [];
	}

	/**
	 * Get plain-text instructions for AI agents.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function get_instructions(): string {
		return '';
	}

	/**
	 * Get the label for WordPress Abilities API.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->get_name();
	}

	/**
	 * Get the namespaced ability name for WordPress Abilities API.
	 *
	 * Format: suremembers/{ability-id}, forced lowercase to satisfy the WP
	 * Abilities API contract regardless of how get_id() is formatted.
	 *
	 * @since 1.1.0
	 *
	 * @return lowercase-string&non-falsy-string
	 */
	public function get_wp_ability_name(): string {
		return strtolower( 'suremembers/' . $this->get_id() );
	}

	/**
	 * Get the description for WordPress Abilities API registration.
	 *
	 * Concatenates the base description with instructions (if any) so AI
	 * agents see workflow guidance in the tool description.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	public function get_wp_description(): string {
		$description  = $this->get_description();
		$instructions = $this->get_instructions();

		if ( ! empty( $instructions ) ) {
			$description .= ' ' . $instructions;
		}

		return $description;
	}

	/**
	 * Convert the parameter schema to JSON Schema format for WP Abilities API.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed> JSON Schema compatible input schema.
	 */
	public function get_input_schema(): array {
		$parameters = $this->get_parameters();

		if ( empty( $parameters ) ) {
			return [
				'type'                 => 'object',
				'properties'           => new \stdClass(),
				'additionalProperties' => false,
				'default'              => [],
			];
		}

		$properties = [];
		$required   = [];

		foreach ( $parameters as $key => $definition ) {
			$property = [
				'description' => $definition['description'] ?? '',
			];

			// Map our types to JSON Schema types.
			$type = $definition['type'] ?? 'string';
			switch ( $type ) {
				case 'integer':
					$property['type'] = 'integer';
					break;
				case 'boolean':
					$property['type'] = 'boolean';
					break;
				case 'array':
					$property['type'] = 'array';
					break;
				case 'object':
					$property['type'] = 'object';
					break;
				default:
					$property['type'] = 'string';
					break;
			}

			if ( isset( $definition['enum'] ) ) {
				$property['enum'] = $definition['enum'];
			}

			if ( isset( $definition['default'] ) ) {
				$property['default'] = $definition['default'];
			}

			$properties[ $key ] = $property;

			if ( ! empty( $definition['required'] ) ) {
				$required[] = $key;
			}
		}

		$schema = [
			'type'                 => 'object',
			'properties'           => $properties,
			'additionalProperties' => false,
			'default'              => [],
		];

		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}

		return $schema;
	}

	/**
	 * Execute callback for WordPress Abilities API.
	 *
	 * Receives the input array from the WP Abilities API, validates, applies
	 * defaults, and delegates to the concrete execute() method.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $input Input from WP Abilities API.
	 * @return array<string, mixed> Result array.
	 */
	public function handle_execute( array $input ): array {
		$params = $this->apply_defaults( $input );

		$validation = $this->validate( $params );

		if ( is_wp_error( $validation ) ) {
			return [
				'success' => false,
				'message' => $validation->get_error_message(),
				'errors'  => $validation->get_error_data()['errors'] ?? [],
			];
		}

		return $this->execute( $params );
	}

	/**
	 * Permission callback for WordPress Abilities API.
	 *
	 * Checks: master toggle -> per-ability gate -> user capability.
	 *
	 * @since 1.1.0
	 *
	 * @return bool Whether the current user can execute this ability.
	 */
	public function check_permission(): bool {
		// Master toggle — on by default; all abilities off only when explicitly disabled.
		if ( ! Abilities_Settings::get( Abilities_Settings::OPTION_MASTER, true ) ) {
			return false;
		}

		// Per-ability gate (edit/delete toggles).
		if ( ! $this->is_enabled() ) {
			return false;
		}

		return current_user_can( 'manage_options' );
	}

	/**
	 * Validate parameters against the schema.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Parameters to validate.
	 * @return true|\WP_Error True if valid, WP_Error with details if not.
	 */
	public function validate( array $params ) {
		$schema = $this->get_parameters();
		$errors = [];

		foreach ( $schema as $key => $definition ) {
			$required = $definition['required'] ?? false;
			$type     = $definition['type'] ?? 'string';

			// Check required.
			if ( $required && ! isset( $params[ $key ] ) ) {
				$errors[] = sprintf(
					/* translators: %s: parameter name */
					__( 'Missing required parameter: %s', 'suremembers-core' ),
					$key
				);
				continue;
			}

			if ( ! isset( $params[ $key ] ) ) {
				continue;
			}

			$value = $params[ $key ];

			// Type checking.
			if ( ! $this->check_type( $value, $type ) ) {
				$errors[] = sprintf(
					/* translators: 1: parameter name, 2: expected type, 3: actual type */
					__( 'Parameter "%1$s" must be of type %2$s, got %3$s', 'suremembers-core' ),
					$key,
					$type,
					gettype( $value )
				);
			}

			// Enum checking.
			if ( ! empty( $definition['enum'] ) && ! in_array( $value, $definition['enum'], true ) ) {
				$errors[] = sprintf(
					/* translators: 1: parameter name, 2: allowed values */
					__( 'Parameter "%1$s" must be one of: %2$s', 'suremembers-core' ),
					$key,
					implode( ', ', $definition['enum'] )
				);
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'invalid_params',
				__( 'Parameter validation failed', 'suremembers-core' ),
				[
					'status' => 400,
					'errors' => $errors,
				]
			);
		}

		return true;
	}

	/**
	 * Apply defaults to parameters.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Raw parameters.
	 * @return array<string, mixed> Parameters with defaults applied.
	 */
	public function apply_defaults( array $params ): array {
		$schema = $this->get_parameters();

		foreach ( $schema as $key => $definition ) {
			if ( ! isset( $params[ $key ] ) && isset( $definition['default'] ) ) {
				$params[ $key ] = $definition['default'];
			}
		}

		return $params;
	}

	/**
	 * Check if a value matches the expected type.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed  $value The value to check.
	 * @param string $type  Expected type (string, integer, boolean, array, object).
	 * @return bool
	 */
	protected function check_type( $value, string $type ): bool {
		switch ( $type ) {
			case 'string':
				return is_string( $value ) || is_numeric( $value );
			case 'integer':
				return is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) );
			case 'boolean':
				return is_bool( $value ) || in_array( $value, [ 0, 1, '0', '1', 'true', 'false' ], true );
			case 'array':
				return is_array( $value );
			case 'object':
				return is_array( $value ) || is_object( $value );
			default:
				return true;
		}
	}

	/**
	 * Cast a parameter value to its declared type.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed  $value The value to cast.
	 * @param string $type  Target type.
	 * @return mixed
	 */
	protected function cast_type( $value, string $type ) {
		switch ( $type ) {
			case 'integer':
				return intval( $value );
			case 'boolean':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			case 'string':
				return strval( $value );
			default:
				return $value;
		}
	}

	/**
	 * Invoke a REST router method and return its response payload.
	 *
	 * SureMembers routers return a WP_REST_Response directly, so we build a
	 * nonce-bearing request, call the handler, and unwrap the data array.
	 *
	 * @since 1.1.0
	 *
	 * @param callable             $callback  Router method, e.g. [ Users::get_instance(), 'get_users_data' ].
	 * @param array<string, mixed> $params    Request parameters.
	 * @param string               $method    HTTP method. Default 'POST'.
	 * @return array<string, mixed> Decoded response data.
	 */
	protected function call_rest_handler( callable $callback, array $params = [], string $method = 'POST' ): array {
		$request = $this->build_request( $params, $method );

		$response = call_user_func( $callback, $request );

		if ( $response instanceof \WP_REST_Response ) {
			$data = $response->get_data();
			return is_array( $data ) ? $data : [];
		}

		if ( is_array( $response ) ) {
			return $response;
		}

		return [
			'success' => false,
			'data'    => [ 'message' => __( 'Unexpected response from handler.', 'suremembers-core' ) ],
		];
	}

	/**
	 * Build a WP_REST_Request with a valid REST nonce and parameters.
	 *
	 * @since 1.1.0
	 *
	 * @param array<string, mixed> $params Parameters to set on the request.
	 * @param string               $method HTTP method. Default 'POST'.
	 * @return \WP_REST_Request
	 */
	protected function build_request( array $params = [], string $method = 'POST' ): \WP_REST_Request {
		$request = new \WP_REST_Request( $method );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}
}
