# Cabintale Booking Calendar

WordPress plugin that puts a [Cabintale](https://cabintale.com/) booking widget on a page as a Gutenberg block or a shortcode.

The user-facing documentation is [`readme.txt`](readme.txt) — that file is what the WordPress.org directory renders, so it is the one to keep current.

## How it fits together

The plugin does not implement any booking logic. It emits the `<cabintale-root>` element that Cabintale's own `widget-embed.js` looks for, and lets that script render the widget in an iframe.

```
block ─┐
       ├─→ Renderer::render() ─→ <cabintale-root data-token="…">
shortcode ─┘                     + enqueue widget-embed.js
```

One renderer, two entry points. The markup contract lives in exactly one file.

| Path | What it is |
|---|---|
| `cabintale-booking-calendar.php` | Header, constants, script registration, bootstrap |
| `includes/class-renderer.php` | The only place widget markup is produced |
| `includes/class-block.php` | Registers `cabintale/widget` |
| `includes/class-shortcode.php` | Registers `[cabintale_widget]` |
| `includes/class-settings.php` | Settings → Cabintale |
| `blocks/widget/` | `block.json` + `render.php` |
| `assets/js/editor.js` | Editor UI |

## Deliberate choices

**No build step.** `assets/js/editor.js` is plain JavaScript using `wp.element.createElement`, not JSX. The editor surface is a placeholder card and four controls; a toolchain would cost more than it returns, and unbuilt source is the easiest thing for a plugin reviewer to read.

**The block is dynamic, not static.** PHP renders the front end, so the markup is never stored in post content. That keeps one renderer shared with the shortcode, and it stops `wp_kses` from stripping `<cabintale-root>` for authors without `unfiltered_html` — the failure that makes copy-paste embed snippets unreliable on WordPress.

**The editor shows a placeholder, not a live widget.** The editor canvas is itself an iframe in current WordPress, and the booking dialog opens as a full-viewport `postMessage`-driven overlay. Nesting that inside the editor invites stacked-overlay bugs Cabintale has already paid for once.

**The app URL is not a setting.** It comes from the `CABINTALE_APP_URL` constant or the `cabintale_app_url` filter, so a low-privilege editor cannot repoint the script tag at a domain of their choosing.

**Widget IDs are shape-checked, not just escaped.** `Renderer::is_token()` requires a UUID before a value reaches an attribute.

## Local development

Point `CABINTALE_APP_URL` at your Cabintale install in `wp-config.php`:

```php
define( 'CABINTALE_APP_URL', 'http://admin.cabintale.local:8888' );
```

Then symlink this directory into your test site:

```bash
ln -s /path/to/cabintale-booking-calendar /path/to/wordpress/wp-content/plugins/cabintale-booking-calendar
```

## Related

The Cabintale side of this integration — the embed contract, the connect handshake and the read-only site API — lives in the `admin.cabintale` repository:

- `docs/embed-patterns.md` — the `<cabintale-root>` attribute contract this plugin depends on
- `docs/superpowers/specs/2026-08-01-wordpress-embed-plugin-design.md`
- `docs/superpowers/specs/2026-08-01-wordpress-connect-flow-design.md`

## License

GPLv2 or later. See [LICENSE](LICENSE).
