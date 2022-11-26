<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once( 'class-wc-gateway-pacypay.php' );

/**
 * Pacypay 
 *
 * @class 		WC_Gateway_Pacypay_Creditcardonline
 * @extends		WC_Payment_Gateway
 * @author 		Pacypay
 */
class WC_Gateway_Pacypay_Creditcardonline extends WC_Gateway_Pacypay {
	public $title = 'Checkout with Onerway';
	protected $pm_id = 'creditcard_plm';
}