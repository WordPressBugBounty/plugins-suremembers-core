<?php
/**
 * Time-boxed promotions shown inside the SureMembers admin app.
 *
 * @package SureMembersCore
 * @since 1.4.2
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Code-defined campaigns (sales, offers) gated by a date window, so a campaign
 * appears and disappears on its own without a plugin update. Admins only.
 *
 * Each campaign has placements, one per UI slot:
 * - `top_bar`: a slim moving text bar at the top of the SureMembers app.
 * - `upgrade_card`: an offer inside the dashboard's Upgrade to Pro card.
 *
 * A placement's `audience` is `all`, `free` (SureMembers premium not active)
 * or `pro`. Dismissal is permanent and per user: one click hides every
 * placement of that campaign for that admin. A new campaign (new id) shows
 * again.
 *
 * @since 1.4.2
 */
class Promotion {
	use Get_Instance;

	/**
	 * User meta holding the ids of dismissed campaigns.
	 *
	 * @since 1.4.2
	 */
	public const DISMISSED = 'suremembers_core_dismissed_promotions';

	/**
	 * UI slots a placement can target.
	 *
	 * @since 1.4.2
	 */
	private const SLOTS = [ 'top_bar', 'upgrade_card' ];

	/**
	 * Constructor.
	 *
	 * @since 1.4.2
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Every campaign, live or not. Past entries can be removed in any later
	 * release; an expired entry is never shown.
	 *
	 * Dates are `Y-m-d H:i:s` in the campaign's `timezone`, both inclusive.
	 * Top bar `items` are segments; each segment is a list of text parts whose
	 * `style` is plain (default), bold, accent or strike.
	 *
	 * @since 1.4.2
	 * @return array<int, array<string, mixed>>
	 */
	public static function campaigns(): array {
		$link = static function ( string $medium ): string {
			return add_query_arg(
				[
					'utm_source'   => 'suremembers-core-dashboard',
					'utm_medium'   => $medium,
					'utm_campaign' => 'bfcm-2026',
				],
				'https://suremembers.com/pricing/'
			);
		};

		$price = static function ( string $name, string $year, string $year_was, string $life = '', string $life_was = '' ): array {
			$parts = [
				[
					'text'  => $name,
					'style' => 'bold',
				],
				[
					'text'  => $year,
					'style' => 'accent',
				],
				[
					'text'  => $year_was,
					'style' => 'strike',
				],
				[ 'text' => __( 'first year', 'suremembers-core' ) ],
			];

			if ( '' !== $life ) {
				$parts[] = [ 'text' => __( 'or', 'suremembers-core' ) ];
				$parts[] = [
					'text'  => $life,
					'style' => 'accent',
				];
				$parts[] = [
					'text'  => $life_was,
					'style' => 'strike',
				];
				$parts[] = [ 'text' => __( 'lifetime', 'suremembers-core' ) ];
			}

			return $parts;
		};

		return [
			[
				'id'         => 'suremembers-bfcm-2026',
				// Matches suremembers.com/pricing: "Valid: 16 Nov - 06 Dec 2026", Pacific Time.
				'timezone'   => 'America/Los_Angeles',
				'starts_at'  => '2026-11-16 00:00:00',
				'ends_at'    => '2026-12-06 23:59:59',
				'placements' => [
					[
						'slot'     => 'top_bar',
						'audience' => 'all',
						'badge'    => __( 'Live now', 'suremembers-core' ),
						'items'    => [
							[
								[
									'text'  => __( 'Black Friday Super Sale', 'suremembers-core' ),
									'style' => 'bold',
								],
							],
							[
								[
									'text'  => __( 'Up to 43% OFF', 'suremembers-core' ),
									'style' => 'accent',
								],
								[ 'text' => __( 'every SureMembers plan', 'suremembers-core' ) ],
							],
							$price( __( 'Starter', 'suremembers-core' ), '$69', '$99' ),
							$price( __( 'Pro', 'suremembers-core' ), '$129', '$199' ),
							$price( __( 'Business', 'suremembers-core' ), '$169', '$299', '$499', '$699' ),
							$price( __( "Creator's Suite", 'suremembers-core' ), '$189', '$299', '$599', '$899' ),
							$price( __( 'Agency Suite', 'suremembers-core' ), '$279', '$449', '$799', '$1,299' ),
						],
						'cta'      => [
							'label' => __( 'Grab the Deal', 'suremembers-core' ),
							'url'   => $link( 'top-bar' ),
						],
					],
					[
						'slot'           => 'upgrade_card',
						'audience'       => 'free',
						'ribbon'         => __( 'Black Friday', 'suremembers-core' ),
						'discount_badge' => __( '33% OFF', 'suremembers-core' ),
						'price'          => '$599',
						'regular_price'  => '$899',
						'price_note'     => __( 'lifetime', 'suremembers-core' ),
						'savings'        => __( 'Save $300', 'suremembers-core' ),
						'plan'           => __( "Creator's Suite", 'suremembers-core' ),
						'plan_details'   => __( 'SureMembers, SureDash, Presto Player & Presto Capture Pro · Up to 10 sites', 'suremembers-core' ),
						'cta'            => [
							'label' => __( 'Upgrade Now', 'suremembers-core' ),
							'url'   => $link( 'upgrade-card' ),
						],
						'countdown'      => __( 'Black Friday price ends in', 'suremembers-core' ),
					],
				],
			],
		];
	}

	/**
	 * The first campaign that is live and not dismissed, with only the
	 * placements meant for this site, for the dashboard's localized data. Null
	 * when there is nothing to show.
	 *
	 * @since 1.4.2
	 * @param int|null $now Unix time to check the window against. Defaults to now.
	 * @return array{id: string, ends_at: int, slots: array<string, array<string, mixed>>}|null
	 */
	public static function get_for_dashboard( ?int $now = null ): ?array {
		$user_id = get_current_user_id();

		if ( ! $user_id || ! current_user_can( 'manage_options' ) ) {
			return null;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$now       = $now ?? time();
		$dismissed = self::get_dismissed( $user_id );
		$audience  = is_plugin_active( 'suremembers/suremembers.php' ) ? 'pro' : 'free';

		foreach ( self::campaigns() as $campaign ) {
			$id = is_string( $campaign['id'] ?? null ) ? $campaign['id'] : '';
			if ( '' === $id || in_array( $id, $dismissed, true ) ) {
				continue;
			}

			$timezone = is_string( $campaign['timezone'] ?? null ) ? $campaign['timezone'] : 'UTC';
			$starts   = self::parse_time( $campaign['starts_at'] ?? null, $timezone );
			$ends     = self::parse_time( $campaign['ends_at'] ?? null, $timezone );
			if ( ! $starts || ! $ends || $now < $starts || $now > $ends ) {
				continue;
			}

			$slots      = [];
			$placements = is_array( $campaign['placements'] ?? null ) ? $campaign['placements'] : [];
			foreach ( $placements as $placement ) {
				if ( ! is_array( $placement ) ) {
					continue;
				}

				$slot   = is_string( $placement['slot'] ?? null ) ? $placement['slot'] : '';
				$target = is_string( $placement['audience'] ?? null ) ? $placement['audience'] : 'all';
				if ( isset( $slots[ $slot ] ) || ! in_array( $slot, self::SLOTS, true ) || ! in_array( $target, [ 'all', $audience ], true ) ) {
					continue;
				}

				unset( $placement['slot'], $placement['audience'] );
				$slots[ $slot ] = $placement;
			}

			if ( [] !== $slots ) {
				return [
					'id'      => $id,
					'ends_at' => $ends,
					'slots'   => $slots,
				];
			}
		}

		return null;
	}

	/**
	 * Register the dismiss route.
	 *
	 * @since 1.4.2
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			'suremembers/v1',
			'/promotion/dismiss',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'dismiss' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => [
					'id' => [
						'type'     => 'string',
						'required' => true,
					],
				],
			]
		);
	}

	/**
	 * Dismiss a campaign for the current user, for good. Only known campaign
	 * ids are stored, so the list cannot be filled from the browser.
	 *
	 * @since 1.4.2
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function dismiss( $request ): \WP_REST_Response {
		$id    = $request->get_param( 'id' );
		$known = array_column( self::campaigns(), 'id' );

		if ( ! is_string( $id ) || ! in_array( $id, $known, true ) ) {
			return new \WP_REST_Response( [ 'dismissed' => false ], 400 );
		}

		$user_id   = get_current_user_id();
		$dismissed = self::get_dismissed( $user_id );

		if ( ! in_array( $id, $dismissed, true ) ) {
			$dismissed[] = $id;
			update_user_meta( $user_id, self::DISMISSED, $dismissed );
		}

		return new \WP_REST_Response( [ 'dismissed' => true ], 200 );
	}

	/**
	 * Campaign ids the user has dismissed.
	 *
	 * @since 1.4.2
	 * @param int $user_id User ID.
	 * @return array<int, string>
	 */
	private static function get_dismissed( int $user_id ): array {
		$ids = get_user_meta( $user_id, self::DISMISSED, true );

		return is_array( $ids ) ? array_values( array_filter( $ids, 'is_string' ) ) : [];
	}

	/**
	 * Parse a `Y-m-d H:i:s` date in a timezone into Unix time, or 0 when the
	 * value or timezone is invalid. Values PHP would roll over (2026-12-32)
	 * count as invalid.
	 *
	 * @since 1.4.2
	 * @param mixed  $value    Date string.
	 * @param string $timezone PHP timezone identifier.
	 * @return int
	 */
	private static function parse_time( $value, string $timezone ): int {
		if ( ! is_string( $value ) ) {
			return 0;
		}

		try {
			$zone = new \DateTimeZone( $timezone );
		} catch ( \Exception $e ) {
			return 0;
		}

		$date   = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, $zone );
		$errors = \DateTimeImmutable::getLastErrors();

		if ( false === $date || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
			return 0;
		}

		return $date->getTimestamp();
	}
}
