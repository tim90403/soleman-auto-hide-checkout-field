<?php
/**
 * Plugin Name:       Soleman Auto Hide Checkout Field
 * Plugin URI:        https://soleman.tw
 * Description:       依 WooCommerce 付款方式動態顯示／隱藏結帳欄位（支援 Classic 與 Block Checkout）。
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Soleman
 * Author URI:        https://soleman.tw
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       soleman-auto-hide-checkout-field
 * Domain Path:       /languages
 * WC requires at least: 6.0
 * WC tested up to:   10.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SAHCF_VERSION', '1.0.0' );
define( 'SAHCF_FILE', __FILE__ );
define( 'SAHCF_PATH', plugin_dir_path( __FILE__ ) );
define( 'SAHCF_URL', plugin_dir_url( __FILE__ ) );
define( 'SAHCF_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check WooCommerce is active.
 *
 * @return bool
 */
function sahcf_is_woocommerce_active() {
	$active_plugins = (array) get_option( 'active_plugins', array() );

	if ( is_multisite() ) {
		$active_plugins = array_merge( $active_plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	}

	return in_array( 'woocommerce/woocommerce.php', $active_plugins, true ) || class_exists( 'WooCommerce' );
}

if ( ! sahcf_is_woocommerce_active() ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Soleman Auto Hide Checkout Field 需要先啟用 WooCommerce。', 'soleman-auto-hide-checkout-field' );
			echo '</p></div>';
		}
	);
	return;
}

require_once SAHCF_PATH . 'includes/class-sahcf-fields.php';
require_once SAHCF_PATH . 'includes/class-sahcf-settings.php';
require_once SAHCF_PATH . 'includes/class-sahcf-frontend.php';
require_once SAHCF_PATH . 'includes/class-sahcf-plugin.php';

/**
 * Bootstrap plugin.
 *
 * @return SAHCF_Plugin
 */
function sahcf() {
	return SAHCF_Plugin::instance();
}

sahcf();

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', SAHCF_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', SAHCF_FILE, true );
		}
	}
);
