# Publishing Arcade — WordPress foundation (0.1.0)

This is an installable development scaffold for the first implementation stage: the plugin foundation and a manual PDF Migration Manager.

## Included in this build

- WordPress plugin bootstrap and activation/upgrade handling.
- Database tables for PDF migration tracking, page references, library folders/items, private content files, reader progress, bookmarks, and audit events.
- Admin menu: **Publishing Arcade → PDF Migration**.
- Media Library scanner for PDF attachments and heuristic page-reference discovery.
- Per-PDF staging action that copies the source to a private storage directory and verifies the copy using SHA-256.
- A storage abstraction seam (`PA_Storage`) with a filter for changing the private storage root.
- REST health/status endpoint: `/wp-json/arcade/v1/status`.
- Placeholder `[publishing_arcade_reader publication="123"]` shortcode. It intentionally does not expose the source PDF.

## Not implemented yet

This is not the complete Publishing Arcade product. In particular, it does **not** yet include:

- A protected page-image/canvas PDF renderer, text layer, OCR, search, TOC, zoom, or accessibility layer.
- Reader progress/bookmark REST endpoints or the reader UI. The database tables are foundations only.
- Per-publication access rules, s2Member entitlement migration, CatFolders import, subscription billing, credit purchases/spending, Attention Points, creator compensation, ownership vesting, or Solana wallet/token integration.
- Automated replacement of existing viewer embeds or page content.
- Production-grade private object storage delivery, S3 adapter, background job queue, or CDN integration.

Do not describe this version as production-ready or use it to sell access to protected PDFs yet.

## Installation

1. Back up the WordPress database and files.
2. In WordPress Admin, open **Plugins → Add New Plugin → Upload Plugin**.
3. Upload `publishing-arcade.zip`, install, and activate.
4. Open **Publishing Arcade → PDF Migration**.
5. Click **Scan Media Library PDFs**. Review the discovered references.
6. Use **Create private copy** on one test PDF. Confirm the SHA-256 verification and inspect the private copy on the server.

## Private storage warning — important

By default the staging adapter writes under `wp-content/pa-private/`. It adds Apache `.htaccess`, IIS `web.config`, and an index file. **The `.htaccess` file is not effective on Nginx.** Before staging real member publications on an Nginx server, configure a server-level deny rule or set a private storage root outside the web document root with the `pa_private_storage_root` filter. Do not rely on an obscure URL as security.

Example filter (place in a small must-use plugin or site-specific plugin):

```php
add_filter('pa_private_storage_root', function ($path) {
    return '/srv/private/publishing-arcade'; // Must be writable by PHP and outside the public document root.
});
```

The copy operation leaves the original Media Library file intact. It does not modify the publication page, remove the old viewer, or revoke access to the original URL. This is deliberate until the protected renderer and activation/rollback workflow exist.

## Migration scanner limitations

The scanner looks for the attachment ID or canonical Media Library URL inside `post_content`. It may miss page-builder metadata, custom fields, dynamically generated URLs, shortcodes that reference an alternate URL, or references stored by third-party plugins. It may also produce false positives when an attachment ID happens to occur in unrelated content. Review every match and inspect the existing PDF viewer plugin's storage/reference format before conversion. One PDF used on multiple pages is tracked against each detected page.

## Data and uninstall policy

Deactivating the plugin does not delete data. There is intentionally no destructive uninstall routine. The original PDF files are not deleted. Migration records and private copies remain until an administrator removes them manually.

## Development sequence after this scaffold

1. Confirm private storage location and web-server deny rules.
2. Add a robust reference adapter for the site's actual PDF viewer and page builder.
3. Implement PDF processing into page assets plus a searchable/selectable text layer; never send the original PDF to the browser.
4. Implement access/entitlement checks before page asset delivery.
5. Implement reader UI, server-authoritative progress sync, bookmarks, and cross-device Continue Reading.
6. Build per-publication preview, activation, rollback, and old-URL revocation only after protected delivery is verified.
7. Add s2Member/CatFolders import and validate existing subscriber entitlements before retiring either plugin.
8. Add the credit/attention/creator economy and Solana integration as separate modules.

## Compatibility

Requires WordPress 6.2+ and PHP 7.4+. Uses `$wpdb->prefix` and WordPress `dbDelta()` for schema creation. Test on a staging copy first.
