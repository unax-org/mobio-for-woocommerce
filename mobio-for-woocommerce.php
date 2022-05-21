<?php
/**
 * Plugin Name: Mobio for WooCommerce
 * Description: A plugin provides payment gateway for SMS payment with Mobio.
 * Version: 1.0.0
 * Requires at least: 4.7
 * Requires PHP: 7.2
 * Author: Unax
 * Author URI: https://unax.org/
 * Text Domain: mobio-woocommerce
 *
 * WC requires at least: 3.0
 * WC tested up to:  6.4.1
 *
 * License: Apache-2.0
 * License URI: https://github.com/mobio/mobio-woocommerce/blob/master/LICENSE
 *
 * @package     MobioWooCommerce
 * @author      Unax
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Set constants.
define( 'MOBIO_WC_NAME', 'Mobio for WooCommerce' );
// Note: this gets replaced at runtime by Github Actions during release, keep the version '1.1.1'.
define( 'MOBIO_WC_VERSION', '1.0.0' );
define( 'MOBIO_WC_GATEWAY_ID', 'wc_mobio' );
define( 'MOBIO_WC_PLUGIN_ID', 'mobio' );
define( 'MOBIO_WC_MIN_PHP_VER', '7.2.0' );
define( 'MOBIO_WC_MIN_WC_VER', '3.0' );
define( 'MOBIO_WC_PATH', plugin_dir_path( __FILE__ ) );
define( 'MOBIO_WC_URL', plugin_dir_url( __FILE__ ) );

// Includes.
require_once MOBIO_WC_PATH . 'functions.php';
require_once MOBIO_WC_PATH . 'includes/class-settings.php';

// Init.
add_action( 'plugins_loaded', 'mobio_plugin_init' );

/**
 * Register deactivation hook.
 */
register_deactivation_hook( __FILE__, 'wc_gateway_mobio_deactivate' );
