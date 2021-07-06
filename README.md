# WPSync: WordPress MediaWiki Content Sync

## Overview

WPSync is a WordPress plugin designed to synchronize WP content using the [MediaWiki](https://www.mediawiki.org/wiki/MediaWiki) Action API web service.
This service allows access to some wiki-features like authentication, page operations, and search. For documentation on available
endpoints, visit [MediaWiki:API](https://www.mediawiki.org/wiki/API:Main_page).

## Installation

1.  Upload `cscan-forum-api.php` to the `/wp-content/plugins/` directory
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Ensure dependent plugins are installed.
4.  Configure `/admin/settings.json` to match the MediaWiki content types.
5.  Create post categories for each post content type.


## Features

- Automates content synchronization between WP and MediaWiki database.
- Allows cron-scheduled updates to generate WP posts from imported data.
- Uses an external JSON file `/admin/settings.json` to configure custom fields associated with different WP posts differentiated by post categories.
- Uses ACF plugin to manage custom fields defined in `/admin/settings.json`.
- Includes bilingual (French/English) option (using Polylang).

## Required Plugins

- [Advanced Custom Fields (ACF)](https://www.advancedcustomfields.com/)
- [Polylang](https://polylang.pro/)
- [WP-Cron](https://developer.wordpress.org/plugins/cron/)
- [Post Expirator](https://en-ca.wordpress.org/plugins/post-expirator/)

## Optional Plugins

- [The Events Calendar](https://theeventscalendar.com)
