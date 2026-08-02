=== Cabintale Booking Calendar ===
Contributors: cabintale
Tags: booking, availability, calendar, reservations, vacation rental
Requires at least: 6.3
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show live availability and take bookings for your cabin, cottage or rental. Add your Cabintale widget as a block or a shortcode.

== Description ==

Cabintale Booking Calendar puts your Cabintale booking widget on your WordPress site without touching any code.

Add the **Cabintale booking widget** block in the block editor, or use the `[cabintale_widget]` shortcode in Elementor, Bricks, the classic editor or a theme template. Both produce the same widget.

**What you get on your page**

* An availability calendar showing which dates are free, with your prices
* Time-slot booking for services (saunas, hot tubs, guided tours)
* A checkout button for products and vouchers
* Availability-only mode, when you want to show a calendar without taking bookings

**Why use the plugin instead of pasting embed code**

Many themes, security plugins and page builders strip `<script>` tags out of post content, which quietly breaks copy-and-paste embed snippets. This plugin enqueues the script the way WordPress expects, so the widget keeps working. It also loads that script **only** on pages where you actually placed a widget.

= Do I need a Cabintale account? =

Yes. Cabintale is a booking system for cabins, cottages and small rentals, and this plugin displays widgets from it. **Creating an account is free** — the free Starter plan covers up to 2 places and 2 services, availability calendars, seasonal pricing and two-way iCal sync with Airbnb, Booking.com and Vrbo. Taking booking requests through the widget and accepting payments are paid plans.

== External services ==

This plugin connects to Cabintale, the booking service that stores your places, availability and widgets. It is required for the plugin to do anything.

**If you connect your account,** this site stores a read-only access token and uses it to ask Cabintale for the list of your widgets, so you can pick one by name. Those requests come from your server, not from your visitors' browsers, and no visitor data is involved. Connecting is optional.

**What is sent, and when:** on a page where you have placed a Cabintale widget, the visitor's browser loads the widget script from `admin.cabintale.com` and displays the widget in an iframe served from the same domain. As with any embedded content, that request includes the visitor's IP address and browser user agent. If the visitor makes a booking, the details they type into the widget are submitted to Cabintale.

Nothing is sent to Cabintale from your WordPress admin, and the plugin makes no requests at all on pages without a widget.

Service provided by Cabintale: [cabintale.com](https://cabintale.com/) — [terms of service and privacy policy](https://cabintale.com/legal).

== Installation ==

1. Install and activate the plugin.
2. Go to **Settings → Cabintale** and click **Connect to Cabintale**. Sign in, or create a free account — the sign-up walks you through adding your place, prices and availability, and makes your first widget for you.
3. Back in WordPress, click **Create my booking page** — or add the **Cabintale booking widget** block to any page and pick a widget from the list.

Prefer not to connect? Paste a widget ID under "Use a widget ID instead" and skip the rest.

== Frequently Asked Questions ==

= Do I have to copy a widget ID? =

No. Connect your account under Settings → Cabintale and your widgets appear in a dropdown, by name. The ID field is still there if you prefer it.

= What can this plugin see in my Cabintale account? =

Only the names of your places, services and widgets. The connection is read-only: it cannot see your bookings, guests or payments, and it cannot change anything. You can disconnect it at any time from Settings → Cabintale or from your Cabintale settings.

= Where do I find my widget ID, if I want to use one? =

Log in to Cabintale, open the widget you want to show, and copy the ID from its embed code. It looks like `3f8a1c22-9b41-4d0e-8a77-2c1f9e5d4b60`.

= Is it free? =

The plugin is free, and Cabintale has a free Starter plan that includes the embeddable widget, availability calendar, seasonal pricing and iCal sync. Accepting booking requests and payments through the widget requires a paid Cabintale plan.

= Does it work with Elementor, Bricks or the classic editor? =

Yes. Use the shortcode `[cabintale_widget]` anywhere those accept shortcodes.

= Can I show more than one widget on a page? =

Yes. Add as many blocks or shortcodes as you need, each with its own widget ID.

= Can I show availability without taking bookings? =

Yes. Turn on **Availability only** in the block settings, or use `[cabintale_widget availability_only="1"]`.

= Why does the block show a card instead of the widget while I am editing? =

The editor shows a placeholder on purpose. The booking dialog opens as a full-screen overlay, which does not behave well nested inside the editor canvas. Preview or publish the page to see the real widget.

= The widget does not appear on my page =

Check that the widget ID is correct and that the widget is active in Cabintale. If your site is behind a security plugin or a strict content security policy, allow `admin.cabintale.com`.

== Shortcode attributes ==

* `token` — the widget ID. Defaults to the one saved in Settings → Cabintale.
* `type` — `property` (availability calendar), `service` (time slots) or `checkout` (product button). Default `property`. `place` is accepted as a synonym.
* `border` — `1` or `0`. Default `1`.
* `availability_only` — `1` or `0`. Places only. Default `0`.

Example: `[cabintale_widget type="service" border="0"]`

== Screenshots ==

1. An availability calendar on a published page.
2. The Cabintale booking widget block in the editor.
3. Block settings: pick a widget by name.
4. Settings → Cabintale: connected account, widget list and live preview.

== Changelog ==

= 0.4.1 =
* Wider settings layout, and the widget actions are now buttons rather than a text link.

= 0.4.0 =
* Each widget now links straight to its style, language and booking-form settings in Cabintale.
* Links to the Cabintale documentation throughout the settings screen.

= 0.3.0 =
* Cabintale branding on the settings screen, layered over standard WordPress admin components so it still follows your admin colour scheme.
* Settings screen rebuilt: it now explains what lives in Cabintale and what lives in WordPress, and only shows instructions once they can be acted on.
* Preview any widget from the settings screen before putting it on a page.
* Connect, Disconnect and Create booking page now show progress instead of sitting silent for a second.
* Terminology follows the Cabintale documentation — a single cabin is a "property". The shortcode accepts `type="property"` as well as `type="place"`.

= 0.2.0 =
* Connect your Cabintale account and pick widgets by name instead of copying IDs.
* The block now asks only which widget to show — each one is labelled as a place, service or product, so there is no type to keep in sync.
* Settings screen rewritten to explain what lives in Cabintale and what lives in WordPress, with links to the right place for each.
* One-click "Create my booking page".

= 0.1.0 =
* First release: Cabintale booking widget block, `[cabintale_widget]` shortcode, and a default-widget setting.
