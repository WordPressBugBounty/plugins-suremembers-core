<?php
/**
 * Modules loader abstract class.
 *
 * @package suremembers.
 */

namespace SureMembersCore\Modules;

use SureMembersCore\Inc\Restricted;

defined( 'ABSPATH' ) || exit;

/**
 * Modules Loader Class
 */
abstract class Base_Module {
	/**
	 * Stores restricted data
	 *
	 * @since 1.4.0
	 * @var array<string, mixed>
	 */
	public $restriction_data = [];

	/**
	 * Class Constructor.
	 *
	 * @since 1.4.0
	 */
	public function __construct() {
		add_filter( 'suremembers_get_post_types_excludes', [ $this, 'exclude_post_types' ], 10, 2 );
		add_filter( 'suremembers_location_selection_options', [ $this, 'add_rule_groups' ] );
		add_filter( 'suremembers_get_access_groups_data', [ $this, 'add_access_group_data' ] );
		add_filter( 'suremembers_access_group_edit_metadata', [ $this, 'save_access_group_data' ], 10, 2 );

		add_action( 'wp_ajax_suremembers_get_posts_by_query', [ $this, 'get_posts_by_query' ] );

		$this->add_actions();
	}

	/**
	 * Additional actions for loader class.
	 *
	 * @since 1.4.0
	 */
	public function add_actions() {
	}

	/**
	 * Returns content as per search string
	 *
	 * @since 1.4.0
	 */
	public function get_posts_by_query() {
		check_ajax_referer( 'suremembers_search_post_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Current user does not have required permission.', 'suremembers-core' ) ] );
		}

		$search_string = isset( $_POST['q'] ) ? sanitize_text_field( $_POST['q'] ) : '';
		$post_type     = isset( $_POST['post_type'] ) ? sanitize_text_field( $_POST['post_type'] ) : 'post';

		$post_type_object = (object) get_post_type_object( $post_type );
		$all_label        = $post_type_object->labels->name;

		$data = [];

		$data[] = [
			'value' => $post_type . '|all',
			/* translators: $1$s All Posts Label */
			'label' => sprintf( esc_html__( 'All %1$s', 'suremembers-core' ), $all_label ),
		];

		$query = (object) new \WP_Query(
			[
				's'              => $search_string,
				'post_type'      => $post_type,
				'posts_per_page' => -1,
			]
		);

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$title  = get_the_title();
				$parent = ! empty( $query->post->post_parent ) ? $query->post->post_parent : 0;
				$title .= $parent !== 0 ? ' (' . get_the_title( $parent ) . ')' : '';
				$id     = get_the_id();
				$data[] = [
					'value' => 'post-' . $id . '-|',
					'label' => $title,
				];
			}
		}

		wp_reset_postdata();

		$result = [
			[
				'label'   => $all_label,
				'options' => $data,
			],
		];

		// return the result in json.
		wp_send_json_success( $result );
	}

	/**
	 * Get restricting rules.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array<string, mixed> Array of restricting rules.
	 *
	 * @since 1.4.0
	 */
	public function get_restricting_rules( $post_id ) {
		$option = [
			'include'           => SUREMEMBERS_PLAN_INCLUDE,
			'exclusion'         => SUREMEMBERS_PLAN_EXCLUDE,
			'priority'          => SUREMEMBERS_PLAN_PRIORITY,
			'current_post_id'   => $post_id,
			'current_post_type' => get_post_type( $post_id ),
		];

		return Restricted::by_access_groups( SUREMEMBERS_POST_TYPE, $option );
	}

	/**
	 * Exclude post types from default rules engine list.
	 *
	 * @param array<string, mixed> $post_types Excluded post types.
	 * @param string               $context get post type context.
	 *
	 * @return array<string, mixed> Excluded post types.
	 *
	 * @since 1.4.0
	 */
	public function exclude_post_types( $post_types, $context ) {
		return $post_types;
	}

	/**
	 * Add rules groups for restriction rules.
	 *
	 * @param array<string, mixed> $locations Rules Engine Locations.
	 *
	 * @return array<string, mixed> Modified locations.
	 *
	 * @since 1.4.0
	 */
	public function add_rule_groups( $locations ) {
		return $locations;
	}

	/**
	 * Add settings localizations data.
	 *
	 * @param array<string, mixed> $localizations array of localization data.
	 *
	 * @return array<string, mixed> updated localizations.
	 *
	 * @since 1.4.0
	 */
	public function add_access_group_data( $localizations ) {
		return $localizations;
	}

	/**
	 * Save Settings data.
	 *
	 * @param array<string, mixed> $include Include data.
	 * @param array<string, mixed> $post_data Post Data.
	 *
	 * @return array<string, mixed> Includes Array.
	 *
	 * @since 1.4.0
	 */
	public function save_access_group_data( $include, $post_data ) {
		return $include;
	}

	/**
	 * Loads template for restricted content
	 *
	 * @param string $template current template.
	 *
	 * @return string|void
	 *
	 * @since 1.4.0
	 */
	public function restricted_page_template( $template ) {
		$path = SUREMEMBERS_CORE_DIR . 'inc/restricted-template.php';
		if ( file_exists( $path ) ) {
			load_template( $path, true, $this->restriction_data );
			return;
		}
		return $template;
	}

	/**
	 * Converts content metadata slug to text
	 *
	 * @param array<string, mixed> $data array to convert data from.
	 *
	 * @since 1.4.0
	 */
	public function convert_to_slug( $data ) {
		$response = [];
		foreach ( $data as $option ) {
			$params = explode( '-', $option );

			if ( count( $params ) <= 1 ) {
				$pm = explode( '|', $option );
				if ( count( $pm ) <= 1 ) {
					return [];
				}
				$post_type_object = (object) get_post_type_object( $pm[0] );
				$post_all_label   = $post_type_object->labels->name;
				$temp             = [
					/* Translators: %s Post Type Label */
					'label' => sprintf( __( 'All %s', 'suremembers-core' ), $post_all_label ),
					'value' => $option,
				];

				$response[] = $temp;
				return $response;
			}

			switch ( $params[0] ) {
				case 'tax':
					$temp = [];
					$term = get_term( intval( $params[1] ) );
					if ( ! empty( $term->name ) ) {
						/* translators: %s term name. */
						$temp['label'] = sprintf( __( 'All singulars from %s', 'suremembers-core' ), $term->name );
						$temp['value'] = $option;
						$response[]    = $temp;
					}
					break;
				case 'postchild':
					$temp  = [];
					$title = get_the_title( intval( $params[1] ) );
					if ( ! empty( $title ) ) {
						/* translators: %s title. */
						$temp['label'] = sprintf( __( 'Child of %s', 'suremembers-core' ), $title );
						$temp['value'] = $option;
						$response[]    = $temp;
					}
					break;
				case 'post':
				default:
					$temp  = [];
					$title = get_the_title( intval( $params[1] ) );
					if ( ! empty( $title ) ) {
						$temp['label'] = $title;
						$temp['value'] = $option;
						$response[]    = $temp;
					}
					break;
			}
		}

		return $response;
	}
}
