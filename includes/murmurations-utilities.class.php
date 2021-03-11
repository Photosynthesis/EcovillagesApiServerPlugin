<?php

class Murmurations_Utilities{

  public static function build_profile($schema,$data,$map = null){
    // Schema: a murmurations schema as PHP array
    // Data: node data as associative array
    // Map: array that translates node data into schema format, possibly with callbacks
    $props = $schema['properties'];

    $profile = array();

    foreach ($props as $name => $attribs) {
      if($map[$name]['load_from']){
        $value = $data[$map[$name]['load_from']];
      }else{
        $value = $data[$name];
      }

      if($map[$name] && $value){
        if($map[$name]['callback'] && is_callable($map[$name]['callback'])){
          $value = call_user_func($map[$name]['callback'],$value,$name);
        }
      }
      $profile[$name] = $value;
    }

    return $profile;

  }


  public static function build_index($defaults,$nodes,$map = null){
    $props = $schema['properties'];

    $index = array();

    $defaults = [
      "linked_schemas" => [
        "gen_ecovillages_v0.0.1"
      ]
    ];

    $index_fields = [
      'profile_url',
      'last_validated',
      'geolocation',
      'location',
      'linked_schemas'
    ];

    foreach ($nodes as $node) {
      $index_node_data = [];
      foreach ($index_fields as $field) {

        if($map[$field]['load_from']){
          $value = $node[$map[$field]['load_from']];
        }else{
          $value = $node[$field];
        }

        if($map[$field] && $value){
          if($map[$field]['callback'] && is_callable($map[$field]['callback'])){
            $value = call_user_func($map[$field]['callback'],$value,$field);
          }
        }

        if(!$value && $defaults[$field]){
          $value = $defaults[$field];
        }

        $index_node_data[$field] = $value;
      }

      $index[] = $index_node_data;

    }

    return $index;

  }
}

  ?>
