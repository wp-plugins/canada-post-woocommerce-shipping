<?php
/*
	Plugin Name: Canada Post WooCommerce Shipping
	Plugin URI: http://www.wooforce.com
	Description: The ultimate Canada Post WooCommerce Shipping plugin. Dynamic shipping rates, Shipment Creation, Label and Invoice/Manifesto Printing. Upgrade to Premium version for streamlining the shipping process & excellent support!
	Version: 1.1.0
	Author: WooForce
	Author URI: http://www.wooforce.com
	Copyright: 2014-2015 WooForce.
	License: GPLv2 or later
	License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
 
if (in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) )) {
 
	function wf_woocommerce_canadapost_init() {
		if ( ! class_exists( 'wf_woocommerce_canadapost_shipping_method' ) ) {
			class wf_woocommerce_canadapost_shipping_method extends WC_Shipping_Method {
				
				public function __construct() {
					$this->id                 = 'wf_shipping_canada_post';
					$this->method_title       = __( 'Canada Post', 'woocommerce_canada_post' );
		
					$this->method_description = __( 'The ultimate Canada Post WooCommerce Shipping plugin. Dynamic shipping rates, Shipment Creation, Label and Invoice/Manifesto Printing. Upgrade to Premium version for streamlining the shipping process & excellent support!', 'woocommerce_canada_post' );
		
					$this->wf_init();		
				}
				 
				function wf_init() {
					
					// Load the settings API
					$this->wf_init_form_fields(); // This is part of the settings API. Override the method to add your own settings
					$this->init_settings(); // This is part of the settings API. Loads settings you previously wf_init.
					
					// Define user set variables
					$this->title = $this->settings['title'];
					$this->enabled = $this->settings['enabled']; 
					$this->username = $this->settings['merchant_username']; 
					$this->password = $this->settings['merchant_password'];
					$this->customerId = $this->settings['customer_number'];
					$this->contractId = $this->settings['contract_number'];
					$this->mailedBy = $this->customerId;
					$this->mobo = $this->customerId;
					$this->groupId = '4326432';
					$this->serviceUrl = $this->settings['service_url'];
					$this->max_weight = $this->settings['max_weight'];
					$this->origin_postcode = $this->settings['origin'];
					$this->quote_type = $this->settings['quote_type'];
					$this->debug = $this->settings['debug'];
					
					
					
					// Save settings in admin if you have any defined
					add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
				}
				
				function wf_init_form_fields() {
					 $this->form_fields = wf_get_woocommerce_canadapost_settings();
				} // End wf_init_form_fields()
				
				/**
				 * admin_options function.
				 *
				 * @access public
				 * @return void
				 */
				public function admin_options() {
					// Check users environment supports this method
					$this->environment_check();
					?>
					<div class="wf-banner updated below-h2">
						<img class="scale-with-grid" src="http://www.wooforce.com/wp-content/uploads/2015/07/WooForce-Logo-Admin-Banner-Basic.png" alt="Wordpress / WooCommerce Canada Post Shipping with Print Label Plugin | WooForce">
						<p class="main"><strong>WooCommerce Canada Post Shipping with Print Label Premium version streamlines your complete shipping process and saves time</strong></p>
						<p>&nbsp;-&nbsp;Print shipping label.<br>
						&nbsp;-&nbsp;Auto Shipment Tracking: It happens automatically while generating the label.<br>
						&nbsp;-&nbsp;Box packing.<br>
						&nbsp;-&nbsp;Enable/disable, edit the names of, and add handling costs to shipping services.<br>
						&nbsp;-&nbsp;Option to set printing paper size as 4*6 for Zebra/Thermal/Dymo printer.<br>
						&nbsp;-&nbsp;Option to enter weight and dimension manually for Label printing, Useful when product weight and dimensions are not maintained.<br>
						&nbsp;-&nbsp;Lettermail Rates.<br>
						&nbsp;-&nbsp;Additional Options Coverage/Insurance.<br>
						&nbsp;-&nbsp;Show delivery time during checkout. Option to add delivery delay if required.<br>
						&nbsp;-&nbsp;Excellent Support for setting it up!</p>
						<p><a href="http://www.wooforce.com/product/canada-post-woocommerce-shipping-with-print-label-plugin/" target="_blank" class="button button-primary">Upgrade to Premium Version</a> <a href="http://canadapost.wooforce.com/wp-admin/admin.php?page=wc-settings&tab=shipping&section=wf_shipping_canada_post" target="_blank" class="button">Live Demo</a></p>
					</div>
					<style>
					.wf-banner img {
						float: right;
						margin-left: 1em;
						padding: 15px 0
					}
					</style>
					<?php 
					// Show settings
					parent::admin_options();
				}

				public function calculate_shipping( $package ){
					
					// REST URL
					$service_url = $this->serviceUrl . 'ship/price';
					$parcelContents = wf_weight_only_shipping($package,$this->max_weight);
					$requestedShippingPoint = str_replace( ' ', '', strtoupper( $this->origin_postcode ));
					$destination = $this->wf_get_destination($package);
					$contractDetails = '';	
					$customerNumberDetails = '';	
					
					if($this->quote_type == 'commercial')
					{
						$contractDetails = "<contract-id>{$this->contractId}</contract-id>";
						$customerNumberDetails = "<customer-number>{$this->mailedBy}</customer-number>";
					}
						
					foreach($parcelContents as $parcelKey => $parcel_characteristics)		
					{	
$xmlRequest = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<mailing-scenario xmlns="http://www.canadapost.ca/ws/ship/rate-v3">
   {$parcel_characteristics}
  <origin-postal-code>{$requestedShippingPoint}</origin-postal-code>
  <quote-type>{$this->quote_type}</quote-type>
  {$contractDetails}
  {$customerNumberDetails}
  <destination>
	{$destination}
  </destination>
</mailing-scenario>
XML;
						
						if($this->debug == 'yes') wc_add_notice( print_r( htmlspecialchars( $xmlRequest ), true ), 'notice' ) ;				
						
						$response = wp_remote_post( $service_url,
									array(
										'method'           => 'POST',
										'timeout'          => 70,
										'sslverify'        => 0,
										'headers'          => $this->wf_get_request_header('application/vnd.cpc.ship.rate-v3+xml','application/vnd.cpc.ship.rate-v3+xml'),
										'body'             => $xmlRequest
									)
								);

						if($this->debug == 'yes')  wc_add_notice( print_r( htmlspecialchars( $response['body'] ), true ), 'notice' ) ;				
						
						if ( ! empty( $response['body'] ) ) {
							$response = $response['body'];
						} else {
							$response = '';
						} 

						libxml_use_internal_errors(true);
						$xml = simplexml_load_string( '<root>' . preg_replace('/<\?xml.*\?>/','', $response ) . '</root>' );

						if (!$xml) {
							$errorMessage .=  'Failed loading XML' . "\n";
							$errorMessage .= $response . "\n";
							foreach(libxml_get_errors() as $error) {
								$errorMessage .= "\t" . $error->message;
							}
						} else {
							if ($xml->{'price-quotes'} ) {
								$priceQuotes = $xml->{'price-quotes'}->children('http://www.canadapost.ca/ws/ship/rate-v3');
								if ( $priceQuotes->{'price-quote'} ) {
									foreach ( $priceQuotes as $priceQuote ) { 
										$rateDetails = array(											
											'id' => $this->id . ':' . (string)$priceQuote->{'service-code'},
											'label' => (string)$priceQuote->{'service-name'},
											'cost' => (string)$priceQuote->{'price-details'}->{'due'},
											'calc_tax' => 'per_item'
										);
										$this->wf_add_if_rate_already_exist($rateDetails['id'],$rateDetails);											
									}
								}
							}
							if ($xml->{'messages'} ) {					
								$messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');		
								foreach ( $messages as $message ) {
									$errorMessage .= 'Error Code: ' . $message->code . "\n";
									$errorMessage .= 'Error Msg: ' . $message->description . "\n\n";
								}
							}
								
						}
						if(empty($errorMessage) && !empty($this->CompleteRateDetails))
						{
							foreach($this->CompleteRateDetails as $rate)
							{
								$this->add_rate( $rate );		
							}
						}
					}		
					
				}
		
				private function wf_add_if_rate_already_exist($rateId, $details){
					if (!empty($this->CompleteRateDetails) && array_key_exists($rateId, $this->CompleteRateDetails)) {
						$details['cost'] = $details['cost'] + $this->CompleteRateDetails[$rateId]['cost'];
					}
					$this->CompleteRateDetails[$rateId] = $details;					
				}
				
				private function wf_get_destination( $package ) {
					// The destination
					$request = '';
					switch ( $package['destination']['country'] ) {
						case "CA" :
							$request .= '		<domestic>' . "\n";
							$request .= '			<postal-code>' . str_replace( ' ', '', strtoupper( $package['destination']['postcode'] ) ) . '</postal-code>' . "\n";
							$request .= '		</domestic>' . "\n";
						break;
						case "US" :
							$request .= '		<united-states>' . "\n";
							$request .= '			<zip-code>' . str_replace( ' ', '', strtoupper( $package['destination']['postcode'] ) ) . '</zip-code>' . "\n";
							$request .= '		</united-states>' . "\n";
						break;
						default :
							$request .= '		<international>' . "\n";
							$request .= '			<country-code>' . $package['destination']['country'] . '</country-code>' . "\n";
							$request .= '		</international>' . "\n";
						break;
					}
					return $request;
				}
	
				private function wf_get_request_header($accept,$contentType){
				   return array(
						'Accept'          => $accept,
						'Content-Type'    => $contentType,
						'Authorization'   => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
						'Accept-language' => 'en-CA'
					);
				}
			}			
		}
	}
	add_action( 'woocommerce_shipping_init', 'wf_woocommerce_canadapost_init' );
	 
	function wf_add_woocommerce_canadapost_method( $methods )	{
		$methods[] = 'wf_woocommerce_canadapost_shipping_method';
		return $methods;
	}
	add_filter( 'woocommerce_shipping_methods', 'wf_add_woocommerce_canadapost_method' );
	
	function wf_get_woocommerce_canadapost_settings(){					
		 return array(
			 'title' => array(
				  'title' => __( 'Title', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'Title which the user sees during checkout.', 'woocommerce_canada_post' ),
				  'default' => __( 'Canada Post', 'woocommerce_canada_post' )
				  ),
			 'enabled' => array(
				  'title' => __( 'Enable/Disable', 'woocommerce_canada_post' ),
				  'type' => 'checkbox',
				  'description' => __( 'Enable/Disable Canada Post Shipping method' , 'woocommerce_canada_post' ),
				  'default' => 'no'
				   ),
			'merchant_username' => array(
				  'title' => __( 'UserName', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'Canada POST API UserName.', 'woocommerce_canada_post' ),
				  'default' => '6e93d53968881714'
				  ),
			'merchant_password' => array(
				  'title' => __( 'Password', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'Canada POST API Password.', 'woocommerce_canada_post' ),
				  'default' => '0bfa9fcb9853d1f51ee57a'
				  ),
			'customer_number' => array(
				  'title' => __( 'CustomerId', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'Canada POST API CustomerId.', 'woocommerce_canada_post' ),
				  'default' => '2004381'
				  ),
			'contract_number' => array(
				  'title' => __( 'ContractId', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'Canada POST API ContractId.', 'woocommerce_canada_post' ),
				  'default' => '42708517'
				  ),
			'service_url' => array(
				  'title' => __( 'ServiceUrl', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'Canada POST API ContractId.', 'woocommerce_canada_post' ),
				  'default' => 'https://ct.soa-gw.canadapost.ca/rs/'
				  ),
			'max_weight' => array(
				  'title' => __( 'Max Weight', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'If the total weight exceeds the max weight then package will be split into different shipping', 'woocommerce_canada_post' ),
				  'default' => '30'
				  ),
			'origin' => array(
				  'title' => __( 'Origin Postcode', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'Post code of the Sender, from where shipment needs to be picked', 'woocommerce_canada_post' ),
				  'default' => 'K6A 3H2'
				  ),
			'sender_company_name' => array(
				  'title' => __( 'Sender Company Name', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'Company Name to be printed in the shipping label and invoice', 'woocommerce_canada_post' ),
				  'default' => 'Sender Company Name'
				  ),
			'sender_contact_phone' => array(
				  'title' => __( 'Sender Contact Phone', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'Contact Phone to be printed in the shipping label and invoice', 'woocommerce_canada_post' ),
				  'default' => '1-222-333-4444'
				  ),
			'sender_address_line1' => array(
				  'title' => __( 'Sender Address Line1', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'Address Line1 to be printed in the shipping label and invoice', 'woocommerce_canada_post' ),
				  'default' => '077 First Street'
				  ),
			'sender_city' => array(
				  'title' => __( 'Sender City', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'City to be printed in the shipping label and invoice', 'woocommerce_canada_post' ),
				  'default' => 'Hawkesbury'
				  ),
			'sender_state' => array(
				  'title' => __( 'Sender State', 'woocommerce_canada_post' ),
				  'type' => 'text',
				  'description' => __( 'State to be printed in the shipping label and invoice', 'woocommerce_canada_post' ),
				  'default' => 'ON'
				  ),			
			'quote_type' => array(
				  'title' => __( 'Quote Type', 'woocommerce_canada_post' ),
				  'type' => 'select',
				  'description' => __( 'Select commercial if you would like to give the discounted rates to customer', 'woocommerce_canada_post' ),
				  'default' => 'counter',
				  'options' => array(
						  'commercial' => '"commercial" will return the discounted price for the commercial customer or VentureOne member',
						  'counter' => '"counter" will return the regular price paid by consumers.'						  
					 ) 
				  ),
			'debug' => array(
				  'title' => __( 'Debug', 'woocommerce_canada_post' ),
				  'type' => 'checkbox',
				  'description' => __( 'Only enable if you need to debug the communications with API', 'woocommerce_canada_post' ),
				  'default' => 'no'
				  )			
		);
	}
	 
	function wf_weight_only_shipping( $package, $max_weight) {
		//TODO Option like RASE may through error while international shipping
		global $woocommerce;

		$requests = array();
		$weight   = 0;
		$value    = 0;

		// Get weight of order
		foreach ( $package['contents'] as $item_id => $values ) {

			if ( ! $values['data']->needs_shipping() ) {
				continue;
			}

			if ( ! $values['data']->get_weight() ) {
				return;
			}

			$weight += woocommerce_get_weight( $values['data']->get_weight(), 'kg' ) * $values['quantity'];
			$value  += $values['data']->get_price() * $values['quantity'];
		}

		
		if ( $weight > $max_weight ) {

			for ( $i = 0; $i < ( $weight / $max_weight ); $i ++ ) {
				$request  = '<parcel-characteristics>' . "\n";
				$request .= '	<weight>' . round( $max_weight, 2 ) . '</weight>' . "\n";
				$request .= '</parcel-characteristics>' . "\n";
				$requests[] = $request;
			}

			if ( ( $weight % $max_weight ) ) {
				$request  = '<parcel-characteristics>' . "\n";
				$request .= '	<weight>' . round( $weight % $max_weight, 2 ) . '</weight>' . "\n";
				$request .= '</parcel-characteristics>' . "\n";
				$requests[] = $request;
			}
		} else {
			$request  = '<parcel-characteristics>' . "\n";
			$request .= '	<weight>' . round( $weight, 2 ) . '</weight>' . "\n";
			$request .= '</parcel-characteristics>' . "\n";
			$requests[] = $request;
		}
		return $requests;
	}
	
	if ( ! class_exists( 'wf_woocommerce_canadapost_admin' ) )
		include_once 'canada-post-woocommerce-shipping-admin.php';	
}		