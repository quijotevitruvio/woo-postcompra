# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Versionado semántico.

## [1.0.0] - 2026-10-03
### Añadido
- Primera versión pública: plugin `woo-postcompra/` (une los módulos internos «Invítanos un café» y «Reseñas con foto»).
- Página de ajustes en WooCommerce → Postcompra (opción `pa_postcompra_ajustes`): montos, mínimo, máximo, impuesto del producto, textos, colores, moderación y tamaño de foto.
- Botón para crear el producto del café.
- Reseñas solo de compradores verificados (ajuste, activo por defecto) y foto que exige compra.
- Enganche propio a `woocommerce_thankyou` y al bloque de totales de «pedido recibido»; ya no depende de plugins privados.
### Cambiado
- Montos con la moneda de WooCommerce; colores como variables CSS (`--pa-postcompra-acento`, `--pa-postcompra-fondo`).
- Foto: se reencoda con GD sin metadatos (EXIF/GPS), nombre aleatorio, carpeta protegida (`index.php` + `.htaccess`), solo se muestra en reseñas aprobadas y se borra con la reseña.
- La foto ahora sí se muestra (el filtro anterior se enganchaba a una acción y nunca pintaba nada).
- El recordatorio de reseñas y la tarjeta comprueban la clave del pedido antes de mostrar nada.
### Quitado
- Textos y nombre de la tienda de origen, medios de pago colombianos en la tarjeta, clase de impuesto fija y montos en pesos.

Este historial empieza en 1.0.0; el desarrollo anterior fue interno de librosmedellin.com.

---

## [1.0.0] - 2026-10-03 (English)
- First public release: settings page (WooCommerce → Postcompra), tip product creation button, verified-buyer reviews, metadata-free GD re-encoded review photos in a protected folder, own hooks for classic and block «order received» pages, CSS variables for colours, store currency for amounts.
- Fixed: review photos are now actually displayed (the previous filter was attached to an action).
