<?php
/**
 * Módulo «Invítanos un café»: propina voluntaria como producto de WooCommerce.
 * Apagar: update_option( 'pa_postcompra_cafe_off', 1 ) (quita el módulo) o
 * update_option( 'pcaf_desactivado', 1 ) (oculta la tarjeta, deja el producto).
 *
 * @package woo-postcompra
 */

defined( 'ABSPATH' ) || exit;

define( 'PCAF_SLUG', 'invitanos-un-cafe' );

/* ==========================================================================
 * Ajustes del monto
 * ========================================================================== */

function pcaf_minimo() {
	return max( 1, (int) pa_postcompra_ajuste( 'cafe_minimo' ) );
}

function pcaf_maximo() {
	return max( pcaf_minimo(), (int) pa_postcompra_ajuste( 'cafe_maximo' ) );
}

/* ==========================================================================
 * El producto
 * ========================================================================== */

/**
 * El café es un producto de WooCommerce de verdad: entra en caja, informes y
 * facturación como cualquier otra venta. Va oculto del catálogo.
 */
function pcaf_producto_id() {
	$id = (int) get_option( 'pcaf_producto_id' );
	if ( $id && 'product' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
		return $id;
	}
	$existente = get_page_by_path( PCAF_SLUG, OBJECT, 'product' );
	if ( $existente && 'publish' === $existente->post_status ) {
		update_option( 'pcaf_producto_id', $existente->ID, false );
		return (int) $existente->ID;
	}
	return 0;
}

/** Aplica la clase/estado de impuesto elegidos en ajustes al producto. */
function pcaf_aplicar_impuesto( $producto ) {
	$clase = (string) pa_postcompra_ajuste( 'cafe_clase_impuesto' );
	if ( '__ninguno' === $clase ) {
		$producto->set_tax_status( 'none' );
		$producto->set_tax_class( '' );
	} else {
		$producto->set_tax_status( 'taxable' );
		$producto->set_tax_class( $clase );
	}
}

/**
 * Crea el producto una sola vez (botón en WooCommerce → Postcompra o
 * `wp eval "pcaf_crear_producto();"`).
 */
function pcaf_crear_producto() {
	if ( pcaf_producto_id() ) {
		return pcaf_producto_id();
	}
	$p = new WC_Product_Simple();
	$p->set_name( pa_postcompra_texto( 'cafe_nombre' ) );
	$p->set_slug( PCAF_SLUG );
	$p->set_status( 'publish' );
	$p->set_catalog_visibility( 'hidden' );   // Ni en tienda ni en búsqueda.
	$p->set_virtual( true );                  // Sin envío.
	$p->set_sold_individually( true );        // Un café por carrito.
	$p->set_regular_price( (string) pcaf_minimo() );
	// Revisa la clase de impuesto con tu contador: si el café paga impuesto,
	// la persona termina pagando más de lo que eligió.
	pcaf_aplicar_impuesto( $p );
	$id = $p->save();
	update_option( 'pcaf_producto_id', $id, false );
	return $id;
}

/* ==========================================================================
 * El monto lo elige quien invita (validado SIEMPRE en el servidor)
 * ========================================================================== */

/**
 * Convierte la entrada en un entero entre el mínimo y el máximo.
 * Negativos, vacíos o basura → mínimo.
 *
 * @param mixed $valor Monto recibido.
 * @return int
 */
function pcaf_sanear_monto( $valor ) {
	$bruto = trim( (string) $valor );
	if ( '' === $bruto || '-' === $bruto[0] ) {
		return pcaf_minimo();
	}
	if ( preg_match( '/^\d+([.,]\d{1,2})?$/', $bruto ) ) {
		// Entero o con 1-2 decimales: se redondea hacia abajo.
		$n = (int) floor( (float) str_replace( ',', '.', $bruto ) );
	} else {
		// Separadores de miles («5.000», «5,000») u otros caracteres: solo dígitos.
		$n = (int) preg_replace( '/[^0-9]/', '', substr( $bruto, 0, 15 ) );
	}
	return min( pcaf_maximo(), max( pcaf_minimo(), $n ) );
}

add_filter( 'woocommerce_add_cart_item_data', 'pcaf_guardar_monto', 10, 3 );
function pcaf_guardar_monto( $datos, $producto_id, $variacion_id ) {
	if ( (int) $producto_id !== pcaf_producto_id() ) {
		return $datos;
	}
	// phpcs:ignore WordPress.Security.NonceVerification -- add-to-cart por enlace, igual que el de WooCommerce.
	$monto               = isset( $_REQUEST['pcaf_monto'] ) ? pcaf_sanear_monto( wp_unslash( $_REQUEST['pcaf_monto'] ) ) : pcaf_minimo();
	$datos['pcaf_monto'] = $monto;
	$datos['pcaf_unico'] = $monto . '-' . wp_generate_password( 6, false );
	return $datos;
}

add_action( 'woocommerce_before_calculate_totals', 'pcaf_aplicar_monto', 20 );
function pcaf_aplicar_monto( $carrito ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}
	foreach ( $carrito->get_cart() as $item ) {
		if ( ! empty( $item['pcaf_monto'] ) && isset( $item['data'] ) ) {
			$item['data']->set_price( (string) pcaf_sanear_monto( $item['pcaf_monto'] ) );
		}
	}
}

add_filter( 'woocommerce_get_item_data', 'pcaf_mostrar_monto', 10, 2 );
function pcaf_mostrar_monto( $datos, $item ) {
	if ( ! empty( $item['pcaf_monto'] ) ) {
		$datos[] = array(
			'key'   => __( 'Invitación', 'woo-postcompra' ),
			'value' => pa_postcompra_precio( (int) $item['pcaf_monto'] ),
		);
	}
	return $datos;
}

add_action( 'woocommerce_checkout_create_order_line_item', 'pcaf_guardar_en_pedido', 10, 4 );
function pcaf_guardar_en_pedido( $linea, $clave, $valores, $pedido ) {
	if ( ! empty( $valores['pcaf_monto'] ) ) {
		$linea->add_meta_data( __( 'Invitación', 'woo-postcompra' ), pa_postcompra_precio( (int) $valores['pcaf_monto'] ), true );
		$linea->add_meta_data( '_pcaf_monto', (int) $valores['pcaf_monto'], true );
	}
}

/* ==========================================================================
 * La tarjeta
 * ========================================================================== */

/** Montos sugeridos (ajuste «cafe_montos», filtrable con pcaf_montos). */
function pcaf_montos() {
	$lista = array();
	foreach ( preg_split( '/[\s,;]+/', (string) pa_postcompra_ajuste( 'cafe_montos' ) ) as $m ) {
		$m = (int) $m;
		if ( $m >= pcaf_minimo() && $m <= pcaf_maximo() ) {
			$lista[] = $m;
		}
	}
	$lista = array_values( array_unique( $lista ) );
	return array_map( 'intval', (array) apply_filters( 'pcaf_montos', $lista ? $lista : array( pcaf_minimo() ) ) );
}

function pcaf_monto_sugerido() {
	return pcaf_sanear_monto( apply_filters( 'pcaf_monto_sugerido', (int) pa_postcompra_ajuste( 'cafe_sugerido' ) ) );
}

/**
 * La tarjeta. $contexto: «compra» (tras comprar) o «lectura» (contenido gratis).
 *
 * @param string $contexto Contexto.
 * @return string HTML.
 */
function pcaf_tarjeta( $contexto = 'compra' ) {
	$id = pcaf_producto_id();
	if ( ! $id ) {
		return '';
	}
	if ( 'lectura' === $contexto ) {
		$titulo = pa_postcompra_texto( 'cafe_titulo_lectura' );
		$sub    = pa_postcompra_texto( 'cafe_sub_lectura' );
	} else {
		$titulo = pa_postcompra_texto( 'cafe_titulo_compra' );
		$sub    = pa_postcompra_texto( 'cafe_sub_compra' );
	}
	$pie      = pa_postcompra_texto( 'cafe_pie' );
	$sugerido = pcaf_monto_sugerido();
	$paso     = max( 1, (int) pa_postcompra_ajuste( 'cafe_paso' ) );

	ob_start();
	?>
	<div class="pcaf-cafe" data-pcaf-min="<?php echo (int) pcaf_minimo(); ?>" data-pcaf-max="<?php echo (int) pcaf_maximo(); ?>">
		<div class="pcaf-cafe__taza" aria-hidden="true">
			<svg viewBox="0 0 64 64" width="52" height="52" focusable="false">
				<path class="pcaf-vapor" d="M24 16c0-4 4-4 4-8s-4-4-4-8" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" opacity=".45"/>
				<path class="pcaf-vapor pcaf-vapor--2" d="M34 16c0-4 4-4 4-8s-4-4-4-8" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" opacity=".3"/>
				<path d="M10 24h36v14a14 14 0 0 1-14 14h-8a14 14 0 0 1-14-14V24z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>
				<path d="M46 28h5a7 7 0 0 1 0 14h-5" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>
			</svg>
		</div>
		<h3 class="pcaf-cafe__titulo"><?php echo esc_html( $titulo ); ?></h3>
		<p class="pcaf-cafe__sub"><?php echo esc_html( $sub ); ?></p>

		<div class="pcaf-cafe__montos" role="group" aria-label="<?php esc_attr_e( 'Elige cuánto invitar', 'woo-postcompra' ); ?>">
			<?php foreach ( pcaf_montos() as $m ) : ?>
				<button type="button" class="pcaf-monto<?php echo ( $m === $sugerido ) ? ' is-on' : ''; ?>" data-pcaf-monto="<?php echo (int) $m; ?>">
					<?php echo esc_html( pa_postcompra_precio( $m ) ); ?>
				</button>
			<?php endforeach; ?>
			<button type="button" class="pcaf-monto pcaf-monto--otro" data-pcaf-otro="1"><?php esc_html_e( 'Otro', 'woo-postcompra' ); ?></button>
			<label class="pcaf-otro-caja" hidden>
				<span class="screen-reader-text"><?php esc_html_e( 'Cuánto quieres invitar', 'woo-postcompra' ); ?></span>
				<input type="number" class="pcaf-otro-input" min="<?php echo (int) pcaf_minimo(); ?>" max="<?php echo (int) pcaf_maximo(); ?>" step="<?php echo (int) $paso; ?>" inputmode="numeric" placeholder="<?php echo (int) pcaf_minimo(); ?>">
			</label>
		</div>

		<a class="pcaf-cafe__btn" href="<?php echo esc_url( add_query_arg( array( 'add-to-cart' => $id, 'pcaf_monto' => $sugerido ), wc_get_checkout_url() ) ); ?>">
			<?php esc_html_e( 'Invitar un café', 'woo-postcompra' ); ?> ☕
		</a>
		<?php if ( '' !== trim( $pie ) ) : ?>
			<p class="pcaf-cafe__pie"><?php echo esc_html( $pie ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ==========================================================================
 * Dónde aparece: tras los totales del pedido (bloques) o en woocommerce_thankyou
 * (plantilla clásica). Lo que salga primero; nunca dos veces.
 * ========================================================================== */

/** HTML para un pedido: tarjeta, o agradecimiento si el pedido ya era un café. */
function pcaf_gracias_html( $pedido_id ) {
	$pedido = wc_get_order( $pedido_id );
	if ( ! $pedido || ! pa_postcompra_puede_ver_pedido( $pedido ) ) {
		return '';
	}
	foreach ( $pedido->get_items() as $linea ) {
		if ( (int) $linea->get_product_id() === pcaf_producto_id() ) {
			return '<p class="pcaf-gracias-cafe">' . esc_html( pa_postcompra_texto( 'cafe_gracias' ) ) . '</p>';
		}
	}
	return pcaf_tarjeta( 'compra' );
}

/** Marca que la tarjeta ya se pintó en esta petición. */
function pcaf_ya_puesta( $marcar = false ) {
	static $puesta = false;
	if ( $marcar ) {
		$puesta = true;
	}
	return $puesta;
}

add_filter( 'render_block_woocommerce/order-confirmation-totals-wrapper', 'pcaf_tras_totales', 10, 1 );
function pcaf_tras_totales( $html ) {
	// Si WooCommerce no pintó los totales (visita sin permiso), no se añade nada.
	if ( '' === trim( (string) $html ) || pcaf_ya_puesta() || get_option( 'pcaf_desactivado' ) ) {
		return $html;
	}
	$pedido = pa_postcompra_pedido_actual();
	if ( ! $pedido ) {
		return $html;
	}
	pcaf_ya_puesta( true );
	return $html . pcaf_gracias_html( $pedido->get_id() );
}

add_action( 'woocommerce_thankyou', 'pcaf_en_gracias_clasico', 25 );
function pcaf_en_gracias_clasico( $pedido_id ) {
	if ( pcaf_ya_puesta() || get_option( 'pcaf_desactivado' ) ) {
		return;
	}
	pcaf_ya_puesta( true );
	echo pcaf_gracias_html( $pedido_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML escapado en pcaf_tarjeta().
}

/** [invitanos_un_cafe contexto="compra|lectura"] */
add_shortcode( 'invitanos_un_cafe', function ( $atts ) {
	if ( get_option( 'pcaf_desactivado' ) ) {
		return '';
	}
	$a = shortcode_atts( array( 'contexto' => 'compra' ), $atts, 'invitanos_un_cafe' );
	return pcaf_tarjeta( 'lectura' === $a['contexto'] ? 'lectura' : 'compra' );
} );

/* ==========================================================================
 * Estilos y comportamiento (solo donde puede aparecer)
 * ========================================================================== */

add_action( 'wp_enqueue_scripts', 'pcaf_assets' );
function pcaf_assets() {
	if ( get_option( 'pcaf_desactivado' ) ) {
		return;
	}
	if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
		global $post;
		if ( ! ( $post instanceof WP_Post ) || ! has_shortcode( (string) $post->post_content, 'invitanos_un_cafe' ) ) {
			return;
		}
	}
	wp_enqueue_style( 'pcaf-cafe', PA_POSTCOMPRA_URL . 'assets/cafe.css', array(), PA_POSTCOMPRA_VERSION );
	wp_add_inline_style( 'pcaf-cafe', pa_postcompra_css_variables() );
	wp_enqueue_script( 'pcaf-cafe', PA_POSTCOMPRA_URL . 'assets/cafe.js', array(), PA_POSTCOMPRA_VERSION, true );
}
