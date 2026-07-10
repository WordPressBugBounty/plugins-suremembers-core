<?php
/**
 * User edit page access table.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Admin;

use SureMembersCore\Inc\Access_Groups;
use SureMembersCore\Inc\Restricted;
use SureMembersCore\Inc\Traits\Get_Instance;
use SureMembersCore\Inc\Utils;
use WP_List_Table;

defined( 'ABSPATH' ) || exit;

/**
 * Create a new table class that will extend the WP_List_Table
 *
 * @package suremembers
 *
 * @since 1.0.0
 */
class User_Access_Table extends WP_List_Table {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		parent::__construct(
			[
				'singular' => esc_html__( 'Membership', 'suremembers-core' ),
				'plural'   => esc_html__( 'Memberships', 'suremembers-core' ),
			]
		);
	}

	/**
	 * Render column values.
	 *
	 * @param mixed $item item.
	 * @param mixed $column_name column name.
	 *
	 * @return bool|string|void
	 *
	 * @since 1.0.0
	 */
	public function column_default( $item, $column_name ) {
		$user_id      = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : get_current_user_id();// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$plan_details = Restricted::get_plan_details( $user_id, $item['id'] );

		switch ( $column_name ) {
			case 'access_group':
				$edit_url = Access_Groups::get_admin_url(
					[
						'tab'     => 'memberships',
						'section' => 'edit_membership',
						'id'      => $item['id'],
					]
				);
				$actions  = [
					'edit'   => '<a class="edit" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'suremembers-core' ) . '</a>',
					'remove' => '<a href="#" class="suremembers-user-actions" data-action="remove_access" data-access="' . esc_attr( $item['id'] ) . '" data-user="' . esc_attr( $user_id ) . '">' . esc_html__( 'Remove', 'suremembers-core' ) . '</a>',
				];
				echo sprintf(
					'<a href="' . esc_url( $edit_url ) . '">%s %s',
					esc_html( $item['title'] ),
					wp_kses_post( $this->row_actions( $actions ) )
				);
				break;
			case 'status':
				$status = ! empty( $plan_details ) && is_array( $plan_details ) && isset( $plan_details['status'] ) ? $plan_details['status'] : '';
				echo esc_html( $status );
				break;
			case 'created_on':
				if ( ! empty( $plan_details ) && is_array( $plan_details ) ) {
					if ( isset( $plan_details['created'] ) && ! empty( $plan_details['created'] ) ) {
						/* translators:  %1$s Time created. */
						echo sprintf( esc_html__( 'Created %1$s ago', 'suremembers-core' ), esc_html( human_time_diff( $plan_details['created'], current_time( 'U' ) ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
					}
				}
				break;
			case 'updated_on':
				if ( ! empty( $plan_details ) && is_array( $plan_details ) ) {
					if ( isset( $plan_details['modified'] ) && ! empty( $plan_details['modified'] ) ) {
						/* translators:  %1$s Time modified. */
						echo sprintf( esc_html__( 'Updated %1$s ago', 'suremembers-core' ), esc_html( human_time_diff( $plan_details['modified'], current_time( 'U' ) ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
					}
				}
				break;
			case 'integration':
				if ( ! empty( $plan_details ) && is_array( $plan_details ) ) {
					if ( ! empty( $plan_details['integration'] ) ) {
						$logo_url = Utils::integration_icons( $plan_details['integration'] );
						if ( is_string( $logo_url ) && ! empty( $logo_url ) ) {
							$logo = file_get_contents( $logo_url );// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
							if ( $logo ) {
								$image = 'data:image/svg+xml;base64,' . base64_encode( $logo );// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
								echo '<img title="' . esc_attr( $plan_details['integration'] ) . '" style="height:24px;" src="' . esc_attr( $image ) . '">'; // phpcs:ignore WordPressVIPMinimum.Security.ProperEscapingFunction.hrefSrcEscUrl
								// Ignoring above as we need to display base64 image in src and data is local from plugin.
							}
						}
					}
				}
				break;
			case 'action':
				if ( Access_Groups::check_plan_active( $user_id, $item['id'] ) ) {
					$action = 'revoke_access';
					$label  = __( 'Revoke Access', 'suremembers-core' );
				} else {
					$action = 'grant_access';
					$label  = __( 'Grant Access', 'suremembers-core' );
				}
				?>
				<a href="#" class="suremembers-user-actions" data-action="<?php echo esc_attr( $action ); ?>" data-access="<?php echo esc_attr( strval( $item['id'] ) ); ?>" data-user="<?php echo esc_attr( strval( $user_id ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php
				break;
			case 'expire_date':
				$expiration  = get_post_meta( $item['id'], SUREMEMBERS_PLAN_EXPIRATION, true );
				$user_expire = get_user_meta( $user_id, SUREMEMBERS_USER_EXPIRATION, true );

				if ( ! empty( $expiration ) && is_array( $expiration ) ) {
					// Check if expiration is enabled.
					$expiration_enabled = true;
					if ( isset( $expiration['enable'] ) ) {
						$enable_value       = $expiration['enable'];
						$expiration_enabled = ! ( $enable_value === false ||
							$enable_value === '0' ||
							$enable_value === 0 ||
							strtolower( $enable_value ) === 'off' ||
							strtolower( $enable_value ) === 'false' );
					}

					if ( $expiration_enabled ) {
						// Determine type - use type field if exists, otherwise infer from data.
						$expiration_type = '';
						if ( isset( $expiration['type'] ) && ! empty( $expiration['type'] ) ) {
							$expiration_type = $expiration['type'];
						} else {
							// Infer type from available data.
							if ( isset( $expiration['specific_date'] ) && ! empty( trim( $expiration['specific_date'] ) ) ) {
								$expiration_type = 'specific_date';
							} elseif ( isset( $expiration['delay'] ) && ! empty( trim( $expiration['delay'] ) ) && intval( $expiration['delay'] ) > 0 ) {
								$expiration_type = 'relative_date';
							}
						}

						// Display based on type.
						if ( $expiration_type === 'relative_date' ) {
							if ( isset( $expiration['delay'] ) && ! empty( trim( $expiration['delay'] ) ) ) {
								$delay = intval( $expiration['delay'] );
								if ( $delay > 0 ) {
									$current_date = time();
									if ( is_array( $plan_details ) ) {
										// Anchor on `modified` first, falling back to `created`, to stay consistent
										// with the SureMembers dashboard and the actual access-enforcement logic
										// (Access_Groups::get_user_expiration_timestamp()).
										if ( ! empty( $plan_details['modified'] ) ) {
											$current_date = intval( $plan_details['modified'] );
										} elseif ( ! empty( $plan_details['created'] ) ) {
											$current_date = intval( $plan_details['created'] );
										}
									}

									$future_timestamp = strtotime( '+' . $delay . ' days', $current_date );

									if ( $future_timestamp !== false ) {
										$future_date = date( 'Y-m-d', $future_timestamp ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

										if ( isset( $user_expire ) && is_array( $user_expire ) && isset( $user_expire[ $item['id'] ] ) && ! empty( trim( $user_expire[ $item['id'] ] ) ) ) {
											$expire_date = sanitize_text_field( strval( $user_expire[ $item['id'] ] ) );
										} else {
											$expire_date = $future_date;
										}

										if ( ! empty( $expire_date ) ) {
											if ( current_user_can( 'manage_options' ) ) {
												?>
												<input type="date" class="suremembers-expire-date" value="<?php echo esc_attr( $expire_date ); ?>" data-access="<?php echo esc_attr( $item['id'] ); ?>"  data-user="<?php echo esc_attr( strval( $user_id ) ); ?>"/>
												<?php
											} else {
												echo esc_html( $expire_date );
											}
										}
									}
								}
							}
						} elseif ( $expiration_type === 'specific_date' ) {
							if ( isset( $expiration['specific_date'] ) && ! empty( trim( $expiration['specific_date'] ) ) ) {
								// Check if user has a custom expiration date set.
								$expire_date = '';
								if ( isset( $user_expire ) && is_array( $user_expire ) && isset( $user_expire[ $item['id'] ] ) && ! empty( trim( $user_expire[ $item['id'] ] ) ) ) {
									// Use user-specific expiration date if set.
									$expire_date = sanitize_text_field( strval( $user_expire[ $item['id'] ] ) );
								} else {
									// Use access group's default specific date.
									$expire_date = trim( $expiration['specific_date'] );
								}

								if ( ! empty( $expire_date ) ) {
									if ( current_user_can( 'manage_options' ) ) {
										?>
										<input type="date" class="suremembers-expire-date" value="<?php echo esc_attr( $expire_date ); ?>" data-access="<?php echo esc_attr( $item['id'] ); ?>"  data-user="<?php echo esc_attr( strval( $user_id ) ); ?>"/>
										<?php
									} else {
										echo esc_html( $expire_date );
									}
								}
							}
						}
					}
				}
				break;
		}
	}

	/**
	 * Show columns.
	 *
	 * @return array<string, mixed> Columns.
	 *
	 * @since 1.0.0
	 */
	public function get_columns() {
		return [
			'access_group' => esc_html__( 'Membership', 'suremembers-core' ),
			'status'       => esc_html__( 'Status', 'suremembers-core' ),
			'created_on'   => esc_html__( 'Created On', 'suremembers-core' ),
			'updated_on'   => esc_html__( 'Updated On', 'suremembers-core' ),
			'integration'  => esc_html__( 'Integration', 'suremembers-core' ),
			'action'       => esc_html__( 'Action', 'suremembers-core' ),
			'expire_date'  => esc_html__( 'Expiration', 'suremembers-core' ),
		];
	}

	/**
	 * Get sortable columns.
	 *
	 * @return Array
	 *
	 * @since 1.0.0
	 */
	public function get_sortable_columns() {
		return [];
	}

	/**
	 * Add bulk operations.
	 *
	 * @return Array
	 *
	 * @since 1.0.0
	 */
	public function get_bulk_actions() {
		return [];
	}

	/**
	 * Prepare items.
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = [];
		$sortable = $this->get_sortable_columns();

		$data = $this->table_data();

		$per_page     = 10;
		$current_page = $this->get_pagenum();

		$this->set_pagination_args(
			[
				'total_items' => count( $data ),
				'per_page'    => $per_page,
			]
		);

		$data = array_slice( $data, ( $current_page - 1 ) * $per_page, $per_page );

		$this->_column_headers = [ $columns, $hidden, $sortable ];
		$this->items           = $data;
	}

	/**
	 * Displays the table.
	 *
	 * @since 1.0.1
	 */
	public function display() {
		$singular = $this->_args['singular'];
		$this->screen->render_screen_reader_content( 'heading_list' );
		?>
		<table class="wp-list-table <?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">
			<thead>
				<tr>
					<?php $this->print_column_headers(); ?>
				</tr>
			</thead>
			<tbody id="the-list"
				<?php
				if ( $singular ) {
					echo " data-wp-lists='list:" . esc_attr( $singular ) . "'";
				}
				?>
				>
				<?php $this->display_rows_or_placeholder(); ?>
			</tbody>

			<tfoot>
			<tr>
				<?php $this->print_column_headers( false ); ?>
			</tr>
			</tfoot>

		</table>
		<?php
		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Get the table data
	 *
	 * @return Array Table Data.
	 *
	 * @since 1.0.0
	 */
	private function table_data() {
		$user_id           = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : get_current_user_id();// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data              = [];
		$user_access_group = get_user_meta( $user_id, SUREMEMBERS_USER_META, true );

		if ( ! is_array( $user_access_group ) ) {
			return $data;
		}

		$user_access_group = array_filter(
			$user_access_group,
			static function ( $access_group ) {
				$result = Access_Groups::is_active_access_group( $access_group );
				return (bool) $result; // Ensure boolean return for array_filter.
			}
		);

		if ( ! empty( $user_access_group ) && is_array( $user_access_group ) ) {
			foreach ( $user_access_group as $key => $access_group_id ) {
				$data[ $key ] = [
					'id'    => $access_group_id,
					'title' => get_the_title( $access_group_id ),
				];
			}
		}
		return $data;
	}
}
