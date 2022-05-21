<?php
/**
 * Mobio for WooCommerce
 *
 * @package MobioWooCommerce
 * @author  Unax
 */

use Unax\Mobio\Includes\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mobio_WooCommerce class.
 *
 * @extends WC_Payment_Gateway
 */
class Mobio_WooCommerce extends \WC_Payment_Gateway {

	/**
	 * Defines main properties, load settings fields and hooks
	 *
	 * @param  string $payment_method Payment method.
	 */
	public function __construct( $payment_method ) {

		$this->id                 = MOBIO_WC_GATEWAY_ID;
		$this->method_title       = MOBIO_WC_NAME;
		$this->method_description = Settings::get_description();
		$this->has_fields         = false;
		$this->supports           = array(
			'products',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->payment_method = $this->get_option( 'payment_method' );

		// Hooks.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}


	/**
	 * Check if the currency is in the supported currencies
	 *
	 * @return bool
	 */
	public function is_supported() {
		return true;
	}


	/**
	 * Check if the gateway is available.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( ! $this->is_supported() ) {
			return false;
		}

		return parent::is_available();
	}


	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = Settings::form_fields();
	}


	/**
	 * Process the payment and return the result
	 *
	 * @param  int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		global $woocommerce;

		$order = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			wc_add_notice(
				esc_html__( 'Order not found.', 'mobio-woocommerce' ),
				'error'
			);
			return false;
		}

		return false;
		//
		// // Remove cart.
		// $woocommerce->cart->empty_cart();
		//
		// return array(
		// 	'result'   => 'success',
		// 	'redirect' => $response->getDepositUrl(),
		// );
	}


	/**
	 * Get payment method icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		$attachment = null;
		if ( ! empty( $this->get_option( 'icon' ) ) ) {
			$atts       = array(
				'class' => 'mobio-icon gateway-' . hash( 'crc32b', $this->id ) . '-icon',
			);
			$attachment = wp_get_attachment_image( $this->get_option( 'icon' ), 'medium', false, $atts );
		}

		$icon = apply_filters( 'mobio_woocommerce_' . $this->id . '_icon', $attachment );
		if ( empty( $icon ) ) {
			return;
		}

		return $icon;
	}
}
