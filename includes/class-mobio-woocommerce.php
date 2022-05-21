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
	 */
	public function __construct() {

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
	 * @param string $description Payment gateway description.
	 * @param string $payment_id  Payment gateway ID.
	 *
	 * @return string
	 */
	public static function checkout_form_field( $description, $payment_id ) {
		if( MOBIO_WC_GATEWAY_ID !== $payment_id ) {
			return;
		}

		if ( WC()->cart->is_empty() ) {
			return;
		}

		$mobio_service_id = '';
		$mobio_number     = '';
		$mobio_text       = '';
		$mobio_price      = '';

		// Loop over $cart items
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$product = $cart_item['data'];

			$mobio_service_id = $product->get_meta( '_mobio_service_id', true );
			$mobio_number     = $product->get_meta( '_mobio_number', true );
			$mobio_text       = $product->get_meta( '_mobio_text', true );
			$mobio_price      = $product->is_on_sale() ? $product->get_sale_price() : $product->get_regular_price();
		}

		$description = wp_kses(
			sprintf(
				// translators: %s SMS text, %s Mobile number, %s Price.
				__(
					'Please send SMS with text <strong>%s</strong> to <strong>%s</strong> and place the received code here. Price: %s',
					'mobio-woocommerce'
				),
				esc_html( $mobio_text ),
				(int) $mobio_number,
				wc_price( $mobio_price )
			),
			array(
				'strong'    => array(),
			)
		);

		ob_start(); // Start buffering

		printf( '<input type="hidden" name="mobio_service_id" value="%s">', (int) $mobio_service_id );

		woocommerce_form_field(
			'mobio_code',
			array(
				'type'          => 'text',
				'label'         => '',
				'class'         => array( 'form-row-wide' ),
				'required'      => false,
			),
			''
		);
		$description .= ob_get_clean();

	    return $description;
	}


	/**
	 * Process the payment and return the result
	 *
	 * @param  int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			wc_add_notice(
				esc_html__( 'Order not found.', 'mobio-woocommerce' ),
				'error'
			);
			return false;
		}

		// Prepare fields.
		$mobio_service_id = isset( $_POST['mobio_service_id'] ) ? (int) $_POST['mobio_service_id'] : null;
		$mobio_code       = isset( $_POST['mobio_code'] ) ? sanitize_text_field( $_POST['mobio_code'] ) : null;

		// Check fields.
		if ( empty( $mobio_service_id ) ) {
			wc_add_notice( esc_html__( 'Bad request: Mobio Service ID missing', 'mobio-woocommerce' ), 'error' );
			return false;
		}

		if ( empty( $mobio_code ) ) {
			wc_add_notice( sprintf( esc_html__( 'Missing required field for the selected payment method %s', 'mobio-woocommerce' ), $this->title ), 'error' );
			return false;
		}

		// Code validation request.
		$args  = array(
			'servID' => $mobio_service_id,
			'code' => $mobio_code
		);

		// Is test mode.
		$settings     = get_option( 'woocommerce_' . MOBIO_WC_GATEWAY_ID . '_settings', array() );
		$is_test_mode = ! empty( $settings['test_mode'] ) && 'yes' === $settings['test_mode'];
		if ( $is_test_mode ) {
			$args  = array(
				'servID' => 29,
				'code' => 'T4JC6G'
			);
		}

		// Build URL.
		$url = sprintf( 'http://www.mobio.bg/code/checkcode.php?%s', http_build_query( $args ) );

		$response = wp_remote_get( $url );
		if ( is_wp_error( $response ) ) {
			return false;
		}

		// Check if is successful payment.
		if ( 'PAYBG=OK' !== $response['body'] ) {
			return false;
		}

		// Process order.
		$order = wc_get_order( $order_id );

		$order->payment_complete();
		$order->update_status( 'completed', __( 'Order paid with SMS', 'mobio-woocommerce' ) );

		// Empty cart.
		WC()->cart->empty_cart();

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order )
		);
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
