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

## Development Commands

Run from plugin root:

```bash
# PHP syntax check (all plugin PHP files)
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

```bash
# Quick git status
git status --short
```

```bash
# Regenerate PNGs from SVG files inside graphics/
cd graphics
for f in *.svg; do rsvg-convert -f png -w 2560 -o "${f%.svg}.png" "$f"; done
```

```bash
# Create raster icon sizes up to 512px
cd graphics
mkdir -p raster
for s in 32 64 96 128 192 256 384 512; do
  rsvg-convert -f png -w "$s" -h "$s" -o "raster/gt-link-manager-icon-${s}.png" gt-link-manager-icon.svg
done
```

```bash
# Generate 2400x1260 banner from hero SVG
cd graphics
rsvg-convert -f png -w 2400 -h 1260 -o raster/gt-link-manager-banner-2400x1260.png gt-link-manager-hero.svg
```

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
