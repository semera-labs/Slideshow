# Slideshow 2.3.2.1 (Semera compatibility release)

Drop-in replacement for the WordPress.org plugin **Slideshow** (`slideshow-jquery-image-gallery`) that runs on
**PHP 8.0 – 8.5**. Based on upstream [Boonstra/Slideshow](https://github.com/Boonstra/Slideshow) `master` (`ae48a5f`,
changelog "2.3.2") by Stefan Boonstra (GPLv2); not made or endorsed by the original author.
Full list of changes: [readme.md](readme.md).

**Download:** `slideshow-jquery-image-gallery-2.3.2.1.zip`
SHA-256 `aa54c95b7ddedb5086e8c69e6a1927d490fc5a5f05e6e591ce2fc960fb0ec850`

## Why this release

Upstream (2.3.1 on WordPress.org and the current `master`) takes down the **whole site** on PHP 8: the widget class still
uses a PHP 4 style constructor, which PHP 8 no longer accepts. WordPress registers the widget on every request, so the
front end **and** wp-admin end in "critical error" (`ArgumentCountError: Too few arguments to function
WP_Widget::__construct()`) as soon as the plugin is active.

## Fixed

- **Fatal error on PHP 8.x** – widget constructor renamed to `__construct()`.
- PHP 8.1+ deprecation: `FILTER_SANITIZE_STRING` (duplicate-slideshow link).
- PHP 8.5 deprecation: `null` used as array offset when rendering slides.
- PHP 8 fatal errors / warnings where PHP 7 only warned:
  - saving the General Settings after deleting the last custom style (`array_key_exists()` on `null`),
  - non-array option values in `array_merge()` / `array_keys()`,
  - "Slides per view" empty, `0` or not numeric (`Modulo by zero` / `TypeError`),
  - undefined array keys for `[slideshow_deploy]` without numeric `id` and when no slideshow exists.
- WordPress 5.7+ notice on every page with a slideshow (`wp_localize_script()` called with a string).
- jQuery 3: `$(window).load()` replaced (minified files and uncompressed sources) – without jQuery Migrate the slideshow
  script threw `TypeError: e.indexOf is not a function`.

## Security

- Path traversal in the public stylesheet endpoint (`admin-ajax.php?action=slideshow_jquery_image_gallery_load_stylesheet`).
- Stored XSS: slideshow `height` / `maxWidth` in the front-end `style` attribute, setting values in admin input fields,
  attachment title/URL in the admin slide list.
- Not changed (needs a logged-in user with edit rights, listed in readme.md): `javascript:` URLs in slide links, unescaped
  widget title.

## Changed behaviour of the fork

- **No updates from WordPress.org.** `Update URI: false`, the plugin is removed from the update list and never auto-updated,
  so the unmaintained upstream release cannot overwrite this version.
- Version string `2.3.2.1` (higher than WordPress.org's 2.3.1 and a possible upstream 2.3.2; also busts cached JS/CSS).
- Includes the upstream fixes that exist on `master` but were never released on WordPress.org: no HTML5 validation errors
  with "Stylesheet location: Footer", "Head" no longer leaves a stylesheet in the footer, element tags can be set on
  text slides.

## Unchanged (drop-in)

Folder name, main file `slideshow.php`, shortcode `[slideshow_deploy id="…"]`, post type `slideshow`, post meta `slides` /
`settings` / `styleSettings`, all `slideshow-jquery-image-gallery-*` options, widget `slideshowwidget`, capabilities,
CSS/JS handles, author credits and license. No database change on update (verified with a before/after snapshot).

## Upgrade

1. Back up files and database.
2. Deactivate the old plugin (it must not be active on PHP 8), replace the folder
   `wp-content/plugins/slideshow-jquery-image-gallery` by the one from the zip (FTP, *Plugins → Upload plugin → Replace
   current with uploaded*, or `wp plugin install <zip> --force`).
3. Activate the plugin, check a page with a slideshow, edit and save a slideshow, check the General Settings.

Rollback: rename the plugin folder via FTP – WordPress deactivates it and the site runs again.

The zip contains only runtime files (minified JS, no `js/*.js` sources, no LESS) plus `readme.md`.

## Tested

WordPress 7.1.5 (SQLite, Twenty Twenty-Five), PHP 8.3.36, 8.4.27, 8.5.12, `WP_DEBUG` on: installation from the zip over a
site with existing slideshows (database unchanged), front end (shortcode, widget, image/text/video slides, with and without
jQuery Migrate), edit screen with media picker, save, General Settings, edge cases; no `debug.log` entries from the plugin
on any of the three PHP versions.

**Not tested:** PHP 7.x, other themes, page builders and plugins, multisite, the widgets block editor UI.

## Known limitations

- Unmaintained upstream code base; only the issues listed above were fixed.
- If the option `slideshow-jquery-image-gallery-plugin-version` is missing while slideshows exist, upstream's installer runs
  its old data migrations – do not delete the plugin's options.
