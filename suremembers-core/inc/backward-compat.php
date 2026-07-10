<?php
/**
 * Backward Compatibility Layer.
 *
 * Maps old SureMembers\* namespace to SureMembersCore\* via class_alias.
 * This allows other plugins (SureDash, SureContact) that reference the old
 * namespace to work seamlessly with SureMembers Core.
 *
 * Registered with prepend=false so Pro's autoloader takes priority when active.
 *
 * @package SureMembersCore
 *
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	/**
	 * Autoload callback that aliases SureMembers\* classes to SureMembersCore\*.
	 *
	 * @param string $class Fully qualified class name.
	 *
	 * @since 1.0.0
	 */
	static function ( $class ) {
		$old_prefix = 'SureMembers\\';

		// Only handle classes in the old SureMembers namespace.
		if ( strpos( $class, $old_prefix ) !== 0 ) {
			return;
		}

		// Skip if the class is already loaded (e.g., by Pro's autoloader).
		if ( class_exists( $class, false ) ) {
			return;
		}

		// When Pro is active, check if Pro defines this class file.
		// If so, skip aliasing and let Pro's autoloader handle it.
		if ( defined( 'SUREMEMBERS_DIR' ) ) {
			$relative = substr( $class, strlen( $old_prefix ) );
			$filename = strtolower(
				(string) preg_replace(
					[ '/([a-z])([A-Z])/', '/_/', '/\\\\/' ],
					[ '$1-$2', '-', DIRECTORY_SEPARATOR ],
					$relative
				)
			);

			if ( is_readable( SUREMEMBERS_DIR . $filename . '.php' ) ) {
				return;
			}
		}

		// Map SureMembers\Foo\Bar → SureMembersCore\Foo\Bar.
		$core_class = 'SureMembersCore\\' . substr( $class, strlen( $old_prefix ) );

		// Only alias if the Core class actually exists.
		if ( class_exists( $core_class ) ) {
			class_alias( $core_class, $class );
		}
	}
);
