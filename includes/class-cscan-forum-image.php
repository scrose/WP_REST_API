<?php

/**
 * Processor to handle image files on the Forum
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       http://forum.cscan-infocan.ca
 * @since      1.0.0
 *
 * @package    WP_Forum_API
 * @subpackage WP_Forum_API/includes
 */

 // Required WP File class for URL downloads
 require_once(ABSPATH . "wp-admin" . '/includes/file.php');

/**
 * The plugin Forum Data object class.
 *
 * This is used to define functionality for loading and handling Forum data.
 *
 * @since      1.0.0
 * @package    WP_Forum_API
 * @subpackage WP_Forum_API/includes
 */
class WP_Forum_Image {

	/**
	 * Forum API image request URL
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_Image    $url    Forum image URL
	 */
	protected $url;


	/**
	 * Forum API image request URL
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_Image    $hashkey    Forum image URL
	 */
	protected $hashkey;

	/**
	 * Forum API image file extensions (from MIME types)
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_Image    $extensions    Supported file extensions.
	 */
	protected $extensions;


	/**
	 * Forum API event logger
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_Image    $logger    Tracks errors and notifications
	 */
	protected $logger;

	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 */
	public function __construct( $logger ) {
		$this->id = 'img_processor';
		$this->url = NULL;
		$this->hashkey = NULL;
		$this->logger = $logger;
		// Initialize WP MIME types
		$this->extensions = array(
			'image/jpeg' 	=> 'jpg',
			'image/jpeg;base64' 	=> 'jpeg',
			'image/gif' 	=> 'gif',
			'image/gif;base64' 	=> 'gif',
			'image/png' 	=> 'png',
			'image/png;base64' 	=> 'png',
			//'application/octet-stream' => 'data'
		);
	}


 /** =============================================
	* Import cached versions of images to WP Media Library
	*
	* @since    1.0.0
	* ============================================= */
	public function import_image ( $url, $hashkey, $page_id = NULL ) {

		$this->hashkey = $hashkey;
		$this->url = $url;

		// Get attachment ID from filename
		$attachment_id = $this->get_attachment_id_by_filename( $this->hashkey );
		// Return attachment ID if exists
		if ( $attachment_id ) return $attachment_id;

		// Download image file if an WP attachment does not exist (returns file data or NULL)
		$results = $this->download_image();

		// Check that file data is not empty (indicates file is invalid)
		if ( ! $results ) {
			$this->logger->log( "Requested image \n $this->url is empty or invalid. ", "error", $this->id );
			return false;
		}

		$filename  = $results['file']; // Full path to the file
		$local_url = $results['url'];  // URL to the file in the uploads dir
		$type      = $results['type']; // MIME type of the file

		// Check whether filename exists in the WP uploads directory
		// $uploads_dir = wp_upload_dir();
		// $uploads_list = list_files( $uploads_dir['path'], 100 );
		// foreach ($uploads_list as $key => $filename) {
		// 	if ( strpos($filename, $this->hashkey) ) {
		// 		echo "<!-- Forum API Warning: Image file $this->hashkey exists. New attachment created. --> \n";
		// 		return $this->attach_image( $filename, $page_id );
		// 	}
		// }

		// Attach file to attachment and return ID value
		return $this->attach_image( $filename, $page_id );

	}


	 /** =============================================
	 * Download image to WP uploads directory
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function download_image() {

		// Download file to temp dir (timeout = 10 seconds)
		$temp_file = download_url($this->url, 120);

		if ( !is_wp_error( $temp_file ) ) {

			// Get MIME type and filename
			$mime_type = mime_content_type( $temp_file );
			$extension = $this->extensions[$mime_type];
			$filename = basename( $this->hashkey ) . '.' . $extension;

	    // Array based on $_FILE as seen in PHP file uploads
	    $file = array(
	        'name'     => $filename, // ex: wp-image.png
	        'type'     => $mime_type,
	        'tmp_name' => $temp_file,
	        'error'    => 0,
	        'size'     => filesize($temp_file),
	    );

	    $overrides = array(
	        // Tells WordPress to not look for the POST form
	        // fields that would normally be present as
	        // we downloaded the file from a remote server, so there
	        // will be no form fields
	        // Default is true
	        'test_form' => false,

	        // Setting this to false lets WordPress allow empty files, not recommended
	        // Default is true
	        'test_size' => true,
	    );

	    // Move the temporary file into the uploads directory
	    $results = wp_handle_sideload( $file, $overrides );
			// Handle upload errors
	    if ( !empty( $results['error'] ) ) {
					$error = $results['error'];
					$this->logger->log( "Attachment $attachment_id for ".basename($filename)." to WP page $page_id failed. ".$error, "error", $this->id );
					return;
	    } else {
					return $results;
	    }
		}
	}


	 /** =============================================
	 * Attach uploaded image to WP attachment post
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function attach_image ( $filename, $page_id ) {
		// Get parent page to attach image
		$wp_filetype = wp_check_filetype( $filename, null );
		// Create attachment instance
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_parent' => $page_id,
			'post_title' => $this->hashkey,
			'post_status' => 'inherit'
		);
		// Insert attachment in WP Posts
		$attachment_id = wp_insert_attachment( $attachment, $filename, $page_id );

		// Proceed to include attachment metadata upon success
		if ( ! is_wp_error( $attachment_id ) ) {
			require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
			// Generate and include image attachment metadata
			$attachment_data = wp_generate_attachment_metadata( $attachment_id, $filename );
			wp_update_attachment_metadata( $attachment_id,  $attachment_data );
			// log update
			$this->logger->log( basename($filename) . " attached to WP page $page_id at $attachment_id.", "notice", $this->id );
			// Return attachment ID
			return $attachment_id;
		}
		// Image attachment failed to be created
		$error = $attachment_id->get_error_message();
		$this->logger->log( "Attachment $attachment_id for $filename to WP page $page_id failed. ".$error, "error", $this->id );
		return false;
	}


	 /** =============================================
	 * Get WP attachment ID by querying filename
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function get_attachment_id_by_filename ( $filename ) {
		if ( ! empty( $filename ) ) {
			$img_query_args = array(
				'post_type'   => 'attachment',
				'post_status' => 'inherit',
				'fields'      => 'ids',
				'name'				=> $filename
			);
			$img_query = new WP_Query( $img_query_args );
			return ( $img_query->have_posts() ) ? $img_query->posts[0] : NULL;
		}
		return NULL;
  }


	 /** =============================================
	 * Get file MIME type from bit stream (Currently not used)
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function get_file_mimetype ( $fp ) {
    if ($fp) {
        $bytes6=fread( $fp,6 );
        if ($bytes6===false) return false;
        if (substr($bytes6,0,3)=="\xff\xd8\xff") return 'image/jpeg';
        if ($bytes6=="\x89PNG\x0d\x0a") return 'image/png';
        if ($bytes6=="GIF87a" || $bytes6=="GIF89a") return 'image/gif';
        return 'application/octet-stream';
    }
  }

} // END of WP_Forum_Data
