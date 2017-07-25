<?php
function wpec_anet_checkout_fields() {
	global $gateway_checkout_form_fields;

	if ( in_array( 'wpec_authnet_gateway', (array) get_option( 'custom_gateway_options' ) ) ) {
		
		$curryear = date( 'Y' );
		$curryear2 = date( 'Y' );
		$years = '';
		//generate year options
		for ( $i = 0; $i < 10; $i++ ) {
			$years .= "<option value='" . $curryear2 . "'>" . $curryear . "</option>\r\n";
			$curryear++;
			$curryear2++;
		}
		
		ob_start(); 
	?>
		</br>
		<tr>
			<td class="wpsc_CC_details"> <?php _e( 'Credit Card Number *', 'wpsc_authorize_net' ); ?></td>
			<td>
				<input type="text" value='' name="card_number" />
			</td>
		</tr>
		<tr>
			<td class='wpsc_cc_details'><?php _e( 'Credit Card Expiry(mm/yy) *', 'wpsc_authorize_net' ); ?></td>
			<td>
				<select class='wpsc_ccBox' name='expiry[month]'>
					<option value='01'>01</option>
					<option value='02'>02</option>
					<option value='03'>03</option>
					<option value='04'>04</option>
					<option value='05'>05</option>
					<option value='06'>06</option>
					<option value='07'>07</option>
					<option value='08'>08</option>
					<option value='09'>09</option>
					<option value='10'>10</option>
					<option value='11'>11</option>
					<option value='12'>12</option>
				</select>
				<select class='wpsc_ccBox' name='expiry[year]'>
					<?php echo $years; ?>
				</select>
			</td>
		</tr>
		<tr>
			<td class='wpsc_CC_details'><?php _e( 'CVC *', 'wpsc_authorize_net' ); ?></td>
			<td><input type='text' size='4' value='' maxlength='4' name='card_code' /></td>
		</tr>
		<?php
		$gateway_checkout_form_fields['wpec_authnet_gateway'] = ob_get_clean();
	}
}