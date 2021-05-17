<?php


//Google OAUTH Constants

//Product: Lead Deck
define('CESP_OAUTH_GOOGLE_CONVERSIONESP', array(
	'client_id' => '921937214798-84dce4275ouptmucg07f9l5gg6j223rg.apps.googleusercontent.com',
	'client_secret' => 'SiXowuCYGeTFqWXE4yrI-Jrt',
	'scope' => 'https://www.googleapis.com/auth/spreadsheets'
));


define('CESP_OAUTH_MAILCHIMP_CONVERSIONESP', array(
	'client_id' => '863894476927',
	'client_secret' => 'b9fd4d383bb69852d3b307010164c8147e5e3989567f6e2136'
));	
	
define('CESP_OAUTH_DRIP_CONVERSIONESP', array(
	'client_id' => '05142d11886c30bf36e7bcf90470e122f7dfc1d472b91e1539b4da6d21f0bbb2',
	'client_secret' => '9bb636e475ee15fdfa607a5b736bb79de48e4f1a0a52fe1bbd2c9fbf45db073e'
));	

define('CESP_OAUTH_GETRESPONSE_CONVERSIONESP', array(
	'client_id' => '94167da8-42db-11e9-bb53-f04da2754d84',
	'client_secret' => '4abf324c1178488139381f268efb07572b5d9f02'
));	

//ThriveCart
define('THRIVE_CART_SECRET', 'Z6NZIWMXTMEK');
define('THRIVE_CART_ACCOUNT', 'paulirvine');


//Product license maps for ThriveCart (See Wp_License_Manager_API::code_to_interval for details on duration)
define('THRIVE_CART_MAP', array(
	//Gold Level
	'product-49' => array(
		'local_plan' => 1, 
		'domains' => 50,
		'api' => false, //Should this plan enable the API key
		'alternative' => 'product_49',
		'groups' => array(4), //Add to Gold group
		'payment_plans' => array(
			'81824' => 0, //code_to_interval unlimited
			'9788' => 4  //code_to_interval 1 month
		)
	),
	//Silver Level
	'product-48' => array(
		'local_plan' => 1, 
		'domains' => 5,
		'api' => false, //Should this plan enable the API key
		'alternative' => 'product_48',
		'groups' => array(3), //Add to silver group
		'payment_plans' => array(
			'81824' => 0, //code_to_interval unlimited
			'83595' => 4 //code_to_interval 1 month
		)
	),
	//Bronze Level
	'product-46' => array(
		'local_plan' => 1, 
		'domains' => 1,
		'api' => false, //Should this plan enable the API key
		'alternative' => 'product_46',
		'groups' => array(2), //Add to bronze group
		'payment_plans' => array(
			'81824' => 0,  //code_to_interval unlimited
			'9682' => 4 //code_to_interval 1 month
		)
	),
	//License upsell/bump
	'upsell-5' => array(
		'local_plan' => '', 
		'domains' => '',
		'api' => true, //Should this plan enable the API key
		'alternative' => 'upsell_5',
		'groups' => array(),
		'payment_plans' => array(
			'81824' => 0, //code_to_interval unlimited
			'83595' => 4 //code_to_interval 1 month
		)
	)		
));

?>