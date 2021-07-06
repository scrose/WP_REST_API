<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://forum.cscan-infocan.ca
 * @since      1.0.0
 * @package    WP_Forum_API
 * @subpackage WP_Forum_API/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WP_Forum_API
 * @subpackage WP_Forum_API/admin
 * @author     Spencer Rose
 */
class WP_Forum_API_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name = 'wp_cscan_forum_api';

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version = '1.0';


	/**
	 * Data API
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $data = NULL;

	/**
	 * Forum API general settings
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      array    $settings    The shortcodes registered with WordPress.
	 */
	protected $settings;
	/**
	 * Polylang API class object
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_Polylang    $pll   API with Polylang WP plug-in
	 */
	protected $pll;
	/**
	* Handles data loading/preprocessing
	*
	* @since    1.0.0
	* @access   protected
	* @var      WP_Forum_Data    $loader    Handles data loading/preprocessing
	*/
	protected $loader;
	/**
	 * Logger class object
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_Logger    $logger    Logs errors and notifications.
	 */
	protected $logger;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $data, $pll, $settings, $logger ) {
		$this->settings = $settings;
		$this->data = $data;
		$this->pll = $pll;
		$this->logger = $logger;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in WP_Forum_API_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The WP_Forum_API_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/cscan-forum-api-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in WP_Forum_API_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The WP_Forum_API_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/cscan-forum-api-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Display Forum API settings form to page
	 *
	 * @since    1.0.0
	 */
	public function plugin_main_page_content() {
		include(plugin_dir_path(__FILE__) . 'partials/cscan-forum-api-admin-display-main.php');
	}
	public function plugin_section_page_content() {
		include(plugin_dir_path(__FILE__) . 'partials/cscan-forum-api-admin-display-section.php');
	}


	/**
	 * A utility function that is used to register the admin settings into a single
	 * collection.
	 *
	 * @since    1.0.0
	 * @access   public
	 */
	public function setup_admin_pages () {
		$menu_page = $this->settings['menu_page'];
		// Register page menu
		add_menu_page(
			$menu_page['page_title'],
			$menu_page['menu_title'],
			$menu_page['capability'],
			$menu_page['menu_slug'],
			array( $this, $menu_page['callback'] ),
			$menu_page['icon'],
			$menu_page['position'] );

		// Register page submenus
		foreach ( $this->settings['submenu_pages'] as $submenu_page ) {
			add_submenu_page(
				$menu_page['menu_slug'],
				$submenu_page['page_title'],
				$submenu_page['menu_title'],
				$submenu_page['capability'],
				$submenu_page['menu_slug'],
				array( $this, $submenu_page['callback']) );

		// Register fieldset sections in WP
		foreach ( $submenu_page['sections'] as $section ) {
			add_settings_section(
				$section['id'],
				$section['title'],
				array( $this, $section['callback']),
				$submenu_page['menu_slug'] );

				// Register option fields in WP
				foreach ( $section['settings'] as $settings ) {
					add_settings_field(
						$settings['id'],
						$settings['title'],
						array( $this, $settings['callback']),
						$submenu_page['menu_slug'],
						$section['id'],
						$settings['args']);
					register_setting( $submenu_page['menu_slug'], $settings['id'] );
				}
			}
		}
	}

	/**
	* Page sections callback
	**/
	public function section_callback( $args ) {
		submit_button();
		foreach ($this->settings['submenu_pages'] as $submenu_page) {
			foreach ($submenu_page['sections'] as $section) {
				if ($section['id'] == $args['id']) {
					echo '<hr />' . $section['description'];
				}
			}
		}
	}

	/*
	* * * * * * * * * * * * * * * * * * * * * * * * * * * *
	Data Fields Manager
	* * * * * * * * * * * * * * * * * * * * * * * * * * * *
	*/

	/**
	* Show Forum API fields: Render Fields for Admin menu
	**/
	public function wp_forum_api_fields_render( $args ) {
		// Load ACF current section fieldset
		$section = $args['section'];
		$fields = $this->data->load_fields( $section );
		$options = get_option( $args['id'] );

		// Display error if fields could not be loaded
		$error_message = ( ! $fields ) ? "Fields for $section section not loaded." : NULL;

		// Display notice if fields not set
		// Check whether required WP fields have been activated
		$notice_message = ( !get_option( $args['post_title'] ) ) ? "Fields required: Post Title (EN). " : NULL;
		$notice_message .= ( !get_option( $args['post_content'] ) ) ? "Fields required: Post Content (EN). " : NULL;
		$notice_message .= ( !get_option( $args['post_title_fr'] ) ) ? "Fields required: Post Title (FR). " : NULL;
		$notice_message .= ( !get_option( $args['post_content_fr'] ) ) ? "Fields required: Post Content (FR). " : NULL;
		$notice_message .= ( !get_option( $args['post_image'] ) ) ? "Fields required: Post Image. " : NULL;
		// Optional fields
		if ( isset($args['expiration_date']) ) {
			$notice_message .= ( !get_option( $args['expiration_date'] ) ) ? "Optional field not set: Expiration Date. " : NULL;
		}
		?>
	<?php if ( $error_message ): ?>
		<div class="error notice">
				<p><?php echo $error_message; ?></p>
		</div>
	<?php else: ?>
		<?php if ( $notice_message ): ?>
			<div class="notice-warning notice">
					<p><?php echo $notice_message; ?></p>
			</div>
			<?php endif; ?>
		<ul class="accordion">
			<strong>Activated Fields</strong> <button class="toggle"></button>
			<ul class="inner">
				<?php $this->render_acf_options( $fields, $args['id'], $options, $args ); ?>
			</ul>
		</ul>
		<?php endif; ?>
		<?php
	}

	/**
	 * Extract nested keys from array
	 *
	 * @since    1.0.0
	 */
	public function render_acf_options( $fields, $option_id, $options, $args ){

		// generate checkboxes to activate/deactivate each field in ACF
		foreach ($fields as $key => $field) {
			if ( is_array( $field ) ) {
				$option_id_update = ( !is_numeric($key) ) ? $option_id . '[' . $key . ']' : $option_id;
				$options_down = ( !is_numeric($key) && isset($options[$key]) ) ? $options[$key] : array();
				?>
				<li class="panel">
				<?php if ( !is_numeric($key) && array() !== $field ): ?>
					<strong><?php echo $key; ?></strong> <button class="toggle"></button>
				<?php endif; ?>
					<ul class="inner" style="<?php if ( !is_numeric($key) ): echo "padding-left:30px"; endif; ?>">
						<?php $this->render_acf_options( $field, $option_id_update, $options_down, $args ); ?>
					</ul>
				</li>
				<?php
				// ignore integer key values (i.e. repeated nodes)
				if ( is_numeric($key) ) break;
			}
			// Render ACF field activation selections / WP Post title/content selections
			else {
				$id = $option_id . '[' . $field . ']';
				// Set required fields
				// Required fields: ID, deleted, visibility, created date, modified date
				if ( $option_id == $args['id'] ) {
					if ( $field == 'id' || $field == 'ID' || $field == 'deleted' || $field == 'created' || $field == 'visibility' || $field == 'language') {
						?><li><?php $this->wp_forum_api_checkbox_render( $field, $id, True, True ); ?></li><?php
						continue;
					}
				}
				$active = isset( $options[$field] );
				$post_field = str_replace( ']', "", str_replace( '[', "/", $option_id ) ) . '/' . $field;
				$wp_post_title_selected = get_option( $args['post_title'] ) == $post_field;
				$wp_post_content_selected = get_option( $args['post_content'] ) == $post_field;
				$wp_post_title_fr_selected = get_option( $args['post_title_fr'] ) == $post_field;
				$wp_post_content_fr_selected = get_option( $args['post_content_fr'] ) == $post_field;
				$wp_post_image_selected = get_option( $args['post_image'] ) == $post_field;
				// Optional expiration date
				if ( isset($args['expiration_date']) ) {
					$wp_post_expiration_date_selected = get_option( $args['expiration_date'] ) == $post_field;
				}
				?>
				<li class="forum_api_field_options <?php if ($active): echo "forum_api_field_options_active"; endif; ?>">
					<?php $this->wp_forum_api_checkbox_render( $field, $id, $active ); ?>
				<?php if ($active): ?>
					<?php $this->wp_forum_api_radio_render($post_field, "Title (EN)", $args['post_title'], $wp_post_title_selected ); ?>
					<?php $this->wp_forum_api_radio_render($post_field, "Content (EN)", $args['post_content'], $wp_post_content_selected ); ?>
					<?php $this->wp_forum_api_radio_render($post_field, "Title (FR)", $args['post_title_fr'], $wp_post_title_fr_selected ); ?>
					<?php $this->wp_forum_api_radio_render($post_field, "Content (FR)", $args['post_content_fr'], $wp_post_content_fr_selected ); ?>
					<?php $this->wp_forum_api_radio_render($post_field, "Image", $args['post_image'], $wp_post_image_selected ); ?>
					<?php if ( isset($args['expiration_date']) ): ?>
						<?php $this->wp_forum_api_radio_render($post_field, "Expiration Date", $args['expiration_date'], $wp_post_expiration_date_selected ); ?>
					<?php endif;?>
				<?php endif; ?>
				</li>
				<?php
			}
		}
	}

	/**
	* Set up settings field callback: Render Checkbox Field
	**/
	public function wp_forum_api_checkbox_render( $field, $option_id, $selected, $disabled=False ) {
		?>
		<label for="<?php echo $option_id; ?>">
			<input  type="checkbox" name="<?php echo $option_id; ?>" id="<?php echo $option_id; ?>"
			value="1" <?php checked( $selected ); ?> <?php if( $disabled ) echo 'disabled'; ?> />
				<?php echo $field; ?>
		</label>
		<?php
		// Make checkbox readonly, but submit the value as selected
		if ( $selected && $disabled ) {
			?>
				<input type="hidden" name="<?php echo $option_id; ?>" id="<?php echo $option_id; ?>" value="1" />
			<?php
		}
	}

	/**
	* Set up settings field callback: Render Radio Button Field
	**/
	public function wp_forum_api_radio_render( $value, $label, $group_id, $selected, $disabled=False ) {
		?>
		<label>
			<input type="radio" name="<?php echo $group_id; ?>" value="<?php echo $value; ?>" <?php checked( $selected ); ?>
			<?php if( $disabled ) echo 'disabled'; ?> />
				<?php echo $label; ?>
		</label>
		<?php
		// Make checkbox readonly, but submit the value as selected
		if ( $selected && $disabled ) {
			?>
				<input type="hidden" name="<?php echo $option_id; ?>" value="1" />
			<?php
		}
	}

	/**
	* Set up settings field callback: Render Text Field
	**/
	public function wp_forum_api_textfield_render( $args ) {
		?>
		<input maxlength="300" size="60" name="<?php echo $args['id'] ?>" id="<?php echo $args['id'] ?>" type="text" value="<?php echo get_option( $args['id'] ); ?>" />
		<?php
	}

	/**
	* Set up settings field callback: Render Textarea Field
	**/
	public function wp_forum_api_textarea_render( $args ) {
		?>
		<textarea rows="5" cols="33" name="<?php echo $args['id'] ?>" id="<?php echo $args['id'] ?>"><?php echo get_option( $args['id'] ); ?></textarea>
		<?php
	}

	/**
	* Set up settings field callback: Render Notification Logs
	**/
	public function wp_forum_api_logs_render( $args ) {
		?>
		<p><b>Updates retrieved as of:</b> <?php echo date('Y-m-d', time() - 60 * 60 * 24); ?></p>
		<p class="forum_notifications"><?php
			$this->logger->print_logs();
		?></p><?php
	}

	/**
	* Set up settings field callback: Render Textarea Field
	**/
	public function wp_forum_api_mappings_render( $args ) {
		$mapped_field = str_replace( '/', " > ", get_option( $args['id'] ) );
		$field_ids = explode('/', get_option( $args['id']) );
		// check that field is activated
		$active = get_option($field_ids[0]);
		for ($i=1; $i < count($field_ids); $i++) {
			if (isset($active[$field_ids[$i]]))
				$active = $active[$field_ids[$i]];
			else
				$active = false;
		}
		// Show selected WP post fields if selected AND active
		if ($mapped_field && $active)
			echo $mapped_field;
		else
			echo "<em>None selected</em>";
	}

	/**
	 * Admin bar
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function admin_bar( $wp_admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get yesterday's date as cut-off for recent posts
		$cutoff_date = date('Y-m-d', time() - 60 * 60 * 24);

		$wp_admin_bar->add_menu( array(
			'id'    => 'wp-forum-api',
			'title' => __( '<span class="ab-icon dashicons dashicons-rest-api"></span>Forum Actions', 'wp-forum-api' ),
			'href'  => '#'
		) );

		// Add admin menu bar subitem for each content section
		// foreach ($this->settings['submenu_pages'] as $section => $field_settings) {
		// 	$wp_admin_bar->add_menu( array(
		// 		'parent' => 'wp-forum-api',
		// 		'id'     => 'wp-forum-api-delete-'.$section,
		// 		'title'  => __( 'Delete '.ucfirst($section).' Data', 'wp-forum-api' ),
		// 		'href'   => wp_nonce_url( admin_url( '?process=delete&section='.$section), 'process' ),
		// 	) );
		// }

		// $wp_admin_bar->add_menu( array(
		// 	'parent' => 'wp-forum-api',
		// 	'id'     => 'wp-forum-api-events-csv',
		// 	'title'  => __( 'Generate Event CSV', 'wp-forum-api' ),
		// 	'href'   => wp_nonce_url( admin_url( '?process=events'), 'process' ),
		// ) );

		// Generate Event ICS (Calendar Event)
		// $wp_admin_bar->add_menu( array(
		// 	'parent' => 'wp-forum-api',
		// 	'id'     => 'wp-forum-api-events-ics',
		// 	'title'  => __( 'Generate Event ICS', 'wp-forum-api' ),
		// 	'href'   => get_feed_link('calendar')
		// ) );

		// Update All Events Feed -> WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-events-update-all',
			'title'  => __( 'Pull All Events', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=update&section=events'), 'process' ),
		) );

		// Update Only Recent Events Feed -> WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-events-update-recent',
			'title'  => __( 'Pull Recent Events', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=update&section=events&date='.$cutoff_date), 'process' ),
		) );

		// Update All News Feed -> WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-news-update-all',
			'title'  => __( 'Pull All News', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=update&section=news'), 'process' ),
		) );

		// Update Recent News Feed -> WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-news-update-recent',
			'title'  => __( 'Pull Recent News', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=update&section=news&date='.$cutoff_date), 'process' ),
		) );


		// Update Jobs Feed -> WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-jobs-update-all',
			'title'  => __( 'Pull All Jobs', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=update&section=opportunities'), 'process' ),
		) );

		// Update Recent Jobs Feed -> WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-jobs-update-recent',
			'title'  => __( 'Pull Recent Jobs', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=update&section=opportunities&date='.$cutoff_date), 'process' ),
		) );


		// Update All Departments Feed -> WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-departments-update-all',
			'title'  => __( 'Pull All Depts', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=update&section=departments'), 'process' ),
		) );

		// Update Recent Departments Feed -> WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-departments-update-recent',
			'title'  => __( 'Pull Recent Depts', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=update&section=departments&date='.$cutoff_date), 'process' ),
		) );

		// Delete Events from WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-events-delete',
			'title'  => __( '__Purge Events', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=delete&section=events'), 'process' ),
		) );

		// Delete News Items from WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-news-delete',
			'title'  => __( '__Purge News Items', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=delete&section=news'), 'process' ),
		) );

		// Delete Job Ads from WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-opportunities-delete',
			'title'  => __( '__Purge Job Ads', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=delete&section=opportunities'), 'process' ),
		) );

		// Delete Member Departments from WP Database
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-departments-delete',
			'title'  => __( '__Purge Depts', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=delete&section=departments'), 'process' ),
		) );

		// Delete All Forum Posts (except Events)
		$wp_admin_bar->add_menu( array(
			'parent' => 'wp-forum-api',
			'id'     => 'wp-forum-api-delete-all',
			'title'  => __( '__Purge All Posts', 'wp-forum-api' ),
			'href'   => wp_nonce_url( admin_url( 'admin.php?page=wp_forum_api_settings&process=delete&section=all'), 'process' ),
		) );
	}



	/** =============================================
	 * Iniialize content categories (EN/FR)
	 *
	 * @since    1.0.0
	 * ============================================= */
	function init_categories() {

		// Look in default post taxonomy
		$taxonomy = 'category';

		// Get language codes
		$lang = $this->pll->lang_codes();
		if ( ! $lang ) return false;

		// iterate over sections
		foreach ( $this->get_sections() as $section ) {
			// English category
			$cats_en = term_exists( $section.'-'.$lang->en, $taxonomy );
			// Create term if does not exist
			if (! $cats_en )
				$cats_en = wp_insert_term( ucfirst($section).' ('.$lang->en.')', $taxonomy );
			if( is_wp_error( $cats_en ) ) {
				// Error in term
				$error = $cats_en->get_error_message();
				$message = "Forum $section category update/insertion failed. " . $error;
				$this->logger->log($message, "error", "database");
				$this->logger->message($message, "error");
				return false;
			}
			// Get category ID
			$cat_en_id = $cats_en['term_id'];

			// French category
			$cats_fr = term_exists( $section.'-'.$lang->fr, $taxonomy);
			// Create term if does not exist
			if (! $cats_fr )
				$cats_fr = wp_insert_term( ucfirst($section).' ('.$lang->fr.')', $taxonomy );
			if( is_wp_error( $cats_fr ) ) {
				// Error in term
				$error = $cats_fr->get_error_message();
				$message = "Forum $section category update/insertion failed. " . $error;
				$this->logger->log($message, "error", "database");
				$this->logger->message($message, "error");
				return false;
			}

			// Get category ID
			$cat_fr_id = $cats_fr['term_id'];

			// [Polylang] Set default language for category
			$this->pll->set_term_language($cat_en_id, $lang->en);
			$this->pll->set_term_language($cat_fr_id, $lang->fr);

			// initialize categories
			$cat_settings = array($lang->en => $cat_en_id, $lang->fr => $cat_fr_id );

			// [Polylang] Defines the translated post
			$this->pll->save_term_translations($cat_settings);
		}
		return true;
	}



	/* ==================================
	 * Plugin notices
	 *
	 * @since    1.0.0
	 * ================================== */

	public function plugin_missing_settings_notice() {
    ?>
    <div class="error notice">
        <p><?php _e( 'Initialization Failed. The JSON settings file is not found.', 'forum_api' ); ?></p>
    </div>
    <?php
	}

	// Polylang plugin requirement
	public function polylang_plugin_notice() {
    ?>
    <div class="error notice">
        <p><?php _e( 'Initialization Failed. The Forum API plugin requires <a target="_blank" href="https://polylang.pro">Polylang</a> plugin.', 'forum_api' ); ?></p>
    </div>
    <?php
	}
	// ACF plugin requirement
	public function acf_plugin_notice() {
	  ?>
	  <div class="error notice">
	      <p><?php _e( 'Initialization Failed. The Forum API plugin requires <a target="_blank" href="https://www.advancedcustomfields.com/">Advanced Custom Fields</a> plugin.', 'forum_api' ); ?></p>
	  </div>
	  <?php
	}
	// The Events Calendar plugin requirement
	public function events_calendar_plugin_notice() {
		?>
		<div class="error notice">
				<p><?php _e( 'Initialization Failed. The Forum API plugin requires <a target="_blank" href="https://theeventscalendar.com/">The Events Calendar</a> plugin.', 'forum_api' ); ?></p>
		</div>
		<?php
	}
	// Post Expirator plugin requirement
	public function post_expirator_plugin_notice() {
		?>
		<div class="error notice">
				<p><?php _e( 'Initialization Failed. The Forum API plugin requires <a target="_blank" href="https://en-ca.wordpress.org/plugins/post-expirator/">Post Expirator</a> plugin.', 'forum_api' ); ?></p>
		</div>
		<?php
	}


	/** =============================================
	 * Filter API settings for class usage
	 *
	 * @since    1.0.0
	 * ============================================= */
	private function get_sections() {
			// remove first "main" section of Forum content settings
			$filtered_settings = $this->settings['submenu_pages'];
			array_shift( $filtered_settings );
			return array_keys( $filtered_settings );

	}

	/** =============================================
	 * Admin notices
	 *
	 * @since    1.0.0
	 * ============================================= */

	private function message( $message, $level ) {
		add_action( 'admin_notices', function() use ( $message, $level ) { $this->db_admin_notice( $message, $level ); }, 10, 2 );
		return False;
	}
	public function db_admin_notice( $message, $level ) {
		?>
		<div class="<?php echo $level; ?> notice">
				<p><?php echo $message; ?></p>
		</div>
		<?php
	}




}
