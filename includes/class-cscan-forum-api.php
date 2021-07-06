<?php

/**
 * The file that defines the core plugin class
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
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    WP_Forum_API
 * @subpackage WP_Forum_API/includes
 * @author     Your Name <email@example.com>
 */
class WP_Forum_API {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_API_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The settings for registering options for the API
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_API_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $settings;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'PLUGIN_NAME_VERSION' ) ) {
			$this->version = PLUGIN_NAME_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'cscan-forum-api';
		$this->settings = array();
		$this->load_settings();
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cscan-forum-api-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cscan-forum-api-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-cscan-forum-api-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-cscan-forum-api-public.php';
		$this->loader = new WP_Forum_API_Loader();

		/**
		 * The class responsible for handling Forum API communication and data encoding
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cscan-forum-data.php';

		/**
		 * The class responsible for handling interactions with WP database (READ/WRITES/DELETES/UPDATES)
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cscan-forum-db.php';

		/**
		 * The class responsible for handling interactions with ACF (READ/WRITES/DELETES/UPDATES)
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cscan-forum-acf.php';

		/**
		 * The class responsible for interactions with the Polylang plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cscan-forum-polylang.php';

		/**
		 * The class responsible for handling interactions with ACF (READ/WRITES/DELETES/UPDATES)
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cscan-forum-events.php';

		/**
		 * The class responsible for handling interactions with ACF Searches
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cscan-forum-search.php';

		/**
		 * The class responsible for handling update processing
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cscan-forum-update.php';

		/**
		 * The class responsible for handling notification logs
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-cscan-forum-logger.php';

	}

	/**
	 * Load fixed settings (JSON file)
	 *
	 * @since    1.0.0
	 */
	private function load_settings() {
		try {
			$settings = file_get_contents(plugin_dir_path(__DIR__) . 'admin/settings.json');
			$this->settings = json_decode($settings, true);

		} catch (Exception $e) {
			$this->loader->add_action( 'admin_notices', $plugin_admin, 'plugin_missing_settings_notice' );
		}
	}


	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the WP_Forum_API_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {
		$plugin_i18n = new WP_Forum_API_i18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		// Create error logger
		$logger = new WP_Forum_Logger();

		// Create Polylang API class instance
		$pll = new WP_Forum_Polylang( $logger );

		// Creat data loader/preprocessing
		$data = new WP_Forum_Data( $this->settings, $logger );
		$plugin_admin = new WP_Forum_API_Admin( $data, $pll, $this->settings, $logger );

		// Register init actions
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'setup_admin_pages');
		$this->loader->add_action( 'admin_bar_menu', $plugin_admin, 'admin_bar', 100 );
		$this->loader->add_action('init', $plugin_admin, 'init_categories');

		// Include expiration dates on posts
		// $this->loader->add_action('post_expirator_event','_scheduleExpiratorEvent', 1, 3);

		// Initialize singletons for Forum API classes
		$acf = new WP_Forum_ACF( $this->settings, $logger );
		$events = new WP_Forum_Events( $pll, $logger );
		$db = new WP_Forum_DB( $data, $acf, $events, $pll, $this->settings, $logger );
		$updater = new Forum_API_Update( $db, $data, $events, $logger );

		// Register active Forum API fields in ACF plugin
		// NOTE: Use 'acf/init' for ACF PRO
		$this->loader->add_action( 'acf/init', $acf, 'register_fields' );

		// Forum updates: background processing
		$this->loader->add_action('init', $updater, 'schedule', 99999);
		$this->loader->add_action('wp_loaded', $updater, 'process_handler', 1);

		// Initialization errors
		if ( ! function_exists( 'is_plugin_active' ) )
     require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

		// Check Polylang is installed and activated
		if ( ! is_plugin_active( 'polylang/polylang.php' ))
			$this->loader->add_action( 'admin_notices', $plugin_admin, 'polylang_plugin_notice' );

		// Check ACF plugin is installed and activated
		if( !function_exists( 'the_field' ) )
  		$this->loader->add_action( 'admin_notices', $plugin_admin, 'acf_plugin_notice' );

		// Check that The Events Calendar plugin is installed and activated
		if ( ! is_plugin_active('the-events-calendar/the-events-calendar.php') )
			$this->loader->add_action( 'admin_notices', $plugin_admin, 'events_calendar_plugin_notice' );

		// Check Post Expirator is installed and activated
		if ( ! is_plugin_active( 'post-expirator/post-expirator.php' ))
			$this->loader->add_action( 'admin_notices', $plugin_admin, 'post_expirator_plugin_notice' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new WP_Forum_API_Public($this->get_plugin_name(), $this->get_version());
		// Create error logger
		$logger = new WP_Forum_Logger();
		// Create Polylang API class instance
		$pll = new WP_Forum_Polylang( $logger );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// Set language locale (PHP setting)
		$this->loader->add_action( 'wp_head', $pll, 'set_locale' );

		// Plug-in initialization errors
		if ( ! function_exists( 'is_plugin_active' ) )
     require_once( ABSPATH . '/wp-admin/includes/plugin.php' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    WP_Forum_API_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
