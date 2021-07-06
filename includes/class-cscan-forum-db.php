<?php

/** =============================================
* The file that defines the Forum API WP Database class
*
* A class definition that includes attributes and functions used across both the
* public-facing side of the site and the admin area.
*
* @link       http://example.com
* @since      1.0.0
*
* @package    WP_Forum_API
* @subpackage WP_Forum_API/includes
* ============================================= */


/** =============================================
* The plugin Forum WP Database object class.
*
* This is used to define API functionality with WP database.
*
* @since      1.0.0
* @package    WP_Forum_API
* @subpackage WP_Forum_API/includes
* ============================================= */
class WP_Forum_DB {

	// Constants
	const FORUM_TAX = 'category';
	const FORUM_POST_TYPE = 'post';
	const FORUM_DATE_FORMAT = 'Y-m-d';

	/**
	* Handles data loading/preprocessing
	*
	* @since    1.0.0
	* @access   protected
	* @var      WP_Forum_Data    $loader    Handles data loading/preprocessing
	*/
	protected $loader;

	/**
	*  Handles custom meta fields (ACF).
	*
	* @since    1.0.0
	* @access   protected
	* @var      WP_Forum_ACF    $fields    Handles custom meta fields (ACF).
	*/
	protected $fields;

	/**
	* The Events Calendar plug-in API class object
	*
	* @since    1.0.0
	* @access   protected
	* @var      WP_Forum_Events    $events   API with The Events Calendar WP plug-in
	*/
	protected $events;

	/**
	* Polylang plug-in API class object
	*
	* @since    1.0.0
	* @access   protected
	* @var      WP_Forum_DB    $pll   API with Polylang WP plug-in
	*/
	protected $pll;

	/**
	* Forum API settings
	*
	* @since    1.0.0
	* @access   protected
	* @var      WP_Forum_API    $settings    Forum API general settings
	*/
	protected $settings;

	/**
	* Logger class object
	*
	* @since    1.0.0
	* @access   protected
	* @var      WP_Forum_Logger    $logger    Logs errors and notifications.
	*/
	protected $logger;

	/**
	* WP Database API :: Constructor
	*
	* @since    1.0.0
	*/
	public function __construct($loader, $fields, $events, $pll, $settings, $logger) {
		$this->loader = $loader;
		$this->fields = $fields;
		$this->events = $events;
		$this->pll = $pll;
		$this->settings = $settings;
		$this->logger = $logger;
		$this->post_type = WP_Forum_DB::FORUM_POST_TYPE;
		$this->taxonomy = WP_Forum_DB::FORUM_TAX;
	}


	/** =============================================
	* Create WP post parameters
	*
	* @since    1.0.0
	* ============================================= */
	private function build_post( $id, $title, $content, $post_type, $cat_id, $import_id ) {
		// initialize English post data
		return array(
			'ID'            => $id,
			'post_author'   => 1,
			'post_title'    => $title,
			'post_content'  => $content,
			'post_type'     => $post_type,
			'post_status'   => ( $id ) ? get_post( $id )->post_status : 'publish',
			'post_category' => array( $cat_id ),
			'meta_input'    => array('import_id' => $import_id)
		);
	}

	/** =============================================
	* Update WP database with imported data per Forum section
	*
	* @since    1.0.0
	* ============================================= */
	public function update_section( $section, $cutoff_date=null, $id=null ) {

		// Load active field settings
		$active_fields = $this->fields->get_active_fields( $section );
		// Pull Forum data
		$results = $this->loader->load_data( $section, $cutoff_date, $id );
		// filter imported data for section (separates ACF from WP fields)
		$filtered_data = $this->loader->preprocess( $results, $section, $active_fields );

		// Abort if imported data is empty
		if ( ! $filtered_data ) return;
		# Check if updating Event posts NOTE: Event actions require
		# special db operations through Events Calendar plug-in
		$events = ($section == 'events');
		$post_type = ($events) ? $this->events->post_type : $this->post_type;
		$taxonomy = ($events) ? $this->events->taxonomy : $this->taxonomy;
		// Retrieve language codes (en/fr)
		$lang = $this->pll->lang_codes();

		// Execute WP database operations
		try {
			foreach ( $filtered_data as $key => $import_data ) {
				// Import ID required
				if ( !isset($import_data['id']) ) return;

				// Initialize import vars
				$import_id = $import_data['id'];
				$title = $import_data['post_title'];
				$fields = $import_data['fields'];
				$post_image = (isset($import_data['post_image'])) ? $import_data['post_image'] : null;

				# Retrieve WP post data by import ID
				$wp_data = $this->get_post_data( $import_data['id'], $post_type );

				// Validate and retrieve post IDs
				$post_ids = $this->validate_ids( $wp_data, $import_id );

				// Initialize database response flag
				$response = false;

				// Determine status of post public visibility
				// NOTE: Forum visibility does not (yet) affect whether posts are
				// visible on the website. If a post is published ('Publish') in the Forum,
				// it remains in WP if the visibility is then set to 'Draft'.
				$is_deleted = (isset($import_data['deleted'])) ? $import_data['deleted'] : false;
				$post_status = (isset($import_data['visibility'])) ? $import_data['visibility'] : 'Publish';

				// Get post expiration date if set
				$expiration_date = (isset($import_data['expiration_date'])) ? $import_data['expiration_date'] : null;

				// Option 1: Delete WP post
				if ( $is_deleted || $post_status != 'Publish' ) {
					if ( !$post_ids ) continue; // Post already deleted (ignore)
					$action = 'delete';
					$response = $this->delete( $section, $post_ids );
				}
				// Option 2: Update WP posts
				else if ( $post_ids ) {
					$action = 'update';
					$response = ($events) ? $this->events->update( $section, $import_data, $post_ids ) : $this->update( $section, $import_data, $post_ids );
				}
				// Option 3: Insert WP posts
				else {
					$action = 'insert';
					$response = ($events) ? $this->events->insert( $section, $import_data ) : $this->insert( $section, $import_data );
				}

				// Get returned post IDs
				$post_ids = (is_object($response)) ? $response : $post_ids;

				// Continue update to post metadata
				if ($post_ids && ($action == 'insert' || $action == 'update')) {
					// [ACF] Update meta fields
					$this->fields->update( $post_ids->en, $fields, $section, $lang->en );
					$this->fields->update( $post_ids->fr, $fields, $section, $lang->fr );
					// [Images] Process attached image (if exists)
					if ( $post_image ) {
						$this->loader->process_image( $post_ids->en, $post_image );
						$this->loader->process_image( $post_ids->fr, $post_image );
					}
					// Add expiration date (en/fr) if set
					if ($expiration_date) {
						$this->set_expiration($post_ids->en, $expiration_date);
						$this->set_expiration($post_ids->fr, $expiration_date);
					}
				}
				// Handle response to action
				$context = "posts ".$post_ids->en.":".$post_ids->fr." (\"$title\") in $section. Import ID $import_id";

				// Notification log message
				if ($response)
					$this->logger->log("Successful $action: $context", "notice", "database" );
				else
					$this->logger->log("Failed $action: $context", "warning", "database" );
			}
		}
		catch (Exception $e) {
			$error = $e->getMessage();
			$this->logger->log("Forum $section posts update failed: " . $error, "error", "database");
			return;
		}
		return;
	}


	/** =============================================
	* Create new WP post from Forum import data
	*
	* @since    1.0.0
	* ============================================= */
	private function insert( $section, $import_data ) {

		// Import data is required to proceed
		if ( !$import_data ) {
			$this->logger->log("Imported Forum post data is empty.", "error", "database");
			return;
		}

		// Initialize import vars
		$import_id = $import_data['id'];
		$ttl_en = $import_data['post_title'];
		$ttl_fr = $import_data['post_title_fr'];
		$cnt_en = $import_data['post_content'];
		$cnt_fr = $import_data['post_content_fr'];
		$fields = $import_data['fields'];

		// Initialize Post IDs
		$post_ids = new stdClass();
		// Retrieve current language codes (en/fr)
		$lang = $this->pll->lang_codes();
		// Retrieve forum content categories
		// For events: get Event (Tribe) categories
		$cat_ids = ($section == 'events') ? $this->events->get_categories() : $this->fields->get_categories( $section );
		// Post type
		$post_type = WP_Forum_DB::FORUM_POST_TYPE;

		// Build WP post arrays (en/fr)
		$post_ids->en = $this->build_post( 0, $ttl_en, $cnt_en, $post_type, $cat_ids->en, $import_id );
		$post_ids->fr = $this->build_post( 0, $ttl_fr, $cnt_fr, $post_type, $cat_ids->fr, $import_id );

		// Insert post (en/fr)
		$post_ids->en = wp_insert_post($post_ids->en);
		$post_ids->fr = wp_insert_post($post_ids->fr);

		// Handle insert errors
		if ( is_wp_error($post_ids->en) ) {
			$this->logger->log($post_ids->en->get_error_message(), "error", "database");
			$this->logger->message($post_ids->en->get_error_message(), "error");
			return;
		}
		if ( is_wp_error($post_ids->fr) ) {
			$this->logger->log($post_ids->fr->get_error_message(), "error", "database");
			$this->logger->message($post_ids->fr->get_error_message(), "error");
			return;
		}

		// Include taxonomy categories (en/fr)
		// For cron scheduled updates (See: https://core.trac.wordpress.org/ticket/19373)
		wp_set_post_terms($post_ids->en, array( $cat_ids->en ), $this->taxonomy);
		wp_set_post_terms($post_ids->fr, array( $cat_ids->fr ), $this->taxonomy);

		// [Polylang] set forum post language (en/fr)
		$this->pll->set_post_language( $post_ids->en, $lang->en );
		$this->pll->set_post_language( $post_ids->fr, $lang->fr );

		// [Polylang] Assigns the translated post
		$this->pll->save_post_translations(array(
			$lang->en => $post_ids->en,
			$lang->fr => $post_ids->fr
		));

		return $post_ids;
	}


	/** =============================================
	* Update WP post using Forum import data
	*
	* @since    1.0.0
	* ============================================= */
	private function update( $section, $import_data, $post_ids ) {

		// Import data is required to proceed
		if ( !$import_data ) {
			$this->logger->log("Imported Forum post data is empty.", "error", "database");
			return;
		}

		// Initialize context vars
		$import_id = $import_data['id'];
		$ttl_en = $import_data['post_title'];
		$ttl_fr = $import_data['post_title_fr'];
		$cnt_en = $import_data['post_content'];
		$cnt_fr = $import_data['post_content_fr'];
		$fields = $import_data['fields'];
		$post_image = $import_data['post_image'];

		// Retrieve current language codes (en/fr)
		$lang = $this->pll->lang_codes();

		// Retrieve forum content categories
		// For events: get Event (Tribe) categories
		$cat_ids = ($section == 'events') ? $this->events->get_categories() : $this->fields->get_categories( $section );

		// Build WP post arrays (en/fr)
		$post_data_en = $this->build_post( $post_ids->en, $ttl_en, $cnt_en, $this->post_type, $cat_ids->en, $import_id );
		$post_data_fr = $this->build_post( $post_ids->fr, $ttl_fr, $cnt_fr, $this->post_type, $cat_ids->fr, $import_id );

		// Update post
		$post_ids->en = wp_update_post($post_data_en);
		$post_ids->fr = wp_update_post($post_data_fr);

		// Handle insert errors
		if ( is_wp_error($post_ids->en) ) {
			$this->logger->log($post_ids->en->get_error_message(), "error", "database");
			$this->logger->message($post_ids->en->get_error_message(), "error");
			return;
		}
		if ( is_wp_error($post_ids->fr) ) {
			$this->logger->log($post_ids->fr->get_error_message(), "error", "database");
			$this->logger->message($post_ids->fr->get_error_message(), "error");
			return;
		}

		return $post_ids;
	}


	/** =============================================
	* Delete posts by Import ID
	*
	* @since    1.0.0
	* ============================================= */
	function delete( $section, $post_data ) {
		// Delete posts with common import ID
		$events = ($section == 'events');
		$success = false;
		if ($post_data) {
			foreach ($post_data as $lang => $post_id) {
				// Force delete of posts
				$response = ($events) ? $this->events->delete( $post_id ) : wp_delete_post( $post_id, true );
				$success = ($response) ? true : false;
			}
		}
		return $success;
	}

	/** =============================================
	* Delete post from WP database
	*
	* @since    1.0.0
	* ============================================= */
	public function delete_section( $section ) {

		// Set section parameters
		$events = ($section == 'events');
		$post_type = ($events) ? $this->events->post_type : $this->post_type;
		$taxonomy = ($events) ? $this->events->taxonomy : $this->taxonomy;

		// Force delete of posts
		if ( $section ) {
			// Retrieve current language codes (en/fr)
			$lang = $this->pll->lang_codes();
			// Load section posts
			$section_posts = $this->get_section_post_ids( $section );

			// Delete all WP posts for section
			if ( $section_posts ) {
				foreach ($section_posts as $key => $wp_post) {
					$post_id = $wp_post->ID;
					$response = ($events) ? $this->events->delete( $post_id ) : wp_delete_post( $post_id, true  );
					// Handle errors
					if ( is_wp_error($response) ) {
						$this->logger->log($response->get_error_message(), "error", "database");
						$this->logger->message($response->get_error_message(), "error");
						return false;
					}
				}
			} else {
				$this->logger->log("Forum $section section is already empty.", "notice", "database");
				return true;
			}
		}
		// verify that section is empty
		if ( empty( $this->get_section_post_ids( $section )  ) ) {
			$message = "Forum $section section successfully deleted.";
			$this->logger->log($message, "notice", "database");
			$this->logger->message($message, "notice-success");
			return true;
		}
		else {
			$message = "Forum $section section deletion failed.";
			$this->logger->log($message, "error", "database");
			$this->logger->message($message, "error");
			return false;
		}
	}

	/** =============================================
	* Delete all Forum posts from the WP database
	*
	* @since    1.0.0
	* ============================================= */
	public function delete_all_sections() {
		foreach ($this->fields->get_sections() as $key => $section) {
			$this->delete_section( $section );
		}
	}

	/** =============================================
	* Get post IDs by import ID
	*
	* @since    1.0.0
	* ============================================= */
	public function get_post_data( $import_id, $post_type ) {
		// Query database for existing post by import ID
		$args = array(
			'post_type' => $post_type,
			'posts_per_page'   => -1,
			'orderby' => 'ID',
			'order' => 'ASC',
			'meta_query' => array(
				array(
					'key'     => 'import_id',
					'value'   => $import_id,
					'compare' => 'LIKE'
				),
			),
		);
		return get_posts($args);
	}

	/** =============================================
	* Query post by import_id
	*
	* @since    1.0.0
	* ============================================= */
	public function get_section_post_ids( $section ){

		// Set section parameters
		$events = ($section == 'events');
		$post_type = ($events) ? $this->events->post_type : $this->post_type;
		$taxonomy = ($events) ? $this->events->taxonomy : $this->taxonomy;
		$cat_ids = ($events) ? $this->events->get_categories() : $this->fields->get_categories( $section );

		// Query database for posts by section category
		return get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => -1,
				'orderby' => 'ID',
				'tax_query' => array(
					'relation' => 'OR',
					array(
						'taxonomy' => $taxonomy,
						'field' => 'term_id',
						'terms' => $cat_ids->en,
					),
					array(
						'taxonomy' => $taxonomy,
						'field' => 'term_id',
						'terms' => $cat_ids->fr,
					)
				)
			)
		);
	}


	/** =============================================
	* Validate retrieved post data
	*
	* @since    1.0.0
	* ============================================= */
	private function validate_ids( $wp_posts, $import_id ) {

		// initialize
		$n = count($wp_posts);
		$lang = $this->pll->lang_codes();
		$post_ids = new stdClass();

		// [Polylang] check that posts are registered translations
		if ($n == 2) {
			$id_1 = $wp_posts[0]->ID;
			$id_2 = $wp_posts[1]->ID;
			// Get language codes
			$lang_1 = $this->pll->get_post_language( $id_1 );
			$lang_2 = $this->pll->get_post_language( $id_2 );
			// Get translation post ID
			$trans_1_id = $this->pll->get_post( $id_1, $lang_2 );
			// Translation confirmed: assign IDs
			if ($id_2 == $trans_1_id) {
				$post_ids->en = ($lang_1 == $lang->en) ? $id_1 : $id_2;
				$post_ids->fr = ($lang_1 == $lang->fr) ? $id_1 : $id_2;
				return $post_ids;
			}
			else {
				$this->logger->log("Translation IDs $id_1:$id_2 do not match $id_1:$trans_1_id. Import ID-".$import_id, "error", "data");
				return;
			}
		}
		// Handle single translation
		elseif ($n == 1) {
			$id = $wp_posts[0]->ID;
			$this->logger->log("Post $id (import ID $import_id) missing translation.", "error", "events" );
			return;
		}
		// Handle duplicates
		elseif ($n > 2) {
			$this->logger->log("Import ID $import_id duplicated in database.", "error", "events" );
			return;
		}
		# Handle none found (e.g. post insertion)
		else {
			return;
		}
	}


	/** =============================================
	* Set expiration date for post
	* Dependency: Post Expirator plug-in
	*
	* @since    1.0.0
	* ============================================= */
	private function set_expiration( $post_id, $expiration_date ) {

		// Convert expiration date to timestamp
		$expiration_ts = strtotime($expiration_date);

		//create a date based on settings
		$expire_date = date( 'Y-m-d H:i:s', $expiration_ts);

		//convert to GMT (Post Expirator uses in this way)
		$ts = get_gmt_from_date($expire_date,'U');

		//create the options as you need (I don't know all options available), in my case works
		$opts = array(
			'id' => $post_id,
			'expireType' => 'delete'
		);

		// Update Post Expirator plug-in metadata for post
		if ( $ts > 0 && function_exists('_scheduleExpiratorEvent') ) {
			_scheduleExpiratorEvent($post_id, $ts, $opts);
			// NOTE: Applies following updates:
			// Update Post Meta
			// update_post_meta($id, '_expiration-date', $ts);
			// update_post_meta($id, '_expiration-date-options', $opts);
			// update_post_meta($id, '_expiration-date-status','saved');
			// Verfiy expiration update
			$set_expiration_date = get_post_meta( $post_id, '_expiration-date', true );

			// $set_opts = get_post_meta( $post_id, '_expiration-date-options' );
			// $set_status = get_post_meta( $post_id, '_expiration-date-status' );
			if ($set_expiration_date == $ts)
				return true;
		}
		// Expiration date failed
		$this->logger->log("Post ID $post_id expiration date set failed.", "warning", "events" );
		return false;
	}

} // End of WP_Forum_DB
