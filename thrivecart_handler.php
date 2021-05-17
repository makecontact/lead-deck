<?php

//Main handler distribution
function thrivecart_handle_webhook() {

	//Check requests credentials
	if (isset($_REQUEST['thrivecart_account']) && $_REQUEST['thrivecart_account'] != THRIVE_CART_ACCOUNT) exit();
	if (isset($_REQUEST['thrivecart_secret']) && $_REQUEST['thrivecart_secret'] != THRIVE_CART_SECRET) exit();	
	if (isset($_REQUEST['mode']) && $_REQUEST['mode'] == 'live') {
	
		//handle the events
		switch( $_REQUEST['event'] ) {
			case 'order.success':
				thrive_cart_new_order();
				break;
			case 'order.refund':
				thrive_cart_refund();
				break;
			case 'order.subscription_cancelled':
				thrive_cart_cancel();
				break;
			case 'order.subscription_payment':
				thrive_cart_renewal();
				break;
			default:
				echo 'Event not supported';
				exit();
		}	
	
	} else {
		echo 'Nothing to do';
		exit();	
	}
}	

//Handle new order 
function thrive_cart_new_order() {
	global $wpdb;
	
	//Check the product is known
	$customer = $_REQUEST['customer'];
	$order_items = $_REQUEST['purchase_map'];
	$order = $_REQUEST['order'];	
	$order_id = isset($_REQUEST['order_id']) ? $_REQUEST['order_id'] : 'UNKNOWN';
	
	$need_license = false;
	$api_being_enabled = 0;
	//Is there a license key or api being enabled
	foreach ($order_items as $item) {
		if (isset(THRIVE_CART_MAP[$item])) {
			//License being added
			if (THRIVE_CART_MAP[$item]['local_plan'] != '') {				
				$need_license = true;
			}
			//API being enabled
			if (THRIVE_CART_MAP[$item]['api'] == true) {
				$api_being_enabled = 1;
			}
		}
	}
	//Do we need a local account for this sale?
	$wp_user_id = 0;
	$password = '';
	if ($need_license || $api_being_enabled == 1) {
		//Lookup or create an account for them
		if (username_exists($customer['email'])) {
			//Lookup the customer and grab the ID for their account
			$user = get_user_by( 'email', $customer['email'] );
			$wp_user_id = $user->ID;
		} else {
			//Create a user
			$password = wp_generate_password( 12, false );
			$userdata = array(
				'user_login'   => $customer['email'],
				'user_email'   => $customer['email'],
				'user_pass'    => $password,
				'display_name' => $customer['first_name'],
				'first_name'   => $customer['first_name'],
				'last_name'    => $customer['last_name']
			);

			//Create the account
			$wp_user_id = wp_insert_user( $userdata );
		}		
	}
	if ($need_license) {
		//Create the correct license
		$licenses = array();
		$groups = array();
		//Now add the license to the account
		foreach ($order_items as $item) {
			if (isset(THRIVE_CART_MAP[$item])) {
				//Find the charge for this item
				$charge = array();
				foreach ($order['charges'] as $c) {
					if ($c['item_identifier'] == THRIVE_CART_MAP[$item]['alternative']) {
						$charge = $c;
					}
				}
				if (!empty($charge)) {
					//Create the license
					array_push($licenses, array(
						'name' => $charge['name'],
						'key' => Wp_License_Manager_API::create_new_license(
							$wp_user_id, 
							THRIVE_CART_MAP[$item]['local_plan'], //Set which license plan to use
							THRIVE_CART_MAP[$item]['domains'], //Set the correct number of domains
							THRIVE_CART_MAP[$item]['payment_plans'][$c['payment_plan_id']], //Get the correct duration
							$_REQUEST['order_id'], //Remeber the order id used to create the license
							$c['payment_plan_id'], //Remeber the plan id in case we need to renew
							$item, //Remember the product id used in ThriveCart
							$api_being_enabled //This wil enable the API key for verification etc...
						)
					));
					//Add them to the group
					foreach (THRIVE_CART_MAP[$item]['groups'] as $g) {
						if (!in_array($g, $groups)) {
							array_push($groups, $g);
						}
					}
				}
			}
			//Add user to groups
			$wpdb->suppress_errors = true;
			foreach ($groups as $g) {
				$wpdb->insert(
					$wpdb->prefix . 'groups_user_group',
					array(
						'user_id' => $wp_user_id,
						'group_id' => $g,
					),
					array(
						'%d',
						'%d'
					)
				);
			}
			$wpdb->suppress_errors = false;
		}		
		//Build the email
		$headers[] = 'From: Lead Deck <support@leaddeck.co>';
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		//Create the message
		$message  = "<html><head><title></title></head>";
		$message .= "<body stye=\"background:white;padding:20px;\">";
		$message .= "<p><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Hello " . $customer['first_name'] . ",</span></span></p>";
		//Add the new account details
		if ($password != '') {
			$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Thank you very much for purchasing Lead Deck! To download the software, please sign into the license dashboard at <a href=\"https://leaddeck.co/dashboard\">https://leaddeck.co/dashboard</a> with your new account:</span></span></p>";
			$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Username - <strong>" . $customer['email'] . "</strong><br>Password - <strong>" . $password . "</strong></span></span></p>";
		} else {			
			$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Thank you very much for purchasing Lead Deck! To download the software, please sign into the license dashboard at <a href=\"https://leaddeck.co/dashboard\">https://leaddeck.co/dashboard</a>.</span></span></p>";
		}
		//Add the product keys purchased
		if (!empty($licenses)) {
			$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Here are the license keys needed to activate the software. Please keep them safe and avoid sharing them with anyone.</span></span></p>";			
			foreach ($licenses as $l) {			
				$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Product - <strong>" . $l['name'] . "</strong><br>Password - <strong>" . $l['key'] . "</strong></span></span></p>";
				
			}
		}	
		$message .= "<p><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Matthew, Founder<br /><a href=\"https://leaddeck.co\" style=\"color:#169E1C\"><strong>Lead Deck</strong></a></span></span></p>";
		$message .= "</body></html>";		
		//Send the messages
		wp_mail( $customer['email'], 'Lead Deck License Keys', $message, $headers );
		wp_mail( 'support@leaddeck.co', '[NEW ACCOUNT] Lead Deck License Keys', $message, $headers );
		//Completed
		echo 'ok';
		exit();
	} else if ($api_being_enabled == 1) {
		//Load licenses for this user
		$current = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'product_licenses WHERE user_id=' . $wp_user_id);
		if (count($current) == 1) {
			//Update the existing license key
	        $wpdb->update($wpdb->prefix . 'product_licenses',
	            array(
	                'api_active' => 1
	            ),
	            array(
	                'id' => $current[0]->license_key
	            ),
	            array(
	                '%d'
	            ),
	            array(
	                '%d'
	            )
	        );
	        //Send them the details
			$headers[] = 'From: Lead Deck <support@leaddeck.co>';
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			//Create the message
			$message  = "<html><head><title></title></head>";
			$message .= "<body stye=\"background:white;padding:20px;\">";
			$message .= "<p><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Hello " . $customer['first_name'] . ",</span></span></p>";
			//Add the new account details
			if ($password != '') {
				$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Thank you very much for activating Lead Deck services! Access your account by signing into the license dashboard at <a href=\"https://leaddeck.co/dashboard\">https://leaddeck.co/dashboard</a> with your new account:</span></span></p>";
				$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Username - <strong>" . $customer['email'] . "</strong><br>Password - <strong>" . $password . "</strong></span></span></p>";
			} else {			
				$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Thank you very much for activating Lead Deck services! Access your account by signing into the license dashboard at <a href=\"https://leaddeck.co/dashboard\">https://leaddeck.co/dashboard</a>.</span></span></p>";
			}
			//Add the API key to message
			$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Here is the API keys needed to activate Lead Deck services. Please keep it safe and avoid sharing them with anyone.</span></span></p>";
$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">API Key - <strong>" . $current[0]['second_key'] . "</strong></span></span></p>";			
				
			$message .= "<p><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Matthew, Founder<br /><a href=\"https://leaddeck.co\" style=\"color:#169E1C\"><strong>Lead Deck</strong></a></span></span></p>";
			$message .= "</body></html>";		
			//Send the messages
			wp_mail( $customer['email'], 'Lead Deck API Key', $message, $headers );
			wp_mail( 'support@leaddeck.co', '[API KEY] Lead Deck API Keys', $message, $headers );
			//Completed
			echo 'ok';
			exit();	
		} else {
			
			//Could not automatically issue the API key
			$headers[] = 'From: Lead Deck <support@leaddeck.co>';
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			//Create the message
			$message  = "<html><head><title></title></head>";
			$message .= "<body stye=\"background:white;padding:20px;\">";
			$message .= "<p><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Hello " . $customer['first_name'] . ",</span></span></p>";
			//Add the new account details
			if ($password != '') {
				$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Thank you very much for requesting to activate Lead Deck services! Access your account by signing into the license dashboard at <a href=\"https://leaddeck.co/dashboard\">https://leaddeck.co/dashboard</a> with your new account:</span></span></p>";
				$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Username - <strong>" . $customer['email'] . "</strong><br>Password - <strong>" . $password . "</strong></span></span></p>";
			} else {			
				$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Thank you very much for requesting to activate Lead Deck services! Access your account by signing into the license dashboard at <a href=\"https://leaddeck.co/dashboard\">https://leaddeck.co/dashboard</a>.</span></span></p>";
			}
			//Add the API key to message
			$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Our support team will contact you shortly when the API has been activated on your account.</span></span></p>";	
			$message .= "<p><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Matthew, Founder<br /><a href=\"https://leaddeck.co\" style=\"color:#169E1C\"><strong>Lead Deck</strong></a></span></span></p>";
			$message .= "</body></html>";		
			//Send the messages
			wp_mail( $customer['email'], 'Lead Deck API Key', $message, $headers );
			
			
			//Email support team
			$headers[] = 'From: Lead Deck <support@leaddeck.co>';
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
			//Create the message
			$message  = "<html><head><title></title></head>";
			$message .= "<body stye=\"background:white;padding:20px;\">";
			$message .= "<p><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Hello Support,</span></span></p>";
			//Customer details
			$message .= "<p><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Name - <strong>" . $customer['first_name'] . "</strong></span></span></p>";
			$message .= "<p><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Email - <strong>" . $customer['email'] . "</strong></span></span></p>";
			$message .= "<p><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Order ID - <strong>" . $order_id . "</strong></span></span></p>";
			//Contact support
			$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">The license key could not be activated automatically because ";	
			if (count($current) == 0) {
				//No licenses to apply change too, email admin
				$message .= "<strong>they do not have a license.</strong>";
			} else {
				//Too many licenses to apply upgrade too, email admin
				$message .= "<strong> they have more than one license.</strong>";				
			}
			$message .= "</span></span></p>";
			//Add the API key to message
			$message .= "<p style=\"padding-bottom:20px;\"><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">Our support team will contact you shortly when the API has been activated on your account.</span></span></p>";	
			$message .= "<p><span style=\"font-size:16px;line-height:24px;\"><span style=\"font-family:arial;\">License Engine</span></span></p>";
			$message .= "</body></html>";		
			//Send the messages
			wp_mail( 'support@leaddeck.co', 'Lead Deck API Activation Request', $message, $headers );			
			//Completed
			echo 'ok';
			exit();	
		}
	} else {
		echo 'No action required';
		exit();
	}	
}

//Cancel an order
function thrive_cart_refund() {
	global $wpdb;	

	//We can only handle full refunds - but this should always be the case
	$order = $_REQUEST['order'];
	$refund = $_REQUEST['refund'];
	if ($order['total'] == $refund['amount']) {		
		//Lookup the license
		$order_id = $_REQUEST['order_id'];
		//load keys for this order
		$licenses = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'product_licenses WHERE remote_order_id = ' . $order_id);
		$user_id = 0;
		$groups_to_remove = array();
		foreach($licenses as $l) {
			//Revoke
			Wp_License_Manager_API::revoke_keys($l->id);
			//Grab the groups assigned for this product
			$groups_to_remove = array_merge($groups_to_remove, THRIVE_CART_MAP[$l->remote_product_id]['groups']);
			$user_id = $l->user_id;			
		}
		//Delete keys
		$wpdb->delete(
			$wpdb->prefix . 'product_licenses',
			array(
				'remote_order_id' => $order_id
			),
			array(
				'%d'
			)
		);
		//Clean up groups
		thrive_carts_remove_groups($groups_to_remove, $user_id);
	}
	echo 'ok';
	exit();
}


//Called when a subscription payment renewals
function thrive_cart_renewal() {
	global $wpdb;	
	//Lookup the license
	$order_id = $_REQUEST['order_id'];
	//load keys for this order
	$licenses = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'product_licenses WHERE remote_order_id = ' . $order_id);
	foreach($licenses as $l) {
		if (isset(THRIVE_CART_MAP[ $l->remote_product_id ])) {
			//Get the duration exension code
			$duration = isset(THRIVE_CART_MAP[ $l->remote_product_id ]['payment_plans'][$l->remote_plan_id]) ? THRIVE_CART_MAP[ $l->remote_product_id ]['payment_plans'][$l->remote_plan_id] : -1;
			if ($duration != -1) {
				//Update expiration dates on the license
				Wp_License_Manager_API::extend_license($l->id, $duration);
			} else {
				error_log('Warning: Remote plan id was not in the product map');
			}
		} else {
			error_log('Warning: Remote product ID was not in the product map ');
		}
	}
	echo 'ok';
	exit();	
}


//Called when a subscription is cancelled
function thrive_cart_cancel() {
	global $wpdb;
	
	//Lookup the license
	$order_id = $_REQUEST['order_id'];
	//load keys for this order
	$licenses = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'product_licenses WHERE remote_order_id = ' . $order_id);
	$groups_to_remove = array();
	$user_id = 0;
	foreach($licenses as $l) {
		//Revoke
		Wp_License_Manager_API::revoke_keys($l->id);
		//Grab the groups assigned for this product
		$groups_to_remove = array_merge($groups_to_remove, THRIVE_CART_MAP[$l->remote_product_id]['groups']);
		$user_id = $l->user_id;	
	}
	//Clean the array
	$groups_to_remove = array_unique($groups_to_remove);
	//Delete keys
	$wpdb->delete(
		$wpdb->prefix . 'product_licenses',
		array(
			'remote_order_id' => $order_id
		),
		array(
			'%d'
		)
	);	
	//Clean up groups
	thrive_carts_remove_groups($groups_to_remove, $user_id);
	echo 'ok';
	exit();
}


//Remove access groups if licensing permissions have changed
function thrive_carts_remove_groups($groups_to_remove, $user_id) {
	global $wpdb;	
	$existing = array();
	//Get existing licenses
	$licenses = $wpdb->get_results('SELECT * from ' . $wpdb->prefix . 'product_licenses WHERE user_id=' . $user_id);
	foreach($licenses as $l) {
		$existing = array_merge($existing, THRIVE_CART_MAP[$l->remote_product_id]['groups']);		
	}
	$existing = array_unique($existing);
	if (!empty($existing)) {		
		//Remove existing groups from groups to remove.
		$t_groups = array();
		foreach ($groups_to_remove as $g) {
			if (!in_array($g, $existing)) {
				array_push($t_groups, $g);
			}
		}
		$groups_to_remove = $t_groups;
	}
	//Remove the groups
	foreach($groups_to_remove as $g) {
		$wpdb->delete(
			$wpdb->prefix . 'groups_user_group',
			array(
				'user_id' => $user_id,
				'group_id' => $g,
			),
			array(
				'%d',
				'%d'
			)
		);	
	}	
}
	
?>