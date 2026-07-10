<?php
/**
 * BuddyBoss Integration.
 *
 * @package suremembers
 *
 * @since 1.4.0
 */

namespace SureMembersCore\Integrations\Buddyboss;

use SureMembersCore\Inc\Access_Groups;

defined( 'ABSPATH' ) || exit;

/**
 * BuddyBoss Integration
 *
 * @since 1.4.0
 */
class Access_Control extends \BB_Access_Control_Abstract {
	/**
	 * The single instance of the class.
	 *
	 * @var ?self
	 * @since 1.4.0
	 */
	private static $instance = null;

	/**
	 * Access_Control constructor.
	 *
	 * @since 1.4.0
	 */
	public function __construct() {
	}

	/**
	 * Get the instance of this class.
	 *
	 * @since 1.4.0
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			$class_name           = self::class;
			self::$instance       = new $class_name();
			self::$instance->slug = 'suremembers';
		}

		return self::$instance;
	}

	/**
	 * Function will return all the available membership.
	 *
	 * @since 1.4.0
	 *
	 * @return array<string, mixed> list of available membership.
	 */
	public function get_level_lists() {
		if ( ! bbp_pro_is_license_valid() ) {
			return [];
		}

		$results = bb_access_control_get_posts( SUREMEMBERS_POST_TYPE );

		return apply_filters( 'suremembers_access_control_get_level_lists', $results );
	}

	/**
	 * Function will check whether user has access or not.
	 *
	 * @param int   $user_id       user id.
	 * @param mixed $settings_data DB settings.
	 * @param mixed $threaded threaded check.
	 *
	 * @since 1.4.0
	 *
	 * @return bool whether user has access to do a particular given action.
	 */
	public function has_access( $user_id = 0, $settings_data = [], $threaded = false ) {
		$has_access = parent::has_access( $user_id, $settings_data, $threaded );

		if ( ! is_null( $has_access ) ) {
			return $has_access;
		}

		$has_access = false;

		if ( $threaded ) {
			$user_access_groups = get_user_meta( bp_loggedin_user_id(), SUREMEMBERS_USER_META, true );

			if ( ! empty( $user_access_groups ) && is_array( $user_access_groups ) ) {
				$user_access_groups = array_values( array_unique( $user_access_groups ) );
			} else {
				$user_access_groups = [];
			}

			if ( $user_access_groups ) {
				foreach ( $user_access_groups as $user_access_group ) {
					if ( in_array( $user_access_group, $settings_data['access-control-options'] ) ) {
						$arr_key = 'access-control-' . $user_access_group . '-options';
						if ( empty( $settings_data[ $arr_key ] ) ) {
							$has_access = true;
							break;
						}

						if ( ! $has_access && in_array( 'all', $settings_data[ $arr_key ] ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
							$has_access = true;
							break;
						}

						$has_access = Access_Groups::check_if_user_has_access( $settings_data[ $arr_key ] );
					}
				}
			}
		} else {
			$has_access = Access_Groups::check_if_user_has_access( $settings_data['access-control-options'] );
		}

		return apply_filters( 'bb_access_control_' . $this->slug . '_has_access', $has_access );
	}
}
