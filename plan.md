##  Plugin Plan: GT Link Manager

A free, very fast Pretty Links alternative.

### Database Schema

Two tables. Clean and normalized.

**Table 1: `{prefix}gt_links`**

```sql
CREATE TABLE {prefix}gt_links (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    redirect_type SMALLINT(3) NOT NULL DEFAULT 301,
    rel VARCHAR(100) DEFAULT '',
    noindex TINYINT(1) NOT NULL DEFAULT 0,
    category_id BIGINT(20) UNSIGNED DEFAULT NULL,
    tags VARCHAR(255) DEFAULT '',
    notes TEXT DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY category_id (category_id),
    KEY redirect_type (redirect_type)
) {charset_collate};
```

**Table 2: `{prefix}gt_link_categories`**

```sql
CREATE TABLE {prefix}gt_link_categories (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT DEFAULT '',
    parent_id BIGINT(20) UNSIGNED DEFAULT 0,
    count BIGINT(20) UNSIGNED DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY parent_id (parent_id)
) {charset_collate};
```

**Why this structure:**

- Two tables instead of five. Categories are a foreign key on the links table (one category per link, which covers 95% of use cases). Tags are a comma-separated VARCHAR field on the link itself. Filterable, searchable, no join table needed.
- `rel` stores values like `nofollow,sponsored` as a comma-separated string. Simple to parse, easy to query with `LIKE`.
- `slug` has a UNIQUE index. That's your redirect lookup. With a direct query on a unique index, lookups resolve in under 1ms even without object caching.
- Auto-increment starts at 1 by default in MySQL/MariaDB. Your first link gets ID #1.
- `count` on categories tracks how many links are in each. Updated on link save/delete. Avoids COUNT queries.

### Redirect Handler

This is the performance-critical piece. It fires on `init` with priority 0 (earliest possible).

```
Request: /go/flyingpress
→ Parse URI, extract "flyingpress"
→ Direct DB query: SELECT url, redirect_type, rel, noindex FROM gt_links WHERE slug = 'flyingpress' LIMIT 1
→ Result cached in object cache (wp_cache_set) with group 'gt_links'
→ Send headers: Location, rel, X-Robots-Tag (if noindex)
→ Exit. No template loading. No WordPress query. Fast.
```

**Why `init` and not `template_redirect`:** For a public plugin, we want this as early as possible. `template_redirect` fires after the main WP query runs, which means WordPress already wasted time figuring out what template to load. Hooking into `init` and checking the request URI directly skips all of that.

**Cache invalidation:** When a link is updated or deleted, we bust the cache for that specific slug. When settings change (like the prefix), we flush the entire `gt_links` cache group.

### Rewrite Rules

Two approaches, and I'd support both:

1. **Rewrite rule method** (default): Register a rewrite rule that maps `go/(.+)` to a query var. Cleaner, works with most server configs.
2. **Direct parse method** (fallback): If rewrite rules aren't working (some hosts are weird), parse `$_SERVER['REQUEST_URI']` directly.

The settings page would have a "Flush Permalinks" button and a diagnostic check that verifies redirects are working.

### Admin Interface

Since we're not using CPTs, we build custom admin pages. Four pages total.

**1. All Links** (`gt-links`)

A custom `WP_List_Table` implementation. Columns:

| ID | Name | Branded URL | Destination | Type | Rel | Category | Created |
|----|------|------------|-------------|------|-----|----------|---------|

Features:
- Sortable by any column
- Filterable by category, redirect type, rel attributes
- Search by name, slug, destination URL
- Bulk actions: delete, change redirect type, change rel, assign category
- Inline "Quick Edit" for destination URL and redirect type (no full page reload)
- Row actions: Edit, Copy URL, Delete, View (opens redirect in new tab)
- Per-page count configurable via Screen Options

**2. Add/Edit Link** (`gt-links-edit`)

Clean form layout:

```
Link Name:        [FlyingPress Annual Deal          ]
Destination URL:  [https://flyingpress.com/?ref=123  ]
Slug:             [flyingpress                       ] → Preview: yourdomain.com/go/flyingpress
Redirect Type:    (•) 301 Permanent  ( ) 302 Temporary  ( ) 307 Temporary
Rel Attributes:   [✓] nofollow  [✓] sponsored  [ ] ugc
Noindex:          [✓] Prevent search engines from indexing this redirect
Category:         [Caching Plugins ▼]
Tags:             [wordpress, caching, performance   ]
Notes:            [Annual deal page, updates every Black Friday]
```

Auto-generates slug from name. Validates URL format. Shows the full branded URL as a copyable preview. "Save" and "Save & Add Another" buttons.

**3. Categories** (`gt-links-categories`)

Standard WordPress-style category management. Add/edit/delete categories. Shows link count per category. Hierarchical (parent/child support).

**4. Settings** (`gt-links-settings`)

Minimal settings page:

- **Base Prefix:** Text field, default `go`. Validates to alphanumeric + hyphens only.
- **Default Redirect Type:** Radio (301/302/307), default 301.
- **Default Rel Attributes:** Checkboxes (nofollow, sponsored, ugc). Applied to new links automatically.
- **Default Noindex:** Checkbox.
- **Flush Permalinks** button.
- **Diagnostics:** Auto-checks that a test redirect resolves correctly.

Settings stored in `wp_options` as a single serialized array: `gt_link_manager_settings`.

### Block Editor Integration

A **RichText format button** registered via `registerFormatType`. Appears in the toolbar when you select text in any RichText block (paragraph, heading, list, quote, etc.).

Workflow:

1. Select text "FlyingPress" in your paragraph
2. Click the link icon (our custom one) in the toolbar
3. A popover opens with a search field
4. Type "flying" and results appear instantly (REST API query)
5. Click "FlyingPress Annual Deal"
6. Selected text becomes a link: `<a href="/go/flyingpress" rel="nofollow sponsored">FlyingPress</a>`

The `rel` attributes come from the link's settings automatically. No manual configuration per insertion.

**REST API endpoint:** `GET /wp-json/gt-link-manager/v1/links?search=flying`

Returns: `[{id: 1, name: "FlyingPress Annual", slug: "flyingpress", url: "/go/flyingpress", rel: "nofollow,sponsored"}]`

This endpoint is permission-gated to users with `edit_posts` capability.

### Hooks and Filters (Public Release)

These let other developers extend the plugin without modifying core files.

**Filters:**
- `gt_link_manager_prefix` — Override the URL prefix programmatically
- `gt_link_manager_redirect_url` — Modify destination URL before redirect (useful for appending UTM params dynamically)
- `gt_link_manager_redirect_code` — Override redirect status code per-link
- `gt_link_manager_rel_attributes` — Modify rel attributes before they're applied
- `gt_link_manager_headers` — Add/modify HTTP headers on redirect
- `gt_link_manager_cache_ttl` — Control cache duration (default: 0, persistent until invalidated)
- `gt_link_manager_link_columns` — Add custom columns to the list table
- `gt_link_manager_capabilities` — Override capability checks

**Actions:**
- `gt_link_manager_before_redirect` — Fires before redirect (for logging, analytics, etc.)
- `gt_link_manager_after_save` — Fires after a link is saved
- `gt_link_manager_after_delete` — Fires after a link is deleted
- `gt_link_manager_activated` — Fires on plugin activation
- `gt_link_manager_settings_saved` — Fires after settings update

That `before_redirect` action is key. Anyone who wants click tracking can hook into it without us building it into core. Clean separation of concerns.

### Import/Export

**CSV Import:**
- Upload CSV file
- Column mapping UI (map CSV columns to: name, slug, URL, redirect type, rel, category, tags)
- Preview first 5 rows before import
- Duplicate slug handling: skip, overwrite, or auto-suffix
- Progress bar for large imports

**CSV Export:**
- Export all links or filtered selection
- Standard CSV with all fields
- One-click download

**LinkCentral compatibility:** Since you're coming from LinkCentral, I'd add a "Import from LinkCentral" preset that auto-maps their CSV column names. That's a nice touch for other migrators too.

### File Structure (Revised)

```
gt-link-manager/
├── gt-link-manager.php              (main plugin file, hooks, constants)
├── includes/
│   ├── class-gt-link-activator.php  (table creation, rewrite flush)
│   ├── class-gt-link-deactivator.php
│   ├── class-gt-link-db.php         (all DB operations, CRUD)
│   ├── class-gt-link-redirect.php   (redirect handler, cache)
│   ├── class-gt-link-admin.php      (admin menu, page routing)
│   ├── class-gt-link-list-table.php (WP_List_Table for links)
│   ├── class-gt-link-categories.php (category management)
│   ├── class-gt-link-settings.php   (settings page, sanitization)
│   ├── class-gt-link-rest-api.php   (REST endpoints for block editor)
│   ├── class-gt-link-import.php     (CSV import/export)
│   └── class-gt-link-uninstall.php  (cleanup on uninstall)
├── blocks/
│   └── link-inserter/
│       ├── src/
│       │   ├── index.js             (registerFormatType)
│       │   ├── link-popover.js      (search + insert UI)
│       │   └── style.css
│       └── build/                   (compiled assets)
├── assets/
│   ├── css/
│   │   └── admin.css
│   └── js/
│       └── admin.js                 (quick edit, copy URL, etc.)
├── languages/
│   └── gt-link-manager.pot
├── uninstall.php                    (runs on plugin delete)
└── readme.txt                       (WordPress.org formatted)
```

### WordPress.org Compliance Checklist

For public release, these are non-negotiable:

- All strings wrapped in `__()` or `esc_html__()` with text domain `gt-link-manager`
- All DB queries use `$wpdb->prepare()`. No raw SQL. Ever.
- All output escaped with `esc_html()`, `esc_url()`, `esc_attr()` as appropriate
- All input sanitized with `sanitize_text_field()`, `absint()`, `wp_kses()` etc.
- Nonce verification on every form submission and AJAX call
- Capability checks on every admin action (`manage_options` for settings, `edit_posts` for link management)
- No external API calls without user consent
- No tracking, no phone-home
- Proper `readme.txt` with screenshots, FAQ, changelog
- GPL v2+ license
- Uses `dbDelta()` for table creation (WordPress standard for schema management)
- Plugin Check plugin passes with zero errors

### Estimated Size

- PHP: ~2,500-3,000 lines across all classes
- JS: ~300-400 lines for block editor integration + admin
- CSS: ~150 lines for admin styling

Small enough to audit in an afternoon. Big enough to be genuinely useful.

### Build Order

Build in this order:

1. **Core:** Plugin bootstrap, DB table creation, link CRUD operations
2. **Redirects:** The handler, caching, header management
3. **Admin UI:** List table, add/edit form, categories page
4. **Settings:** Settings page, prefix configuration, defaults
5. **Block Editor:** Format button, REST API, search popover
6. **Import/Export:** CSV handling, LinkCentral preset
7. **Polish:** Diagnostics, flush tool, translation file, readme.txt

Each step is testable independently.