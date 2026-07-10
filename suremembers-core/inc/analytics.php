<?php
/**
 * Analytics — BSF Analytics integration for SureMembers Core.
 *
 * @package SureMembersCore
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics class.
 *
 * Connects SureMembers Core to BSF Analytics for product telemetry.
 * Tracks one-time milestone events and daily KPI metrics.
 *
 * @since 1.0.0
 */
class Analytics {
	use Get_Instance;

	/**
	 * BSF Analytics Events instance.
	 *
	 * @var \BSF_Analytics_Events|null
	 */
	private static $events_instance = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// These hooks must run on REST API requests too (not just admin).
		add_filter( 'suremembers_global_settings', [ $this, 'sync_usage_tracking_setting' ] );
		add_action( 'suremembers_settings_updated', [ $this, 'update_usage_optin' ], 10, 2 );

		// Hook-based event: first access group published (fires via REST API on Gutenberg save).
		add_action( 'transition_post_status', [ $this, 'track_first_access_group_published' ], 10, 3 );

		// Hook-based event: first member added to a membership (fires via REST API).
		add_action( 'suremembers_user_access_group_granted', [ $this, 'track_first_member_added' ], 10, 3 );

		if ( ! is_admin() ) {
			return;
		}

		if ( ! class_exists( 'BSF_Analytics_Loader' ) ) {
			require_once SUREMEMBERS_CORE_DIR . 'lib/bsf-analytics/class-bsf-analytics-loader.php';
		}

		if ( ! class_exists( 'BSF_Analytics_Loader' ) ) {
			return;
		}

		\BSF_Analytics_Loader::get_instance()->set_entity(
			[
				'suremembers' => [
					'product_name'        => 'SureMembers',
					'path'                => SUREMEMBERS_CORE_DIR . 'lib/bsf-analytics',
					'author'              => 'Brainstorm Force',
					'time_to_display'     => '+24 hours',
					'hide_optin_checkbox' => true,
					'deactivation_survey' => apply_filters(
						'suremembers_deactivation_survey_data',
						[
							[
								'id'                => 'deactivation-survey-suremembers-core',
								'popup_logo'        => SUREMEMBERS_CORE_URL . 'admin/assets/images/icon.svg',
								'plugin_slug'       => 'suremembers-core',
								'popup_title'       => __( 'Quick Feedback', 'suremembers-core' ),
								'support_url'       => 'https://suremembers.com/contact/',
								'popup_description' => __( 'If you have a moment, please share why you are deactivating SureMembers:', 'suremembers-core' ),
								'show_on_screens'   => [ 'plugins' ],
								'plugin_version'    => SUREMEMBERS_CORE_VER,
							],
						]
					),
				],
			]
		);

		add_filter( 'bsf_core_stats', [ $this, 'add_analytics_data' ] );

		// State-based events — run on init at priority 98 (after CPTs are registered
		// and BSF Analytics library loads, but before maybe_track_analytics sends at 99).
		if ( get_transient( 'suremembers_state_events_checked' ) === false ) {
			add_action( 'init', [ $this, 'detect_state_events' ], 98 );
		}
	}

	/**
	 * Get shared BSF Analytics Events instance.
	 *
	 * @return \BSF_Analytics_Events|null
	 * @since 1.0.0
	 */
	public static function events() {
		if ( self::$events_instance === null ) {
			if ( ! class_exists( 'BSF_Analytics_Events' ) ) {
				$lib_path = SUREMEMBERS_CORE_DIR . 'lib/bsf-analytics/class-bsf-analytics-events.php';
				if ( file_exists( $lib_path ) ) {
					require_once $lib_path;
				} else {
					return null;
				}
			}
			self::$events_instance = new \BSF_Analytics_Events( 'suremembers' );
		}
		return self::$events_instance;
	}

	/**
	 * Add SureMembers analytics data to BSF stats payload.
	 *
	 * @param array<string, mixed> $stats_data Existing stats payload.
	 * @return array<string, mixed>
	 * @since 1.0.0
	 */
	public function add_analytics_data( $stats_data ) {
		$events = self::events();

		$stats_data['plugin_data']['suremembers'] = [
			'plugin_version' => SUREMEMBERS_CORE_VER,
			'events_record'  => $events ? $events->flush_pending() : [],
			'kpi_records'    => $this->get_kpi_tracking_data(),
		];

		return $stats_data;
	}

	// -------------------------------------------------------------------------
	// Hook-based events
	// -------------------------------------------------------------------------

	/**
	 * Track first access group published.
	 *
	 * Fires on every post status transition. Dedup ensures it tracks once.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Previous post status.
	 * @param \WP_Post $post       Post object.
	 * @return void
	 * @since 1.0.0
	 */
	public function track_first_access_group_published( $new_status, $old_status, $post ): void {
		if ( $post->post_type !== SUREMEMBERS_POST_TYPE ) {
			return;
		}

		if ( $new_status !== 'publish' || $old_status === 'publish' ) {
			return;
		}

		$events = self::events();
		if ( $events === null ) {
			return;
		}

		$events->track(
			'first_access_group_published',
			'yes',
			[
				'days_since_install'  => (string) $this->get_days_since_install(),
				'total_access_groups' => (string) ( (int) ( wp_count_posts( SUREMEMBERS_POST_TYPE )->publish ?? 0 ) ),
			]
		);
	}

	/**
	 * Track first member added to any membership.
	 *
	 * @param int        $user_id          User ID granted access.
	 * @param int        $ag_id            Access group ID.
	 * @param array<int> $access_group_ids All access group IDs in this grant operation.
	 * @return void
	 * @since 1.0.0
	 */
	public function track_first_member_added( $user_id, $ag_id, $access_group_ids ): void {
		$events = self::events();
		if ( $events === null ) {
			return;
		}

		$access_data = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$ag_id}", true );
		$integration = ! empty( $access_data['integration'] ) ? $access_data['integration'] : 'default';

		$events->track(
			'first_member_added',
			'yes',
			[
				'days_since_install' => (string) $this->get_days_since_install(),
				'integration'        => $integration,
			]
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Sync usage_tracking value from suremembers_usage_optin option into settings.
	 *
	 * @param array<string, mixed> $settings Global settings array.
	 * @return array<string, mixed>
	 * @since 1.0.0
	 */
	public function sync_usage_tracking_setting( $settings ) {
		$usage_optin = get_option( 'suremembers_usage_optin', 'no' );

		$settings[ SUREMEMBERS_ADMIN_SETTINGS ]['usage_tracking'] = $usage_optin === 'yes';

		return $settings;
	}

	/**
	 * Update suremembers_usage_optin option when admin settings are saved.
	 *
	 * @param string               $key  Setting key that was updated.
	 * @param array<string, mixed> $data Setting data.
	 * @return void
	 * @since 1.0.0
	 */
	public function update_usage_optin( $key, $data ): void {
		if ( $key !== SUREMEMBERS_ADMIN_SETTINGS ) {
			return;
		}

		if ( ! is_array( $data ) || ! isset( $data['usage_tracking'] ) ) {
			return;
		}

		$usage_tracking = $data['usage_tracking'] ? 'yes' : 'no';
		update_option( 'suremembers_usage_optin', $usage_tracking );
	}

	// -------------------------------------------------------------------------
	// State detection (runs once per day on admin load)
	// -------------------------------------------------------------------------

	/**
	 * Detect and track state-based events.
	 *
	 * Runs on init at priority 98. BSF_Analytics_Events dedup
	 * prevents duplicate tracking across calls.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function detect_state_events(): void {
		$events = self::events();
		if ( $events === null ) {
			// BSF_Analytics_Events class not loaded yet — do NOT set transient so
			// this retries on the next admin page load.
			return;
		}

		// Throttle: set transient AFTER confirming events class is available.
		set_transient( 'suremembers_state_events_checked', 1, DAY_IN_SECONDS );

		$this->track_plugin_activated( $events );
		$this->track_plugin_updated( $events );
		$this->track_onboarding_events( $events );
		$this->track_content_restriction_events( $events );
		$this->track_integration_events( $events );
		$this->track_learn_events( $events );

		/**
		 * Fires after core state events are detected.
		 *
		 * Allows premium plugins to track their own state-based events.
		 *
		 * @since 1.0.0
		 *
		 * @param \BSF_Analytics_Events $events BSF Analytics Events instance.
		 */
		do_action( 'suremembers_detect_state_events', $events );
	}

	/**
	 * Track plugin_activated (once per install).
	 *
	 * @param \BSF_Analytics_Events $events Event tracker.
	 * @return void
	 * @since 1.0.0
	 */
	private function track_plugin_activated( $events ): void {
		$bsf_referrers = get_option( 'bsf_product_referers', [] );
		$source        = ! empty( $bsf_referrers['suremembers'] )
			? sanitize_text_field( $bsf_referrers['suremembers'] )
			: 'self';

		$events->track( 'plugin_activated', SUREMEMBERS_CORE_VER, [ 'source' => $source ] );
	}

	/**
	 * Track plugin_updated on version change (re-trackable).
	 *
	 * @param \BSF_Analytics_Events $events Event tracker.
	 * @return void
	 * @since 1.0.0
	 */
	private function track_plugin_updated( $events ): void {
		$stored_version = get_option( 'suremembers_tracked_version', '' );

		if ( $stored_version !== SUREMEMBERS_CORE_VER ) {
			if ( ! empty( $stored_version ) ) {
				$events->flush_pushed( [ 'plugin_updated' ] );
				$events->track( 'plugin_updated', SUREMEMBERS_CORE_VER, [ 'from_version' => $stored_version ] );
			}
			update_option( 'suremembers_tracked_version', SUREMEMBERS_CORE_VER );
		}
	}

	/**
	 * Track onboarding completed/skipped state events.
	 *
	 * @param \BSF_Analytics_Events $events Event tracker.
	 * @return void
	 * @since 1.0.0
	 */
	private function track_onboarding_events( $events ): void {
		$completed = get_option( 'suremembers_onboarding_completed' ) === 'yes';
		$skipped   = get_option( 'suremembers_onboarding_skipped' ) === 'yes';

		if ( ! $completed && ! $skipped ) {
			return;
		}

		$properties = [];

		if ( $skipped ) {
			$skipped_on_step = get_option( 'suremembers_onboarding_skipped_step', '' );
			if ( ! empty( $skipped_on_step ) ) {
				$properties['skipped_on_step'] = $skipped_on_step;
			}
		}

		$events->track(
			'onboarding_completed',
			$completed ? 'yes' : 'no',
			$properties
		);
	}

	/**
	 * Track first_content_restricted via state.
	 *
	 * @param \BSF_Analytics_Events $events Event tracker.
	 * @return void
	 * @since 1.0.0
	 */
	private function track_content_restriction_events( $events ): void {
		// first_content_restricted: any published access group has restriction rules configured.
		$restricted_query = new \WP_Query(
			[
				'post_type'      => SUREMEMBERS_POST_TYPE,
				'post_status'    => 'publish',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => SUREMEMBERS_PLAN_INCLUDE,
						'compare' => 'EXISTS',
					],
				],
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			]
		);

		if ( $restricted_query->post_count > 0 ) {
			$total_groups = (string) ( (int) ( wp_count_posts( SUREMEMBERS_POST_TYPE )->publish ?? 0 ) );
			$events->track(
				'first_content_restricted',
				'yes',
				[
					'total_access_groups' => $total_groups,
				]
			);
		}
	}

	/**
	 * Track integration connected events.
	 *
	 * @param \BSF_Analytics_Events $events Event tracker.
	 * @return void
	 * @since 1.0.0
	 */
	private function track_integration_events( $events ): void {
		$enabled = [];

		if ( function_exists( 'WC' ) && $this->is_woocommerce_connected() ) {
			$enabled[] = 'woocommerce';
		}

		if ( defined( 'SURECART_APP_URL' ) ) {
			$enabled[] = 'surecart';
		}

		if ( class_exists( 'SFWD_LMS' ) ) {
			$enabled[] = 'learndash';
		}

		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'buddyboss-platform/bp-loader.php' ) ) {
			$enabled[] = 'buddyboss';
		}

		if ( function_exists( 'tutor_lms' ) ) {
			$enabled[] = 'tutorlms';
		}

		if ( ! empty( $enabled ) ) {
			$events->track(
				'integration_enabled',
				implode( ',', $enabled )
			);
		}
	}

	/**
	 * Track Learn tab progress events.
	 *
	 * Emits two events on every cycle so the latest state is always reported:
	 *  - learn_progress       — value `completed` or `in_progress`.
	 *  - learn_tab_dismissed  — value `yes` or `no`.
	 *
	 * Both events share the same properties so the analytics backend can
	 * correlate dismissal against which steps remain incomplete. Pro-gated
	 * steps are excluded so progress reflects only the free experience.
	 *
	 * @param \BSF_Analytics_Events $events Event tracker.
	 * @return void
	 * @since 1.2.0
	 */
	private function track_learn_events( $events ): void {
		if ( ! class_exists( 'SureMembersCore\Inc\Modules\Learn\Learn' ) ) {
			return;
		}

		$learn = \SureMembersCore\Inc\Modules\Learn\Learn::get_instance();
		if ( ! method_exists( $learn, 'get_chapters_with_progress' ) ) {
			return;
		}

		$chapters = $learn->get_chapters_with_progress();

		$completed_ids  = [];
		$incomplete_ids = [];

		foreach ( $chapters as $chapter ) {
			foreach ( $chapter['steps'] ?? [] as $step ) {
				if ( ! empty( $step['isPro'] ) ) {
					continue;
				}

				$step_id = $chapter['id'] . '/' . $step['id'];

				if ( ! empty( $step['completed'] ) ) {
					$completed_ids[] = $step_id;
				} else {
					$incomplete_ids[] = $step_id;
				}
			}
		}

		$all_complete = empty( $incomplete_ids ) && ! empty( $completed_ids );
		$is_dismissed = \SureMembersCore\Inc\Modules\Learn\Learn::is_learn_dismissed();

		$properties = [
			'completed_steps_count'  => (string) count( $completed_ids ),
			'incomplete_steps_count' => (string) count( $incomplete_ids ),
			'completed_steps'        => implode( ',', $completed_ids ),
			'incomplete_steps'       => implode( ',', $incomplete_ids ),
		];

		// Re-track on every cycle so properties reflect the latest state.
		$events->flush_pushed( [ 'learn_progress', 'learn_tab_dismissed' ] );

		$events->track(
			'learn_progress',
			$all_complete ? 'completed' : 'in_progress',
			$properties
		);

		$events->track(
			'learn_tab_dismissed',
			$is_dismissed ? 'yes' : 'no',
			$properties
		);
	}

	/**
	 * Get days since plugin install.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	private function get_days_since_install(): int {
		$install_time = (int) get_option( 'suremembers_usage_installed_time', 0 );
		if ( $install_time <= 0 ) {
			return 0;
		}
		return (int) floor( ( time() - $install_time ) / DAY_IN_SECONDS );
	}

	// -------------------------------------------------------------------------
	// KPI tracking
	// -------------------------------------------------------------------------

	/**
	 * Get KPI tracking data for the last 2 days (excluding today).
	 *
	 * @return array<string, array<string, array<string, int>>> KPI records keyed by date.
	 * @since 1.0.0
	 */
	private function get_kpi_tracking_data() {
		$kpi_records = [];

		for ( $i = 1; $i <= 2; $i++ ) {
			$date = wp_date( 'Y-m-d', strtotime( "-{$i} days" ) );
			if ( ! is_string( $date ) ) {
				continue;
			}
			$kpi_records[ $date ] = [
				'numeric_values' => [
					'access_groups'  => $this->get_total_access_groups_count(),
					'active_members' => $this->get_active_members_count(),
				],
			];
		}

		return $kpi_records;
	}

	/**
	 * Get total count of published access groups.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	private function get_total_access_groups_count() {
		$count = wp_count_posts( SUREMEMBERS_POST_TYPE );
		return isset( $count->publish ) ? (int) $count->publish : 0;
	}

	/**
	 * Get count of users with at least one active membership.
	 *
	 * @return int
	 * @since 1.0.0
	 */
	private function get_active_members_count() {

		global $wpdb;

		$meta_key_pattern = $wpdb->esc_like( SUREMEMBERS_USER_META . '_' ) . '%';
		$meta_value_like  = '%' . $wpdb->esc_like( '"active"' ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
				WHERE meta_key LIKE %s
				AND meta_value LIKE %s",
				$meta_key_pattern,
				$meta_value_like
			)
		);
	}

	/**
	 * Check if WooCommerce integration is actively connected
	 * (at least one WC product linked to a SureMembers access group).
	 *
	 * @return bool
	 * @since 1.0.0
	 */
	private function is_woocommerce_connected() {
		$cache_key = 'suremembers_wc_connected';
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $cached === 'yes';
		}

		$query = new \WP_Query(
			[
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					[
						'key'     => SUREMEMBERS_ACCESS_GROUPS,
						'compare' => 'EXISTS',
					],
				],
				'posts_per_page' => 1,
				'no_found_rows'  => true,
				'fields'         => 'ids',
			]
		);

		$connected = $query->post_count > 0;
		set_transient( $cache_key, $connected ? 'yes' : 'no', DAY_IN_SECONDS );

		return $connected;
	}
}
