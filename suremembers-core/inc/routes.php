<?php
/**
 * Define the REST API routes.
 *
 * @package SureMembers
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Routers\Analytics as AnalyticsRoute;
use SureMembersCore\Inc\Routers\Members as MembersRoute;
use SureMembersCore\Inc\Routers\Settings as SettingsRoute;
use SureMembersCore\Inc\Routers\Users as UsersRoute;
use SureMembersCore\Inc\Services\Router;
use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Class Routes.
 */
class Routes {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->initialize_actions();

		add_action(
			'rest_api_init',
			static function () {
				if ( method_exists( Router::get_instance(), 'registerRoutes' ) ) {
					Router::get_instance()->registerRoutes();
				}
			}
		);
	}

	/**
	 * Init Hooks.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function initialize_actions() {
		$this->register_rest_routes();
	}

	/**
	 * Return the rest response.
	 *
	 * @param mixed $response The response.
	 * @param int   $status The status code.
	 * @return \WP_Error|\WP_REST_Response
	 */
	public static function rest_response( $response, $status = 200 ) {
		if ( empty( $response ) ) {
			return new \WP_Error( 'no_data_found', __( 'Oops! Something wrong here...', 'suremembers-core' ), [ 'status' => 404 ] );
		}

		$response = rest_ensure_response( $response );

		// Only call set_status if the response is a WP_REST_Response instance.
		if ( $response instanceof \WP_REST_Response ) {
			$response->set_status( $status );
		}

		return $response;
	}

	/**
	 * Get SureMembers routes.
	 *
	 * @return array<string, array<string, array<int, callable>>>
	 */
	public function get_suremembers_routes() {
		return apply_filters(
			'suremembers_rest_routes',
			[
				// Members/Admin endpoints.
				'fetch-posts'                  => [
					'method'              => 'POST',
					'callback'            => [ MembersRoute::get_instance(), 'fetch_posts' ],
					'permission_callback' => 'admin',
				],
				'fetch-adminbar-groups'        => [
					'method'              => 'POST',
					'callback'            => [ MembersRoute::get_instance(), 'fetch_adminbar_groups' ],
					'permission_callback' => 'admin',
				],
				'search-posts'                 => [
					'method'              => 'POST',
					'callback'            => [ MembersRoute::get_instance(), 'search_posts_by_query' ],
					'permission_callback' => 'admin',
				],
				'update-status'                => [
					'method'              => 'POST',
					'callback'            => [ MembersRoute::get_instance(), 'update_access_group_status' ],
					'permission_callback' => 'admin',
				],
				'submit-form'                  => [
					'method'              => 'POST',
					'callback'            => [ MembersRoute::get_instance(), 'submit_form' ],
					'permission_callback' => 'admin',
				],
				// save-downloads route is registered by SureMembers Pro.
				'get-table-data'               => [
					'method'              => 'POST',
					'callback'            => [ MembersRoute::get_instance(), 'get_table_data' ],
					'permission_callback' => 'admin',
				],
				'get-post-data'                => [
					'method'              => 'POST',
					'callback'            => [ MembersRoute::get_instance(), 'get_post_data' ],
					'permission_callback' => 'admin',
				],
				'get-analytics'                => [
					'method'              => 'POST',
					'callback'            => [ AnalyticsRoute::get_instance(), 'get_analytics' ],
					'permission_callback' => 'admin',
				],
				'get-recent-activity'          => [
					'method'              => 'POST',
					'callback'            => [ AnalyticsRoute::get_instance(), 'get_recent_activity' ],
					'permission_callback' => 'admin',
				],
				'get-expiring-memberships'     => [
					'method'              => 'POST',
					'callback'            => [ AnalyticsRoute::get_instance(), 'get_expiring_memberships' ],
					'permission_callback' => 'admin',
				],
				'delete-membership'            => [
					'method'              => 'POST',
					'callback'            => [ MembersRoute::get_instance(), 'delete_membership' ],
					'permission_callback' => 'admin',
				],
				// Users endpoints.
				'get-users-data'               => [
					'method'              => 'POST',
					'callback'            => [ UsersRoute::get_instance(), 'get_users_data' ],
					'permission_callback' => 'admin',
				],
				'get-all-memberships'          => [
					'method'              => 'POST',
					'callback'            => [ UsersRoute::get_instance(), 'get_all_memberships' ],
					'permission_callback' => 'admin',
				],
				'get-user-details'             => [
					'method'              => 'POST',
					'callback'            => [ UsersRoute::get_instance(), 'get_user_details' ],
					'permission_callback' => 'admin',
				],
				'grant-membership'             => [
					'method'              => 'POST',
					'callback'            => [ UsersRoute::get_instance(), 'grant_membership' ],
					'permission_callback' => 'admin',
				],
				'revoke-membership'            => [
					'method'              => 'POST',
					'callback'            => [ UsersRoute::get_instance(), 'revoke_membership' ],
					'permission_callback' => 'admin',
				],
				'update-membership-expiration' => [
					'method'              => 'POST',
					'callback'            => [ UsersRoute::get_instance(), 'update_membership_expiration' ],
					'permission_callback' => 'admin',
				],
				'remove-membership'            => [
					'method'              => 'POST',
					'callback'            => [ UsersRoute::get_instance(), 'remove_membership' ],
					'permission_callback' => 'admin',
				],
				// Settings endpoints.
				'get-global-settings'          => [
					'method'              => 'POST',
					'callback'            => [ SettingsRoute::get_instance(), 'get_global_settings' ],
					'permission_callback' => 'admin',
				],
				'update-global-settings'       => [
					'method'              => 'POST',
					'callback'            => [ SettingsRoute::get_instance(), 'update_global_settings' ],
					'permission_callback' => 'admin',
				],
				'update-email-template'        => [
					'method'              => 'POST',
					'callback'            => [ SettingsRoute::get_instance(), 'update_email_template_settings' ],
					'permission_callback' => 'admin',
				],
				// Premium routes (add-user-role, remove-user-role, update-user-role, import-users) are registered by SureMembers Pro.
				'search-access-groups'         => [
					'method'              => 'POST',
					'callback'            => [ SettingsRoute::get_instance(), 'search_access_groups' ],
					'permission_callback' => 'admin',
				],
				'save-redirection-rules'       => [
					'method'              => 'POST',
					'callback'            => [ SettingsRoute::get_instance(), 'save_redirection_rules' ],
					'permission_callback' => 'admin',
				],
			]
		);
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		$sm_routes = $this->get_suremembers_routes();

		foreach ( $sm_routes as $route => $route_data ) {
			$method              = $route_data['method'] ?? 'POST';
			$callback            = $route_data['callback'] ?? null;
			$permission_callback = $route_data['permission_callback'] ?? '';
			$args                = $route_data['args'] ?? [];
			$this->register_route( $method, $route, $callback, $permission_callback, $args ); // @phpstan-ignore-line
		}
	}

	/**
	 * Register route.
	 *
	 * @param string       $method Method.
	 * @param string       $route Route.
	 * @param array        $callback Callback.
	 * @param bool         $permission_callback Permission callback.
	 * @param array<mixed> $args Route arguments.
	 * @return void
	 * @since 1.0.0
	 * @phpstan-ignore-next-line
	 */
	public function register_route( $method, $route, $callback, $permission_callback = '', $args = [] ) {
		sm_route()->addRoute(
			$method,
			$route,
			static function ( $request ) use ( $callback ) {
				return self::rest_response( call_user_func_array( $callback, [ $request ] ) );
			},
			$permission_callback, // @phpstan-ignore-line
			$args
		);
	}
}
