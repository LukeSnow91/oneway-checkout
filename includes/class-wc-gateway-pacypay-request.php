<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

include_once( 'class-wc-gateway-pacypay-order.php' );

/**
 * Generates requests to send to Pacypay
 */
class WC_Gateway_Pacypay_Request {

	/**
	 * Stores line items to send to Pacypay
	 * @var array
	 */
	protected $line_items = array();

	/**
	 * Pointer to gateway making the request
	 * @var WC_Gateway_Pacypay
	 */
	protected $gateway;

	/**
	 * Endpoint for requests from Pacypay
	 * @var string
	 */
	protected $notify_url;

	protected $return_url;

	/**
	 * Constructor
	 * @param WC_Gateway_Pacypay $gateway
	 */
	public function __construct( $gateway ) {
		$this->gateway    = $gateway;
		$this->notify_url = WC()->api_request_url( 'WC_Gateway_Pacypay' );
		$this->return_url = WC()->api_request_url( 'Callback' );
	}

	/**
	 * Get the Pacypay request URL for an order
	 * @param  WC_Order  $order
	 * @param  boolean $sandbox
	 * @return string
	 */
	public function get_request_url( $order, $sandbox = false ) {
		$Pacypay_args = $this->get_pacypay_args( $order );

		if ( $sandbox ) {
			$url = 'https://sandbox-pg.pacypay.com/payment';
			$return = $this->vpost($url, $Pacypay_args);
			$result = json_decode($return,true);
			return $result ;
		} else {
			$url = 'https://pg.pacypay.com/payment';
			$return = $this->vpost($url, $Pacypay_args);
			$result = json_decode($return,true);
			return $result ;
		}
	}

	/**
	 * Get Pacypay Args for passing to PP
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	protected function get_pacypay_args( $order ) {
		$order = new WC_Gateway_Pacypay_Order($order);
		WC_Gateway_Pacypay::log( 'Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $this->notify_url );

		$order_total = number_format($order->order_total, 2, '.', '');
		$strProduits = $this->get_order_item_names($order);
		$phone = $this->get_phone_number_args($order);
		$pacypay = new WC_Gateway_Pacypay();
		
		$data = array(
				'merchantNo' => $this->gateway->get_apikey(),
				'transactionId' => $order->id.'||'.$order->order_key.'||'.date('His'),
				'transactionType' => 'Checkout',
				'transactionMethod' => 'ALL',
				'currency' => get_woocommerce_currency(),
				'amount' => $order_total,
				'products' => $strProduits,
				'returnUrl' => $this->return_url,
				'notifyUrl' => $this->notify_url,
				'firstName' => trim($order->billing_first_name),
				'lastName' => trim($order->billing_last_name),
				'phone' => trim($order->billing_phone),
				'email'=> trim($order->billing_email),
				'zipCode'=> trim($order->billing_postcode),
				'address'=> trim($order->billing_address_1.' '.$order->billing_address_2),
				'country' => trim($order->billing_country),
				'state' => trim($this->get_Pacypay_state( $order->billing_country, $order->billing_state )),
				'city' => trim($order->billing_city),
				'shippingFirstName' => trim($order->billing_first_name),
				'shippingLastName' => trim($order->billing_last_name),
				'shippingPhone' => trim($order->billing_phone),
				'shippingEmail' => trim($order->billing_email),
				'shippingZipCode' => trim($order->billing_postcode),
				'shippingAddress' => trim($order->billing_address_1.' '.$order->billing_address_2),
				'shippingCountry' => trim($order->billing_country),
				'shippingCity' => trim($order->billing_city),
				'shippingState' => trim($this->get_Pacypay_state( $order->billing_country, $order->billing_state ))
		);
		$signlogtext = $this->ASCII_HASH_TEXT($data, $this->gateway->get_secretkey());
		WC_Gateway_Pacypay::log( 'Sign Text : ' . $signlogtext );
		$data['sign'] = $this->generateSignature($data, $this->gateway->get_secretkey());
		WC_Gateway_Pacypay::log( 'Sha256 : ' . $data['sign'] );
		return $data;
	}

	private function generateSignature($params, $secretKey) {
        $PrivateKey = $secretKey;
        if(!empty($params)){
           $p =  ksort($params);
           if($p){
               $strs = '';
               foreach ($params as $k=>$val){
                   if(!empty($val) && $k != 'sign' && $k != 'route')
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
                    if(!empty($val) && $k != 'sign' && $k != 'route')
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

	private function vpost($url, $data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_TIMEOUT, 300);
        $tmpInfo = curl_exec($curl);
        $error = curl_errno($curl);

        curl_close($curl);
        
        if($error){
            return false;
        }
        return $tmpInfo;
    }

	/**
	 * Get phone number args for Pacypay request
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_phone_number_args( $order ) {
		$order = new WC_Gateway_Pacypay_Order($order);
		if ( in_array( $order->billing_country, array( 'US','CA' ) ) ) {
			$phone_number = str_replace( array( '(', '-', ' ', ')', '.' ), '', $order->billing_phone );
			$phone_args   = array(
				'night_phone_a' => substr( $phone_number, 0, 3 ),
				'night_phone_b' => substr( $phone_number, 3, 3 ),
				'night_phone_c' => substr( $phone_number, 6, 4 ),
				'day_phone_a' 	=> substr( $phone_number, 0, 3 ),
				'day_phone_b' 	=> substr( $phone_number, 3, 3 ),
				'day_phone_c' 	=> substr( $phone_number, 6, 4 )
			);
		} else {
			$phone_args = array(
				'night_phone_b' => $order->billing_phone,
				'day_phone_b' 	=> $order->billing_phone
			);
		}
		return $phone_args;
	}

	/**
	 * Get shipping args for Pacypay request
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_shipping_args( $order ) {
		$order = new WC_Gateway_Pacypay_Order($order);
		$shipping_args = array();

		if ( 'yes' == $this->gateway->get_option( 'send_shipping' ) ) {
			$shipping_args['address_override'] = $this->gateway->get_option( 'address_override' ) === 'yes' ? 1 : 0;
			$shipping_args['no_shipping']      = 0;

			// If we are sending shipping, send shipping address instead of billing
			$shipping_args['first_name']       = $order->shipping_first_name;
			$shipping_args['last_name']        = $order->shipping_last_name;
			$shipping_args['company']          = $order->shipping_company;
			$shipping_args['address1']         = $order->shipping_address_1;
			$shipping_args['address2']         = $order->shipping_address_2;
			$shipping_args['city']             = $order->shipping_city;
			$shipping_args['state']            = $this->get_Pacypay_state( $order->shipping_country, $order->shipping_state );
			$shipping_args['country']          = $order->shipping_country;
			$shipping_args['zip']              = $order->shipping_postcode;
		} else {
			$shipping_args['no_shipping']      = 1;
		}

		return $shipping_args;
	}

	/**
	 * Get line item args for Pacypay request
	 * @param  WC_Order $order
	 * @return array
	 */
	protected function get_line_item_args( $order ) {
		/**
		 * Try passing a line item per product if supported
		 */
		if ( ( ! wc_tax_enabled() || ! wc_prices_include_tax() ) && $this->prepare_line_items( $order ) ) {

			$line_item_args             = $this->get_line_items();
			$line_item_args['tax_cart'] = $order->get_total_tax();

			if ( $order->get_total_discount() > 0 ) {
				$line_item_args['discount_amount_cart'] = round( $order->get_total_discount(), 2 );
			}

		/**
		 * Send order as a single item
		 *
		 * For shipping, we longer use shipping_1 because Pacypay ignores it if *any* shipping rules are within Pacypay, and Pacypay ignores anything over 5 digits (999.99 is the max)
		 */
		} else {

			$this->delete_line_items();

			$this->add_line_item( $this->get_order_item_names( $order ), 1, number_format( $order->get_total() - round( $order->get_total_shipping() + $order->get_shipping_tax(), 2 ), 2, '.', '' ), $order->get_order_number() );
			$this->add_line_item( sprintf( __( 'Shipping via %s', 'woocommerce' ), ucwords( $order->get_shipping_method() ) ), 1, number_format( $order->get_total_shipping() + $order->get_shipping_tax(), 2, '.', '' ) );

			$line_item_args = $this->get_line_items();
		}

		return $line_item_args;
	}

	/**
	 * Get order item names as a string
	 * @param  WC_Order $order
	 * @return string
	 */
	protected function get_order_item_names( $order ) {
		$item_names = array();

		foreach ( $order->get_items() as $item ) {
			$item_names[] = $item['name'] . ' x ' . $item['qty'];
		}

		return implode( ', ', $item_names );
	}

	/**
	 * Get order item names as a string
	 * @param  WC_Order $order
	 * @param  array $item
	 * @return string
	 */
	protected function get_order_item_name( $order, $item ) {
		$item_name = $item['name'];
		$item_meta = new WC_Order_Item_Meta( $item['item_meta'] );

		if ( $meta = $item_meta->display( true, true ) ) {
			$item_name .= ' ( ' . $meta . ' )';
		}

		return $item_name;
	}

	/**
	 * Return all line items
	 */
	protected function get_line_items() {
		return $this->line_items;
	}

	/**
	 * Remove all line items
	 */
	protected function delete_line_items() {
		$this->line_items = array();
	}

	/**
	 * Get line items to send to Pacypay
	 *
	 * @param  WC_Order $order
	 * @return bool
	 */
	protected function prepare_line_items( $order ) {
		$order = new WC_Gateway_Pacypay_Order($order);
		$this->delete_line_items();
		$calculated_total = 0;

		// Products
		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item ) {
			if ( 'fee' === $item['type'] ) {
				$line_item        = $this->add_line_item( $item['name'], 1, $item['line_total'] );
				$calculated_total += $item['line_total'];
			} else {
				$product          = $order->get_product_from_item( $item );
				$line_item        = $this->add_line_item( $this->get_order_item_name( $order, $item ), $item['qty'], $order->get_item_subtotal( $item, false ), $product->get_sku() );
				$calculated_total += $order->get_item_subtotal( $item, false ) * $item['qty'];
			}

			if ( ! $line_item ) {
				return false;
			}
		}

		// Shipping Cost item - Pacypay only allows shipping per item, we want to send shipping for the order
		if ( $order->get_total_shipping() > 0 && ! $this->add_line_item( sprintf( __( 'Shipping via %s', 'woocommerce' ), $order->get_shipping_method() ), 1, round( $order->get_total_shipping(), 2 ) ) ) {
			return false;
		}

		// Check for mismatched totals
		if ( wc_format_decimal( $calculated_total + $order->get_total_tax() + round( $order->get_total_shipping(), 2 ) - round( $order->get_total_discount(), 2 ), 2 ) != wc_format_decimal( $order->get_total(), 2 ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add Pacypay Line Item
	 * @param string  $item_name
	 * @param integer $quantity
	 * @param integer $amount
	 * @param string  $item_number
	 * @return bool successfully added or not
	 */
	protected function add_line_item( $item_name, $quantity = 1, $amount = 0, $item_number = '' ) {
		$index = ( sizeof( $this->line_items ) / 4 ) + 1;

		if ( ! $item_name || $amount < 0 || $index > 9 ) {
			return false;
		}

		$this->line_items[ 'item_name_' . $index ]   = html_entity_decode( wc_trim_string( $item_name, 127 ), ENT_NOQUOTES, 'UTF-8' );
		$this->line_items[ 'quantity_' . $index ]    = $quantity;
		$this->line_items[ 'amount_' . $index ]      = $amount;
		$this->line_items[ 'item_number_' . $index ] = $item_number;

		return true;
	}

	/**
	 * Get the state to send to Pacypay
	 * @param  string $cc
	 * @param  string $state
	 * @return string
	 */
	protected function get_Pacypay_state( $cc, $state ) {
		if ( 'US' === $cc ) {
			return $state;
		}

		$states = WC()->countries->get_states( $cc );

		if ( isset( $states[ $state ] ) ) {
			return $states[ $state ];
		}

		return $state;
	}
}
?>