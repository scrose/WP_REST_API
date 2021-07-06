<?php

/**
* WP_Forum_Data: The file that defines the Forum API data class
*
* A class definition that includes attributes and functions used across both the
* public-facing side of the site and the admin area.
*
* @link       http://example.com
* @since      1.0.0
*
* @package    WP_Forum_API
* @subpackage WP_Forum_API/includes
*/

/**
* The class responsible for handling string translations (uses Polylang plugin)
*/
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cscan-forum-image.php';

/**
* The plugin Forum Data object class.
*
* This is used to define functionality for loading and handling Forum data.
*
* @since      1.0.0
* @package    WP_Forum_API
* @subpackage WP_Forum_API/includes
*/
class WP_Forum_Data {

  /**
  * Forum API settings
  *
  * @since    1.0.0
  * @access   protected
  * @var      WP_Forum_Data    $settings    Forum API settings
  */
  protected $settings;

  /**
  * Forum API data object from request
  *
  * @since    1.0.0
  * @access   protected
  * @var      WP_Forum_Data    $data    Imported Forum Data
  */
  protected $data;

  /**
  * Forum API data from request
  *
  * @since    1.0.0
  * @access   protected
  * @var      WP_Forum_Data    $url    Maintains and registers all hooks for the plugin.
  */
  protected $url;

  /**
  * Forum API image processor
  *
  * @since    1.0.0
  * @access   protected
  * @var      WP_Forum_Data    $img_processor    Handles updates to images on WP Media Library
  */
  protected $img_processor;


  /**
  * Constructor
  *
  * @since    1.0.0
  */
  public function __construct( $settings = NULL, $logger = NULL) {
    $this->id = 'data';
    $this->data = NULL;
    $this->fields = array();
    $this->url = NULL;

    // Initialize image processor
    $this->img_processor = new WP_Forum_Image( $logger );

    $this->filter_settings( $settings );
    $this->logger = $logger;
  }



  /** =============================================
  * Loads Forum API request URL from settings
  * Example: https://forum.cscan-infocan.ca/index.php?action=api.newsposting/new/2020-01-01
  *
  * @since    1.0.0
  * ============================================= */
  public function load_request( $section, $modified_date=NULL, $id=NULL) {
    // Build API base request url (set in WP Forum API settings)
    $base_url = $this->settings['url'];
    // Include action option (set in WP Forum API settings)
    $forum_action = $modified_date ? $this->settings[$section]['action'].'/new/'.$modified_date : $this->settings[$section]['action'];
    // include id / range (if provided)
    //$forum_action .= $id ? '/' . $id : ( $start>=0 && $count ? '/:' . $start . '/:' . $count : '' );
    //$forum_action .= $id ? '/' . $id : '';
    $this->url = add_query_arg( 'action', $forum_action, $base_url );

    // add security token if enabled
    if ($this->settings['key'] && $this->settings['token'])
    $this->url = add_query_arg( $this->settings['key'], $this->settings['token'], $this->url );

  }



  /** =============================================
  * Loads Forum API request URL from settings
  *
  * @since    1.0.0
  * ============================================= */
  public function load_data( $section, $modified_date=NULL, $id=NULL ) {

    // Build request URL
    $this->load_request( $section, $modified_date, $id );

    ini_set('max_execution_time', 40); // prevents 30 seconds from being fatal

    // cURL request to Forum API
    try {
      $start = microtime(true);
      $ch = curl_init();
      $curlConfig = array(
        CURLOPT_URL            => $this->url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_NOSIGNAL => 1,
        CURLOPT_TIMEOUT_MS => 5000
      );
      curl_setopt_array($ch, $curlConfig);
      // time response
      $result = curl_exec($ch);
      $curl_errno = curl_errno($ch);
      $curl_error = curl_error($ch);
      curl_close($ch);

      // handle timeout error
      if ( $curl_errno ){
        $this->logger->log( "Forum API timeout: $curl_error.", "error", $this->id );
        return;
      }
      // convert data feed to PHP array
      $this->data = json_decode( $result, true );

      // Check if page is API-enabled
      if ( empty($result) ) {
        $this->logger->log( "Forum data was not received at ".$this->url.".", "error", $this->id );
        return;
      }
      // Return the results
      return $this->data;

    } catch (Exception $e) {
      $error = $e->getMessage();
      $this->logger->log( "Forum API Connection Problem: $error.", "error", $this->id );
    }
    return;
  }


  /** =============================================
  * Extract field information from Forum templates
  *
  * @since    1.0.0
  * ============================================= */
  public function load_fields( $section ) {
    // Load section field templates from local files
    $template = file_get_contents(plugin_dir_path(__DIR__) . "admin/templates/$section.json");
    $template = json_decode( $template, true );

    // Display error if data is empty
    if (! $template ) {
      $this->logger->log("Data loaded to initialize $section fields are empty.", "error", $this->id );
    }

    // Extract fields from data
    $this->fields = $this->extract_fields( $template );

    // Display error if fields could not be loaded
    if (! $this->fields ) {
      $this->logger->log("No fields loaded to initialize $section settings.", "error", $this->id );
    }

    return $this->fields;
  }


  /** =============================================
  * Extract nested keys from array
  *
  * @since    1.0.0
  * ============================================= */
  public function extract_fields( $array ){
    $key_array = array();
    if ( is_array( $array ) ) {
      foreach ($array as $key => $value) {
        if ( is_array( $value ) ) {
          $key_array[$key] = $this->extract_fields( $value );
        }
        else {
          array_push($key_array, $key);
        }
      }
    }
    else {
      return $array;
    }
    return $key_array;
  }



  /** =============================================
  * Preprocess data by content section
  * Requires nested indices for recursive traversal
  *
  * @since    1.0.0
  * ============================================= */
  public function preprocess( $feed_data, $section, $active_fields ) {
    // Initialize filtered post arrays
    $filtered_post = array();
    $filtered_posts = array();
      // Extract field data from Forum post data
      if ( $feed_data ) {
        foreach ( $feed_data as $id => $post_data ) {
          // generate unique import ID (MD5 hashkey)
          $filtered_post['id'] = md5( $section . '_' . $post_data['id'] );
          // Load filtered ACF fields
          if ( isset($active_fields['fields']) && $active_fields['fields'] )
          $filtered_post['fields'] = $this->filter_data( $post_data, $active_fields['fields'], True );
          // Set delete flag
          if ( isset($active_fields['fields']['deleted']) )
          $filtered_post['deleted'] = $filtered_post['fields']['deleted'];
          // Set visibility flag
          if ( isset($active_fields['fields']['visibility']) )
          $filtered_post['visibility'] = $filtered_post['fields']['visibility'];
          // Set created timestamp flag
          if ( isset($active_fields['fields']['created']) )
          $filtered_post['created'] = $filtered_post['fields']['created'];
          // Process WP post fields
          $filtered_post['post_title'] = $this->filter_data( $post_data, $active_fields['post_title'] );
          $filtered_post['post_content'] = $this->filter_data( $post_data, $active_fields['post_content'] );
          $filtered_post['post_image'] = $this->filter_data( $post_data, $active_fields['post_image'] );
          // Include optional fields
          if (isset($active_fields['expiration_date'])) {
            $filtered_post['expiration_date'] = $this->filter_data( $post_data, $active_fields['expiration_date'] );
          }
          // Check that English content is not empty
          if ( empty($filtered_post['post_title']) || empty($filtered_post['post_title']) ) {
            $this->logger->log("Forum $section content and/or title field are not selected.", "error", $this->id );
            return;
          }
          // Default to English title/content if French fields are empty
          $post_title_fr = $this->filter_data( $post_data, $active_fields['post_title_fr'] );
          $filtered_post['post_title_fr'] = ( empty($post_title_fr) ) ? $filtered_post['post_title'] : $post_title_fr;
          $post_content_fr = $this->filter_data( $post_data, $active_fields['post_content_fr'] );
          $filtered_post['post_content_fr'] = ( empty($post_content_fr) ) ? $filtered_post['post_content'] : $post_content_fr;
          // Add field data to filtered data array
          array_push( $filtered_posts, $filtered_post );
        }
        return $filtered_posts;
      }
      else {
        // $this->logger->log("Forum $section data is empty.", "notice", $this->id );
      }
    }


    /** =============================================
    * Process Forum images to WP and retrieve WP attachement ID
    *
    * @since    1.0.0
    * ============================================= */
    public function process_image( $post_id, $img_src ) {
      // Generate hash key from image source URI
      $haskey = ( ! empty( trim($img_src) ) ) ? md5( $img_src ) : null;
      // Handle empty image src
      if ( empty( $img_src ) || empty( $haskey ) ) {
        $this->logger->log("Requested image source $img_src is empty.", "warning", $this->id );
        return false;
      }
      // Build image request url + add security token if enabled
      if ($this->settings['key'] && $this->settings['token'])
      $url = add_query_arg( $this->settings['key'], $this->settings['token'], $img_src );

      // Use image processor to import image to WP
      $attachment_id = $this->img_processor->import_image( $url, $haskey, $post_id );

      if ( $post_id && $attachment_id ) {
        // Set uploaded image to featured image on post
        set_post_thumbnail($post_id, $attachment_id);
      }
      return $attachment_id;
    }


    /** =============================================
    * Filter active fields in data
    * Requires nested indices for recursive traversal
    *
    * @since    1.0.0
    * ============================================= */
    public function filter_data( $data, $active_fields, $in_acf=False ) {
      // Set filtered data to array if ACF fieldset
      $filtered = ( ! $in_acf ) ? '' : array();
      if ( is_array( $data ) ) {
        foreach ( $data as $key => $value ) {
          if ( isset( $active_fields[$key] ) ) {
            if ( is_array( $active_fields[$key] ) && count( $active_fields[$key] ) == 0 ) continue;
            // Return only unique scalar
            if ( ! $in_acf )
            $filtered = ( is_array( $value ) ) ? $this->filter_data( $value, $active_fields[$key], $in_acf ) : $value;
            // Return array
            else
            $filtered[$key] = ( is_array( $value ) ) ? $this->filter_data( $value, $active_fields[$key], $in_acf ) : $value;
          }
        }
      }
      else {
        return $data;
      }
      return $filtered;
    }



    /** =============================================
    * Filter Forum API Settings for Data Handling
    *
    * @since    1.0.0
    * ============================================= */
    private function filter_settings( $settings ) {
      // Load field values stored in WP
      foreach ( $settings['submenu_pages'] as $section => $section_settings ) {
        // filter basic Forum API settings
        if ($section == 'main') {
          foreach ($section_settings['sections']['base']['settings'] as $setting_key => $setting ) {
            $this->settings[$setting_key] = get_option( $setting['id']);
          }
        }
        // filter section-specific settings
        else {
          foreach ($section_settings['sections']['base']['settings'] as $setting_key => $setting ) {
            // Process multiple fields
            if ( $setting['callback'] == 'wp_forum_api_textarea_render' ) {
              $fields_array = preg_split( '/\r\n|[\r\n]/', trim( get_option( $setting['id'] ) ) );
              foreach ( $fields_array as $field ) {
                $key_value_pair = explode( '|', $field, 3 );
                if (count( $key_value_pair ) == 3) {
                  $field_key = trim($key_value_pair[0]);
                  if ($field_key) {
                    $this->settings[$section][$setting_key][$field_key] =
                    array ( 'en' => trim($key_value_pair[1]), 'fr' => trim($key_value_pair[2]) );
                  }
                }
              }
            } else { // Process single field
              $this->settings[$section][$setting_key] = get_option( $setting['id'] );
            }
          }
        }
      }
    }

  } // END of WP_Forum_Data
