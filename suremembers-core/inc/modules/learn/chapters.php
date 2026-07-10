<?php
/**
 * Learn module — chapters structure.
 *
 * Defines the ordered list of chapters and steps shown inside the Learn tab.
 * The structure is filterable via `suremembers_learn_chapters` so that
 * SureMembers (Pro) or other extensions can inject additional content.
 *
 * @package SureMembersCore
 * @since 1.1.0
 */

namespace SureMembersCore\Inc\Modules\Learn;

defined( 'ABSPATH' ) || exit;

/**
 * Static chapters registry.
 *
 * @since 1.1.0
 */
class Chapters {
	/**
	 * Build and return the chapters structure.
	 *
	 * Each chapter is an associative array with:
	 *  - id          string Unique chapter identifier (kebab-case).
	 *  - title       string Localized chapter title.
	 *  - description string Localized chapter description.
	 *  - docsUrl     string External documentation URL.
	 *  - steps       array  List of step arrays.
	 *
	 * Each step is an associative array with:
	 *  - id           string Unique step identifier within the chapter.
	 *  - title        string Localized step title.
	 *  - description  string Localized step description.
	 *  - screenshot   array  { url: string, alt: string } — preview image.
	 *  - headerAction array  { label: string, url: string } CTA button.
	 *  - isPro        bool   Whether the step requires SureMembers (Pro).
	 *
	 * @since 1.1.0
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_structure(): array {
		$dashboard_url     = admin_url( 'admin.php?page=suremembers' );
		$settings_base     = $dashboard_url . '&tab=settings';
		$create_membership = $dashboard_url . '&tab=memberships&section=new_membership';
		$memberships_list  = $dashboard_url . '&tab=memberships';
		$members_list      = $dashboard_url . '&tab=users';
		$login_customizer  = $settings_base . '&section=login_form';
		$admin_settings    = $settings_base . '&section=admin_settings';
		$redirection_rules = $settings_base . '&section=redirection_rules';
		$create_user_roles = $settings_base . '&section=create_user_roles';
		$import_users      = $settings_base . '&section=import_user';
		$email_templates   = $settings_base . '&section=email';

		$is_pro_active = defined( 'SUREMEMBERS_VER' );
		$pricing_url   = 'https://suremembers.com/pricing/';

		$chapters = [
			[
				'id'          => 'set-up-membership-site',
				'title'       => __( 'Set Up Your Membership Site', 'suremembers-core' ),
				'description' => __( 'Lay the foundation — protect your content and shape how members sign in.', 'suremembers-core' ),
				'steps'       => [
					[
						'id'           => 'create-membership',
						'title'        => __( 'Create Your First Membership', 'suremembers-core' ),
						'description'  => __( 'Create an access group to decide which content, pages, and posts your members can unlock.', 'suremembers-core' ),
						'screenshot'   => [
							'url' => 'https://suremembers.com/wp-content/uploads/2026/06/BSF-Suman-Dashboard-‹-S…-2026-06-08-at-10.15.36-AM.jpg',
							'alt' => __( 'Create your first membership', 'suremembers-core' ),
						],
						'headerAction' => [
							'label' => __( 'Create Membership', 'suremembers-core' ),
							'url'   => $create_membership . '&source=learn-membership',
						],
						'isPro'        => false,
					],
					[
						'id'           => 'choose-protected-content',
						'title'        => __( 'Choose What to Protect', 'suremembers-core' ),
						'description'  => __( 'Open your membership and select the pages, posts, custom post types, or URLs that should be locked behind it.', 'suremembers-core' ),
						'screenshot'   => [
							'url' => 'https://suremembers.com/wp-content/uploads/2026/06/BSF-Suman-Dashboard-‹-S…-2026-06-08-at-10.16.50-AM.jpg',
							'alt' => __( 'Choose what to protect', 'suremembers-core' ),
						],
						'headerAction' => [
							'label' => __( 'Select Protected Content', 'suremembers-core' ),
							'url'   => $memberships_list . '&source=learn-protect',
						],
						'isPro'        => false,
					],
					[
						'id'           => 'set-restricted-experience',
						'title'        => __( 'Set What Visitors See When Locked', 'suremembers-core' ),
						'description'  => __( 'Decide what happens when a visitor without access lands on protected content — redirect them, show a custom message, or prompt them to log in.', 'suremembers-core' ),
						'screenshot'   => [
							'url' => 'https://suremembers.com/wp-content/uploads/2026/06/BSF-Suman-Dashboard-‹-S…-2026-06-08-at-10.35.20-AM.jpg',
							'alt' => __( 'Set the restricted experience', 'suremembers-core' ),
						],
						'headerAction' => [
							'label' => __( 'Configure Restricted Access', 'suremembers-core' ),
							'url'   => $memberships_list . '&source=learn-unauthorized',
						],
						'isPro'        => false,
					],
					[
						'id'           => 'customize-login',
						'title'        => __( 'Customize the Login Page', 'suremembers-core' ),
						'description'  => __( 'Personalize the login form with your logo, colors, and branding so members feel at home.', 'suremembers-core' ),
						'screenshot'   => [
							'url' => 'https://suremembers.com/wp-content/uploads/2026/06/BSF-Suman-Dashboard-‹-S…-2026-06-08-at-10.36.01-AM.jpg',
							'alt' => __( 'Customize the login page', 'suremembers-core' ),
						],
						'headerAction' => [
							'label' => __( 'Open Login Customizer', 'suremembers-core' ),
							'url'   => $login_customizer . '&source=learn-login',
						],
						'isPro'        => false,
					],
				],
			],
			[
				'id'          => 'bring-in-members',
				'title'       => __( 'Bring In & Manage Members', 'suremembers-core' ),
				'description' => __( 'Get members into your memberships and control how they sign in and out.', 'suremembers-core' ),
				'steps'       => [
					[
						'id'           => 'add-members',
						'title'        => __( 'Add Members to a Membership', 'suremembers-core' ),
						'description'  => __( 'Assign existing users to a membership so they unlock its protected content right away.', 'suremembers-core' ),
						'screenshot'   => [
							'url' => 'https://suremembers.com/wp-content/uploads/2026/06/BSF-Suman-Dashboard-‹-S…-2026-06-08-at-10.36.46-AM.jpg',
							'alt' => __( 'Add members to a membership', 'suremembers-core' ),
						],
						'headerAction' => [
							'label' => __( 'Manage Members', 'suremembers-core' ),
							'url'   => $members_list . '&source=learn-add-members',
						],
						'isPro'        => false,
					],
					[
						'id'           => 'auto-grant-registration',
						'title'        => __( 'Auto-Grant Access on Registration', 'suremembers-core' ),
						'description'  => __( 'Pick a membership that is automatically granted to every new user who registers on your site.', 'suremembers-core' ),
						'screenshot'   => [
							'url' => 'https://suremembers.com/wp-content/uploads/2026/06/BSF-Suman-Dashboard-‹-S…-2026-06-08-at-10.37.53-AM.jpg',
							'alt' => __( 'Auto-grant access on registration', 'suremembers-core' ),
						],
						'headerAction' => [
							'label' => __( 'Open Admin Settings', 'suremembers-core' ),
							'url'   => $admin_settings . '&source=learn-registration',
						],
						'isPro'        => false,
					],
					[
						'id'           => 'login-redirects',
						'title'        => __( 'Set Login & Logout Redirects', 'suremembers-core' ),
						'description'  => __( 'Send members to the right place after they log in or out — like a dashboard, course, or welcome page.', 'suremembers-core' ),
						'screenshot'   => [
							'url' => 'https://suremembers.com/wp-content/uploads/2026/06/BSF-Suman-Dashboard-‹-S…-2026-06-08-at-10.38.39-AM.jpg',
							'alt' => __( 'Set login and logout redirects', 'suremembers-core' ),
						],
						'headerAction' => [
							'label' => __( 'Set Redirection Rules', 'suremembers-core' ),
							'url'   => $redirection_rules . '&source=learn-redirects',
						],
						'isPro'        => false,
					],
				],
			],
			[
				'id'          => 'grow-with-pro',
				'title'       => __( 'Grow with Pro', 'suremembers-core' ),
				'description' => __( 'Scale your membership site with advanced features available in SureMembers Pro.', 'suremembers-core' ),
				'steps'       => [
					[
						'id'           => 'create-user-roles',
						'title'        => __( 'Create User Roles', 'suremembers-core' ),
						'description'  => __( 'Define custom user roles and sync them with memberships to manage access at scale.', 'suremembers-core' ),
						'screenshot'   => [
							'url' => 'https://suremembers.com/wp-content/uploads/2026/06/BSF-Suman-Dashboard-‹-S…-2026-06-08-at-10.39.46-AM.jpg',
							'alt' => __( 'Create user roles', 'suremembers-core' ),
						],
						'headerAction' => [
							'label' => $is_pro_active ? __( 'Create User Roles', 'suremembers-core' ) : __( 'Upgrade to Pro', 'suremembers-core' ),
							'url'   => $is_pro_active ? $create_user_roles . '&source=learn-user-roles' : $pricing_url . '?source=learn-user-roles',
						],
						'isPro'        => ! $is_pro_active,
					],
					[
						'id'           => 'import-users',
						'title'        => __( 'Import Users', 'suremembers-core' ),
						'description'  => __( 'Bulk-import existing users via CSV and assign them to memberships in one go.', 'suremembers-core' ),
						'screenshot'   => [
							'url' => 'https://suremembers.com/wp-content/uploads/2026/06/BSF-Suman-Dashboard-‹-S…-2026-06-08-at-10.40.35-AM.jpg',
							'alt' => __( 'Import users', 'suremembers-core' ),
						],
						'headerAction' => [
							'label' => $is_pro_active ? __( 'Import Users', 'suremembers-core' ) : __( 'Upgrade to Pro', 'suremembers-core' ),
							'url'   => $is_pro_active ? $import_users . '&source=learn-import-users' : $pricing_url . '?source=learn-import-users',
						],
						'isPro'        => ! $is_pro_active,
					],
					[
						'id'           => 'setup-emails',
						'title'        => __( 'Set Up Email Templates', 'suremembers-core' ),
						'description'  => __( 'Customize the emails members receive when memberships are granted, revoked, or expire.', 'suremembers-core' ),
						'screenshot'   => [
							'url' => 'https://suremembers.com/wp-content/uploads/2026/06/BSF-Suman-Dashboard-‹-S…-2026-06-08-at-10.41.40-AM.jpg',
							'alt' => __( 'Set up email templates', 'suremembers-core' ),
						],
						'headerAction' => [
							'label' => $is_pro_active ? __( 'Open Email Templates', 'suremembers-core' ) : __( 'Upgrade to Pro', 'suremembers-core' ),
							'url'   => $is_pro_active ? $email_templates . '&source=learn-emails' : $pricing_url . '?source=learn-emails',
						],
						'isPro'        => ! $is_pro_active,
					],
				],
			],
		];

		/**
		 * Filter the Learn tab chapters structure.
		 *
		 * SureMembers (Pro) uses this to replace Pro-gated steps with live CTAs
		 * that point to real admin pages when Pro is active.
		 *
		 * @since 1.1.0
		 * @param array<int, array<string, mixed>> $chapters Default chapters.
		 */
		return apply_filters( 'suremembers_learn_chapters', $chapters );
	}
}
