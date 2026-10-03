<?php
/**
 * Utilidades comunes: ajustes con valores por defecto, acceso al pedido en
 * «pedido recibido» y variables CSS.
 *
 * @package woo-postcompra
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valores por defecto de todos los ajustes (opción única `pa_postcompra_ajustes`).
 * Los textos vacíos usan el texto por defecto traducible de pa_postcompra_texto().
 *
 * @return array
 */
function pa_postcompra_defaults() {
	return array(
		// Café / propina.
		'cafe_minimo'         => 1,
		'cafe_maximo'         => 1000,
		'cafe_montos'         => '2, 5, 10, 20',
		'cafe_sugerido'       => 5,
		'cafe_paso'           => 1,
		'cafe_clase_impuesto' => '',   // '' = estándar; '__ninguno' = sin impuesto; o el slug de una clase.
		'cafe_nombre'         => '',
		'cafe_titulo_compra'  => '',
		'cafe_sub_compra'     => '',
		'cafe_titulo_lectura' => '',
		'cafe_sub_lectura'    => '',
		'cafe_pie'            => '',   // Nota bajo el botón (p. ej. medios de pago). Vacío = no se muestra.
		'cafe_gracias'        => '',
		// Reseñas.
		'solo_compradores'    => 1,    // Solo quien compró el producto puede reseñarlo.
		'moderar_fotos'       => 1,    // Reseñas con foto quedan pendientes de aprobación.
		'foto_mb'             => 5,
		'resenas_titulo_gracias' => '',
		'resenas_intro_gracias'  => '',
		'resenas_titulo_cuenta'  => '',
		'resenas_intro_cuenta'   => '',
		'resenas_etiqueta_foto'  => '',
		// Aspecto.
		'color_acento'        => '',   // Vacío = #2271b1.
		'color_fondo'         => '',   // Vacío = #ffffff.
	);
}

/**
 * Lee un ajuste.
 *
 * @param string $clave Clave de pa_postcompra_defaults().
 * @return mixed
 */
function pa_postcompra_ajuste( $clave ) {
	static $cache = null;
	if ( null === $cache ) {
		$guardado = get_option( 'pa_postcompra_ajustes', array() );
		$cache    = wp_parse_args( is_array( $guardado ) ? $guardado : array(), pa_postcompra_defaults() );
	}
	return isset( $cache[ $clave ] ) ? $cache[ $clave ] : null;
}

/**
 * Textos por defecto (traducibles). Si el ajuste tiene texto, manda el ajuste.
 *
 * @param string $clave Clave del texto.
 * @return string
 */
function pa_postcompra_texto( $clave ) {
	$valor = (string) pa_postcompra_ajuste( $clave );
	if ( '' !== trim( $valor ) ) {
		return $valor;
	}
	return pa_postcompra_texto_por_defecto( $clave );
}

/**
 * Texto por defecto de una clave (traducible), sin mirar el ajuste guardado.
 *
 * @param string $clave Clave del texto.
 * @return string
 */
function pa_postcompra_texto_por_defecto( $clave ) {
	switch ( $clave ) {
		case 'cafe_nombre':
			/* translators: %s: nombre del sitio. */
			return sprintf( __( 'Un café para %s', 'woo-postcompra' ), get_bloginfo( 'name' ) );
		case 'cafe_titulo_compra':
			return __( 'Gracias por tu compra.', 'woo-postcompra' );
		case 'cafe_sub_compra':
			return __( 'Si quieres apoyarnos un poco más, puedes invitarnos un café.', 'woo-postcompra' );
		case 'cafe_titulo_lectura':
			return __( 'Este contenido es gratis y va a seguir siéndolo.', 'woo-postcompra' );
		case 'cafe_sub_lectura':
			return __( 'Si te sirvió, puedes invitarnos un café. No hace falta.', 'woo-postcompra' );
		case 'cafe_gracias':
			return __( 'Y gracias por el café ☕', 'woo-postcompra' );
		case 'resenas_titulo_gracias':
			return __( 'Cuando lo pruebes, cuéntanos qué te pareció', 'woo-postcompra' );
		case 'resenas_intro_gracias':
			return __( 'Tu reseña (puedes subir una foto) ayuda a otros clientes a decidir.', 'woo-postcompra' );
		case 'resenas_titulo_cuenta':
			return __( '¿Ya probaste lo que compraste?', 'woo-postcompra' );
		case 'resenas_intro_cuenta':
			return __( 'Deja tu reseña y ayuda a otros clientes.', 'woo-postcompra' );
		case 'resenas_etiqueta_foto':
			return __( 'Sube una foto de tu producto (opcional)', 'woo-postcompra' );
	}
	return '';
}

/**
 * ¿Puede la visita actual ver este pedido en «pedido recibido»?
 * Exige la clave del pedido en la URL y, si el pedido es de un usuario, que
 * sea ese usuario. Evita mostrar datos de pedidos ajenos cambiando el número.
 *
 * @param WC_Order $pedido Pedido.
 * @return bool
 */
function pa_postcompra_puede_ver_pedido( $pedido ) {
	if ( ! $pedido instanceof WC_Order ) {
		return false;
	}
	$clave = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( '' === $clave || ! hash_equals( (string) $pedido->get_order_key(), (string) $clave ) ) {
		return false;
	}
	$dueno = (int) $pedido->get_user_id();
	if ( $dueno && get_current_user_id() !== $dueno ) {
		return false;
	}
	return true;
}

/** Pedido de la página «pedido recibido» actual, si la visita puede verlo. */
function pa_postcompra_pedido_actual() {
	if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
		return null;
	}
	$id     = absint( get_query_var( 'order-received' ) );
	$pedido = $id ? wc_get_order( $id ) : null;
	return ( $pedido && pa_postcompra_puede_ver_pedido( $pedido ) ) ? $pedido : null;
}

/** Sanea un color hexadecimal; vacío si no es válido. */
function pa_postcompra_color( $clave, $por_defecto ) {
	$c = sanitize_hex_color( (string) pa_postcompra_ajuste( $clave ) );
	return $c ? $c : $por_defecto;
}

/** Variables CSS comunes a los dos módulos. */
function pa_postcompra_css_variables() {
	return ':root{--pa-postcompra-acento:' . pa_postcompra_color( 'color_acento', '#2271b1' )
		. ';--pa-postcompra-fondo:' . pa_postcompra_color( 'color_fondo', '#ffffff' ) . '}';
}

/** Formatea un monto con la moneda de la tienda (texto plano). */
function pa_postcompra_precio( $monto ) {
	return html_entity_decode( wp_strip_all_tags( wc_price( $monto ) ), ENT_QUOTES, 'UTF-8' );
}
