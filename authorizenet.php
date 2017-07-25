<?php
/**
 * Plugin Name: WP eCommerce Authorize.net Gateway
 * Plugin URI: http://wpecommerce.org
 * Description: Authorize.net Payment Gateway
 * Version: 1.0.0
 * Author: WP eCommerce
 * Author URI: https://wpecommerce.org/
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WPEC_ANET_PLUGIN_DIR' ) ) {
	define( 'WPEC_ANET_PLUGIN_DIR', dirname( __FILE__ ) );
}
if ( ! defined( 'WPEC_ANET_PLUGIN_URL' ) ) {
	define( 'WPEC_ANET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'WPEC_ANET_VERSION' ) ) {
	define( 'WPEC_ANET_VERSION', '1.0.0' );
}
if ( ! defined( 'WPEC_ANET_PRODUCT_ID' ) ) {
	define( 'WPEC_ANET_PRODUCT_ID', 481745 );
}

function wpec_authnet_register_file() {
	wpsc_register_payment_gateway_file( dirname(__FILE__) . '/authorize-net.php' );
}
add_filter( 'wpsc_init', 'wpec_authnet_register_file' );

if( is_admin() ) {
	// setup the updater
	if( ! class_exists( 'WPEC_Product_Licensing_Updater' ) ) {
		// load our custom updater
		include( dirname( __FILE__ ) . '/WPEC_Product_Licensing_Updater.php' );
	}
	function wpec_authnet_plugin_updater() {
		// retrieve our license key from the DB
		$license = get_option( 'wpec_product_'. WPEC_ANET_PRODUCT_ID .'_license_active' );
		$key = ! $license ? '' : $license->license_key;
		// setup the updater
		$wpec_updater = new WPEC_Product_Licensing_Updater( 'https://wpecommerce.org', __FILE__, array(
				'version' 	=> WPEC_ANET_VERSION, 				// current version number
				'license' 	=> $key, 		// license key (used get_option above to retrieve from DB)
				'item_id' 	=> WPEC_ANET_PRODUCT_ID 	// id of this plugin
			)
		);
	}
	add_action( 'admin_init', 'wpec_authnet_plugin_updater', 0 );
}