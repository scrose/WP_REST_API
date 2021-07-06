<?php

/**
 * The file that defines the Forum API WP Polylang API class
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
 * The plugin Forum WP Database object class.
 *
 * This is used to define functionality for loading and handling Forum data.
 *
 * @since      1.0.0
 * @package    WP_Forum_API
 * @subpackage WP_Forum_API/includes
 */
class WP_Forum_Polylang {

	/**
	 * Logger class object
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_DB    $logger    Logs errors and notifications.
	 */
	protected $logger;

	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
	}

	 /** =============================================
	 * Get Polylang language codes (en/fr)
	 *
	 * @since    1.0.0
	 * ============================================= */
	 public function lang_codes() {

		// Define language codes
		$lang = new stdClass();
		$lang->en = 'en';
		$lang->fr = 'fr';

		// [Polylang] Get default language code
		$lang_default = ( function_exists('pll_default_language') ) ? pll_default_language() : NULL;
		// Ensure default language is English
		if ( ! $lang_default || $lang_default != $lang->en ) {
			$message = "English is not the default language. Please update Polylang settings.";
			$this->logger->log( $message, "error", "Polylang" );
			$this->logger->message($message, "error");
		}
		// Also check that French is activated
		if ( ! in_array( $lang->fr, pll_languages_list() ) ) {
			$message = "French language has not been activated. Please update Polylang settings.";
			$this->logger->log( $message, "error", "Polylang" );
			$this->logger->message($message, "error");
		}
		return $lang;
	 }

		/** =============================================
		 * Set locale based on current post language
		 *
		 * @since    1.0.0
			 * ============================================= */
		 public function set_locale() {
			 if ( function_exists( 'pll_current_language' ) ) {
				 switch ( pll_current_language() ) {
					 case 'fr':
						 setlocale(LC_TIME, 'fr_CA.UTF-8');
						 break;

					 case 'en':
						 setlocale(LC_TIME, 'en_CA.UTF-8');
						 break;
				 }
			 }
		 }

		/** =============================================
		 * [Polylang Wrapper] Set category term language
		 *
		 * @since    1.0.0
			 * ============================================= */
		public function set_term_language( $post_id, $lang ) {
			if ( function_exists('pll_set_term_language') ) {
				return pll_set_term_language($post_id, $lang);
			}
		}

		/** =============================================
		 * [Polylang Wrapper] Set category term translations
		 * input associative array of translations with
		 * language code as key and post id as value
		 *
		 * @since    1.0.0
			 * ============================================= */
		public function save_term_translations( $arr ) {
			if ( function_exists('pll_save_term_translations') ) {
				return pll_save_term_translations( $arr );
			}
		}

		/** =============================================
		 * [Polylang Wrapper] Get translation post ID for language
		 *
		 * @since    1.0.0
			 * ============================================= */
		public function get_post( $post_id, $lang ) {
			if ( function_exists('pll_get_post') ) {
				return pll_get_post( $post_id, $lang );
			}
		}

		/** =============================================
		 * [Polylang Wrapper] Set language of post
		 *
		 * @since    1.0.0
			 * ============================================= */
		public function set_post_language( $post_id, $lang ) {
			if ( function_exists('pll_set_post_language') ) {
				return pll_set_post_language( $post_id, $lang );
			}
		}

		/** =============================================
		 * [Polylang Wrapper] Get language of post
		 *
		 * @since    1.0.0
			 * ============================================= */
		public function get_post_language( $post_id, $field='slug' ) {
			if ( function_exists('pll_get_post_language') ) {
				return pll_get_post_language( $post_id, $field );
			}
		}

		/** =============================================
		 * [Polylang Wrapper] Defines the translated post
		 *
		 * @since    1.0.0
			 * ============================================= */
		public function save_post_translations( $arr ) {
			if ( function_exists('pll_save_post_translations') ) {
				return pll_save_post_translations( $arr );
			}
		}



} // end Forum Polylang API
