<?php
/**
 * Main functions
 *
 * @package MobioWooCommerce
 * @author  Zota
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Check if requirements are ok.
 *
 * @return bool
 */
function wc_gateway_mobio_requirements() {
	// Check if all requirements are ok.
	$woocommerce_active  = class_exists( 'woocommerce' );
	$woocommerce_version = defined( 'WC_VERSION' ) && version_compare( WC_VERSION, MOBIO_WC_MIN_WC_VER, '>=' );
	$php_version         = version_compare( PHP_VERSION, MOBIO_WC_MIN_PHP_VER, '>=' );

	return $woocommerce_active && $woocommerce_version && $php_version;
}


/**
 * WooCommerce not activated error message.
 *
 * @return void
 */
function wc_gateway_mobio_woocommerce_error() {
	global $pagenow;

	if ( 'plugins.php' !== $pagenow ) {
		return;
	}

	?>
	<div class="notice notice-warning">
		<p>
			<?php
			printf(
				// translators: %1$s is plugin name.
				esc_html__( '%1$s requires WooCommerce to be active.', 'mobio-woocommerce' ),
				'<strong>' . esc_html( MOBIO_WC_NAME ) . '</strong>'
			);
			?>
			</strong>
		</p>
	</div>
	<?php
}


/**
 * Plugin initialization.
 *
 * @return void
 */
function mobio_plugin_init() {
	// Load the textdomain.
	load_plugin_textdomain( 'mobio-woocommerce', false, plugin_basename( dirname( __FILE__, 2 ) ) . '/languages' );

	// Check requirements
	if ( ! wc_gateway_mobio_requirements() ) {
		add_action( 'admin_notices', 'wc_gateway_mobio_requirements_error' );
		return;
	}

	// Show admin notice if WooCommerce is not active.
	if ( ! class_exists( 'woocommerce' ) ) {
		add_action( 'admin_notices', 'wc_gateway_mobio_woocommerce_error' );
		return;
	}

	wc_gateway_mobio_init();
}


/**
 * Gateway init.
 *
 * @return void
 */
function wc_gateway_mobio_init() {
	// Register admin scripts.
	add_action( 'admin_enqueue_scripts', 'mobio_admin_enqueue_scripts' );

	// Register scripts.
	add_action( 'wp_enqueue_scripts', 'mobio_enqueue_scripts' );

	// Hook filters and actions.
	add_filter( 'woocommerce_gateway_description', array( 'Mobio_WooCommerce', 'checkout_form_field' ), 20, 2 );
	add_action( 'woocommerce_product_options_general_product_data', array( '\Unax\Mobio\Includes\Settings', 'product_options_general_product_data' ) );
	add_action( 'woocommerce_process_product_meta', array( '\Unax\Mobio\Includes\Settings', 'process_product_meta' ) );

	// Initialize.
	require_once MOBIO_WC_PATH . '/includes/class-mobio-woocommerce.php';

	// Add to woocommerce payment gateways.
	add_filter(
		'woocommerce_payment_gateways',
		function ( $gateways ) {
			$gateways[] = new Mobio_WooCommerce();
			return $gateways;
		}
	);

	// add_filter( 'woocommerce_gateway_description', array(  ), 20, 2 );
}


/**
 * Admin options scripts.
 *
 * @param  string $hook WooCommerce Hook.
 * @return void
 */
function mobio_admin_enqueue_scripts( $hook ) {
	if ( 'woocommerce_page_wc-settings' !== $hook ) {
		return;
	}

	wp_enqueue_style( 'mobio-woocommerce-admin', MOBIO_WC_URL . 'dist/css/admin.css', array(), MOBIO_WC_VERSION );

	wp_enqueue_script( 'mobio-polyfill', MOBIO_WC_URL . 'dist/js/polyfill.js', array(), MOBIO_WC_VERSION, true );
	wp_enqueue_script( 'mobio-woocommerce', MOBIO_WC_URL . 'dist/js/admin.js', array( 'wp-i18n', 'jquery', 'mobio-polyfill' ), MOBIO_WC_VERSION, true );
}


/**
 * Public scripts.
 *
 * @return void
 */
function mobio_enqueue_scripts() {
	if ( is_admin() ) {
		return;
	}

	wp_register_style( 'mobio-woocommerce', MOBIO_WC_URL . 'dist/css/styles.css', array( 'woocommerce-inline' ), MOBIO_WC_VERSION );

	if ( is_checkout() ) {
		wp_enqueue_style( 'mobio-woocommerce' );
	}
}


/**
 * Deactivate plugin.
 *
 * @return void
 */
function wc_gateway_mobio_deactivate() {}
