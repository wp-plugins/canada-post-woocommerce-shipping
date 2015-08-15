<?php
class wf_woocommerce_canadapost_admin
{
	public function __construct(){
		// Load form_field settings
		$this->settings = get_option( 'woocommerce_wf_shipping_canada_post_settings', null );

		if ( $this->settings && is_array( $this->settings ) ) {
		  $this->settings = array_map( array( $this, 'wf_format_settings' ), $this->settings );
		  $this->enabled  = isset( $this->settings['enabled'] ) && $this->settings['enabled'] == 'yes' ? 'yes' : 'no';
		}
					
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
		
		$this->senderCompanyName = $this->settings['sender_company_name'];
		$this->senderContactPhone = $this->settings['sender_contact_phone'];
		$this->senderAddressLine1 = $this->settings['sender_address_line1'];
		$this->senderCity = $this->settings['sender_city'];
		$this->senderState = $this->settings['sender_state'];
					
		if (is_admin()) {
			add_action('add_meta_boxes', array($this, 'wf_add_canada_post_metabox'));
		}
		if (isset($_GET['wf_canadapost_createshipment'])) {
			add_action('init', array($this, 'wf_canadapost_createshipment'));
		}
		if (isset($_GET['wf_canadapost_viewlabel'])) {
			add_action('init', array($this, 'wf_canadapost_viewlabel'));
		}
		if (isset($_GET['wf_canadapost_transmitshipment'])) {
			add_action('init', array($this, 'wf_canadapost_transmitshipment'));
		}
		
		if (isset($_GET['wf_canadapost_viewmanifest'])) {
			add_action('init', array($this, 'wf_canadapost_viewmanifest'));
		}

		if (isset($_GET['wf_canadapost_getmanifest'])) {
			add_action('init', array($this, 'wf_canadapost_getmanifest'));
		}
	}
	
	public function wf_format_settings( $value ) {
		return is_array( $value ) ? $value : $value;
	}
	
	public function wf_canadapost_getmanifest(){
		$user_ok = $this->wf_user_permission();
		if (!$user_ok) 			
			return;
			
		$order = $this->wf_load_order($_GET['wf_canadapost_getmanifest']);
		if (!$order) 
			return;
			
		$manifestLinkList = get_post_meta($order->id, 'wf_woo_canadapost_manifestLink', false);						
		if(!empty($manifestLinkList))
		{
			foreach($manifestLinkList as $manifestLink)
			{
				$this->wf_get_manifest($manifestLink,$order);
			}
		}
				
		// Redirect back to order page
		wp_redirect(admin_url('/post.php?post='.$_GET['wf_canadapost_getmanifest'].'&action=edit'));
		exit;
	}

	public function wf_canadapost_transmitshipment(){
		$user_ok = $this->wf_user_permission();
		if (!$user_ok) 			
			return;

		$order = $this->wf_load_order($_GET['wf_canadapost_transmitshipment']);
		if (!$order) 
			return;
		
		$this->wf_transmit_shipment($order);

		wp_redirect(admin_url('/post.php?post='.$_GET['wf_canadapost_transmitshipment'].'&action=edit'));
		exit;
	}

	public function wf_canadapost_viewmanifest(){
		$manifestUrlAndID = explode('||', base64_decode($_GET['wf_canadapost_viewmanifest']));
		$service_url = $manifestUrlAndID[0]; 
		$orderId =  $manifestUrlAndID[1];		
		$response = wp_remote_post( $service_url,
					array(
						'method'           => 'GET',
						'timeout'          => 70,
						'sslverify'        => 0,
						'headers'          => $this->wf_get_request_header('application/pdf,application/zpl','application/pdf,application/zpl')
					)
				);
		
		header('Content-Type: application/pdf');
		header('Content-disposition: attachment; filename="Artifact_PO_' . $orderId  . '.pdf"');
		print($response['body'] );
		exit;
	}

	public function wf_canadapost_createshipment(){
		$user_ok = $this->wf_user_permission();
		if (!$user_ok) 			
			return;
		
		$order = $this->wf_load_order($_GET['wf_canadapost_createshipment']);
		if (!$order) 
			return;
		
		$this->wf_create_shipment($order);

		wp_redirect(admin_url('/post.php?post='.$_GET['wf_canadapost_createshipment'].'&action=edit'));
		exit;
	}
	
	public function wf_canadapost_viewlabel(){
		$shipmentDetails = explode('|', base64_decode($_GET['wf_canadapost_viewlabel']));

		if (count($shipmentDetails) != 2) {
			exit;
		}
		
		$service_url = get_post_meta($shipmentDetails[1], 'wf_woo_canadapost_shippingLabel_'.$shipmentDetails[0], true); 
		
		$response = wp_remote_post( $service_url,
					array(
						'method'           => 'GET',
						'timeout'          => 70,
						'sslverify'        => 0,
						'headers'          => $this->wf_get_request_header('application/pdf,application/zpl','application/pdf,application/zpl')
					)
				);
				

		/* 
		TODO Capture the error message if any ["response"]=> array(2) { ["code"]=> int(200) ["message"]=> string(2) "OK" }
		 */
		header('Content-Type: application/pdf');
		header('Content-disposition: attachment; filename="ShipmentArtifact-' . $shipmentDetails[0] . '.pdf"');
		print($response['body'] );
		exit;
	}
	
	public function wf_transmit_shipment($order){
		$service_url = $this->serviceUrl . $this->mailedBy . '/' . $this->mobo . '/manifest';

		$requestedShippingPoint = str_replace( ' ', '', strtoupper( $this->origin_postcode ));

$xmlRequest = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<transmit-set xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns="http://www.canadapost.ca/ws/manifest-v7" >
  <group-ids>
    <group-id>{$this->groupId}</group-id>
  </group-ids>
  <requested-shipping-point>{$requestedShippingPoint}</requested-shipping-point>
  <cpc-pickup-indicator>true</cpc-pickup-indicator>
  <detailed-manifests>true</detailed-manifests>
  <method-of-payment>Account</method-of-payment>
  <manifest-address>
    <manifest-company>{$this->senderCompanyName}</manifest-company>
    <phone-number>{$this->senderContactPhone}</phone-number>
    <address-details>
      <address-line-1>{$this->senderAddressLine1}</address-line-1>
      <city>{$this->senderCity}</city>
      <prov-state>{$this->senderState}</prov-state>
  	  <country-code>CA</country-code>
      <postal-zip-code>{$requestedShippingPoint}</postal-zip-code>
    </address-details>
  </manifest-address>
</transmit-set>
XML;

		$response = wp_remote_post( $service_url,
					array(
						'method'           => 'POST',
						'timeout'          => 70,
						'sslverify'        => 0,
						'headers'          => $this->wf_get_request_header('application/vnd.cpc.manifest-v7+xml','application/vnd.cpc.manifest-v7+xml'),
						'body'             => $xmlRequest
					)
				);

		if ( ! empty( $response['body'] ) ) {
			$response = $response['body'];
		} else {
			$response = '';
		} 

		libxml_use_internal_errors(true);
		$xml = simplexml_load_string( '<root>' . preg_replace('/<\?xml.*\?>/','', $response ) . '</root>' );

		$transmitErrorMessage = '';
		if (!$xml) {
			$transmitErrorMessage .=  'Failed loading XML;' ;
			$transmitErrorMessage .=  $response . ";";
			foreach(libxml_get_errors() as $error) {
				$transmitErrorMessage .=  $error->message;
			}
		} else {
			if ($xml->{'manifests'} ) {
				$manifest = $xml->{'manifests'}->children('http://www.canadapost.ca/ws/manifest-v7');
				if ( $manifest->{'link'} ) {
					foreach ( $manifest->{'link'} as $link ) {  
						if($link->attributes()->{'rel'} ==  "manifest")
						{
							$manifestLinkList[] = (string)$link->attributes()->{'href'};
						}						
					}
				}
			}
			if ($xml->{'messages'} ) {					
				$messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');		
				foreach ( $messages as $message ) {
					$transmitErrorMessage .=  'Error Code: ' . $message->code . "\n";
					$transmitErrorMessage .=  'Error Msg: ' . $message->description . "\n\n";
				}
			}
		} 
		
		if(!empty($transmitErrorMessage))
		{
			update_post_meta($order->id, 'wf_woo_canadapost_transmitErrorMessage', $transmitErrorMessage, true);
		}
		
		if(!empty($manifestLinkList) )
		{
			foreach($manifestLinkList as $manifestLink)
			{
				add_post_meta($order->id, 'wf_woo_canadapost_manifestLink', $manifestLink, false);	
				$this->wf_get_manifest($manifestLink,$order);			
			}
		}		
	}
	
	public function wf_get_manifest($manifestLink,$order){
		// REST URL
		$service_url = $manifestLink;

		// Create wf_create_shipment request xml
		$response = wp_remote_post( $service_url,
					array(
						'method'           => 'GET',
						'timeout'          => 70,
						'sslverify'        => 0,
						'headers'          => $this->wf_get_request_header('application/vnd.cpc.manifest-v7+xml','application/vnd.cpc.manifest-v7+xml')
					)
				);

		if ( ! empty( $response['body'] ) ) {
			$response = $response['body'];
		} else {
			$response = '';
		} 
		
	
		libxml_use_internal_errors(true);
		$xml = simplexml_load_string( '<root>' . preg_replace('/<\?xml.*\?>/','', $response ) . '</root>' );
		$manifestErrorMessage = '';
		if (!$xml) {
			$manifestErrorMessage .= 'Failed loading XML' . "\n";
			$manifestErrorMessage .= $response . "\n";
			foreach(libxml_get_errors() as $error) {
				$manifestErrorMessage .= "\t" . $error->message;
			}
		} else {
			if ($xml->{'manifest'} ) {
				$manifest = $xml->{'manifest'}->children('http://www.canadapost.ca/ws/manifest-v7');
				if ( $manifest->{'po-number'} ) {
					$poNumber =  (string)$manifest->{'po-number'} ;
					foreach ( $manifest->{'links'}->{'link'} as $link ) { 
						if($link->attributes()->{'rel'} == 'artifact')
							$manifestArtifactLink = (string)$link->attributes()->{'href'};						
					}
				}
			}			
			if ($xml->{'messages'} ) {					
				$messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');		
				foreach ( $messages as $message ) {
					$manifestErrorMessage .=  'Error Code: ' . $message->code . "\n";
					$manifestErrorMessage .=  'Error Msg: ' . $message->description . "\n\n";
				}
			}
		}
		if(!empty($manifestArtifactLink))
		{
			add_post_meta($order->id, 'wf_woo_canadapost_manifestArtifactLink', $manifestArtifactLink, false);				
		}
		if(!empty($manifestErrorMessage) )
		{
			update_post_meta($order->id, 'wf_woo_canadapost_manifestErrorMessage', $manifestErrorMessage, true);						
		}
	}
	
	public function wf_create_shipment($order){
		// REST URL
		$service_url = $this->serviceUrl . $this->mailedBy . '/' . $this->mobo . '/shipment';
		
		
		$mailingDate = date('Y-m-d');
		$shipping_first_name = $order->shipping_first_name;
        $shipping_last_name = $order->shipping_last_name;
        $shipping_company = $order->shipping_company;
        $shipping_address_1 = $order->shipping_address_1;
        $shipping_address_2 = $order->shipping_address_2;
        $shipping_city = $order->shipping_city;
        $shipping_postcode = $order->shipping_postcode;
        $shipping_country = $order->shipping_country;
        $shipping_state = $order->shipping_state;
		$order_id = 'Order Id:' . $order->get_order_number();
		$billing_email = $order->billing_email;
		$billing_phone =  $order->billing_phone;
		$parcelContents = $this->wf_get_package_request_wrapper($order);
		$serviceCode = $this->wf_get_shipping_service($order);
		$requestedShippingPoint = str_replace( ' ', '', strtoupper( $this->origin_postcode ));
		$customs = $this->wf_get_item_details_for_custom($order);
		$shipmentErrorMessage = '';
		foreach($parcelContents as $parcelKey => $parcelContent){	
			if(is_array($parcelContent))
			{
				$parcel_characteristics = $parcelContent[0];
				$boxID = $parcelContent[1];
			}
			else
			{
				$parcel_characteristics = $parcelContent;
			}
		
		
$xmlRequest = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<shipment xmlns="http://www.canadapost.ca/ws/shipment-v7">
	<group-id>{$this->groupId}</group-id>
	<requested-shipping-point>{$requestedShippingPoint}</requested-shipping-point>
	<cpc-pickup-indicator>true</cpc-pickup-indicator>
	<expected-mailing-date>{$mailingDate}</expected-mailing-date>
	<delivery-spec>
		<service-code>{$serviceCode}</service-code>
			<sender>
				<company>{$this->senderCompanyName}</company>
				<contact-phone>{$this->senderContactPhone}</contact-phone>
				<address-details>
					<address-line-1>{$this->senderAddressLine1}</address-line-1>
					<city>{$this->senderCity}</city>
					<prov-state>{$this->senderState}</prov-state>
					<postal-zip-code>{$requestedShippingPoint}</postal-zip-code>
					<country-code>CA</country-code>					
				</address-details>
			</sender>
			<destination>
				<name>{$shipping_first_name} {$shipping_last_name}</name>
				<company>{$shipping_company}</company>
				<client-voice-number>{$billing_phone}</client-voice-number>
				<address-details>
					<address-line-1>{$shipping_address_1}</address-line-1>
					<address-line-2>{$shipping_address_2}</address-line-2>
					<city>{$shipping_city}</city>
					<prov-state>{$shipping_state}</prov-state>
					<country-code>{$shipping_country}</country-code>
					<postal-zip-code>{$shipping_postcode}</postal-zip-code>
				</address-details>
			</destination>
		{$parcel_characteristics}
		<options><option><option-code>RASE</option-code></option></options>
		<notification>
			<email>{$billing_email}</email>
			<on-shipment>true</on-shipment>
			<on-exception>false</on-exception>
			<on-delivery>true</on-delivery>
		</notification>
		<print-preferences>
			<output-format>8.5x11</output-format>
		</print-preferences>
		<preferences>
			<show-packing-instructions>true</show-packing-instructions>
			<show-postage-rate>false</show-postage-rate>
			<show-insured-value>true</show-insured-value>
		</preferences>
		<references>
			<customer-ref-1>{$order_id}</customer-ref-1>
		</references>
		<settlement-info>
			<contract-id>{$this->contractId}</contract-id>
			<intended-method-of-payment>Account</intended-method-of-payment>
		</settlement-info>
		{$customs}
	</delivery-spec>
</shipment>
XML;

			$response = wp_remote_post( $service_url,
						array(
							'method'           => 'POST',
							'timeout'          => 70,
							'sslverify'        => 0,
							'headers'          => $this->wf_get_request_header('application/vnd.cpc.shipment-v7+xml','application/vnd.cpc.shipment-v7+xml'),
							'body'             => $xmlRequest
						)
					);

			if ( ! empty( $response['body'] ) ) {
				$response = $response['body'];
			} else {
				$response = '';
			} 

			libxml_use_internal_errors(true);
			$xml = simplexml_load_string( '<root>' . preg_replace('/<\?xml.*\?>/','', $response ) . '</root>' );

			if (!$xml) {
				$shipmentErrorMessage .= 'Failed loading XML' . "\n";
				$shipmentErrorMessage .= $response . "\n";
				foreach(libxml_get_errors() as $error) {
					$shipmentErrorMessage .= "\t" . $error->message;
				}
			} else {
				if ($xml->{'shipment-info'} ) {
					$shipment = $xml->{'shipment-info'}->children('http://www.canadapost.ca/ws/shipment-v7');
					if ($shipment->{'shipment-id'} ) {
						$shipmentId = (string)$shipment->{'shipment-id'};
						$shipmentStatus = (string)$shipment->{'shipment-status'};
						$trackingPin = (string)$shipment->{'tracking-pin'};		
						foreach ( $shipment->{'links'}->{'link'} as $link ) { 
							if($link->attributes()->{'rel'} == "label")
								$shippingLabel = (string)$link->attributes()->{'href'};
						}
					}
				}
				if ($xml->{'messages'} ) {					
					$messages = $xml->{'messages'}->children('http://www.canadapost.ca/ws/messages');		
					foreach ( $messages as $message ) {
						$shipmentErrorMessage .=  'Error Code: ' . $message->code . "\n";
						$shipmentErrorMessage .=  'Error Msg: ' . $message->description . "\n\n";
					}
				}
			}
		
			if(!empty($shipmentId) && !empty($shippingLabel))
			{
				add_post_meta($order->id, 'wf_woo_canadapost_shipmentId', $shipmentId, false);
				add_post_meta($order->id, 'wf_woo_canadapost_shippingLabel_'.$shipmentId, $shippingLabel, true);
				add_post_meta($order->id, 'wf_woo_canadapost_shipmentStatus', $shipmentStatus, false);
				add_post_meta($order->id, 'wf_woo_canadapost_trackingPin', $trackingPin, false);	
				add_post_meta($order->id, 'wf_woo_canadapost_packageDetails_'.$shipmentId, $this->wf_get_parcel_details($parcel_characteristics) , true);	
				if(!empty($boxID))
				{
					add_post_meta($order->id, 'wf_woo_canadapost_boxid_'.$shipmentId, $boxID  , true);
				}
			}
		}
		update_post_meta($order->id, 'wf_woo_canadapost_shipmentErrorMessage', $shipmentErrorMessage, true);		
	}
	
	public function wf_add_canada_post_metabox(){
		global $post;
		if (!$post) {
			return;
		}
		
		$order = $this->wf_load_order($post->ID);
		if (!$order) 
			return;
		
		//Shipping method is not canada post
		$canadaShipId = $this->wf_get_shipping_service($order);
		if(!empty($canadaShipId))
		{
			add_meta_box('CyDCanadaPost_metabox', __('Canada Post', 'CyDCanadaPost'), array($this, 'wf_canada_post_metabox_content'), 'shop_order', 'side', 'default');
		}
	}

	public function wf_canada_post_metabox_content(){
		global $post;
		
		if (!$post) {
			return;
		}

		$order = $this->wf_load_order($post->ID);
		if (!$order) 
			return;			

		$shipmentIds = get_post_meta($order->id, 'wf_woo_canadapost_shipmentId', false);
		$manifestLink = get_post_meta($order->id, 'wf_woo_canadapost_manifestLink', false);
		$manifestArtifactLinkList = get_post_meta($order->id, 'wf_woo_canadapost_manifestArtifactLink', false);				
		$shipmentErrorMessage = get_post_meta($order->id, 'wf_woo_canadapost_shipmentErrorMessage',true);
		$manifestErrorMessage = get_post_meta($order->id, 'wf_woo_canadapost_manifestErrorMessage',true);
		$transmitErrorMessage = get_post_meta($order->id, 'wf_woo_canadapost_transmitErrorMessage',true);
		
		//Only Display error message if the process is not complete. If the Invoice link available then Error Message is unnecessary
		if(empty($manifestArtifactLinkList))
		{
			if(!empty($transmitErrorMessage))
			{
					echo '<div class="error"><p>' . sprintf( __( 'Canada Post Transmitting Error :%s', 'CyDCanadaPost' ), $transmitErrorMessage) . '</p></div>';
			}
			
			if(!empty($manifestErrorMessage))
			{
				if (!empty($manifestLink))
				{
					//Common Error. User just need to Process again by clicking on Fetch Manifest Button.
					echo '<div class="error"><p>' . sprintf( __( 'Canada Post Fetching Manifest failed, Please click on Fetch Manifest :%s', 'CyDCanadaPost' ), $manifestErrorMessage) . '</p></div>';
				}
				else
				{
					echo '<div class="error"><p>' . sprintf( __( 'Canada Post Fetching Manifest Error:%s', 'CyDCanadaPost' ), $manifestErrorMessage) . '</p></div>';
				}
			}
			
			if(!empty($shipmentErrorMessage))
			{
					echo '<div class="error"><p>' . sprintf( __( 'Canada Post Create Shipment Error:%s', 'CyDCanadaPost' ), $shipmentErrorMessage) . '</p></div>';
			}
		}
		echo '<ul>';
		
		if (!empty($shipmentIds)) {
			$transmit_url = home_url('/?wf_canadapost_transmitshipment='.$post->ID);
			$manifestLink_url = home_url('/?wf_canadapost_getmanifest='.$post->ID);
			
			foreach($shipmentIds as $shipmentId) {
				echo '<li><strong>Shipment #:</strong> '.$shipmentId.'<hr>';
				$packageDetailForTheshipment = get_post_meta($order->id, 'wf_woo_canadapost_packageDetails_'.$shipmentId, true);
				$packageBoxName = get_post_meta($order->id, 'wf_woo_canadapost_boxid_'.$shipmentId, true);
				if(!empty($packageBoxName))
				{
					echo '<strong>Box Name: ' . '</strong>' . $packageBoxName . '<br>'; 
				}
				if(!empty($packageDetailForTheshipment))
				{
					foreach($packageDetailForTheshipment as $dimentionKey => $dimentionValue)
					{
						if(empty($dimentionValue)) continue;
						echo '<strong>' . $dimentionKey . ': ' . '</strong>' . $dimentionValue ;
						if($dimentionKey == 'Weight') echo ' kg<br>';
						else echo ' cm<br>';
					}
					echo '<hr>';
				}
				$download_url = home_url('/?wf_canadapost_viewlabel='.base64_encode($shipmentId.'|'.$post->ID));?>
				<a class="button tips" href="<?php echo $download_url; ?>" data-tip="<?php _e('Print Shipment Label', 'CyDCanadaPost'); ?>"><?php _e('Print Shipment Label', 'CyDCanadaPost'); ?></a><hr style="border-color:#0074a2">
				<?php 
				echo '</li>';
			} ?>		
			<?php 
			if(!empty($manifestArtifactLinkList)) { 
					foreach($manifestArtifactLinkList as $manifestArtifactLink)
					{
						$manifestArtifactdownloadLink = home_url('/?wf_canadapost_viewmanifest=' . base64_encode($manifestArtifactLink.'||'.$post->ID));?>
					<li><a class="button button-primary tips" href="<?php echo $manifestArtifactdownloadLink; ?>" data-tip="<?php _e('Print Manifest', 'CyDCanadaPost'); ?>"><?php _e('Print Manifest', 'CyDCanadaPost'); ?></a></li>	
					<?php 
					}
			} elseif (!empty($manifestLink)) { ?>
			<li><a class="button tips" href="<?php echo $manifestLink_url; ?>" data-tip="<?php _e('Fetch Manifest', 'CyDCanadaPost'); ?>"><?php _e('Fetch Manifest', 'CyDCanadaPost'); ?></a></li>
			<?php } else { ?>
			<li><a class="button button-primary tips" href="<?php echo $transmit_url; ?>" data-tip="<?php _e('Transmit Shipment', 'CyDCanadaPost'); ?>"><?php _e('Transmit Shipment', 'CyDCanadaPost'); ?></a></li>
			<?php }  ?>						

			<?php
		}
		else {
			$generate_url = home_url('/?wf_canadapost_createshipment='.$post->ID);?>
				<li><a class="button tips" href="<?php echo $generate_url; ?>" data-tip="<?php _e('Create Shipment', 'CyDCanadaPost'); ?>"><?php _e('Create Shipment', 'CyDCanadaPost'); ?></a></li>
			<?php
		}
		echo '</ul>';
	}
	
	private function wf_get_parcel_details($package){
		$weight = $this->wf_get_string_between($package,'<weight>','</weight>');
		$height = $this->wf_get_string_between($package,'<height>','</height>');
		$width = $this->wf_get_string_between($package,'<width>','</width>');
		$length = $this->wf_get_string_between($package,'<length>','</length>');
		return array('Weight' => $weight, 'Height' => $height, 'Width' => $width, 'Length' => $length);
	}
	
	private function wf_get_string_between($string, $start, $end){
		$string = " ".$string;
		$ini = strpos($string,$start);
		if ($ini == 0) return "";
		$ini += strlen($start);   
		$len = strpos($string,$end,$ini) - $ini;
		return substr($string,$ini,$len);
	}
	
	private function wf_load_order($orderId){
		if (!class_exists('WC_Order')) {
			return false;
		}
		return new WC_Order($orderId);      
	}
	
	private function wf_user_permission(){
		// Check if user has rights to generate invoices
		$current_user = wp_get_current_user();
		$user_ok = false;
		if ($current_user instanceof WP_User) {
			if (in_array('administrator', $current_user->roles) || in_array('shop_manager', $current_user->roles)) {
				$user_ok = true;
			}
		}
		return $user_ok;
	}
	
	private function wf_get_shipping_service($order){
		//TODO: Take the first shipping method. It doesnt work if you have item wise shipping method
		$shipping_methods = $order->get_shipping_methods();
		if ( ! $shipping_methods ) {
			return '';
		}

		$shipping_method = array_shift($shipping_methods);
		if (empty($shipping_method['method_id']) || strpos($shipping_method['method_id'], 'wf_shipping_canada_post:') === false) {
			return '';
		}

		if($shipping_method['method_id'])
		return str_replace('wf_shipping_canada_post:', '', $shipping_method['method_id']);
	}
	
	private function wf_get_package_request_wrapper($order){
		$orderItems = $order->get_items();
		foreach($orderItems as $orderItem)
		{
			$product_data   = wc_get_product( $orderItem['variation_id'] ? $orderItem['variation_id'] : $orderItem['product_id'] );
			$items[] = array('data' => $product_data , 'quantity' => $orderItem['qty']);
		}
		$package['contents'] = $items;
		return wf_weight_only_shipping($package,$this->max_weight);
	}
	
	private function wf_get_item_details_for_custom($order){
		if($order->shipping_country == 'CA') return '';

		$orderCurrency = $order->get_order_currency();
		$orderItems = $order->get_items();
		$returnString = '';
		foreach($orderItems as $orderItem)
		{
			$product_data   = wc_get_product( $orderItem['variation_id'] ? $orderItem['variation_id'] : $orderItem['product_id'] );

			$description = $product_data->get_title();
			$units = $orderItem['qty'];
			$weight = $product_data->get_weight();
			$value = $product_data->get_price();
			$returnString .= "<item>";
			$returnString .= "<customs-number-of-units>{$units}</customs-number-of-units>";
			$returnString .= "<customs-description>{$description}</customs-description>";
			$returnString .= "<unit-weight>{$weight}</unit-weight>";
			$returnString .= "<customs-value-per-unit>{$value}</customs-value-per-unit>";				
			$returnString .= "</item>";
		}
		if(!empty($returnString))
		{
			$returnString = "<customs><currency>{$orderCurrency}</currency><conversion-from-cad>1</conversion-from-cad><reason-for-export>SOG</reason-for-export><sku-list>{$returnString}</sku-list></customs>";			
		}
		return $returnString;
	}
	
	private function wf_get_request_header($accept,$contentType) {	   
	   return array(
			'Accept'          => $accept,
			'Content-Type'    => $contentType,
			'Authorization'   => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
			'Accept-language' => 'en-CA'
		);
    }	
}
new wf_woocommerce_canadapost_admin();
?>
