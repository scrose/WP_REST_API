<?php

/**
 * The file that defines the Forum API WP Advanced Custom Fields Search Extension
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
class WP_Forum_Search {


	/**
	 * Forum API::ACF API
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      WP_Forum_Search    $data    Maintains and registers all hooks for the plugin.
	 */
	protected $acf;

	/**
	 * Constructor
	 *
	 * @since    1.0.0
	 */
	public function __construct( $acf ) {
    $this->acf = $acf;
  }

  /**
   * [advanced_custom_search search that encompasses ACF/advanced custom fields and taxonomies and split expression before request]
   * @param  [query-part/string]      $where    [the initial "where" part of the search query]
   * @param  [object]                 $wp_query []
   * @return [query-part/string]      $where    [the "where" part of the search query as we customized]
   * see https://vzurczak.wordpress.com/2013/06/15/extend-the-default-wordpress-search/
   * credits to Vincent Zurczak for the base query structure/spliting tags section
   */
  public function advanced_custom_search( $where, $wp_query ) {

      global $wpdb;

      if ( empty( $where ))
          return $where;

      // get search expression
      $terms = $wp_query->query_vars[ 's' ];

      // explode search expression to get search terms
      $exploded = explode( ' ', $terms );
      if( $exploded === FALSE || count( $exploded ) == 0 )
          $exploded = array( 0 => $terms );

      // reset search in order to rebuilt it as we whish
      $where = '';

      // get searcheable_acf, a list of advanced custom fields you want to search content in
      $list_searcheable_acf = $this->acf->get_active_fields();

      foreach( $exploded as $tag ) :
          $where .= "
            AND (
              (wp_posts.post_title LIKE '%$tag%')
              OR (wp_posts.post_content LIKE '%$tag%')
              OR EXISTS (
                SELECT * FROM wp_postmeta
  	              WHERE post_id = wp_posts.ID
  	                AND (";

          foreach ($list_searcheable_acf as $searcheable_acf) :
            if ($searcheable_acf == $list_searcheable_acf[0]):
              $where .= " (meta_key LIKE '%" . $searcheable_acf . "%' AND meta_value LIKE '%$tag%') ";
            else :
              $where .= " OR (meta_key LIKE '%" . $searcheable_acf . "%' AND meta_value LIKE '%$tag%') ";
            endif;
          endforeach;

  	        $where .= ")
              )
              OR EXISTS (
                SELECT * FROM wp_comments
                WHERE comment_post_ID = wp_posts.ID
                  AND comment_content LIKE '%$tag%'
              )
              OR EXISTS (
                SELECT * FROM wp_terms
                INNER JOIN wp_term_taxonomy
                  ON wp_term_taxonomy.term_id = wp_terms.term_id
                INNER JOIN wp_term_relationships
                  ON wp_term_relationships.term_taxonomy_id = wp_term_taxonomy.term_taxonomy_id
                WHERE (
            		taxonomy = 'post_tag'
              		OR taxonomy = 'category'
              		OR taxonomy = 'myCustomTax'
            		)
                	AND object_id = wp_posts.ID
                	AND wp_terms.name LIKE '%$tag%'
              )
          )";
      endforeach;
      return $where;
  }
}
