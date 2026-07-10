<?php
/**
 * TutorLMS Integration.
 *
 * @package suremembers
 *
 * @since 1.4.0
 */

namespace SureMembersCore\Modules\Tutorlms;

use SureMembersCore\Inc\Traits\Get_Instance;
use SureMembersCore\Modules\Base_Module;

defined( 'ABSPATH' ) || exit;

/**
 * SureCart Integration
 *
 * @since 1.4.0
 */
class Tutorlms extends Base_Module {
	use Get_Instance;

	/**
	 * Stores restricted data
	 *
	 * @var array Restriction data.
	 *
	 * @since 1.4.0
	 */
	public $restriction_data = [];

	/**
	 * Actions Constructor.
	 */
	public function add_actions() {
		add_filter( 'suremembers_filter_redirection_post_id', [ $this, 'filter_redirection_post_id' ] );
		add_filter( 'suremembers_get_content_meta_values', [ $this, 'filter_content_meta_values' ], 10, 2 );
	}

	/**
	 * Filter post id for Tutor LMS associated content.
	 *
	 * @param int $post_id The post id to be filtered.
	 *
	 * @return int $post_id or Associated post id.
	 *
	 * @since 1.4.0
	 */
	public function filter_redirection_post_id( $post_id ) {
		$restricting_rules = $this->get_restricting_rules( $post_id );

		if ( empty( $restricting_rules ) ) {
			$course_associates = $this->get_associated_post_types();
			if ( in_array( get_post_type( $post_id ), $course_associates, true ) ) {
				$post_id = tutor_utils()->get_course_id_by( get_post_type( $post_id ), $post_id );
			}
		}

		return $post_id;
	}

	/**
	 * Filter Content meta values for Tutor LMS.
	 *
	 * @param array<string, mixed> $meta_args array of arguments meta to filter.
	 * @param object               $q_obj The post object.
	 *
	 * @return array<string, mixed> Modified post meta values.
	 *
	 * @since 1.4.0
	 */
	public function filter_content_meta_values( $meta_args, $q_obj ) {
		if ( ! isset( $q_obj->post_type ) || ! isset( $q_obj->ID ) ) {
			return $meta_args;
		}

		$course_associates = $this->get_associated_post_types();
		if ( in_array( $q_obj->post_type, $course_associates, true ) ) {
			$associated_course = tutor_utils()->get_course_id_by( $q_obj->post_type, $q_obj->ID );
			$meta_args[]       = "post-{$associated_course}-|";
		}
		return $meta_args;
	}

	/**
	 * Get Associated post types for Tutor LMS course.
	 *
	 * @return array<string, mixed> Array of post types.
	 *
	 * @since 1.4.0
	 */
	public function get_associated_post_types() {
		return [ 'lesson', 'topic' ];
	}

	/**
	 * Exclude BuddyBoss supported post types.
	 *
	 * @param array<string, mixed> $post_types Excluded post types.
	 * @param string               $context get post type context.
	 *
	 * @return array<string, mixed> Post Types Array to exclude.
	 *
	 * @since 1.4.0
	 */
	public function exclude_post_types( $post_types, $context ) {
		if ( $context === 'search' ) {
			return $post_types;
		}
		if ( ! is_array( $post_types ) ) {
			return $post_types;
		}
		return array_merge( $post_types, [ 'courses', 'lesson' ] );
	}

	/**
	 * Add Tutor LMS Rule Groups.
	 *
	 * @param array<string, mixed> $locations Current rules locations.
	 *
	 * @return array<string, mixed> Modified locations array.
	 *
	 * @since 1.4.0
	 */
	public function add_rule_groups( $locations ) {
		if ( ! is_array( $locations ) ) {
			return $locations;
		}

		$locations['tutor_lms'] = [
			'label' => __( 'Tutor LMS', 'suremembers-core' ),
			'value' => [
				'courses|all' => __( 'Tutor LMS Courses', 'suremembers-core' ),
				'lesson|all'  => __( 'Tutor LMS Lessons', 'suremembers-core' ),
			],
		];

		return apply_filters( 'suremembers_rules_engine_tutor_lms_locations', $locations );
	}

	/**
	 * Add Access Group Data.
	 *
	 * @param array<string, mixed> $localizations Access group locatlization data.
	 *
	 * @return array<string, mixed> Localization Array.
	 *
	 * @since 1.4.0
	 */
	public function add_access_group_data( $localizations ) {
		// Ignored as the data is for localization.
		if ( empty( $_GET['post_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $localizations;
		}
		// Ignored as the data is for localization.
		$id = absint( $_GET['post_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $id ) ) {
			return $localizations;
		}

		$post = get_post( $id );

		if ( empty( $post ) || $post->post_type !== SUREMEMBERS_POST_TYPE ) {
			return $localizations;
		}

		$includes = get_post_meta( $id, SUREMEMBERS_PLAN_INCLUDE, true );
		if ( empty( $includes ) || ! is_array( $includes ) ) {
			$includes = [];
		}
		$lessons_data = ! empty( $includes['tutorlms_lessons'] ) ? $includes['tutorlms_lessons'] : [];
		$courses_data = ! empty( $includes['tutorlms_courses'] ) ? $includes['tutorlms_courses'] : [];

		$localizations['tutorlms_lessons'] = $this->convert_to_slug( $lessons_data );
		$localizations['tutorlms_courses'] = $this->convert_to_slug( $courses_data );

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
		if ( ! empty( $post_data['tutorlms_lessons'] ) ) {
			$include['tutorlms_lessons'] = $post_data['tutorlms_lessons'];
			unset( $post_data['tutorlms_lessons'] );
		}

		if ( ! empty( $post_data['tutorlms_courses'] ) ) {
			$include['tutorlms_courses'] = $post_data['tutorlms_courses'];
			unset( $post_data['tutorlms_courses'] );
		}

		return $include;
	}
}
