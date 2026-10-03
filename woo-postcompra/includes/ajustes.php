<?php
/**
 * Página de ajustes: WooCommerce → Postcompra.
 * Todo se guarda en la opción `pa_postcompra_ajustes` (array).
 *
 * @package woo-postcompra
 */

defined( 'ABSPATH' ) || exit;

/** Campos de la página: clave => [etiqueta, tipo, ayuda]. */
function pa_postcompra_campos() {
	return array(
		'cafe'    => array(
			'titulo' => __( 'Invítanos un café (propina)', 'woo-postcompra' ),
			'campos' => array(
				'cafe_minimo'         => array( __( 'Monto mínimo', 'woo-postcompra' ), 'number', __( 'En la moneda de la tienda (WooCommerce → Ajustes → General). Enteros.', 'woo-postcompra' ) ),
				'cafe_maximo'         => array( __( 'Monto máximo', 'woo-postcompra' ), 'number', __( 'Tope de cordura contra un cero de más.', 'woo-postcompra' ) ),
				'cafe_montos'         => array( __( 'Montos sugeridos', 'woo-postcompra' ), 'text', __( 'Separados por comas, p. ej. 2, 5, 10, 20.', 'woo-postcompra' ) ),
				'cafe_sugerido'       => array( __( 'Monto marcado por defecto', 'woo-postcompra' ), 'number', '' ),
				'cafe_paso'           => array( __( 'Paso del campo «Otro»', 'woo-postcompra' ), 'number', '' ),
				'cafe_clase_impuesto' => array( __( 'Impuesto del producto', 'woo-postcompra' ), 'impuesto', __( 'Consúltalo con tu contador. Se aplica al producto al guardar.', 'woo-postcompra' ) ),
				'cafe_nombre'         => array( __( 'Nombre del producto', 'woo-postcompra' ), 'text', __( 'Vacío: «Un café para» + nombre del sitio. Se usa al crear el producto.', 'woo-postcompra' ) ),
				'cafe_titulo_compra'  => array( __( 'Título tras la compra', 'woo-postcompra' ), 'text', '' ),
				'cafe_sub_compra'     => array( __( 'Texto tras la compra', 'woo-postcompra' ), 'textarea', '' ),
				'cafe_titulo_lectura' => array( __( 'Título en contexto «lectura»', 'woo-postcompra' ), 'text', __( 'Para [invitanos_un_cafe contexto="lectura"].', 'woo-postcompra' ) ),
				'cafe_sub_lectura'    => array( __( 'Texto en contexto «lectura»', 'woo-postcompra' ), 'textarea', '' ),
				'cafe_pie'            => array( __( 'Nota bajo el botón', 'woo-postcompra' ), 'text', __( 'Opcional, p. ej. los medios de pago que aceptas. Vacío: no se muestra.', 'woo-postcompra' ) ),
				'cafe_gracias'        => array( __( 'Agradecimiento si el pedido ya era un café', 'woo-postcompra' ), 'text', '' ),
			),
		),
		'resenas' => array(
			'titulo' => __( 'Reseñas con foto', 'woo-postcompra' ),
			'campos' => array(
				'solo_compradores'       => array( __( 'Solo compradores reseñan', 'woo-postcompra' ), 'checkbox', __( 'Rechaza en el servidor las reseñas de quien no compró el producto. La foto exige compra siempre.', 'woo-postcompra' ) ),
				'moderar_fotos'          => array( __( 'Moderar reseñas con foto', 'woo-postcompra' ), 'checkbox', __( 'Las reseñas con foto quedan pendientes hasta que las apruebes.', 'woo-postcompra' ) ),
				'foto_mb'                => array( __( 'Tamaño máximo de la foto (MB)', 'woo-postcompra' ), 'number', '' ),
				'resenas_etiqueta_foto'  => array( __( 'Etiqueta del campo de foto', 'woo-postcompra' ), 'text', '' ),
				'resenas_titulo_gracias' => array( __( 'Recordatorio tras la compra: título', 'woo-postcompra' ), 'text', '' ),
				'resenas_intro_gracias'  => array( __( 'Recordatorio tras la compra: texto', 'woo-postcompra' ), 'text', '' ),
				'resenas_titulo_cuenta'  => array( __( 'Recordatorio en «Mi cuenta»: título', 'woo-postcompra' ), 'text', '' ),
				'resenas_intro_cuenta'   => array( __( 'Recordatorio en «Mi cuenta»: texto', 'woo-postcompra' ), 'text', '' ),
			),
		),
		'aspecto' => array(
			'titulo' => __( 'Aspecto', 'woo-postcompra' ),
			'campos' => array(
				'color_acento' => array( __( 'Color de acento', 'woo-postcompra' ), 'color', __( 'Hexadecimal. Vacío: #2271b1.', 'woo-postcompra' ) ),
				'color_fondo'  => array( __( 'Fondo de la tarjeta', 'woo-postcompra' ), 'color', __( 'Hexadecimal. Vacío: #ffffff.', 'woo-postcompra' ) ),
			),
		),
	);
}

add_action( 'admin_menu', function () {
	add_submenu_page(
		'woocommerce',
		__( 'Postcompra', 'woo-postcompra' ),
		__( 'Postcompra', 'woo-postcompra' ),
		'manage_woocommerce',
		'woo-postcompra',
		'pa_postcompra_pagina'
	);
}, 60 );

add_action( 'admin_init', function () {
	register_setting(
		'pa_postcompra',
		'pa_postcompra_ajustes',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'pa_postcompra_sanear_ajustes',
			'default'           => array(),
		)
	);
} );

/** options.php exige manage_options por defecto; se abre a quien gestiona la tienda. */
add_filter( 'option_page_capability_pa_postcompra', function () {
	return 'manage_woocommerce';
} );

/**
 * Sanea todos los ajustes.
 *
 * @param mixed $entrada Lo enviado.
 * @return array
 */
function pa_postcompra_sanear_ajustes( $entrada ) {
	$entrada = is_array( $entrada ) ? $entrada : array();
	$def     = pa_postcompra_defaults();
	$salida  = array();
	foreach ( pa_postcompra_campos() as $grupo ) {
		foreach ( $grupo['campos'] as $clave => $campo ) {
			$v = isset( $entrada[ $clave ] ) ? wp_unslash( $entrada[ $clave ] ) : null;
			switch ( $campo[1] ) {
				case 'checkbox':
					$salida[ $clave ] = empty( $v ) ? 0 : 1;
					break;
				case 'number':
					$salida[ $clave ] = ( null === $v || '' === $v ) ? $def[ $clave ] : max( 0, absint( $v ) );
					break;
				case 'color':
					$c                = sanitize_hex_color( (string) $v );
					$salida[ $clave ] = $c ? $c : '';
					break;
				case 'impuesto':
					$v       = ( '__ninguno' === (string) $v ) ? '__ninguno' : sanitize_title( (string) $v );
					$validas = array_merge( array( '', '__ninguno' ), class_exists( 'WC_Tax' ) ? WC_Tax::get_tax_class_slugs() : array() );
					$salida[ $clave ] = in_array( $v, $validas, true ) ? $v : '';
					break;
				case 'textarea':
					$salida[ $clave ] = sanitize_textarea_field( (string) $v );
					break;
				default:
					$salida[ $clave ] = sanitize_text_field( (string) $v );
			}
		}
	}
	$salida['foto_mb']     = min( 20, max( 1, (int) $salida['foto_mb'] ) );
	$salida['cafe_minimo'] = max( 1, (int) $salida['cafe_minimo'] );
	$salida['cafe_maximo'] = max( $salida['cafe_minimo'], (int) $salida['cafe_maximo'] );
	$salida['cafe_paso']   = max( 1, (int) $salida['cafe_paso'] );
	return $salida;
}

/** Al guardar, el impuesto y el precio base del producto del café siguen a los ajustes. */
add_action( 'add_option_pa_postcompra_ajustes', 'pa_postcompra_sincronizar_producto' );
add_action( 'update_option_pa_postcompra_ajustes', 'pa_postcompra_sincronizar_producto' );
function pa_postcompra_sincronizar_producto() {
	if ( ! function_exists( 'pcaf_producto_id' ) || ! function_exists( 'wc_get_product' ) ) {
		return;
	}
	$id = pcaf_producto_id();
	$p  = $id ? wc_get_product( $id ) : null;
	if ( ! $p ) {
		return;
	}
	$guardado = get_option( 'pa_postcompra_ajustes', array() );
	$clase    = isset( $guardado['cafe_clase_impuesto'] ) ? (string) $guardado['cafe_clase_impuesto'] : '';
	if ( '__ninguno' === $clase ) {
		$p->set_tax_status( 'none' );
		$p->set_tax_class( '' );
	} else {
		$p->set_tax_status( 'taxable' );
		$p->set_tax_class( $clase );
	}
	if ( isset( $guardado['cafe_minimo'] ) ) {
		$p->set_regular_price( (string) max( 1, (int) $guardado['cafe_minimo'] ) );
	}
	$p->save();
}

/** Botón «Crear el producto del café». */
add_action( 'admin_post_pa_postcompra_crear_cafe', function () {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'No tienes permiso.', 'woo-postcompra' ), 403 );
	}
	check_admin_referer( 'pa_postcompra_crear_cafe' );
	$ok = function_exists( 'pcaf_crear_producto' ) ? (int) pcaf_crear_producto() : 0;
	wp_safe_redirect( add_query_arg( array( 'page' => 'woo-postcompra', 'cafe' => $ok ? 'ok' : 'error' ), admin_url( 'admin.php' ) ) );
	exit;
} );

/** La página. */
function pa_postcompra_pagina() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$opciones = get_option( 'pa_postcompra_ajustes', array() );
	$opciones = wp_parse_args( is_array( $opciones ) ? $opciones : array(), pa_postcompra_defaults() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Woo Postcompra', 'woo-postcompra' ); ?></h1>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification -- solo muestra un aviso.
		$aviso = isset( $_GET['cafe'] ) ? sanitize_key( wp_unslash( $_GET['cafe'] ) ) : '';
		if ( 'ok' === $aviso ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Producto del café listo.', 'woo-postcompra' ) . '</p></div>';
		} elseif ( 'error' === $aviso ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'No se pudo crear el producto (¿módulo del café apagado?).', 'woo-postcompra' ) . '</p></div>';
		}
		?>

		<h2><?php esc_html_e( 'Producto del café', 'woo-postcompra' ); ?></h2>
		<?php if ( function_exists( 'pcaf_producto_id' ) && pcaf_producto_id() ) : ?>
			<p>
				<?php esc_html_e( 'Producto creado:', 'woo-postcompra' ); ?>
				<a href="<?php echo esc_url( get_edit_post_link( pcaf_producto_id() ) ); ?>"><?php echo esc_html( get_the_title( pcaf_producto_id() ) ); ?></a>
			</p>
		<?php elseif ( function_exists( 'pcaf_crear_producto' ) ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pa_postcompra_crear_cafe">
				<?php wp_nonce_field( 'pa_postcompra_crear_cafe' ); ?>
				<p><?php esc_html_e( 'La tarjeta no aparece hasta que exista el producto (oculto, virtual, uno por carrito).', 'woo-postcompra' ); ?></p>
				<?php submit_button( __( 'Crear el producto del café', 'woo-postcompra' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php else : ?>
			<p><?php esc_html_e( 'El módulo del café está apagado (opción pa_postcompra_cafe_off).', 'woo-postcompra' ); ?></p>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields( 'pa_postcompra' ); ?>
			<?php foreach ( pa_postcompra_campos() as $grupo ) : ?>
				<h2><?php echo esc_html( $grupo['titulo'] ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( $grupo['campos'] as $clave => $campo ) : ?>
						<?php
						$id     = 'pa-postcompra-' . $clave;
						$nombre = 'pa_postcompra_ajustes[' . $clave . ']';
						$valor  = $opciones[ $clave ];
						?>
						<tr>
							<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $campo[0] ); ?></label></th>
							<td>
								<?php if ( 'checkbox' === $campo[1] ) : ?>
									<input type="hidden" name="<?php echo esc_attr( $nombre ); ?>" value="0">
									<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nombre ); ?>" value="1" <?php checked( ! empty( $valor ) ); ?>>
								<?php elseif ( 'number' === $campo[1] ) : ?>
									<input type="number" min="0" step="1" class="small-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nombre ); ?>" value="<?php echo esc_attr( $valor ); ?>">
								<?php elseif ( 'textarea' === $campo[1] ) : ?>
									<textarea class="large-text" rows="2" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nombre ); ?>" placeholder="<?php echo esc_attr( pa_postcompra_texto_defecto( $clave ) ); ?>"><?php echo esc_textarea( $valor ); ?></textarea>
								<?php elseif ( 'impuesto' === $campo[1] ) : ?>
									<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nombre ); ?>">
										<option value="" <?php selected( $valor, '' ); ?>><?php esc_html_e( 'Clase estándar', 'woo-postcompra' ); ?></option>
										<?php if ( class_exists( 'WC_Tax' ) ) : ?>
											<?php foreach ( WC_Tax::get_tax_class_slugs() as $i => $slug ) : ?>
												<?php $nombres = WC_Tax::get_tax_classes(); ?>
												<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $valor, $slug ); ?>><?php echo esc_html( isset( $nombres[ $i ] ) ? $nombres[ $i ] : $slug ); ?></option>
											<?php endforeach; ?>
										<?php endif; ?>
										<option value="__ninguno" <?php selected( $valor, '__ninguno' ); ?>><?php esc_html_e( 'Sin impuesto (estado «ninguno»)', 'woo-postcompra' ); ?></option>
									</select>
								<?php else : ?>
									<input type="text" class="<?php echo 'color' === $campo[1] ? 'regular-text code' : 'large-text'; ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nombre ); ?>" value="<?php echo esc_attr( $valor ); ?>" placeholder="<?php echo esc_attr( pa_postcompra_texto_defecto( $clave ) ); ?>">
								<?php endif; ?>
								<?php if ( '' !== $campo[2] ) : ?>
									<p class="description"><?php echo esc_html( $campo[2] ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			<?php endforeach; ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/** Texto por defecto (para el placeholder). */
function pa_postcompra_texto_defecto( $clave ) {
	return pa_postcompra_texto_por_defecto( $clave );
}
