<?php
/**
 * Forum_API_Update: The file that defines the Forum API Update class
 *
 * @package WP_Forum_API
 */

class Forum_API_Update {

	/**
	 * Database API
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Forum_API_Update    $db    Handles WP database queries
	 */
	protected $db;
	/**
	 * Data API
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Forum_API_Update    $loader    Handles Forum field data interactions
	 */
	protected $loader;
	/**
	 * The Event Calendar (Events) API
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Forum_API_Update    $events    Handles Forum events
	 */
	protected $events;
	/**
	 * Cutoff modified date for updates
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Forum_API_Update    $cutoff_date    Date to define recent Forum updates
	 */
	protected $cutoff_date;
	/**
	 * WP hook identifier for cron jobs
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Forum_API_Update    $cron_hook_id    WP hook identifier for cron jobs
	 */
	protected $cron_hook_id;
	/**
	 * WP interval identifier for cron jobs
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Forum_API_Update    $cron_interval_id    WP interval identifier for cron jobs
	 */
	protected $cron_interval_id;

	/**
	 * Forum_API_Cron object constructor.
	 */
	public function __construct( $db, $loader, $events, $logger ) {
		$this->id = 'updater';
		$this->sections = array('news', 'events', 'opportunities', 'departments');
		$this->db = $db;
		$this->loader = $loader;
		$this->events = $events;
		$this->logger = $logger;
		$this->cutoff_date = date('Y-m-d', time() - 60 * 60 * 24);

		$this->cron_hook_id     = 'forum_api_cron';
		$this->cron_interval_id = 'forum_api_cron_interval';
		// Initialize cron interval (every five minutes)
		add_action( $this->cron_hook_id, array( $this, 'forum_api_cron_update' ) );
		add_filter( 'cron_schedules', array( $this, 'forum_api_add_cron_interval' ) );
		register_deactivation_hook( __FILE__, array($this, 'forum_api_cron_deactivate') );
	}


	/** =============================================
	 * Schedule Forum Update cron job: WP Registration
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function schedule() {

		if ( ! wp_next_scheduled( $this->cron_hook_id ) ) {
			wp_schedule_event( time(), $this->cron_interval_id, $this->cron_hook_id );
		}
	}

	/** =============================================
	 * Initialize Forum Plugin deactivation method
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function forum_api_cron_deactivate() {
		wp_clear_scheduled_hook( $this->cron_hook_id );
	}

	/** =============================================
	 * Initialize wp-cron interval
	 * WP-Cron uses intervals to simulate a system cron.
	 * WP-Cron is given two arguments: the time for the first task,
	 * and an interval (in seconds) after which the task should
	 * be repeated.
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function forum_api_add_cron_interval( $schedules ) {
		$schedules[$this->cron_interval_id] = array(
				'interval' => 600,
				'display'  => esc_html__( 'Every 10 Minutes' ), );
		return $schedules;
	}


	/** =============================================
	 * Cron executable for updating WP database
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function forum_api_cron_update() {
		$this->logger->log( " === Automatic Forum update started === ", "notice", 'cron' );
		// Update Forum sections
		foreach ( $this->sections as $key => $section ) {
			$this->handle_update( $section, $this->cutoff_date );
		}
	}


	/** =============================================
	 * Handle manually-triggered processes
	 *
	 * @since    1.0.0
	 * ============================================= */
	public function process_handler() {
		// Verify HTTP parameters are set
		if ( ! isset( $_GET['process'] ) || ! isset( $_GET['section'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}
		// Verify WP process token
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'process') ) {
			return;
		}
		// Log manual event
		$section = $_GET['section'];
		$action = $_GET['process'];
		$this->logger->log( " === Manual Forum $action for $section started === ", "notice", 'update' );

		// Update Section content from Forum -> WP Database
		if ( 'update' === $action ) {
			$cutoff_date = isset($_GET['date']) ? $_GET['date'] : false;
			$this->handle_update( $section, $cutoff_date );
		}
		// Delete Section content from WP Database
		if ( 'delete' === $action ) {
			if ( 'all' === $section )
				$this->handle_delete_all();
			else
				$this->handle_delete( $section );
		}
		return;
	}


	/** =============================================
	 * Handles process requests to plugin modules
	 *
	 * @since    1.0.0
	 * ============================================= */
	protected function handle_update( $section, $cutoff_date ) {
		$this->db->update_section( $section, $cutoff_date );
	}
	protected function handle_delete( $section ) {
		$this->db->delete_section( $section );
	}
	protected function handle_delete_all() {
		$this->db->delete_all_sections();
	}

} // End of Forum_API_Update
