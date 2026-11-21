# Slideshow (compatibility fork)

- **Contributors:** stefanboonstra
- **Donate link:** http://stefanboonstra.com/donate-to-slideshow/
- **Tags:** responsive, slideshow, slider, slide show, images, image, photo, video, text, gallery, galleries, jquery, javascript
- **Requires at least:** 3.5
- **Tested up to:** 4.3
- **Stable tag:** 2.3.1
- **License:** GPLv2

Integrate a fancy slideshow in just five steps. - Rainbows. Rainbows everywhere.

> **This is a compatibility fork by Semera of Slideshow by Stefan Boonstra** ([Boonstra/Slideshow](https://github.com/Boonstra/Slideshow),
> branch `master`). The section "Compatibility patch" below describes everything that differs from the original. The original
> description, FAQ and changelog follow after it, unchanged (only converted from `readme.txt` to Markdown).

## Compatibility patch (Semera) – notes on all changes

Patched version: **2.3.2.1** (PHP 8.0 – 8.5, current WordPress/jQuery). It is a private patch, *not* made or endorsed by
the original author. Author credits and the GPLv2 license are unchanged. Plugin folder name (`slideshow-jquery-image-gallery`),
main file, shortcode `[slideshow_deploy]`, post type `slideshow`, meta keys, option names, widget id and CSS/JS handles are
**unchanged**, so existing slideshows, settings, shortcodes and widgets keep working when the plugin files are replaced.

### Base

Upstream `master` at commit `ae48a5f` (22 Nov 2015). Its code header still says 2.3.1, but the changelog lists **2.3.2**,
which was never released on WordPress.org (the plugin directory ships 2.3.1). Compared to 2.3.1 from WordPress.org this
adds three upstream fixes: "Stylesheet location" set to "Footer" no longer causes HTML5 validation errors, "Head" no longer
leaves a stylesheet in the footer (`SlideshowPluginSlideshowStylesheet::filterStyleLoaderTag()` plus a changed
`enqueueStylesheet()`), and element tags can be set on text slides (`views/SlideshowPluginSlideshowSlide/backend_text.php`).
The new upstream code was reviewed for PHP 8 problems: none found. Besides the plugin files the repository contains the
uncompressed JavaScript (`js/*.js`, `js/backend`, `js/frontend`) and LESS sources, which are not part of the release zip.
Data format (post meta `slides`, `settings`, `styleSettings`, options) is identical to 2.3.1.

**Caveat (upstream behaviour):** the installer runs its data migrations when the option
`slideshow-jquery-image-gallery-plugin-version` does not exist. If that option (and the `…-updated-from-…` options) was
deleted while slideshows exist, the old migrations run on current data. Do not delete these options.

### Why the website crashed (root cause)

`classes/SlideshowPluginWidget.php` declared the widget constructor in the **PHP 4 style**:

```php
class SlideshowPluginWidget extends WP_Widget {
    function SlideshowPluginWidget() { ... parent::__construct(...); }
}
```

PHP 8.0 **removed** PHP-4-style constructors: a method named like the class is just an ordinary method. WordPress
instantiates the widget with `new SlideshowPluginWidget()` on the `widgets_init` hook (runs on **every** request,
front end, admin, cron, WP-CLI) and now `WP_Widget::__construct($id_base, $name)` is called without arguments:

```
PHP Fatal error: Uncaught ArgumentCountError: Too few arguments to function WP_Widget::__construct(), 0 passed
in wp-includes/class-wp-widget-factory.php on line 77 and at least 2 expected in wp-includes/class-wp-widget.php:163
#2 .../slideshow-jquery-image-gallery/classes/SlideshowPluginWidget.php(153): register_widget('SlideshowPlugin...')
```

Result: the whole site (front end **and** wp-admin) shows "critical error" while the plugin is active. This is not
specific to 8.3.36 – it happens on **every PHP 8.x** (the PHP 7.x series still accepted the old constructor). Because it
is a fatal error on every request, the user could not even reach wp-admin to deactivate the plugin.

### Changes (all of them)

Line numbers refer to the patched files. "Fix" = needed for PHP 8.x, "Hardening" = avoids a fatal error / warning on
PHP 8 that was only a warning on PHP 7, "WP" = WordPress/jQuery behaviour change independent of PHP, "Sec" = security.

#### Compatibility

| # | File:line | Change | Why |
|---|---|---|---|
| 1 | `classes/SlideshowPluginWidget.php:18` | `function SlideshowPluginWidget()` → `function __construct()` | **Fix – the crash.** PHP 8 no longer treats the class-named method as constructor (see above). Works on PHP 5.x–8.5. |
| 2 | `classes/SlideshowPluginPostType.php:288-289` | `FILTER_SANITIZE_STRING` → `FILTER_SANITIZE_SPECIAL_CHARS` (nonce and `post_type` of the "Duplicate slideshow" link) | **Fix.** `FILTER_SANITIZE_STRING` is deprecated since PHP 8.1 (E_DEPRECATED on every duplicate action). The values are alphanumeric slugs/nonces, result is identical for valid input. |
| 3 | `classes/SlideshowPluginSlideInserter.php:42` | `isset($elementTags[$id])` → `$id !== null && isset($elementTags[$id])` | **Fix (PHP 8.5).** "Using null as an array offset is deprecated" – triggered on every front-end render of an image/text slide (`getElementTag()` is called without id). |
| 4 | `classes/SlideshowPluginShortcode.php:64-65` | `isset($settings['avoidFilter']) && …` | **Hardening.** `[slideshow_deploy]` without numeric `id` (random slideshow) or with a slug `id` got an empty settings array → "Undefined array key" warning (notice on PHP 7). Behaviour unchanged (falls through to direct output as before). |
| 5 | `classes/SlideshowPlugin.php:77` | `$post = $post[0]` → `isset($post[0]) ? $post[0] : null` | **Hardening.** "Undefined array key 0" warning when no slideshow exists at all (e.g. widget/shortcode placed, all slideshows deleted). Result unchanged (the existing "No slideshows available" comment is returned). |
| 6 | `classes/SlideshowPluginGeneralSettings.php:349-353` | In `saveCustomStyles()`: non-array input (WordPress passes `null` when no custom style is posted, e.g. after the last custom style was deleted) is treated as an empty array | **Fix.** `array_key_exists($key, null)` throws a **TypeError** on PHP 8 (was a warning on PHP 7) → saving the General Settings page would crash. Behaviour on PHP 7 was identical (all old custom styles deleted). |
| 7 | `classes/SlideshowPluginGeneralSettings.php:237-240` (`getStylesheets()`), `classes/SlideshowPluginSlideshowStylesheet.php:193-194` (`getStylesheet()`), `classes/SlideshowPluginSlideshowSettingsHandler.php:441-446` and `535-540` (`getDefaultSettings()`, `getDefaultStyleSettings()`) | Option values read with `get_option()` are only passed to `array_merge()` / `array_keys()` / `foreach` if they are arrays | **Hardening.** If one of these options holds a scalar (e.g. an empty string after a failed save), PHP 8 throws a TypeError in `array_merge()` / `array_keys()` instead of a warning, which would take down the front end. |
| 8 | `views/SlideshowPlugin/slideshow.php:17-18, 31` | `$data->settings['slidesPerView']` is converted with `max(1, (int) …)` before it is used in `%` | **Hardening.** "Slides per view" is a free text field; empty, `0` or non-numeric values made `%` throw `DivisionByZeroError` / `TypeError` (a fatal error on the front end on PHP 8, also `Modulo by zero` on 7). Normal values (`1`, `2`, …) behave exactly as before; fractions are truncated (also PHP 8.1 deprecates implicit float→int conversion in `%`). |
| 9 | `slideshow.php:141, 151-169` (new method `SlideshowPluginMain::localizeAdminURL()`), `classes/SlideshowPlugin.php:175` | The admin URL for the front-end script is passed with `wp_add_inline_script()` (`var slideshow_jquery_image_gallery_script_adminURL = "…";`, position `before`) instead of `wp_localize_script()` with a *string* | **WP.** Since WordPress 5.7 passing a non-array to `wp_localize_script()` logs `_doing_it_wrong` ("The $l10n parameter must be an array") on **every** page that shows a slideshow. Same JavaScript variable name and value; falls back to the old call on WordPress < 4.5. |
| 10 | `js/min/all.frontend.min.js` (2×), `js/min/all.backend.min.js` (1×) and the uncompressed sources `js/all.frontend.js`, `js/all.backend.js`, `js/frontend/slideshow/slideshow.constructor.js` | `$(window).load(fn)` → `$(window).on("load", fn)` | **WP/jQuery.** `.load(handler)` was removed in jQuery 3.0; it only keeps working while *jQuery Migrate* is loaded. Without Migrate (many performance plugins/themes remove it) the slideshow script throws `TypeError: e.indexOf is not a function`. Minified file edited in place (three one-for-one substitutions, no other change). |

#### Drop-in safety (no unwanted updates)

| # | File:line | Change | Why |
|---|---|---|---|
| 11 | `slideshow.php:6, 31` | Version `2.3.1` (header and `$version` of upstream master; its changelog already says 2.3.2) → `2.3.2.1` (header **and** `SlideshowPluginMain::$version`) | Browsers/CDNs cache the old JS/CSS under `?ver=2.3.1`; the new version string busts that cache. `2.3.2.1` is *greater* than WordPress.org's 2.3.1 and than a later upstream 2.3.2, so WordPress never offers those as an update. The installer's version check treats `2.3.1` (stored in the database) vs `2.3.2.1` as "no migration needed" (verified: no database change on activation). |
| 12 | `slideshow.php:12` | Header `Update URI: false` | WordPress ≥ 5.8 does not ask wordpress.org for updates of plugins with a foreign/`false` Update URI. |
| 13 | `slideshow.php:75-76, 85-121` (`hideFromUpdates()`, `disableAutoUpdate()`) | Filter `site_transient_update_plugins` removes this plugin from `response`/`no_update`; filter `auto_update_plugin` returns `false` for this plugin | Belt and braces for WordPress < 5.8 and for anything that rewrites the update transient: the (unmaintained, unpatched) upstream release is never offered, never auto-installed, not even if a future upstream 2.3.2 appears. Verified with a fake transient offering "9.9.9": the plugin is removed from the list, other plugins stay. |
| 14 | `slideshow.php:14-17` | Header comment: patch note | Requested attribution note. Plugin name/author/URI/license lines unchanged. |

#### Security review (clear-cut and low risk only)

Fixed:

| # | File:line | Finding / fix |
|---|---|---|
| S1 | `classes/SlideshowPluginSlideshowStylesheet.php:204` | **Path traversal** in the public (`nopriv`) stylesheet loader `admin-ajax.php?action=slideshow_jquery_image_gallery_load_stylesheet&style=…`: the `style` value went unchecked into `include '…/style/SlideshowPlugin/<style>.css'`, so `style=../../x` could include any existing `*.css` file on the server (it is an `include`, so PHP code inside such a file would run). Fix: `basename($styleName)`. Normal style names (`style-light`, `style-dark`) unchanged. |
| S2 | `views/SlideshowPlugin/slideshow.php:3` | **Stored XSS** (front end): the settings `height` and `maxWidth` (editable by everybody with the "edit slideshows" capability = authors) were echoed unescaped into the `style="…"` attribute. Fix: `esc_attr()`. |
| S3 | `classes/SlideshowPluginSlideshowSettingsHandler.php:591, 603` | **Stored XSS** (admin): text/textarea setting values were echoed unescaped into `value="…"` / `<textarea>` on the slideshow and settings screens. Fix: `esc_attr()` / `esc_textarea()`. |
| S4 | `views/SlideshowPluginSlideshowSlide/backend_attachment.php:118-119` | Attachment title and image URL echoed unescaped in the admin slide list. Fix: `esc_attr()` / `esc_url()`. |

Checked and OK: saving a slideshow (`SlideshowPluginSlideshowSettingsHandler::save`) verifies nonce, post type,
capability and ignores autosaves; the "Duplicate" action verifies a nonce and the capability; the General Settings
page needs `manage_options` and its save callbacks verify the nonce; the shortcode/widget output of slide texts passes
through the plugin's own whitelist filter. No SQL is built from request data (everything goes through
`WP_Query` / `get_post_meta`).

**Found but deliberately NOT changed** (not clear-cut / would change behaviour; low impact because they require a
logged-in user with slideshow-edit rights, i.e. author or higher):

* Slide link URLs (`url`) are escaped with `htmlspecialchars` but not filtered for `javascript:` schemes.
* The widget title is printed without escaping (settable only by users who can edit widgets).
* Custom-style titles and option labels in the admin selects are not escaped (admin only).
* Slideshow settings are stored without server-side validation (the values are escaped on output now, S2/S3).
* The public stylesheet endpoint is intentionally reachable without login (that is how the plugin serves its CSS).

### Not changed (known, harmless, left as is)

* Upstream bugs unrelated to PHP/WordPress versions (for example `pauseOnHover` default uses the `loop` default;
  `backend_text.php` sets `$titleElementTag` instead of `…TagID`; `__CLASS__` in a view resolves to
  `SlideshowPluginMain`).
* `SlideshowPluginInstaller` (migration of data from plugin versions < 2.2) – runs only if the stored plugin version is
  missing; it has no PHP 8 incompatibility that shows on normal data (verified on a fresh site and on existing data);
  with pathologically broken legacy data (`settings` meta being a string instead of an array) it can still log
  "foreach() argument must be of type array|object" warnings.
* The jQuery Migrate console message ("JQMIGRATE: Migrate is installed") that WordPress itself produces.
* Upstream readme/translations, images, CSS: untouched.

### Syntax level

Everything added uses PHP 5.x syntax only (no scalar type hints, no `??`, no short `[]` arrays added, no closures),
so the same files still run on the PHP 7.x installations the original ran on. Not tested on PHP 7.x (no such PHP is
available in the local test environment, see below).

## How this was tested

Local WordPress Studio sites (WordPress 7.1.5, SQLite, theme Twenty Twenty-Five, `WP_DEBUG` + log + display on), PHP **8.3.36**,
**8.4.27** and **8.5.12**:

* `php -l` on every PHP file with 8.3, 8.4 and 8.5: no errors.
* Unpatched upstream code: activating works, the first request fatals with
  `ArgumentCountError: Too few arguments to function WP_Widget::__construct()` (PHP 8.3).
* Patched version, installed from the release zip over a site with existing data: front end with shortcodes (image, text and video
  slides) and widget, also without jQuery Migrate; slideshow edit screen with the media picker (add an image slide, save);
  General Settings (save, delete all custom styles); duplicate; a set of edge cases (shortcode without id, slug id,
  `slidesPerView` empty/0/text, scalar option values, no slideshow available, escaping, update filter); database snapshot
  before/after identical. The PHP `debug.log` has **no** entries from this plugin on 8.3, 8.4 and 8.5.
* **Not tested:** PHP 7.x, other themes, page builders, other plugins, multisite, the live site's data, the widgets block
  editor UI (widget form and output were tested through PHP).

## Description

Slideshow provides an easy way to integrate a slideshow for any WordPress installation.

Any image can be loaded into the slideshow by picking it from the WordPress media page, even images you've already
uploaded can be inserted into your slideshow right away!

Fancy doing something crazy? You can create and use as many slideshows as you'd like, with
different images, settings and styles for each one of them.

### Features

 - Create as many slideshows with as many slides as you like
 - Image slides
 - Text slides
 - YouTube Video slides
 - Responsive
 - Place it anywhere on your website
 - Run multiple slideshows on the same page
 - Change animations and handling
 - Customize to taste
 - Show that visitor who's boss

### Languages

 - Bulgarian	  (87%  - bg_BG - Translated by [Ilko Ivanov](http://software.avalonbg.com/en/index.php))
 - Chinese        (65%  - zh_CN - Translated by [Kevin Tell](http://www.ivygg.com/) and [Leo Newbiesup](http://smallseotips.com/))
 - Czech          (81%  - cs_CZ - Translated by Edhel)
 - Dutch          (100% - nl_NL - Translated by [Stefan Boonstra](http://stefanboonstra.com/) (That's me!))
 - English        (100%)
 - Finnish        (83%  - fi    - Translated by A. Nonymous)
 - French         (91%  - fr_FR - Translated by [Romain Sandri](http://www.onidesign.fr/))
 - German         (99%  - de_DE - Translated by [Markus Amann](http://www.dema-itsupport.com/) and others)
 - Hebrew         (53%  - he_IL - Translated by Eli Segev)
 - Italian        (83%  - it_IT - Translated by [Tecnikgeek](http://tecnikgeek.com/))
 - Japanese       (82%  - ja    - Translated by [Michihide Hotta](http://net-newbie.com/))
 - Norwegian      (99%  - nb_NO - Translated by A. Nonymous)
 - Persian        (100% - fa_IR - Translated by [W3Design](http://w3design.ir/))
 - Polish         (83%  - pl_PL - Translated by Wicher Wiater)
 - Portuguese     (92%  - pt_BR - Translated by [Piero Luiz](http://www.newer7.com.br/) and others)
 - Portuguese     (83%  - pt_PT - Translated by [Filipe Catraia](http://www.filipecatraia.com/))
 - Russian        (100% - ru_RU - Translated by [Coupofy](http://www.coupofy.com/) and Dmitry Fatakov)
 - Serbo-Croatian (91%  - sr_RS - Translated by [Webhosting Hub](http://www.webhostinghub.com/))
 - Spanish        (51%  - es_ES - Translated by [Violeta Rosales](https://twitter.com/violetisha))
 - Swedish        (91%  - sv_SE - Translated by [Åke Isacsson](http://www.nojdkund.se/) and Wilhelm Svenselius)
 - Turkish        (83%  - tr_TR - Translated by [İlker Akdoğan](http://www.kelkirpi.net/))
 - Ukrainian      (100% - uk_UK - Translated by [Coupofy](http://www.coupofy.com/))

### Project board

Check upcoming features, bug-fixes and development progress that are currently on the Slideshow project board at:
[Slideshow project board - Trello.com](https://trello.com/board/slideshow-plugin-wordpress/506311260cc04591010463c2)

### Need the (uncompressed) source code?

Find the Slideshow project's source code in my [GitHub](https://github.com/Boonstra/Slideshow) repository. The
uncompressed files can be compiled using [Prepros](http://alphapixels.com/prepros/), or [CodeKit](http://incident57.com/codekit/).


## Installation

1. Install Slideshow either via the WordPress.org plugin directory, or by uploading the files to your server.

2. After activating Slideshow, click on 'Slideshows' and create a new slideshow.

3. Click on 'Insert Image Slide' to insert an image slide, a popup will appear where you can search for the desired
image. Insert the image by clicking 'Insert'. The same goes for text and video slides, don't forget to save!

4. Go to a post or a page and click the 'Insert Slideshow' button above the editor. A popup appears where you can select
your newly created slideshow to insert. You can also use the shortcode or code snippet visible in your slideshow admin
panel to deploy your slideshow anywhere on your website. Use the widget to show any of your slideshows in the sidebar
of your website.

5. Feel like a sir.


## Frequently Asked Questions

### How do I add image slides?

Click the 'Image slide' button in the 'Slides List' of the slideshow. A screen will pop up where you'll be able to
search though all images that have already been uploaded to your WordPress website. If you want to add new images to the
slideshow, or you do not have any images yet, you'll have to upload them to the WordPress media page first.

### How do I change a slideshow's settings?

Just like the posts and pages you're already familiar with, slideshows can be edited. Go to the 'Slideshows' tab in your
WordPress admin, and you'll see a list of slideshows. If you have not created a slideshow yet, you can do so by clicking
'Add new' on that same page. If there are slideshows in the list, click on the title of the slideshow you want to change
the settings of. On the slideshow's edit page you'll be able to find a box titled 'Slideshow Settings', in this box you
can change the slideshow's settings.

If you're creating multiple slideshows that should have the same settings, but their settings need to be different from
the default settings, you can change the default settings by going to the 'General Settings' page and clicking on the
'Default Slideshow Settings' tab. Newly created slideshows will start off with the settings you set there.

### How do I customize the slideshow's style?

On your WordPress admin page, go to the 'Slideshows' menu item and click on 'General Settings', then go to the
'Custom styles' tab. Here you'll see a list of default stylesheets, such as 'Light' and 'Dark', and a list of custom
stylesheets; The ones you created.

Choose a default stylesheet you'd like to customize and click 'Customize' to open the 'Custom style editor'. When
you're done editing click 'Save Changes' and go to the slideshow you'd like to style with the newly created stylesheet.
In the 'Slideshow Style' box you can now find and select your custom stylesheet. You can set a stylesheet for multiple
slideshows.

If you've already created a custom stylesheet, you can edit it by clicking 'Edit'. You can also delete it by clicking
'Delete'. Be careful with this though, a deleted stylesheet cannot be retrieved and cannot be used by any slideshow
anymore.

### Some users can add, edit or delete slideshows, although I do not want them to. Can I prevent this from happening?

Yes you can. On your WordPress admin page, go to the 'Slideshows' menu item and click on 'General Settings', then go to
the 'User Capabilities' tab (If you're not already there). The privileges that allow user groups to perform certain
actions are listed here. To allow, for instance, a contributor to add a slideshow, click the box in front of 'Contributor'
to grant him the right to add slideshows.

Note that when you grant someone the right to add or delete a slideshow, you'll also automatically grant him or her the
right to edit slideshows, as this right is required to add or delete slideshows. The same is true for the reversed
situation.

### The slideshow does not show up

- The slideshow is mostly called after the `</head>` tag, which means the scripts and stylesheet need to load in the footer
of the website. A theme that has no `<?php wp_footer(); ?>` call in it's footer will not be able to load the slideshow's
scripts.

- Often when the slideshow isn't showing, there's a javascript error somewhere on the page and this error has caused
javascript to break. For the slideshow to work again, this error needs to be fixed. Check if any errors were thrown by
opening Google Chrome or Firefox (with Firebug installed) and press the 'F12' key. Errors show in the console tab.

### Why does Internet Explorer show a big blank space above the slideshow?

Internet Explorer is a very strict browser, so when a big blank space above your slideshow is showing your page may
contain some invalid HTML. Most times invalid HTML is caused by placing the slideshow's shortcode or PHP snippet into
an anchor tag (`<a></a>`) or paragraph tag (`<p></p>`), while you can only place a slideshow within a 'div' element
(`<div></div>`).


## Screenshots

1. Here's what a default slideshow can look like. Sit back, grab a beer, enjoy.

2. Create a new slideshow. Slides and settings specific to this slideshow can be set here.

3. If you haven't uploaded any images yet, you can do so on the WordPress media page.

4. Click the 'Image Slide' button in the Slides List to search and pick images from the WordPress media page.
Click 'Insert' to insert the image as slide.

5. The images you selected are directly visible in your Slides List, don't forget to save!

6. When you understand the basics of creating slideshows, you may want to go a little more in depth and have a look at
the General Settings page. As seen in the image above, privileges can be granted to user roles to give users the ability
to add, edit or delete slideshows.

7. Default slideshow settings can be edited here. Slideshows that are newly created, will start out with these options.

8. Custom styles can be added and customized here. Custom styles can be used to style one or more slideshows to your own
personal taste.


## Changelog

### 2.3.2
*   Fixed: Setting the "Stylesheet location" setting to "Footer" no longer causes HTML5 validations errors.
*   Fixed: Setting the "Stylesheet location" setting to "Head" did not prevent the stylesheet from showing up in the footer.
*   Fixed: Element tags could not be set on text slides.

### 2.3.1
*   Fixed: Text and video slides could not be inserted.

### 2.3.0
*   Refactored the slideshow plugin's backend codebase to prepare for a larger backend rewrite.
*   Removed backwards compatibility on inserting images with pre-3.5 versions of WordPress.
*   Slideshows now work on infinite scroll and single page websites through the "Enable lazy loading" option.
*   Fixed: YouTube thumbnail image disappeared after clicking the slideshow's navigation buttons.

### 2.2.25
*   Changed localization domain to match the plugin's slug in order to support language packs.

### 2.2.24
*   Compatible with WordPress 4.3.
*   Widget constructor no longer uses deprecated PHP 4 style constructor.
*	Fixed: Mismatch between session ID and settings ID.
*	Fixed: Unescaped data in slideshow editor.
*	Fixed: Resizing issues.
*   Added Ukrainian translation and updated Russian translation, thanks to Coupofy.

### 2.2.23
*   Fixed: Despite default stylesheets now loading through static file again, admin-ajax.php was still being called.
*   Fixed: Invalid HTML in the shortcode inserter. Thanks to [Iazel](https://wordpress.org/support/profile/iazel).
*	Fixed: HTML5 validation issue when loading stylesheets in the head of the document.
*   Fixed: Slideshows now load their settings by slideshow ID, instead of the variable session ID.
*   Updated Spanish translation, thanks to Manuel Ballesta Ruiz.

### 2.2.22
*   Compatible with WordPress 4.2.
*	Default stylesheets are no longer dynamically loaded through admin-ajax.php.
*	Fixed: Security vulnerability in the SlideshowPluginSlideshowStylesheet class.
*	Fixed: An issue with admin-ajax.php when the frontend uses HTTP, while the backend uses HTTPS. Thanks to [Chris Scott](https://github.com/chrisscott)
*	Fixed: A bug that caused the previous slide button and the navigation bullets to malfunction on "Cross Fade" setting.
*   Fixed: Slideshow container calculation now uses the JavaScript round function instead of the floor function.
*	Fixed: Separated assignment of $sessionID and increment of SlideshowPlugin::$sessionCounter.
*   Added Norwegian translation by A. Nonymous.
*   Updated Persian translation by W3Design.
*   Updated German translation by A. Nonymous.
*	Updated Brazilian Portuguese by A. Nonymous.

### 2.2.21
*	Compatible with WordPress 3.9.
*	Added "Cross Fade" option to the animation settings.
*	Updated the API to support retrieving the direction of the animation on the animation event.
*	Fixed: Slideshow moving to next slide on keyboard navigation. By [Ben Wetzel](https://github.com/bensashi)
*	Fixed: HTML5 validation issue.
*	Fixed: Permission check that checked for "edit_post" instead of "edit_slideshow".

### 2.2.20
*	Compatible with WordPress 3.8.
*	Accessibility improvements by [Ben Wetzel](https://github.com/bensashi)
*	Title and description element types can now be set from backend and anchor tags are omitted when no URL is set.
*	Improved functional stylesheet.
*	Post published/updated message doesn't show a link to a non-existant page anymore.
*	New tags are available for custom stylesheets: %site-url%, %stylesheet-url% and %template-url%
*	Fixed: When the slideshow would not have any height, slides would not hide outside of the slideshow.
*	Added Serbo-Croatian translation by Webhosting Hub.
*	Updated Persian translation by W3Design.
*	Updated Chinese translation by Leo Newbiesup.
*	Updated Swedish translation by Wilhelm Svenselius.

### 2.2.19
*	Slideshows can now detect when their stylesheet isn't loaded and load their own when this is the case.
*   The functional stylesheet should now provide a more stable base for all slideshows.
*	Fixed: Uploading images through the WordPress 3.5 uploader on a slideshow's edit page would return an error.
*	Fixed: Images with a 'max-width: 100%;' style rule would size wrongly on responsive websites.
*	Fixed: The randomization method returned error messages.

### 2.2.18
*	Added option to select whether or not search engines should follow a slide's link.
*   Improved filter avoiding mechanism.
*   Disappearing settings now have a more apparent animation.
*	Fixed: First slide of a slideshow wouldn't show when the slideshow's content had no size on page load.
*   Added Brazilian Portuguese translation by Piero Luiz.
*   Updated French translation by Romain Sandri.

### 2.2.17
*   Fixed: Images disappeared when their width was larger than the slide's width and image behaviour was set to 'Natural and centered'

### 2.2.16
*   Inserting images can now be done using the WordPress 3.5 uploader.
*   Images can now fill up an entire slide without losing their dimensions, using the 'Zoom to fit' option.
*   Fixed: Descriptions came up and stayed up on fly-over.
*   Fixed: Image calculations failed in a few because of a 'jQuery.outerWidth()' bug.
*   Fixed: Control panel wasn't correctly initialized when slideshow was set to automatically slide to the next slide.

### 2.2.15
*   The image dimension calculation algorithm now uses a more stable and more efficient way to determine the size of images.
*   Added slideshow API.
*   Fixed: WP Color Picker (Iris) adds a hashtag to color codes. As slideshow does this as well, text slides didn't display color properly.

### 2.2.14
*   Fixed: Slideshow didn't show because of a JavaScript error when calculating an unstretched image's dimension.

### 2.2.13
*   The SlideshowPluginAjax.php has been re-added to SVN.

### 2.2.12
*   Compatible with WordPress 3.6.
*   Complete javascript revision to be better manageable with the help of the Prepros tool.
*   Related videos can now be enabled or disabled on YouTube videos.
*   YouTube API now is only loaded when a video slide needs to be shown.
*   Re-arranged the 'General Settings' page to have a 'General Settings' tab that contains overall system settings.
*   Added option to choose the loading location of the stylesheets, as IE8 and older don't always allow in-body styling.
*   Implemented a new algorithm for randomizing slides.
*   Slideshow can now wait for a slide to load before showing it.
*   Fixed: The 'Enable responsiveness' setting disappeared when disabling the 'Show title and description' option.
*   Added Turkish translation by İlker Akdoğan.
*   Added Polish translation by Wicher Wiater.
*   Added Finnish translation by an anonymous supporter.
*   Updated French translation by Romain Sandri.
*   Added Portuguese translation by Filipe Catraia.
*   Added Persian translation by W3Design.
*   Added Italian translation by Tecnikgeek.
*   Added Japanese translation by Michihide Hotta.

### 2.2.11
*   Fixed: The slideshow script loaded the YouTube API twice.
*   Fixed: Floating the slideshow resulted in it having no width, therefore being invisible.
*   Fixed: Sites that don't support URL data weren't able to load a slideshow's stylesheet correctly.
*   Fixed: Slideshow randomly disappeared in Internet Explorer 8 when absolute positioning was used.
*   Added Swedish translation by Åke Isacsson.
*   Added Hebrew translation by Eli Segev.

### 2.2.10
*   Fixed: Auto margin combined with a fixed width will no longer break slideshow's responsiveness.
*   Updated Russian translation, thanks to Dmitry Fatakov.

### 2.2.9
*   Fixed: Descriptions didn't show when 'Hide descriptions' was set to 'No'.
*   Fixed: Title was given wrong size by CSS.

### 2.2.8
*   Fixed: Images that have a, to WordPress, unknown size weren't able to show on all websites.
*   Fixed: Images in the image inserter popup were being skipped over when loading more results because of a faulty query.
*   All stylesheets are now loaded at the page's bottom to validate in HTML checkers and be cached by the visitor's browser.
*   Improved the handling of description boxes. Description boxes will no longer be 'jumpy' nor will they pop up on fly-over.
*   Errors in jQuery's 'ready' method no longer stop the slideshow from showing. However, JavaScript errors should always be resolved.
*   Text slides can now have transparent backgrounds by leaving the 'Background color' field empty.

### 2.2.7
*   Fixed: Slideshow control buttons were placed behind the slideshow.
*   Fixed: Image slides doubly inserted after having clicked on 'Load more results'
*   Included width and height attributes on all image elements.

### 2.2.6
*   Fixed: Theme's that set image heights affected the slideshow's image dimensions.
*   Fixed: Compatibility with the ALO EasyMail Newsletter plugin.
*   Fixed: Image's 'alt' values didn't show when no image title was set.

### 2.2.5
*   Fixed: Slideshow settings nonce prevented 3.4 and older WP users from saving their slideshows.

### 2.2.4
*   Fixed: Video slideshows will now work on mobile devices supported by YouTube.
*   Fixed: Removed multi-line element tags to prevent WordPress from adding '<br />' tags into them.
*   Fixed: Quick editing a slideshow deleted its content.
*   Image files can now be found by their file names.

### 2.2.3
*   Fixed: A 'console.log();' message presumably caused Internet Explorer to have problems when inserting slides.
*   Fixed: Slideshows that are hidden on page's load now wait to become visible before calculating their size.

### 2.2.2
*   Fixed: Float and size bugs caused by the maximum width element.
*   Fixed: PHP errors were showing in 'Delete slide' links.
*   Live width calculations have been halved to improve performance.
*   Default settings have been tuned to cater better to most users.
*   Added Czech translation by Edhel.

### 2.2.1
*   Fixed: A bug in the width calculations caused slideshows in width-less elements to be hidden.
*   Fixed: The functional stylesheet defined the last five header elements for the entire page, instead of just for the slideshow.
*   Fixed: Pagination bullets were showing multiple times because of a CSS bug.
*   Padding and margin in unordered lists set to 0 by default.
*   Videos can now be played in full-screen.
*   Functional stylesheet temporarily moved to the head section of the page until the stylesheets can be loaded beneath it.

### 2.2.0
*   The slideshow script has been completely renewed, making it more lightweight, more versatile and responsive altogether.
*   Slideshow now has a continuing loop.
*   Pagination bullets can now be shown.
*   Slide order can now be completely random, as can the animation type.
*   Added new animations.
*   Slideshow now pauses when a (YouTube) video is playing or when a mouse is hovering over.
*   Fixed: Image title and description were wrongly filled with attachment's content.

### 2.1.23
*   Default settings can now be changed from the 'General Settings' page.
*   Custom styles are now shared across all slideshows and endless customizations can be created.
*   Added input fields to set separate titles and descriptions for shared images.
*   Cleaned up the slides list's layout, to reach a more WordPress-like look.
*   Adapted the 'Dark' style to be more compatible with the WordPress TwentyTwelve theme.
*   Changed 'Insert Slideshow' link to appear as a button.
*   Empty settings won't be shown at the end of the settings boxes.

### 2.1.22
*   Added French translation.
*   Added a "General Settings" page, containing user capability settings.
*   Cleaned up unnecessary settings that were showing on page.
*   Replaced the, in WordPress 3.5, deprecated function 'wp_get_single_post' with the 'get_post' function.
*   Added an on-page error logger to be able to solve back-end issues faster. Nothing is shown when no errors exist, doesn't affect SEO.

### 2.1.21
*   Fixed: Adding new slides was made impossible by a faulty setting.
*   Fixed: Image tag placed on multiple lines caused some sites to not display images correctly due to an inserted break character.

### 2.1.20
*   Fixed: Query filters will no longer alter the output of the slideshow.
*   Fixed: Images not always showing in image inserter popup.
*   Compatibility with WordPress 3.5 confirmed.
*   First back-end increment towards version 2.2.0, introducing a more efficient way to store and retrieve the slideshow's settings and slides.

### 2.1.19
*   Fixed: Slides are now always floated, despite any parent CSS settings.
*   Fixed: Slideshow settings will no longer cloud any other posts with their post-meta.
*   PHP snippet will now only be shown when the current user can edit themes.
*   Videos in the slideshow will from now on depend on Wordpress' swfobject.js file.
*   Changed slideshow's script namespace from 'slideshow_script' to 'slideshow-jquery-image-gallery-script'.
*   Untitled slideshows in the widget form will now display as 'Untitled slideshow', instead of an empty field.

### 2.1.18
*   Text slide descriptions are now displayed in text areas, making editing of long descriptions more convenient.
*   Backgrounds of text slides can now be set to transparent by leaving the 'Background color' field empty.
*   Settings are now loaded from a JavaScript variable, so (the major) search engines won't read them as actual content.
*   Widget title's HTML tags are now discarded when no widget title is set.

### 2.1.17
*   Fixed: Invalid argument being supplied for the foreach loop in SlideshowPluginPostType on line 352.
*   Fixed: Undefined index being thrown by URL target setting on slideshow creation.
*   Video slide now accepts YouTube URLs as well.

### 2.1.16
*   Security update enabling HTML in slides again, but only allowing it in a very strict format without any scripts.
*   Added shortcode editor, which provides a more convenient way of inserting slideshows in your posts and pages.
*   Updated the way slideshows are retrieved. A faulty ID will no longer cause the slideshow to not show at all.
*   Slideshows can now also be fetched by their slugs.
*   The example shortcode's ID on the slideshow settings page is now surrounded by quotes to prevent confusion.

### 2.1.15
*   Fixed: Security issues.
*   Added Chinese translation.

### 2.1.14
*   Fixed: Text slide descriptions allow HTML again.

### 2.1.13
*   Fixed: PHP security issues.
*   Set order of images gotten in 'Image slide' pop-up to post date, descending.

### 2.1.12
*   Moved slideshow activation to the footer script. Footer jQuery scripts are now supported.

### 2.1.11
*   Fixed: Conflict with the Gravity Forms plugin.

### 2.1.10
*   Fixed: Slideshow widget form now is compatible with older versions of PHP, that didn't recognize a null value as a set value.

### 2.1.9
*   Fixed: Not all admin themes support scripts in the admin footer, admin scripts are moved to the header.

### 2.1.8
*   Option added to be able to control whether to use a filter, or to directly output on shortcode.

### 2.1.7
*   Fixed: Slideshow in some cases unable to show next slide in fade animation.

### 2.1.6
*   Slideshow widgets are loaded using theme sidebar settings, making the widget more dynamic.
*   Fixed: Slideshow widget title can now be set to an empty value.

### 2.1.5
*   Fixed: Wordpress intervened with the HTML output by the shortcode, this caused scripts to break.
*   Fixed: Slideshow width isn't affected by width-less elements anymore, instead it seeks the first div's width.

### 2.1.4
*   Fixed: Slideshows in posts are now longer broken by Wordpress inserted 'em' tags.
*   Fixed: Image borders no longer fall off-slide.

### 2.1.3
*   Fixed: Overflow container now adapts to its parent element correctly.
*   Fixed: Internet Explorer now shows control panel (buttons etc.) on top of the Flash element.
*   Fixed: Images are now loaded by the Wordpress function, rather than being loaded from the database's 'guid'.

### 2.1.2
*   Wordpress media uploader link in image inserter pop-up now opens in a new window.
*   Fixed: Image inserter pop-up CSS no longer pushes the 'insert' buttons off-screen.

### 2.1.1
*   Fixed: Settings meta-box threw an unexpected 'T_ENDFOREACH' since a shorthand PHP tag was used improperly.

### 2.1.0
*   Added Youtube video slides.
*   Slide URLs can now be chosen to open in a new window.
*   Added headers above settings, giving the user mover oversight.
*   Endless scrolling is now available in the image inserter pop up.
*   Images are now centered in their slides by default.
*   Script is now activated on document ready, not window load.
*   Hid slides in another element so that buttons could overflow the slideshow container.
*   Fixed: Hide-away settings were influenced by their own settings fields.
*   Fixed: Stretching was not always handled correctly.
*   Fixed: Script counter made the first view show twice.

### 2.0.1
*   Fixed: Version 1.x.x slides disappeared after updating to version 2.0.0. An automatic converter has been added.

### 2.0.0
*   Complete sideshow script revision to support new features.
*   The script now supports two kinds of animations: 'Slide' and 'Fade'.
*   Multiple images can be shown in one slide, instead of one.
*   Text slides are available.
*   Descriptions are more cooperative, they don't overlap the entire image anymore. (Instead they hide or have a user-defined fixed height)
*   Multiple slideshows can now be shown on one page.
*   Play and pause buttons are now available, as is the option not to auto-play and/or loop the slideshow.
*   Stylesheets no longer partially depend on the website's stylesheet, except for the fonts.
*   The script and its functional stylesheet are now compressed to save loading time.
*   Added jQuery sortables script to sort slides.
*   Images you've already uploaded and attached to other posts can now be loaded into the slideshow, saving disk space (and time).

### 1.3.5
*   Fixed: Namespace complications found with the Slideshow widget, renamed all classes.

### 1.3.4
*   Fixed: Custom width of the slideshow will no longer cause buttons to fall off-screeen.

### 1.3.3
*   Extended compatibility to servers that do not support short php opening tags.

### 1.3.2
*   Fixed: 1.3.1 Bugfix failed to work, fixed problem entirely after reproducing it.
*   Added alternative way to load default css into empty custom-style box, so that users without 'allow_url_fopen' enabled aren't influenced negatively by it.

### 1.3.1
*   Fixed: Check if function 'file_get_contents' exists before calling it, some servers have this disabled. (This throws errors and messes up the plugin)

### 1.3.0
*   Added Dutch translation.
*   Custom styles for each slideshow are now available to be more compatable with every theme. (Black and transparent scheme)
*   Encapsulated a css class so that it does not interfere with anything outside the slideshow_container.
*   Moved slides list to the side, saving space on the slideshow specific settings page.
*   Settings bugs completely fixed, finally. (Previous version deleted post-meta on auto-save)
*   Moved Slideshow settings and images script to inside the slideshow_container, outputting a more coherent whole.
*   Settings moved from multiple meta keys to a single one. (This resets everyone's settings)
*   Added a Wordpress media upload button to the slides list, this simplifies attaching images to a slideshow.
*   Better way of including the jQuery library is now being used.
*   Fixed bug with the number of slides shown in the slideshow stuck at the default value of five.

### 1.2.1
*   Fixed: Slideshow specific settings not saving.

### 1.2.0
*   Slideshows can now be placed in posts as well, using shortcode [slideshow id=*SlideshowPostId*].
*   Added a widget that can be loaded with an existing slideshow of choice.
*   Tested up to version 3.4.

### 1.1.0
*   Added jQuery library as Wordpress websites don't seem to load them by default.
*   Slideshow script now depends on by the plugin enqueued jQuery script.

### 1.0.1
*   Added documentary comments.
*   Fixed error with directory paths causing Slideshows post type page to generate warnings.

### 1.0.0
*	Initial release.


## Links

*	[Stefan Boonstra](http://stefanboonstra.com/)
*   [Slideshow project board](https://trello.com/board/slideshow-plugin-wordpress/506311260cc04591010463c2)
*   [GitHub](https://github.com/Boonstra/Slideshow)
*   [Prepros](http://alphapixels.com/prepros/)