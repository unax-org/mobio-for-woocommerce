<?php
/**
 * Uninstall actions.
 *
 * @package MobioWooCommerce
 * @author  Zota
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// If uninstall not called from WordPress exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options.
delete_option( 'woocommerce_wc_gateway_mobio_settings' );
