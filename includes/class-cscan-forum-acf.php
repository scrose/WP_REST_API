<?php

/**
 * The file that defines the Forum API ACF Fields class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @since      1.0.0
 *
 * @package    WP_Forum_API
 * @subpackage WP_Forum_API/includes
 */

/**
 * The plugin Forum WP Database object class.
 *
 * This is used to define functionality for loading and handling Forum data.
 *
 * @since      1.0.0
 * @package    WP_Forum_API
 * @subpackage WP_Forum_API/includes
 */
class WP_Forum_ACF {
	/**
	 * Forum category slug
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_ACF    FORUM_TAX    Forum API custom category slug
	 */
	const FORUM_TAX = "category";
	const FORUM_TYPE = "post";
	const TRIBE_EVENT_TYPE = 'tribe_events';
	const TRIBE_VENUE_TYPE = 'tribe_venue';
	const TRIBE_ORGANIZER_TYPE = 'tribe_organizer';
	const TRIBE_EVENT_TAX = 'tribe_events_cat';
	const ACF_DIR = "/acf-json/";

	/**
	 * ACF field groups
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_ACF    $groups    Created ACF Forum field groups.
	 */
	protected $groups;

	/**
	 * Forum API data from request
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_ACF    $settings    Forum API settings.
	 */
	protected $settings;

	/**
	 * Forum API ACF local directory
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_ACF    $acf_dir    Local directory for ACF field settings.
	 */
	protected $acf_dir;

	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 */
	public function __construct( $settings, $logger ) {
		$this->id = 'acf_fields';
		$this->filter_settings( $settings );
		$this->groups = array();
		$this->logger = $logger;

		// Initialize acf directory
		$this->acf_dir = get_template_directory() . WP_Forum_ACF::ACF_DIR;

		// Create acf directory in theme directory if none exists
		if ( ! is_dir( $this->acf_dir ) ) {
			mkdir( $this->acf_dir, 0755, true );
		}

	}



 /** =============================================
	* Returns all active fields for given content section
	*
	* @since    1.0.0
	* ============================================= */
	public function get_active_fields( $section ) {
		// Initialize arrays
		$active_fields = array();
		$req_activation = [];

		// Get field settings for this section
		if (
			! isset($this->settings[$section]['sections']['fields']['settings']['fields']['args'])
		) {
			$this->logger->log("Forum $section fields not properly configured in settings file.", "error", $this->id);
			$this->logger->message("Forum $section fields not properly configured in settings file.", "error");
			return null;
		}
		$field_settings = $this->settings[$section]['sections']['fields'];
		$fields = $field_settings['settings']['fields']['args'];

		// Load activated field values
		foreach ($fields as $field_name => $field_id) {
			// Load ACF fields by ID: Load user-activated fields for this section
			if ( $field_name == 'id' ) {
				$active_fields['fields'] = get_option( $field_id );
			}
			elseif ( $field_name == 'section' ) {
				$active_fields['section'] = $field_id;
			}
			else {
				$option_value = get_option( $field_id );
				$active_fields[$field_name] = $this->format_fields( get_option( $field_id ) );
				if ( ! $active_fields['fields'] )
					array_push( $activation, $field_name );
			}
			// if ( is_array( $active_fields[$field_name] ) )
			// 	array_push( $active_fields, key($active_fields[$field_name]) );
		}
		// Check for fields not properly activated
		if ( $req_activation ) {
			$req_fields = implode(", ", $req_activation);
			$this->logger->log("Forum $section fields $req_fields require activation.", "warning", $this->id);
			$this->logger->message("Forum $section fields $req_fields require activation.", "notice-warning");
		}
		// Return active fields
		return $active_fields;
	}


	 /** =============================================
		* Create nested fields from linear field array
		*
		* @since    1.0.0
		* ============================================= */
		public function format_fields( $fields_str ) {
			// create array of indices and remove first index (i.e. section index)
			$fields = explode( '/', $fields_str );
			array_shift( $fields );
			$result = array();
			$target =& $result;
			// format linear array as nested indices for recursive tree traversal
			foreach( $fields as $key => $field ) {
				$target[$field] = array();
				$target =& $target[$field];
			}
			$target = 1;
			return $result;
		}


/** =============================================
 * Register ACF fields with WP
 *
 * @since    1.0.0
 * ============================================= */
	public function register_fields() {

		// Register fields for each section
		foreach ( $this->settings as $section => $field_settings ) {
			// Retrive user-activated Forum fields
			$active_fields = $this->get_active_fields( $section );
			if ( ! $active_fields ) {
				$this->logger->log("No activated fields found for $section.", "warning", $this->id);
				return false;
			}

			// get section ACF activation options
			$field_settings = $field_settings['sections']['fields'];
			$grp_title = $field_settings['title'];
			$grp_id = $field_settings['settings']['fields']['id'];
			$fields = $active_fields['fields'];
			// build section group
			$post_type = ( 'events' === $section ) ? WP_Forum_ACF::TRIBE_EVENT_TYPE : WP_Forum_ACF::FORUM_TYPE;
			$taxonomy = ( 'events' === $section ) ? WP_Forum_ACF::TRIBE_EVENT_TAX : WP_Forum_ACF::FORUM_TAX;
			$this->build_group( array('key' => $grp_id, 'title' => $grp_title, 'fields' => $fields), $section, $post_type, $taxonomy );
		}
		return true;
	}




 /** =============================================
 * Build ACF Group from Forum field settings
 *
 * @since    1.0.0
 * ============================================= */
	private function build_group( $fieldset, $section, $post_type, $taxonomy ) {
		$acf_fields = array();
		$date = new DateTime();
		$tribe_prefix = 'forum-';
		// Section categories
		$cat_ids = $this->get_categories($section);

		// Create taxonomy slugs
		$cat_slug_en = ('events' === $section ) ? $tribe_prefix.$section.'-en' : $section.'-en';
		$cat_slug_fr = ('events' === $section ) ? $tribe_prefix.$section.'-fr' : $section.'-fr';

		$timestamp = $date->getTimestamp();
		$json_filepath = $this->acf_dir . 'group_' . md5($fieldset['key']) . '.json';
		$group = array (
				'key'      => $fieldset['key'],
				'title'    => $fieldset['title'],
				'fields'	 => $this->build_fields( $section, $fieldset['fields'], $acf_fields ),
				'location' => array(
						array(
							array(
								'param' => 'post_type',
								'operator' => '==',
								'value' => $post_type,
							),
							array(
								'param' => 'post_category',
								'operator' => '==',
								'value' => $taxonomy.':'.$cat_slug_en,
							),
						),
						array(
							array(
								'param' => 'post_type',
								'operator' => '==',
								'value' => $post_type,
							),
							array(
								'param' => 'post_category',
								'operator' => '==',
								'value' => $taxonomy.':'.$cat_slug_fr,
							),
						),
					),
				'menu_order'            => 0,
				'position'              => 'side',
				'style'                 => 'default',
				'label_placement'       => 'top',
				'instruction_placement' => 'label',
				'hide_on_screen'        => '',
				'active'        				=> 1,
				'modified'							=> $timestamp
			);

		// Write ACF field group to local file
		if ( file_exists($this->acf_dir) ) {
			$fp = fopen( $json_filepath, 'w');
				fwrite($fp, json_encode($group, JSON_PRETTY_PRINT));
			fclose($fp);
		} else {
			$error = "ACF local file: ". $json_filepath. " cannot be found. Ensure the folder <b>'.$this->acf_dir.'</b> is created in the theme root to initialize ACF fields for the Forum API.";
			$this->logger->log($error, "warning", $this->id);
			$this->logger->message($error, "notice-warning");
		}

		// store created section group
		$this->groups[$section] = $group;
	}


 /** =============================================
	* Build ACF Fields from Forum field settings
	*
	* @since    1.0.0
	* ============================================= */
	private function build_fields( $section, $fields, $acf_fields ) {
		if ( is_array( $fields ) ) {
			foreach ($fields as $key => $field) {
				if ( is_array( $field ) ) {
					// add ACF second-level group
					$field_options = $this->build_fields( $section, $field, array() );
					$acf_fields = array_merge( $acf_fields, $field_options );
				}
				else {
					// add ACF fields to internal group
					$field_options = array (
						'key' => $section.'_'.$key,
						'label' => ucfirst($key),
						'name' => $section.'_'.$key,
						'type' => 'text',
					);
					array_push( $acf_fields, $field_options );
				}
			}
		}
		return $acf_fields;
	}



	 /** =============================================
		* Get section content category IDs (EN/FR)
		*
		* @since    1.0.0
		* ============================================= */
	 function get_categories( $section ) {

		 $cat_ids = new stdClass();
		 $cat_ids->en = term_exists( $section.'-en', WP_Forum_ACF::FORUM_TAX );
		 $cat_ids->fr = term_exists( $section.'-fr', WP_Forum_ACF::FORUM_TAX );

		 // Confirm category terms exist
		 if ($cat_ids->en && $cat_ids->fr) {
			 $cat_ids->en = $cat_ids->en['term_id'];
			 $cat_ids->fr = $cat_ids->fr['term_id'];
		 }
		 else {
			 $this->logger->log( "Category terms not found.", "error", $this->id );
		 }

		 return $cat_ids;
		}


	/** =============================================
		* Update ACF field values in WP database
		*
		* @since    1.0.0
		* ============================================= */
		public function update( $post_id, $field_data, $section ) {

			// Flatten nested data array
			$field_data = $this->flatten($field_data);

			// update fields individually
			foreach ( $field_data as $field => $value ) {
				$field_key = $section.'_'.$field;
				update_field( $field_key, $value, $post_id);
			}
		}


		/** =============================================
 		* Flatten nested data array
 		*
 		* @since    1.0.0
 		* ============================================= */
 		public function flatten( $array ){
 			$flat_array = array();
 			if ( is_array( $array ) ) {
 				foreach ($array as $key => $value) {
 					if ( is_array( $value ) ) {
 						$flat_array = array_merge($flat_array, $this->flatten( $value ));
 					}
 					else {
 						$flat_array[$key] = $value;
 					}
 				}
 			}
 			return $flat_array;
 		}



		/** =============================================
		 * Filter API settings for class usage
		 *
		 * @since    1.0.0
		 * ============================================= */
		private function filter_settings( $settings ) {
			// remove first "main" section of Forum content settings
			$filtered_settings = $settings['submenu_pages'];
			array_shift( $filtered_settings );
			$this->settings = $filtered_settings;
		 }

		/** =============================================
		 * Get array of section names
		 *
		 * @since    1.0.0
		 * ============================================= */
		public function get_sections() {
				return array_keys( $this->settings );
		}

} // end Forum API: ACF API
