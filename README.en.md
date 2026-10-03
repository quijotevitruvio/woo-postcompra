# Woo Postcompra

> What you offer customers after they buy: a buy-us-a-coffee tip and photo reviews.

Español: [README.md](README.md)

## What it is
- **Buy us a coffee**: tip card on the «order received» page (and via the `[invitanos_un_cafe]` shortcode). Suggested amounts, «Other», minimum and maximum are configurable and validated server-side.
- The tip is **a real WooCommerce product** (hidden from the catalogue, virtual, one per cart), so it flows into checkout, reports and invoicing like any sale.
- **Photo reviews**: photo field (JPG/PNG/WebP, configurable max size) in the review form; re-encoded to an 800 px WebP **without metadata** (EXIF/GPS) and shown inside approved reviews. 📸 column in the comments admin.
- **Verified buyers only**: by default only customers who bought the product can review it (checked server-side); photos always require a purchase.
- **Email-free review campaign**: on «order received» and in «My account» it asks for reviews of purchased items that have none yet.
- Works with the classic «order received» page (`woocommerce_thankyou`) and the block-based one (after the totals block). HPOS-compatible.

## Who it is for
Small WooCommerce shops (books, free content, crafts…) that accept voluntary support and want more photo reviews without sending emails.

## Screenshots
_(Pending: they will live in `docs/img/` without real customer or order data.)_

## Requirements
- WordPress 6.0+, PHP 7.4+, WooCommerce 7.0+ (the plugin declares `Requires Plugins: woocommerce`).
- For photos: the PHP **GD** extension (with WebP to save WebP; otherwise JPEG). Imagick is deliberately not used: WordPress keeps EXIF when processing with Imagick, which would publish the customer's location.

## Installation
1. Copy the `woo-postcompra/` folder into `wp-content/plugins/` and activate it.
2. Go to **WooCommerce → Postcompra** and click **«Crear el producto del café»** (or from the CLI: `wp eval "pcaf_crear_producto();"`).
3. On the same page choose the **product tax** (ask your accountant), amounts and texts.

## Settings
Everything lives in the `pa_postcompra_ajustes` option (array) and is edited under **WooCommerce → Postcompra**. Empty texts fall back to the translatable default (text domain `woo-postcompra`).

| Setting | Key in `pa_postcompra_ajustes` | Default | What it does |
|---|---|---|---|
| Minimum amount | `cafe_minimo` | `1` | Minimum tip in the store currency (integers). |
| Maximum amount | `cafe_maximo` | `1000` | Sanity cap (avoids an extra zero). |
| Suggested amounts | `cafe_montos` | `2, 5, 10, 20` | Card buttons. |
| Preselected amount | `cafe_sugerido` | `5` | Highlighted button and no-JS link amount. |
| «Other» step | `cafe_paso` | `1` | `step` of the number field. |
| Product tax | `cafe_clase_impuesto` | `''` (standard class) | Tax class slug, or `__ninguno` (no tax). Applied to the product on save. |
| Product name | `cafe_nombre` | «Un café para» + site name | Used when the product is created. |
| Title / text after purchase | `cafe_titulo_compra`, `cafe_sub_compra` | Neutral text | Card heading on «order received». |
| Title / text «reading» | `cafe_titulo_lectura`, `cafe_sub_lectura` | Neutral text | For `[invitanos_un_cafe contexto="lectura"]`. |
| Note under the button | `cafe_pie` | empty (hidden) | E.g. the payment methods you accept. |
| Thank-you line | `cafe_gracias` | «Y gracias por el café ☕» | When the order already was a coffee. |
| Verified buyers only | `solo_compradores` | `1` | Rejects (403) product reviews from people who did not buy it. Moderators are not blocked. |
| Moderate photo reviews | `moderar_fotos` | `1` | Reviews with a photo stay pending until approved. |
| Max photo size | `foto_mb` | `5` | In MB (1-20). |
| Review texts | `resenas_etiqueta_foto`, `resenas_titulo_gracias`, `resenas_intro_gracias`, `resenas_titulo_cuenta`, `resenas_intro_cuenta` | Neutral text | Photo field label and reminders. |
| Accent colour | `color_acento` | `#2271b1` | CSS variable `--pa-postcompra-acento`. |
| Card background | `color_fondo` | `#ffffff` | CSS variable `--pa-postcompra-fondo`. |

Other options and constants:

| Setting | Option / constant | Default | What it does |
|---|---|---|---|
| Turn coffee off | option `pa_postcompra_cafe_off` | `0` | `1` does not load the coffee module. |
| Turn reviews off | option `pa_postcompra_resenas_off` | `0` | `1` does not load the reviews module. |
| Hide the card | option `pcaf_desactivado` | `0` | `1` hides the card without touching the product. |
| Coffee product | option `pcaf_producto_id` | (filled on creation) | ID of the tip product. |
| Photo folder | constant `PA_POSTCOMPRA_CARPETA_FOTOS` | `lm-resenas` | Sub-folder of `uploads/` for photos. |

Filters: `pcaf_montos` (array of amounts) and `pcaf_monto_sugerido` (int).

**Currency**: the WooCommerce currency (Settings → General) is used to display amounts; amounts are stored as integers.

## Photo security
- Type checked from the content (`getimagesize` + `finfo`), not the extension; max size and dimensions (8000 px, 40 MP).
- Re-encoded with GD to WebP (or JPEG): metadata and any hidden payload are dropped.
- Random name (`resena-<id>-<12 chars>.webp`); the folder gets an `index.php` (no listing) and an `.htaccess` that denies scripts.
- Shown only on approved reviews; deleting the review deletes the photo.

More in [SEGURIDAD.md](SEGURIDAD.md).

## How to undo
- Set the switch option to 0 (or delete it) and the piece comes back.
- Deactivate the plugin to remove everything; the tip product, the `pa_postcompra_ajustes` option and the photos in `uploads/lm-resenas/` stay until you delete them.

## FAQ

**Where is the tip paid?**  
In your normal checkout with your payment methods. To mention them, use the «Note under the button».

**Can someone pay less than the minimum?**  
No: the amount is sanitised server-side (minimum, maximum, negatives and junk).

**Are review photos moderated?**  
Yes, by default a review with a photo stays pending; WordPress comment moderation also applies.

**Can a guest review?**  
If they bought as a guest, yes: it is checked against the order email. The visitor types that email, which is why photos go to moderation.

## Limitations
- Amounts are integers in the store currency.
- A photo that fails the checks is silently dropped (the review is published without it).
- Without GD on the server, photos are not saved.

## License, author and support
- License: GPL-2.0-or-later (see `LICENSE`).
- Author: **Andrés Valencia Tobón** · [GitHub](https://github.com/quijotevitruvio) · [LinkedIn](https://www.linkedin.com/in/andr%C3%A9s-valencia-tob%C3%B3n/)
- Support the project: https://paypal.me/bibliotecologo
- Developed for librosmedellin.com.
