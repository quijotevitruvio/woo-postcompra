# Woo Postcompra

> Lo que se le ofrece al cliente después de comprar: invitar un café (propina) y dejar una reseña con foto.

English: [README.en.md](README.en.md)

## Qué es
- **Invítanos un café**: tarjeta de propina en la página «pedido recibido» (y con el shortcode `[invitanos_un_cafe]`). Montos sugeridos, «Otro», mínimo y máximo configurables y validados en el servidor.
- La propina es **un producto real de WooCommerce** (oculto del catálogo, virtual, un café por carrito), así que entra en caja, informes y facturación como cualquier venta.
- **Reseñas con foto**: campo de foto (JPG/PNG/WebP, tamaño máximo configurable) en el formulario de reseñas; se vuelve a codificar a WebP de 800 px **sin metadatos** (EXIF/GPS) y se muestra dentro de la reseña aprobada. Columna 📸 en el admin de comentarios.
- **Solo compradores verificados**: por defecto, solo quien compró el producto puede reseñarlo (comprobado en el servidor); la foto exige compra siempre.
- **Campaña de reseñas sin correos**: en «pedido recibido» y en «Mi cuenta» invita a reseñar lo comprado que aún no tiene reseña.
- Funciona con la página «pedido recibido» clásica (`woocommerce_thankyou`) y con la de bloques (tras el bloque de totales). Compatible con HPOS.

## Para quién
Tiendas WooCommerce pequeñas (libros, contenido gratuito, artesanía…) que aceptan apoyo voluntario y quieren más reseñas con foto sin enviar correos.

## Capturas
_(Pendientes: se guardarán en `docs/img/` sin datos de clientes ni pedidos reales.)_

## Requisitos
- WordPress 6.0+, PHP 7.4+, WooCommerce 7.0+ (el plugin declara `Requires Plugins: woocommerce`).
- Para la foto: extensión **GD** de PHP (con WebP para guardar en WebP; si no, guarda JPEG). Imagick no se usa a propósito: WordPress conserva el EXIF al procesar con Imagick y eso publicaría la ubicación del cliente.

## Instalación
1. Copia la carpeta `woo-postcompra/` en `wp-content/plugins/` y actívalo.
2. Ve a **WooCommerce → Postcompra** y pulsa **«Crear el producto del café»** (o por consola: `wp eval "pcaf_crear_producto();"`).
3. En la misma página elige el **impuesto del producto** (consúltalo con tu contador), los montos y los textos.

## Ajustes
Todos viven en la opción `pa_postcompra_ajustes` (array) y se editan en **WooCommerce → Postcompra**. Los textos vacíos usan el texto por defecto traducible (dominio `woo-postcompra`).

| Ajuste | Clave en `pa_postcompra_ajustes` | Por defecto | Qué hace |
|---|---|---|---|
| Monto mínimo | `cafe_minimo` | `1` | Mínimo de la propina, en la moneda de la tienda (enteros). |
| Monto máximo | `cafe_maximo` | `1000` | Tope de cordura (evita un cero de más). |
| Montos sugeridos | `cafe_montos` | `2, 5, 10, 20` | Botones de la tarjeta. |
| Monto marcado | `cafe_sugerido` | `5` | Botón marcado y monto del enlace sin JS. |
| Paso de «Otro» | `cafe_paso` | `1` | `step` del campo numérico. |
| Impuesto del producto | `cafe_clase_impuesto` | `''` (clase estándar) | Clase de impuesto, o `__ninguno` (sin impuesto). Se aplica al producto al guardar. |
| Nombre del producto | `cafe_nombre` | «Un café para» + nombre del sitio | Se usa al crear el producto. |
| Título / texto tras la compra | `cafe_titulo_compra`, `cafe_sub_compra` | Texto neutro | Encabezado de la tarjeta en «pedido recibido». |
| Título / texto «lectura» | `cafe_titulo_lectura`, `cafe_sub_lectura` | Texto neutro | Para `[invitanos_un_cafe contexto="lectura"]`. |
| Nota bajo el botón | `cafe_pie` | vacío (no se muestra) | P. ej. los medios de pago que aceptas. |
| Agradecimiento | `cafe_gracias` | «Y gracias por el café ☕» | Si el pedido ya era un café. |
| Solo compradores reseñan | `solo_compradores` | `1` | Rechaza (403) reseñas de producto de quien no lo compró. Los moderadores no se bloquean. |
| Moderar reseñas con foto | `moderar_fotos` | `1` | La reseña con foto queda pendiente hasta aprobarla. |
| Tamaño máximo de foto | `foto_mb` | `5` | En MB (1-20). |
| Textos de reseñas | `resenas_etiqueta_foto`, `resenas_titulo_gracias`, `resenas_intro_gracias`, `resenas_titulo_cuenta`, `resenas_intro_cuenta` | Texto neutro | Etiqueta del campo de foto y recordatorios. |
| Color de acento | `color_acento` | `#2271b1` | Variable CSS `--pa-postcompra-acento`. |
| Fondo de la tarjeta | `color_fondo` | `#ffffff` | Variable CSS `--pa-postcompra-fondo`. |

Otras opciones y constantes:

| Ajuste | Opción / constante | Por defecto | Qué hace |
|---|---|---|---|
| Apagar el café | opción `pa_postcompra_cafe_off` | `0` | `1` no carga el módulo del café. |
| Apagar las reseñas | opción `pa_postcompra_resenas_off` | `0` | `1` no carga el módulo de reseñas. |
| Ocultar la tarjeta | opción `pcaf_desactivado` | `0` | `1` oculta la tarjeta sin tocar el producto. |
| Producto del café | opción `pcaf_producto_id` | (se rellena al crear) | ID del producto de la propina. |
| Carpeta de fotos | constante `PA_POSTCOMPRA_CARPETA_FOTOS` | `lm-resenas` | Subcarpeta de `uploads/` para las fotos. |

Filtros: `pcaf_montos` (array de montos) y `pcaf_monto_sugerido` (int).

**Moneda**: se usa la de WooCommerce (Ajustes → General) para mostrar los montos; los montos se guardan como enteros.

## Seguridad de las fotos
- Tipo comprobado por el contenido (`getimagesize` + `finfo`), no por la extensión; tamaño y dimensiones máximas (8000 px, 40 MP).
- Se vuelve a codificar con GD a WebP (o JPEG): se descartan metadatos y cualquier carga escondida.
- Nombre aleatorio (`resena-<id>-<12 caracteres>.webp`); la carpeta lleva `index.php` (sin listado) y `.htaccess` que niega scripts.
- Solo se muestra en reseñas aprobadas; al borrar la reseña se borra la foto.

Más detalle en [SEGURIDAD.md](SEGURIDAD.md).

## Cómo deshacer
- Pon la opción de apagado en 0 (o bórrala) y la pieza vuelve.
- Desactiva el plugin para quitarlo todo; el producto de la propina, la opción `pa_postcompra_ajustes` y las fotos de `uploads/lm-resenas/` quedan hasta que los borres.

## Preguntas frecuentes

**¿Dónde se paga la propina?**  
En el checkout normal de tu tienda, con los métodos de pago que tengas. Si quieres mencionarlos, usa la «Nota bajo el botón».

**¿Puede alguien pagar menos del mínimo?**  
No: el monto se sanea en el servidor (mínimo, máximo, negativos y basura).

**¿Las fotos de reseña se moderan?**  
Sí, por defecto la reseña con foto queda pendiente; además siguen la moderación de comentarios de WordPress.

**¿Un invitado puede reseñar?**  
Si compró como invitado, sí: se comprueba con el correo del pedido. Ese correo lo escribe el visitante, por eso las fotos van a moderación.

## Limitaciones
- Los montos son enteros en la moneda de la tienda.
- Una foto que no pasa las comprobaciones se descarta en silencio (la reseña se publica sin foto).
- Sin GD en el servidor, no se guardan fotos.

## Licencia, autor y apoyo
- Licencia: GPL-2.0-or-later (ver `LICENSE`).
- Autor: **Andrés Valencia Tobón** · [GitHub](https://github.com/quijotevitruvio) · [LinkedIn](https://www.linkedin.com/in/andr%C3%A9s-valencia-tob%C3%B3n/)
- Apoya el proyecto: https://paypal.me/bibliotecologo
- Desarrollado para librosmedellin.com.
