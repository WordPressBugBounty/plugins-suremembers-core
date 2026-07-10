<?php
/**
 * Member List.
 *
 * @package suremembers
 *
 * @since 1.2.0
 */

namespace SureMembersCore\Inc;

use SureMembersCore\Inc\Traits\Get_Instance;

defined( 'ABSPATH' ) || exit;

/**
 * Member List
 *
 * Renders a list of active users belonging to an access group via the
 * [suremembers_list_members] shortcode.
 *
 * @since 1.2.0
 */
class Member_List {
	use Get_Instance;

	/**
	 * Constructor
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		add_shortcode( 'suremembers_list_members', [ $this, 'render_member_list' ] );
	}

	/**
	 * Generates a list of members for the 'suremembers_list_members' shortcode.
	 *
	 * Supported attributes:
	 * - membership_id (int)  Membership (Access Group) ID to list members from. Required.
	 * - limit         (int)  Maximum number of members to display. 0 for no limit. Default 0.
	 * - layout        (string) Display layout: 'grid' (cards) or 'list' (rows). Default 'grid'.
	 * - show_avatar   (bool) Whether to display each member's avatar. Default true.
	 * - show_email    (bool) Whether to display each member's email. Default true.
	 * - show_joined   (bool) Whether to display the date the member joined. Default false.
	 * - show_expiry   (bool) Whether to display the access expiry date. Default false.
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 *
	 * @return string Rendered member list HTML.
	 *
	 * @since 1.2.0
	 */
	public function render_member_list( $atts ) {
		$atts = shortcode_atts(
			[
				'membership_id' => 0,
				'limit'         => 0,
				'layout'        => 'grid',
				'show_avatar'   => 'true',
				'show_email'    => 'true',
				'show_joined'   => 'false',
				'show_expiry'   => 'false',
			],
			is_array( $atts ) ? $atts : [],
			'suremembers_list_members'
		);

		$membership_id = absint( $atts['membership_id'] );
		$limit         = absint( $atts['limit'] );
		$layout        = strtolower( (string) $atts['layout'] ) === 'list' ? 'list' : 'grid';
		$show_avatar   = filter_var( $atts['show_avatar'], FILTER_VALIDATE_BOOLEAN );
		$show_email    = filter_var( $atts['show_email'], FILTER_VALIDATE_BOOLEAN );
		$show_joined   = filter_var( $atts['show_joined'], FILTER_VALIDATE_BOOLEAN );
		$show_expiry   = filter_var( $atts['show_expiry'], FILTER_VALIDATE_BOOLEAN );

		if ( empty( $membership_id ) ) {
			return '';
		}

		// Determine pagination when a per-page limit is set.
		$current_page = 1;
		$total_pages  = 1;
		$offset       = 0;

		if ( $limit > 0 ) {
			$total = Access_Groups::get_users_count( $membership_id );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public listing, no state change.
			$current_page = isset( $_GET['sm_member_page'] ) ? max( 1, absint( wp_unslash( $_GET['sm_member_page'] ) ) ) : 1;
			$total_pages  = max( 1, (int) ceil( $total / $limit ) );
			$current_page = min( $current_page, $total_pages );
			$offset       = ( $current_page - 1 ) * $limit;
		}

		$users = Access_Groups::get_users_by_group( $membership_id, $limit, $offset );

		if ( empty( $users ) ) {
			return '<p class="suremembers-member-list__empty">' . esc_html__( 'No members found.', 'suremembers-core' ) . '</p>';
		}

		$this->enqueue_styles();

		$date_format = (string) get_option( 'date_format', 'F j, Y' );

		$items = '';
		foreach ( $users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$items .= $this->render_item(
				$user,
				$membership_id,
				[
					'show_avatar' => $show_avatar,
					'show_email'  => $show_email,
					'show_joined' => $show_joined,
					'show_expiry' => $show_expiry,
					'date_format' => $date_format,
				]
			);
		}

		$output = sprintf(
			'<ul class="suremembers-member-list suremembers-member-list--%1$s">%2$s</ul>',
			esc_attr( $layout ),
			$items
		);

		if ( $total_pages > 1 ) {
			$output .= $this->render_pagination( $current_page, $total_pages );
		}

		/**
		 * Filters the rendered member list HTML output.
		 *
		 * @param string               $output        The member list HTML.
		 * @param array<int, \WP_User> $users         The members being listed.
		 * @param int                  $membership_id The membership (access group) ID.
		 *
		 * @since 1.2.0
		 */
		return apply_filters( 'suremembers_member_list_output', $output, $users, $membership_id );
	}

	/**
	 * Render a single member item.
	 *
	 * @param \WP_User             $user          The member.
	 * @param int                  $membership_id Membership (access group) ID.
	 * @param array<string, mixed> $args          Display flags: show_avatar, show_email,
	 *                                             show_joined, show_expiry, date_format.
	 *
	 * @return string Member list item HTML.
	 *
	 * @since 1.2.0
	 */
	private function render_item( $user, $membership_id, $args ) {
		// Avatar (or coloured initial badge when avatars are disabled).
		if ( ! empty( $args['show_avatar'] ) ) {
			$avatar = '<span class="suremembers-member-list__avatar">' . get_avatar( $user->ID, 96 ) . '</span>';
		} else {
			$initial = strtoupper( mb_substr( $user->display_name, 0, 1 ) );
			$avatar  = '<span class="suremembers-member-list__avatar suremembers-member-list__avatar--initial">' . esc_html( $initial ) . '</span>';
		}

		$meta = '';

		if ( ! empty( $args['show_email'] ) && ! empty( $user->user_email ) ) {
			$meta .= sprintf(
				'<span class="suremembers-member-list__meta suremembers-member-list__email">%s</span>',
				esc_html( $user->user_email )
			);
		}

		if ( ! empty( $args['show_joined'] ) ) {
			$joined = Access_Groups::get_user_join_timestamp( $membership_id, $user->ID );
			if ( $joined > 0 ) {
				$meta .= sprintf(
					'<span class="suremembers-member-list__meta suremembers-member-list__joined">%1$s %2$s</span>',
					esc_html__( 'Joined:', 'suremembers-core' ),
					esc_html( (string) wp_date( $args['date_format'], $joined ) )
				);
			}
		}

		if ( ! empty( $args['show_expiry'] ) ) {
			$expiry = Access_Groups::get_user_expiration_timestamp( $membership_id, $user->ID );
			$value  = $expiry > 0 ? (string) wp_date( $args['date_format'], $expiry ) : __( 'Lifetime', 'suremembers-core' );
			$meta  .= sprintf(
				'<span class="suremembers-member-list__meta suremembers-member-list__expiry">%1$s %2$s</span>',
				esc_html__( 'Expires:', 'suremembers-core' ),
				esc_html( $value )
			);
		}

		return sprintf(
			'<li class="suremembers-member-list__item">%1$s<span class="suremembers-member-list__body"><span class="suremembers-member-list__name">%2$s</span>%3$s</span></li>',
			$avatar,
			esc_html( $user->display_name ),
			$meta
		);
	}

	/**
	 * Render numbered pagination links for the member list.
	 *
	 * Uses the `sm_member_page` query argument and preserves any other query
	 * args already present on the current URL.
	 *
	 * @param int $current_page Current page number.
	 * @param int $total_pages  Total number of pages.
	 *
	 * @return string Pagination HTML, or empty string when not applicable.
	 *
	 * @since 1.2.0
	 */
	private function render_pagination( $current_page, $total_pages ) {
		$links = paginate_links(
			[
				'base'      => esc_url_raw( add_query_arg( 'sm_member_page', '%#%' ) ),
				'format'    => '',
				'current'   => $current_page,
				'total'     => $total_pages,
				'add_args'  => [],
				'prev_text' => __( '&laquo; Previous', 'suremembers-core' ),
				'next_text' => __( 'Next &raquo;', 'suremembers-core' ),
			]
		);

		if ( empty( $links ) ) {
			return '';
		}

		// paginate_links() can return a string or an array depending on 'type'.
		if ( is_array( $links ) ) {
			$links = implode( '', $links );
		}

		return '<nav class="suremembers-member-list__pagination" aria-label="' . esc_attr__( 'Member list pagination', 'suremembers-core' ) . '">' . $links . '</nav>';
	}

	/**
	 * Enqueue the member list stylesheet.
	 *
	 * Loaded on demand only when the shortcode is rendered so the CSS is not
	 * shipped on pages that do not use the member list.
	 *
	 * @since 1.2.0
	 */
	private function enqueue_styles(): void {
		wp_enqueue_style(
			'suremembers-member-list',
			SUREMEMBERS_CORE_URL . 'assets/css/member-list.css',
			[],
			SUREMEMBERS_CORE_VER
		);
	}
}
