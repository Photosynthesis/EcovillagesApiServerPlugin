<?php

class Murmurations_Utilities {

	public static function build_profile( $schema, $data, $map = null ) {
		// Schema: a murmurations schema as PHP array
		// Data: node data as associative array
		// Map: array that translates node data into schema format, possibly with callbacks
		$props = $schema['properties'];

		$profile = array();

		foreach ( $props as $name => $attribs ) {

      $value = null;

			if ( isset( $map[ $name ]['load_from'] ) ) {
        if ( isset( $data[ $map[ $name ]['load_from'] ] ) ){
          $value = $data[ $map[ $name ]['load_from'] ];
        }
			} else if ( isset( $data[ $name ] ) ) {
				$value = $data[ $name ];
			}

			if ( isset( $map[ $name ] ) && $value ) {
				if ( isset( $map[ $name ]['callback'] ) ) {
          if ( is_callable( $map[ $name ]['callback'] ) ) {
  					$value = call_user_func( $map[ $name ]['callback'], $value, $name );
          }
				}
			}
			$profile[ $name ] = $value;
		}

		return $profile;

	}


	public static function build_index( $defaults, $nodes, $map = null ) {

		$index = array();

		$index_fields = array(
			'profile_url',
			'last_validated',
			'geolocation',
			'location',
			'linked_schemas',
		);

		foreach ( $nodes as $node ) {
			$index_node_data = array();
			foreach ( $index_fields as $field ) {

        $value = null;

				if ( isset ( $map[ $field ]['load_from'] ) ) {
          if ( isset ( $node[ $map[ $field ]['load_from'] ] ) ){
            $value = $node[ $map[ $field ]['load_from'] ];
          }
				} else if ( isset ( $node[ $field ] ) ){
					$value = $node[ $field ];
				}

				if ( isset( $map[ $field ] ) && $value ) {
          if ( isset( $map[ $field ]['callback'] ) ) {
  					if ( is_callable( $map[ $field ]['callback'] ) ) {
  						$value = call_user_func( $map[ $field ]['callback'], $value, $field );
  					}
          }
				}

				if ( ! $value && isset( $defaults[ $field ] ) ) {
					$value = $defaults[ $field ];
				}

				if ( $value ) {
					$index_node_data[ $field ] = $value;
				}
			}

			$index[] = $index_node_data;

		}

		$out = array(
			'data' => $index,
		);

		return $out;

	}
}
