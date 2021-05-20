<?php
error_reporting (E_ALL);
ini_set('dispay_errors', true);
define('WP_USE_THEMES', false);
$base = dirname(dirname(__FILE__));
require($base.'/../../wp-load.php');
if(!current_user_can('manage_options')) {
  die("Permission denied");
}

if($_GET['t'] && method_exists('GEN_API_Tests',$_GET['t'])){
  $f = $_GET['t'];
  if($_GET['p']){
    $p = $_GET['p'];
    GEN_API_Tests::$f($p);
  }else{
    GEN_API_Tests::$f();
  }
}

class GEN_API_Tests{

  public static function check_api_key(){
    $_SERVER["PHP_AUTH_USER"] = 'JD%2js9#dflj';
    $i = Ecovillages_API_Server::check_api_key();
    self::print($i,"Check API key");
  }

  public static function get_index($country = 'Canada'){
    $i = Ecovillages_API_Server::get_index(array('country'=>$country));
    self::print($i,"Index");
  }

  public static function load_project_list(){
    $args = [
      'country' => 'Canada',
      //'last_validated' => '2019-01-01'
    ];
    $p = Ecovillages_API_Server::load_project_list($args);
    self::print(count($p),"Number of projects");
    self::print($p,"Projects");
  }

  public static function get_field_map(){
    $p = Ecovillages_API_Server::get_field_map();
    self::print($p,"Map");
  }

  public static function get_project($url = 'tamera-0'){
    $p = Ecovillages_API_Server::get_project(array('url'=>$url));
    self::print($p,"Project");
  }

  public static function get_schema(){
    $p = Ecovillages_API_Server::get_schema();
    self::print($p,"Schema");
  }

  public static function load_project($url = 'tamera-0'){
    $p = Ecovillages_API_Server::load_project($url);
    self::print($p,"Project");
  }

  public static function test_api_routes(){

    $result = Ecovillages_API_Server::get_project();

  }

  public static function meta_stats(){
    $threshold = 2;
    global $wpdb;
    $sql = "SELECT ID, post_name FROM wp_posts WHERE post_type = 'gen_project'";
    //AND ID = 3742";
    $posts = $wpdb->get_results( $sql, ARRAY_A );

    $meta_stats = array();

    $num_posts = count($posts);
    $num_metas_checked = 0;

    foreach ($posts as $p) {
      $p_meta_stats = array();
      $meta = get_post_meta($p['ID']);
      foreach ($meta as $key => $value) {
        $num_metas_checked++;
        $p_meta_stats[$key] = count($value);
      }
      foreach ($p_meta_stats as $key => $num) {
        if($num > ($threshold - 1)){
          $meta_stats[$key][$p['post_name']] = $num;
        }
      }
    }

    self::print($meta_stats,"Meta stats");
    self::print($num_metas_checked,"Metas checked");

  }

  public static function inspect_project($id = 3742){

    global $wpdb;
    // The farm: 3737
    // Findhorn: 3763

    $all_data = array();

    $post = get_post($id,ARRAY_A);
    self::print($post,"Post");

    $meta = get_post_meta($id);
    foreach ($meta as $key_1 => $value_a) {
      foreach ($value_a as $key => $value) {
        $meta[$key_1][$key] = maybe_unserialize($value);
      }
    }

    self::print($meta,"Meta");

    $sql = "SELECT t.*, tt.*
    FROM wp_posts AS p
    LEFT JOIN wp_term_relationships AS tr ON tr.object_id = p.id
    LEFT JOIN wp_term_taxonomy AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
    LEFT JOIN wp_terms AS t ON t.term_id = tt.term_id
    WHERE  p.ID = ".$id."";

    $taxonomies = $wpdb->get_results( $sql, ARRAY_A );

    $taxes = array();

    foreach ($taxonomies as $tax) {
      $taxes[$tax['taxonomy']][] = $tax['name'];
    }

    self::print($taxonomies,"Terms");
    self::print($taxes,"Reshaped");

  }

  protected static function print($out,$name = null){
    echo "<pre>";
    echo $name ? $name.": " : "";
    echo (is_array($out) || is_object($out)) ? print_r((array)$out,true) : $out;
    echo "</pre>";
  }


}
?>
