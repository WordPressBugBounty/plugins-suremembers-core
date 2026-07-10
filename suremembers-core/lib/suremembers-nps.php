<?php
/**
 * SureMembers NPS Survey Notice.
 *
 * @package SureMembersCore
 * @since 1.0.0
 */

namespace SureMembersCore\Lib;

defined( 'ABSPATH' ) || exit;

/**
 * SureMembers_Nps
 *
 * Handles the NPS survey notice display for SureMembers.
 *
 * @since 1.0.0
 */
class SureMembers_Nps {
	/**
	 * Instance
	 *
	 * @var object|null Class Instance.
	 * @since 1.0.0
	 */
	private static $instance = null;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'admin_footer', [ $this, 'show_suremembers_nps_notice' ], 999 );
	}

	/**
	 * Get Instance
	 *
	 * @since 1.0.0
	 *
	 * @return object Class object.
	 */
	public static function get_instance(){
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Check if at least two access groups have been created.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if two or more published access groups exist.
	 */
	public static function has_minimum_access_groups(){
		$total_access_groups = wp_count_posts( SUREMEMBERS_POST_TYPE );
		$published_count     = isset( $total_access_groups->publish ) ? (int) $total_access_groups->publish : 0;

		return $published_count >= 2;
	}

	/**
	 * Render SureMembers NPS Survey notice.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function show_suremembers_nps_notice(){
			if ( class_exists( 'Nps_Survey' ) ) {
			\Nps_Survey::show_nps_notice(
				'nps-survey-suremembers',
				[
					'show_if'          => self::has_minimum_access_groups(),
					'dismiss_timespan' => '',
					'display_after'    => 0,
					'plugin_slug'      => 'suremembers',
					'message'          => [

						// Step 1 - Rating input.
						'logo'                  => esc_url( SUREMEMBERS_CORE_URL . 'admin/assets/images/icon.svg' ),
						'plugin_name'           => __( 'SureMembers', 'suremembers-core' ),
						'nps_rating_title'      => __( 'Quick Question!', 'suremembers-core' ),
						'nps_rating_message'    => __( 'How would you rate your SureMembers experience? Love it or not, your feedback helps us make content restriction better.', 'suremembers-core' ),
						'rating_min_label'      => __( 'Very unlikely!', 'suremembers-core' ),
						'rating_max_label'      => __( 'Very likely!', 'suremembers-core' ),

						// Step 2A - (rating 8-10).
						'feedback_title'        => __( 'Thanks for your amazing feedback!', 'suremembers-core' ),
						'feedback_content'      => __( 'Glad you\'re enjoying SureMembers! Thanks for growing with us. Got ideas? We\'d love to hear them.', 'suremembers-core' ),

						// Step 2B - (rating 0-7).
						'plugin_rating_title'   => __( 'Thank you for sharing your feedback', 'suremembers-core' ),
						'plugin_rating_content' => __( 'We truly value your input. Tell us what\'s missing or what could be better - we\'re here to improve your SureMembers experience.', 'suremembers-core' ),
					],
					'allow_review'     => false,
					'show_overlay'     => false,
					'show_on_screens'  => [ 'toplevel_page_suremembers' ],
				]
			);
            }
	}
}
