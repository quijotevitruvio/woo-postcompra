# Seguridad — Woo Postcompra 1.0.0

Revisión hecha al preparar la versión pública (2026-10-03). Superficie: subida de fotos en reseñas, propina (monto elegido por el cliente), página «pedido recibido» y ajustes.

## Arreglado en esta versión

| # | Hallazgo | Dónde (versión interna) | Gravedad | Arreglo |
|---|---|---|---|---|
| 1 | Con Imagick, WordPress **conserva el perfil EXIF** (`strip_meta()` lo protege): la foto publicada podía llevar la ubicación GPS del cliente. | `pa-las-resenas.php:56-67` | Media-alta (datos personales) | Se fuerza GD (`wp_image_editors`), que reencoda desde píxeles sin metadatos; antes se aplica `maybe_exif_rotate()`. Sin GD no se guarda foto. `includes/resenas.php`, `plr_guardar_foto()`. |
| 2 | Cualquiera que pudiera comentar subía foto; no se exigía haber comprado. | `pa-las-resenas.php:39-55` | Media | La foto exige `wc_customer_bought_product()`; ajuste `solo_compradores` (activo) rechaza con 403 reseñas de no compradores en `preprocess_comment`. |
| 3 | Foto guardada aunque la reseña fuera spam/pendiente, con nombre adivinable (`resena-<id>-<time>`). | `pa-las-resenas.php:66` | Baja | Nombre con 12 caracteres aleatorios; no se procesa spam/papelera ni respuestas; reseñas con foto pendientes por defecto (`moderar_fotos`); solo se muestra en reseñas aprobadas; se borra con la reseña. |
| 4 | Carpeta `uploads/lm-resenas` sin `index.php` ni reglas. | `pa-las-resenas.php:64-65` | Baja | `index.php` (sin listado) + `.htaccess` que niega scripts y fija `nosniff`. |
| 5 | Tipo comprobado solo con `getimagesize`; sin límite de dimensiones. | `pa-las-resenas.php:49-55` | Baja | `is_uploaded_file`, `UPLOAD_ERR_OK`, `filesize` real, `getimagesize` + `finfo`, máx. 8000 px y 40 MP. |
| 6 | Tarjeta del café añadida al bloque de totales sin comprobar la clave del pedido. | `pa-el-cafe.php:278-289` | Informativa (solo revela si el pedido incluía café) | `pa_postcompra_puede_ver_pedido()`: clave del pedido (`hash_equals`) y dueño; no se añade nada si WooCommerce no pintó los totales. Igual para el recordatorio de reseñas (lista productos del pedido). |
| 7 | La foto **nunca se mostraba**: se usaba `add_filter` sobre `woocommerce_review_comment_text`, que es una acción. | `pa-las-resenas.php:78-89` | Funcional | Filtro `comment_text` con comprobación de producto y aprobación. |

## Riesgos que quedan (documentados)

| # | Riesgo | Archivo | Gravedad | Recomendación |
|---|---|---|---|---|
| A | Un invitado se verifica por el correo que escribe: si conoce el correo de un comprador, puede reseñar ese producto. | `woo-postcompra/includes/resenas.php` (`plr_exigir_compra`) | Baja | Las fotos van a moderación por defecto. Si importa, exige cuenta para reseñar (Ajustes → Comentarios: «los usuarios deben estar registrados»). |
| B | El `.htaccess` de la carpeta de fotos solo lo leen Apache/LiteSpeed. | `includes/resenas.php` (`plr_preparar_carpeta`) | Baja | En nginx, niega la ejecución en `uploads/` desde la configuración del servidor. Los archivos son siempre `.webp`/`.jpg` reencodados. |
| C | El añadido al carrito del café es por enlace GET sin nonce (igual que `?add-to-cart=` de WooCommerce). | `includes/cafe.php` (`pcaf_tarjeta`, `pcaf_guardar_monto`) | Informativa | El monto se valida en el servidor (mínimo/máximo); un enlace malicioso solo añade un café que el cliente ve antes de pagar. |

## Comprobaciones
- Sin `wp_ajax_*` ni rutas REST. Formularios de admin: Settings API (`options.php`, nonce de `settings_fields`, capacidad `manage_woocommerce`) y `admin-post.php` con `check_admin_referer` + `current_user_can`.
- Salida escapada (`esc_html`, `esc_attr`, `esc_url`); entrada saneada (`wc_clean`, `sanitize_*`, `absint`).
- Sin SQL directo, sin `unserialize`, sin redirecciones abiertas (`wp_safe_redirect` a admin).
