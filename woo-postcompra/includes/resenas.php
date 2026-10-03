<?php
/**
 * Módulo «Reseñas con foto» + recordatorio de reseñas sin correos.
 * Apagar: update_option( 'pa_postcompra_resenas_off', 1 ).
 *
 * Seguridad de la foto:
 *  - Solo en reseñas de producto (no respuestas) de quien compró ese producto.
 *  - Tamaño máximo configurable; tipo comprobado por el CONTENIDO (getimagesize
 *    y finfo), no por la extensión ni por lo que dice el navegador.
 *  - Se vuelve a codificar con GD (WebP o JPEG): eso descarta cualquier carga
 *    escondida y TODOS los metadatos (EXIF/GPS). Imagick no se usa porque
 *    WordPress conserva el perfil EXIF al procesar con Imagick.
 *  - Nombre aleatorio; carpeta con index.php y .htaccess que niega scripts.
 *  - Por defecto la reseña con foto queda pendiente de moderación.
 *
 * @package woo-postcompra
 */

defined( 'ABSPATH' ) || exit;

/** Carpeta dentro de uploads (constante PA_POSTCOMPRA_CARPETA_FOTOS). */
function plr_carpeta() {
	$nombre = defined( 'PA_POSTCOMPRA_CARPETA_FOTOS' ) ? (string) PA_POSTCOMPRA_CARPETA_FOTOS : 'lm-resenas';
	$nombre = sanitize_file_name( $nombre );
	return '' !== $nombre ? $nombre : 'lm-resenas';
}

/** ¿Este usuario/correo compró el producto? */
function plr_compro( $product_id, $user_id, $email ) {
	if ( ! function_exists( 'wc_customer_bought_product' ) ) {
		return false;
	}
	$user_id = (int) $user_id;
	$email   = (string) $email;
	if ( $user_id ) {
		$u = get_userdata( $user_id );
		if ( $u && '' === $email ) {
			$email = $u->user_email;
		}
	}
	if ( ! $user_id && ! is_email( $email ) ) {
		return false;
	}
	return (bool) wc_customer_bought_product( $email, $user_id, (int) $product_id );
}

/* ── 1. Solo compradores verificados reseñan (servidor) ───────────────────── */

add_filter( 'preprocess_comment', 'plr_exigir_compra', 20 );
function plr_exigir_compra( $commentdata ) {
	if ( ! pa_postcompra_ajuste( 'solo_compradores' ) ) {
		return $commentdata;
	}
	$pid = isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0;
	if ( ! $pid || 'product' !== get_post_type( $pid ) ) {
		return $commentdata;
	}
	// Las respuestas del equipo de la tienda no se bloquean.
	if ( current_user_can( 'moderate_comments' ) ) {
		return $commentdata;
	}
	$uid   = isset( $commentdata['user_id'] ) ? (int) $commentdata['user_id'] : get_current_user_id();
	$email = isset( $commentdata['comment_author_email'] ) ? (string) $commentdata['comment_author_email'] : '';
	if ( ! plr_compro( $pid, $uid, $email ) ) {
		wp_die(
			esc_html__( 'Solo los clientes que compraron este producto pueden reseñarlo.', 'woo-postcompra' ),
			esc_html__( 'Reseña no permitida', 'woo-postcompra' ),
			array( 'response' => 403, 'back_link' => true )
		);
	}
	return $commentdata;
}

/* ── 2. Campo de foto en el formulario de reseña + enctype ────────────────── */

add_action( 'comment_form_logged_in_after', 'plr_photo_field' );
add_action( 'comment_form_after_fields', 'plr_photo_field' );
function plr_photo_field() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	static $printed = false;
	if ( $printed ) {
		return;
	}
	// Solo con sesión y habiendo comprado se ofrece la foto.
	$uid = get_current_user_id();
	if ( ! $uid || ( ! current_user_can( 'moderate_comments' ) && ! plr_compro( get_the_ID(), $uid, '' ) ) ) {
		return;
	}
	$printed = true;
	$mb      = max( 1, (int) pa_postcompra_ajuste( 'foto_mb' ) );
	?>
	<p class="comment-form-plr-photo">
		<label for="plr_photo"><?php echo esc_html( pa_postcompra_texto( 'resenas_etiqueta_foto' ) ); ?></label><br>
		<input type="file" name="plr_photo" id="plr_photo" accept="image/jpeg,image/png,image/webp">
		<span class="plr-hint">
			<?php
			/* translators: %d: tamaño máximo en MB. */
			echo esc_html( sprintf( __( 'JPG, PNG o WebP, máx. %d MB. Se publica cuando se aprueba la reseña.', 'woo-postcompra' ), $mb ) );
			?>
		</span>
	</p>
	<script>
	(function(){var f=document.querySelector('#commentform, form.comment-form');if(f){f.enctype='multipart/form-data';}})();
	</script>
	<?php
}

/** ¿La petición trae una foto subida sin errores? */
function plr_hay_foto() {
	return isset( $_FILES['plr_photo']['tmp_name'], $_FILES['plr_photo']['error'] ) // phpcs:ignore WordPress.Security.NonceVerification
		&& UPLOAD_ERR_OK === (int) $_FILES['plr_photo']['error'] // phpcs:ignore WordPress.Security.NonceVerification
		&& '' !== (string) $_FILES['plr_photo']['tmp_name']; // phpcs:ignore WordPress.Security.NonceVerification
}

/* ── 3. Reseña con foto → pendiente de moderación (ajuste) ────────────────── */

add_filter( 'pre_comment_approved', function ( $approved, $commentdata ) {
	if ( 1 !== $approved && '1' !== $approved ) {
		return $approved;
	}
	if ( ! pa_postcompra_ajuste( 'moderar_fotos' ) || ! plr_hay_foto() || current_user_can( 'moderate_comments' ) ) {
		return $approved;
	}
	$pid = isset( $commentdata['comment_post_ID'] ) ? (int) $commentdata['comment_post_ID'] : 0;
	return ( $pid && 'product' === get_post_type( $pid ) ) ? 0 : $approved;
}, 20, 2 );

/* ── 4. Procesar la foto: validar, reencodar con GD sin metadatos, guardar ── */

/** Crea la carpeta con index.php y .htaccess (sin listado ni scripts). */
function plr_preparar_carpeta( $dir ) {
	if ( ! wp_mkdir_p( $dir ) ) {
		return false;
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		$reglas = "# Woo Postcompra: solo imágenes, nada ejecutable.\n"
			. "<FilesMatch \"\\.(?i:php\\d*|phtml|phar|pl|py|cgi|sh|shtml|html?|svg|js)$\">\n"
			. "  <IfModule mod_authz_core.c>\n    Require all denied\n  </IfModule>\n"
			. "  <IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n  </IfModule>\n"
			. "</FilesMatch>\n"
			. "<IfModule mod_headers.c>\n  Header set X-Content-Type-Options nosniff\n</IfModule>\n";
		@file_put_contents( $dir . '/.htaccess', $reglas ); // phpcs:ignore
	}
	return true;
}

/** ¿Quedan metadatos (EXIF, XMP, IPTC) en el archivo? Busca sus firmas en los bytes. */
function plr_tiene_metadatos( $path ) {
	$bytes = (string) @file_get_contents( $path ); // phpcs:ignore
	if ( '' === $bytes ) {
		return true;
	}
	foreach ( array( "Exif\0\0", 'EXIF', 'XMP ', 'http://ns.adobe.com/xap', 'Photoshop 3.0' ) as $firma ) {
		if ( false !== strpos( $bytes, $firma ) ) {
			return true;
		}
	}
	return false;
}

add_action( 'comment_post', 'plr_guardar_foto', 10, 3 );
function plr_guardar_foto( $comment_id, $approved, $commentdata ) {
	if ( ! plr_hay_foto() || 'spam' === $approved || 'trash' === $approved ) {
		return;
	}
	$comment = get_comment( $comment_id );
	if ( ! $comment || 'product' !== get_post_type( $comment->comment_post_ID ) || (int) $comment->comment_parent ) {
		return;
	}
	// La foto, siempre solo de quien tiene sesión y compró (aunque el ajuste de
	// reseñas esté apagado). Un invitado no sube foto: su correo no prueba nada.
	$uid = (int) $comment->user_id;
	if ( $uid <= 0 || get_current_user_id() !== $uid ) {
		return;
	}
	if ( ! user_can( $uid, 'moderate_comments' ) && ! plr_compro( $comment->comment_post_ID, $uid, '' ) ) {
		return;
	}

	$tmp = (string) $_FILES['plr_photo']['tmp_name']; // phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput
	if ( ! is_uploaded_file( $tmp ) ) {
		return;
	}
	$max = max( 1, (int) pa_postcompra_ajuste( 'foto_mb' ) ) * MB_IN_BYTES;
	$tam = (int) @filesize( $tmp ); // phpcs:ignore
	if ( $tam <= 0 || $tam > $max ) {
		return;
	}
	$permitidos = array( 'image/jpeg', 'image/png', 'image/webp' );
	$info       = @getimagesize( $tmp ); // phpcs:ignore
	if ( ! $info || empty( $info['mime'] ) || ! in_array( $info['mime'], $permitidos, true ) ) {
		return;
	}
	// Bombas de descompresión: límite de dimensiones.
	if ( $info[0] < 1 || $info[1] < 1 || $info[0] > 8000 || $info[1] > 8000 || ( $info[0] * $info[1] ) > 40000000 ) {
		return;
	}
	if ( function_exists( 'finfo_open' ) ) {
		$fi   = finfo_open( FILEINFO_MIME_TYPE );
		$real = $fi ? finfo_file( $fi, $tmp ) : '';
		if ( $fi ) {
			finfo_close( $fi );
		}
		if ( $real && ! in_array( $real, $permitidos, true ) ) {
			return;
		}
	}

	// GD obligatorio: reencodar desde píxeles elimina EXIF/GPS y cualquier carga.
	if ( ! class_exists( 'WP_Image_Editor_GD' ) ) {
		require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
		require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';
	}
	$solo_gd = function () {
		return array( 'WP_Image_Editor_GD' );
	};
	add_filter( 'wp_image_editors', $solo_gd, 999 );
	$editor = wp_get_image_editor( $tmp, array( 'mime_type' => $info['mime'] ) );
	remove_filter( 'wp_image_editors', $solo_gd, 999 );
	if ( is_wp_error( $editor ) || ! $editor instanceof WP_Image_Editor_GD ) {
		return;
	}
	if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
		$editor->maybe_exif_rotate(); // Respeta la orientación del móvil antes de perder el EXIF.
	}
	$editor->resize( 800, 800, false ); // Cabe en 800 px, sin recortar (si es menor, se deja).
	$editor->set_quality( 80 );

	$up = wp_upload_dir();
	if ( ! empty( $up['error'] ) ) {
		return;
	}
	$dir = trailingslashit( $up['basedir'] ) . plr_carpeta();
	if ( ! plr_preparar_carpeta( $dir ) ) {
		return;
	}
	$base   = 'resena-' . (int) $comment_id . '-' . strtolower( wp_generate_password( 12, false ) );
	$formato = WP_Image_Editor_GD::supports_mime_type( 'image/webp' ) ? array( 'webp', 'image/webp' ) : array( 'jpg', 'image/jpeg' );
	$saved  = $editor->save( $dir . '/' . $base . '.' . $formato[0], $formato[1] );
	if ( is_wp_error( $saved ) || empty( $saved['file'] ) ) {
		return;
	}
	// Comprobación final (falla cerrado): si quedara algún bloque EXIF/XMP/IPTC, se borra.
	if ( plr_tiene_metadatos( $saved['path'] ) ) {
		wp_delete_file( $saved['path'] );
		return;
	}
	// Se guarda solo el nombre del archivo; la URL se arma al mostrarla.
	update_comment_meta( $comment_id, 'plr_photo', sanitize_file_name( $saved['file'] ) );
}

/** URL pública de la foto de una reseña ('' si no tiene). */
function plr_photo_url( $comment_id ) {
	$valor = (string) get_comment_meta( $comment_id, 'plr_photo', true );
	if ( '' === $valor ) {
		return '';
	}
	if ( preg_match( '#^https?://#i', $valor ) ) {
		return $valor; // Compatibilidad con versiones que guardaban la URL completa.
	}
	$up = wp_upload_dir();
	return trailingslashit( $up['baseurl'] ) . plr_carpeta() . '/' . rawurlencode( basename( $valor ) );
}

/* ── 5. Mostrar la foto dentro de la reseña (solo aprobadas) ──────────────── */

add_filter( 'comment_text', function ( $text, $comment = null ) {
	if ( is_admin() || ! $comment instanceof WP_Comment || '1' !== (string) $comment->comment_approved ) {
		return $text;
	}
	if ( 'product' !== get_post_type( $comment->comment_post_ID ) ) {
		return $text;
	}
	$url = plr_photo_url( $comment->comment_ID );
	if ( $url ) {
		$text .= '<a class="plr-photo-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">'
			. '<img class="plr-photo" src="' . esc_url( $url ) . '" alt="' . esc_attr__( 'Foto de la reseña', 'woo-postcompra' ) . '" loading="lazy"></a>';
	}
	return $text;
}, 20, 2 );

/** Borrar la foto del disco al borrar la reseña. */
add_action( 'delete_comment', function ( $comment_id ) {
	$valor = (string) get_comment_meta( $comment_id, 'plr_photo', true );
	if ( '' === $valor ) {
		return;
	}
	$nombre = sanitize_file_name( basename( (string) wp_parse_url( $valor, PHP_URL_PATH ) ) );
	if ( '' === $nombre || ! preg_match( '/^resena-\d+-[A-Za-z0-9-]+\.(webp|jpg)$/', $nombre ) ) {
		return;
	}
	$up   = wp_upload_dir();
	$ruta = trailingslashit( $up['basedir'] ) . plr_carpeta() . '/' . $nombre;
	if ( file_exists( $ruta ) ) {
		wp_delete_file( $ruta );
	}
} );

/* ── 6. Recordatorio de reseñas (sin correos) ─────────────────────────────── */

/** ¿El usuario (o su email) ya reseñó este producto? */
function plr_user_reviewed( $product_id, $user_id, $email ) {
	$args = array(
		'post_id' => $product_id,
		'type'    => 'review',
		'count'   => true,
		'status'  => 'all',
	);
	if ( $user_id ) {
		$args['user_id'] = $user_id;
	} elseif ( $email ) {
		$args['author_email'] = $email;
	} else {
		return false;
	}
	return get_comments( $args ) > 0;
}

/** Productos del pedido que el cliente aún puede reseñar (máx. $limit). */
function plr_pending_reviews_for_order( $order, $limit = 6 ) {
	$out     = array();
	$user_id = $order->get_user_id();
	$email   = $order->get_billing_email();
	$cafe    = function_exists( 'pcaf_producto_id' ) ? pcaf_producto_id() : 0;
	foreach ( $order->get_items() as $item ) {
		$pid = $item->get_product_id();
		if ( ! $pid || isset( $out[ $pid ] ) || $pid === $cafe ) {
			continue;
		}
		if ( 'product' !== get_post_type( $pid ) || 'publish' !== get_post_status( $pid ) || ! comments_open( $pid ) ) {
			continue;
		}
		if ( plr_user_reviewed( $pid, $user_id, $email ) ) {
			continue;
		}
		$out[ $pid ] = get_the_title( $pid );
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

function plr_nudge_html( $pending, $titulo, $intro ) {
	if ( empty( $pending ) ) {
		return '';
	}
	$html  = '<div class="plr-nudge">';
	$html .= '<p class="plr-nudge__titulo">' . esc_html( $titulo ) . '</p>';
	$html .= '<p class="plr-nudge__intro">' . esc_html( $intro ) . '</p>';
	$html .= '<div class="plr-nudge__lista">';
	foreach ( $pending as $pid => $name ) {
		$html .= '<a class="plr-nudge__btn" href="' . esc_url( get_permalink( $pid ) . '#reviews' ) . '">⭐ '
			/* translators: %s: nombre del producto. */
			. esc_html( sprintf( __( 'Reseñar: %s', 'woo-postcompra' ), wp_html_excerpt( $name, 38, '…' ) ) ) . '</a>';
	}
	return $html . '</div></div>';
}

/** Recordatorio para un pedido, solo si la visita puede ver ese pedido. */
function plr_nudge_pedido( $order ) {
	if ( ! $order instanceof WC_Order || ! pa_postcompra_puede_ver_pedido( $order ) ) {
		return '';
	}
	return plr_nudge_html(
		plr_pending_reviews_for_order( $order, 6 ),
		pa_postcompra_texto( 'resenas_titulo_gracias' ),
		pa_postcompra_texto( 'resenas_intro_gracias' )
	);
}

function plr_nudge_ya_puesto( $marcar = false ) {
	static $puesto = false;
	if ( $marcar ) {
		$puesto = true;
	}
	return $puesto;
}

// (a) Pedido recibido con bloques: tras los totales (y tras la tarjeta del café).
add_filter( 'render_block_woocommerce/order-confirmation-totals-wrapper', function ( $html ) {
	if ( '' === trim( (string) $html ) || plr_nudge_ya_puesto() ) {
		return $html;
	}
	$pedido = pa_postcompra_pedido_actual();
	if ( ! $pedido ) {
		return $html;
	}
	plr_nudge_ya_puesto( true );
	return $html . plr_nudge_pedido( $pedido );
}, 11 );

// (b) Pedido recibido clásico.
add_action( 'woocommerce_thankyou', function ( $order_id ) {
	if ( plr_nudge_ya_puesto() ) {
		return;
	}
	plr_nudge_ya_puesto( true );
	echo plr_nudge_pedido( wc_get_order( $order_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapado en plr_nudge_html().
}, 26 );

// (c) Panel de «Mi cuenta»: lo comprado y no reseñado del usuario actual.
add_action( 'woocommerce_account_dashboard', function () {
	$customer_id = get_current_user_id();
	if ( ! $customer_id ) {
		return;
	}
	$orders  = wc_get_orders( array(
		'customer_id' => $customer_id,
		'status'      => array( 'wc-completed', 'wc-processing' ),
		'limit'       => 10,
		'orderby'     => 'date',
		'order'       => 'DESC',
	) );
	$pending = array();
	foreach ( $orders as $order ) {
		foreach ( plr_pending_reviews_for_order( $order, 6 ) as $pid => $name ) {
			$pending[ $pid ] = $name;
		}
		if ( count( $pending ) >= 4 ) {
			break;
		}
	}
	echo plr_nudge_html( // phpcs:ignore WordPress.Security.EscapeOutput -- escapado en plr_nudge_html().
		array_slice( $pending, 0, 4, true ),
		pa_postcompra_texto( 'resenas_titulo_cuenta' ),
		pa_postcompra_texto( 'resenas_intro_cuenta' )
	);
} );

add_action( 'wp_enqueue_scripts', function () {
	$es_producto = function_exists( 'is_product' ) && is_product();
	$es_gracias  = function_exists( 'is_order_received_page' ) && is_order_received_page();
	$es_cuenta   = function_exists( 'is_account_page' ) && is_account_page();
	if ( $es_producto || $es_gracias || $es_cuenta ) {
		wp_enqueue_style( 'plr', PA_POSTCOMPRA_URL . 'assets/resenas.css', array(), PA_POSTCOMPRA_VERSION );
		wp_add_inline_style( 'plr', pa_postcompra_css_variables() );
	}
} );

/* ── 7. Admin: columna que avisa qué reseñas traen foto ───────────────────── */

add_filter( 'manage_edit-comments_columns', function ( $cols ) {
	$cols['plr_photo'] = '📸';
	return $cols;
} );
add_action( 'manage_comments_custom_column', function ( $col, $cid ) {
	if ( 'plr_photo' !== $col ) {
		return;
	}
	$url = plr_photo_url( $cid );
	if ( $url ) {
		echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">✅</a>';
	}
}, 10, 2 );
