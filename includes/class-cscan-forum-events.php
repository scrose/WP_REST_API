<?php

/**
* WP_Forum_Events - The file that defines the Forum API Events class
*
* @since      1.0.0
*
* @package    WP_Forum_API
* @subpackage WP_Forum_API/includes
*/

/**
* Serves as API to The Events Calendar API
*
* @package    WP_Forum_API
* @subpackage WP_Forum_API/includes
* @author     Spencer Rose <support@runtime.ca>
*/
class WP_Forum_Events {

	/**
	* ICS/Forum datetime format type
	*
	* @since    1.0.0
	* @access   protected
	* @var      array    $actions    The actions registered with WordPress to fire when the plugin loads.
	*/
	const FORUM_API_TAX = 'post_category';
	const TRIBE_EVENT_TYPE = 'tribe_events';
	const TRIBE_VENUE_TYPE = 'tribe_venue';
	const TRIBE_ORGANIZER_TYPE = 'tribe_organizer';
	const TRIBE_EVENT_TAX = 'tribe_events_cat';

	/**
	 * Polylang plug-in API class object
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_DB    $pll   API with Polylang WP plug-in
	 */
	protected $pll;

	/**
	* Log errors and notifications
	*
	* @since    1.0.0
	* @access   protected
	* @var      WP_Forum_Events    $logger    Date to define recent Forum updates
	*/
	protected $logger;

	/**
	* Initialize the collections used to maintain the actions and filters.
	*
	* @since    1.0.0
	*/
	public function __construct( $pll, $logger ) {
		$this->id = 'events';
		$this->taxonomy = WP_Forum_Events::TRIBE_EVENT_TAX;
		$this->post_type = WP_Forum_Events::TRIBE_EVENT_TYPE;
		$this->pll = $pll;
		$this->logger = $logger;
	}

	/** =============================================
	* Iniialize content categories (EN/FR)
	*
	* @since    1.0.0
	* ============================================= */
	public function init_categories() {

		// Get language codes
		$lang = $this->pll->lang_codes();
		if ( ! $lang ) return false;

		// Create term slugs
		$cat_slug_en = 'forum-' . $this->id . '-'. $lang->en;
		$cat_slug_fr = 'forum-' . $this->id . '-'. $lang->fr;

		// English forum event category
		$cats_en = ( ! term_exists( $cat_slug_en, $this->taxonomy ) ) ?
		wp_insert_term( 'Forum Events ('.$lang->en.')', $this->taxonomy, array('slug' => $cat_slug_en) ) : NULL;
		$cat_en_id = ( $cats_en ) ? $cats_en['term_id'] : term_exists( $cat_slug_en, $this->taxonomy );

		// French forum event category
		$cats_fr = ( ! term_exists( $cat_slug_fr, $this->taxonomy ) ) ?
		wp_insert_term( 'Forum Events ('.$lang->fr.')', $this->taxonomy, array('slug' => $cat_slug_fr) ) : NULL;
		$cat_fr_id = ( $cats_fr ) ? $cats_fr['term_id'] : term_exists( $cat_slug_fr, $this->taxonomy );

		// Error check
		if ( is_wp_error($cats_en) ) {
			$error = $cat_ids->en->get_error_message();
			$this->logger->log("Tribe event category for ".$this->taxonomy." initialization failed. " . $error, "error", "events");
			return;
		}
		if ( is_wp_error($cats_fr) ) {
			$error = $cat_ids->fr->get_error_message();
			$this->logger->log("Tribe event category for ".$this->taxonomy." initialization failed. " . $error, "error", "events");
			return;
		}
		$this->logger->log( "Forum category terms added to Tribe.", "notice", $this->id );

		// [Polylang] Set default language for category
		$this->pll->set_term_language($cat_en_id, $lang->en);
		$this->pll->term_language($cat_fr_id, $lang->fr);

		// initialize categories
		$cat_settings = array($lang->en => $cat_en_id, $lang->fr => $cat_fr_id );

		// [Polylang] Define translated posts
		$this->pll->save_term_translations($cat_settings);

		return true;
	}


	/** =============================================
	* Initialize Event Calendar content terms (EN/FR)
	*
	* @since    1.0.0
	* ============================================= */
	public function get_categories() {

		// Get language codes
		$lang = $this->pll->lang_codes();
		if ( ! $lang ) return;

		// Create term slugs
		$cat_slug_en = 'forum-' . $this->id . '-'. $lang->en;
		$cat_slug_fr = 'forum-' . $this->id . '-'. $lang->fr;

		// Create categories
		$cat_ids = new stdClass();
		$cat_ids->en = term_exists( $cat_slug_en, $this->taxonomy );
		$cat_ids->fr = term_exists( $cat_slug_fr, $this->taxonomy );

		// Confirm category terms exist
		if ( !$cat_ids->en || !$cat_ids->fr ) {
			// Initialize Tribe Category terms
			$this->init_categories();
		} else {
			// Load term IDs to object
			$cat_ids->en = $cat_ids->en['term_id'];
			$cat_ids->fr = $cat_ids->fr['term_id'];
		}
		// Return categories
		return $cat_ids;
	}



	/** =============================================
	* Create new Tribe Venue
	*
	* @since    1.0.0
	* ============================================= */
	private function build_venue( $import_data ) {
		// Venue details
		return array(
			'Venue' => $import_data['fields']['address'],
			'Country' => $import_data['fields']['country'],
			'Address' => $import_data['fields']['address'],
			'City' => $import_data['fields']['city'],
			'Province' => $import_data['fields']['province'],
			'meta_input' => array(
				'import_id' => $import_data['id'],
			)
		);
	}


	/** =============================================
	* Create new Tribe Organizer
	*
	* @since    1.0.0
	* ============================================= */
	private function build_organizer( $import_data ) {
		// Organizer details
		return array(
			'Organizer' => $import_data['post_title'],
			'meta_input' => array(
				'import_id' => $import_data['id'],
			)
		);
	}


	/** =============================================
	* Create new Tribe Event Data
	*
	* @since    1.0.0
	* ============================================= */
	private function build_event( $title, $content, $fields, $import_id, $venue_id, $organizer_id ) {
		// Format event dates
		$start_date = date('Y-m-d', strtotime($fields['startDate']));
		// Set end date to be at least 24hrs after start date
		$end_date = ($fields['endDate'] == '0000-00-00') ? date('Y-m-d', strtotime($fields['startDate']) + 3600*24) : date('Y-m-d', strtotime($fields['endDate']));
		// Return event post data
		return array(
			'post_title' => $title,
			'post_content' => $content,
			'post_status'   => 'publish',
			'post_author' => 1,
			'meta_input' => array(
				'import_id' => $import_id,
				'venue_id' => $venue_id,
				'organizer_id' => $organizer_id
			),
			'EventStartDate' => $start_date,
			'EventEndDate' => $end_date,
			'EventAllDay' => true,
			// 'EventStartHour' => '12',
			// 'EventStartMinute' => '00',
			// 'EventStartMeridian' => 'am',
			// 'EventEndHour' => '11',
			// 'EventEndMinute' => '59',
			// 'EventEndMeridian' => 'pm',
			'EventURL' => $fields['articleLink'],
			'EventVenueID' => $venue_id,
			'EventOrganizerID' => $organizer_id
		);
	}


	/** =============================================
	* Create new Event from Forum import data
	*
	* @since    1.0.0
	* ============================================= */
	public function insert( $section, $import_data ) {

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

		// Initialize Post IDs
		$post_ids = new stdClass();

		// Retrieve current language codes (en/fr)
		$lang = $this->pll->lang_codes();

		// Retrieve Event Tribe categories
		$cat_ids = $this->get_categories();

		// Create new Tribe Venue & Organizer
		$venue_data = $this->build_venue( $import_data );
		$organizer_data = $this->build_organizer( $import_data );
		$venue_id = tribe_create_venue( $venue_data, $import_id, $cat_ids->en );
		$organizer_id = tribe_create_organizer( $organizer_data, $import_id, $cat_ids->en );

		// Create new Tribe Event
		$event_data_en = $this->build_event( $ttl_en, $cnt_en, $fields, $import_id, $venue_id, $organizer_id );
		$event_data_fr = $this->build_event( $ttl_fr, $cnt_fr, $fields, $import_id, $venue_id, $organizer_id );
		$post_ids->en = tribe_create_event( $event_data_en );
		$post_ids->fr = tribe_create_event( $event_data_fr );

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
	* Update Event using Forum import data
	*
	* @since    1.0.0
	* ============================================= */
	public function update( $section, $import_data, $post_ids ) {

		// Import data is required to proceed
		if ( !$import_data ) {
			$this->logger->log("Imported Forum post data is empty.", "error", "events");
			return;
		}

		$import_id = $import_data['id'];
		$organizer_id = NULL;
		$venue_id = NULL;

		$ttl_en = $import_data['post_title'];
		$ttl_fr = $import_data['post_title_fr'];
		$cnt_en = $import_data['post_content'];
		$cnt_fr = $import_data['post_content_fr'];
		$fields = $import_data['fields'];
		$post_image = $import_data['post_image'];

		// Retrieve current language codes (en/fr)
		$lang = $this->pll->lang_codes();
		// Get Event (Tribe) categories
		$cat_ids = $this->get_categories();

		// Build Tribe Event post data arrays (en/fr)
		$venue_data = $this->build_venue( $import_data );
		$organizer_data = $this->build_organizer( $import_data );

		// Update existing Tribe records (Venue/Organizer/Event)

		// Venues
		$venue_id = tribe_get_venue_id( $post_ids->en );
		if (!$venue_id)
			$venue_id = tribe_create_venue( $venue_data, $import_id, $cat_ids->en );
		$venue_id = tribe_update_venue( $venue_id, $venue_data );

		// Organizer
		$organizer_id = tribe_get_organizer_id( $post_ids->en );
		if (!$organizer_id)
			$organizer_id = tribe_create_organizer( $organizer_data, $import_id, $cat_ids->en );
		$organizer_id = tribe_update_organizer( $organizer_id, $venue_data );

		// Events
		$event_data_en = $this->build_event( $ttl_en, $cnt_en, $fields, $import_id, $venue_id, $organizer_id );
		$event_data_fr = $this->build_event( $ttl_fr, $cnt_fr, $fields, $import_id, $venue_id, $organizer_id );
		$post_ids->en = tribe_update_event( $post_ids->en, $event_data_en );
		$post_ids->fr = tribe_update_event( $post_ids->fr, $event_data_fr );

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
* [Events Calendar Wrapper] Delete posts by Event ID
*
* @since    1.0.0
* ============================================= */
public function delete( $post_id ) {
	// // Delete Venue
	// $venue_id = tribe_get_venue_id( $post_ids->en );
	// tribe_delete_venue();
	// // Delete Organizer
	// $organizer_id = tribe_get_organizer_id( $post_ids->en );
	// tribe_delete_organizer();
	return tribe_delete_event( $post_id, true );
}


} // End of WP_Forum_Events
