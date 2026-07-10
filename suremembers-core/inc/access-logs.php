<?php
/**
 * Access Logs.
 *
 * Records membership grant/revoke events in a custom table so the dashboard
 * can render time-based analytics (joins, cancellations) per access group.
 * Historical data is backfilled from existing user meta in background batches
 * via Action Scheduler (works on sites with WP-Cron disabled).
 *
 * @package suremembers
 *
 * @since x.x.x
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Access Logs.
 *
 * @since x.x.x
 */
class Access_Logs {
	use Get_Instance;

	/**
	 * Option storing backfill state: '' (never ran), 'pending' or 'done'.
	 *
	 * @since x.x.x
	 */
	public const BACKFILL_OPTION = 'suremembers_access_log_backfill';

	/**
	 * Action Scheduler hook used to process one backfill batch.
	 *
	 * @since x.x.x
	 */
	public const BACKFILL_ACTION = 'suremembers_access_log_backfill_batch';

	/**
	 * Action Scheduler group for all SureMembers actions.
	 *
	 * @since x.x.x
	 */
	public const AS_GROUP = 'suremembers';

	/**
	 * Number of user meta rows processed per backfill batch.
	 *
	 * @since x.x.x
	 */
	public const BATCH_SIZE = 200;

	/**
	 * Constructor.
	 *
	 * @since x.x.x
	 */
	public function __construct() {
		add_action( 'suremembers_user_access_group_granted', [ $this, 'log_grant' ], 10, 2 );
		add_action( 'suremembers_user_access_group_revoked', [ $this, 'log_revoke' ], 10, 2 );
		add_action( self::BACKFILL_ACTION, [ $this, 'process_backfill_batch' ] );
		add_action( 'admin_init', [ $this, 'maybe_schedule_backfill' ] );
	}

	/**
	 * Get the access logs table name with the site prefix.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'suremembers_access_logs';
	}

	/**
	 * Create the access logs table. Safe to call repeatedly (dbDelta).
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			access_group_id BIGINT(20) UNSIGNED NOT NULL,
			event VARCHAR(20) NOT NULL,
			source VARCHAR(50) NOT NULL DEFAULT 'default',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY group_date (access_group_id, created_at),
			KEY user_id (user_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Whether the access logs table exists.
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;
		$table_name = self::get_table_name();
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name; //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Install the table and flag the backfill as pending. Runs once ever —
	 * called from both Activator (new installs) and Updates (upgrades).
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function install() {
		if ( ! empty( get_option( self::BACKFILL_OPTION ) ) ) {
			return;
		}

		self::create_table();
		update_option( self::BACKFILL_OPTION, 'pending' );
	}

	/**
	 * Insert a single log row.
	 *
	 * @param int    $user_id         User ID.
	 * @param int    $access_group_id Access group ID.
	 * @param string $event           Event type: 'grant' or 'revoke'.
	 * @param string $source          Integration source ('default', 'surecart', 'sureforms', ...).
	 * @param string $created_at      MySQL datetime in site timezone. Defaults to now.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function log( $user_id, $access_group_id, $event, $source = 'default', $created_at = '' ) {
		global $wpdb;

		if ( empty( $user_id ) || empty( $access_group_id ) || ! self::table_exists() ) {
			return;
		}

		$wpdb->insert( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			self::get_table_name(),
			[
				'user_id'         => absint( $user_id ),
				'access_group_id' => absint( $access_group_id ),
				'event'           => sanitize_key( $event ),
				'source'          => sanitize_key( $source ),
				'created_at'      => ! empty( $created_at ) ? $created_at : current_time( 'mysql' ),
			],
			[ '%d', '%d', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Log a grant event. Hooked to `suremembers_user_access_group_granted`.
	 *
	 * @param int $user_id         User ID.
	 * @param int $access_group_id Access group ID.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function log_grant( $user_id, $access_group_id ) {
		$source     = 'default';
		$group_meta = get_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$access_group_id}", true );
		if ( is_array( $group_meta ) && ! empty( $group_meta['integration'] ) && is_string( $group_meta['integration'] ) ) {
			$source = $group_meta['integration'];
		}

		self::log( $user_id, $access_group_id, 'grant', $source );
	}

	/**
	 * Log a revoke event. Hooked to `suremembers_user_access_group_revoked`.
	 *
	 * @param int $user_id         User ID.
	 * @param int $access_group_id Access group ID.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function log_revoke( $user_id, $access_group_id ) {
		self::log( $user_id, $access_group_id, 'revoke' );
	}

	/**
	 * Enqueue the first backfill batch when pending and not already queued.
	 * Self-healing: re-queues if a previous run died before completion.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function maybe_schedule_backfill() {
		$state = get_option( self::BACKFILL_OPTION );

		// Self-install: covers sites where neither activation nor a version
		// bump ran (e.g. the plugin was updated in place during development).
		if ( empty( $state ) ) {
			self::install();
			$state = get_option( self::BACKFILL_OPTION );
		}

		if ( $state !== 'pending' ) {
			return;
		}

		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_enqueue_async_action' ) ) {
			return;
		}

		if ( as_has_scheduled_action( self::BACKFILL_ACTION ) ) {
			return;
		}

		as_enqueue_async_action( self::BACKFILL_ACTION, [ 0 ], self::AS_GROUP );
	}

	/**
	 * Process one backfill batch: read per-group user meta rows after the
	 * given umeta_id, reconstruct grant/revoke events from their `created`
	 * and `modified` timestamps, then chain the next batch.
	 *
	 * @param int $last_umeta_id Process rows with umeta_id greater than this.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public function process_backfill_batch( $last_umeta_id = 0 ) {
		global $wpdb;

		if ( get_option( self::BACKFILL_OPTION ) !== 'pending' || ! self::table_exists() ) {
			return;
		}

		$meta_prefix = SUREMEMBERS_USER_META . '_';

		$rows = $wpdb->get_results( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT umeta_id, user_id, meta_key, meta_value FROM {$wpdb->usermeta}
				WHERE umeta_id > %d AND meta_key LIKE %s
				ORDER BY umeta_id ASC LIMIT %d",
				absint( $last_umeta_id ),
				$wpdb->esc_like( $meta_prefix ) . '%',
				self::BATCH_SIZE
			)
		);

		if ( empty( $rows ) ) {
			update_option( self::BACKFILL_OPTION, 'done' );
			return;
		}

		$max_umeta_id = $last_umeta_id;

		foreach ( $rows as $row ) {
			$max_umeta_id = max( $max_umeta_id, (int) $row->umeta_id );

			$access_group_id = substr( (string) $row->meta_key, strlen( $meta_prefix ) );
			// Skip keys whose suffix is not purely numeric (e.g. the main list key has no suffix).
			if ( ! ctype_digit( $access_group_id ) ) {
				continue;
			}

			$data = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $data ) ) {
				continue;
			}

			$source = ! empty( $data['integration'] ) && is_string( $data['integration'] ) ? $data['integration'] : 'default';

			if ( ! empty( $data['created'] ) ) {
				// Meta timestamps come from current_time( 'timestamp' ) — already
				// shifted to site-local time, so gmdate() yields site-local wall time.
				self::backfill_insert( (int) $row->user_id, (int) $access_group_id, 'grant', $source, gmdate( 'Y-m-d H:i:s', (int) $data['created'] ) );
			}

			if ( ! empty( $data['status'] ) && $data['status'] === 'revoked' && ! empty( $data['modified'] ) ) {
				self::backfill_insert( (int) $row->user_id, (int) $access_group_id, 'revoke', $source, gmdate( 'Y-m-d H:i:s', (int) $data['modified'] ) );
			}
		}

		if ( count( $rows ) < self::BATCH_SIZE ) {
			update_option( self::BACKFILL_OPTION, 'done' );
			return;
		}

		as_enqueue_async_action( self::BACKFILL_ACTION, [ $max_umeta_id ], self::AS_GROUP );
	}

	/**
	 * Get grant/revoke counts grouped by interval for the analytics charts.
	 *
	 * @param array<string, mixed> $args {
	 *     Query arguments.
	 *
	 *     @type int    $access_group_id Limit to one access group. 0 for all groups.
	 *     @type string $from            Start date (Y-m-d).
	 *     @type string $to              End date (Y-m-d, inclusive).
	 *     @type string $interval        Bucket size: 'day', 'week' or 'month'.
	 * }
	 *
	 * @since x.x.x
	 *
	 * @return array<int, array<string, mixed>> Series of [ period, grants, revokes ] rows.
	 */
	public static function get_stats( $args = [] ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return [];
		}

		$defaults = [
			'access_group_id' => 0,
			'from'            => gmdate( 'Y-m-d', strtotime( '-29 days', (int) current_time( 'timestamp' ) ) ), //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			'to'              => gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ), //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			'interval'        => 'day',
		];
		$args     = wp_parse_args( $args, $defaults );

		$formats = [
			'day'   => '%Y-%m-%d',
			'week'  => '%x-W%v',
			'month' => '%Y-%m',
		];
		$format  = $formats[ $args['interval'] ] ?? $formats['day'];

		$table_name = self::get_table_name();
		$where      = 'WHERE created_at >= %s AND created_at <= %s';
		$params     = [ $format, $args['from'] . ' 00:00:00', $args['to'] . ' 23:59:59' ];

		if ( ! empty( $args['access_group_id'] ) ) {
			$where   .= ' AND access_group_id = %d';
			$params[] = absint( $args['access_group_id'] );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table name and placeholders-built WHERE are prepared above.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT( created_at, %s ) AS period,
					SUM( CASE WHEN event = 'grant' THEN 1 ELSE 0 END ) AS grants,
					SUM( CASE WHEN event = 'revoke' THEN 1 ELSE 0 END ) AS revokes
				FROM {$table_name} {$where}
				GROUP BY period ORDER BY period ASC",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( empty( $results ) || ! is_array( $results ) ) {
			return [];
		}

		return array_map(
			static function ( $row ) {
				return [
					'period'  => (string) $row['period'],
					'grants'  => (int) $row['grants'],
					'revokes' => (int) $row['revokes'],
				];
			},
			$results
		);
	}

	/**
	 * Get total grant/revoke counts and per-source breakdown for a range.
	 *
	 * @param array<string, mixed> $args Same arguments as get_stats().
	 *
	 * @since x.x.x
	 *
	 * @return array<string, mixed>
	 */
	public static function get_totals( $args = [] ) {
		global $wpdb;

		$totals = [
			'grants'  => 0,
			'revokes' => 0,
			'sources' => [],
		];

		if ( ! self::table_exists() ) {
			return $totals;
		}

		$defaults = [
			'access_group_id' => 0,
			'from'            => gmdate( 'Y-m-d', strtotime( '-29 days', (int) current_time( 'timestamp' ) ) ), //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			'to'              => gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ), //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		];
		$args     = wp_parse_args( $args, $defaults );

		$table_name = self::get_table_name();
		$where      = 'WHERE created_at >= %s AND created_at <= %s';
		$params     = [ $args['from'] . ' 00:00:00', $args['to'] . ' 23:59:59' ];

		if ( ! empty( $args['access_group_id'] ) ) {
			$where   .= ' AND access_group_id = %d';
			$params[] = absint( $args['access_group_id'] );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom table name and placeholders-built WHERE are prepared above.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source, event, COUNT(*) AS total FROM {$table_name} {$where} GROUP BY source, event",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( empty( $results ) || ! is_array( $results ) ) {
			return $totals;
		}

		foreach ( $results as $row ) {
			$event = (string) $row['event'];
			$count = (int) $row['total'];

			if ( $event === 'grant' ) {
				$totals['grants'] += $count;
			} elseif ( $event === 'revoke' ) {
				$totals['revokes'] += $count;
			}

			$source = (string) $row['source'];
			if ( ! isset( $totals['sources'][ $source ] ) ) {
				$totals['sources'][ $source ] = [
					'grants'  => 0,
					'revokes' => 0,
				];
			}
			$totals['sources'][ $source ][ 'grant' === $event ? 'grants' : 'revokes' ] += $count;
		}

		return $totals;
	}

	/**
	 * Get grant/revoke counts per access group for a date range,
	 * ordered by most grants first.
	 *
	 * @param array<string, mixed> $args {
	 *     Query arguments.
	 *
	 *     @type int    $access_group_id Limit to one access group. 0 for all groups.
	 *     @type string $from            Start date (Y-m-d).
	 *     @type string $to              End date (Y-m-d, inclusive).
	 * }
	 *
	 * @since x.x.x
	 *
	 * @return array<int, array<string, int>> Rows of [ access_group_id, grants, revokes ].
	 */
	public static function get_group_breakdown( $args = [] ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return [];
		}

		$defaults = [
			'access_group_id' => 0,
			'from'            => gmdate( 'Y-m-d', strtotime( '-29 days', (int) current_time( 'timestamp' ) ) ), //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			'to'              => gmdate( 'Y-m-d', (int) current_time( 'timestamp' ) ), //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		];
		$args     = wp_parse_args( $args, $defaults );

		$table_name = self::get_table_name();
		$where      = 'WHERE created_at >= %s AND created_at <= %s';
		$params     = [ $args['from'] . ' 00:00:00', $args['to'] . ' 23:59:59' ];

		if ( ! empty( $args['access_group_id'] ) ) {
			$where   .= ' AND access_group_id = %d';
			$params[] = absint( $args['access_group_id'] );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom table name and placeholders-built WHERE are prepared above.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT access_group_id,
					SUM( CASE WHEN event = 'grant' THEN 1 ELSE 0 END ) AS grants,
					SUM( CASE WHEN event = 'revoke' THEN 1 ELSE 0 END ) AS revokes
				FROM {$table_name} {$where}
				GROUP BY access_group_id ORDER BY grants DESC",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( empty( $results ) || ! is_array( $results ) ) {
			return [];
		}

		return array_map(
			static function ( $row ) {
				return [
					'access_group_id' => (int) $row['access_group_id'],
					'grants'          => (int) $row['grants'],
					'revokes'         => (int) $row['revokes'],
				];
			},
			$results
		);
	}

	/**
	 * Get the most recent grant/revoke events with user display names,
	 * for the dashboard activity feed. Not date-filtered by design.
	 *
	 * @param int $limit Maximum number of events to return.
	 *
	 * @since x.x.x
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_recent_activity( $limit = 15 ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return [];
		}

		$table_name = self::get_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPressVIPMinimum.Variables.RestrictedVariables.user_meta__wpdb__users -- Custom table name, query is prepared; read-only join on users for display names.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT l.user_id, l.access_group_id, l.event, l.source, l.created_at, u.display_name, u.user_email
				FROM {$table_name} l
				LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
				ORDER BY l.created_at DESC, l.id DESC
				LIMIT %d",
				absint( $limit )
			),
			ARRAY_A
		);
		// phpcs:enable

		if ( empty( $results ) || ! is_array( $results ) ) {
			return [];
		}

		return array_map(
			static function ( $row ) {
				return [
					'user_id'         => (int) $row['user_id'],
					'user_name'       => ! empty( $row['display_name'] ) ? (string) $row['display_name'] : __( 'Deleted user', 'suremembers-core' ),
					'user_email'      => (string) ( $row['user_email'] ?? '' ),
					'access_group_id' => (int) $row['access_group_id'],
					'event'           => (string) $row['event'],
					'source'          => (string) $row['source'],
					'created_at'      => (string) $row['created_at'],
				];
			},
			$results
		);
	}

	/**
	 * Count users currently holding the given status for an access group,
	 * based on the per-group user meta (source of truth for current state).
	 *
	 * @param int    $access_group_id Access group ID.
	 * @param string $status          Status to count: 'active' or 'revoked'.
	 *
	 * @since x.x.x
	 *
	 * @return int
	 */
	public static function get_status_count( $access_group_id, $status = 'active' ) {
		global $wpdb;

		$status = sanitize_key( $status );

		$count = $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
				SUREMEMBERS_USER_META . '_' . absint( $access_group_id ),
				'%' . $wpdb->esc_like( sprintf( '"status";s:%d:"%s"', strlen( $status ), $status ) ) . '%'
			)
		);

		return (int) $count;
	}

	/**
	 * Insert a backfilled row unless an identical event already exists,
	 * keeping the backfill safely re-runnable.
	 *
	 * @param int    $user_id         User ID.
	 * @param int    $access_group_id Access group ID.
	 * @param string $event           Event type.
	 * @param string $source          Integration source.
	 * @param string $created_at      Site-local MySQL datetime of the original event.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	private static function backfill_insert( $user_id, $access_group_id, $event, $source, $created_at ) {
		global $wpdb;

		$table_name = self::get_table_name();

		$exists = $wpdb->get_var( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE user_id = %d AND access_group_id = %d AND event = %s AND created_at = %s LIMIT 1", //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$access_group_id,
				$event,
				$created_at
			)
		);

		if ( ! empty( $exists ) ) {
			return;
		}

		self::log( $user_id, $access_group_id, $event, $source, $created_at );
	}
}
