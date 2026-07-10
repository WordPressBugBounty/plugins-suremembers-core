<?php
/**
 * WooCommerce Integration.
 *
 * @package suremembers
 *
 * @since 1.1.0
 */

namespace SureMembersCore\Integrations;

use SureMembersCore\Admin\Templates;
use SureMembersCore\Inc\Access;
use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Settings;
use SureMembersCore\Inc\Traits\Get_Instance;
use SureMembersCore\Inc\Utils;
use WC_Coupon;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Integration
 *
 * @since 0.0.1
 */
class Woocommerce {
	use Get_Instance;

	/**
	 * Store updated order id.
	 *
	 * @var int
	 * @since 1.1.0
	 */
	public $order_id;

	/**
	 * Stores product id associated with updated order.
	 *
	 * @var int
	 * @since 1.1.0
	 */
	public $product_id;

	/**
	 * Stores users access group coupon code
	 *
	 * @var int
	 * @since 1.4.0
	 */
	public $access_group_coupon_code = 0;

	/**
	 * Whether coupon is applied by suremembers or not.
	 *
	 * @var bool
	 * @since 1.4.0
	 */
	public $applied_by_suremembers;

	/**
	 * Checks if coupon code is removed
	 *
	 * @var bool
	 * @since 1.4.0
	 */
	public $is_coupon_removed;

	/**
	 * Constructor function
	 *
	 * @since 1.1.0
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', [ $this, 'access_groups_meta_box' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'access_group_scripts' ] );
		add_action( 'save_post_product', [ $this, 'save_access_groups' ] );
		add_action( 'save_post_shop_coupon', [ $this, 'save_coupon_data' ], 10, 0 );
		add_action( 'woocommerce_order_status_changed', [ $this, 'update_access' ], 10, 3 );
		add_action( 'woocommerce_subscription_status_updated', [ $this, 'update_subscription_access' ], 10, 3 );
		add_action( 'suremembers_process_aborted_same_status', [ $this, 'update_order_ids' ], 10, 3 );
		add_action( 'woocommerce_before_delete_order', [ $this, 'revoke_deleting_order' ], 5, 2 ); // Using 'woocommerce_before_delete_order' instead of 'woocommerce_delete_order' to revoke the access before deleting the order object from database.
		add_action( 'woocommerce_coupon_data_panels', [ $this, 'suremembers_coupon_panels' ], 10, 0 );
		add_action( 'woocommerce_before_cart', [ $this, 'apply_coupon_code' ] );
		add_action( 'woocommerce_before_checkout_form_cart_notices', [ $this, 'apply_coupon_code' ] );
		add_action( 'woocommerce_removed_coupon', [ $this, 'coupon_removed' ] );
		add_action( 'woocommerce_coupon_data_panels', [ $this, 'suremembers_coupon_panels' ], 10, 0 );
		add_action( 'woocommerce_applied_coupon', [ $this, 'should_remove_coupon' ] );

		add_filter( 'woocommerce_coupon_data_tabs', [ $this, 'suremembers_coupon_tab' ], 20, 1 );
		add_filter( 'woocommerce_coupon_message', [ $this, 'coupon_message' ], 19, 3 );
		add_filter( 'woocommerce_coupon_data_tabs', [ $this, 'suremembers_coupon_tab' ], 20, 1 );
		add_filter( 'suremembers_should_redirect_to_custom_template', [ $this, 'redirect_to_custom_template' ], 10, 1 );
		add_filter( 'woocommerce_cart_totals_coupon_label', [ $this, 'add_custom_coupon_label' ], 99, 2 );
	}

	/**
	 * Adds meta box in WooCommerce product page.
	 *
	 * @since 1.1.0
	 */
	public function access_groups_meta_box() {
		add_meta_box(
			'access_groups_meta_box',
			__( 'Add Memberships', 'suremembers-core' ),
			[ $this, 'meta_box_content' ],
			'product',
			'side',
			'core'
		);
	}

	/**
	 * Undocumented function
	 *
	 * @since 1.1.0
	 */
	public function meta_box_content() {
		global $post;
		Templates::access_groups_markup( $post->ID );
	}

	/**
	 * Enqueue scripts for access groups
	 *
	 * @since 1.1.0
	 */
	public function access_group_scripts() {
		$screen = get_current_screen();
		if ( is_null( $screen ) || ! in_array( $screen->id, [ 'product', 'shop_coupon' ], true ) ) {
			return;
		}

		wp_register_script( 'suremembers-menu-items', SUREMEMBERS_CORE_URL . 'admin/assets/js/menu-items.js', [ 'jquery' ], SUREMEMBERS_CORE_VER, true );
		wp_enqueue_script( 'suremembers-menu-items' );

		wp_localize_script(
			'suremembers-menu-items',
			'suremembers_menu_items',
			[
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'security' => current_user_can( 'manage_options' ) ? wp_create_nonce( 'suremembers_queried_access_groups' ) : '',
			]
		);
	}

	/**
	 * Save WooCommerce item access groups.
	 *
	 * @since 1.1.0
	 */
	public function save_access_groups() {
		if ( ! isset( $_POST['wc-suremembers-access-groups-nonce'] ) ) {
			return;
		}

		global $post;

		// Nonce verification is done in the same line, hence ignored.
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( sanitize_text_field( $_POST['wc-suremembers-access-groups-nonce'] ), 'wc-suremembers-access-groups-nonce' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! isset( $_POST['wc-suremembers-access-groups'] ) ) {
			delete_post_meta( $post->ID, SUREMEMBERS_ACCESS_GROUPS );
			return;
		}

		// Ignored as we have used recursive sanitization.
		update_post_meta( $post->ID, SUREMEMBERS_ACCESS_GROUPS, Utils::sanitize_recursively( 'intval', $_POST['wc-suremembers-access-groups'] ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * Updates the access status
	 *
	 * @param int    $order_id WooCommerce order id.
	 * @param string $initial_status initial status of order.
	 * @param string $updated_status final status of order.
	 *
	 * @since 1.1.0
	 */
	public function update_access( $order_id, $initial_status, $updated_status ) {
		$order = wc_get_order( $order_id );

		if ( ! is_object( $order ) ) {
			return;
		}

		// If the order is not found, then return.
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$user_id = $order->get_customer_id();

		$items = [];
		foreach ( $order->get_items() as $item ) {
			$pid     = $item->get_product_id();
			$product = \wc_get_product( $pid );
			if ( $product instanceof \WC_Product && ! $product->is_type( 'subscription' ) && ! $product->is_type( 'variable-subscription' ) ) {
				$items[] = $item;
			}
		}

		if ( empty( $user_id ) || empty( $items ) ) {
			return;
		}

		$grant_status  = apply_filters( 'suremembers_wc_grant_status', [ 'processing', 'completed' ], $order_id );
		$revoke_status = apply_filters( 'suremembers_wc_revoke_status', [ 'cancelled', 'refunded', 'failed', 'pending', 'trash', 'deleted' ], $order_id );

		$this->grant_revoke_access( $items, $grant_status, $revoke_status, $updated_status, $user_id, $order_id );
	}

	/**
	 * Updates the access status for subscription product
	 *
	 * @param object $subscription WooCommerce order id.
	 * @param string $updated_status final status of order.
	 * @param string $initial_status initial status of order.
	 *
	 * @since 1.1.0
	 */
	public function update_subscription_access( $subscription, $updated_status, $initial_status ) {
		$user_id  = $subscription->get_customer_id();
		$order_id = $subscription->get_id();

		if ( empty( $user_id ) ) {
			return;
		}

		$grant_status  = apply_filters( 'suremembers_wcs_grant_status', [ 'active' ], $order_id );
		$revoke_status = apply_filters( 'suremembers_wcs_revoke_status', [ 'on-hold', 'cancelled', 'expired', 'failed' ], $order_id );

		$this->grant_revoke_access( $subscription->get_items(), $grant_status, $revoke_status, $updated_status, $user_id, $order_id );
	}

	/**
	 * Grant or Revoke access as per status change
	 *
	 * @param array<string, mixed> $items object of items in current order.
	 * @param array<string, mixed> $grant_status array of status on which access should be granted.
	 * @param array<string, mixed> $revoke_status array of status on which access should be revoked.
	 * @param string               $updated_status updated status of order/subscription.
	 * @param int                  $user_id current user id.
	 * @param int                  $order_id current order id.
	 *
	 * @since 1.1.0
	 */
	public function grant_revoke_access( $items, $grant_status, $revoke_status, $updated_status, $user_id, $order_id ) {
		$access_groups = [];
		foreach ( $items as $item ) {
			$product_id         = $item->get_product_id();
			$item_access_groups = get_post_meta( $product_id, SUREMEMBERS_ACCESS_GROUPS, true );

			if ( empty( $item_access_groups ) ) {
				continue;
			}

			if ( is_array( $item_access_groups ) ) {
				$access_groups = array_merge( $access_groups, $item_access_groups );
				continue;
			}

			$access_groups = array_merge( $access_groups, [ $item_access_groups ] );
		}

		if ( empty( $access_groups ) ) {
			return;
		}

		if ( in_array( $updated_status, $grant_status, true ) ) {
			$this->order_id = $order_id;
			add_filter( 'suremembers_grant_creation_data', [ $this, 'update_grant_creation_data' ], 10, 1 );
			Access::grant( $user_id, $access_groups, 'woocommerce' );
		}

		if ( in_array( $updated_status, $revoke_status, true ) ) {
			foreach ( $access_groups as $ag_id ) {
				$user_meta = get_user_meta( $user_id, SUREMEMBERS_USER_META . '_' . $ag_id, true );
				if ( is_array( $user_meta ) && ! empty( $user_meta['integration'] ) && $user_meta['integration'] === 'woocommerce' ) {
					if ( ! empty( $user_meta['wc_order_ids'] ) && is_array( $user_meta['wc_order_ids'] ) ) {
						$key = array_search( $order_id, $user_meta['wc_order_ids'], true );
						if ( $key !== false ) {
							unset( $user_meta['wc_order_ids'][ $key ] );
							update_user_meta( $user_id, SUREMEMBERS_USER_META . '_' . $ag_id, $user_meta );
						}
						if ( empty( $user_meta['wc_order_ids'] ) ) {
							Access::revoke( $user_id, $ag_id );
						}
					}
				}
			}
		}
	}

	/**
	 * Update data while creating grant access entry
	 *
	 * @param array<string, mixed> $data user meta data.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.1.0
	 */
	public function update_grant_creation_data( $data ) {
		$data['wc_order_ids'][] = intval( $this->order_id );
		$data['wc_order_ids']   = array_unique( $data['wc_order_ids'] );
		return $data;
	}

	/**
	 * Updates order id for multiple orders
	 *
	 * @param string               $action action performed.
	 * @param array<string, mixed> $data access group data.
	 * @param int                  $ag_id access group id.
	 *
	 * @since 1.1.0
	 */
	public function update_order_ids( $action, $data, $ag_id ) {
		if ( empty( $data['integration'] ) || $data['integration'] !== 'woocommerce' ) {
			return;
		}

		$order_id = intval( $this->order_id );

		if ( empty( $order_id ) ) {
			return;
		}

		if ( $action === 'grant' ) {
			if ( in_array( $order_id, $data['wc_order_ids'], true ) ) {
				return;
			}

			$data['wc_order_ids'][] = $order_id;
			$order                  = wc_get_order( $order_id );
			if ( ! $order instanceof \WC_Order ) {
				return;
			}
			$user_id = $order->get_customer_id();
			if ( empty( $user_id ) ) {
				return;
			}
			update_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$ag_id}", $data );
		} elseif ( $action === 'revoke' ) {
			$key = array_search( $order_id, $data['wc_order_ids'], true );

			if ( $key !== false ) {
				return;
			}

			unset( $data['wc_order_ids'][ $key ] );
			$order = wc_get_order( $order_id );
			if ( ! $order instanceof \WC_Order ) {
				return;
			}
			$user_id = $order->get_customer_id();
			if ( empty( $user_id ) ) {
				return;
			}

			update_user_meta( $user_id, SUREMEMBERS_USER_META . "_{$ag_id}", $data );

			if ( empty( $data['wc_order_ids'] ) ) {
				Access::revoke( $user_id, $ag_id );
			}
		}
	}

	/**
	 * Update status for deleting order
	 *
	 * @param int       $order_id current order id.
	 * @param \WC_Order $order current order object.
	 *
	 * @since 1.1.0
	 */
	public function revoke_deleting_order( $order_id, $order ) {
		$this->update_access( $order_id, '', 'deleted' );
	}

	/**
	 * Adds new tab in coupon data
	 *
	 * @param array<string, mixed> $tabs existing tabs data.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.4.0
	 */
	public function suremembers_coupon_tab( $tabs ) {
		$tabs['suremembers_tab'] = [
			'label'  => __( 'SureMembers', 'suremembers-core' ),
			'target' => 'suremembers_tab',
			'class'  => 'suremembers_tab',
		];

		return $tabs;
	}

	/**
	 * Generates coupon SureMembers panel.
	 *
	 * @since 1.4.0
	 */
	public function suremembers_coupon_panels() {
		global $post;
		$coupon_id = absint( $post->ID );
		?>
		<div id="suremembers_tab" class="panel woocommerce_options_panel suremembers_tab">
			<?php $this->coupon_html( $coupon_id ); ?>
		</div>
		<?php
	}

	/**
	 * Html for coupon panel
	 *
	 * @param int $coupon_id current WooCommerce coupon id.
	 *
	 * @since 1.4.0
	 */
	public function coupon_html( $coupon_id ) {
		wp_nonce_field( 'suremembers_coupon_nonce', 'suremembers_coupon_nonce' );
		$access_groups = $this->get_coupon_associated_access_groups( $coupon_id );
		woocommerce_wp_select(
			[
				'id'                => 'suremembers_access_groups',
				'class'             => 'suremembers-select2',
				'name'              => 'suremembers_access_groups[]',
				'style'             => 'width:80%;',
				'label'             => __( 'Restrict to Memberships', 'suremembers-core' ),
				'options'           => $access_groups,
				'value'             => array_keys( $access_groups ),
				'custom_attributes' => [
					'multiple'    => '',
					'placeholder' => __( 'Select Memberships', 'suremembers-core' ),
				],
				'description'       => __( 'Select memberships to apply this coupon. Only one coupon can be attached to a membership. Coupon restricted with any membership will not be available for non-members', 'suremembers-core' ),
				'desc_tip'          => true,
			]
		);
		$label = get_post_meta( $coupon_id, 'suremembers_summary_label', true );
		woocommerce_wp_text_input(
			[
				'id'          => 'suremembers_summary_label',
				'label'       => __( 'Discount label', 'suremembers-core' ),
				'description' => __( 'Label to displayed on order summary.', 'suremembers-core' ),
				'desc_tip'    => true,
				'value'       => ! empty( $label ) ? $label : __( 'Membership discount', 'suremembers-core' ),
			]
		);
		$message = get_post_meta( $coupon_id, 'suremembers_summary_message', true );
		woocommerce_wp_text_input(
			[
				'id'          => 'suremembers_summary_message',
				'label'       => __( 'Discount Message', 'suremembers-core' ),
				'description' => __( 'Add text to override coupon added notice on cart / checkout page.', 'suremembers-core' ),
				'desc_tip'    => true,
				'value'       => ! empty( $message ) ? $message : __( 'Membership coupon applied.', 'suremembers-core' ),
			]
		);
	}

	/**
	 * Saves suremembers meta associated with coupon.
	 *
	 * @since 1.4.0
	 */
	public function save_coupon_data() {
		if ( empty( $_POST['suremembers_coupon_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['suremembers_coupon_nonce'] ), 'suremembers_coupon_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( empty( $_POST['ID'] ) ) {
			return;
		}

		$coupon_id = absint( $_POST['ID'] );
		if ( empty( $coupon_id ) ) {
			return;
		}

		// Ignored as we have custom sanitize recursive function.
		$access_groups             = ! empty( $_POST['suremembers_access_groups'] ) ? Utils::sanitize_recursively( 'absint', $_POST['suremembers_access_groups'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$existing_access_groups    = $this->get_coupon_associated_access_groups( $coupon_id );
		$existing_access_group_ids = array_keys( $existing_access_groups );

		$new_access_groups = array_diff( $access_groups, $existing_access_group_ids );
		if ( ! empty( $new_access_groups ) ) {
			foreach ( $new_access_groups as $access_group ) {
				update_post_meta( $access_group, 'suremembers_wc_coupon', $coupon_id );
			}
		}
		$removed_access_groups = array_diff( $existing_access_group_ids, $access_groups );
		if ( ! empty( $removed_access_groups ) ) {
			foreach ( $removed_access_groups as $access_group ) {
				delete_post_meta( $access_group, 'suremembers_wc_coupon' );
			}
		}

		$coupon_label = ! empty( $_POST['suremembers_summary_label'] ) ? trim( sanitize_text_field( $_POST['suremembers_summary_label'] ) ) : '';
		update_post_meta( $coupon_id, 'suremembers_summary_label', $coupon_label );

		$coupon_message = ! empty( $_POST['suremembers_summary_message'] ) ? trim( sanitize_text_field( $_POST['suremembers_summary_message'] ) ) : '';
		update_post_meta( $coupon_id, 'suremembers_summary_message', $coupon_message );
	}

	/**
	 * Returns access groups associated with current coupon
	 *
	 * @param int $coupon_id WooCommerce coupon id.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.4.0
	 */
	public function get_coupon_associated_access_groups( $coupon_id ) {
		return Access_Groups::get_active(
			[
				'meta_key'   => 'suremembers_wc_coupon',
				// Ignored in favor of functionality.
				'meta_value' => $coupon_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			]
		);
	}

	/**
	 * Returns coupon id associated with users access group
	 *
	 * @since 1.4.0
	 */
	public function get_coupon_id_by_access_group() {
		if ( ! empty( $this->access_group_coupon_code ) ) {
			return $this->access_group_coupon_code;
		}

		$user_id = get_current_user_id();
		if ( empty( $user_id ) ) {
			return 0;
		}

		$access_groups = get_user_meta( $user_id, SUREMEMBERS_USER_META, true );
		if ( ! is_array( $access_groups ) ) {
			$access_groups = [];
		}

		if ( empty( $access_groups ) ) {
			return 0;
		}

		$access_group = Access_Groups::get_priority_id( $access_groups );
		if ( empty( $access_group ) ) {
			return 0;
		}

		$access_group_coupon_code       = get_post_meta( $access_group, 'suremembers_wc_coupon', true );
		$this->access_group_coupon_code = is_string( $access_group_coupon_code ) ? absint( $access_group_coupon_code ) : 0;

		return $this->access_group_coupon_code;
	}

	/**
	 * Auto apply coupon associated with access group
	 *
	 * @since 1.4.0
	 */
	public function apply_coupon_code() {
		add_filter( 'woocommerce_coupons_enabled', [ $this, 'disable_coupons' ] );
		$coupon_id = $this->get_coupon_id_by_access_group();
		if ( empty( $coupon_id ) ) {
			return;
		}

		add_filter( 'woocommerce_coupons_enabled', [ $this, 'disable_coupons' ] );

		if ( $this->is_coupon_removed ) {
			$this->is_coupon_removed = false;
			return;
		}

		remove_action( 'woocommerce_add_to_cart', [ $this, 'apply_coupon_code' ], 30 ); // don't need to do this twice.

		WC()->cart->calculate_totals();

		$coupon_code = wc_get_coupon_code_by_id( $coupon_id );
		if ( empty( $coupon_code ) ) {
			return;
		}

		if ( count( WC()->cart->get_applied_coupons() ) > 0 ) {
			return;
		}
		remove_filter( 'woocommerce_coupons_enabled', [ $this, 'disable_coupons' ] );
		$this->applied_by_suremembers = true;
		WC()->cart->apply_coupon( $coupon_code );
		WC()->cart->calculate_totals();
		add_filter( 'woocommerce_coupons_enabled', [ $this, 'disable_coupons' ] );
		if ( is_cart() || is_checkout() ) {
			wc_print_notices();
		}
	}

	/**
	 * Returns coupon message is set by user or else returns default WooCommerce message as notice
	 *
	 * @param string    $message current message.
	 * @param int       $message_code message code.
	 * @param WC_Coupon $coupon coupon object.
	 *
	 * @since 1.4.0
	 */
	public function coupon_message( $message, $message_code, $coupon ) {
		$coupon_id = $this->get_coupon_id_by_access_group();
		if ( empty( $coupon_id ) ) {
			return $message;
		}

		$coupon_code = wc_get_coupon_code_by_id( $coupon_id );
		if ( empty( $coupon_code ) ) {
			return $message;
		}

		if ( strtolower( $coupon_code ) !== $coupon->get_code() ) {
			return $message;
		}

		$coupon_message = get_post_meta( $coupon_id, 'suremembers_summary_message', true );
		if ( ! is_string( $coupon_message ) ) {
			$coupon_message = '';
		}

		if ( empty( trim( $coupon_message ) ) ) {
			return $message;
		}

		return esc_html( $coupon_message );
	}

	/**
	 * Return false to disable coupon feature
	 *
	 * @param bool $status current status returned by filter woocommerce_coupons_enabled.
	 *
	 * @since 1.4.0
	 */
	public function disable_coupons( $status ) {
		$settings = Settings::get_setting( SUREMEMBERS_ADMIN_SETTINGS );
		$hide     = ! empty( $settings['hide_woocommerce_coupon'] ) ? $settings['hide_woocommerce_coupon'] : false;
		return $hide ? false : $status;
	}

	/**
	 * Updates is_coupon_removed variable to true when coupon is removed
	 *
	 * @since 1.4.0
	 */
	public function coupon_removed() {
		$this->is_coupon_removed = true;
	}

	/**
	 * Returns custom label if set in options or else returns WooCommerce default label
	 *
	 * @param string    $label current Coupon code label.
	 * @param WC_Coupon $coupon WC_Coupon object.
	 *
	 * @since 1.4.0
	 */
	public function add_custom_coupon_label( $label, $coupon ) {
		$coupon_id = $this->get_coupon_id_by_access_group();

		if ( empty( $coupon_id ) ) {
			return $label;
		}

		$coupon_code = wc_get_coupon_code_by_id( $coupon_id );
		if ( empty( $coupon_code ) ) {
			return $label;
		}

		if ( strtolower( $coupon_code ) !== $coupon->get_code() ) {
			return $label;
		}

		$coupon_message = get_post_meta( $coupon_id, 'suremembers_summary_label', true );
		if ( ! is_string( $coupon_message ) ) {
			$coupon_message = '';
		}

		if ( empty( trim( $coupon_message ) ) ) {
			return $label;
		}

		return esc_html( $coupon_message );
	}
	/**
	 * Checks whether custom template redirection is required
	 *
	 * @param bool $status current status to redirect to custom template.
	 *
	 * @since 1.3.1
	 */
	public function redirect_to_custom_template( $status ) {
		if ( is_product() || is_shop() || is_cart() || is_checkout() ) {
			$status = true;
		}
		return $status;
	}

	/**
	 * Removes coupon if applied by non members.
	 *
	 * @param string $coupon_code coupon code.
	 *
	 * @since 1.4.0
	 */
	public function should_remove_coupon( $coupon_code ) {
		if ( $this->applied_by_suremembers ) {
			return;
		}

		$coupon_id     = wc_get_coupon_id_by_code( $coupon_code );
		$access_groups = $this->get_coupon_associated_access_groups( $coupon_id );
		if ( empty( $access_groups ) ) {
			return;
		}

		$user_id            = get_current_user_id();
		$user_access_groups = [];
		if ( ! empty( $user_id ) ) {
			$user_access_groups = (array) get_user_meta( $user_id, SUREMEMBERS_USER_META, true );
		}

		if ( empty( array_intersect( array_keys( $access_groups ), $user_access_groups ) ) ) {
			WC()->cart->remove_coupon( $coupon_code );
			WC()->cart->calculate_totals();
			wc_clear_notices();
			wc_add_notice( __( 'Invalid coupon code', 'suremembers-core' ), 'error' );
		}
	}
}
