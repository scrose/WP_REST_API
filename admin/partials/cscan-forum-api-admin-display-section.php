<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    WP_Forum_API
 * @subpackage WP_Forum_API/admin/partials
 */
?>
  <?php settings_errors(); global $plugin_page; ?>
    <div class="wrap">
      <h2><?php echo get_admin_page_title(); ?></h2>
      <p>
        Use this options page to manage data fields for imported Forum API data.
      </p>
      <form method="post" action="options.php">
        <?php
          settings_fields( $plugin_page );
          do_settings_sections( $plugin_page );
          submit_button();
        ?>
      </form>
    </div>
