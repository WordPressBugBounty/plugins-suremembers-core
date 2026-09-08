<?php
/**
 * SureForms Integration.
 *
 * Registers SureMembers in the SureForms "Add Integration" list and grants /
 * revokes access-group membership in response to SureForms payment-lifecycle
 * events.
 *
 * Division of responsibility (mirrors the SureCart integration):
 *  - SureForms owns the UI. The form ↔ access-group mapping is configured inside
 *    SureForms (Form Settings → Integrations) and stored in the form's
 *    `_srfm_native_integrations` post meta.
 *  - SureMembers reacts. Grant / revoke is driven entirely by the payment
 *    lifecycle actions emitted by SureForms (`srfm_payment_completed`,
 *    `srfm_subscription_renewed`, `srfm_subscription_canceled`,
 *    `srfm_payment_refunded`), so access tracks the real payment state rather
 *    than the raw form submission.
 *
 * @package suremembers
 *
 * @since 1.3.0
 */

namespace SureMembersCore\Integrations;

use SureMembersCore\Inc\Access;
use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * SureForms Integration.
 *
 * @since 1.3.0
 */
class Sureforms_Integration {
	use Get_Instance;

	/**
	 * Integration identifier, as registered in the SureForms catalog.
	 *
	 * @since 1.3.0
	 */
	public const INTEGRATION_ID = 'suremembers';

	/**
	 * Grant action identifier (the sanitized SureForms action name).
	 *
	 * SureForms derives the action key from the action `name` by lowercasing and
	 * replacing spaces with underscores, so "Grant Access" becomes "grant_access".
	 *
	 * @since 1.3.0
	 */
	public const ACTION_GRANT = 'grant_access';

	/**
	 * Fully-qualified SureForms action hook that dispatches the "Grant Access" action.
	 *
	 * SureForms' WordPress provider fires `do_action( 'srfm_' . $action, $data )`
	 * where `$action` is the handler's `action` key ("suremembers_grant_access"),
	 * and then reads the response back from
	 * `$GLOBALS['srfm_action_results']['srfm_suremembers_grant_access']`. A listener
	 * on this hook must populate that slot, or SureForms reports
	 * "No handler responded to action: suremembers_grant_access" and never counts the run.
	 *
	 * @since 1.3.0
	 */
	public const ACTION_HOOK = 'srfm_suremembers_grant_access';

	/**
	 * Source string recorded against each granted access group ("granted via").
	 *
	 * @since 1.3.0
	 */
	public const SOURCE = 'sureforms';

	/**
	 * Post meta key SureForms uses to store per-form integration workflows.
	 *
	 * @since 1.3.0
	 */
	public const FORM_META = '_srfm_native_integrations';

	/**
	 * Statuses on a SureForms payment row that represent a paid, access-granting state.
	 *
	 * One-time captures land as `succeeded`; an activated subscription's payment row
	 * is `active`. Pending / failed / refunded / canceled rows are intentionally
	 * excluded so access is only granted for money actually received.
	 *
	 * @since 1.3.0
	 */
	public const PAID_PAYMENT_STATUSES = [ 'succeeded', 'active' ];

	/**
	 * Constructor — wire registration filters and lifecycle listeners.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		// Register SureMembers in the SureForms integrations catalog + dropdown.
		add_filter( 'srfm_pro_native_integrations_json_configs', [ $this, 'register_integration_config' ] );
		add_filter( 'srfm_suremembers_get_access_groups', [ $this, 'get_access_groups_options' ] );

		// Respond to the submit-time workflow action so SureForms' "Test action"
		// succeeds and each submission is counted as a run. The action fires before
		// (and independently of) payment, so the real grant is deferred to the
		// payment-lifecycle listeners below — this handler only acknowledges.
		add_action( self::ACTION_HOOK, [ $this, 'acknowledge_grant_action' ], 10, 1 );

		// Primary grant signal. SureForms only fires `srfm_payment_completed`
		// synchronously for some gateways (Stripe) — one-time PayPal, for example,
		// has no completion action at all (no PAYMENT.CAPTURE.COMPLETED webhook case
		// and the synchronous payment↔entry link does not survive PayPal's separate
		// capture / submit requests). `srfm_after_submission_process`, by contrast,
		// fires for every gateway and only *after* a successful submission — and
		// SureForms aborts submission before this hook when payment verification
		// fails, so reaching it on a payment form means the payment succeeded.
		add_action( 'srfm_after_submission_process', [ $this, 'maybe_grant_on_submission' ], 20, 1 );

		// Secondary / lifecycle signals. `srfm_payment_completed` still covers the
		// gateways that fire it (and is idempotent against the submission grant);
		// the rest drive renewals and revocation, which have no form submission.
		add_action( 'srfm_payment_completed', [ $this, 'on_payment_completed' ], 10, 2 );
		add_action( 'srfm_subscription_renewed', [ $this, 'on_subscription_renewed' ], 10, 3 );
		add_action( 'srfm_subscription_canceled', [ $this, 'on_subscription_canceled' ], 10, 2 );
		add_action( 'srfm_payment_refunded', [ $this, 'on_payment_refunded' ], 10, 4 );

		// Button-initiated refund / cancel. SureForms Pro's own Refund (admin) and
		// Cancel (frontend account page) buttons do NOT fire the lifecycle actions
		// above — they only run these processing filters, and the PayPal webhook
		// that would normally fire the action is de-duplicated afterwards
		// (check_if_refund_already_exists). Listen at a late priority, after the
		// gateway has processed and updated the payment status, to revoke for
		// button-initiated full refunds and cancellations across every gateway.
		add_filter( 'srfm_process_transaction_refund', [ $this, 'on_admin_refund_processed' ], 99, 2 );
		add_filter( 'srfm_process_subscription_cancellation', [ $this, 'on_admin_subscription_canceled' ], 99, 2 );
	}

	/**
	 * Acknowledge the SureForms "Grant Access" workflow action.
	 *
	 * SureForms dispatches this action at submission time (via its workflow
	 * processor) and on "Test action", then reads the result back from
	 * `$GLOBALS['srfm_action_results'][ self::ACTION_HOOK ]`. Without a response
	 * SureForms reports "No handler responded…" and records the workflow as failed,
	 * so it is never counted as a run.
	 *
	 * Access itself is granted by the payment-lifecycle listeners
	 * (`on_payment_completed()` et al.), not here: the submit-time action fires
	 * before — and regardless of — payment, so granting at this point would hand
	 * out access for an unpaid (or failed) checkout. This handler therefore only
	 * acknowledges that the workflow ran; the grant follows when the payment
	 * actually reaches the `succeeded` state.
	 *
	 * @param array<string, mixed> $data Submission data SureForms passes to the action (unused).
	 * @return void
	 * @since 1.3.0
	 */
	public function acknowledge_grant_action( $data = [] ) {
		if ( ! isset( $GLOBALS['srfm_action_results'] ) || ! is_array( $GLOBALS['srfm_action_results'] ) ) {
			$GLOBALS['srfm_action_results'] = [];
		}

		$GLOBALS['srfm_action_results'][ self::ACTION_HOOK ] = [
			'success'  => true,
			'deferred' => true,
			'message'  => __( 'SureMembers is connected. Memberships will be granted automatically when the payment for this form completes, and revoked on cancellation or full refund.', 'suremembers-core' ),
		];
	}

	/**
	 * Register the SureMembers integration config in the SureForms catalog.
	 *
	 * Hooked on `srfm_pro_native_integrations_json_configs`; the returned config is
	 * merged into the catalog that powers the "Add Integration" list and the
	 * per-form integration UI.
	 *
	 * @param array<int, string> $configs Existing JSON config strings.
	 * @return array<int, string> Configs with the SureMembers integration appended.
	 * @since 1.3.0
	 */
	public function register_integration_config( $configs ) {
		if ( ! is_array( $configs ) ) {
			$configs = [];
		}

		$config = [
			'integrations' => [
				[
					'id'               => self::INTEGRATION_ID,
					'integration'      => [
						'name'        => __( 'SureMembers', 'suremembers-core' ),
						// Absolute URL: SureForms renders the icon directly when it starts
						// with "http" (see SureForms' integrations-utils useIntegrationIcon),
						// so no asset needs to ship inside SureForms.
						'icon'        => esc_url_raw( SUREMEMBERS_CORE_URL . 'admin/assets/images/icon.svg' ),
						'website_url' => 'https://suremembers.com',
						'docs_url'    => 'https://suremembers.com/docs/',
						'category'    => __( 'Membership', 'suremembers-core' ),
						'description' => __( 'Grant SureMembers memberships when a SureForms payment completes, and revoke them on cancellation or refund.', 'suremembers-core' ),
					],
					'provider'         => 'wordpress',
					'plugin_detection' => [
						'constant' => 'SUREMEMBERS_CORE_FILE',
						'slug'     => 'suremembers-core',
					],
					'auth'             => [
						'type'          => 'plugin_detection',
						'test_endpoint' => [
							'method'           => 'plugin_check',
							'success_criteria' => [
								'plugin_active' => true,
							],
						],
					],
					'actions'          => [
						[
							'name'        => 'Grant Access',
							'description' => __( 'Grant the selected memberships when a payment for this form completes. Access is revoked automatically when the subscription is cancelled or the payment is fully refunded.', 'suremembers-core' ),
							'provider'    => 'wordpress',
							'handler'     => [
								// Grant is driven by the payment-lifecycle hooks, not the
								// submit-time action, so no submit handler is registered.
								'type'   => 'action',
								'action' => 'suremembers_grant_access',
							],
							'fields'      => [
								[
									'key'         => 'access_group_ids',
									'label'       => __( 'Memberships', 'suremembers-core' ),
									'type'        => 'multi-select-async',
									'required'    => true,
									'placeholder' => __( 'Select memberships', 'suremembers-core' ),
									'description' => __( 'Members gain access to these memberships after a successful payment, and lose it when the subscription is cancelled or the payment is fully refunded.', 'suremembers-core' ),
								],
							],
							'apis'        => [
								'access_groups' => [
									'filter'        => 'suremembers_get_access_groups',
									'response_type' => 'select_options',
									'map'           => [
										'data'  => 'access_groups',
										'label' => 'label',
										'value' => 'value',
									],
									'target_fields' => [ 'access_group_ids' ],
									'dependencies'  => [],
								],
							],
						],
					],
					'settings_url'     => admin_url( 'admin.php?page=suremembers' ),
				],
			],
		];

		$encoded = wp_json_encode( $config );
		if ( is_string( $encoded ) ) {
			$configs[] = $encoded;
		}

		return $configs;
	}

	/**
	 * Provide the active access groups as options for the integration dropdown.
	 *
	 * Hooked on `srfm_suremembers_get_access_groups` — SureForms' WordPress provider
	 * applies this filter to populate the "Access Groups" multi-select.
	 *
	 * @param array<int, array<string, mixed>> $options Existing options (default empty).
	 * @return array<int, array<string, mixed>> Access groups as select options (each `value` int, `label` string).
	 * @since 1.3.0
	 */
	public function get_access_groups_options( $options = [] ) {
		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$access_groups = Access_Groups::get_active();
		if ( ! is_array( $access_groups ) || empty( $access_groups ) ) {
			return $options;
		}

		foreach ( $access_groups as $id => $title ) {
			$options[] = [
				'value' => intval( $id ),
				'label' => is_string( $title ) ? $title : (string) $title,
			];
		}

		return $options;
	}

	/**
	 * Grant mapped access groups when a payment completes.
	 *
	 * @param array<string, mixed> $payment Payment record (a `sureforms_payments` row).
	 * @param array<string, mixed> $context Resolved payment context.
	 * @return void
	 * @since 1.3.0
	 */
	public function on_payment_completed( $payment, $context ) {
		$user_id = $this->get_context_user_id( $context );
		if ( empty( $user_id ) ) {
			// Guest checkout — provision an account from the payment so the
			// membership can still be granted.
			$entry_id = is_array( $context ) && ! empty( $context['entry_id'] ) && is_numeric( $context['entry_id'] )
				? intval( $context['entry_id'] )
				: 0;
			$user_id  = $this->resolve_or_create_user_id( $payment, $entry_id );
		}

		if ( empty( $user_id ) ) {
			return;
		}

		$group_ids = $this->get_form_access_groups( $this->get_context_form_id( $context ) );
		if ( ! empty( $group_ids ) ) {
			Access::grant( $user_id, $group_ids, self::SOURCE );
		}
	}

	/**
	 * Grant mapped access groups after a successful, paid form submission.
	 *
	 * Hooked on `srfm_after_submission_process`, which SureForms fires for every
	 * gateway once a submission completes. Because SureForms aborts submission
	 * (before this hook) whenever payment verification fails, the hook firing on a
	 * payment-mapped form already implies the payment succeeded — but we still
	 * confirm a `succeeded` payment row exists so the workflow can never grant on a
	 * non-payment form that merely happens to carry the mapping.
	 *
	 * This is the gateway-agnostic counterpart to `on_payment_completed()`: it
	 * covers one-time PayPal (which fires no completion action), while the
	 * lifecycle listeners continue to handle renewals and revocation. Grants are
	 * idempotent (`Access::grant()` aborts when the group is already active), so
	 * overlapping with a gateway that *does* fire `srfm_payment_completed` is safe.
	 *
	 * @param array<string, mixed> $form_data Submission payload (includes `form_id` and `submission_id`).
	 * @return void
	 * @since 1.3.0
	 */
	public function maybe_grant_on_submission( $form_data ) {
		if ( ! is_array( $form_data ) ) {
			return;
		}

		$form_id  = isset( $form_data['form_id'] ) && is_numeric( $form_data['form_id'] ) ? intval( $form_data['form_id'] ) : 0;
		$entry_id = isset( $form_data['submission_id'] ) && is_numeric( $form_data['submission_id'] ) ? intval( $form_data['submission_id'] ) : 0;

		if ( empty( $form_id ) ) {
			return;
		}

		// Only act on forms mapped to SureMembers access groups.
		$group_ids = $this->get_form_access_groups( $form_id );
		if ( empty( $group_ids ) ) {
			return;
		}

		// Resolve who actually submitted this form. The payment must be bound to
		// this submitter: without it, an unlinked-entry fallback (see
		// get_succeeded_payment()) would attach access to the submitter off an
		// unrelated buyer's recent payment on the same form — letting a visitor who
		// skipped payment inherit someone else's completed purchase.
		$submitter = $this->get_submitter_identity( $entry_id );

		// Confirm the submission is backed by a real, succeeded payment that belongs
		// to this submitter.
		$payment = $this->get_succeeded_payment( $form_id, $entry_id, $submitter );
		if ( empty( $payment ) ) {
			return;
		}

		// Resolve the buyer, provisioning an account for guest checkouts so the
		// membership has a real WordPress user to attach to.
		$user_id = $this->resolve_or_create_user_id( $payment, $entry_id );
		if ( empty( $user_id ) ) {
			return;
		}

		Access::grant( $user_id, $group_ids, self::SOURCE );
	}

	/**
	 * Re-grant mapped access groups on a subscription renewal.
	 *
	 * `Access::grant()` aborts when the group is already active, so this is a no-op
	 * for healthy subscriptions and only restores access that had lapsed.
	 *
	 * @param array<string, mixed> $payment             Renewal payment record.
	 * @param array<string, mixed> $parent_subscription Parent subscription record.
	 * @param array<string, mixed> $context             Resolved payment context.
	 * @return void
	 * @since 1.3.0
	 */
	public function on_subscription_renewed( $payment, $parent_subscription, $context ) {
		$this->on_payment_completed( $payment, $context );
	}

	/**
	 * Revoke mapped access groups when a subscription is cancelled.
	 *
	 * @param array<string, mixed> $subscription_record Cancelled subscription record.
	 * @param array<string, mixed> $context             Resolved payment context.
	 * @return void
	 * @since 1.3.0
	 */
	public function on_subscription_canceled( $subscription_record, $context ) {
		$user_id = $this->get_context_user_id( $context );
		if ( empty( $user_id ) ) {
			return;
		}

		$group_ids = $this->get_form_access_groups( $this->get_context_form_id( $context ) );
		if ( ! empty( $group_ids ) ) {
			Access::revoke( $user_id, $group_ids );
		}
	}

	/**
	 * Revoke mapped access groups when a payment is fully refunded.
	 *
	 * A full refund pulls access (mirroring the SureCart integration); partial
	 * refunds leave access intact.
	 *
	 * @param array<string, mixed> $payment        Payment record.
	 * @param float                $refund_amount  Refunded amount for this event.
	 * @param bool                 $is_full_refund Whether cumulative refunds cover the full payment.
	 * @param array<string, mixed> $context        Resolved payment context.
	 * @return void
	 * @since 1.3.0
	 */
	public function on_payment_refunded( $payment, $refund_amount, $is_full_refund, $context ) {
		if ( ! $is_full_refund ) {
			return;
		}

		$user_id = $this->get_context_user_id( $context );
		if ( empty( $user_id ) ) {
			return;
		}

		$group_ids = $this->get_form_access_groups( $this->get_context_form_id( $context ) );
		if ( ! empty( $group_ids ) ) {
			Access::revoke( $user_id, $group_ids );
		}
	}

	/**
	 * Revoke access for a SureForms Pro button-initiated full refund.
	 *
	 * Filter callback on `srfm_process_transaction_refund` (the admin "Refund"
	 * button). The gateway handler at the default priority has already processed
	 * the refund and updated the payment status by the time this late-priority
	 * callback runs, so the freshly read status distinguishes a full refund
	 * (`refunded`) from a partial one (`partially_refunded`) — only full refunds
	 * revoke, mirroring {@see self::on_payment_refunded()}.
	 *
	 * @param mixed                $refund_result Gateway refund result (returned unchanged).
	 * @param array<string, mixed> $refund_args   Refund arguments, incl. `payment` and `payment_id`.
	 * @return mixed The unmodified refund result.
	 * @since 1.3.0
	 */
	public function on_admin_refund_processed( $refund_result, $refund_args = [] ) {
		if ( ! is_array( $refund_result ) || empty( $refund_result['success'] ) ) {
			return $refund_result;
		}

		$payment    = is_array( $refund_args ) && isset( $refund_args['payment'] ) && is_array( $refund_args['payment'] ) ? $refund_args['payment'] : [];
		$payment_id = is_array( $refund_args ) && isset( $refund_args['payment_id'] ) && is_numeric( $refund_args['payment_id'] ) ? intval( $refund_args['payment_id'] ) : 0;

		// Re-read the payment so the status reflects the just-applied refund.
		if ( $payment_id > 0 && class_exists( '\SRFM\Inc\Database\Tables\Payments' ) ) {
			$fresh = \SRFM\Inc\Database\Tables\Payments::get( $payment_id );
			if ( is_array( $fresh ) && ! empty( $fresh ) ) {
				$payment = $fresh;
			}
		}

		$status = is_array( $payment ) && isset( $payment['status'] ) ? $payment['status'] : '';

		// Only a full refund revokes; a partial refund (`partially_refunded`) keeps access.
		if ( $status === 'refunded' ) {
			$this->revoke_for_payment( $payment );
		}

		return $refund_result;
	}

	/**
	 * Revoke access for a SureForms Pro button-initiated subscription cancellation.
	 *
	 * Filter callback on `srfm_process_subscription_cancellation` (the frontend
	 * account-page "Cancel" button), which strips access immediately. Runs late so
	 * the gateway has already cancelled the subscription.
	 *
	 * @param mixed                $result  Gateway cancellation result (returned unchanged).
	 * @param array<string, mixed> $payment Subscription payment record.
	 * @return mixed The unmodified cancellation result.
	 * @since 1.3.0
	 */
	public function on_admin_subscription_canceled( $result, $payment = [] ) {
		if ( ! is_array( $result ) || empty( $result['success'] ) ) {
			return $result;
		}

		$this->revoke_for_payment( is_array( $payment ) ? $payment : [] );

		return $result;
	}

	/**
	 * Collect the access-group IDs mapped to a form via SureMembers workflows.
	 *
	 * Parses the form's SureForms integration workflows and returns the merged,
	 * de-duplicated set of access-group IDs across every enabled SureMembers
	 * "Grant Access" workflow.
	 *
	 * @param int $form_id Form ID.
	 * @return array<int, int> Sanitized, de-duplicated access-group IDs.
	 * @since 1.3.0
	 */
	public function get_form_access_groups( $form_id ) {
		$group_ids = [];

		if ( empty( $form_id ) ) {
			return $group_ids;
		}

		$raw = get_post_meta( $form_id, self::FORM_META, true );
		if ( empty( $raw ) || ! is_string( $raw ) ) {
			return $group_ids;
		}

		$workflows = json_decode( $raw, true );
		if ( ! is_array( $workflows ) ) {
			return $group_ids;
		}

		foreach ( $workflows as $workflow ) {
			if ( ! is_array( $workflow ) ) {
				continue;
			}

			if ( ( $workflow['integration'] ?? '' ) !== self::INTEGRATION_ID ) {
				continue;
			}

			// Honour the per-workflow enabled toggle, mirroring SureForms'
			// own definition (Workflow_Processor::is_workflow_enabled): a workflow
			// counts as enabled only when its status is the string "enabled" or
			// boolean true. Any other value (e.g. "disabled") is treated as off, so
			// a disabled workflow never grants access on payment.
			$status = $workflow['status'] ?? null;
			if ( $status !== 'enabled' && $status !== true ) {
				continue;
			}

			$fields = isset( $workflow['fields'] ) && is_array( $workflow['fields'] ) ? $workflow['fields'] : [];

			$group_ids = array_merge( $group_ids, $this->normalize_group_ids( $fields['access_group_ids'] ?? [] ) );
		}

		return array_values( array_unique( $group_ids ) );
	}

	/**
	 * Find the paid SureForms payment backing a submission by this submitter.
	 *
	 * Prefers a payment linked to this exact entry (unambiguously this
	 * submission's). Gateways that do not link the entry synchronously (notably
	 * one-time PayPal) leave `entry_id` at 0, so a scoped fallback scans the most
	 * recent payments for the form — but only accepts one that provably belongs to
	 * the submitter (see {@see self::payment_belongs_to_submitter()}), never an
	 * arbitrary recent buyer.
	 *
	 * Status is filtered in PHP (against {@see self::PAID_PAYMENT_STATUSES}) rather
	 * than in the query, so both one-time (`succeeded`) and subscription (`active`)
	 * payments are accepted without an `IN (...)` clause.
	 *
	 * @param int                                                                   $form_id   Form ID.
	 * @param int                                                                   $entry_id  Entry (submission) ID, 0 when unknown.
	 * @param array{user_id:int, user_email:string, form_emails:array<int, string>} $submitter Resolved submitter identity used to bind the fallback.
	 * @return array<string, mixed> The payment row, or an empty array when none is found.
	 * @since 1.3.0
	 */
	private function get_succeeded_payment( $form_id, $entry_id, $submitter ) {
		if ( ! class_exists( '\SRFM\Inc\Database\Tables\Payments' ) ) {
			return [];
		}

		$payments = \SRFM\Inc\Database\Tables\Payments::get_instance();

		// Preferred: a payment linked to this exact entry. An entry-linked payment
		// is unambiguously this submission's, so no further ownership check is
		// needed.
		if ( $entry_id > 0 ) {
			$rows  = $payments->get_results(
				[
					'form_id'  => $form_id,
					'entry_id' => $entry_id,
				]
			);
			$match = $this->first_paid_payment( $rows );
			if ( ! empty( $match ) ) {
				return $match;
			}
		}

		// Fallback for gateways that do not link the payment to the entry
		// synchronously (notably one-time PayPal, which stores entry_id = 0). A
		// bare form-wide scan is NOT safe here: "some recent payment on this form
		// succeeded" does not prove that *this* submission paid, so granting off it
		// would hand the submitter access bought by an unrelated buyer. Every
		// candidate is therefore bound to the submitter (by resolved user or a
		// submitted email) and rejected if it already belongs to another
		// submission. With no submitter identity we cannot bind, so we grant
		// nothing here and leave it to the payment-lifecycle listeners.
		$has_identity = ( ! empty( $submitter['user_id'] ) ) || ( ! empty( $submitter['form_emails'] ) && is_array( $submitter['form_emails'] ) );
		if ( ! $has_identity ) {
			return [];
		}

		$rows = $payments->get_results(
			[ 'form_id' => $form_id ],
			'*',
			[ 'ORDER BY `created_at` DESC', 'LIMIT 20' ]
		);

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return [];
		}

		foreach ( $rows as $row ) {
			if ( $this->payment_belongs_to_submitter( $row, $entry_id, $submitter ) ) {
				return $row;
			}
		}

		return [];
	}

	/**
	 * Resolve the identity of the visitor who submitted a form.
	 *
	 * Returns the submitter as two distinct trust levels, because an unlinked
	 * fallback payment is bound differently for each:
	 *  - `user_id` / `user_email` are the entry's authenticated account (trusted):
	 *    the submission was made while logged in, so a payment that resolves to this
	 *    user — and this user alone — may authorize a grant to them.
	 *  - `form_emails` are every email typed into the form fields (untrusted): a
	 *    logged-in visitor can type anyone's address, so these must NEVER authorize
	 *    a grant to the logged-in user. They are used only for guest submissions,
	 *    where the grant follows the payment's own buyer rather than the submitter.
	 *
	 * @param int $entry_id Entry (submission) ID.
	 * @return array{user_id:int, user_email:string, form_emails:array<int, string>} Trusted account id/email (0/'' when guest) and untrusted submitted emails.
	 * @since 1.3.0
	 */
	private function get_submitter_identity( $entry_id ) {
		$identity = [
			'user_id'     => 0,
			'user_email'  => '',
			'form_emails' => [],
		];

		if ( $entry_id <= 0 || ! class_exists( '\SRFM\Inc\Database\Tables\Entries' ) ) {
			return $identity;
		}

		$entry = \SRFM\Inc\Database\Tables\Entries::get( $entry_id );
		if ( ! is_array( $entry ) ) {
			return $identity;
		}

		if ( ! empty( $entry['user_id'] ) && is_numeric( $entry['user_id'] ) && intval( $entry['user_id'] ) > 0 ) {
			$identity['user_id'] = intval( $entry['user_id'] );
			$user                = get_userdata( $identity['user_id'] );
			if ( $user instanceof \WP_User && ! empty( $user->user_email ) ) {
				$identity['user_email'] = strtolower( $user->user_email );
			}
		}

		// Emails typed into the form fields — untrusted, used only for guest binding.
		$form_data = $entry['form_data'] ?? [];
		if ( is_string( $form_data ) ) {
			$decoded   = json_decode( $form_data, true );
			$form_data = is_array( $decoded ) ? $decoded : [ $form_data ];
		}
		$identity['form_emails'] = array_values( array_unique( array_filter( $this->collect_emails( $form_data ) ) ) );

		return $identity;
	}

	/**
	 * Recursively collect valid, lowercased email addresses from submission data.
	 *
	 * @param mixed $data Submitted field value (string, or nested array of values).
	 * @return array<int, string> Lowercased valid email addresses.
	 * @since 1.3.0
	 */
	private function collect_emails( $data ) {
		$found = [];

		if ( is_string( $data ) ) {
			$value = sanitize_email( $data );
			if ( '' !== $value && is_email( $value ) ) {
				$found[] = strtolower( $value );
			}
			return $found;
		}

		if ( is_array( $data ) ) {
			foreach ( $data as $value ) {
				$found = array_merge( $found, $this->collect_emails( $value ) );
			}
		}

		return $found;
	}

	/**
	 * Whether a fallback payment provably belongs to the submitter of this form.
	 *
	 * A candidate is accepted only when it is a paid, access-granting row, is not
	 * already linked to a *different* submission, and its buyer matches the
	 * submitter. Matching is deliberately split by trust level to avoid a
	 * privilege-escalation gap:
	 *  - Logged-in submitter: bind ONLY by the authenticated account — the payment
	 *    resolves to this user, or its customer email is the user's own account
	 *    email. Form-typed emails are ignored here, because the grant goes to the
	 *    logged-in user and they could otherwise type a stranger's address to
	 *    inherit that stranger's purchase.
	 *  - Guest submitter: bind by a form-typed email. This is safe because the
	 *    grant then follows the payment's own buyer (resolved / provisioned from the
	 *    payment email), not the anonymous submitter — so a typed address can only
	 *    ever grant access to the address's real owner, never to the attacker.
	 *
	 * @param mixed                                                                 $payment   Candidate payment row.
	 * @param int                                                                   $entry_id  This submission's entry ID.
	 * @param array{user_id:int, user_email:string, form_emails:array<int, string>} $submitter Resolved submitter identity.
	 * @return bool True when the payment belongs to the submitter.
	 * @since 1.3.0
	 */
	private function payment_belongs_to_submitter( $payment, $entry_id, $submitter ) {
		if ( ! is_array( $payment ) || ! isset( $payment['status'] ) ) {
			return false;
		}

		// Must be a paid, access-granting row.
		if ( ! in_array( $payment['status'], self::PAID_PAYMENT_STATUSES, true ) ) {
			return false;
		}

		// Never claim a payment already linked to a different submission.
		$payment_entry_id = isset( $payment['entry_id'] ) && is_numeric( $payment['entry_id'] ) ? intval( $payment['entry_id'] ) : 0;
		if ( $payment_entry_id > 0 && $payment_entry_id !== $entry_id ) {
			return false;
		}

		$customer_email = ! empty( $payment['customer_email'] ) && is_string( $payment['customer_email'] )
			? strtolower( sanitize_email( $payment['customer_email'] ) )
			: '';

		$user_id = isset( $submitter['user_id'] ) ? intval( $submitter['user_id'] ) : 0;

		// Logged-in submitter: the grant goes to THIS user, so only their own
		// authenticated identity may authorize it — never a form-typed email.
		if ( $user_id > 0 ) {
			if ( class_exists( '\SRFM\Inc\Payments\Payment_Helper' )
				&& intval( \SRFM\Inc\Payments\Payment_Helper::resolve_payment_user( $payment ) ) === $user_id ) {
				return true;
			}

			$user_email = isset( $submitter['user_email'] ) && is_string( $submitter['user_email'] ) ? $submitter['user_email'] : '';
			if ( '' !== $user_email && '' !== $customer_email && $customer_email === $user_email ) {
				return true;
			}

			return false;
		}

		// Guest submitter: bind by a form-typed email. Safe because the grant
		// follows the payment's own buyer, not the anonymous submitter.
		$form_emails = isset( $submitter['form_emails'] ) && is_array( $submitter['form_emails'] ) ? $submitter['form_emails'] : [];
		if ( '' !== $customer_email && in_array( $customer_email, $form_emails, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return the first payment row whose status is access-granting.
	 *
	 * @param mixed $rows Payment rows from the Payments table query.
	 * @return array<string, mixed> The matching row, or an empty array.
	 * @since 1.3.0
	 */
	private function first_paid_payment( $rows ) {
		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return [];
		}

		foreach ( $rows as $row ) {
			if ( is_array( $row ) && isset( $row['status'] ) && in_array( $row['status'], self::PAID_PAYMENT_STATUSES, true ) ) {
				return $row;
			}
		}

		return [];
	}

	/**
	 * Resolve the WordPress user who should receive access for a submission.
	 *
	 * The submitting user owns the access, so the entry's recorded user is
	 * preferred; when the submission was made by a guest, the payment's customer
	 * email is matched to a user as a fallback (via SureForms' own resolver).
	 *
	 * @param array<string, mixed> $payment  Succeeded payment row.
	 * @param int                  $entry_id Entry (submission) ID, 0 when unknown.
	 * @return int User ID, or 0 when none is resolvable.
	 * @since 1.3.0
	 */
	private function resolve_user_id( $payment, $entry_id ) {
		if ( $entry_id > 0 && class_exists( '\SRFM\Inc\Database\Tables\Entries' ) ) {
			$entry = \SRFM\Inc\Database\Tables\Entries::get( $entry_id );
			if ( is_array( $entry ) && ! empty( $entry['user_id'] ) && is_numeric( $entry['user_id'] ) && intval( $entry['user_id'] ) > 0 ) {
				return intval( $entry['user_id'] );
			}
		}

		if ( is_array( $payment ) && class_exists( '\SRFM\Inc\Payments\Payment_Helper' ) ) {
			return intval( \SRFM\Inc\Payments\Payment_Helper::resolve_payment_user( $payment ) );
		}

		return 0;
	}

	/**
	 * Resolve the WordPress user for a payment, creating one for guest checkouts.
	 *
	 * Memberships can only be assigned to a real WordPress user. When the buyer
	 * checked out as a guest — no user on the entry and no account matching the
	 * customer email — an account is provisioned from the payment so the grant
	 * can proceed. Used only by the grant callbacks; revocation never creates users.
	 *
	 * @param array<string, mixed> $payment  Succeeded payment row.
	 * @param int                  $entry_id Entry (submission) ID, 0 when unknown.
	 * @return int Resolved or newly created user ID, or 0 when none could be obtained.
	 * @since 1.4.1
	 */
	private function resolve_or_create_user_id( $payment, $entry_id ) {
		$user_id = $this->resolve_user_id( $payment, $entry_id );
		if ( ! empty( $user_id ) ) {
			return $user_id;
		}

		return $this->create_user_from_payment( $payment );
	}

	/**
	 * Provision a WordPress user from a guest payment's customer details.
	 *
	 * The customer email becomes the account email (and the basis for the login);
	 * the customer name, when present, is stored as display name and split into
	 * first/last name profile meta. The new-user notification is sent so the buyer
	 * can set a password and sign in to use their membership.
	 *
	 * @param array<string, mixed> $payment Succeeded payment row (carries `customer_email` and `customer_name`).
	 * @return int New user ID, or 0 when a user could not be created.
	 * @since 1.4.1
	 */
	private function create_user_from_payment( $payment ) {
		if ( ! is_array( $payment ) ) {
			return 0;
		}

		$email = ! empty( $payment['customer_email'] ) && is_string( $payment['customer_email'] )
			? sanitize_email( $payment['customer_email'] )
			: '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			return 0;
		}

		// Guard against a race where the account appeared after resolution.
		$existing = get_user_by( 'email', $email );
		if ( $existing instanceof \WP_User ) {
			return intval( $existing->ID );
		}

		$full_name = ! empty( $payment['customer_name'] ) && is_string( $payment['customer_name'] )
			? sanitize_text_field( $payment['customer_name'] )
			: '';

		$userdata = [
			'user_login' => $this->generate_unique_username( $email, $full_name ),
			'user_email' => $email,
			'user_pass'  => wp_generate_password( 24, true, true ),
			'role'       => get_option( 'default_role', 'subscriber' ),
		];

		// Use the captured name for the profile when the form supplied one.
		if ( ! empty( $full_name ) ) {
			$userdata['display_name'] = $full_name;
			$parts                    = preg_split( '/\s+/', $full_name, 2 );
			if ( is_array( $parts ) ) {
				$userdata['first_name'] = $parts[0];
				if ( ! empty( $parts[1] ) ) {
					$userdata['last_name'] = $parts[1];
				}
			}
		}

		$user_id = wp_insert_user( $userdata );

		if ( is_wp_error( $user_id ) ) {
			return 0;
		}

		$user_id = intval( $user_id );

		// Let the buyer set a password and sign in to use their membership.
		wp_new_user_notification( $user_id, null, 'user' );

		/**
		 * Fires after a guest buyer is provisioned a WordPress account so a
		 * SureMembers membership can be granted for their SureForms payment.
		 *
		 * @param int                  $user_id New user ID.
		 * @param array<string, mixed> $payment Payment row the user was created from.
		 * @since 1.4.1
		 */
		do_action( 'suremembers_sureforms_guest_user_created', $user_id, $payment );

		return $user_id;
	}

	/**
	 * Build a unique, sanitized login for a provisioned guest account.
	 *
	 * Prefers the email local-part, then the customer name, falling back to a
	 * generic base; a numeric suffix is appended until the login is free.
	 *
	 * @param string $email     Customer email.
	 * @param string $full_name Customer full name, may be empty.
	 * @return string Unique user login.
	 * @since 1.4.1
	 */
	private function generate_unique_username( $email, $full_name ) {
		$local = strstr( $email, '@', true );
		$base  = is_string( $local ) && $local !== '' ? $local : $full_name;

		$base = sanitize_user( $base, true );
		if ( empty( $base ) ) {
			$base = 'sforms_user';
		}

		$username = $base;
		$suffix   = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $suffix;
			$suffix++;
		}

		return $username;
	}

	/**
	 * Revoke the form's mapped access groups for the user behind a payment record.
	 *
	 * Shared by the button-initiated refund / cancel filter callbacks.
	 *
	 * @param array<string, mixed> $payment Payment record (carries `form_id`, `entry_id`, `customer_email`).
	 * @return void
	 * @since 1.3.0
	 */
	private function revoke_for_payment( $payment ) {
		$form_id  = is_array( $payment ) && isset( $payment['form_id'] ) && is_numeric( $payment['form_id'] ) ? intval( $payment['form_id'] ) : 0;
		$entry_id = is_array( $payment ) && isset( $payment['entry_id'] ) && is_numeric( $payment['entry_id'] ) ? intval( $payment['entry_id'] ) : 0;

		$group_ids = $this->get_form_access_groups( $form_id );
		$user_id   = $this->resolve_user_id( $payment, $entry_id );

		if ( empty( $user_id ) || empty( $group_ids ) ) {
			return;
		}

		Access::revoke( $user_id, $group_ids );
	}

	/**
	 * Normalize the stored access-group field into a list of positive integer IDs.
	 *
	 * SureForms' `multi-select-async` field is stored in several shapes depending on
	 * how the selection was made, so every one is accepted here:
	 *  - a scalar string for a single selection (e.g. "69");
	 *  - a comma-separated string (e.g. "69,70");
	 *  - an array of scalar IDs (e.g. [ 69, 70 ]);
	 *  - an array of `{ value, label }` option objects (e.g.
	 *    [ [ 'value' => 69, 'label' => 'Gold' ] ]) — the shape the async select
	 *    persists when options are chosen in the UI. This last shape is why the
	 *    `value` key must be unwrapped before casting (mirrors how the bundled
	 *    MailPoet / MailerPress integrations parse their list fields); casting the
	 *    object directly yields 0 and silently drops the selection.
	 *
	 * @param mixed $value Raw stored field value.
	 * @return array<int, int> Sanitized, de-duplicated access-group IDs.
	 * @since 1.3.0
	 */
	private function normalize_group_ids( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			$value = [ $value ];
		}

		$ids = array_map(
			static function( $id ) {
				// Unwrap `{ value, label }` option objects from the async select.
				if ( is_array( $id ) && isset( $id['value'] ) ) {
					$id = $id['value'];
				}

				return absint( is_string( $id ) ? trim( $id ) : $id );
			},
			$value
		);

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Extract a sanitized user ID from the payment context.
	 *
	 * @param array<string, mixed> $context Resolved payment context.
	 * @return int User ID, or 0 when none is resolvable.
	 * @since 1.3.0
	 */
	private function get_context_user_id( $context ) {
		return is_array( $context ) && ! empty( $context['user_id'] ) && is_numeric( $context['user_id'] )
			? intval( $context['user_id'] )
			: 0;
	}

	/**
	 * Extract a sanitized form ID from the payment context.
	 *
	 * @param array<string, mixed> $context Resolved payment context.
	 * @return int Form ID, or 0 when none is resolvable.
	 * @since 1.3.0
	 */
	private function get_context_form_id( $context ) {
		return is_array( $context ) && ! empty( $context['form_id'] ) && is_numeric( $context['form_id'] )
			? intval( $context['form_id'] )
			: 0;
	}
}
