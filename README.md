# GT Link Manager

A fast, lightweight Pretty Links alternative for WordPress.

GT Link Manager gives you branded short URLs, quick redirect management, category organization, CSV import/export, and block editor integration without plugin bloat.

## Highlights

- Fast redirects with custom indexed tables
- Redirect handling on `init` for low overhead
- Redirect types: `301`, `302`, `307`
- Rel attributes: `nofollow`, `sponsored`, `ugc`
- Categories + tags + notes
- Bulk actions and Quick Edit in admin list
- CSV import/export (including LinkCentral-style data)
- REST API for links/categories
- Gutenberg toolbar button to insert saved links

## Requirements

- WordPress `6.4+`
- PHP `8.2+`

## Installation (Local)

1. Copy this plugin folder to:
   `wp-content/plugins/gt-link-manager`
2. Activate **GT Link Manager** from WP Admin -> Plugins.
3. Go to **GT Links** in admin.

## Main Admin Pages

- `GT Links -> All Links`
- `GT Links -> Add New`
- `GT Links -> Categories`
- `GT Links -> Settings`
- `GT Links -> Import / Export`

## CSV Import Format (Generic)

```csv
name,slug,url,redirect_type,rel,noindex,category,tags,notes
FlyingPress,flyingpress,https://example.com,301,"nofollow,sponsored",1,Caching,"wp,speed","Campaign link"
```

Sample files are in:

- `samples/gt-links-sample.csv`
- `samples/linkcentral-sample.csv`

## REST API

Base namespace:

`/wp-json/gt-link-manager/v1`

### Links

- `GET /links`
- `POST /links`
- `GET /links/{id}`
- `PATCH /links/{id}`
- `DELETE /links/{id}`
- `POST /links/bulk-category` (`mode: move|copy`)

### Categories

- `GET /categories`
- `POST /categories`
- `PATCH /categories/{id}`
- `DELETE /categories/{id}`

Capability check defaults to `edit_posts` (filterable).

## Build Assets

This repo currently ships static plugin assets directly (no JS build pipeline required for the included files).

## File Structure

```text
gt-link-manager.php
includes/
assets/
blocks/
languages/
samples/
```

## Notes

- WordPress.org packaging details remain in `readme.txt`.
- `README.md` is for repository/developer usage.
