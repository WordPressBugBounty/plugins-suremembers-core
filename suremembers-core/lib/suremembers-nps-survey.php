<?php
/**
 * Init
 *
 * Responsible for loading the latest version of the NPS Survey plugin.
 *
 * @since 1.0.0
 * @package NPS Survey
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'SureMembers_Nps_Survey' ) ) {

	/**
	 * Admin
	 */
	class SureMembers_Nps_Survey {
		/**
		 * Instance
		 *
		 * @since 1.0.0
		 */
		private static $instance = null;

		/**
		 * Constructor.
		 *
		 * @since 1.0.0
		 */
		private function __construct() {
			$this->version_check();
			add_action( 'init', [ $this, 'load' ], 999 );
		}

		/**
		 * Get Instance
		 *
		 * @since 1.0.0
		 *
		 * @return object Class object.
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Version Check
		 *
		 * @return void
		 */
		public function version_check(){

			$file = realpath( dirname( __FILE__ ) . '/nps-survey/version.json' );

			// Is file exist?
			if ( is_file( $file ) ) {
				// @codingStandardsIgnoreStart
				$file_data = json_decode( file_get_contents( $file ), true );
				// @codingStandardsIgnoreEnd
				global $nps_survey_version, $nps_survey_init;
				$path = realpath( dirname( __FILE__ ) . '/nps-survey/nps-survey.php' );
				$version = $file_data['nps-survey'] ?? 0;

				if ( $nps_survey_version === null ) {
					$nps_survey_version = '1.0.0';
				}

				// Compare versions.
				if ( version_compare( $version, $nps_survey_version, '>=' ) ) {
					$nps_survey_version = $version;
					$nps_survey_init = $path;
				}
			}
		}

		/**
		 * Load latest plugin
		 *
		 * @return void
		 */
		public function load(){
			global $nps_survey_version, $nps_survey_init;
			if ( is_file( realpath( $nps_survey_init ) ) ) {
				include_once realpath( $nps_survey_init );
			}
		}
	}
	SureMembers_Nps_Survey::get_instance();
}
