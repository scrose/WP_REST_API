<?php

/**
 * The file that defines the Forum API Error Logger
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
 * The plugin Forum Data object class.
 *
 * This is used to define functionality for loading and handling Forum data.
 *
 * @since      1.0.0
 * @package    WP_Forum_API
 * @subpackage WP_Forum_API/includes
 */
class WP_Forum_Logger {

	/**
	 * Log directory and file for current date
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_Logger    $settings    Maintains and registers all hooks for the plugin.
	 */
	protected $log_dir;
	protected $log_file;

	/** =============================================
	 * Logger constructor
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function __construct() {

		// Initialize log directory
		$this->log_dir = get_template_directory() . '/forum_api_logs';

		// Create log directory in theme directory if none exists
		if ( ! is_dir( $this->log_dir ) ) {
			mkdir( $this->log_dir, 0755, true );
		}

		// Create log file for current date
		$current_date = date("Y-m-d", strtotime("now"));
		$this->log_file = $this->log_dir . '/forum_api_event_logs_' . $current_date .'.log';

		// Initialize file header
		if ( ! file_exists( $this->log_file ) ) {
			$fp = fopen($this->log_file, 'a'); //opens file in append mode
				fwrite($fp, PHP_EOL.'======================================'.PHP_EOL);
				fwrite($fp, 'Forum API Error Logs and Notifications'.PHP_EOL);
				fwrite($fp, 'DATE: '.$current_date.PHP_EOL);
				fwrite($fp, '======================================'.PHP_EOL);
			fclose($fp);
		}
	}


	/** =============================================
	 * write logs to file
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function log( $log, $level, $module ) {
		$timestamp = date("H:i:s");
		$fp = fopen($this->log_file, 'a'); //opens file in append mode
			fwrite($fp, $timestamp.'  ');
			fwrite($fp, strtoupper($level).'  (');
			fwrite($fp, $module.')  ');
			fwrite($fp, $log.PHP_EOL);
		fclose($fp);
	}


	/** =============================================
	 * write logs to file
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function print_logs() {
		echo nl2br ( file_get_contents ( $this->log_file ) );
	}

	/** =============================================
	 * Display admin notices
	 *
	 * @since    1.0.0
	 * ============================================= */

	public function message( $log, $level ) {
		add_action( 'admin_notices', function() use ( $log, $level ) { $this->db_admin_notice( $log, $level ); }, 10, 2 );
		return False;
	}
	// Associated action function
	public function db_admin_notice( $log, $level ) {
		?>
		<div class="<?php echo $level; ?> notice">
				<p><?php echo $log; ?></p>
		</div>
		<?php
	}

}
