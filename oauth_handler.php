<?php
/*
	
	OAUTH2 Handler for ThirdParties
	Connects plugins with OAUTH2 services which are blocked because
	the redirect_uri is static and must be proxied to allow access.
	
*/

//Add the intercept param to WordPress
function cesp_query_vars($vars) {
	$vars[] = '__oauth';
	$vars[] = '__tc';
	$vars[] = '__ldout';
	return $vars;
}


//Register handlers for both URLs
function cesp_oauth_patterns() {
	add_rewrite_rule('^oauth/([^/]*)/?', 'index.php?__oauth=$matches[1]', 'top');
	add_rewrite_rule('^thrivecart/([^/]*)/?', 'index.php?__tc=$matches[1]', 'top');
	add_rewrite_rule( 'signout/?', 'index.php?__ldout=1', 'top' );
	if ( get_option( 'cesp_remote_rules' ) != '1.5' ) {
		flush_rewrite_rules();
		update_option( 'cesp_remote_rules', '1.5' );
	}
}

//Detect which request is coming in
function cesp_request() {
	global $wp;
	if (isset($wp->query_vars['__oauth'])) {
		if ($wp->query_vars['__oauth'] == 'init') {
			cesp_oauth_handle_init();
		} else if ($wp->query_vars['__oauth'] == 'response') {
			cesp_oauth_handle_callback();
		} else if ($wp->query_vars['__oauth'] == 'refresh') {
			cesp_oauth_handle_refresh();
		}
	}
	if (isset($wp->query_vars['__tc'])) {
		thrivecart_handle_webhook();
	} 
	if (isset( $wp->query_vars['__ldout'])) {
		wp_logout();
		header('Location: /');
		exit();	
	}
}

//Register actions
add_action( 'query_vars', 'cesp_query_vars');
add_action( 'init', 'cesp_oauth_patterns');
add_action( 'parse_request', 'cesp_request');


//Handle refreshing the token when required
function cesp_oauth_handle_refresh() {
	global $wpdb;
	//Read in params for this call
	$license = isset($_REQUEST['l']) ? $_REQUEST['l'] : '';
	$type =  isset($_REQUEST['t']) ? $_REQUEST['t'] : '';
	$refresh =  isset($_REQUEST['r']) ? $_REQUEST['r'] : '';
	if ($license == '' || $type == '' || $refresh == '') {
		echo json_encode(array('error' => true, 'code' => 1, 'message' => 'Missing parameters'));
		exit();
	}
	//Check the license is in the database
	/*
	$valid = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . $wpdb->prefix . 'product_licenses WHERE license_key = %s',
			$license			
		)
	);
	if ($valid == null) {
		echo json_encode(array('error' => true, 'code' => 2, 'message' => 'Invalid license'));
		exit();
	}
	*/
	//Handle the OAUTH refresh call
	switch ($type) {
		case "Google":
			//Set the correct params
			$oauth_params = CESP_OAUTH_GOOGLE_CONVERSIONESP;
			//Request access from Google
			$result = wp_remote_post( 'https://www.googleapis.com/oauth2/v4/token', array(
				'body' => array(
					'refresh_token' => $refresh,
					'client_id' => $oauth_params['client_id'],
					'client_secret' => $oauth_params['client_secret'],
					'grant_type' => 'refresh_token'
				)
			));		
			if (is_wp_error( $result )) {
				echo json_encode(array('error' => true, 'message' => 'Google responded with an error when we asked for the refresh token.'));
				exit();
			} else {
				$json = json_decode($result['body'], true);
				if (isset($json['access_token'])) {
					//Return the token back for Google
					$response = array(
						'access_token' => $json['access_token'],
						'refresh_token' => $refresh, //Google doesn't change the refresh token
						'expires_in' => $json['expires_in'],
						'token_type' => $json['token_type']
					);
					echo json_encode(array('error' => false, 'token' => $response));
					exit();
				} else {
					echo json_encode(array('error' => true, 'message' => 'Google\'s response did not contain an access token.'));
					exit();
				}
			}				
			break;
		case "MailChimp":
			echo json_encode(array('error' => false, 'message' => 'MailChimp tokens do not expire.'));
			exit();		
			break;
		case "Drip":
			echo json_encode(array('error' => false, 'message' => 'Drip tokens do not expire.'));
			exit();		
			break;
		case "GetResponse":
			//Set the correct params
			$oauth_params = CESP_OAUTH_GETRESPONSE_CONVERSIONESP;
			//Request access
			$auth = base64_encode( $oauth_params['client_id'] . ':' . $oauth_params['client_secret'] );
			$result = wp_remote_post( 'https://api.getresponse.com/v3/token', array(
				'body' => array(
					'refresh_token' => $refresh,
					'client_id' => $oauth_params['client_id'],
					'client_secret' => $oauth_params['client_secret'],
					'grant_type' => 'refresh_token'
				),
				'headers' => array(
					'Authorization' => "Basic $auth"
				)
			));
			if (is_wp_error( $result )) {
				echo json_encode(array('error' => true, 'message' => 'GetResponse responded with an error when we asked for the refresh token.'));
				exit();
			} else {
				$json = json_decode($result['body'], true);
				if (isset($json['access_token'])) {
					//Return the token back for Google
					$response = array(
						'access_token' => $json['access_token'],
						'refresh_token' => $json['refresh_token'],
						'expires_in' => $json['expires_in'],
						'token_type' => $json['token_type']
					);
					echo json_encode(array('error' => false, 'token' => $response));
					exit();
				} else {
					echo json_encode(array('error' => true, 'message' => 'GetResponse\'s response did not contain an access token.'));
					exit();
				}
			}						
	}
	//Fallout at the bottome for unsupported type
	echo json_encode(array('error' => true, 'code' => 5, 'message' => 'Unsupported type'));
	exit();	
}

//Register a new request for an AUTH session
function cesp_oauth_handle_init() {
	global $wpdb;
	//Read in params for callback
	$license = isset($_REQUEST['l']) ? $_REQUEST['l'] : '';
	$product = isset($_REQUEST['p']) ? $_REQUEST['p'] : '';
	$type =  isset($_REQUEST['t']) ? $_REQUEST['t'] : '';
	$cb =  isset($_REQUEST['c']) ? $_REQUEST['c'] : '';	
	$page =  isset($_REQUEST['g']) ? $_REQUEST['g'] : '';	
	if ($license == '' || $type == '' || $cb == '' || $product == '' || $page == '') {
		echo json_encode(array('error' => true, 'code' => 1, 'message' => 'Missing parameters'));
		exit();
	}	
	//Check the license is in the database
	/*
	$valid = $wpdb->get_row(
		$wpdb->prepare(
			'SELECT * FROM ' . $wpdb->prefix . 'product_licenses WHERE license_key = %s',
			$license			
		)
	);
	if ($valid == null) {
		echo json_encode(array('error' => true, 'code' => 2, 'message' => 'Invalid license'));
		exit();
	}
	*/	
	//Generate a session key
	$session_key = wp_generate_password( 15, false );
	//Set a transient for 10 minutes, max time to complete the transaction.
	set_transient( 'cesp_oauth_' . $session_key, array(
		'license' => $license,
		'type' => $type,
		'callback' => $cb,
		'page' => $page
	), 60 * 10);
	//Handle the OAUTH initial call
	switch ($type) {
		case "Google":
			//Obtain the correct params for Google's OAUTH
			$oauth_params = array();
			switch ($product) {
				case '1':
					$oauth_params = CESP_OAUTH_GOOGLE_CONVERSIONESP;
					break;
				default:
					echo json_encode(array('error' => true, 'code' => 5, 'message' => 'Unsupported OAUTH product'));
					exit();
					break;
			}
			//Build the url
			$url = 'https://accounts.google.com/o/oauth2/v2/auth?redirect_uri=' . rawurlencode('https://leaddeck.co/oauth/response/');
			$url .= '&prompt=consent&response_type=code&client_id=' . $oauth_params['client_id'];
			$url .= '&scope=' . rawurlencode($oauth_params['scope']);
			$url .= '&access_type=offline&state=' . $session_key;
			wp_redirect( $url );
			exit();
			break;
		case "MailChimp":
			//Obtain the correct params
			$oauth_params = array();
			switch ($product) {
				case '1':
					$oauth_params = CESP_OAUTH_MAILCHIMP_CONVERSIONESP;
					break;
				default:
					echo json_encode(array('error' => true, 'code' => 5, 'message' => 'Unsupported OAUTH product'));
					exit();
					break;
			}
			//Build the url
			$url = 'https://login.mailchimp.com/oauth2/authorize?redirect_uri=' . rawurlencode('https://leaddeck.co/oauth/response/');
			$url .= '&response_type=code&client_id=' . $oauth_params['client_id'];
			$url .= '&state=' . $session_key;
			wp_redirect( $url );
			exit();
			break;
		case "Drip":
			//Obtain the correct params 
			$oauth_params = array();
			switch ($product) {
				case '1':
					$oauth_params = CESP_OAUTH_DRIP_CONVERSIONESP;
					break;
				default:
					echo json_encode(array('error' => true, 'code' => 5, 'message' => 'Unsupported OAUTH product'));
					exit();
					break;
			}
			//Build the url
			$url = 'https://www.getdrip.com/oauth/authorize?redirect_uri=' . rawurlencode('https://leaddeck.co/oauth/response/');
			$url .= '&response_type=code&client_id=' . $oauth_params['client_id'];
			$url .= '&state=' . $session_key;
			wp_redirect( $url );
			exit();
			break;
		case "GetResponse":
			//Obtain the correct params 
			$oauth_params = array();
			switch ($product) {
				case '1':
					$oauth_params = CESP_OAUTH_GETRESPONSE_CONVERSIONESP;
					break;
				default:
					echo json_encode(array('error' => true, 'code' => 5, 'message' => 'Unsupported OAUTH product'));
					exit();
					break;
			}
			//Build the url
			$url = 'https://app.getresponse.com/oauth2_authorize.html?';
			$url .= '&response_type=code&client_id=' . $oauth_params['client_id'];
			$url .= '&state=' . $session_key;
			wp_redirect( $url );
			exit();
			break;											
		default:
			echo json_encode(array('error' => true, 'code' => 3, 'message' => 'Unsupported OAUTH Request'));
			exit();
			break;
	}
	exit();	
}

//Get the token, cleanup and fire the callback at our plugin
function cesp_oauth_handle_callback() {
	//Decode the callback
	$state = isset($_REQUEST['state']) ? $_REQUEST['state'] : '';
	if ($state == '') {
		echo 'Callback state is missing from the reply.';
		exit();
	}
	$session = get_transient( 'cesp_oauth_' . $state);	
	if ($session == '') {
		echo 'Authentication session has expired. Try to complete with in 10 minutes.';
		exit();
	}
	//How to extract the code correctly
	$oauth_params = array();
	$auth_code = '';
	$refresh_token = '';
	$expires = 0;
	switch ($session['type']) {
		case "Google":
			$token = isset($_GET['code']) ? $_GET['code'] : '';
			if ($token == '') {
				echo 'Missing token in the response. Contact the administrator and let them know.';
				exit();
			}
			//Set the correct params
			$oauth_params = CESP_OAUTH_GOOGLE_CONVERSIONESP;
			//Request access from Google
			$result = wp_remote_post( 'https://www.googleapis.com/oauth2/v4/token', array(
				'body' => array(
					'code' => $token,
					'redirect_uri' => 'https://leaddeck.co/oauth/response/',
					'client_id' => $oauth_params['client_id'],
					'client_secret' => $oauth_params['client_secret'],
					'grant_type' => 'authorization_code'
				)
			));
			if (is_wp_error( $result )) {
				echo 'Google responded with an error when we asked for the token.';
				exit();
			} else {
				$json = json_decode($result['body'], true);
				if (isset($json['access_token'])) {
					//Grab OAUTH details
					$auth_code = $json['access_token'];
					$refresh_token =  $json['refresh_token']; 
					$expires = $json['expires_in'];
				} else {
					echo 'Google\'s response did not contain an access token.';
					exit();
				}
			}
			break;
		case "MailChimp":
			$token = isset($_GET['code']) ? $_GET['code'] : '';
			if ($token == '') {
				echo 'Missing token in the response. Contact the administrator and let them know.';
				exit();
			}
			$oauth_params = CESP_OAUTH_MAILCHIMP_CONVERSIONESP;
			//Request access from Google
			$result = wp_remote_post( 'https://login.mailchimp.com/oauth2/token', array(
				'body' => array(
					'code' => $token,
					'redirect_uri' => 'https://leaddeck.co/oauth/response/',
					'client_id' => $oauth_params['client_id'],
					'client_secret' => $oauth_params['client_secret'],
					'grant_type' => 'authorization_code'
				)
			));			
			if (is_wp_error( $result )) {
				echo 'MailChimp responded with an error when we asked for the token.';
				exit();
			} else {
				$json = json_decode($result['body'], true);
				if (isset($json['access_token'])) {
					//Grab OAUTH details
					$auth_code = $json['access_token'];
					$refresh_token =  ''; 
					$expires = 0;
				} else {
					echo 'MailChimp\'s response did not contain an access token.';
					exit();
				}			
			}
			break;
		case "Drip":
			$token = isset($_GET['code']) ? $_GET['code'] : '';
			if ($token == '') {
				echo 'Missing token in the response. Contact the administrator and let them know.';
				exit();
			}
			$oauth_params = CESP_OAUTH_DRIP_CONVERSIONESP;
			//Request access
			$result = wp_remote_post( 'https://www.getdrip.com/oauth/token', array(
				'body' => array(
					'code' => $token,
					'redirect_uri' => 'https://leaddeck.co/oauth/response/',
					'client_id' => $oauth_params['client_id'],
					'client_secret' => $oauth_params['client_secret'],
					'grant_type' => 'authorization_code'
				)
			));		
			if (is_wp_error( $result )) {
				echo 'Drip responded with an error when we asked for the token.';
				exit();
			} else {
				$json = json_decode($result['body'], true);
				if (isset($json['access_token'])) {
					//Grab OAUTH details
					$auth_code = $json['access_token'];
					$refresh_token =  ''; 
					$expires = 0;
				} else {
					echo 'Drip\'s response did not contain an access token.';
					exit();
				}			
			}
			break;
		case "GetResponse":
			$token = isset($_GET['code']) ? $_GET['code'] : '';
			if ($token == '') {
				echo 'Missing token in the response. Contact the administrator and let them know.';
				exit();
			}
			$oauth_params = CESP_OAUTH_GETRESPONSE_CONVERSIONESP;
			//Request access from GetResponse
			$auth = base64_encode( $oauth_params['client_id'] . ':' . $oauth_params['client_secret'] );
			$result = wp_remote_post( 'https://api.getresponse.com/v3/token', array(
				'body' => array(
					'code' => $token,
					'client_id' => $oauth_params['client_id'],
					'client_secret' => $oauth_params['client_secret'],
					'grant_type' => 'authorization_code'
				),
				'headers' => array(
					'Authorization' => "Basic $auth"
				)
			));	
			if (is_wp_error( $result )) {
				echo 'GetResponse responded with an error when we asked for the token.';
				exit();
			} else {
				$json = json_decode($result['body'], true);
				if (isset($json['access_token'])) {
					//Grab OAUTH details
					$auth_code = $json['access_token'];
					$refresh_token =  $json['refresh_token']; 
					$expires = $json['expires_in'];
				} else {
					echo 'GetResponse\'s response did not contain an access token.';
					exit();
				}			
			}
			break;									
	}	
	//Transmit back to the plugin that srequested this service
	$response = wp_remote_post( $session['callback'], array(
		'body' => array(
			'_oauth_token' => $auth_code,
			'_oauth_refresh' => $refresh_token,
			'_oauth_expires' => $expires,
			'_oauth_type' => $session['type'],
			'_oauth_license' => $session['license']
		)
	));
	if (is_wp_error( $response )) {
		echo '<p>Automatic import was not available. Please manually import the authorization key:</p><p><strong>' . $auth_code . '</strong><p/>';
		exit();
	} else {
		wp_redirect( $session['page'] );
		exit();
	}
}
?>