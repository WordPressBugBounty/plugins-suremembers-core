<?php
/**
 * Mailer — Core email send helper.
 *
 * The reusable "send an email" mechanics live in Core (free): from name/email,
 * HTML content type, WooCommerce/wp_mail dispatch. Template *content* is supplied
 * by callers (Pro provides the team/membership template data). This keeps the
 * sending engine free while the templates remain a Pro feature.
 *
 * @package suremembers
 *
 * @since 1.2.0
 */

namespace SureMembersCore\Inc\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class Mailer.
 *
 * @since 1.2.0
 */
class Mailer {
	/**
	 * Option holding the shared email settings (from name/email).
	 *
	 * @since 1.2.0
	 */
	private const SETTINGS_OPTION = 'suremembers_email_template_settings';

	/**
	 * Send an HTML email, applying the configured From name/email.
	 *
	 * @param string            $to      Recipient email address.
	 * @param string            $subject Email subject.
	 * @param string            $body    HTML body.
	 * @param array<int,string> $headers Optional extra headers.
	 *
	 * @since 1.2.0
	 *
	 * @return bool True when wp_mail accepted the message.
	 */
	public static function send( string $to, string $subject, string $body, array $headers = [] ): bool {
		$to = sanitize_email( $to );
		if ( empty( $to ) || trim( $subject ) === '' ) {
			return false;
		}

		$mail_headers = array_merge( [ 'Content-Type: text/html; charset=UTF-8' ], $headers );

		$from = self::from_header();
		if ( $from !== '' ) {
			$mail_headers[] = $from;
		}

		// Transactional, per-recipient send (not bulk).
		return (bool) wp_mail( $to, $subject, $body, $mail_headers ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail
	}

	/**
	 * Build the "From: Name <email>" header from saved email settings.
	 *
	 * @since 1.2.0
	 *
	 * @return string From header, or empty string when not configured.
	 */
	private static function from_header(): string {
		$settings = get_option( self::SETTINGS_OPTION, [] );
		if ( ! is_array( $settings ) ) {
			return '';
		}

		// The editor stores the sender name under "form_name" (legacy key).
		$name  = isset( $settings['form_name'] ) ? sanitize_text_field( (string) $settings['form_name'] ) : '';
		$email = isset( $settings['from_email'] ) ? sanitize_email( (string) $settings['from_email'] ) : '';

		if ( empty( $email ) ) {
			return '';
		}

		return $name !== ''
			? sprintf( 'From: %s <%s>', $name, $email )
			: sprintf( 'From: %s', $email );
	}
}
