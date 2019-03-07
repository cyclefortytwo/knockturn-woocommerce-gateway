<?php
/*
 * Plugin Name: WooCommerce Knockturn Allee Payment Gateway
 * Plugin URI: https://cycle42.com/Knockturn/woocommerce-gateway-plugin.html
 * Description: Accept grins.
 * Author: cycle42
 * Author URI: https://cycle42.com
 * Version: 1.0.0
 */



add_filter( 'woocommerce_payment_gateways', 'knockturn_add_gateway_class' );
function knockturn_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_Knockturn_Gateway';
	return $gateways;
}
 
add_action( 'plugins_loaded', 'knockturn_init_gateway_class' );
function Knockturn_init_gateway_class() {
 
	class WC_Knockturn_Gateway extends WC_Payment_Gateway {
 
 		public function __construct() {
			$this->id = 'knockturn'; // payment gateway plugin ID
        	$this->icon = apply_filters( 'woocommerce_knockturn_icon', plugins_url().'/knockturn-woocommerce-gateway/assets/icon.png' );
			$this->has_fields = false; // in case you need a custom credit card form
			$this->method_title = 'Knockturn Allee Grin Payments';
			$this->method_description = 'Pay with Grins keeping your privacy'; // will be displayed on the options page
		 
			// gateways can support subscriptions, refunds, saved payment methods,
			// but in this tutorial we begin with simple payments
			$this->supports = array(
				'products'
			);
		 
			// Method with all the options fields
			$this->init_form_fields();
		 
			// Load the settings.
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->gateway_url = $this->get_option( 'gateway_url' );
			$this->merchant_id = $this->get_option( 'merchant_id' );
			$this->testmode = 'yes' === $this->get_option( 'testmode' );
			$this->api_key = $this->testmode ? $this->get_option( 'test_api_key' ) : $this->get_option( 'api_key' );
		 
			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		 
			// We need custom JavaScript to obtain a token
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		 
			// You can also register a webhook here
			add_action( 'woocommerce_api_knockturn', array( $this, 'webhook' ) ); 
 		}
 
 		public function init_form_fields(){
 			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable Knockturn Allee Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'yes'
				),
				'title' => array(
					'title'       => 'Pay with grin',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'Pay with grin',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with grin on Knockturn Allee',
				),
				'testmode' => array(
					'title'       => 'Test mode',
					'label'       => 'Enable Test Mode',
					'type'        => 'checkbox',
					'description' => 'Place the payment gateway in test mode using test API keys.',
					'default'     => 'no',
					'desc_tip'    => true,
				),
				'gateway_url' => array(
					'title'       => 'Gateway URL',
					'type'        => 'text',
					'default'	  => 'http://castle.yourowncryp.to:3000/merchants/a/orders'
				),
				'merchant_id' => array(
					'title'       => 'Merchant ID',
					'type'        => 'text',
					'default'	  => 'a'
				),
				'test_api_key' => array(
					'title'       => 'Test API Key',
					'type'        => 'password',
				),
				'api_key' => array(
					'title'       => 'Live API Key',
					'type'        => 'password'
				)
			);
	 	}
 
	 
	 
		public function process_payment( $order_id ) {
				global $woocommerce;
			 
				// we need it to get any order detailes
				$order = wc_get_order( $order_id );
			 
			 
				/*
			 	 * Array with parameters for API interaction
				 */
				$args = array( 
					'order_id' => (string) $order_id,
					'amount' => array (
						'amount' => (int)( $order->get_total() * 100),
						'currency' => $order->get_currency(),
					),
					'confirmations' => 1,

				);
			 
				/*
				 * Your API interaction could be built with wp_remote_post()
			 	 */
				 $response = wp_remote_post( $this->gateway_url, array(
					 'headers'     => array(
						'Content-Type' => 'application/json; charset=utf-8',
					 	'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->api_key )
						 ),
    					'body'        => json_encode($args),
    					'method'      => 'POST',
    					'data_format' => 'body',
				 ));
			 
			 
				 if( !is_wp_error( $response ) ) {
			 
				  $body = json_decode( $response['body']);
			 
					 //if ( $body['response'] == 'APPROVED' ) {
			 
						// we received the payment
								 
						// some notes to customer (replace true with false to make it private)
						$order->add_order_note( 'Hey, your ! Thank you!', true );
			 
						// Empty cart
						$woocommerce->cart->empty_cart();
			 
						// Redirect to the thank you page
						return array(
							'result' => 'success',
							'redirect' => sprintf("%s/%s", $this->gateway_url, $body->id),
						);
			 
					 //} 			 
				} else {
					wc_add_notice(  'Connection error.', 'error' );
					return;
				}
			 
 
	 	}
 
		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
			$body = file_get_contents('php://input');
			$req = json_decode($body);
			$order = wc_get_order( (int)$req->external_id );
			if ($req->status == 'Confirmed') {
			   	$order->payment_complete($req->id);
				$order->reduce_order_stock();
			} else {
				$order->update_status('cancelled', 'Knockturn Allee rejected the payment ');
			}
 
	 	}
 	}
}
