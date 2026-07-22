<?php
/**
 * WooCommerce settings tab: 動態結帳欄位.
 *
 * @package Soleman_Auto_Hide_Checkout_Field
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'SAHCF_WC_Settings_Page', false ) ) {
	return new SAHCF_WC_Settings_Page();
}

/**
 * Class SAHCF_WC_Settings_Page
 */
class SAHCF_WC_Settings_Page extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'sahcf_dynamic_fields';
		$this->label = __( '動態結帳欄位', 'soleman-auto-hide-checkout-field' );

		parent::__construct();

		add_action( 'woocommerce_admin_field_sahcf_visibility_matrix', array( $this, 'output_visibility_matrix' ) );
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save_visibility_matrix' ), 5 );
	}

	/**
	 * Default section settings.
	 *
	 * @return array
	 */
	protected function get_settings_for_default_section() {
		return array(
			array(
				'title' => __( '動態結帳欄位', 'soleman-auto-hide-checkout-field' ),
				'type'  => 'title',
				'desc'  => __( '勾選的欄位會在該付款方式被選擇時顯示；未勾選則隱藏。若某個付款方式尚未儲存過設定，前端會顯示全部欄位。隱藏的必填欄位會一併取消必填驗證。', 'soleman-auto-hide-checkout-field' ),
				'id'    => 'sahcf_dynamic_fields_options',
			),
			array(
				'type' => 'sahcf_visibility_matrix',
				'id'   => 'sahcf_visibility_matrix',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'sahcf_dynamic_fields_options',
			),
		);
	}

	/**
	 * Render checkbox matrix.
	 */
	public function output_visibility_matrix() {
		$gateways = SAHCF_Fields::get_payment_gateways();
		$grouped  = SAHCF_Fields::get_checkout_fields_grouped();
		$map      = SAHCF_Fields::get_visibility_map();
		$sections = array(
			'billing'    => __( '帳單欄位 (Billing)', 'soleman-auto-hide-checkout-field' ),
			'shipping'   => __( '運送欄位 (Shipping)', 'soleman-auto-hide-checkout-field' ),
			'additional' => __( '其他欄位 (Additional)', 'soleman-auto-hide-checkout-field' ),
		);

		wp_enqueue_style(
			'sahcf-admin',
			SAHCF_URL . 'assets/css/admin.css',
			array(),
			SAHCF_VERSION
		);
		wp_enqueue_script(
			'sahcf-admin',
			SAHCF_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			SAHCF_VERSION,
			true
		);

		if ( empty( $gateways ) ) {
			echo '<tr><td colspan="2"><p class="description">' . esc_html__( '目前沒有已啟用的付款方式。請先至 WooCommerce → 設定 → 付款 啟用至少一種付款方式。', 'soleman-auto-hide-checkout-field' ) . '</p></td></tr>';
			return;
		}

		echo '<tr><td colspan="2">';
		echo '<div class="sahcf-settings">';
		echo '<input type="hidden" name="sahcf_visibility_matrix_submitted" value="1" />';

		echo '<div class="sahcf-gateway-tabs" role="tablist">';
		$first = true;
		foreach ( $gateways as $gateway_id => $gateway_title ) {
			$tab_id = 'sahcf-tab-' . sanitize_html_class( $gateway_id );
			printf(
				'<button type="button" class="sahcf-gateway-tab%s" data-target="%s" role="tab">%s</button>',
				$first ? ' is-active' : '',
				esc_attr( $tab_id ),
				esc_html( $gateway_title )
			);
			$first = false;
		}
		echo '</div>';

		$first = true;
		foreach ( $gateways as $gateway_id => $gateway_title ) {
			$tab_id      = 'sahcf-tab-' . sanitize_html_class( $gateway_id );
			$configured  = isset( $map[ $gateway_id ] ) && is_array( $map[ $gateway_id ] );
			$visible     = $configured ? $map[ $gateway_id ] : SAHCF_Fields::get_all_field_keys();
			$visible_map = array_fill_keys( $visible, true );

			printf(
				'<div id="%1$s" class="sahcf-gateway-panel%2$s" data-gateway="%3$s" role="tabpanel">',
				esc_attr( $tab_id ),
				$first ? ' is-active' : '',
				esc_attr( $gateway_id )
			);

			echo '<div class="sahcf-panel-toolbar">';
			printf(
				'<p class="description">%s <strong>%s</strong>%s</p>',
				esc_html__( '付款方式：', 'soleman-auto-hide-checkout-field' ),
				esc_html( $gateway_title ),
				$configured
					? ''
					: ' — ' . esc_html__( '尚未儲存過設定（預設顯示全部）', 'soleman-auto-hide-checkout-field' )
			);
			echo '<p class="sahcf-bulk-actions">';
			echo '<button type="button" class="button sahcf-select-all">' . esc_html__( '全選', 'soleman-auto-hide-checkout-field' ) . '</button> ';
			echo '<button type="button" class="button sahcf-select-none">' . esc_html__( '全不選', 'soleman-auto-hide-checkout-field' ) . '</button>';
			echo '</p>';
			echo '</div>';

			foreach ( $sections as $section_key => $section_label ) {
				$fields = isset( $grouped[ $section_key ] ) ? $grouped[ $section_key ] : array();
				if ( empty( $fields ) ) {
					continue;
				}

				echo '<fieldset class="sahcf-section">';
				echo '<legend>' . esc_html( $section_label ) . '</legend>';
				echo '<ul class="sahcf-field-list">';

				foreach ( $fields as $field_key => $field ) {
					$input_id = 'sahcf_' . sanitize_html_class( $gateway_id . '_' . $field_key );
					$checked  = isset( $visible_map[ $field_key ] );
					$badges   = array();
					if ( ! empty( $field['required'] ) ) {
						$badges[] = '<span class="sahcf-badge sahcf-badge-required">' . esc_html__( '必填', 'soleman-auto-hide-checkout-field' ) . '</span>';
					}
					if ( ! empty( $field['custom'] ) ) {
						$badges[] = '<span class="sahcf-badge sahcf-badge-custom">' . esc_html__( '自訂', 'soleman-auto-hide-checkout-field' ) . '</span>';
					}

					echo '<li>';
					printf(
						'<label for="%1$s"><input type="checkbox" id="%1$s" class="sahcf-field-checkbox" name="sahcf_visible[%2$s][]" value="%3$s" %4$s /> <span class="sahcf-field-label">%5$s</span> <code class="sahcf-field-key">%3$s</code> %6$s</label>',
						esc_attr( $input_id ),
						esc_attr( $gateway_id ),
						esc_attr( $field_key ),
						checked( $checked, true, false ),
						esc_html( $field['label'] ),
						implode( ' ', $badges ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					);
					echo '</li>';
				}

				echo '</ul>';
				echo '</fieldset>';
			}

			echo '</div>';
			$first = false;
		}

		echo '</div>';
		echo '</td></tr>';
	}

	/**
	 * Persist visibility matrix.
	 */
	public function save_visibility_matrix() {
		if ( empty( $_POST['sahcf_visibility_matrix_submitted'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$gateways = array_keys( SAHCF_Fields::get_payment_gateways() );
		$raw      = isset( $_POST['sahcf_visible'] ) ? wp_unslash( $_POST['sahcf_visible'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$clean    = array();

		foreach ( $gateways as $gateway_id ) {
			$fields = array();
			if ( isset( $raw[ $gateway_id ] ) && is_array( $raw[ $gateway_id ] ) ) {
				foreach ( $raw[ $gateway_id ] as $field_key ) {
					$field_key = sanitize_text_field( $field_key );
					if ( '' !== $field_key ) {
						$fields[] = $field_key;
					}
				}
			}
			$clean[ $gateway_id ] = array_values( array_unique( $fields ) );
		}

		SAHCF_Fields::save_visibility_map( $clean );
	}
}

return new SAHCF_WC_Settings_Page();
