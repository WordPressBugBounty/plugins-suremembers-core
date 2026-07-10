<?php
/**
 * Router class.
 *
 * @package SureMembers\Inc\Services
 */

namespace SureMembersCore\Inc\Services;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Router class.
 */
class Router extends \WP_REST_Controller {
	use Get_Instance;

	/**
	 * Namespace for the API.
	 *
	 * @var non-falsy-string
	 */
	protected $namespace = 'suremembers/v1';

	/**
	 * Routes.
	 *
	 * @var array<mixed> $routes routes.
	 */
	protected $routes = [];

	/**
	 * Dynamic method handler for HTTP methods.
	 *
	 * @param string       $name name.
	 * @param array<mixed> $arguments arguments.
	 * @return void
	 * @throws \BadMethodCallException If HTTP method is not supported.
	 * @throws \InvalidArgumentException If a valid callback is not provided.
	 */
	public static function __callStatic( $name, $arguments ) {
		$instance = static::instance(); // @phpstan-ignore-line
		$method   = strtoupper( $name );

		if ( ! in_array( $method, [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH' ], true ) ) {
			throw new \BadMethodCallException( "HTTP method {$name} is not supported." );
		}

		$endpoint            = $arguments[0] ?? '/';
		$callback            = $arguments[1] ?? null;
		$permission_callback = $arguments[2] ?? null;
		$args                = $arguments[3] ?? [];

		if ( is_null( $callback ) ) {
			throw new \InvalidArgumentException( 'A valid callback is required for the route.' );
		}

		$instance->addRoute( $method, $endpoint, $callback, $permission_callback, $args );
	}

	/**
	 * Register a REST route.
	 *
	 * @param string                $method HTTP method (GET, POST, etc.).
	 * @param string                $endpoint Endpoint URL (e.g., '/example').
	 * @param callable|array<mixed> $callback Callback function or array (Controller::method).
	 * @param callable|null         $permission_callback Custom permission callback.
	 * @param array<mixed>          $args Argument schema for validation.
	 */
	public function addRoute( $method, $endpoint, $callback, $permission_callback = null, $args = [] ) {

		switch ( $permission_callback ) {
			case 'user':
				$permission_callback = [ $this, 'user_permission_callback' ];
				break;
			case 'admin':
				$permission_callback = [ $this, 'admin_permission_callback' ];
				break;
			default:
				$permission_callback = [ $this, 'admin_permission_callback' ];
		}

		$this->routes[] = [
			'methods'             => strtoupper( $method ),
			'endpoint'            => $endpoint,
			'callback'            => $callback,
			'permission_callback' => $permission_callback,
			'args'                => $args,
		];
	}

	/**
	 * Default permission callback.
	 *
	 * @return bool
	 */
	public function default_permission_callback() {
		return true;
	}

	/**
	 * Admin permission callback.
	 *
	 * @return bool
	 */
	public function admin_permission_callback() {
		// Check if user has manage_options capability.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * User permission callback.
	 *
	 * @return bool
	 */
	public function user_permission_callback() {

		if ( is_user_logged_in() ) {
			return true;
		}
		return false;
	}

	/**
	 * Allow permission callback.
	 *
	 * @return bool
	 */
	public function allowPermission() {
		return true;
	}

	/**
	 * Register all defined routes with WordPress.
	 */
	public function registerRoutes() {

		foreach ( $this->routes as $route ) {
			register_rest_route(
				$this->namespace,
				$route['endpoint'],
				[
					'methods'             => $route['methods'],
					'callback'            => $route['callback'],
					'permission_callback' => $route['permission_callback'],
					'args'                => $route['args'],
				]
			);
		}
	}

	/**
	 * Standardized success response.
	 *
	 * @param array<mixed> $data data.
	 * @param int          $status status.
	 * @return \WP_REST_Response
	 */
	public static function success( $data, $status = 200 ) {
		return new \WP_REST_Response(
			[
				'success' => true,
				'data'    => $data,
			],
			$status
		);
	}

	/**
	 * Standardized error response.
	 *
	 * @param string $message message.
	 * @param int    $status status.
	 * @return \WP_REST_Response
	 */
	public static function error( $message, $status = 400 ) {
		return new \WP_REST_Response(
			[
				'success' => false,
				'message' => $message,
			],
			$status
		);
	}
}
