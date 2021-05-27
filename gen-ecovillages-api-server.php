<?php
/*
Plugin Name: GEN Ecovillages API Server
Plugin URI: https://github.com/Photosynthesis/gen-ecovillages-api-server
Description: Provide GEN project data for regional network websites
Version: 0.0.1
*/

define( 'ECOVILLAGE_API_PATH', plugin_dir_path( __FILE__ ) );

require_once ECOVILLAGE_API_PATH . 'includes/ecovillages-api-server.class.php';
require_once ECOVILLAGE_API_PATH . 'includes/murmurations-utilities.class.php';

add_action( 'rest_api_init', array( 'Ecovillages_API_Server', 'register_api_routes' ) );


function ecovillage_api_server_settings_page() {

	$args = array(
		'page_title' => 'Ecovillage API Server Settings',
		'menu_title' => 'Ecovillage API',
		'capability' => 'manage_options',
		'menu_slug'  => 'ecovillage-api-settings',
		'function'   => array( 'Ecovillages_API_Server', 'settings_page' ),
	);

	$settings_page = add_menu_page( $args['page_title'], $args['menu_title'], $args['capability'], $args['menu_slug'], $args['function'] );

  add_action( 'load-' . $settings_page, function() {
    add_action( 'admin_enqueue_scripts', function() {
      wp_enqueue_script(
        'ecovillage-api-json-editor',
        'https://cdn.jsdelivr.net/npm/@json-editor/json-editor@latest/dist/jsoneditor.min.js'
      );
      wp_enqueue_style( 'ecovillage-api-admin-styles', plugins_url( 'css/ecovillage-api-admin-styles.css', __FILE__ ) );
    } );
  } );
}


add_action( 'admin_menu', 'ecovillage_api_server_settings_page' );
