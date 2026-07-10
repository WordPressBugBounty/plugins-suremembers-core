<?php
/**
 * Learn module bootstrap.
 *
 * Registers REST routes for the Learn tab and exposes helpers for fetching
 * chapters, merging user progress, and updating step completion state.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Modules\Learn;

use SureMembersCore\Inc\Traits\Get_Instance;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Learn module entry point.
 *
 * @since 1.1.0
 */
class Learn {
	use Get_Instance;

	/**
	 * User meta key for per-user progress storage.
	 *
	 * @since 1.1.0
	 */
	public const PROGRESS_META_KEY = 'suremembers_learn_progress';

	/**
	 * User meta key for tracking whether the Learn tab has been dismissed.
	 *
	 * @since 1.1.0
	 */
	public const DISMISSED_META_KEY = 'suremembers_learn_dismissed';

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register REST routes exposed by the Learn module.
	 *
	 * Routes are namespaced under `suremembers/v1` to stay consistent with the
	 * rest of the plugin.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'suremembers/v1',
			'/learn-chapters',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'rest_get_learn_chapters' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			'suremembers/v1',
			'/learn-progress',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_update_learn_progress' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'chapterId' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'stepId'    => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'completed' => [
						'required'          => true,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
				],
			]
		);

		register_rest_route(
			'suremembers/v1',
			'/learn-dismiss',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_dismiss_learn' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'dismissed' => [
						'required'          => true,
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
				],
			]
		);
	}

	/**
	 * Permission callback — restrict Learn endpoints to administrators.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	public function permission_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * REST handler: return the full chapters structure merged with the
	 * current user's progress.
	 *
	 * @since 1.1.0
	 * @return WP_REST_Response
	 */
	public function rest_get_learn_chapters(): WP_REST_Response {
		$chapters = $this->get_chapters_with_progress( get_current_user_id() );

		return new WP_REST_Response(
			[
				'success'  => true,
				'chapters' => $chapters,
			],
			200
		);
	}

	/**
	 * REST handler: persist a single step's completion state for the current
	 * user, after validating the chapter/step IDs exist in the structure.
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request REST request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_update_learn_progress( WP_REST_Request $request ) {
		$chapter_id = (string) $request->get_param( 'chapterId' );
		$step_id    = (string) $request->get_param( 'stepId' );
		$completed  = (bool) $request->get_param( 'completed' );

		if ( ! $this->is_valid_step( $chapter_id, $step_id ) ) {
			return new WP_Error(
				'invalid_step',
				__( 'Invalid chapter or step identifier.', 'suremembers-core' ),
				[ 'status' => 400 ]
			);
		}

		$user_id = get_current_user_id();
		$this->set_step_completion( $user_id, $chapter_id, $step_id, $completed );

		return new WP_REST_Response(
			[
				'success'   => true,
				'chapterId' => $chapter_id,
				'stepId'    => $step_id,
				'completed' => $completed,
			],
			200
		);
	}

	/**
	 * REST handler: persist the dismissed state of the Learn tab for the
	 * current user.
	 *
	 * @since 1.1.0
	 * @param WP_REST_Request $request REST request instance.
	 * @return WP_REST_Response
	 */
	public function rest_dismiss_learn( WP_REST_Request $request ): WP_REST_Response {
		$dismissed = (bool) $request->get_param( 'dismissed' );
		$user_id   = get_current_user_id();

		update_user_meta( $user_id, self::DISMISSED_META_KEY, $dismissed );

		return new WP_REST_Response(
			[
				'success'   => true,
				'dismissed' => $dismissed,
			],
			200
		);
	}

	/**
	 * Return the chapters structure for rendering, merging each step with the
	 * user's saved completion state.
	 *
	 * @since 1.1.0
	 * @param int $user_id User ID whose progress should be merged. `0` means
	 *                    use the current user.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_chapters_with_progress( int $user_id = 0 ): array {
		if ( $user_id === 0 ) {
			$user_id = get_current_user_id();
		}

		$chapters = Chapters::get_structure();
		$progress = $this->get_user_progress( $user_id );

		foreach ( $chapters as $chapter_index => $chapter ) {
			$chapter_id    = $chapter['id'] ?? '';
			$chapter_steps = $chapter['steps'] ?? [];

			foreach ( $chapter_steps as $step_index => $step ) {
				$step_id = $step['id'] ?? '';
				$done    = (bool) ( $progress[ $chapter_id ][ $step_id ] ?? false );

				$chapters[ $chapter_index ]['steps'][ $step_index ]['completed'] = $done;
			}
		}

		return $chapters;
	}

	/**
	 * Fetch the raw progress array stored in user meta.
	 *
	 * @since 1.1.0
	 * @param int $user_id User ID.
	 * @return array<string, array<string, bool>>
	 */
	public function get_user_progress( int $user_id ): array {
		$progress = get_user_meta( $user_id, self::PROGRESS_META_KEY, true );

		return is_array( $progress ) ? $progress : [];
	}

	/**
	 * Check whether the Learn tab has been dismissed by the given user.
	 *
	 * @since 1.1.0
	 * @param int $user_id User ID. Defaults to current user.
	 * @return bool
	 */
	public static function is_learn_dismissed( int $user_id = 0 ): bool {
		if ( $user_id === 0 ) {
			$user_id = get_current_user_id();
		}

		return (bool) get_user_meta( $user_id, self::DISMISSED_META_KEY, true );
	}

	/**
	 * Check whether the user has any incomplete free (non-Pro) steps.
	 *
	 * Useful to show a notification dot on the Learn tab when the tab is
	 * dismissed but new steps have been added in a plugin update.
	 *
	 * @since 1.1.0
	 * @param int $user_id User ID. Defaults to current user.
	 * @return bool
	 */
	public function has_incomplete_free_steps( int $user_id = 0 ): bool {
		$chapters = $this->get_chapters_with_progress( $user_id );

		foreach ( $chapters as $chapter ) {
			foreach ( $chapter['steps'] ?? [] as $step ) {
				if ( ! empty( $step['isPro'] ) ) {
					continue;
				}

				if ( empty( $step['completed'] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Update a single step's completion flag in user meta.
	 *
	 * @since 1.1.0
	 * @param int    $user_id    User ID.
	 * @param string $chapter_id Chapter identifier.
	 * @param string $step_id    Step identifier.
	 * @param bool   $completed  New completion state.
	 * @return void
	 */
	private function set_step_completion( int $user_id, string $chapter_id, string $step_id, bool $completed ): void {
		$progress = $this->get_user_progress( $user_id );

		if ( ! isset( $progress[ $chapter_id ] ) || ! is_array( $progress[ $chapter_id ] ) ) {
			$progress[ $chapter_id ] = [];
		}

		$progress[ $chapter_id ][ $step_id ] = $completed;

		update_user_meta( $user_id, self::PROGRESS_META_KEY, $progress );
	}

	/**
	 * Validate that a chapter/step combination exists in the defined
	 * structure. Prevents arbitrary keys from being written to user meta.
	 *
	 * @since 1.1.0
	 * @param string $chapter_id Chapter identifier.
	 * @param string $step_id    Step identifier.
	 * @return bool
	 */
	private function is_valid_step( string $chapter_id, string $step_id ): bool {
		foreach ( Chapters::get_structure() as $chapter ) {
			if ( ( $chapter['id'] ?? '' ) !== $chapter_id ) {
				continue;
			}

			foreach ( $chapter['steps'] ?? [] as $step ) {
				if ( ( $step['id'] ?? '' ) === $step_id ) {
					return true;
				}
			}
		}

		return false;
	}
}
