=== Disable Comments ===
Contributors: rbplugins
Tags: disable comments, comments, woocommerce reviews, multisite, ai
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPLv2 or later

Disable, hide, bulk-delete & auto-reply to comments and WooCommerce reviews — multisite-ready, with an AI Assistant PRO add-on.

== Description ==

**Disable Comments RB** gives you real control over comments instead of a single on/off switch: choose exactly which content types are affected, what happens to comments already on the site, and clean up what's left — all from one settings screen, with a dedicated PRO add-on for stores and agencies that need more.

📖 [Plugin Overview](https://rbplugins.com/wordpress-plugins/disable-comments-rb/) | 📚 [Documentation](https://rbplugins.com/docs/disable-comments-rb/) | 💎 [Pricing & PRO](https://rbplugins.com/wordpress-plugins/disable-comments-rb/#pricing)

🚫 **Flexible disabling**
Turn comments off everywhere, for selected content types only, or not at all. Block new comments and control the visibility of existing ones independently — hide them all, or leave them showing.

🗑️ **Bulk delete, safely**
Remove or trash comments by content type and status, with a Count step before anything is deleted so you always know the number before you commit.

📊 **Dashboard & Comments-page shortcuts**
A WordPress Dashboard widget shows your comment stats at a glance. Optional one-click shortcuts on the native Comments page and in the admin toolbar jump straight into settings.

🛡️ **Spam-channel blocking**
Closes the classic XML-RPC endpoint and the REST API's comment-creation endpoint that some spam bots use to bypass the normal comment form.

⚙️ **Built for real admin workflows**
Choose whether the settings screen lives under Settings or gets its own top-level menu item. Everything reports into Site Health → Info for easy support requests.

= 💎 PRO add-on =

Install the separate **Disable Comments RB — PRO Key** plugin to unlock:

🤖 **AI Assistant** — automatically reply to new comments that mention specific keywords, with ready-made presets (Sales, Support, Availability, Complaints). On WooCommerce, rules can also trigger by star rating alone — perfect for catching negative reviews.

🛒 **WooCommerce review management** — filter Delete by product review vs. regular comment, and see reviews broken out separately in Statistics.

📅 **Hide by date** — show only comments between two dates, before a date, or after a date, instead of all-or-nothing.

👥 **Role-based exceptions** — let editors or admins keep commenting (for internal notes) on content types where the public can't.

🌐 **Multisite enforcement** — lock the Disable settings network-wide from Network Admin.

📤 **Export / Import** — move your full configuration between sites as a JSON file.

💻 **WP-CLI** — `wp disable-comments-rb status | set | stats | delete`, scriptable and automatable.

📈 **Full Statistics tab** — live counts by status and content type, plus a running "permanently deleted" total.

No license key to enter, no account, nothing to phone home — having the PRO Key plugin installed and active **is** the unlock.

📖 [Plugin Overview](https://rbplugins.com/wordpress-plugins/disable-comments-rb/) | 📚 [Documentation](https://rbplugins.com/docs/disable-comments-rb/) | 💎 [Pricing & PRO](https://rbplugins.com/wordpress-plugins/disable-comments-rb/#pricing)

= Free features =

* Disable comments everywhere, for selected content types, or not at all
* Independent controls for blocking new comments vs. hiding existing ones
* Bulk delete or trash comments by content type + status, with a Count-first step
* Blocks comment spam via XML-RPC and the REST API
* WordPress Dashboard widget with live comment stats
* Optional shortcut button on the native Comments page
* Optional quick-access menu in the admin toolbar
* Choice of Settings submenu or a top-level admin menu item
* Site Health integration for easy support requests

= PRO features =

* AI Assistant — keyword- and (WooCommerce) star-rating-triggered auto-replies, with presets
* WooCommerce product review management (Delete filter + separate Statistics)
* Hide existing comments by date range (between, before, or after a date)
* Role-based exceptions for staff
* Multisite network-wide enforcement
* Export/import settings as JSON
* WP-CLI commands
* Full Statistics tab

Before enabling Disable Comments RB, it's a good idea to review any existing comments first — depending on your theme, already-published comments may still render in unexpected ways once comments are turned off for that content type.

== Frequently Asked Questions ==

= How do I install Disable Comments RB? =

Search for "Disable Comments RB" from Plugins → Add New, or upload the plugin zip manually, then activate it.

= How do I turn off comments for my whole site? =

Open the plugin's settings and choose "Disable everywhere" on the Disable tab.

= Can I disable comments for specific posts? =

Yes — without needing the plugin at all. Open the post editor, open the "Discussion" panel (enable it via Screen Options if it's hidden), and uncheck "Allow comments."

= Does Disable Comments RB work with custom post types? =

Yes. Any public post type that supports comments — including WooCommerce products — shows up in the content-type list on the Disable and Delete tabs.

= What's the difference between "hiding" and "deleting" comments? =

Hiding (on the Disable tab) only affects what's shown on the front end — nothing is removed from your database. Only the Delete tab actually removes comments, and even then you can choose Trash (recoverable) instead of permanent deletion.

= Do I need the PRO Key plugin to use AI Assistant or WooCommerce review tools? =

Yes — install and activate the separate "Disable Comments RB — PRO Key" plugin ([see the plans](https://rbplugins.com/wordpress-plugins/disable-comments-rb/#pricing)). There's no license key to enter; having it active is the unlock.

= Where can I find the documentation? =

Installation and setup guides are in the [Disable Comments RB documentation](https://rbplugins.com/docs/disable-comments-rb/).

= Does this help with spam prevention? =

Disable Comments RB blocks two specific spam channels (XML-RPC and the REST API's comment endpoint) but isn't a full anti-spam solution. Pair it with a dedicated anti-spam plugin or comment moderation for complete coverage.

== Screenshots ==

1. Disable tab — choose where comments are turned off
2. Delete tab — bulk remove or trash comments by type and status
3. AI Assistant (PRO) — keyword-triggered auto-replies with presets
4. Statistics (PRO) — live comment counts by status and content type

== Changelog ==

= 2.1.0 =
* New: PRO add-on support via the separate Disable Comments RB — PRO Key plugin, which holds the PRO features' code
* New (PRO): AI Assistant — keyword- and WooCommerce-rating-triggered auto-replies, with presets
* New (PRO): WooCommerce product review management (Delete filter, separate Statistics)
* New (PRO): Hide existing comments by date range (between / before / after a date)
* New (PRO): Role-based exceptions for staff
* New (PRO): Multisite network-wide enforcement from Network Admin
* New (PRO): Export/import settings as JSON
* New (PRO): WP-CLI commands (status / set / stats / delete)
* New (PRO): Full Statistics tab with content-type breakdown
* Fix: "Everywhere" also covers content types added after saving, as it did in 1.0.x
* Fix: Hiding existing comments also works on block themes and for comments read through the REST API
* Fix: The "not configured" notice shows only until settings are saved, and can be closed
* Fix: Sites updating from 1.0.x don't get the new dashboard widget and toolbar item until they turn them on
* Fix: Settings stored in a wrong shape no longer break saving
* Fix: Block-editor notes are not counted as comments in the statistics
* Fix (PRO): Network enforcement no longer overwrites a site's own settings; General and AI Assistant stay per-site
* Fix (PRO): Import is refused while the network enforces the settings
* Fix (PRO): Markup in a commenter's name never reaches an AI Assistant reply

= 2.0.0 =
* New: completely redesigned settings screen (Disable / Delete / General tabs)
* New: per-content-type comment disabling, independent of blocking new comments
* New: Delete tab — bulk trash/delete comments by content type and status, with a Count-first step
* New: blocks comment spam via XML-RPC and the REST API
* New: WordPress Dashboard widget with live comment stats
* New: optional shortcut button on the native Comments page
* New: optional quick-access menu in the admin toolbar
* New: choice of Settings submenu or a top-level admin menu item
* New: Site Health integration
* Security/compliance pass: escaping, nonce verification, i18n

= 1.0.28 =
* Compatibility with WordPress 7.1

= 1.0.27 =
* Compatibility with WordPress 7.0

= 1.0.26 =
* Compatibility with WordPress 6.9

= 1.0.25 =
* Compatibility with WordPress 6.8

= 1.0.24 =
* Compatibility with WordPress 6.7

= 1.0.23 =
* Compatibility with WordPress 6.5

= 1.0.22 =
* Compatibility with WordPress 6.2

= 1.0.21 =
* Compatibility with WordPress 6.1

= 1.0.20 =
* Fixed navigation issues

= 1.0.19 =
* Compatibility with WordPress 6.0

= 1.0.18 =
* Fixed Menu Issue

= 1.0.17 =
* Compatibility with WordPress 5.9

= 1.0.16 =
* Admin warning fix

= 1.0.15 =
* Update the admin interface section, menu and navigation

= 1.0.14 =
* Update the admin interface section

= 1.0.13 =
* Update interface and test in WordPress 5.8

= 1.0.12 =
* Update interface and test in WordPress 5.7

= 1.0.11 =
* Update interface and test in WordPress 5.6

= 1.0.10 =
* Update interface and test in WordPress 5.5

= 1.0.9 =
* Update interface and test in WordPress 5.3

= 1.0.8 =
* Update interface and test in WordPress 5.0

= 1.0.5 =
* Files Disable Comments plugin update.

= 1.0.4 =
* Core Disable Comments plugin update.

= 1.0.3 =
* Recommit Disable Comments plugin.

= 1.0.2 =
* Changed the structure of the Disable Comments plugin.

= 1.0.1 =
* Modification of the admin interface, added a description.

= 1.0 =
* First release of the Disable Comments plugin.

== Upgrade Notice ==

= 2.0.0 =
Major update: redesigned settings screen, per-content-type disabling, bulk delete, XML-RPC/REST blocking, and more. Review your settings after updating.
