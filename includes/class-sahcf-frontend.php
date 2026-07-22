<?php
/**
 * Frontend classic + block checkout behaviour.
 *
 * @package Soleman_Auto_Hide_Checkout_Field
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAHCF_Frontend
 *
 * Intentionally does NOT hook country-locale / default address filters.
 * Those hooks can recurse with THWCFD/WooCommerce field builders and take down the site.
 */
class SAHCF_Frontend {

	/**
	 * Payment method captured from Store API request.
	 *
	 * @var string
	 */
	private $rest_payment_method = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Classic checkout: adjust required flags before validation.
		add_filter( 'woocommerce_checkout_fields', array( $this, 'filter_checkout_fields' ), 9999 );

		// Classic: clear posted values for hidden fields.
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'filter_posted_data' ), 20 );

		// Store API / Block: capture payment method early.
		add_filter( 'rest_pre_dispatch', array( $this, 'capture_rest_payment_method' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'on_store_api_update_order' ), 5, 2 );

		// Block additional field validation softener.
		add_action( 'woocommerce_blocks_validate_location_address_fields', array( $this, 'filter_block_location_errors' ), 10, 3 );
		add_action( 'woocommerce_blocks_validate_location_contact_fields', array( $this, 'filter_block_location_errors' ), 10, 3 );
		add_action( 'woocommerce_blocks_validate_location_order_fields', array( $this, 'filter_block_location_errors' ), 10, 3 );
	}

	/**
	 * Enqueue frontend assets.
	 */
	public function enqueue_scripts() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
			return;
		}

		$config = SAHCF_Fields::get_frontend_config();

		wp_enqueue_style(
			'sahcf-frontend',
			SAHCF_URL . 'assets/css/frontend.css',
			array(),
			SAHCF_VERSION
		);

		wp_enqueue_script(
			'sahcf-classic-checkout',
			SAHCF_URL . 'assets/js/classic-checkout.js',
			array( 'jquery' ),
			SAHCF_VERSION,
			true
		);
		wp_localize_script( 'sahcf-classic-checkout', 'sahcfConfig', $config );

		wp_enqueue_script(
			'sahcf-block-checkout',
			SAHCF_URL . 'assets/js/block-checkout.js',
			array( 'wp-data', 'wp-hooks' ),
			SAHCF_VERSION,
			true
		);
		wp_localize_script( 'sahcf-block-checkout', 'sahcfBlockConfig', $config );
	}

	/**
	 * Classic: unset required for hidden fields.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function filter_checkout_fields( $fields ) {
		$payment = SAHCF_Fields::get_current_payment_method();
		$hidden  = SAHCF_Fields::get_hidden_fields( $payment );

		if ( empty( $hidden ) || ! is_array( $fields ) ) {
			return $fields;
		}

		foreach ( $fields as $section => &$section_fields ) {
			if ( ! is_array( $section_fields ) ) {
				continue;
			}
			foreach ( $section_fields as $key => &$field ) {
				if ( ! in_array( $key, $hidden, true ) ) {
					continue;
				}
				$field['required'] = false;
				$field['class']    = isset( $field['class'] ) && is_array( $field['class'] ) ? $field['class'] : array();
				$field['class'][]  = 'sahcf-server-hidden';
			}
		}

		return $fields;
	}

	/**
	 * Classic: blank out posted values for hidden fields.
	 *
	 * @param array $data Posted data.
	 * @return array
	 */
	public function filter_posted_data( $data ) {
		$payment = isset( $data['payment_method'] ) ? $data['payment_method'] : SAHCF_Fields::get_current_payment_method();
		$hidden  = SAHCF_Fields::get_hidden_fields( $payment );

		foreach ( $hidden as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$data[ $key ] = is_array( $data[ $key ] ) ? array() : '';
			}
		}

		return $data;
	}

	/**
	 * Capture payment method from Store API JSON body.
	 *
	 * @param mixed           $result  Response.
	 * @param WP_REST_Server  $server  Server.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function capture_rest_payment_method( $result, $server, $request ) {
		unset( $server );

		if ( ! ( $request instanceof WP_REST_Request ) ) {
			return $result;
		}

		$route = $request->get_route();
		if ( ! is_string( $route ) || false === strpos( $route, '/wc/store' ) ) {
			return $result;
		}

		$payment = $request->get_param( 'payment_method' );
		if ( empty( $payment ) ) {
			$json = $request->get_json_params();
			if ( is_array( $json ) && ! empty( $json['payment_method'] ) ) {
				$payment = $json['payment_method'];
			}
		}

		if ( ! empty( $payment ) ) {
			$this->rest_payment_method = sanitize_text_field( (string) $payment );
			if ( function_exists( 'WC' ) && WC()->session ) {
				WC()->session->set( 'chosen_payment_method', $this->rest_payment_method );
			}
		}

		return $result;
	}

	/**
	 * Store API order update hook.
	 *
	 * Fills placeholders for hidden required address fields so Block validation
	 * does not fail — without touching country locale (avoids recursion).
	 *
	 * @param WC_Order        $order   Order.
	 * @param WP_REST_Request $request Request.
	 */
	public function on_store_api_update_order( $order, $request ) {
		if ( ! ( $order instanceof WC_Order ) ) {
			return;
		}

		$payment = $request->get_param( 'payment_method' );
		if ( ! empty( $payment ) ) {
			$this->rest_payment_method = sanitize_text_field( (string) $payment );
		}

		$hidden = SAHCF_Fields::get_hidden_fields( $this->resolve_payment_method() );
		if ( empty( $hidden ) ) {
			return;
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		$host = $host ? $host : 'localhost';

		$setters = array(
			'billing_first_name'  => 'set_billing_first_name',
			'billing_last_name'   => 'set_billing_last_name',
			'billing_company'     => 'set_billing_company',
			'billing_address_1'   => 'set_billing_address_1',
			'billing_address_2'   => 'set_billing_address_2',
			'billing_city'        => 'set_billing_city',
			'billing_state'       => 'set_billing_state',
			'billing_postcode'    => 'set_billing_postcode',
			'billing_country'     => 'set_billing_country',
			'billing_phone'       => 'set_billing_phone',
			'billing_email'       => 'set_billing_email',
			'shipping_first_name' => 'set_shipping_first_name',
			'shipping_last_name'  => 'set_shipping_last_name',
			'shipping_company'    => 'set_shipping_company',
			'shipping_address_1'  => 'set_shipping_address_1',
			'shipping_address_2'  => 'set_shipping_address_2',
			'shipping_city'       => 'set_shipping_city',
			'shipping_state'      => 'set_shipping_state',
			'shipping_postcode'   => 'set_shipping_postcode',
			'shipping_country'    => 'set_shipping_country',
			'shipping_phone'      => 'set_shipping_phone',
		);

		foreach ( $hidden as $field_key ) {
			if ( ! isset( $setters[ $field_key ] ) ) {
				continue;
			}

			$setter = $setters[ $field_key ];
			$getter = str_replace( 'set_', 'get_', $setter );

			if ( ! method_exists( $order, $getter ) || ! method_exists( $order, $setter ) ) {
				continue;
			}

			$current = $order->{$getter}();
			if ( '' !== (string) $current ) {
				continue;
			}

			if ( 'billing_email' === $field_key ) {
				$order->{$setter}( 'hidden-checkout@' . $host );
				continue;
			}

			if ( 'billing_country' === $field_key || 'shipping_country' === $field_key ) {
				$base = function_exists( 'WC' ) && WC()->countries ? WC()->countries->get_base_country() : '';
				if ( $base ) {
					$order->{$setter}( $base );
				}
				continue;
			}

			// Non-empty placeholder so Store API required checks pass for intentionally hidden fields.
			$order->{$setter}( '-' );
		}
	}

	/**
	 * Resolve active payment method for validation.
	 *
	 * @return string
	 */
	private function resolve_payment_method() {
		if ( $this->rest_payment_method ) {
			return $this->rest_payment_method;
		}
		return SAHCF_Fields::get_current_payment_method();
	}

	/**
	 * Soften block location validation for hidden additional fields.
	 *
	 * @param WP_Error $errors Errors.
	 * @param array    $fields Fields.
	 * @param string   $group  Group.
	 */
	public function filter_block_location_errors( $errors, $fields, $group ) {
		unset( $fields, $group );

		if ( ! ( $errors instanceof WP_Error ) || ! $errors->has_errors() ) {
			return;
		}

		$payment = $this->resolve_payment_method();
		$hidden  = SAHCF_Fields::get_hidden_fields( $payment );
		if ( empty( $hidden ) ) {
			return;
		}

		foreach ( $errors->get_error_codes() as $code ) {
			foreach ( $hidden as $classic_key ) {
				if ( false !== strpos( (string) $code, $classic_key ) ) {
					$errors->remove( $code );
				}
			}
		}
	}
}
