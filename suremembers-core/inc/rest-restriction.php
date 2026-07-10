<?php
/**
 * REST API access restriction.
 *
 * Enforces SureMembers access-group restrictions across the WordPress REST API
 * for every restrictable public post type (posts, pages, and custom post
 * types). For users who lack access, restricted items are:
 *
 *  - excluded entirely from collection listings (e.g. `/wp/v2/posts`), so their
 *    titles, slugs, links, and meta are not leaked;
 *  - excluded from `/wp/v2/search` results (the core search controller runs its
 *    own WP_Query, so it is tagged separately from the collection controllers);
 *    and
 *  - blocked with a 403 on single-item requests (e.g. `/wp/v2/posts/{id}`).
 *
 * Administrators (`manage_options`) bypass all checks.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * REST API access restriction.
 *
 * @since 1.1.0
 */
class Rest_Restriction {
	use Get_Instance;

	/**
	 * Query var used to tag the core REST collection query, so the `the_posts`
	 * filter only acts on `/wp/v2/{type}` listings and leaves unrelated queries
	 * (page rendering, other plugins' custom REST routes) untouched.
	 *
	 * @since 1.1.0
	 */
	private const REST_COLLECTION_FLAG = 'suremembers_filter_rest_collection';

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		// Per-post-type collection query tags are registered on rest_api_init,
		// by which point all post types are guaranteed to be registered.
		add_action( 'rest_api_init', [ $this, 'register_collection_query_tags' ] );
		add_filter( 'the_posts', [ $this, 'filter_rest_collection_posts' ], 10, 2 );
		add_filter( 'rest_pre_dispatch', [ $this, 'restrict_single_rest_item' ], 10, 3 );

		// `/wp/v2/search` runs its own WP_Query inside the core post search
		// handler, bypassing the per-type `rest_{type}_query` tags above. Tag
		// that query with the same flag so `the_posts` strips restricted hits
		// (titles, links, excerpts) from search results too. Covers every
		// restrictable post type, including plain posts and pages.
		add_filter( 'rest_post_search_query', [ $this, 'tag_rest_collection_query' ], 10, 2 );
	}

	/**
	 * Post types whose REST output must respect access groups.
	 *
	 * @since 1.1.0
	 * @return array<int, string>
	 */
	public function get_post_types(): array {
		$post_types = array_values( Restricted::get_post_types( 'names' ) );

		/**
		 * Filter the post types whose REST output respects access groups.
		 *
		 * @since 1.1.0
		 * @param array<int, string> $post_types Post type slugs.
		 */
		return (array) apply_filters( 'suremembers_rest_restricted_post_types', $post_types );
	}

	/**
	 * Register the collection-query tag filter for each restrictable post type.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_collection_query_tags(): void {
		foreach ( $this->get_post_types() as $post_type ) {
			add_filter( "rest_{$post_type}_query", [ $this, 'tag_rest_collection_query' ], 10, 2 );
		}
	}

	/**
	 * Tag the core REST collection query so `the_posts` can scope its filtering
	 * to `/wp/v2/{type}` listings only.
	 *
	 * @since 1.1.0
	 * @param array<string, mixed>|mixed $args    WP_Query args built by the REST controller.
	 * @param mixed                      $request The REST request (unused).
	 * @return array<string, mixed>|mixed
	 */
	public function tag_rest_collection_query( $args, $request ) {
		unset( $request );
		if ( is_array( $args ) ) {
			$args[ self::REST_COLLECTION_FLAG ] = 1;
		}
		return $args;
	}

	/**
	 * Remove restricted posts from the tagged REST collection listings.
	 *
	 * Only runs for the core collection controllers (tagged via
	 * {@see self::tag_rest_collection_query()}), so non-REST queries and custom
	 * REST routes are never altered.
	 *
	 * @since 1.1.0
	 * @param array<int, WP_Post>|mixed $posts The posts returned for the query.
	 * @param mixed                     $query The current query.
	 * @return array<int, WP_Post>|mixed
	 */
	public function filter_rest_collection_posts( $posts, $query ) {
		if ( ! $query instanceof WP_Query || ! $query->get( self::REST_COLLECTION_FLAG ) ) {
			return $posts;
		}

		if ( empty( $posts ) || ! is_array( $posts ) || current_user_can( 'manage_options' ) ) {
			return $posts;
		}

		return array_values(
			array_filter(
				$posts,
				static function ( $post ) {
					if ( ! $post instanceof WP_Post ) {
						return true;
					}
					// Keep posts the current user can edit (author/editor), so
					// editorial REST workflows (block editor, list tables) are
					// not broken for non-admin roles.
					if ( current_user_can( 'edit_post', (int) $post->ID ) ) {
						return true;
					}
					return ! Restricted::is_restricted_for_user( (int) $post->ID );
				}
			)
		);
	}

	/**
	 * Short-circuit a restricted single-item request (/wp/v2/{type}/{id}) with a
	 * 403 before the REST controller runs.
	 *
	 * `rest_pre_dispatch` is the safe place to do this — returning a WP_Error
	 * from `rest_prepare_{type}` instead would fatal inside
	 * WP_REST_Posts_Controller::get_item() (it calls $response->link_header()
	 * on the value, which WP_Error does not implement).
	 *
	 * @since 1.1.0
	 * @param mixed $result  Existing short-circuit result (null to continue dispatch).
	 * @param mixed $server  REST server instance (unused).
	 * @param mixed $request The REST request.
	 * @return mixed|WP_Error
	 */
	public function restrict_single_rest_item( $result, $server, $request ) {
		unset( $server );

		if ( $result !== null || ! $request instanceof WP_REST_Request ) {
			return $result;
		}
		if ( ! in_array( $request->get_method(), [ 'GET', 'HEAD' ], true ) || current_user_can( 'manage_options' ) ) {
			return $result;
		}

		$route = (string) $request->get_route();
		foreach ( $this->get_single_item_routes() as $regex ) {
			if ( ! preg_match( $regex, $route, $matches ) ) {
				continue;
			}

			$post_id = (int) $matches['id'];

			// Users who can edit the post (author/editor) may read it, so the
			// block editor and other edit-context REST reads keep working.
			if ( current_user_can( 'edit_post', $post_id ) ) {
				return $result;
			}

			if ( Restricted::is_restricted_for_user( $post_id ) ) {
				return new WP_Error(
					'suremembers_rest_forbidden',
					__( 'Sorry, you are not allowed to view this content.', 'suremembers-core' ),
					[ 'status' => rest_authorization_required_code() ]
				);
			}

			return $result;
		}

		return $result;
	}

	/**
	 * Build the REST single-item route patterns for the guarded post types,
	 * derived from each post type's registered REST namespace and base.
	 *
	 * @since 1.1.0
	 * @return array<int, string> List of regex patterns with a named `id` group.
	 */
	private function get_single_item_routes(): array {
		$routes = [];

		foreach ( $this->get_post_types() as $post_type ) {
			$object = get_post_type_object( $post_type );
			if ( ! $object || empty( $object->show_in_rest ) ) {
				continue;
			}

			$base      = is_string( $object->rest_base ) && $object->rest_base !== '' ? $object->rest_base : $post_type;
			$namespace = is_string( $object->rest_namespace ) && $object->rest_namespace !== '' ? $object->rest_namespace : 'wp/v2';

			$routes[] = '#^/' . preg_quote( $namespace, '#' ) . '/' . preg_quote( $base, '#' ) . '/(?P<id>\d+)$#';
		}

		return $routes;
	}
}
