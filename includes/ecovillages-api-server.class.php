<?php

class Ecovillages_API_Server {

	public static $schema_file          = 'gen_ecovillages_v0.0.1.json';
	public static $field_map_file       = 'field_map.json';
	public static $index_field_map_file = 'index_field_map.json';
	public static $api_route            = 'ecovillages/v1';
  public static $log_file             =  ECOVILLAGE_API_PATH . 'logs/ecovillage_api.log';
  public static $no_log_append        =  true;
  public static $log_buffer           =  "";

	public static function register_api_routes() {
		register_rest_route(
			self::$api_route,
			'/get/project/(?P<url>[a-zA-Z\-\d]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'Ecovillages_API_Server', 'get_project' ),
  				'permission_callback' => array( 'Ecovillages_API_Server', 'check_api_key' ),
			)
		);

		register_rest_route(
			self::$api_route,
			'/get/index',
			array(
				'methods'             => 'GET',
				'callback'            => array( 'Ecovillages_API_Server', 'get_index' ),
				'permission_callback' => array( 'Ecovillages_API_Server', 'check_api_key' ),
			)
		);

	}

	public static function get_project( $req ) {

		$project = self::load_project( $req['url'] );

		if ( ! $project ) {
			$result = new WP_Error( 'gen_api_project_not_found', 'No project exists with this URL', array( 'status' => 404 ) );
		} else {
			$schema = self::get_schema();

			$map = self::get_field_map();

			$result = Murmurations_Utilities::build_profile( $schema, $project, $map );

		}

    // Deal with PHP notices or other extraneous output
    if( ob_get_length() ){
      ob_clean();
    }

		return rest_ensure_response( $result );

	}

	public static function get_option( $option ) {
		$options = get_option( 'ecovillage_api_server_options' );
		return $options[ $option ];
	}

	public static function check_api_key( $request = null ) {

    if ( isset( $request['api_key'] ) ) {
      $client_key = $request['api_key'];
    } else if ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
      $client_key = $_SERVER['PHP_AUTH_USER'];
    } else {
      self::log( "Request is missing API key" );
      return new WP_Error( 'rest_forbidden', esc_html__( 'No API key provided', 'gen-api-server' ), array( 'status' => 401 ) );
    }

		$keys = self::get_option( 'api_keys' );

		$authorized = false;

		foreach ( $keys as $authorized_key ) {
			if ( $client_key == $authorized_key['key'] ) {
				$authorized = true;
			}
		}

		if ( ! $authorized ) {
			return new WP_Error( 'rest_forbidden', esc_html__( 'Invalid API key', 'gen-api-server' ), array( 'status' => 401 ) );
		} else {
			return true;
		}
	}

	public static function get_index( $req ) {

    self::log( "Index request: " . $_SERVER['REMOTE_ADDR'] );

		$project_posts = self::load_project_list( $req );

		if ( is_wp_error( $project_posts ) ) {

			$result = $project_posts;

		} else {

			$projects = array();

			foreach ( $project_posts as $p ) {
				$projects[] = $p->to_array();
			}

			$map = self::get_index_field_map();

			$defaults = array(
				'linked_schemas' => array(
					'gen_ecovillages_v0.0.1',
				),
			);

      self::log( count( $projects ) . " projects found" );

			$result = Murmurations_Utilities::build_index( $defaults, $projects, $map );
		}

    // Deal with PHP notices or other extraneous output
    if( ob_get_length() ){
      ob_clean();
    }

		return rest_ensure_response( $result );

	}

	public static function load_project( $url ) {

		global $wpdb;

    $args = array(
      'post_type'      => 'gen_project',
      'post_status'    => 'publish',
      'name'           => $url
    );

    $query = new WP_Query( $args );

    $results = $query->get_posts();

		if ( count( $results ) < 1 ) {
			return false;
		}

		$project = $results[0]->to_array();

		$metas = get_post_meta( $project['ID'] );

		foreach ( $metas as $key => $values ) {
			$value           = maybe_unserialize( $values[0] );
			$project[ $key ] = $value;
		}

		$sql = "SELECT t.name, tt.taxonomy
    FROM wp_posts AS p
    LEFT JOIN wp_term_relationships AS tr ON tr.object_id = p.id
    LEFT JOIN wp_term_taxonomy AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
    LEFT JOIN wp_terms AS t ON t.term_id = tt.term_id
    WHERE  p.ID = '" . $project['ID'] . "'";

		$taxonomies = $wpdb->get_results( $sql, ARRAY_A );

		$taxes = array();

		foreach ( $taxonomies as $tax ) {
			$taxes[ $tax['taxonomy'] ][] = $tax['name'];
		}

		$project = array_merge( $taxes, $project );

		$project['profile_url'] = self::generate_profile_url( $url );

		return $project;
	}


	public static function load_project_list( $parameters ) {
		global $wpdb;

		$args = array(
			'post_type'      => 'gen_project',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		if ( isset( $parameters['gen_region'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'gen_region',
					'field'    => 'name',
					'terms'    => array( $parameters['gen_region'] ),
				),
			);
		}

		if ( isset( $parameters['country'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'gen_country',
					'field'    => 'name',
					'terms'    => array( $parameters['country'] ),
				),
			);
		}

		if ( isset( $parameters['last_validated'] ) ) {
			$args['date_query'] = array(
				'column' => 'post_modified',
				'after'  => array(
					'year'  => date( 'Y', $parameters['last_validated'] ),
					'month' => date( 'm', $parameters['last_validated'] ),
					'day'   => date( 'j', $parameters['last_validated'] ),
				),
			);
		}

		$query = new WP_Query( $args );

		return $query->get_posts();

	}

	public static function get_schema() {
		$schema_file = plugin_dir_path( __DIR__ ) . 'schemas/' . self::$schema_file;
		$schema_json = file_get_contents( $schema_file );
		$schema      = json_decode( $schema_json, true );
		return $schema;
	}

	public static function get_field_map() {
		$map_file = plugin_dir_path( __DIR__ ) . self::$field_map_file;
		$map_json = file_get_contents( $map_file );
		$map      = json_decode( $map_json, true );
		return $map;
	}

	public static function get_index_field_map() {
		$map_file = plugin_dir_path( __DIR__ ) . self::$index_field_map_file;
		$map_json = file_get_contents( $map_file );
		$map      = json_decode( $map_json, true );
		return $map;
	}

	public static function process_coordinates( $coords ) {
		return array(
			'lat' => $coords['lat'],
			'lon' => $coords['lng'],
		);
	}

	public static function reduce_array( $a ) {
		return is_array( $a ) ? $a[0] : $a;
	}

	public static function generate_profile_url( $post_name ) {
		return get_rest_url() . self::$api_route . '/get/project/' . $post_name;
	}

	public static function generate_last_validated( $date ) {
		return strtotime( $date );
	}

  public static function log( $content , $meta = null ) {
    $log = date( DATE_ATOM ) . " ";
    $log .= $meta ? $meta . ': ' : '';
    $log .=  ( is_array( $content ) || is_object( $content ) ) ? print_r( (array) $content, true ) : $content;
    if ( is_writable( self::$log_file ) ) {
      if ( self::$no_log_append ){
        $flag = null;
        self::$log_buffer .= $log . "\n";
        $log = self::$log_buffer;
      } else {
        $flag = FILE_APPEND;
      }
      return file_put_contents( self::$log_file, $log . "\n", $flag );
    } else {
      return "Log file is not writable: " . self::$log_file;
    }
  }

	public static function settings_page() {

		if ( isset( $_POST['ecovillage_api_server_options'] ) ) {
			check_admin_referer( 'ecovillage_api_server_admin_form' );
			$update_options = array();
			$option_value   = json_decode( stripslashes( $_POST['ecovillage_api_server_options'] ), true );
			update_option( 'ecovillage_api_server_options', $option_value );
		}

		$option_values       = get_option( 'ecovillage_api_server_options');

    if ( $option_values ) {
      $current_values_json = json_encode( $option_values );
    } else {
      $current_values_json = false;
    }

		?>
   <div class="wrap">
	 <h1 class="wp-heading-inline">Ecovillage API Server</h1>

	 <form method="post" id="ecovillage-api-settings-form">
		 <?php wp_nonce_field( 'ecovillage_api_server_admin_form' ); ?>
	   <input type="hidden" id="ecovillage-api-settings-input" name="ecovillage_api_server_options" />
	 </form>

	 <form>
	   <div id="ecovillage-api-settings-fields-container"></div>
	   <input type="submit" id="submit" value="Save Settings" class="button button-primary button-large" style="margin-top:3em">
	 </form>
   </div>

   <script>
	 var options = {
	   disable_array_delete_all_rows: true,
	   disable_array_delete_last_row: true,
	   disable_array_reorder: true,
	   disable_collapse: true,
	   disable_edit_json: true,
	   disable_properties: true,
	   theme: 'barebones',
     <?php
     // JSON-editor doesn't handle empty starval objects well. https://github.com/json-editor/json-editor/issues/998.
     if($current_values_json){
       ?>
       startval: JSON.parse('<?php echo $current_values_json ?>'),
       <?php
     }
     ?>
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

	 var editor = new JSONEditor(document.getElementById('ecovillage-api-settings-fields-container'),options);

	 document.getElementById('submit').addEventListener('click',function(event) {
	   event.preventDefault();
	   document.getElementById('ecovillage-api-settings-input').value = JSON.stringify(editor.getValue());
	   var settings_form = document.getElementById("ecovillage-api-settings-form");
	   settings_form.submit();
	 });
	 </script>
		<?php
	}
}




?>
