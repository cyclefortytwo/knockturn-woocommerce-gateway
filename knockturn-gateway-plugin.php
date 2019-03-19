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
			$this->id = 'knockturn'; 
        	$this->icon = apply_filters( 'woocommerce_knockturn_icon', plugins_url().'/knockturn-woocommerce-gateway/assets/icon.png' );
			$this->has_fields = false; 
			$this->method_title = 'Knockturn Allee Grin Payments';
			$this->method_description = 'Pay with Grins keeping your privacy'; 		 
			$this->supports = array(
				'products'
			);
		 
			$this->init_form_fields();
		 
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->gateway_url = $this->get_option( 'gateway_url' );
			$this->merchant_id = $this->get_option( 'merchant_id' );
			$this->payment_message = $this->get_option( 'payment_message' );
			$this->api_key = $this->get_option( 'api_key' );
			$this->confirmations = $this->get_option( 'confirmations' );
		 
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		 
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		 
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
				'gateway_url' => array(
					'title'       => 'Gateway URL',
					'type'        => 'text',
					'default'	  => 'http://castle.yourowncryp.to:3000/merchants/a/payments'
				),
				'merchant_id' => array(
					'title'       => 'Merchant ID',
					'type'        => 'text',
					'default'	  => 'a'
				),
				'payment_message' => array(
					'title'       => 'Payment message',
					'type'        => 'text',
					'description' => 'Message in Grin slate (usually a store name)',
					'default'	  => 'WooCommerce'
				),
				'confirmations' => array(
					'title'       => 'Number of block confirmations for a payment',
					'type'        => 'text',
					'default'	  => '5'
				),
				'api_key' => array(
					'title'       => 'API Key',
					'type'        => 'password'
				)
			);
	 	}
 
	 
	 
		public function process_payment( $order_id ) {
			global $woocommerce;
			 
			$order = wc_get_order( $order_id );
			 
			$args = array( 
				'order_id' => (string) $order_id,
				'amount' => array (
					'amount' => (int)( $order->get_total() * 100),
					'currency' => $order->get_currency(),
				),
				'message' => $this->payment_message,
				'confirmations' => (int)$this->confirmations,
				'redirect_url' => $order->get_checkout_order_received_url(),

			);
		 
			 $response = wp_remote_post( $this->gateway_url, array(
				 'headers'     => array(
					'Content-Type' => 'application/json; charset=utf-8',
				 	'Authorization' => 'Basic ' . base64_encode( $this->merchant_id . ':' . $this->api_key )
					 ),
    				'body'        => json_encode($args),
    				'method'      => 'POST',
    				'data_format' => 'body',
			 ));
		 
		 
			 if(is_wp_error( $response ) ) {
				wc_add_notice(  'Connection error.', 'error' );
				return;
			 }

			 if ($response['response']['code'] != 201) {
				wc_add_notice(  'Unexpected payment service response.', 'error' );
				return;
			 }
		 
			  $body = json_decode( $response['body']);
		 	  $order->add_order_note( 'Payment started', true );
			  $woocommerce->cart->empty_cart();
			  return array(
						'result' => 'success',
						'redirect' => sprintf("%s/%s", $this->gateway_url, $body->id),
					);
		 
	 	}
 
		public function webhook() {
			$body = file_get_contents('php://input');
			$req = json_decode($body);
			if ((!isset($req->token)) || ($req->token != $this->api_key)) {
				header("HTTP/1.1 401 Unauthorized");
				exit;
			} 
			if ((!isset($req->external_id)) || (!isset($req->status))) {
				header("HTTP/1.1 400 Bad Request");
				exit;
			}
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
