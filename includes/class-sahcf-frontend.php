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

		// Block: make hidden address fields optional / hidden via locale.
		add_filter( 'woocommerce_get_country_locale_default', array( $this, 'filter_locale_default' ), 100 );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'filter_default_address_fields' ), 100 );

		// Block additional field validation.
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
	 * Classic: unset required / optionally disable validation for hidden fields.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public function filter_checkout_fields( $fields ) {
		$payment = SAHCF_Fields::get_current_payment_method();
		$hidden  = SAHCF_Fields::get_hidden_fields( $payment );

		if ( empty( $hidden ) ) {
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
				$data[ $key ] = '';
			}
		}

		return $data;
	}

	/**
	 * Capture payment method from Store API JSON body.
	 *
	 * @param mixed            $result  Response.
	 * @param WP_REST_Server   $server  Server.
	 * @param WP_REST_Request  $request Request.
	 * @return mixed
	 */
	public function capture_rest_payment_method( $result, $server, $request ) {
		unset( $server );

		$route = $request instanceof WP_REST_Request ? $request->get_route() : '';
		if ( ! is_string( $route ) || false === strpos( $route, '/wc/store' ) ) {
			return $result;
		}

		$payment = $request->get_param( 'payment_method' );
		if ( empty( $payment ) && $request instanceof WP_REST_Request ) {
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
			$this->reset_country_locale_cache();
		}

		return $result;
	}

	/**
	 * Store API order update hook.
	 *
	 * @param WC_Order        $order   Order.
	 * @param WP_REST_Request $request Request.
	 */
	public function on_store_api_update_order( $order, $request ) {
		$payment = $request->get_param( 'payment_method' );
		if ( ! empty( $payment ) ) {
			$this->rest_payment_method = sanitize_text_field( (string) $payment );
			$this->reset_country_locale_cache();
		}

		// Store API always requires a billing email; provide a placeholder when the field is intentionally hidden.
		$hidden = SAHCF_Fields::get_hidden_fields( $this->resolve_payment_method() );
		if ( in_array( 'billing_email', $hidden, true ) && $order instanceof WC_Order && ! $order->get_billing_email() ) {
			$host = wp_parse_url( home_url(), PHP_URL_HOST );
			$host = $host ? $host : 'localhost';
			$order->set_billing_email( 'hidden-checkout@' . $host );
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
	 * Reset WC_Countries locale cache so filters re-apply with current payment method.
	 */
	private function reset_country_locale_cache() {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return;
		}

		try {
			$ref  = new ReflectionClass( WC()->countries );
			$prop = $ref->getProperty( 'locale' );
			$prop->setAccessible( true );
			$prop->setValue( WC()->countries, null );
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Silently ignore if reflection fails.
		}
	}

	/**
	 * Filter default locale address fields for Block validation.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public function filter_locale_default( $fields ) {
		return $this->apply_hidden_to_address_fields( $fields );
	}

	/**
	 * Filter default address fields.
	 *
	 * @param array $fields Fields.
	 * @return array
	 */
	public function filter_default_address_fields( $fields ) {
		return $this->apply_hidden_to_address_fields( $fields );
	}

	/**
	 * Mark address fields hidden/optional based on classic field mapping.
	 *
	 * @param array $fields Address fields (unprefixed keys).
	 * @return array
	 */
	private function apply_hidden_to_address_fields( $fields ) {
		$payment = $this->resolve_payment_method();
		$hidden  = SAHCF_Fields::get_hidden_fields( $payment );

		if ( empty( $hidden ) || ! is_array( $fields ) ) {
			return $fields;
		}

		foreach ( $hidden as $classic_key ) {
			$mapped = SAHCF_Fields::map_classic_to_block( $classic_key );
			if ( ! $mapped ) {
				continue;
			}

			// Address locale only covers core address keys (not billing_/shipping_ prefix).
			if ( ! in_array( $mapped['group'], array( 'billing', 'shipping' ), true ) ) {
				continue;
			}

			$name = $mapped['name'];
			if ( ! isset( $fields[ $name ] ) ) {
				continue;
			}

			$fields[ $name ]['required'] = false;
			$fields[ $name ]['hidden']   = true;
		}

		return $fields;
	}

	/**
	 * Soften block location validation for hidden additional fields.
	 *
	 * Note: core address required checks use locale; this catches additional field errors when possible.
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
