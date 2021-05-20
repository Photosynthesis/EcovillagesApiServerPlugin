<?php
/*
Plugin Name: GEN Ecovillages API
Plugin URI: https://github.com/Photosynthesis/gen-ecovillages-api-server
Description: Provide GEN project data for regional network websites
Version: 0.0.1
Author: Adam McKenty/Photosynthesis
*/

require_once plugin_dir_path( __FILE__ ) . 'includes/ecovillages-api-server.class.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/murmurations-utilities.class.php';

add_action( 'rest_api_init', array( 'Ecovillages_API_Server', 'register_api_routes' ) );


function ecovillage_api_server_settings_page() {

	$args = array(
		'page_title' => 'Ecovillage API Server Settings',
		'menu_title' => 'Ecovillage API',
		'capability' => 'manage_options',
		'menu_slug'  => 'ecovillage-api-settings',
		'function'   => array( 'Ecovillages_API_Server', 'settings_page' ),
	);

	add_menu_page( $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'] );

}


add_action( 'admin_menu', 'ecovillage_api_server_settings_page' );


