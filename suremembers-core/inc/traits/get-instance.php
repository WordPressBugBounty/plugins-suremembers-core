<?php
/**
 * Trait.
 *
 * @package suremembers
 */

namespace SureMembersCore\Inc\Traits;

defined( 'ABSPATH' ) || exit;

/**
 * Trait Get_Instance.
 */
trait Get_Instance {
	/**
	 * Instance object.
	 *
	 * @var object|null Class Instance.
	 */
	private static $instance = null;

	/**
	 * Initiator
	 *
	 * @since 0.0.1
	 *
	 * @return object initialized object of class.
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
