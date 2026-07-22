<?php
/**
 * Field and payment gateway helpers.
 *
 * @package Soleman_Auto_Hide_Checkout_Field
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAHCF_Fields
 */
class SAHCF_Fields {

	const OPTION_KEY = 'sahcf_visibility_map';

	/**
	 * Get enabled payment gateways.
	 *
	 * @return array<string,string> id => title
	 */
	public static function get_payment_gateways() {
		if ( ! function_exists( 'WC' ) || ! WC()->payment_gateways() ) {
			return array();
		}

		$gateways = WC()->payment_gateways()->payment_gateways();
		$result   = array();

		foreach ( $gateways as $id => $gateway ) {
			if ( ! is_object( $gateway ) ) {
				continue;
			}

			$enabled = isset( $gateway->enabled ) ? $gateway->enabled : 'no';
			if ( 'yes' !== $enabled ) {
				continue;
			}

			$result[ $id ] = $gateway->get_title() ? $gateway->get_title() : $id;
		}

		return $result;
	}

	/**
	 * Whether Checkout Field Editor Pro utilities are available.
	 *
	 * @return bool
	 */
	public static function is_thwcfd_available() {
		return class_exists( 'THWCFD_Utils' );
	}

	/**
	 * Get checkout fields grouped by section.
	 *
	 * @return array<string,array<string,array>>
	 */
	public static function get_checkout_fields_grouped() {
		$sections = array(
			'billing'    => array(),
			'shipping'   => array(),
			'additional' => array(),
		);

		if ( self::is_thwcfd_available() ) {
			foreach ( array_keys( $sections ) as $section ) {
				$fields = THWCFD_Utils::get_fields( $section );
				foreach ( $fields as $key => $field ) {
					if ( is_array( $field ) && isset( $field['enabled'] ) && false === $field['enabled'] ) {
						continue;
					}
					$sections[ $section ][ $key ] = self::normalize_field( $key, $field, $section );
				}
			}

			return $sections;
		}

		if ( function_exists( 'WC' ) && WC()->checkout() ) {
			$all = WC()->checkout()->get_checkout_fields();
			foreach ( array( 'billing', 'shipping', 'order' ) as $section ) {
				$target = ( 'order' === $section ) ? 'additional' : $section;
				if ( empty( $all[ $section ] ) || ! is_array( $all[ $section ] ) ) {
					continue;
				}
				foreach ( $all[ $section ] as $key => $field ) {
					$sections[ $target ][ $key ] = self::normalize_field( $key, $field, $target );
				}
			}
		}

		return $sections;
	}

	/**
	 * Flatten all field keys.
	 *
	 * @return string[]
	 */
	public static function get_all_field_keys() {
		$keys     = array();
		$grouped  = self::get_checkout_fields_grouped();
		foreach ( $grouped as $fields ) {
			$keys = array_merge( $keys, array_keys( $fields ) );
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Normalize field meta for admin/UI.
	 *
	 * @param string $key     Field key.
	 * @param array  $field   Field args.
	 * @param string $section Section.
	 * @return array
	 */
	private static function normalize_field( $key, $field, $section ) {
		$label = '';
		if ( is_array( $field ) ) {
			if ( ! empty( $field['label'] ) ) {
				$label = wp_strip_all_tags( (string) $field['label'] );
			} elseif ( ! empty( $field['title'] ) ) {
				$label = wp_strip_all_tags( (string) $field['title'] );
			}
		}

		if ( '' === $label ) {
			$label = $key;
		}

		$required = false;
		if ( is_array( $field ) && ! empty( $field['required'] ) ) {
			$required = (bool) $field['required'];
		}

		$custom = false;
		if ( is_array( $field ) && ! empty( $field['custom'] ) ) {
			$custom = true;
		}

		return array(
			'key'      => $key,
			'label'    => $label,
			'required' => $required,
			'custom'   => $custom,
			'section'  => $section,
			'type'     => is_array( $field ) && isset( $field['type'] ) ? $field['type'] : 'text',
		);
	}

	/**
	 * Get saved visibility map.
	 *
	 * @return array<string,string[]> payment_method => list of visible field keys
	 */
	public static function get_visibility_map() {
		$map = get_option( self::OPTION_KEY, array() );
		return is_array( $map ) ? $map : array();
	}

	/**
	 * Save visibility map.
	 *
	 * @param array $map Map.
	 * @return bool
	 */
	public static function save_visibility_map( $map ) {
		return update_option( self::OPTION_KEY, $map, false );
	}

	/**
	 * Whether a payment method has explicit configuration.
	 *
	 * @param string $payment_method Payment method id.
	 * @return bool
	 */
	public static function has_config( $payment_method ) {
		$map = self::get_visibility_map();
		return isset( $map[ $payment_method ] ) && is_array( $map[ $payment_method ] );
	}

	/**
	 * Get visible field keys for a payment method.
	 * Returns null when unconfigured (show all).
	 *
	 * @param string $payment_method Payment method id.
	 * @return string[]|null
	 */
	public static function get_visible_fields( $payment_method ) {
		if ( ! $payment_method || ! self::has_config( $payment_method ) ) {
			return null;
		}

		$map = self::get_visibility_map();
		return array_values( array_map( 'strval', $map[ $payment_method ] ) );
	}

	/**
	 * Get fields that should be hidden for a payment method.
	 *
	 * @param string $payment_method Payment method id.
	 * @return string[]
	 */
	public static function get_hidden_fields( $payment_method ) {
		$visible = self::get_visible_fields( $payment_method );
		if ( null === $visible ) {
			return array();
		}

		$all = self::get_all_field_keys();
		return array_values( array_diff( $all, $visible ) );
	}

	/**
	 * Detect current payment method from request/session.
	 *
	 * @return string
	 */
	public static function get_current_payment_method() {
		if ( isset( $_POST['payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		if ( function_exists( 'WC' ) && WC()->session ) {
			$chosen = WC()->session->get( 'chosen_payment_method' );
			if ( $chosen ) {
				return (string) $chosen;
			}
		}

		return '';
	}

	/**
	 * Map classic field key to block address field key (without prefix).
	 *
	 * @param string $field_key Classic field key.
	 * @return array{group:string,name:string}|null
	 */
	public static function map_classic_to_block( $field_key ) {
		if ( 0 === strpos( $field_key, 'billing_' ) ) {
			$name = substr( $field_key, 8 );
			if ( 'email' === $name ) {
				return array(
					'group' => 'contact',
					'name'  => 'email',
				);
			}
			return array(
				'group' => 'billing',
				'name'  => $name,
			);
		}

		if ( 0 === strpos( $field_key, 'shipping_' ) ) {
			return array(
				'group' => 'shipping',
				'name'  => substr( $field_key, 9 ),
			);
		}

		if ( in_array( $field_key, array( 'order_comments', 'customer_note' ), true ) ) {
			return array(
				'group' => 'order',
				'name'  => 'order_comments',
			);
		}

		return array(
			'group' => 'additional',
			'name'  => $field_key,
		);
	}

	/**
	 * Build frontend config payload.
	 *
	 * @return array
	 */
	public static function get_frontend_config() {
		return array(
			'visibilityMap' => self::get_visibility_map(),
			'allFields'     => self::get_all_field_keys(),
			'i18n'          => array(
				'unconfigured' => __( '未設定的付款方式將顯示全部欄位。', 'soleman-auto-hide-checkout-field' ),
			),
		);
	}
}
