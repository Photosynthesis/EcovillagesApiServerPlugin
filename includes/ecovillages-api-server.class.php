<?php

class Ecovillages_API_Server{

  public static $schema_file = 'gen_ecovillages_v0.0.1.json';
  public static $field_map_file = 'field_map.json';
  public static $index_field_map_file = 'index_field_map.json';
  public static $api_route = 'ecovillages/v1';

  // TODO: set these in admin interface
  public static $api_keys = array('JD%2js9#dflj');

  public static function register_api_routes(){
    register_rest_route(
      self::$api_route,
      '/get/project/(?P<url>[a-zA-Z\-\d]+)',
      array(
        'methods' => 'GET',
        'callback' => array('Ecovillages_API_Server','get_project'),
        'permission_callback' => array('Ecovillages_API_Server','check_api_key')
      )
    );

    register_rest_route(
      self::$api_route,
      '/get/index',
      array(
        'methods' => 'GET',
        'callback' => array('Ecovillages_API_Server','get_index'),
        'permission_callback' => array('Ecovillages_API_Server','check_api_key')
      )
    );

  }

  public static function get_project($req){

    $project = self::load_project((string)$req['url']);

    $schema = self::get_schema();

    $map = self::get_field_map();

    $json = Murmurations_Utilities::build_profile($schema,$project,$map);

    return rest_ensure_response($json);

  }

  public static function get_option($option){
    $options = get_option( 'ecovillage_api_server_options' );
    return $options[$option];
  }

  public static function check_api_key(){

    $client_key = $_SERVER["PHP_AUTH_USER"];

    $keys = self::get_option('api_keys');

    $authorized = false;

    foreach ($keys as $authorized_key) {
      if($client_key == $authorized_key['key']){
        $authorized = true;
      }
    }

    if(!$authorized){
      return new WP_Error( 'rest_forbidden', esc_html__( 'Invalid API key', 'gen-api-server' ), array( 'status' => 401 ) );
    }else{
      return true;
    }
  }

  public static function get_index($req){

    $projects_p = self::load_project_list($req);

    $projects = array();

    foreach ($projects_p as $p) {
      $projects[] = $p->to_array();
    }

    $map = self::get_index_field_map();

    $defaults = [
      "linked_schemas" => [
        "gen_ecovillages_v0.0.1"
      ]
    ];


    $data = Murmurations_Utilities::build_index($defaults,$projects,$map);

    return rest_ensure_response($data);

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

    $project['profile_url'] = self::generate_profile_url($url);

    return $project;
  }


  public static function load_project_list($parameters){
    global $wpdb;

    $args = array(
      'post_type' => 'gen_project',
      'post_status' => 'publish',
      'posts_per_page' => -1
    );

    if($parameters['gen_region']){
      $args['tax_query'] = [
        [
            'taxonomy' => 'gen_region',
            'field'    => 'name',
            'terms'    => array($parameters['gen_region'])
        ]
      ];
    }

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
    return get_rest_url().self::$api_route."/get/project/".$post_name;
  }

  public static function generate_last_validated($date){
    return strtotime($date);
  }

  public static function settings_page(){

    if (isset($_POST['ecovillage_api_server_options'])) {
      check_admin_referer( 'ecovillage_api_server_admin_form');
      $update_options = array();
      $option_value = json_decode( stripslashes($_POST['ecovillage_api_server_options']), true);
      update_option('ecovillage_api_server_options',$option_value);
    }

    $option_values = get_option('ecovillage_api_server_options');
    $current_values_json = json_encode($option_values);

   ?>
   <form method="post" id="gen_api_settings_form">
   <?php wp_nonce_field( 'ecovillage_api_server_admin_form' ); ?>
   <input type="hidden" id="gen_api_settings_input" name="ecovillage_api_server_options" />
   </form>

   <form>
     <div id="settings_fields_container"></div>
     <button id='submit'>Save</button>
   </form>
   <script src="https://cdn.jsdelivr.net/npm/@json-editor/json-editor@latest/dist/jsoneditor.min.js"></script>
   <script>
     var options = {
       disable_array_delete_all_rows: true,
       disable_array_delete_last_row: true,
       disable_array_reorder: true,
       disable_collapse: true,
       disable_edit_json: true,
       disable_properties: true,
       startval: JSON.parse(<?= json_encode($current_values_json) ?>),
       schema: {
         type: "object",
         title : "Settings",
         properties : {
           api_keys: {
             type: "array",
             format: "table",
             title: "API Keys",
             items: {
               type: "object",
               title: "Key",
               properties: {
                 name: {
                   type : "string",
                   title: "Name"
                 },
                 key:{
                   type : "string",
                   title : "Key"
                 }
               }
             }
           }
         }
       }
     };
     var editor = new JSONEditor(document.getElementById('settings_fields_container'),options);

     document.getElementById('submit').addEventListener('click',function(event) {
       event.preventDefault();
       document.getElementById('gen_api_settings_input').value = JSON.stringify(editor.getValue());
       var settings_form = document.getElementById("gen_api_settings_form");
       settings_form.submit();
     });
     </script>
  <?php
  }
}




?>