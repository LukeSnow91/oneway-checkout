<?php
/*
Plugin Name: WooCommerce Pacypay
Plugin URI: http://www.pacypay.com
Description: Integrates your Pacypay payment getway into your WooCommerce installation.
Version: 1.1.1
Author: Pacypay
Text Domain: pacypay
Author URI: http://www.pacypay.com
*/
add_action('plugins_loaded', 'init_pacypay_gateway', 0);

function init_pacypay_gateway() {
	if( !class_exists('WC_Payment_Gateway') )  return;
	
	require_once('class-wc-gateway-pacypay.php');
	
	require_once('class-wc-gateway-pacypay-creditcardonline.php');
	
	
	// Add the gateway to WooCommerce
	function add_pacypay_gateway( $methods )
	{
		return array_merge($methods, 
				array(
						'WC_Gateway_Pacypay', 
						'WC_Gateway_Pacypay_Creditcardonline'
						));
	}
	add_filter('woocommerce_payment_gateways', 'add_pacypay_gateway' );
	
	function wc_pacypay_plugin_edit_link( $links ){
		return array_merge(
				array(
						'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_pacypay') . '">'.__( 'Settings', 'alipay' ).'</a>'
				),
				$links
		);
	}
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_pacypay_plugin_edit_link' );
}
?>
