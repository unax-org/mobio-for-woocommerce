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
	 * Validate code by Mobio.
	 *
	 * @param string $fields Checkout fields.
	 * @param string $errors Checkout errors.
	 *
	 * @return bool
	 */
	public static function after_checkout_code_validation( $fields, $errors ) {
		$code 		   = isset( $_POST['mobio_code'] ) ? sanitize_text_field( $_POST['mobio_code'] ) : null;
		$validate_code = self::validate_code( $code );

		if ( 'error' === $validate_code['result'] ) {
			$errors->add( 'validation', esc_html( $validate_code['message'] ) );

			return false;
		}

		return true;
	}


	/**
	 * Validate code by Mobio.
	 *
	 * @param string $code Code.
	 *
	 * @return array
	 */
	public static function validate_code( $code = '' ) {
		$ttdb = new wpdb( 'root', '0000', 'tvoetotaro_old', 'localhost' );

		try {
			if( empty( $code ) ) {
				throw new \Exception( 'Не е въведен код.' );
			}

			// Check if code length is ok.
			if( strlen( $code ) !== 6 ) {
				throw new \Exception( 'Кодът трябва да е с дължина 6 цифри.' );
			}

			// Check code content.
			if( preg_match('/^[\w]*$/', $code )!==1 ) {
				throw new \Exception( 'Кодът трябва да съдържа само цифри.' );
			}

			// Check code.
			$code_valid = $ttdb->get_row( $wpdb->prepare( "SELECT * FROM codes WHERE code = '%s' AND module = 1", \sanitize_text_field( $code ) ) );

			if( empty( $code_valid ) ) {
				throw new \Exception( 'Кодът е невалиден или вече е използван.' );
			}

			return array(
				'result'  => 'success',
				'data'    => $code_valid,
			);

		} catch ( \Exception $e ) {
			return array(
				'result'  => 'error',
				'message' => esc_html( $e->getMessage() ),
			);
		}
	}


	/**
	 * Validate code by Mobio.
	 *
 	* @param string $code Code.
 	*
 	* @return bool
	 */
	public static function archive_code( $code_used ) {
		$ttdb = new \wpdb( 'root', '0000', 'tvoetotaro_old', 'localhost' );

		try {
			// Change status.
			$code_status = $ttdb->update(
				'codes',
				array(
					'status' => 'used',
					'used'   => date('Y:m:d H:i:s'),
				),
				array(
					'id' => $code_used->id,
				),
				array( '%s', '%s' ),
				array( '%d' ),
			);

			if( empty( $code_status ) ) {
				throw new \Exception( 'Update code status failed.' );
			}

			// Move the code to codes archive.
			$data['customers_id']   = (int)$code_used->customers_id;
			$data['payments_id']    = (int)$code_used->payments_id;
			$data['mt_messages_id'] = (int)$code_used->mt_messages_id;
			$data['module']         = (int)$module;
			$data['code']           = \sanitize_text_field( $code_used->code );
			$data['status']         = \sanitize_text_field( $code_used->status );
			$data['resent']         = \sanitize_text_field( $code_used->resent );
			$data['added']          = \sanitize_text_field( $code_used->added );
			$data['delivered']      = \sanitize_text_field( $code_used->delivered );
			$data['used']           = \sanitize_text_field( $code_used->used );
			$data['modified']       = \sanitize_text_field( $code_used->modified );

			$codes_archive_id = $ttdb->insert( 'codes_archive', $data );
			if( empty( $codes_archive_id ) ) {
				throw new \Exception( 'Insert code in archive failed.' );
			}

			// Update mt_messages.
			$mt_messages = $ttdb->update(
				'mt_messages',
				array(
					'relation' 	   => 'codes_archive',
					'relation_key' => (int)$codes_archive_id,
				),
				array(
					'id' => (int)$code_used->mt_messages_id,
				),
				array( '%s', '%d' ),
				array( '%d' ),
			);

			if( empty( $mt_messages ) ) {
				throw new \Exception( 'Update mt_messages failed.' );
			}

			// Delete the original code.
			$mt_messages = $ttdb->delete(
				'codes',
				array(
					'id' => (int)$code_used->id,
				),
				array( '%d' ),
			);

			return array(
				'result'  => 'success',
				'data'    => $codes_archive_id,
			);

		} catch ( \Exception $e ) {
			return array(
				'result'  => 'error',
				'message' => esc_html( $e->getMessage() ),
			);
		}
	}


	/**
	 * Process the payment and return the result
	 *
	 * @param  int $order_id Order ID.
	 * @return array|null
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			wc_add_notice(
				esc_html__( 'Order not found.', 'mobio-woocommerce' ),
				'error'
			);
			return;
		}

		// Process order.
		$order = wc_get_order( $order_id );

		$code 		   = isset( $_POST['mobio_code'] ) ? sanitize_text_field( $_POST['mobio_code'] ) : null;
		$validate_code = self::validate_code( $code );
		if ( 'error' === $validate_code['result'] ) {
			wc_add_notice( esc_html( $validate_code['message'] ), 'error' );
			$order->update_status( 'failed', sanitize_text_field( $validate_code['result'] ) );

			return;
		}

		$archive_code = self::archive_code( $validate_code );
		if ( 'error' === $archive_code['result'] ) {
			wc_add_notice( esc_html( $archive_code['message'] ), 'error' );
			$order->update_status( 'failed', sanitize_text_field( $archive_code['result'] ) );

			return;
		}

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
