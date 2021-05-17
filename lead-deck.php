<?php
/**
 * Lead Deck Site Plugin
 *
 * This is premium plugin that is supported and updated via our license.
 *
 * @package   Reheat\Main
 * @copyright Copyright (C) 2016-2019, Reheat - support@reheat.io
 *
 * @wordpress-plugin
 * Plugin Name: Lead Deck Site Plugin
 * Version:     1.0.0
 * Plugin URI:  https://lead-deck.com/
 * Description: Site configurationand services (Lead Deck partner)
 * Author:      Matthew Shelley
 * Author URI:  https://lead-deck.com/
 * Text Domain: lead-deck
 * Domain Path: /languages/
 *
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;	
}	

//Constants
require 'constants.php';

//Site functions
require 'site_functions.php';

//Handles OAUTH services for our plugins, e.g. Google OAUTH etc..
require 'oauth_handler.php';

//Handle purchases and refunds
require 'thrivecart_handler.php';

/*
	//Disable admin stuff
	add_action( 'admin_enqueue_scripts', 'temp_css_block' );
	
	function temp_css_block() {
		wp_register_style( 'temp-css', get_template_directory_uri() . '/admin-style.css', false, '1.0.0' );
		wp_enqueue_style( 'temp-css' );
	}

*/
?>