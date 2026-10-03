<?php
/**
 * Plugin Name: Woo Postcompra
 * Plugin URI: https://github.com/quijotevitruvio/woo-postcompra
 * Description: Lo que se le ofrece al cliente después de comprar: invitar un café (propina como producto de WooCommerce) y dejar una reseña con foto.
 * Version: 1.0.1
 * Author: Andrés Valencia Tobón
 * Author URI: https://github.com/quijotevitruvio
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-postcompra
 *
 * LinkedIn: https://www.linkedin.com/in/andr%C3%A9s-valencia-tob%C3%B3n/
 * Donate link: https://paypal.me/bibliotecologo
 *
 * Dos módulos independientes:
 *  - «Invítanos un café»: includes/cafe.php
 *  - «Reseñas con foto» y recordatorio de reseñas: includes/resenas.php
 * Ajustes: WooCommerce → Postcompra (includes/ajustes.php).
 * Apagar una pieza: update_option( 'pa_postcompra_cafe_off', 1 ) o
 *                   update_option( 'pa_postcompra_resenas_off', 1 ).
 */

defined( 'ABSPATH' ) || exit;

define( 'PA_POSTCOMPRA_VERSION', '1.0.1' );
define( 'PA_POSTCOMPRA_FILE', __FILE__ );
define( 'PA_POSTCOMPRA_URL', plugin_dir_url( __FILE__ ) );

// WooCommerce: compatible con pedidos en tablas propias (HPOS).
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

require_once __DIR__ . '/includes/comun.php';
require_once __DIR__ . '/includes/ajustes.php';

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	if ( ! get_option( 'pa_postcompra_cafe_off' ) ) {
		require_once __DIR__ . '/includes/cafe.php';
	}
	if ( ! get_option( 'pa_postcompra_resenas_off' ) ) {
		require_once __DIR__ . '/includes/resenas.php';
	}
}, 20 );
