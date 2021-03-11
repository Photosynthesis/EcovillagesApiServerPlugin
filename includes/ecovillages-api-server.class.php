<?php

class Ecovillages_API_Server{

  public static $schema_file = 'gen_ecovillages_v0.0.1.json';
  public static $field_map_file = 'field_map.json';
  public static $index_field_map_file = 'index_field_map.json';
  public static $api_base_url = 'http://localhost/TestPress4/wp-json/ecovillages/v1/';

  public static function register_api_routes(){
    register_rest_route( 'ecovillages/v1', '/get/project/(?P<url>[a-zA-Z\-\d]+)', array(
    'methods' => 'GET',
    'callback' => array('Ecovillages_API_Server','get_project')
    ) );

    register_rest_route( 'ecovillages/v1', '/get/index/(?P<country>[a-zA-Z\-\d]+)', array(
    'methods' => 'GET',
    'callback' => array('Ecovillages_API_Server','get_index'),
    ) );

  }

  public static function get_project($req){

    $project = self::load_project((string)$req['url']);

    $schema = self::get_schema();

    $map = self::get_field_map();

    $json = Murmurations_Utilities::build_profile($schema,$project,$map);

    return rest_ensure_response($json);

  }


  public static function get_index($req){

    $projects_p = self::load_project_list($req);

    $projects = array();

    foreach ($projects_p as $p) {
      $projects[] = $p->to_array();
    }

    $map = self::get_index_field_map();

    $json = Murmurations_Utilities::build_index(array(),$projects,$map);

    return rest_ensure_response($json);

  }

  public static function load_project($url){

    global $wpdb;

    $sql = "SELECT * FROM $wpdb->posts WHERE post_name = '$url'";

    $result = $wpdb->get_results( $sql, ARRAY_A );

    if(count($result) < 1){
      return false;
    }

    $project = $result[0];

    $metas = get_post_meta($project['ID']);

    foreach ($metas as $key => $values) {
      $value = maybe_unserialize($values[0]);
      $project[$key] = $value;
    }

    $sql = "SELECT t.name, tt.taxonomy
    FROM wp_posts AS p
    LEFT JOIN wp_term_relationships AS tr ON tr.object_id = p.id
    LEFT JOIN wp_term_taxonomy AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
    LEFT JOIN wp_terms AS t ON t.term_id = tt.term_id
    WHERE  p.ID = '".$project['ID']."'";

    $taxonomies = $wpdb->get_results( $sql, ARRAY_A );

    $taxes = array();

    foreach ($taxonomies as $tax) {
      $taxes[$tax['taxonomy']][] = $tax['name'];
    }

    $project = array_merge($taxes,$project);

    return $project;
  }


  public static function load_project_list($parameters){
    global $wpdb;

    $args = array(
      'post_type' => 'gen_project',
      'post_status' => 'publish',
      'posts_per_page' => -1
    );

    if($parameters['country']){
      $args['tax_query'] = [
        [
            'taxonomy' => 'gen_country',
            'field'    => 'name',
            'terms'    => array($parameters['country'])
        ]
      ];
    }

    if($parameters['last_validated']){
      $args['date_query'] = [
        'column' => 'post_modified',
        'after'  => [
            'year'  => date('Y',strtotime($parameters['last_validated'])),
            'month' => date('m',strtotime($parameters['last_validated'])),
            'day'   => date('j',strtotime($parameters['last_validated'])),
        ],
      ];
    }

    $query = new WP_Query($args);

    return $query->get_posts();

  }

  public static function get_schema(){
    $schema_file = plugin_dir_path(__DIR__) .'schemas/'.self::$schema_file;
    // gen_ecovillages_v0.0.1.json';
    $schema_json = file_get_contents($schema_file);
    $schema = json_decode($schema_json,true);
    return $schema;
  }

  public static function get_field_map(){
    $map_file = plugin_dir_path(__DIR__) .self::$field_map_file;
    $map_json = file_get_contents($map_file);
    $map = json_decode($map_json,true);
    return $map;
  }

  public static function get_index_field_map(){
    $map_file = plugin_dir_path(__DIR__) .self::$index_field_map_file;
    $map_json = file_get_contents($map_file);
    $map = json_decode($map_json,true);
    return $map;
  }

  public static function process_coordinates($coords){
    return [
      'lat'=>$coords['lat'],
      'lon'=>$coords['lng'],
    ];
  }

  public static function reduce_array($a){
    return is_array($a) ? $a[0] : $a;
  }

  public static function generate_profile_url($post_name){
    return self::$api_base_url."get/project/".$post_name;
  }

  public static function generate_last_validated($date){
    return strtotime($date);
  }
}




?>
