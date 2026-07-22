<?php
/**
 * Main plugin bootstrap.
 *
 * @package Soleman_Auto_Hide_Checkout_Field
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAHCF_Plugin
 */
class SAHCF_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var SAHCF_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings handler.
	 *
	 * @var SAHCF_Settings
	 */
	public $settings;

	/**
	 * Frontend handler.
	 *
	 * @var SAHCF_Frontend
	 */
	public $frontend;

	/**
	 * Get singleton.
	 *
	 * @return SAHCF_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings = new SAHCF_Settings();
		$this->frontend = new SAHCF_Frontend();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'register_settings_page' ) );
		add_filter( 'plugin_action_links_' . SAHCF_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'soleman-auto-hide-checkout-field', false, dirname( SAHCF_BASENAME ) . '/languages' );
	}

	/**
	 * Register WooCommerce settings tab.
	 *
	 * @param array $pages Settings pages.
	 * @return array
	 */
	public function register_settings_page( $pages ) {
		$pages[] = include SAHCF_PATH . 'includes/class-sahcf-wc-settings-page.php';
		return $pages;
	}

	/**
	 * Plugin action links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=wc-settings&tab=sahcf_dynamic_fields' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( '設定', 'soleman-auto-hide-checkout-field' ) . '</a>'
		);
		return $links;
	}
}
