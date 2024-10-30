<?php
/**
 * Plugin Name: Lamium Decentralized Crypto Payment Plugin
 * Plugin URI: https://www.lamium.io/merchant
 * Description: This plugin integrates crypto currency payments into your webstore(Bitcoin and Dash).
 *The lamium Api allows merchants to accept the bitcoin or dash directly to their wallet
 *or convert them automatically into EUR,CHF or USD via our decentralized invoice service.
 * 
 * Author: Kryptolis AG
 * Author URI: https://www.lamium.io/
 * Version: 2.1.6
 * Text Domain: woocommerce-gateway-lamium-accept-bitcoin-api
 * Domain Path: /languages/
 *
 * Copyright: (c) 2019 Kryptolis AG (support@lamium.io) and WooCommerce
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package   woocommerce-gateway-lamium-accept-bitcoin-api
 * @author    Kryptolis AG
 * @category  Admin
 * @copyright Copyright (c) 2019, Kryptolis AG and WooCommerce
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 *
 * This offline gateway forks the WooCommerce core "Cheque" payment gateway to create a bitcoin to fiat conversion payment plugin.
 */

 
defined( 'ABSPATH' ) or exit;


//exit;

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

register_activation_hook(__FILE__,'lamiumActivationForCryptoPay');
add_action( 'wp', 'lamiumActivationForCryptoPay' );

function lamiumActivationForCryptoPay() {
   //if (! wp_next_scheduled ( 'lamium_hourly_event_for_crypto_pay' )) {
	 wp_schedule_event(time(), 'hourly', 'lamium_hourly_event_for_crypto_pay');
   // }
}
//updates order status of pending orders by connecting to the coinnexus api
add_action('lamium_hourly_event_for_crypto_pay','lamium_do_this_hourly_for_crypto_pay');
register_deactivation_hook(__FILE__, 'LamiumDeactivationForCryptoPay');
function lamium_do_this_hourly_for_crypto_pay()
{	                 
	$lamiumPaymentObj = new WC_Gateway_Lamium_Accept_Crypto_Api;
	$lamiumPaymentObj->do_this_hourly_for_crypto_pay();
}
function LamiumDeactivationForCryptoPay() {
	wp_clear_scheduled_hook('lamium_hourly_event_for_crypto_pay');
}


/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + lamium accept crypto api gateway
 */
function wc_lamium_accept_crypto_api_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Lamium_Accept_Crypto_Api';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_lamium_accept_crypto_api_add_to_gateways' );


/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */ 
function wc_lamium_accept_crypto_api_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=lamium_accept_crypto_api_gateway' ) . '">' . __( 'Configure', 'wc-gateway-fiat-to-crypto-coinnexus-api' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_lamium_accept_crypto_api_gateway_plugin_links' );


/**
 * Crypto Currency To Fiat or Crypto Currency Lamium Api 
 *
 * Lamium Crypto payment gateway that allows you to accept EUR, USD or CHF payments without an own bank account and coverts them directly into crypto currency.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class 		WC_Gateway_Lamium_Accept_Crypto_Api
 * @extends		WC_Payment_Gateway
 * @version		2.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Kryptolis AG
 */
add_action( 'plugins_loaded', 'wc_lamium_accept_crypto_api_gateway_init', 11 );

function wc_lamium_accept_crypto_api_gateway_init() {

	class WC_Gateway_Lamium_Accept_Crypto_Api extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
			global $wp_session;
	  
			$this->id                 = 'lamium_accept_crypto_api_gateway';
			$this->icon               = apply_filters('woocommerce_lamium_accept_crypto_api_gateway_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Lamium accept crypto api', 'wc-gateway-lamium-accept-crypto-api' );
			$this->method_description = __( 'Allows to accept payments in crypto currency. Very handy if you use your cheque gateway for another payment method, and can help with testing. Orders are marked as "payment-pending" when received.', 'wc-gateway-lamium-accept-crypto-api' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->username  = $this->get_option( 'username' );
			$this->password  = $this->get_option( 'password' );
			$this->iban  = $this->get_option( 'iban' );
			$this->bic = $this->get_option( 'bic' );
			$this->fiat_pay_or_bitcoin_pay = $this->get_option( 'fiat_bitcoin' );
			$this->fiat_pay_or_dash_pay = $this->get_option( 'fiat_dash' );
			$this->full_name = $this->get_option( 'full_name' );
			$this->email_address = $this->get_option( 'email_address' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
		 
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		  
		  // Customer Emails
			//add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
			add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
			
		}
		public function payment_fields() {
 
 
	// I will echo() the form, but you can close PHP tags and print it directly in HTML
	echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';
	if('bitcoin_disabled'!=$this->fiat_pay_or_bitcoin_pay)
 	$cryptos[] ='Bitcoin';
 	if('dash_disabled'!=$this->fiat_pay_or_dash_pay)
 	$cryptos[] ='Dash';
	// Add this action hook if you want your custom payment gateway to support it
	do_action( 'woocommerce_crypto_form_start', $this->id );
	foreach($cryptos as $crypto)
	{
		echo '<input id="crypto_"'.$crypto.' type="radio" class="input-radio" name="crypto" value="'.$crypto.'" checked="" data-order_button_text="Pay with '.$crypto.'">

	<label for="payment_method_btcpay">
		Pay with '.$crypto.' 
	<div class="clear"></div>';
	}
	// I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
	// echo '<select name="crypto" id="crypto" class="" autocomplete="" tabindex="-1" aria-hidden="true"><option value="">Select a crypto currency…</option><option value="dash">Pay with Dash</option><option value="BTC">Pay with BTC</option></select>
		//<div class="clear"></div>';
 
	do_action( 'woocommerce_crypto_form_end', $this->id );
 
	echo '<div class="clear"></div></fieldset>';
 
}
	
	function register_plugin_styles() {
	    // wp_register_style( 'lamium-crypto', plugins_url( 'lamium-crypto/assets/lamium_plugin.css' ) );
	    wp_enqueue_style( 'lamium-crypto' );
	}
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_lamium_accept_crypto_api_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Lamium accept Crypto currency api', 'wc-gateway-lamium-accept-crypto-api' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'        => 'text',
					'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-lamium-accept-crypto-api' ),
					'default'     => __( 'Crypto Currency Payment', 'wc-gateway-lamium-accept-crypto-api' ),
					'desc_tip'    => true,
				),
				'username' => array(
					'title'       => __( 'Lamium username', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'        => 'text',
					'description' => __( 'Lamium api username', 'wc-gateway-lamium-accept-crypto-api' ),
					'default'     => __( '', 'wc-gateway-lamium-accept-crypto-api' ),
					'desc_tip'    => true,
				),
				'password' => array(
					'title'       => __( 'Lamium api password', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'        => 'text',
					'description' => __( 'Lamium api password', 'wc-gateway-lamium-accept-crypto-api' ),
					'default'     => __( '', 'wc-gateway-lamium-accept-crypto-api' ),
					'desc_tip'    => true,
				),
				'fiat_bitcoin'=> array(
					'title'       => __( 'Settings for Bitcoin', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'        => 'select',
					'description' => __( 'Please select your config for Bitcoin', 'wc-gateway-lamium-accept-crypto-api' ),
					'options'     => array(
										'bitcoin_fiat'   => __( 'Bitcoin accepted and converted to fiat'),
						                'bitcoin_wallet'   => __( 'Bitcoin accepted and paid to wallet' ),
						                'bitcoin_disabled'  => __( 'Bitcoin not accepted' ),
            						),
					'default'     => __( '', 'wc-gateway-lamium-accept-crypto-api' ),
					'desc_tip'    => true,
					),
				'fiat_dash'=> array(
					'title'       => __( 'Settings for Dash', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'        => 'select',
					'description' => __( 'Please select your config for Dash', 'wc-gateway-lamium-accept-crypto-api' ),
					'options'     => array(
										'dash_fiat'   => __( 'Dash accepted and converted to fiat' ),
						                'dash_wallet'   => __( 'Dash accepted and paid to wallet' ),
						                'dash_disabled'  => __( 'Dash not accepted' ),
            						),
					'default'     => __( '', 'wc-gateway-lamium-accept-crypto-api' ),
					'desc_tip'    => true,
					),
				'full_name' =>array(
					'title'       => __( 'Your full name', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'        => 'text',
					'description' => __( 'Your full name as in the bank account', 'wc-gateway-lamium-accept-crypto-api' ),
					'default'     => __( '', 'wc-gateway-lamium-accept-crypto-api' ),
					'desc_tip'    => true,
				),
				'email_address' =>array(
					'title'       => __( 'Your email address', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'        => 'text',
					'description' => __( 'Your email address(compulsory)', 'wc-gateway-lamium-accept-crypto-api' ),
					'default'     => __( '', 'wc-gateway-lamium-accept-crypto-api' ),
					'desc_tip'    => true,
				),

				'iban' => array(
					'title'       => __( 'Your IBAN', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'        => 'text',
					'description' => __( 'Bank details where coinnexus will deposit your fiat', 'wc-gateway-lamium-accept-crypto-api' ),
					'default'     => __( '', 'wc-gateway-lamium-accept-crypto-api' ),
					'desc_tip'    => true,
				),
				'bic' => array(
					'title'       => __( 'Your BIC', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'        => 'text',
					'description' => __( 'Bank details where Lamium will deposit your fiat', 'wc-gateway-lamium-accept-crypto-api' ),
					'default'     => __( '', 'wc-gateway-lamium-accept-crypto-api' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-lamium-accept-crypto-api' ),
					'default'     => __( 'Pay the cryptocurrency to the shown QR code', 'wc-gateway-lamium-accept-crypto-api' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-gateway-lamium-accept-crypto-api' ),
					'type'        => 'textarea',
					'description' => __( 'Pay the cryptocurrency to the shown QR code', 'wc-gateway-lamium-accept-crypto-api' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				print_r(WC()->session->get('lamiumData'));
			}
		}
		
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			$orderData = $order->get_data();
			if ( $this->instructions && ! $sent_to_admin && $this->id === $orderData['payment_method'] && $order->has_status( 'payment-pending' ) ) {
				echo wpautop( wptexturize( $this->instructions )) . PHP_EOL;
			}
		}

		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) 
		{	
			 	
			
			 try { 
				$order = wc_get_order( $order_id );
		 		$orderData = $order->get_data();
		 		$crypto = $this->_cryptoFilter($_POST['crypto']);
				$data = array(
					'username' =>$this->username,
	                'password'  => $this->password,
	               );
				$data = json_encode($data);
				$url = 'http://api.lamium.io/api/users/token';
	            $tokenRemoteCall = null;
	            $i = 0;
	            do {
	            	$tokenRemoteCall = $this->_getCoinnexusToken();
	            	$tokenRemoteCall =json_decode($tokenRemoteCall['body']);
	            	$i = $i +1;
	            }while (($i<3) && (@$tokenRemoteCall->success!=true));
	            if(empty($tokenRemoteCall->success)){
	            	
	            	//$this->_storeFailRecord($tokenRemoteCall,$url.'-- call failed',false,true);
	            	//$this->_fail($tokenRemoteCall,'Api login failed',$orderData);
	            }
	            $lamiumApiData['order_id_from_woocommerce']=$order_id;
	            $lamiumApiData['domain'] = get_home_url();
	            $lamiumApiData['crypto'] = $this->{"fiat_pay_or_".$crypto."_pay"};
	            $lamiumApiData['iban'] = $this->iban;
	            $lamiumApiData['bic_code'] = $this->bic;
	            $lamiumApiData['payer_name'] = $this->full_name;
	            $lamiumApiData['payer_email_address'] = $this->email_address;
	            $lamiumApiData['amount']= $orderData['total'];
	            $lamiumApiData['currency']= $orderData['currency'];
	            $lamiumApiData['purchase_bitcoin_agreement']= 1;
	            $lamiumApiData['customer_name']= $orderData['billing']['first_name'].'--'.$orderData['billing']['last_name'];
	            $lamiumApiData['customer_phone']= $orderData['billing']['phone'];
	            $lamiumApiData['customer_address']= $orderData['billing']['address_1'].'--'.$orderData['billing']['address_2'].'--'.$orderData['billing']['city'].'--'.
					$orderData['billing']['state'].'--'.$orderData['billing']['postcode'].'--'.$orderData['billing']['country'];	
	            $lamiumApiData['item']='url - '.get_home_url().'-pay with bitcoin request- Woocommerce Order id -'.$orderData['id'];
	            $lamiumApiData['vat_rate']=$orderData['total_tax'];
	            $lamiumApiData = json_encode($lamiumApiData);
	            $url = 'http://api.lamium.io/api/payments/payCryptoCoins';
	            $apiDataRemoteCall = null;
	            $i = 0;
	            do {
	            	$apiDataRemoteCall = $this->_wpRemoteCall($url,$lamiumApiData,$tokenRemoteCall->data->token);
	            	$apiDataRemoteCall =json_decode($apiDataRemoteCall['body']);
	            	$i = $i +1;
	            }while (($i<3) && (@$apiDataRemoteCall->success!=true));
	            if(empty($apiDataRemoteCall->success)){
	            	update_post_meta( $order_id , '_lamium_api_error',$apiDataRemoteCall);
	            	update_post_meta( $order_id , '_lamium_api_error_url',$url.'--- plugin url--'.get_home_url().
	            	 '---call failed');
	            }
				// Mark as payment-pending (we're awaiting the payment)
			    $order->update_status( 'payment-pending', __( 'Awaiting fiat payment', 'wc-gateway-lamium-accept-bitcoin-api' ) );
				update_post_meta( $order_id , '_lamium_merchant_id',$apiDataRemoteCall->data[0]->merchant_id);
				update_post_meta( $order_id , '_lamium_transaction_id',$apiDataRemoteCall->data[0]->transaction_id);

				update_post_meta( $order_id , '_crypto',$apiDataRemoteCall->data[0]->crypto_setting);
	 			update_post_meta( $order_id , '_lamium_customer_reference',$apiDataRemoteCall->data[0]->customer_reference);
				update_post_meta( $order_id , '_lamium_btc_address',$apiDataRemoteCall->data[0]->btc_address);
				update_post_meta( $order_id , '_lamium_btc_amount',$apiDataRemoteCall->data[0]->btc_amount);
				update_post_meta( $order_id , '_lamium_btc_api_payment_id',$apiDataRemoteCall->data[0]->btcserver_api_payment_id);
				 
				 // Reduce stock levels
				$order->reduce_order_stock();
			 	// Remove cart
			    WC()->cart->empty_cart();
			    if($apiDataRemoteCall->data[0]->bitalo_link)
			    {
			    	$bitaloLinkHtml = '<p>'.$apiDataRemoteCall->data[0]->bitalo_link.'</p>';
			    	$bitaloLinkHtmlTable = '<tr><td>'.$apiDataRemoteCall->data[0]->bitalo_link.'</td></tr>';
			    }else{
			    	$bitaloLinkHtml = '';
			    	$bitaloLinkHtmlTable = '';
			    }
			    //send new order and payment details email to customer
			   // load the mailer class
				 $mailer = WC()->mailer();
				//format the email
				$recipient = $orderData['billing']['email'];
				$subject = get_bloginfo()." payment details for order #".$order_id;
				$content = '<div>Dear '.$orderData['billing']['first_name'].' '.$orderData['billing']['last_name'].',<br/>
					Thank you for your order at '.get_bloginfo().'.</div>
					<div>In order to complete the order please send '.$apiDataRemoteCall->data[0]->payment_method. ' to the following address:</div>
					<table class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
					<tr><td>'.$apiDataRemoteCall->data[0]->crypto_currency_choosen. ': <strong>'.$apiDataRemoteCall->data[0]->btc_address.'</strong><td><tr>
					<tr><td>'.$apiDataRemoteCall->data[0]->crypto_currency_choosen. ' Amount :  <strong>'.$apiDataRemoteCall->data[0]->btc_amount.'</strong><td></tr>
					<tr><td>Message/Reference : <strong>'.$apiDataRemoteCall->data[0]->customer_reference.'</strong></td></tr>
					<tr><td><button onclick="openInvoice()">Pay Now</button></td></tr>
					'.$bitaloLinkHtmlTable.'
					<script src="https://api.lamium.io/js/payment_iframe.js"> </script>
					  <script>
					    function openInvoice() {
					      
					        lamium.setApiUrlPrefix("https://pay.lamium.io")
					      lamium.showInvoice("'.$apiDataRemoteCall->data[0]->btcserver_api_payment_id.'");
					    }
					  </script>';
				$content .='</table>';
				$content .= $this->_get_custom_email_html( $order, $subject, $mailer );
				$headers = "Content-Type: text/html\r\n";
				//send the email through wordpress
				$mailer->send( $recipient, $subject, $content, $headers );
			    $paymentDetailsBlock ='<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
					<li class="woocommerce-order-overview__order order"><p>Please send '.$apiDataRemoteCall->data[0]->payment_method. ' to the following address:</p>
					<p>'.$apiDataRemoteCall->data[0]->payment_method. ' : <strong>'.$apiDataRemoteCall->data[0]->btc_address.'</strong></p>
					<p>'.$apiDataRemoteCall->data[0]->payment_method. ' Amount :  <strong>'.$apiDataRemoteCall->data[0]->btc_amount.'</strong></p>
					<p>Message/Reference : <strong>'.$apiDataRemoteCall->data[0]->customer_reference.'</strong></p>
					<p><button onclick="openInvoice()">Pay Now</button></p>
					'.$bitaloLinkHtml.'
					<script src="https://api.lamium.io/js/payment_iframe.js"> </script>
					  <script>
					 
					    function openInvoice() {
					      
					        lamium.setApiUrlPrefix("https://pay.lamium.io")
					      lamium.showInvoice("'.$apiDataRemoteCall->data[0]->btcserver_api_payment_id.'");
					    }
					  </script>';
				$lamiumData = '<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
					<li class="woocommerce-order-overview__order order"><p>Please send '.$apiDataRemoteCall->data[0]->payment_method. ' to the following address :</p>
					<p>'.$apiDataRemoteCall->data[0]->payment_method. ' : <strong>'.$apiDataRemoteCall->data[0]->btc_address.'</strong></p>
					<p>'.$apiDataRemoteCall->data[0]->payment_method. ' Amount:  <strong>'.$apiDataRemoteCall->data[0]->btc_amount.'</strong></p>
					<p>Message/Reference : <strong>'.$apiDataRemoteCall->data[0]->customer_reference.'</strong></p>
					<p><button onclick="openInvoice()" id="lamium-pay-now">Pay Now</button></p>
					'.$bitaloLinkHtml.'
					 <script src="https://api.lamium.io/js/payment_iframe.js"> </script>
					  <script>
					 	jQuery(function(){
						   jQuery("#lamium-pay-now").click();
						   jQuery(".header__icon__img").attr("src","https://lamium.io/img/lamium-logo.png");
						});
					    function openInvoice() {
					      
					        bitpay.setApiUrlPrefix("https://pay.lamium.io")
					      bitpay.showInvoice("'.$apiDataRemoteCall->data[0]->btcserver_api_payment_id
					      	.'&paymentMethodId='.$apiDataRemoteCall->data[0]->payment_method.'");
					    }
					  </script>';
			 //    if(!empty($apiDataRemoteCall->data[0]->pay_with_bitalo)){
				// 	$lamiumData  .='<p><a style="background:#2778b2;padding:10px 10px 10px 10px;color:#fff" href ="'.$apiDataRemoteCall->data[0]->pay_with_bitalo.'" class="button" target="_blank">Pay with Lamium</a></p>';
				// }
				// Return thankyou redirect
			    WC()->session->set( 'lamiumData', $lamiumData);
				return array(
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url($order)
				);
			}catch(Exception $e) {
					update_post_meta( $order_id , '_lamium_api_error',$e->getMessage());
	            	update_post_meta( $order_id , '_lamium_api_error_url',$url.'--- plugin url--'.get_home_url().
	            	 '---call failed');
   		 			//$this->_tryCatchError($e->getMessage());
			}
		}
	public function do_this_hourly_for_crypto_pay() {
				
	try
	{
		//$this->_tryCatchError('hourly job begins');
		$customer_orders = get_posts( array(
			        'numberposts' => 100,
			        'order' => 'ASC',
			        'meta_key'    => '_customer_user',
			        'post_type'   => array( 'shop_order' ),
			        'post_status' => array( 'wc-pending')
	    		));
		if(empty($customer_orders)){return true;}
		//$this->_tryCatchError('Customer order is not empty'.$this->fiat_pay_or_bitcoin_pay.'check data');
		$errorEmailSent = false;
		$transaction_ids = array();	
		$orderIdTransactionIdMap = array();
		foreach($customer_orders as $key =>$customer_order) 		
		{
					
			$metaData = get_post_meta($customer_order->ID);
			if(!isset($metaData["_lamium_customer_reference"])|| !isset($metaData["_lamium_transaction_id"][0]) || !isset($metaData["_lamium_merchant_id"][0])){continue;}
			$transaction_id = $metaData["_lamium_transaction_id"][0];
			$transaction_ids[] = $transaction_id;
			$merchantId = $metaData["_lamium_merchant_id"][0];
			$orderIdTransactionIdMap[$transaction_id] = $customer_order->ID;
		}     
				$lamiumApiData['merchant_id'] = $merchantId;
			    $lamiumApiData['transaction_ids'] = $transaction_ids;
			    $url = 'http://api.lamium.io/api/payments/payCryptoCoinsAllOrderPaymentStatus';
			$lamiumApiData = json_encode($lamiumApiData);

		    $tokenRemoteCall = $this->_getCoinnexusToken();
		    $tokenRemoteCall =json_decode($tokenRemoteCall['body']);
		    if(empty($tokenRemoteCall->success))
		    {

	        }else{
	        	
	        	$apiDataRemoteCall = $this->_wpRemoteCall($url,$lamiumApiData,$tokenRemoteCall->data->token);
		        $apiDataRemoteCall =json_decode($apiDataRemoteCall['body']);
		        if(!empty($apiDataRemoteCall->success))
		        {
		        	foreach($apiDataRemoteCall->data[0]->records as $apiData)
			        {	  
			            if($apiData->status =='paid')
			            { 
			            	$orderId = $orderIdTransactionIdMap[$apiData->transaction_id];
			            	$order = wc_get_order($orderId);
			            	
			            	$orderUpdate = $order->update_status('processing', __( 'Bitcoins paid by customer', 'wc-gateway-lamium-accept-bitcoin-api'));
			            }
			        }
		        }			        
			}    	
    }
	    catch(Exception $e) {
	    	if(false ==$errorEmailSent)
	    	{
	    		$this->_tryCatchError($e->getMessage());
	    	}
	    	
		}
	}

	protected function _cryptoFilter($crypto)
	{
		switch ($crypto) {
			case 'Bitcoin':
				$crypto ='bitcoin';
				break;
			case 'Dash':
				$crypto ='dash';
				break;
			default:
				$crypto =' bitcoin';
				break;
		}
		return $crypto;
	}

	protected function _get_custom_email_html( $order, $heading = false, $mailer ) {
		$template = 'emails/customer-invoice.php';
		return wc_get_template_html( $template, array(
			'order'         => $order,
			'email_heading' => $heading,
			'sent_to_admin' => false,
			'plain_text'    => false,
			'email'         => $mailer
		) );
	}

	protected function _wpRemoteCall($url,$bodyData,$token=null)
	{	
		return wp_remote_post( $url, array(
			'method' => 'POST',
			'timeout' => 45,
			'redirection' => 5,
			'headers' => array("Content-type" =>'application/json','Accept'=>'application/json','Authorization'=>'Bearer '.$token),
			'body' => $bodyData
		    )
		);
	}

	protected function _getCoinnexusToken()
	{
		$data = array(
				'username' =>$this->username,
                'password'  => $this->password,
               );
			$data = json_encode($data);
			$url = 'http://api.lamium.io/api/users/token';
            return $this->_wpRemoteCall($url,$data);
	}

	protected function _storeFailRecord($cornCallObj,$sub,$orderData=false,$automatedCall=false)

	{
		$writeErrorCache = wp_cache_add( 'test_cache_basic', 'static', $group = '', $expire = 0 );
		$keyAdded = false;
		$i=0;
		$message = get_home_url().'---'.@$cornCallObj->message;
		if($orderData)
		{
			$message .= '--------'.$cornCallObj->url.'-------'.$orderData['currency'].'--'.$orderData['total'].
			'--'.$orderData['billing']['first_name'].$orderData['billing']['last_name'].'--'.$orderData['billing']['phone'].'--'.
			$orderData['billing']['address_1'].'--'.$orderData['billing']['address_2'].'--'.$orderData['billing']['city'].'--'.
			$orderData['billing']['state'].'--'.$orderData['billing']['postcode'].'--'.$orderData['billing']['country'].'--'.$orderData['total_tax'];
		}
		do {
			$i = $i + 1;
			$writeErrorCache = wp_cache_add( $i, $message, $group = '', $expire = 0 );
			if($writeErrorCache){
				$keyAdded==true;
			}
		}while ($keyAdded==false);
		$writeErrorCache = wp_cache_add( 'test_cache', $message, $group = '', $expire = 0 );
		
	}

    protected function _fail($cornCallObj,$sub,$orderData=false,$automatedCall=false)
    {
    	$to = 'debanjan@lamium.io,support@lamium.io';
		$subject = 'WC_Gateway_Lamium_Accept_Crypto_Api ---'.$sub;
		$message = get_home_url().'---'.@$cornCallObj->message;
		if($orderData)
		{
			$message .= '--------'.$cornCallObj->url.'-------'.$orderData['currency'].'--'.$orderData['total'].
			'--'.$orderData['billing']['first_name'].$orderData['billing']['last_name'].'--'.$orderData['billing']['phone'].'--'.
			$orderData['billing']['address_1'].'--'.$orderData['billing']['address_2'].'--'.$orderData['billing']['city'].'--'.
			$orderData['billing']['state'].'--'.$orderData['billing']['postcode'].'--'.$orderData['billing']['country'].'--'.$orderData['total_tax'];
		}
		wp_mail( $to, $subject, $message);
		if(!$automatedCall)
		{
			throw new Exception( __( 'order processing failed, please try again later', 'woo' ) );
		}	
    }

    protected function _tryCatchError($error)
    {
    	$to = 'debanjan@lamium.io,support@lamium.io';
		$subject = 'WC_Gateway_Lamium_Accept_Crypto_Api plugin run failed';
		$message = get_home_url().'-----'.$error;
		wp_mail( $to, $subject, $message);
    }
	
  } // end \WC_Gateway_Lamium_Accept_Crypto_Api class
}