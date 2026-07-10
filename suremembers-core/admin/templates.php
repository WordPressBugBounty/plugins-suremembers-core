<?php
/**
 * Admin Templates.
 *
 * @package suremembers
 *
 * @since 1.0.0
 */

namespace SureMembersCore\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin Templates
 *
 * @since 0.0.1
 */
class Templates {
	/**
	 * HTML to choose available access groups
	 *
	 * @param int $id current menu navigation id.
	 *
	 * @since 1.0.0
	 */
	public static function menu_restriction_markup( $id ) {
		$saved_access_groups  = get_post_meta( $id, SUREMEMBERS_ACCESS_GROUPS, true );
		$menu_user_condition  = get_post_meta( $id, SUREMEMBERS_MENU_USER_CONDITION, true );
		$menu_user_condition  = is_string( $menu_user_condition ) ? $menu_user_condition : '';
		$saved_user_condition = ! empty( $menu_user_condition ) ? esc_html( $menu_user_condition ) : 'is_in';
		$sid                  = strval( $id );
		?>
			<p class="field-access-groups description description-wide">
				<?php wp_nonce_field( "menu-item-suremembers-access-groups-{$id}", "menu-item-suremembers-access-groups-{$id}" ); ?>
				<?php esc_html_e( 'Show menu when user', 'suremembers-core' ); ?>
				<select style="margin-bottom: 10px;" name="menu-item-suremembers-access-groups-condition[<?php echo esc_attr( $sid ); ?>]">
					<option <?php selected( $saved_user_condition, 'is_in' ); ?> value="is_in"><?php echo esc_html__( 'is in', 'suremembers-core' ); ?> </option>
					<option <?php selected( $saved_user_condition, 'is_not_in' ); ?> value="is_not_in"><?php echo esc_html__( 'is not in', 'suremembers-core' ); ?> </option>
				</select>
				<?php esc_html_e( 'Memberships:', 'suremembers-core' ); ?>
				<select multiple="multiple" style="width:100%"
					class="menu-item-suremembers-access-groups suremembers-select2"
					id="menu-item-suremembers-access-groups-<?php echo esc_attr( $sid ); ?>"
					name="menu-item-suremembers-access-groups[<?php echo esc_attr( $sid ); ?>][]">
					<?php
					if ( is_array( $saved_access_groups ) && ! empty( $saved_access_groups ) ) {
						foreach ( $saved_access_groups as $aid ) {
							?>
								<option selected='selected' value=<?php echo esc_attr( $aid ); ?>><?php echo esc_html( get_the_title( $aid ) ); ?> </option>
								<?php
						}
					}

					?>
				</select>
				<span class="description"><?php esc_html_e( 'This menu item will be hidden for user not in selected memberships.', 'suremembers-core' ); ?></span>
			</p>
		<?php
	}

	/**
	 * HTML to choose available access groups
	 *
	 * @param int $id current menu navigation id.
	 *
	 * @since 1.1.0
	 */
	public static function access_groups_markup( $id ) {
		$saved_access_groups = get_post_meta( $id, SUREMEMBERS_ACCESS_GROUPS, true );
		?>
			<p class="field-access-groups description description-wide">
				<?php wp_nonce_field( 'wc-suremembers-access-groups-nonce', 'wc-suremembers-access-groups-nonce' ); ?>
				<select multiple="multiple" style="width:100%"
					class="wc-suremembers-access-groups suremembers-select2"
					id="wc-suremembers-access-groups"
					name="wc-suremembers-access-groups[]">
					<?php
					if ( is_array( $saved_access_groups ) && ! empty( $saved_access_groups ) ) {
						foreach ( $saved_access_groups as $aid ) {
							?>
								<option selected='selected' value=<?php echo esc_attr( $aid ); ?>><?php echo esc_html( get_the_title( $aid ) ); ?> </option>
								<?php
						}
					}
					?>
				</select>
				<span class="description"><?php esc_html_e( 'Associate Memberships with this product', 'suremembers-core' ); ?></span>
			</p>
		<?php
	}

	/**
	 * HTML markup for choosing and displaying user access groups in user edit page.
	 *
	 * @param object $user User object to get data.
	 *
	 * @since 1.0.0
	 */
	public static function access_group_selection_markup( $user ) {
		$user_id = $user->ID ?? 0;
		?>
			<table id="suremembers-add-access-group-select" class="form-table" role="presentation">
				<tbody>
					<tr class="user-description-wrap">
						<th><label for="suremembers_access_groups"><?php echo esc_html__( 'Add Membership', 'suremembers-core' ); ?></label></th>
						<td>
							<select name="access_group[]" id="suremembers_access_groups" multiple></select>
							<button data-user="<?php echo esc_attr( $user_id ); ?>" id="suremembers-add-access-group" class="button button-primary"><?php echo esc_html__( 'Add Membership(s)', 'suremembers-core' ); ?></button>
							<p class="description"><?php echo esc_html__( 'Choose memberships to assign to this user.', 'suremembers-core' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		<?php
	}

	/**
	 * Users table bulk edit template.
	 *
	 * @since 1.2.0
	 */
	public static function users_bulk_edit_template() {
		?>
			<script type="text/html" id="tmpl-suremembers_users_bulk_edit_template">
				<tr class="hidden"></tr>
				<tr id="suremembers-access-groups-bulk-edit" class="inline-edit-row inline-edit-row-post bulk-edit-row bulk-edit-row-post bulk-edit-post inline-editor">
					<td colspan="{{data[0].firstHeadColSpan}}" class="colspanchange">
						<div class="inline-edit-wrapper" role="region" aria-labelledby="bulk-edit-legend" tabindex="-1">
							<fieldset class="inline-edit-col-left">
								<legend class="inline-edit-legend" id="bulk-edit-legend"><?php echo esc_html__( 'Bulk Edit', 'suremembers-core' ); ?></legend>
								<div class="suremembers-inline-edit-col">
									<div id="suremembers-bulk-title-div">
										<div id="suremembers-bulk-titles">
											<ul id="suremembers-bulk-titles-list" role="list">
												<# for ( index in data ) {
												let current_user = data[index];
												#>
													<li class="ntdelitem">
														<button type="button" id="_{{current_user.id}}" class="suremembers-button-link ntdelbutton">
															<span class="screen-reader-text">{{current_user.buttonVisuallyHiddenText}}</span>
														</button>
														<span class="ntdeltitle" aria-hidden="true">{{current_user.theTitle}}</span>
													</li>
												<#
												}
												#>
											</ul>
										</div>
									</div>
								</div>
							</fieldset>
							<fieldset class="inline-edit-col-right">
								<div class="inline-edit-tags-wrap">
									<label class="inline-edit-tags">
										<span class="title"><?php echo esc_html__( 'Select Memberships', 'suremembers-core' ); ?></span>
										<select name="access_group[]" id="suremembers_access_groups" multiple></select>
									</label>
									<p class="howto" id="inline-edit-post_tag-desc"><?php echo esc_html__( 'Choose memberships to grant or revoke access to selected users.', 'suremembers-core' ); ?></p>
								</div>
							</fieldset>
							<div class="submit inline-edit-save">
								<button name="bulk_grant_access" id="bulk_grant_access" class="button button-primary"><?php echo esc_html__( 'Grant Access', 'suremembers-core' ); ?></button>
								<button name="bulk_revoke_access" id="bulk_revoke_access" class="button button-secondary"><?php echo esc_html__( 'Revoke Access', 'suremembers-core' ); ?></button>
								<button type="button" class="button cancel"><?php echo esc_html__( 'Cancel', 'suremembers-core' ); ?></button>
								<?php wp_nonce_field( 'suremembers_bulk_actions_nonce' ); ?>
							</div>
						</div> <!-- end of .inline-edit-wrapper -->
					</td>
				</tr>
			</script>
		<?php
	}

	/**
	 * Prepare the design template for email notification.
	 *
	 * @param string $from_name The name of the sender.
	 * @param string $message The Message to send in email.
	 *
	 * @return string $output The HTML format of the content template.
	 *
	 * @since 1.10.0
	 */
	public static function prepare_email_content( $from_name, $message ) {
		$site_logo_id = get_theme_mod( 'custom_logo' );

		// Get the logo URL.
		$logo_data = ! empty( $site_logo_id ) ? wp_get_attachment_image_src( intval( $site_logo_id ), 'full' ) : [];

		// Replace the base URL with the local site URL.
		$logo_url = is_array( $logo_data ) && ! empty( $logo_data[0] ) ? $logo_data[0] : '';

		ob_start();
		?>

			<table class="email-content-wrapper" style="width: 100%; font-size: 15px; background-color: #f8fafc; color: #26282c; font-family: Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica Neue,Arial,Noto Sans,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol,Noto Color Emoji;">
				<tbody>
					<tr>
						<td align="center">
							<table class="email-content" style="width: 100%;" cellpadding="0" cellspacing="0" role="presentation">
								<tbody>
									<tr>
										<td class="email-header" align="center" style="padding: 25px 45px; text-align: center;">
											<h3 style="font-size: 22px; margin: 0; font-weight: 600;"><?php echo esc_html( $from_name ); ?></h3>
										</td>
									</tr>
									<tr>
										<td class="email-body-wrapper" style="width: 100%;">
											<table class="email-body sm-w-full" style="margin-left: auto; margin-right: auto; width: 700px; background-color: #fff; border-radius: 8px;" align="center" cellpadding="0" cellspacing="0" role="presentation">
												<tbody>
													<tr>
														<td class="email-body-inner" style="padding: 35px 45px;">
															<?php echo do_shortcode( $message ); ?>
														</td>
													</tr>
												</tbody>
											</table>
										</td>
									</tr>
									<tr>
										<td class="email-footer-wrapper" style="width: 100%;">
											<table class="email-footer sm-w-full" style="padding: 45px 20px; text-align: center; margin-left: auto; margin-right: auto; width: 700px;" align="center" cellpadding="0" cellspacing="0" role="presentation">
												<tbody>
													<tr>
														<td>
															<p style="font-size: 13px; margin: 0 0 10px 0; text-align: center; line-height: 1.2rem;">
																<?php
																echo wp_kses_post(
																	apply_filters(
																		'suremembers_email_notification_footer_message',
																		sprintf(
																			/* translators: %1$s main website URL. */
																			__( 'This e-mail was sent from %1$s', 'suremembers-core' ),
																			'<a href="' . esc_url( site_url() ) . '" target="_blank">' . esc_html( get_bloginfo( 'name' ) ) . '</a>'
																		)
																	)
																);
																?>
															</p>
															<?php if ( ! empty( $logo_url ) ) { ?>
																<a href="<?php echo esc_url( site_url() ); ?>" style="text-decoration: none;">
																	<img style="width:20%;" src="<?php echo esc_url( $logo_url ); ?>">
																</a>
															<?php } ?>
														</td>
													</tr>
												</tbody>
											</table>
										</td>
									</tr>
								</tbody>
							</table>
						</td>
					</tr>
				</tbody>
			</table>

		<?php
		$output = ob_get_clean();

		return ! is_string( $output ) ? '' : $output;
	}

	/**
	 * Prepare the email design using WooCommerce email format.
	 *
	 * @param string $from_name The name of the sender.
	 * @param string $subject The email subject.
	 * @param string $message The message to send in the email.
	 *
	 * @return string $output The HTML format of the email.
	 *
	 * @since 1.10.0
	 */
	public static function prepare_woo_email_content( $from_name, $subject, $message ) {
		ob_start();

		wc_get_template( 'emails/email-header.php', [ 'email_heading' => apply_filters( 'suremembers_woo_email_heading_text', esc_html( $subject ) ) ] );
		$email_header = ob_get_clean();

		ob_start();

		wc_get_template( 'emails/email-footer.php' );
		$email_footer = ob_get_clean();

		$site_title = get_bloginfo( 'name' );

		// This below line is added to solve the PHPstan error as the str_ireplace's 3rd para require to be string and not the false.
		$email_footer = $email_footer === false ? '' : $email_footer;

		$email_footer = str_ireplace( '{site_title}', $site_title, $email_footer );

		$output = $email_header . $message . $email_footer;

		return ! is_string( $output ) ? '' : $output;
	}
}
