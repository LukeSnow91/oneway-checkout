<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

include_once( 'class-wc-gateway-pacypay-response.php' );

/**
 * Handles responses from Pacypay Notify
 */
class WC_Gateway_Pacypay_Notify_Handler extends WC_Gateway_Pacypay_Response {

	/**
	 * Constructor
	 */
	public function __construct( $sandbox = false) {
		add_action( 'woocommerce_api_wc_gateway_pacypay', array( $this, 'check_response' ) );
		add_action( 'valid-pacypay-notify', array( $this, 'valid_response' ) );

		$this->sandbox        = $sandbox;
	}

	/**
	 * Check for Pacypay Notify Response
	 */
	public function check_response() {

		$params = json_decode(@file_get_contents("php://input") , true);

		if ( ! empty( $params ) && $this->validate_notify($params) ) {

			do_action( "valid-pacypay-notify", $params );
			exit;
		}

		WC_Gateway_Pacypay::log( 'Pacypay Notify failed');
		//wp_die( "Pacypay Notify failed", "Pacypay Notify", array( 'response' => 400 ) );
	}

	/**
	 * There was a valid response
	 * @param  array $posted Post data after wp_unslash
	 */
	public function valid_response( $posted ) {

		$BillNo = isset($posted['transactionId'])?$posted['transactionId']:null;
		$Currency = isset($posted['currency'])?$posted['currency']:null;
		$Amount = isset($posted['amount'])?$posted['amount']:null;
		$Succeed = isset($posted['responseCode'])?$posted['responseCode']:null;
		$state = $this->Get_State($Succeed);
		$Remark = isset($posted['message'])?$posted['message']:null;
		$timestamp = isset($posted['timestamp'])?$posted['timestamp']:null;
		$HASH = isset($posted['sign'])?$posted['sign']:null;
		$transaction_id = isset($posted['uniqueId'])?$posted['uniqueId']:null;
		if(!empty($BillNo))
		{
			$temp = explode("||" , $BillNo);
			$track_id = $temp[0] ;
			$sub_track_id = $temp[1] ;
		}
		if ( ! empty( $track_id ) && ( $order = $this->get_pacypay_order($track_id, $sub_track_id) ) ) {

			// if (!$this->isPacypay($order->payment_method)) {
			// 	if ($posted['state'] != 'completed') {
			// 		die ("payment method changed");
			// 	}
			// }
			
			WC_Gateway_Pacypay::log( 'Found order #' . $order->id );
			WC_Gateway_Pacypay::log( 'Payment status: ' . $state );

			if ( method_exists( __CLASS__, 'payment_status_' . $state ) ) {
				call_user_func( array( __CLASS__, 'payment_status_' . $state ), $order, $posted );
				die ($transaction_id);
			}
		} else {
			die ("order not found ");
		}
	}
	
	protected function isPacypay($payment_method) {
		return substr($payment_method, 0, strlen('pacypay')) === 'pacypay';
	}

	/**
	 * Check Pacypay notify validity
	 */
	public function validate_notify($params) {
		WC_Gateway_Pacypay::log( 'Checking Notify response is valid' );
		
		$pacypay = new WC_Gateway_Pacypay();
		$apiKey = $pacypay->get_option('api_key');
		$secretKey = $pacypay->get_option('secret_key');
		
        $HASH = "" ;
        if(!empty($params['sign']))
        {
            $HASH = $params['sign'];
        }

        $md5str = $this->ASCII_HASH($params , $secretKey);
        if(empty($HASH))
        {
			WC_Gateway_Pacypay::log( 'HASH is null' );
            return false;
        }
        else if($md5str == 'error')
        {
			WC_Gateway_Pacypay::log( 'Unable to encrypt signature' );
            return false ;
        }
        else
        {
            if($md5str != $HASH){
				$signtext = $this->ASCII_HASH_TEXT($params , $secretKey);

				WC_Gateway_Pacypay::log( 'String : '.$signtext );
				WC_Gateway_Pacypay::log( 'My sign : '.$md5str );
				WC_Gateway_Pacypay::log( 'System Sign : '.$HASH );
                return false;
            }
            else
            {
                return true ;
            }
        }
	}

	private function Get_State($code)
	{
		$state = 'pending' ;
		if($code == '88')
		{
			$state = 'completed' ;
		}
		else if($code == '28')
		{
			$state = 'cancelled' ;
		}
		else
		{
			$state = 'failed';
		}
		return $state ;
	}

	private function ASCII_HASH($params, $secretKey) {
        $PrivateKey = $secretKey;
        if(!empty($params)){
           $p =  ksort($params);
           if($p){
               $strs = '';
               foreach ($params as $k=>$val){
                   if(!empty($val) && $k != 'sign' && $k != 'wc-api')
                   {
                       $strs .= $val ;
                   }
               }
               $strs = $strs.$PrivateKey ;
               return hash('sha256' , $strs);
           }
        }
        return 'error';
	}

	private function ASCII_HASH_TEXT($params, $secretKey){
        $PrivateKey = $secretKey;
        if(!empty($params)){
            $p =  ksort($params);
            if($p){
                $strs = '';
                foreach ($params as $k=>$val){
                    if(!empty($val) && $k != 'sign' && $k != 'wc-api')
                    {
                        $strs .= $val ;
                    }
                }
                $strs = $strs.$PrivateKey ;
                return $strs;
            }
        }
        return 'error';
    }
	
	
	private function validate_amount_currency( $order, $posted ) {
		// Validate currency
		$order_amount = number_format( $order->get_total(), 2, '.', '' );
		$order_currency = $order->get_order_currency();
		$currency = $posted['currency'];
		$amount = $posted['amount'];
		$error = false;
		$error_amount = null;
		$error_currency = null;
		if ($order_currency == $currency) {
			if ($order_amount != $amount) {
				$error = 1;
				$error_amount = $amount;
			}
		} else {
			$currency_local = @$posted['currency_local'];
			if ($currency_local) {
				if ($order_currency == $currency_local) {
					$amount_local = @$posted['amount_local'];
					if ($order_amount != $amount_local) {
						$error = 1;
						$error_amount = $amount_local;
			        }
				} else {
					$error = 2;
					$error_currency = $currency_local;
				}
			} else {
				$error = 2;
				$error_currency = $currency;
			}
		}
		
		if (1 == $error) {
			WC_Gateway_Pacypay::log( 'Payment error: Amounts do not match (gross ' . $error_amount . ')' );
			
			// Put this order on-hold for manual checking
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: Pacypay amounts do not match (gross %s).', 'woocommerce' ), $error_amount ) );
			exit;
		} else if (2 == $error) {
			WC_Gateway_Pacypay::log( 'Payment error: Currencies do not match (sent "' . $order->get_order_currency() . '" | returned "' . $error_currency . '")' );
			
			// Put this order on-hold for manual checking
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: Pacypay currencies do not match (code %s).', 'woocommerce' ), $error_currency ) );
			exit;
		}
	}
	
	/**
	 * Check currency from Notify matches the order
	 * @param  WC_Order $order
	 * @param  string $currency
	 */
	private function validate_currency( $order, $currency ) {
		// Validate currency
		if ( $order->get_order_currency() != $currency ) {
			WC_Gateway_Pacypay::log( 'Payment error: Currencies do not match (sent "' . $order->get_order_currency() . '" | returned "' . $currency . '")' );

			// Put this order on-hold for manual checking
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: Pacypay currencies do not match (code %s).', 'woocommerce' ), $currency ) );
			exit;
		}
	}

	/**
	 * Check payment amount from Notify matches the order
	 * @param  WC_Order $order
	 */
	private function validate_amount( $order, $amount ) {
		if ( number_format( $order->get_total(), 2, '.', '' ) != number_format( $amount, 2, '.', '' ) ) {
			WC_Gateway_Pacypay::log( 'Payment error: Amounts do not match (gross ' . $amount . ')' );

			// Put this order on-hold for manual checking
			$order->update_status( 'on-hold', sprintf( __( 'Validation error: Pacypay amounts do not match (gross %s).', 'woocommerce' ), $amount ) );
			exit;
		}
	}

	/**
	 * Handle a completed payment
	 * @param  WC_Order $order
	 */
	private function payment_status_completed( $order, $posted ) {
		if ( $order->has_status( 'completed' ) || $order->has_status( 'processing' ) ){
			WC_Gateway_Pacypay::log( 'Aborting, Order #' . $order->id . ' is already complete.' );
			echo $posted["uniqueId"];
			exit;
		}
		
		//$this->validate_amount_currency( $order, $posted );
		//$this->validate_currency( $order, $posted['currency'] );
		//$this->validate_amount( $order, $posted['amount'] );
		$Succeed = isset($posted['responseCode'])?$posted['responseCode']:null;
		$state = $this->Get_State($Succeed);
		if ( 'completed' === $state ) {
			$this->payment_complete( $order, 'Pacypay', __( 'Pacypay Notify payment completed', 'woocommerce' ) );
		} else {
			$this->payment_on_hold( $order, sprintf( __( 'Payment pending', 'woocommerce' )) );
		}
	}

	/**
	 * Handle a pending payment
	 * @param  WC_Order $order
	 */
	private function payment_status_pending( $order, $posted ) {
		$this->payment_status_completed( $order, $posted );
	}

	/**
	 * Handle a failed payment
	 * @param  WC_Order $order
	 */
	private function payment_status_failed( $order, $posted ) {

		if ( $order->has_status( 'completed' ) || $order->has_status( 'processing' ) ) {
			WC_Gateway_Pacypay::log( 'Aborting, Order #' . $order->id . ' is already complete.' );
			echo $posted["uniqueId"];
			exit;
		}

		$Succeed = isset($posted['responseCode'])?$posted['responseCode']:null;
		$state = $this->Get_State($Succeed);
		$order->update_status( 'failed', sprintf( __( 'Payment %s via Pacypay Notify.', 'woocommerce' ), wc_clean( $state ) ) );
	}

	/**
	 * Handle a cancelled by user payment
	 * @param  WC_Order $order
	 */
	private function payment_status_cancelled_by_user( $order, $posted ) {
		$this->payment_status_failed( $order, $posted );
	}
	
	/**
	 * Handle a cancelled payment
	 * @param  WC_Order $order
	 */
	private function payment_status_cancelled( $order, $posted ) {
		$this->payment_status_failed( $order, $posted );
	}

	
	/**
	 * Handle an expired payment
	 * @param  WC_Order $order
	 */
	private function payment_status_expired( $order, $posted ) {
		$this->payment_status_failed( $order, $posted );
	}

	/**
	 * Handle a rejected payment
	 * @param  WC_Order $order
	 */
	private function payment_status_rejected_by_bank( $order, $posted ) {
		$this->payment_status_failed( $order, $posted );
	}
	
	/**
	 * Handle a error payment
	 * @param  WC_Order $order
	 */
	private function payment_status_error( $order, $posted ) {
		$this->payment_status_failed( $order, $posted );
	}
	

	/**
	 * Handle a refunded order
	 * @param  WC_Order $order
	 */
	private function payment_status_refunded( $order, $posted ) {
		// Only handle full refunds, not partial
		if ( $order->get_total() == ( $posted['mc_gross'] * -1 ) ) {

			// Mark order as refunded
			$order->update_status( 'refunded', sprintf( __( 'Payment %s via Pacypay Notify.', 'woocommerce' ), strtolower( $posted['state'] ) ) );

			$this->send_email_notification(
				sprintf( __( 'Payment for order #%s refunded/reversed', 'woocommerce' ), $order->get_order_number() ),
				sprintf( __( 'Order %s has been marked as refunded', 'woocommerce' ), $order->get_order_number())
			);
		}
	}

	/**
	 * Handle a chargeback
	 * @param  WC_Order $order
	 */
	private function payment_status_chargeback( $order, $posted ) {
		$order->update_status( 'on-hold', sprintf( __( 'Payment %s via Pacypay Notify.', 'woocommerce' ), wc_clean( $posted['state'] ) ) );

		$this->send_email_notification(
			sprintf( __( 'Payment for order #%s reversed', 'woocommerce' ), $order->get_order_number() ),
			sprintf( __( 'Order %s has been marked on-hold due to a chargeback', 'woocommerce' ), $order->get_order_number() )
		);
	}

	/**
	 * Send a notification to the user handling orders.
	 * @param  string $subject
	 * @param  string $message
	 */
	private function send_email_notification( $subject, $message ) {
		$new_order_settings = get_option( 'woocommerce_new_order_settings', array() );
		$mailer             = WC()->mailer();
		$message            = $mailer->wrap_message( $subject, $message );

		$mailer->send( ! empty( $new_order_settings['recipient'] ) ? $new_order_settings['recipient'] : get_option( 'admin_email' ), $subject, $message );
	}
}
?>
