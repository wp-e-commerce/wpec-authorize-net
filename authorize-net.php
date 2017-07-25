<?php
class WPSC_Payment_Gateway_Authorize_Net extends WPSC_Payment_Gateway {

	private $endpoints = array(
		'sandbox' => array(
			'authorize_url' => 'https://test.authorize.net/gateway/transact.dll',
			'service_url'   => 'https://apitest.authorize.net/soap/v1/Service.asmx',
		),
		'production' => array(
			'authorize_url' => 'https://secure2.authorize.net/gateway/transact.dll',
			'service_url'   => 'https://api2.authorize.net/soap/v1/Service.asmx',
		)
	);

	private $aim_response_keys = array(
		'1' => 'response_code',
		'2' => 'response_sub_code',
		'3' => 'response_reason_code',
		'4' => 'response_description',
		'5' => 'authorization_code',
		'6'=> 'avs_response',
		'7' => 'transaction_id',
		'8' => 'invoice_number',
		'9' => 'description',
		'10' => 'amount',
		'11' => 'method',
		'12' => 'transaction_type',
		'13' => 'customer_id',
		'37' => 'purchase_order_number',
		'39' => 'card_code_response'
	);

	private $endpoint;
	private $sandbox;
	private $wdsl_url;

	public function __construct() {
		parent::__construct();

		$this->title            = __( 'Authorize.net', 'wpsc_authorize_net' );
		$this->supports         = array( 'default_credit_card_form', 'tev1' );
		$this->sandbox          = $this->setting->get( 'sandbox' ) == '1' ? true : false;
		$this->endpoint         = $this->sandbox ? $this->endpoints['sandbox'] : $this->endpoints['production'];
		$this->wdsl_url         = 'https://api2.authorize.net/soap/v1/Service.asmx?WSDL';

		// Define user set variables
		$this->api_id           = $this->setting->get( 'api_id' );
		$this->trans_key        = $this->setting->get( 'trans_key' );
	}

	public function init() {
		parent::init();
	}

	public function process() {
		$name_value_pairs = array();
		$order            = $this->purchase_log;

		require_once( dirname( __FILE__ ) . '/includes/anet_php_sdk/AuthorizeNet.php' );

		$this->credit_card_details = array(
			'card_number' => $_POST['authorize-net-card-number'],
			'expiry_date' => array( 'year' => $_POST['authorize-net-card-expiry-year'], 'month' => $_POST['authorize-net-card-expiry-month'] ),
			'card_code' => $_POST['authorize-net-card-cvc']
		);

		$transaction = new AuthorizeNetAIM( $this->setting->get( 'api_id' ), $this->setting->get( 'trans_key' ) );
		$transaction->setSandbox( $this->sandbox );

		$transaction->amount      = number_format( $order->get('totalprice'),2,'.','' );
		$transaction->card_num    = strip_tags( trim( $this->credit_card_details['card_number'] ) );
		$transaction->card_code   = strip_tags( trim( $this->credit_card_details['card_code'] ) );
		$transaction->exp_date    = strip_tags( trim( $this->credit_card_details['expiry_date']['month'] ) ) . '/' . strip_tags( trim( $this->credit_card_details['expiry_date']['year'] ) );
		$transaction->description = 'Your Shopping Cart';
		$transaction->first_name  = $this->checkout_data->get('billingfirstname');
		$transaction->last_name   = $this->checkout_data->get('billinglastname');
		$transaction->address     = $this->checkout_data->get('billingaddress');
		$transaction->city        = $this->checkout_data->get('billingcity');
		$transaction->country     = $this->checkout_data->get('billingcountry');
		$transaction->state       = $this->checkout_data->get('billingstate');
		$transaction->zip         = $this->checkout_data->get('billingpostcode');
		$transaction->customer_ip = $_SERVER['REMOTE_ADDR'];
		$transaction->email       = $this->checkout_data->get('billingemail');
		$transaction->invoice_num = $order->get('id');

		if ( wpsc_uses_shipping() ) {
			$transaction->ship_to_first_name  = $this->checkout_data->get('shippingfirstname');
			$transaction->ship_to_last_name   = $this->checkout_data->get('shippinglastname');
			$transaction->ship_to_address     = $this->checkout_data->get('shippingaddress');
			$transaction->ship_to_city        = $this->checkout_data->get('shippingcity');
			$transaction->ship_to_country     = $this->checkout_data->get('shippingcountry');
			$transaction->ship_to_state       = $this->checkout_data->get('shippingstate');
			$transaction->ship_to_zip         = $this->checkout_data->get('shippingpostcode');			
		}

		$transaction->setCustomField( 'sessionid', $order->get('sessionid') );
		$response = $transaction->authorizeAndCapture();

		if ( $response->approved ) {
			$order->set( 'processed', WPSC_Purchase_Log::ACCEPTED_PAYMENT )->save();
			$order->set( 'transactid', $response->transaction_id )->save();
			$this->go_to_transaction_results();
		} else {
			$order->set( 'processed', WPSC_Purchase_Log::INCOMPLETE_SALE )->save();

			//echo '<pre>'; print_r( $response ); echo '</pre>'; exit;
			if( isset( $response->response_reason_text ) ) {
				$error = $response->response_reason_text;
			} elseif( isset( $response->error_message ) ) {
				$error = $response->error_message;
			} else {
				$error = '';
			}

			if( strpos( strtolower( $error ), 'the credit card number is invalid' ) !== false ) {
				$err_text = __( 'Your card number is invalid', 'edda' );
			} elseif( strpos( strtolower( $error ), 'this transaction has been declined' ) !== false ) {
				$err_text = __( 'Your card has been declined', 'edda' );
			} elseif( isset( $response->response_reason_text ) ) {
				$err_text = $response->response_reason_text;
			} elseif( isset( $response->error_message ) ) {
				$err_text = $response->error_message;
			} else {
				$err_text = sprintf( __( 'An error occurred. Error data: %s', 'edda' ), print_r( $response, true ) );
			}

			$this->set_payment_error_message( $err_text );
			wp_safe_redirect( $this->get_shopping_cart_payment_url() );
		}
	}

	public function set_payment_error_message( $error ) {
		if ( wpsc_is_theme_engine( '1.0' ) ) {
			$messages = wpsc_get_customer_meta( 'checkout_misc_error_messages' );
			if ( ! is_array( $messages ) ) {
				$messages = array();
			}
			$messages[] = $error;
			wpsc_update_customer_meta( 'checkout_misc_error_messages', $messages );
		} else {
			WPSC_Message_Collection::get_instance()->add( $error, 'error', 'main', 'flash' );
		}
	}

	/**
	* Do SOAP request wrapper function
	* can use either the built in PHP library, or nusoap
	*/
	public function do_soap_request($function, $arguments) {
		$function = (string)$function;

		if(@extension_loaded('soap')) { // Check to see if PHP-SOAP is loaded, if so, use that
		  if(($this->soap_client == null) || !is_a($this->soap_client, 'SoapClient')) {
				$this->soap_client = @ new SoapClient($this->wdsl_url, array('soap_version' => SOAP_1_2, 'trace' => 1));
			}
			$this->soap_client->__setLocation( $this->endpoint['service_url'] );
			$returned_data = $this->soap_client->__soapCall($function, array($function => $arguments));
		} else { // otherwise include and use nusoap
		  if(($this->soap_client == null) || !is_a($this->soap_client, 'soapclient')) {
				include_once(WPSC_FILE_PATH.'/wpsc-includes/nusoap/nusoap.php');
				$this->soap_client = new soapclient($this->wdsl_url, true);
			}
			$this->soap_client->setEndpoint( $this->endpoint['service_url'] );
			$subscription_results = $this->soap_client->call($function, $arguments);
		}

		$returned_data = wpsc_object_to_array($returned_data);
		return $returned_data;
	}

	public function cancel_subscription($cart_id, $subscription_id) {
		$arb_body = array(
			/// Authentication Details go here
			'merchantAuthentication'=>array(
				'name'=>get_option('authorize_login'),
				'transactionKey'=>get_option("authorize_password")
			)	,
			'subscriptionId' => $subscription_id
		);
		
		$subscription_results = $this->do_soap_request('ARBCancelSubscription', $arb_body);

		if($subscription_results['ARBCancelSubscriptionResult']['resultCode'] == "Ok") {
			wpsc_update_cartmeta($cart_id, 'is_subscribed', 0);
		}
	}

	/**
	* construct ARB Array, constructs the array for the ARB SOAP requests
	* @access public
	*/
	public function construct_arb_array(&$cart_item) {
	  //print_r($cart_item);

	  /// Authorize.net ARB accepts days or months, nothing else
	  switch($cart_item['recurring_data']['rebill_interval']['unit']) {
	  	case "w":
	  	$arb_length = (int)$cart_item['recurring_data']['rebill_interval']['length'] * 7;
	  	$arb_unit = 'days';
	  	break;
	  	
	  	case "y":
	  	$arb_length = (int)$cart_item['recurring_data']['rebill_interval']['length'] / 12;
	  	$arb_unit = 'months';
	  	break;

	  	
	  	case "m":
	  	default:
	  	$arb_length = $cart_item['recurring_data']['rebill_interval']['length'];
	  	$arb_unit = 'months';
	  	break;
	  }
		if($cart_item['recurring_data']['charge_to_expiry'] !== true) {
			$arb_times_to_rebill = $cart_item['recurring_data']['times_to_rebill'];
	  } else {
			/// If subscription is permanent, rebill over 9000 times
	  	$arb_times_to_rebill = 9999;
	  }
	  if($arb_times_to_rebill > 1) {
			$arb_times_to_rebill--;
	  }

	  
		$arb_body = array(
			/// Authentication Details go here
			'merchantAuthentication'=>array(
				'name'=>get_option('authorize_login'),
				'transactionKey'=>get_option("authorize_password")
			)	,
			'subscription' => array(
				/// Name goes here
				'name' =>$cart_item['name'],
				/// Amount goes here
				'amount' => number_format($cart_item['price'],2,'.',''),
				'trialAmount' => number_format(0,2,'.',''),

				/// Payment Schedule goes here
				'paymentSchedule' => array(
					'interval' => array(
						'length' => $arb_length,
						'unit' => $arb_unit
					),
					'startDate' => gmdate("Y-m-d"),
					'totalOccurrences' => $arb_times_to_rebill,
					'trialOccurrences' => '1'
				),
				/// Payment Details go here
				'payment' => array(
					'creditCard' => array(
						'cardNumber' => $this->credit_card_details['card_number'],
						'expirationDate' => $this->credit_card_details['expiry_date']['month']."-".$this->credit_card_details['expiry_date']['year'],
						'cardCode' => $this->credit_card_details['card_code']
					)
				),
				/// Customer Details go Here
				'order' => array(
					//'invoiceNumber' => $this->cart_data['session_id']."123",
					'description' => ''
				),
				/// Customer Details go Here
				'customer' => array(
					//'id' => 1,
					'email' => $this->cart_data['email_address']
				),
				/// Billing Address Details go here
				'billTo' => array(
					'firstName' => $this->cart_data['billing_address']['first_name'],
					'lastName' => $this->cart_data['billing_address']['last_name'],
					'address' => $this->cart_data['billing_address']['address'],
					'city' => $this->cart_data['billing_address']['city'],
					//'state' => '',
					'zip' => $this->cart_data['billing_address']['post_code'],
					'country' => $this->cart_data['billing_address']['country']
				)
			)
		);
		return $arb_body;
	}	

	/**
	* parse AIM response, translate numeric keys into meaningful names.
	* @access public
	*/
	public function parse_aim_response( $split_response ) {
		$parsed_response = array();
		foreach($split_response as $key => $response_item) {
			if(isset($this->aim_response_keys[($key+1)])) {
				$parsed_response[$this->aim_response_keys[($key+1)]] = $response_item;
			}
		}
		return 	$parsed_response;
	}

	public function payment_fields( $args = array(), $fields = array() ) {
		$name = str_replace( '_', '-', $this->setting->gateway_name );

		$curryear = date( 'Y' );
		$curryear2 = date( 'y' );
		$years = '';
		//generate year options
		for ( $i = 0; $i < 10; $i++ ) {
			$years .= "<option value='" . $curryear2 . "'>" . $curryear . "</option>\r\n";
			$curryear++;
			$curryear2++;
		}

		if ( $this->supports( 'tev1' ) && '1.0' == get_option( 'wpsc_get_active_theme_engine' ) ) {
			// Show 2.0 gateway API table-based code
			?>
				<table class="wpsc_checkout_table <?php echo wpsc_gateway_form_field_style(); ?>">
					<?php do_action( 'wpsc_tev1_default_credit_card_form_start', $name ); ?>

					<tr>
						<td><?php _e( 'Card Number', 'wpsc_authorize_net' ); ?></td>
						<td>
							<input type="text" id="<?php esc_attr_e( $name ); ?>-card-number" value="" autocomplete="off" <?php echo $this->field_name( $name .'-card-number' ); ?> />
						</td>
					</tr>
					<tr>
						<td><?php _e( 'Expiration Date', 'wpsc_authorize_net' ); ?></td>
						<td>
							<input type="text" maxlength="2" id="<?php esc_attr_e( $name ); ?>-card-expiry-month" value="" autocomplete="off" placeholder="<?php esc_attr_e( 'MM', 'wp-e-commerce' ); ?>" <?php echo $this->field_name( $name .'-card-expiry-month' ); ?> />
							<input type="text" maxlength="2" id="<?php esc_attr_e( $name ); ?>-card-expiry-year" value="" autocomplete="off" placeholder="<?php esc_attr_e( 'YY', 'wp-e-commerce' ); ?>" <?php echo $this->field_name( $name .'-card-expiry-year' ); ?> />
						</td>
					</tr>
					<tr>
						<td><?php _e( 'Card Code', 'wpsc_authorize_net' ); ?></td>
						<td>
							<input type="text" id="<?php esc_attr_e( $name ); ?>-card-cvc" value="" autocomplete="off" placeholder="<?php esc_attr_e( 'CVC', 'wp-e-commerce' ); ?>" <?php echo $this->field_name( $name .'-card-cvc' ); ?> />
						</td>
					</tr>

					<?php do_action( 'wpsc_tev1_default_credit_card_form_end', $name ); ?>

				</table>
			<?php
		} else {
			$default_args = array(
				'fields_have_names' => true, // Some gateways like stripe don't need names as the form is tokenized.
			);
			$args = wp_parse_args( $args, apply_filters( 'wpsc_default_credit_card_form_args', $default_args, $this->setting->gateway_name ) );
			$default_fields = array(
				'card-number-field' => '<p class="wpsc-form-row wpsc-form-row-wide wpsc-cc-field">
					<label for="' . esc_attr( $name ) . '-card-number">' . __( 'Card Number', 'wp-e-commerce' ) . ' <span class="required">*</span></label>
					<input id="' . esc_attr( $name ) . '-card-number" class="input-text wpsc-cc-input wpsc-credit-card-form-card-number" type="tel" maxlength="20" autocomplete="off" placeholder="•••• •••• •••• ••••" ' . $this->field_name( $name .'-card-number' ) . ' />
				</p>',
				'card-expiry-field' => '<p class="wpsc-form-row-middle wpsc-cc-field">
					<label for="' . esc_attr( $name ) . '-card-expiry">' . __( 'Expiration Date', 'wp-e-commerce' ) . ' <span class="required">*</span></label>
					<input id="' . esc_attr( $name ) . '-card-expiry-month" class="input-text wpsc-cc-input wpsc-credit-card-form-card-expiry-month" type="tel" autocomplete="off" placeholder="' . esc_attr__( 'MM', 'wp-e-commerce' ) . '" ' . $this->field_name( $name .'-card-expiry-month' ) . ' />
					<input id="' . esc_attr( $name ) . '-card-expiry-year" class="input-text wpsc-cc-input wpsc-credit-card-form-card-expiry-year" type="tel" autocomplete="off" placeholder="' . esc_attr__( 'YY', 'wp-e-commerce' ) . '" ' . $this->field_name( $name .'-card-expiry-year' ) . ' />
				</p>',
				'card-cvc-field' => '<p class="wpsc-form-row-last wpsc-cc-field">
					<label for="' . esc_attr( $name ) . '-card-cvc">' . __( 'Card Code', 'wp-e-commerce' ) . ' <span class="required">*</span></label>
					<input id="' . esc_attr( $name ) . '-card-cvc" class="input-text wpsc-cc-input wpsc-credit-card-form-card-cvc" type="tel" maxlength="4" autocomplete="off" placeholder="' . esc_attr__( 'CVC', 'wp-e-commerce' ) . '" ' . $this->field_name( $name .'-card-cvc' ) . ' />
				</p>'
			);
			$fields = wp_parse_args( $fields, apply_filters( 'wpsc_default_credit_card_form_fields', $default_fields, $name ) );
			?>
			<fieldset class="cc-form-fieldset" id="<?php echo esc_attr( $name ); ?>-cc-form">
				<?php do_action( 'wpsc_default_credit_card_form_start', $name );
					foreach ( $fields as $field ) {
						echo $field;
					}
					do_action( 'wpsc_default_credit_card_form_end', $name ); ?>
				<div class="clear"></div>
			</fieldset>
		<?php
		}
	}

	public function setup_form() {
?>
		<!-- Account Credentials -->
		<tr>
			<td colspan="2">
				<h4><?php _e( 'Account Credentials', 'wpsc_authorize_net' ); ?></h4>
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-net-id"><?php _e( 'API Login ID', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'api_id' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'api_id' ) ); ?>" id="wpsc-anet-api-id" />
			</td>
		</tr>
		<tr>
			<td>
				<label for="wpsc-worldpay-secure-key"><?php _e( 'Transaction Key', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<input type="text" name="<?php echo esc_attr( $this->setting->get_field_name( 'trans_key' ) ); ?>" value="<?php echo esc_attr( $this->setting->get( 'trans_key' ) ); ?>" id="wpsc-anet-trans-key" />
			</td>
		</tr>
		<tr>
			<td>
				<label><?php _e( 'Sandbox Mode', 'wpsc_authorize_net' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'sandbox' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wpsc_authorize_net' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'sandbox' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'sandbox' ) ); ?>" value="0" /> <?php _e( 'No', 'wpsc_authorize_net' ); ?></label>
			</td>
		</tr>
		<!-- Error Logging -->
		<tr>
			<td colspan="2">
				<h4><?php _e( 'Error Logging', 'wpec-square' ); ?></h4>
			</td>
		</tr>
		<tr>
			<td>
				<label><?php _e( 'Enable Debugging', 'wpec-square' ); ?></label>
			</td>
			<td>
				<label><input <?php checked( $this->setting->get( 'debugging' ) ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'debugging' ) ); ?>" value="1" /> <?php _e( 'Yes', 'wpsc_authorize_net' ); ?></label>&nbsp;&nbsp;&nbsp;
				<label><input <?php checked( (bool) $this->setting->get( 'debugging' ), false ); ?> type="radio" name="<?php echo esc_attr( $this->setting->get_field_name( 'debugging' ) ); ?>" value="0" /> <?php _e( 'No', 'wpsc_authorize_net' ); ?></label>
			</td>
		</tr>
<?php
	}
}