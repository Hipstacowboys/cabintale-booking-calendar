# Translation sources

**Nothing in this directory ships in the plugin zip.** [`.distignore`](../.distignore)
excludes it, and the plugin does not call `load_plugin_textdomain()`.

Plugins hosted on WordPress.org get their translations from
[translate.wordpress.org](https://translate.wordpress.org/), which compiles every
locale and delivers it to `WP_LANG_DIR/plugins`. Just-in-time loading has found
translations there since WordPress 4.6, so a hosted plugin needs no `.mo`, no
`.l10n.php`, no locale `.json` and no `Domain Path` header. Shipping them is a
review rejection, and they go stale the moment a community translator improves
on them.

What is left here is source material only:

| File | What it is for |
|---|---|
| `cabintale-booking-calendar.pot` | Reference template. WordPress.org generates its own from the source; keep this only as a local diffing aid. |
| `cabintale-booking-calendar-cs_CZ.po` | The Czech translation, to be imported into GlotPress once the plugin is approved. |

To get the Czech strings live: open the plugin's translation project on
translate.wordpress.org, then **Import Translations** and upload the `.po`. A
General Translation Editor for Czech has to approve them before they reach
users.

Regenerate the template after changing any translatable string:

```bash
wp i18n make-pot . languages/cabintale-booking-calendar.pot
```

Do **not** run `wp i18n make-mo`, `make-php` or `make-json` here. Those produce
exactly the compiled artefacts that must not be in the zip.
