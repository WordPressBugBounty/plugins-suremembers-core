# Membership Analytics — Access Logs, Backfill & Dashboard Tab

> Goal: show per-membership growth analytics (joins, cancellations, net growth, active members) over a selectable timespan in a new **Analytics** tab in the SureMembers admin.

## Architecture

| Piece | Location |
|---|---|
| Action Scheduler library (v3.9.3, copied from SureDash) | `lib/action-scheduler/` — required in `plugin-loader.php` constructor |
| Access logs class (table, live logging, backfill, queries) | `inc/access-logs.php` (`SureMembersCore\Inc\Access_Logs`) |
| REST router | `inc/routers/analytics.php` → route `POST suremembers/v1/get-analytics` (admin-only) |
| Route registration | `inc/routes.php` (`get-analytics` entry) |
| Install hooks | `inc/activator.php` (activation) + `inc/updates.php` (upgrade) → `Access_Logs::install()` |
| Bootstrapping | `plugin-loader.php` → `Access_Logs::get_instance()` in `load_classes()` |
| React tab | `src/Dashboard/Tabs/Analytics/` + registration in `src/TabContent.js`, `src/Dashboard/components/Header.js`, API helper in `src/Api.js` (`getAnalytics`) |

## Custom table

`{$wpdb->prefix}suremembers_access_logs` — the plugin's first custom table. Append-only event log:

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AI | PK |
| `user_id` | BIGINT UNSIGNED | indexed |
| `access_group_id` | BIGINT UNSIGNED | part of `group_date` index |
| `event` | VARCHAR(20) | `grant` or `revoke` |
| `source` | VARCHAR(50) | integration that caused it: `default`, `surecart`, `sureforms`, … |
| `created_at` | DATETIME | site-local time (consistent with `current_time()` used by Access meta) |

Indexes: `(access_group_id, created_at)` for the chart queries, `(user_id)`.

## Live logging

`Access_Logs` listens to the existing actions — **no changes to grant/revoke logic**:

- `suremembers_user_access_group_granted` → `grant` row; source read from the per-group user meta's `integration` key (meta is written before the action fires).
- `suremembers_user_access_group_revoked` → `revoke` row (source `default`; the integration key is unset by revoke before the hook fires).

## Install & one-time backfill

`Access_Logs::install()` runs from **both** `Activator::activate()` and `Updates::init()` and is guarded by the `suremembers_access_log_backfill` option, so it executes once ever: creates the table (dbDelta) and sets the option to `pending`.

Backfill runs via **Action Scheduler** (not WP-Cron — works on sites with `DISABLE_WP_CRON`):

1. On `admin_init`, `maybe_schedule_backfill()` enqueues `suremembers_access_log_backfill_batch` (async action, group `suremembers`) when the option is `pending` and no action is queued. Self-healing: if a run dies, the next admin visit re-queues it.
2. Each batch reads 200 `suremembers_user_access_group_{id}` usermeta rows (keyset pagination on `umeta_id`, numeric-suffix keys only), reconstructing events: `created` → `grant` row, `status === 'revoked' && modified` → `revoke` row. Source taken from the meta's `integration` key.
3. Inserts are idempotent — skipped when an identical `(user_id, group, event, created_at)` row exists — so the backfill is safely re-runnable.
4. Next batch is chained with the last processed `umeta_id`; when a batch comes back short, the option flips to `done`.

**Known limitation:** usermeta only preserves first-join (`created`) and latest revoke (`modified`), so pre-upgrade join→cancel→rejoin churn cannot be reconstructed. Post-upgrade data is fully event-accurate.

## REST endpoint

`POST /wp-json/suremembers/v1/get-analytics` (admin permission + `wp_rest` nonce):

Request: `{ access_group_id (0 = all), from (Y-m-d), to (Y-m-d), interval (day|week|month) }`

Response `data`:
- `series` — `[ { period, grants, revokes } ]` bucketed by `DATE_FORMAT` (`%Y-%m-%d`, `%x-W%v`, `%Y-%m`)
- `totals` — `{ grants, revokes, sources: { surecart: { grants, revokes }, … } }`
- `status_counts` — per group `{ active, revoked }` current counts (from usermeta — source of truth for *current* state)
- `groups` — active access groups for the filter dropdown
- `backfill` — `pending`/`done`, drives the "importing historical data" notice

## Analytics tab (React)

`src/Dashboard/Tabs/Analytics/AnalyticsPage.js`, ForceUI components throughout:

- Filters: membership `Select` (All + each active group) and timespan `Select` (7/30 days → daily, 90 days → weekly, 12 months → monthly buckets).
- Stat cards: New Members, Cancellations, Net Growth, Active Members.
- `AreaChart` (ForceUI/recharts) of grants vs revokes; day-interval series are zero-filled client-side for a continuous axis.
- "Joins by Source" badges from `totals.sources`.
- Yellow notice while `backfill === 'pending'`.

Tab slug `analytics` registered in `TabContent.js` switch and `Header.js` `Tabs.Group` (between Users and Settings). The store's `NAVIGATE_TO` has no tab whitelist, so deep-linking `?page=suremembers&tab=analytics` works.

## QA checklist

- [ ] Fresh activation: table created, backfill flag set, completes immediately (`done`) on empty sites.
- [ ] Upgrade path: existing site with members — backfill reconstructs joins; chart shows historical data; notice disappears when done.
- [ ] Grant/revoke via admin UI, SureCart, expiration — rows logged with correct source.
- [ ] `DISABLE_WP_CRON` site: backfill still completes (Action Scheduler async runner).
- [ ] Large site (10k+ members): batches chain without timeouts; admin pages stay responsive.
- [ ] Timespan/membership filters return correct buckets across month boundaries and DST.
- [ ] Uninstall: decide whether `uninstall.php` should drop the table when delete-data is enabled (TODO).

## Future

- Segment chart by source (multi-series) once SureForms integration lands ([#642](https://github.com/brainstormforce/suremembers/issues/642)).
- Churn/retention metrics (needs post-upgrade event history to accumulate).
- Per-group drill-down rows on the Memberships list.
