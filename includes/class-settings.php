<?php
/**
 * Mobio for WooCommerce Settings.
 *
 * @package MobioWooCommerce
 * @author  Unax
 */

namespace Unax\Mobio\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class.
 */
class Settings {

	/**
	 * Test mode
	 *
	 * @var string
	 */
	public static $test_mode;

	/**
	 * Admin
	 */
	public static function form_fields() {
		$woocommerce_currency = get_woocommerce_currency();

		// @codingStandardsIgnoreStart
		return apply_filters(
			'wc_mobio_settings',
			array(
				'enabled'                     => array(
					'title'   => esc_html__( 'Enable/Disable', 'mobio-woocommerce' ),
					'label'   => sprintf( '%s %s', esc_html__( 'Enable', 'mobio-woocommerce' ), MOBIO_WC_NAME ),
					'type'    => 'checkbox',
					'default' => 'yes',
				),
				'title'                       => array(
					'title'       => esc_html__( 'Title', 'mobio-woocommerce' ),
					'description' => esc_html__( 'This controls the title which the user sees during checkout.', 'mobio-woocommerce' ),
					'default'     => esc_html__( 'SMS Code', 'mobio-woocommerce' ),
					'type'        => 'text',
					'desc_tip'    => true,
				),
				'test_mode'                    => array(
					'title'   => esc_html__( 'Test Mode', 'mobio-woocommerce' ),
					'label'   => esc_html__( 'Enable Test Mode', 'mobio-woocommerce' ),
					'type'    => 'checkbox',
					'default' => 'yes',
				),
			)
		);
		// @codingStandardsIgnoreEnd
	}


	/**
	 * Get payment method description.
	 *
	 * @return string
	 */
	public static function get_description() {

		return wp_kses(
			sprintf(
				// translators: %1$s Plugin name, %2$s Mobio registration url, %3$s Mobio in english.
				__(
					'%1$s provides payments by SMS. <a href="%2$s" target="_blank">Sign up</a> for %1$s account.',
					'mobio-woocommerce'
				),
				'Mobio',
				'https://mobio.bg/site/registration'
			),
			array(
				'a'     => array(
					'href'   => array(),
					'target' => array(),
				),
				'br'    => array(),
				'small' => array(),
			)
		);

	}


	/**
     * Add the fields to product general tab in admin.
     */
    public static function product_options_general_product_data() {
        global $post;

		$product = wc_get_product( $post->ID );

        woocommerce_wp_text_input(
			array(
	            'id'            => 'mobio_service_id',
	            'label'         => esc_html__( 'Mobio service ID', 'mobio-woocommerce' ),
	            'description'   => '',
	            'value'         => $product->get_meta( '_mobio_service_id', true ),
	        )
		);

		woocommerce_wp_text_input(
			array(
	            'id'            => 'mobio_number',
	            'label'         => esc_html__( 'Mobio number', 'mobio-woocommerce' ),
	            'description'   => '',
	            'value'         => $product->get_meta( '_mobio_number', true ),
	        )
		);

		woocommerce_wp_text_input(
			array(
	            'id'            => 'mobio_text',
	            'label'         => esc_html__( 'Mobio text', 'mobio-woocommerce' ),
	            'description'   => '',
	            'value'         => $product->get_meta( '_mobio_text', true ),
	        )
		);
    }


    /**
     * Save product meta.
	 *
	 * @param int $post_id Post ID.
     */
    public static function process_product_meta( $post_id ) {
        $product = wc_get_product( $post_id );

		if ( isset( $_POST['mobio_service_id'] ) ) {
			$product->update_meta_data( '_mobio_service_id', (int) $_POST['mobio_service_id'] );
		}

		if ( isset( $_POST['mobio_number'] ) ) {
			$product->update_meta_data( '_mobio_number', (int) $_POST['mobio_number'] );
		}

		if ( isset( $_POST['mobio_text'] ) ) {
			$product->update_meta_data( '_mobio_text', sanitize_text_field( $_POST['mobio_text'] ) );
		}

        $product->save();
    }
}
