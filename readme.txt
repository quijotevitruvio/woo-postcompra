=== Woo Postcompra ===
Contributors: quijotevitruvio
Donate link: https://paypal.me/bibliotecologo
Tags: woocommerce, tip, reviews, photo reviews, thank you page
Requires at least: 6.0
Requires Plugins: woocommerce
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

What you offer customers after they buy: buy-us-a-coffee tip and photo reviews.

== Description ==

* **Buy us a coffee**: tip card on the «order received» page (classic and block-based) and via `[invitanos_un_cafe]`. Suggested amounts, «Other», minimum and maximum are settings and are validated server-side.
* The tip is **a real WooCommerce product** (hidden from the catalogue, virtual, one per cart), so it flows into checkout, reports and invoicing like any sale.
* **Photo reviews**: photo field (JPG/PNG/WebP, configurable max size); re-encoded with GD to an 800 px WebP without EXIF/GPS metadata and shown inside approved reviews. 📸 column in the comments admin.
* **Verified buyers only** (setting, on by default): product reviews from people who did not buy the product are rejected server-side; photos always require a purchase and go to moderation by default.
* **Email-free review campaign**: on «order received» and in «My account» it asks for reviews of purchased items that have none yet.
* HPOS-compatible.

Who it is for: small WooCommerce shops that accept voluntary support and want more photo reviews without sending emails.

Author: Andrés Valencia Tobón - https://github.com/quijotevitruvio - https://www.linkedin.com/in/andr%C3%A9s-valencia-tob%C3%B3n/
Developed for librosmedellin.com. Support: https://paypal.me/bibliotecologo

== Installation ==

1. Copy the `woo-postcompra` folder into `wp-content/plugins/` and activate it (requires WooCommerce).
2. Go to WooCommerce → Postcompra and click «Crear el producto del café» (or `wp eval "pcaf_crear_producto();"`).
3. Choose the product tax (ask your accountant), amounts, texts and colours on the same page.

== Setup ==

* All settings live in the `pa_postcompra_ajustes` option (WooCommerce → Postcompra). See README.md for the full table.
* Filters: `pcaf_montos`, `pcaf_monto_sugerido`.
* Switches: options `pa_postcompra_cafe_off`, `pa_postcompra_resenas_off`, `pcaf_desactivado`.
* Photo folder: constant `PA_POSTCOMPRA_CARPETA_FOTOS` (default `lm-resenas` inside uploads).

== Frequently Asked Questions ==

= Where is the tip paid? =
In your normal checkout with your payment methods. Use the «note under the button» setting to mention them.

= Can someone pay less than the minimum? =
No: the amount is sanitised server-side (minimum, maximum, negatives and junk).

= Are review photos moderated? =
Yes by default (setting «Moderar reseñas con foto»), on top of the WordPress comment moderation.

== Limitations ==

* Amounts are integers in the store currency.
* A photo that fails the checks is silently dropped.
* The PHP GD extension is required for photos (Imagick is not used because WordPress keeps EXIF with it).

== Screenshots ==

1. «Buy us a coffee» card after checkout
2. Photo review on a product page
3. Review reminder in «My account»

== Changelog ==

= 1.0.0 =
* First public release: settings page, verified-buyer reviews, metadata-free photos, works without private companion plugins.

== Upgrade Notice ==

= 1.0.0 =
First public release.
