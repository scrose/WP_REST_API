<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @author     Spencer Rose <support@runtime.ca>
 * @since      1.0.0
 *
 * @package    WP_Forum_API
 * @subpackage WP_Forum_API/admin/partials
 */
?>
  <?php settings_errors(); ?>
    <div class="wrap">
      <h2>API Plugin Settings</h2>
      <p>
        Use this options page to configure the connection settings to the REST API.</a>.
      </p>
        <form method="post" action="options.php">
          <?php
            settings_fields( 'wp_forum_api_settings' );
            do_settings_sections( 'wp_forum_api_settings' );
            submit_button();
          ?>
        </form>
      </div>
