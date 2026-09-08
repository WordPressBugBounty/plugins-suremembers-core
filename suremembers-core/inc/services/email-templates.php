<?php
/**
 * Email_Templates — Core template registry + resolver.
 *
 * Holds the *mechanics* for templated emails (free): a filterable registry of
 * template definitions, merge-tag replacement, and "resolve + send" via Mailer.
 * The actual template *content* (defaults) is registered by Pro through the
 * `suremembers_email_templates` filter, and saved overrides live in the shared
 * `suremembers_email_template_settings` option edited from the Email tab.
 *
 * @package suremembers
 *
 * @since 1.2.0
 */

namespace SureMembersCore\Inc\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Class Email_Templates.
 *
 * @since 1.2.0
 */
class Email_Templates {
	/**
	 * Option holding saved template overrides (enable/subject/content per key).
	 *
	 * @since 1.2.0
	 */
	private const SETTINGS_OPTION = 'suremembers_email_template_settings';

	/**
	 * Registered template definitions, keyed by template key.
	 *
	 * Pro populates this with default subject/content via the filter. Shape:
	 * [ 'team_owner_welcome' => [ 'subject' => '…', 'content' => '…' ], … ].
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array<string, string>> Template defaults.
	 */
	public static function registered(): array {
		/**
		 * Filters the registered email template defaults.
		 *
		 * @since 1.2.0
		 *
		 * @param array<string, array<string, string>> $templates Defaults keyed by template key.
		 */
		$templates = apply_filters( 'suremembers_email_templates', [] );

		return is_array( $templates ) ? $templates : [];
	}

	/**
	 * Resolve a template: merge saved overrides over the registered defaults.
	 *
	 * @param string $key Template key.
	 *
	 * @since 1.2.0
	 *
	 * @return array{enabled:bool, subject:string, content:string} Resolved template.
	 */
	public static function get( string $key ): array {
		$settings = get_option( self::SETTINGS_OPTION, [] );
		$settings = is_array( $settings ) ? $settings : [];
		$defaults = self::registered()[ $key ] ?? [];

		$subject = isset( $settings[ $key . '_subject' ] ) ? (string) $settings[ $key . '_subject' ] : '';
		$content = isset( $settings[ $key . '_content' ] ) ? (string) $settings[ $key . '_content' ] : '';

		if ( trim( $subject ) === '' ) {
			$subject = (string) ( $defaults['subject'] ?? '' );
		}
		if ( trim( wp_strip_all_tags( $content ) ) === '' ) {
			$content = (string) ( $defaults['content'] ?? '' );
		}

		// Respect a saved enable flag when present; otherwise fall back to the
		// template's registered default (so transactional emails work before the
		// admin ever opens the editor).
		$enable_key = 'enable_' . $key;
		$enabled    = array_key_exists( $enable_key, $settings )
			? ! empty( $settings[ $enable_key ] )
			: ! empty( $defaults['default_enabled'] );

		return [
			'enabled' => $enabled,
			'subject' => $subject,
			'content' => $content,
		];
	}

	/**
	 * Replace `{$tag}` placeholders in a string.
	 *
	 * @param string                $text Text containing placeholders.
	 * @param array<string, string> $tags Map of `{$tag}` => value.
	 *
	 * @since 1.2.0
	 *
	 * @return string Text with placeholders replaced.
	 */
	public static function replace( string $text, array $tags ): string {
		return strtr( $text, $tags );
	}

	/**
	 * Resolve a template, replace tags and send it.
	 *
	 * @param string                $key  Template key.
	 * @param string                $to   Recipient email.
	 * @param array<string, string> $tags Merge-tag map.
	 * @param array<string, mixed>  $args Optional: { force: bool } to ignore the enable toggle (test sends).
	 *
	 * @since 1.2.0
	 *
	 * @return bool True when sent.
	 */
	public static function send( string $key, string $to, array $tags, array $args = [] ): bool {
		$template = self::get( $key );

		if ( empty( $args['force'] ) && ! $template['enabled'] ) {
			return false;
		}

		$subject = self::replace( $template['subject'], $tags );
		$body    = self::replace( $template['content'], $tags );

		if ( trim( wp_strip_all_tags( $body ) ) === '' ) {
			return false;
		}

		return Mailer::send( $to, $subject, self::wrap( $body, $key ) );
	}

	/**
	 * Wrap the email body in the shared SureMembers email shell (header, card, footer).
	 *
	 * Keeps the editable content simple while every templated email goes out with
	 * consistent branding. Uses the same wrapper as the membership emails.
	 *
	 * @param string $body Resolved HTML body (tags already replaced).
	 * @param string $key  Template key, passed to the filter for per-template control.
	 *
	 * @since 1.4.1
	 *
	 * @return string Wrapped HTML body, or the original body when wrapping is disabled.
	 */
	private static function wrap( string $body, string $key ): string {
		/**
		 * Filters whether the branded email wrapper is applied to a templated email.
		 *
		 * @since 1.4.1
		 *
		 * @param bool   $use_wrapper Default true.
		 * @param string $key         Template key.
		 */
		if ( ! apply_filters( 'suremembers_email_use_template_wrapper', true, $key ) ) {
			return $body;
		}

		if ( ! class_exists( '\SureMembersCore\Admin\Templates' ) ) {
			return $body;
		}

		$settings  = get_option( self::SETTINGS_OPTION, [] );
		$settings  = is_array( $settings ) ? $settings : [];
		$from_name = isset( $settings['form_name'] ) ? sanitize_text_field( (string) $settings['form_name'] ) : '';
		if ( $from_name === '' ) {
			$from_name = (string) get_bloginfo( 'name' );
		}

		return \SureMembersCore\Admin\Templates::prepare_email_content( $from_name, $body );
	}
}
