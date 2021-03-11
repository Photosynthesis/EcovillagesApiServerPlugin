<?php
/*
Plugin Name: GEN Ecovillages API
Plugin URI: https://github.com/Photosynthesis/gen-ecovillages-api-server
Description: Provide GEN project data for regional network websites
Version: 0.0.1
Author: Adam McKenty/Photosynthesis
*/

require_once plugin_dir_path( __FILE__ ) .'includes/ecovillages-api-server.class.php';
require_once plugin_dir_path( __FILE__ ) .'includes/murmurations-utilities.class.php';

add_action( 'rest_api_init',array('Ecovillages_API_Server','register_api_routes'));


function register_gen_taxonomies() {
    $labels = array(
        'name'              => _x( 'Countries', 'taxonomy general name', 'textdomain' ),
        'singular_name'     => _x( 'Genre', 'taxonomy singular name', 'textdomain' ),
    );

    $args = array(
        'labels'            => $labels,
        'rewrite'           => array( 'slug' => 'country' ),
    );

    register_taxonomy( 'gen_country', array( 'gen_project' ), $args );

}
add_action( 'init', 'register_gen_taxonomies', 0 );

?>
