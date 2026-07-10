=== SureMembers - Membership & Content Restriction Plugin ===
Contributors: brainstormforce
Tags: membership, content restriction, members only, paywall, access control
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect your content, build memberships, and control who sees what on your WordPress site, without writing a single line of code.

== Description ==

**SureMembers turns your WordPress site into a powerful membership platform.**

Lock content, build unlimited membership levels, and decide exactly who has access to what. Whether you run a paid membership site, a course platform, or simply want to share content with a select group, SureMembers gives you everything you need to get started.

Set it up in minutes. No complicated configurations. No monthly per-member fees. Everything runs natively on WordPress, under your brand, with your data.

**[Try the live demo](https://app.zipwp.com/blueprint/suremembers-demo-48o)** (no signup required).

== Why SureMembers? ==

Most membership tools either lock you into expensive monthly plans or require hours of configuration. SureMembers takes a different approach: a clean, focused content restriction solution that works the way you'd expect WordPress to work.

You stay in control of your content, your members, and your business. Pair it with your favorite payment platform like SureCart, and you have a complete membership system running on WordPress.

== What You Can Build ==

**Membership Sites**: Create unlimited membership levels and decide what each level can access.

**Paid Courses**: Protect lessons and course content based on membership.

**Private Content Libraries**: Share guides, resources, and downloads with paying members only.

**Subscription Communities**: Restrict access to community pages, posts, and discussions.

**Premium Newsletters**: Gate articles and posts behind a membership.

**Resource Hubs**: Give different access levels to different customer tiers.

== Free Features ==

* Unlimited memberships: create as many access groups as you need
* Content protection for posts, pages, and custom post types
* Unauthorized access handling with custom redirects
* LMS integrations for popular learning platforms
* Redirection rules for login, logout, and restricted content
* Login customizer to brand your login page
* Clean, intuitive admin dashboard
* Works with any WordPress theme
* Lightweight and fast, no bloat
* Mobile-friendly responsive design
* Translation-ready

== SureMembers Premium ==

Take your membership site further with SureMembers Premium:

* **User Role Sync**: Automatically sync WordPress user roles with membership access groups
* **Block-Level Restrictions**: Restrict individual blocks within posts and pages
* **Drip Content**: Schedule when members get access to specific content
* **Downloads Protection**: Protect file downloads behind membership levels
* **Login Restrictions**: Control login attempts, session limits, and access rules
* **Email Templates**: Customize member onboarding and notification emails
* **Import Users via CSV**: Bulk import existing users into access groups
* **Advanced Protected Content**: Protect all posts, archives, CPTs, and URL-based content

[Learn more about SureMembers Premium](https://suremembers.com)

== Works Great With ==

SureMembers is part of a growing WordPress ecosystem:

* **[SureCart](https://surecart.com/)**: Sell memberships, courses, and digital products. SureCart pairs perfectly with SureMembers to power your entire membership business.
* **[SureDash](https://suredash.com/)**: Build a community right inside WordPress with discussion spaces, courses, and member dashboards. Use SureMembers to control access to portals and spaces.
* **[Astra Theme](https://wpastra.com/)**: The most popular WordPress theme, fully compatible with SureMembers.

Each plugin works independently, but together they give you a complete membership and community business, all on WordPress.

== Perfect For ==

* Course creators selling online courses
* Coaches running paid coaching programs
* Bloggers offering premium content
* Membership sites with multiple tiers
* Communities that need private spaces
* Businesses sharing resources with customers
* Anyone who wants to control content access on WordPress

== Installation ==

1. Go to **Plugins > Add New** in your WordPress dashboard
2. Search for **SureMembers**
3. Click **Install Now**, then **Activate**
4. Go to **SureMembers** in your admin menu to create your first access group

Or upload the plugin zip file via **Plugins > Add New > Upload Plugin**.

== Frequently Asked Questions ==

= Do I need any coding skills? =

Not at all. SureMembers works out of the box. Install it, create an access group, and start protecting your content. Everything is point-and-click.

= Will it work with my theme? =

Yes. SureMembers is designed to work with any standard WordPress theme. It does not change your site's appearance; it simply controls who can access your content.

= Can I sell memberships with SureMembers? =

SureMembers handles content restriction. To sell memberships, pair it with SureCart or any compatible payment plugin. SureMembers will automatically grant access when a customer purchases.

= Can I have multiple membership levels? =

Yes. You can create unlimited access groups, each with their own rules for what content they can access.

= Will it work with my LMS plugin? =

Yes. SureMembers integrates with popular LMS plugins so you can protect lessons and courses based on membership level.

= Is my data safe? =

Your data stays on your WordPress site. SureMembers does not store member information on external servers. You own everything.

= How can I report a security bug? =

We take plugin security seriously. Report vulnerabilities through our [Bug Bounty Program](https://brainstormforce.com/bug-bounty-program/). We collaborate with Patchstack to validate and triage security issues responsibly.

== Links ==

* [SureMembers Website](https://suremembers.com)
* [Documentation](https://suremembers.com/docs)
* [Support](https://suremembers.com/support)

== Changelog ==

= 2026-07-08 - version 1.2.2 =
* Improvement: Added the missing Analytics link in the admin sidebar so the Analytics page can be opened directly from the menu.
* Improvement: Switched the admin bar restriction script to WordPress's built-in element library for better compatibility and a smaller footprint.
* Fix: Fixed a security issue where redirect URL suggestions could render unsafe HTML; titles are now decoded and displayed safely.
* Fix: Fixed an issue where a membership's relative expiration date shown on the WordPress user profile screen could differ from the date shown in SureMembers and from when access actually ends.

= 2026-06-29 - version 1.2.1 =
* New: A new Analytics tab with membership growth insights, recent activity, upcoming expirations, and per-membership snapshots.
* Improvement: Improved membership status handling by automatically checking and updating expired memberships in the background.
* Improvement: Draft memberships now appear as disabled in the user details popup and can no longer be granted or revoked.
* Fix: Fixed an issue where some blocks, including Gravity Forms blocks, could show errors when used with SureMembers.
* Fix: Fixed an issue that could cause redirect loops in certain protected page settings.
* Fix: Fixed an issue on Multisite networks where viewing a user's profile could silently remove their membership assignments from other sites in the network.
* Fix: Fixed an issue where the expiration date set from the user details popup was not getting saved.

= 2026-06-17 - version 1.2.0 =
* New: Introduced WordPress Abilities support for managing memberships and accessing membership analytics, along with new MCP settings integration.
* New: Added the suremembers_list_members shortcode to display members from specific Access Groups directly on pages and posts.
* Improvement: Enhanced overall UI/UX across the plugin for a smoother and more intuitive user experience.

= 2026-06-08 - version 1.1.0 =
* New: Added a Learn tab to help users complete setup and configure the plugin more easily.
* Improvement: Added French, Dutch, and Polish language translations for improved localization support.
* Fix: Improved security by ensuring access restrictions are properly enforced across all post types, REST API requests, and SureDash content access.

= 2026-06-03 - version 1.0.2 =
* Fix: Resolved a JavaScript console error on admin pages caused by a missing UI styles dependency.

= 2026-06-02 - version 1.0.1 =
* Fix: Handled onboarding redirection while installing plugins through the onboarding process.

= 2026-06-02 - version 1.0.0 =
* Initial release of SureMembers Core, the free version of SureMembers.
