<?php defined('ABSPATH') || die(-1);
/*
 * @package Sitka Carousels
 * @copyright BC Libraries Coop 2019
 *
 **/
/**
 * Plugin Name: Sitka Carousels 
 * Description: New book carousel generator from Sitka/Evergreen catalogue; provides shortcode for carousels. Install as MUST USE.
 * Author: Ben Holt, BC Libraries Coop 
 * Author URI: http://bc.libraries.coop
 * Version: 0.1.0
 **/

// Include constants definitions
include('inc/coop-sitka-carousels-constants.php');

global $sitka_carousels_db_version;
$sitka_carousels_db_version = '0.1.0';

// Hook called when plugin is activated, called function checks for
// network activation on a multisite install
register_activation_hook( __FILE__, 'sitka_carousels_activate' );

// Action for when a new blog is added to a network install
add_action('wpmu_new_blog', 'sitka_carousels_new_blog', 10, 6 );

// Register sitka_carousel shortcode
add_shortcode( 'sitka_carousel', 'sitka_carousels_shortcode');

// Add slick javascript to <head> - https://kenwheeler.github.io/slick/
wp_enqueue_script('coop-sitka-carousels-slick-js', plugins_url( 'slick/slick.min.js', __FILE__ ), array('jquery', 'jquery-migrate'), FALSE, TRUE);

// Add CSS for slick javascript library
wp_register_style('coop-sitka-carousels-slick-css', plugins_url( 'slick/slick.css', __FILE__ ), false );
wp_register_style('coop-sitka-carousels-slick-theme-css', plugins_url( 'slick/slick-theme.css', __FILE__ ), false );
wp_enqueue_style('coop-sitka-carousels-slick-css' );
wp_enqueue_style('coop-sitka-carousels-slick-theme-css' );

// Add CSS for carousel customization
wp_register_style('coop-sitka-carousels-css', plugins_url( 'css/coop-sitka-carousels.css', __FILE__ ), false );
wp_enqueue_style('coop-sitka-carousels-css' );

// Register add_meta_box to provide instructions on how to add a carousel to a Highlight post
add_action( 'add_meta_boxes', 'coop_sitka_carousels_meta_box_add' );

// Add submenu page for managing the Sitka libraries, their library code, catalogue links, etc. 
#add_submenu_page( 'sites.php', 'Sitka Libraries', 'Sitka Libraries', 'manage_network', 'sitka-libraries', array(&$sitkalistsadmin,'sitka_libraries_page'));

function coop_sitka_carousels_meta_box_add() {
  add_meta_box('coop_sitka_carousels', 'Sitka Carousel Placement', array('sitka_carousels_inner_custom_box'), 'highlight', 'side', 'high');
}


// Callback function for site activation - checks for network activation
function sitka_carousels_activate($network_wide) {
      if ( is_multisite() && $network_wide ) {
        // installing across a multisite network, loop through each blog to install

        global $wpdb;

        foreach ($wpdb->get_col("SELECT blog_id FROM $wpdb->blogs") as $blog_id) {
            switch_to_blog($blog_id);
            sitka_carousels_install();
            restore_current_blog();
        }

    } else {
      // Only installing on one site
      sitka_carousels_install();
    }

}


// Callback function for single site install 
function sitka_carousels_install() {
  global $wpdb;
  global $sitka_carousels_db_version;

  // Name of the database table used by this plugin
  $table_name = $wpdb->prefix . 'sitka_carousels';

  // Check to see if the table exists
  if($wpdb->get_var("SHOW TABLES LIKE '" . $table_name ."'") != $table_name) {

    // Table doesn't exist so create it

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE `$table_name` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `carousel_type` ENUM('adult_fiction','adult_nonfiction', 'adult_largeprint', 'adult_dvds', 'adult_music', 'teen_fiction', 'teen_nonfiction', 'juvenile_fiction', 'juvenile_nonfiction', 'juvenile_dvdscds') NULL,
      `date_active` datetime NULL,
      `date_created` datetime NULL,
      `bibkey` int(11) NULL, 
      `catalogue_url` varchar(2048) NULL,
      `cover_url` varchar(2048) NULL,
      `title` varchar(255) NULL,
      `author` varchar (255) NULL,
      `description` text NULL,
      PRIMARY KEY (`id`),
      INDEX carousel_type_index (`carousel_type`),
      INDEX date_created_index (`date_created`),
      INDEX date_active_index (`date_active`),
      INDEX bibkey_index (`bibkey`));";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( 'sitka_carousels_db_version', $sitka_carousels_db_version );
  }
}


// Callback function for when a new blog is added to a network install
function sitka_carousels_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta) {

    //replace with your base plugin path E.g. dirname/filename.php
    if ( is_plugin_active_for_network( 'coop-sitka-carousels/coop-sitka-carousels.php' ) ) {
        switch_to_blog($blog_id);
        sitka_carousels_install();
        restore_current_blog();
    } 

}


// Callback function for generating sitka_carousel shortag
function sitka_carousels_shortcode( $attr ) {
  global $wpdb;

  // Variable created to ensure each carousel on a page has a unique class, variable is static so that it
  // is available across multiple calls to this function
  static $carousel_class = array();
  $carousel_class[] = 'sitka-carousel-' . count($carousel_class);

  // Set carousel type
  in_array($attr['type'], CAROUSEL_TYPE  ) ? $type = $attr['type'] : $type = CAROUSEL_TYPE[0];

  // Set transition type
  in_array($attr['transition'], CAROUSEL_TRANSITION) ? $transition = $attr['transition'] : $transition = CAROUSEL_TRANSITION[0];

  // Get the number of new items in the last month, will be used to decide whether to show items from last month or 
  // up to CAROUSEL_MIN items, which would include items older than 1 month
  if ($wpdb->get_var($wpdb->prepare("SELECT COUNT(*)
                                       FROM " . $wpdb->prefix . "sitka_carousels
                                      WHERE carousel_type = %s
                                        AND date_active > (NOW() - INTERVAL 1 MONTH)", $type)) >= CAROUSEL_MIN ) {
  
    // Use items added within last month
    $sql = $wpdb->prepare("SELECT bibkey AS bibkey,
                                  catalogue_url AS catalogue_url,
                                  cover_url AS cover_url,
                                  title AS title,
                                  author AS author,
                                  description AS description
                             FROM " . $wpdb->prefix . "sitka_carousels
                            WHERE carousel_type = %s
                              AND date_active > (NOW() - INTERVAL 1 MONTH)
                         ORDER BY date_active DESC", $type);
  }
  else {

    // Use most recent CAROUSEL_MIN items
    $sql = $wpdb->prepare("SELECT bibkey AS bibkey,
                                  catalogue_url AS catalogue_url,
                                  cover_url AS cover_url,
                                  title AS title,
                                  author AS author,
                                  description AS description
                             FROM " . $wpdb->prefix ."sitka_carousels
                            WHERE carousel_type = %s
                              AND date_active IS NOT NULL
                         ORDER BY date_active DESC
                            LIMIT %d", array($type, CAROUSEL_MIN));
  }

  $results = $wpdb->get_results( $sql, ARRAY_A );

  $tag_html = "<div class='sitka-carousel-container'><div class='" . end( $carousel_class )  . "' >";

  foreach ( $results as $row ) {

    // Build the HTML to return for the short tag
    $tag_html .= "<div class='sikta-item'>" . 
                   "<a href='" . $row['catalogue_url'] . "'>" .
                     "<img src='" . $row['cover_url'] . "' class='sitka-carousel-image'>" .
                     "<img src='/wp-content/mu-plugins/coop-sitka-lists/img/nocover.jpg' class='sitka-carousel-image sitka-carousel-image-default'>" .
                     "<div class='sitka-info'>" . 
                       "<span class='sitka-title'>" . $row['title'] . "</span><br />" .
                       "<span class='sitka-author'>" . $row['author'] . "</span>" .
                     "</div>" .
                   "</a>" .
                   "<div class='sitka-description'>" . $row['description'] . "</div>" .
                 "</div>";
  } // foreach

  $tag_html .= "</div></div>";

  $tag_html .= "<script type='text/javascript'>
    jQuery(document).ready(function(){
      jQuery('." . end( $carousel_class ) . "').slick({
      slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: true,
        autoplaySpeed: 3000,
        speed: 1000,
        infinite: true,
        pauseOnHover: true,
        accessibility: true,
        fade: "; 
  $transition == 'fade' ? $tag_html .= "true" : $tag_html .= "false";
  $tag_html .=  "});
     })
    </script>";

  return $tag_html;

} // function sitka_carousel_shortcode


/*
 * Custom Meta Box on Highlights admin page to provide instructions on how to add
 * a Sitka Carousel shortcode.
 */
function sitka_carousels_inner_custom_box($post) {

  $out = '<p>Sitka Carousels can be added to this Highlight by inserting one or more shortcodes into the Highlight text. ' . 
         'Shortcodes take the following format:<br /> <strong>[sitka_carousel type="adult_fiction" transition="fade"]</strong></p>' .
         '<p>Possible values for type: adult_fiction, adult_nonfiction, adult_largeprint, adult_dvds, adult_music, ' .
         'teen_fiction, teen_nonfiction, juvenile_fiction, juvenile_nonfiction, or juvenile_dvdscds. Defaults ' .
         'to adult_fiction.</p>' .
         '<p>Possible values for transition are fade or swipe. Defaults to fade.</p>' .
         '<p>More than one carousel shortcode can be added to a Highlight.</p>';

  return $out;
}






#function sitka_libraries_page() {
#
#  global $wpdb;
#
#  // Get the blog_id of each blog
#  $blogs = $wpdb->get_results("SELECT blog_id AS blog_id,
#                                      domain AS domain
#                                 FROM wp_blogs", ARRAY_A);
#
#//  require_once 'config-build-lists.inc';
#
#  $out = '<div class="wrap">' . 
#         '  <div id="icon-options-general" class="icon32">' . 
#         '  <br>' .
#         '</div>' .
#
#         '<h2>Sitka Libraries</h2>' .
#         '<p>&nbsp;</p>' .
#
#         '<table class="sitka-lists-admin-table">' .
#         '  <tr><th>WP site id</th><th>Domain name</th><th>Sitka Shortcode</th><th>Sitka Locale</th><th>Catalogue domain</th></tr>';
#
#  // Loop through each blog, lookup options, and output form
#  foreach ($blogs as $blog) {
#
#    switch_to_blog($blog->blog_id);
#
#    $lib_shortcode = get_option('_coop_sitka_lib_shortname');
#
#    //If no value for locg exists, set it to 1 (parent container for Sitka)
#    $lib_locg = (!get_option('_coop_sitka_lib_locg')) ? update_option('_coop_sitka_lib_locg', '1') : get_option('_coop_sitka_lib_locg');
#
#    //Must be blank by default. Same shortname stem used when it agrees with Sitka catalogue subdomain. Blogs with custom domains are the only ones targetted here.
#    $lib_cat_link = get_option('_coop_sitka_lib_cat_link');
#
#    // Switch back to previous blog (main network blog)
#    restore_current_blog();
#
#    // Output form
#    $out .= sprintf('<tr><td>%d</td><td>%s</td><td><input type="text" id="shortcode_%d" class="shortcode widefat" value="%s"></td>' . 
#                    '<td><input type="text" id="locg_%d" class="shortcode widefat" value="%d"></td><td><input type="text" id="cat_link_%d" class=shortcode widefat" value="%s"></td></tr>',
#                    $blog['blog_id'], $blog['domain'], $blog['blog_id'], $lib_shortcode, $blog['blog_id'], $lib_locg, $blog['blog_id'], $lib_cat_link);
#
#  }
#
#  $out .= '<tr><td>&nbsp;</td><td></td><td></td></tr>' .
#          '<tr><td><button class="button button-primary sitka-libraries-save-btn">Save changes</button></td><td></td></tr>' .
#          '</table><!-- .sitka-lists-admin-table -->' . 
#          '</div><!-- .wrap -->';
#
#}
