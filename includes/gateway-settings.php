<?php
function wpec_authnet_admin_form() {
	$authorize_testmode = get_option( 'authorize_testmode' );
	$authorize_testmode1 = "";
	$authorize_testmode2 = "";
	switch( $authorize_testmode ) {
		case 0:
			$authorize_testmode2 = "checked='checked'";
			break;

		case 1:
			$authorize_testmode1 = "checked='checked'";
			break;
	}

	$output = "
	<tr>
		<td>
			".__( 'Authorize API Login ID', 'wpsc_authorize_net' )."
		</td>
		<td>
			<input type='text' size='40' value='".get_option('authorize_login')."' name='authorize_login' />
		</td>
	</tr>
	<tr>
		<td>
			".__( 'Authorize Transaction Key', 'wpsc_authorize_net' )."
		</td>
		<td>
			<input type='text' size='40' value='".get_option('authorize_password')."' name='authorize_password' />
		</td>
	</tr>
	<tr>
		<td>
			".__( 'Test Mode', 'wpsc_authorize_net' )."
		</td>
		<td>
			<input type='radio' value='1' name='authorize_testmode' id='authorize_testmode1' " . $authorize_testmode1 . " />" . __( 'Yes', 'wpsc_authorize_net' ) . "&nbsp;
			<input type='radio' value='0' name='authorize_testmode' id='authorize_testmode2' " . $authorize_testmode2 . " />" . __( 'No', 'wpsc_authorize_net' ) . "
		</td>
	</tr>";

	return $output;
}

function wpec_authnet_save_admin_form() {
	if ( isset ( $_POST['authorize_login'] ) ) {
		update_option( 'authorize_login', $_POST['authorize_login'] );
	}

	if ( isset ( $_POST['authorize_password'] ) ) {
		update_option( 'authorize_password', $_POST['authorize_password'] );
	}

	if ( isset ( $_POST['authorize_testmode'] ) ) {
		update_option( 'authorize_testmode', $_POST['authorize_testmode'] );
	}
	return true;
}