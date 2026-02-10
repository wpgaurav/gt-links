=== GT Link Manager ===
Contributors: gauravtiwari
Tags: links, redirects, affiliate links, pretty links, marketing
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A fast, lightweight Pretty Links alternative with custom tables, early redirects, CSV import/export, and block editor integration.

== Description ==

GT Link Manager helps you create branded short links on your WordPress site without CPT overhead.

Key features:

- Direct table lookup for redirect slugs
- Early redirect execution on `init`
- 301/302/307 redirect support
- `rel` controls: `nofollow`, `sponsored`, `ugc`
- Noindex header support (`X-Robots-Tag`)
- Category and tag organization
- Full admin list table with search, filters, sorting, bulk actions
- Quick Edit without page reload
- CSV import/export with LinkCentral-compatible preset
- Block editor toolbar button to search links and insert them quickly
- Extensible actions and filters for developers

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate it from **Plugins**.
3. Go to **GT Links** in wp-admin.
4. Create your first link and test it using your prefix (default: `/go/slug`).

== Frequently Asked Questions ==

= Is this a Pretty Links replacement? =

Yes. The focus is speed and simplicity for branded redirects.

= Does it track clicks? =

Not in core yet. Use the `gt_link_manager_before_redirect` action to hook your own tracking.

= Can I import from LinkCentral? =

Yes. Use **GT Links -> Import / Export**, choose the LinkCentral preset, preview, map columns, and import.

= How are redirects resolved? =

The plugin checks request URI early and loads the matching slug from a unique indexed column in a custom table.

== Screenshots ==

1. All Links admin list with filters and bulk actions
2. Add/Edit Link form with branded URL preview
3. Categories manager
4. Import/Export with preview and mapping
5. Settings with diagnostics

== Changelog ==

= 1.0.0 =
* Added license activation and auto-update support via FluentCart Pro
* Added License admin page with activation, deactivation, and update checks
* Added weekly background license verification

= 0.1.0 =
* Initial release
* Custom DB schema with links and categories
* Fast redirect handler with cache invalidation
* Admin CRUD for links/categories/settings
* Block editor link inserter format button
* REST API endpoint for editor search
* CSV import/export with preview, mapping, and duplicate handling
* LinkCentral-compatible CSV preset

== Upgrade Notice ==

= 0.1.0 =
Initial public release.
