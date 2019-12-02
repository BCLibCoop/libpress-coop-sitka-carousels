<?php defined('ABSPATH') || die(-1);

/**
 * @package Coop Sitka Carousels
 * @copyright BC Libraries Coop 2019
 *
 **/
/**
 * Plugin Name: Coop Sitka Carousels 
 * Description: New item carousel generator for Sitka/Evergreen ILS; provides shortcode for carousels. Install as MUST USE.
 * Author: Ben Holt, BC Libraries Coop 
 * Author URI: http://bc.libraries.coop
 * Version: 0.1.0
 **/

// Include constants definitions
include('inc/coop-sitka-carousels-constants.php');

global $sitka_carousels_db_version;
$sitka_carousels_db_version = '0.1.0';
 
register_activation_hook( __FILE__, 'sitka_carousels_install' );

// Register sitka_carousel shortcode
add_shortcode( 'sitka_carousel', 'sitka_carousels_shortcode');

// Add javascript to <head>
//wp_enqueue_script('coop-sitka-carousels', get_template_directory_uri() .'/js/coop-sitka-carousels.js', array('jquery'), null, true);

// Callback function for plugin activation 
function sitka_carousels_install() {
  global $wpdb;
  global $sitka_carousels_db_version;

  $table_name = $wpdb->prefix . 'sitka_carousels';
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


// Callback function for generating sitka_carousel shortag
function sitka_carousels_shortcode( $attr ) {
  global $wpdb;

  // Variable created to ensure each carousel on a page has a unique class
  static $carousel_class = array();
  $carousel_class[] = 'sitka-carousel-' . count($carousel_class);

  // Set carousel type
  in_array($attr['type'], CAROUSEL_TYPE  ) ? $type = $attr['type'] : $type = CAROUSEL_TYPE[0];

  // Set transition type
  in_array($attr['transition'], CAROUSEL_TRANSITION) ? $transition = $attr['transition'] : $transition = CAROUSEL_TRANSITION[0];

  // Get the number of new items in the last month, will be used to decide whether to show items from last month or 
  // up to CAROUSEL_MIN items, which would include items older than 1 month
  $sql = "SELECT COUNT(*) FROM {$wpdb->sitka_carousels} WHERE date_active > NOW() - 1 MONTH";
  
  if ( $wpdb->get_var( $sql ) >= CAROUSEL_MIN ) { 

    // Use items added within last month
    $sql = "SELECT bibkey AS bibkey,
                   catalogue_url AS catalogue_url,
                   cover_url AS cover_url,
                   title AS title,
                   author AS author,
                   description AS description
              FROM {$wpdb->sitka_carousels}
             WHERE carousel_type = " . $type . "
               AND date_active > NOW() - 1 MONTH
          ORDER BY date_active DESC";
  }
  else {

    // Use most recent CAROUSEL_MIN items
    $sql = "SELECT bibkey AS bibkey,
                   catalogue_url AS catalogue_url,
                   cover_url AS cover_url,
                   title AS title,
                   author AS author,
                   description AS description
              FROM {$wpdb->sitka_carousels}
             WHERE carousel_type = " . $type . "
               AND date_active NOT NULL
          ORDER BY date_active DESC
             LIMIT " . CAROUSEL_MIN;
  }

  $results = $wpdb->get_results( $sql, ARRAY_A );

  $tag_html = "<div class='" . end( $carousel_class )  . "' >";

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

  $tag_html .= "</div>";

  $tag_javascript = "jQuery(document).ready(function(){" .
                      "jQuery('" . end( $carousel_class ) . "').slick({" .
                        "slidesToShow: 1," .
                        "slidesToScroll: 1," .
                        "autoplay: true," .
                        "autoplaySpeed: 2000," .
                        "speed: 300," .
                        "infinite: true," .
                        "pauseOnHover: true," .
                        "accessibility: true,"; 
  $transition == 'fade' ? $tag_javascript .= "true" : $tag_javascript .= "false";
  $tag_javascript .=  "});" .
                    "});";

  wp_add_inline_script('slick-script', $tag_javascript);

  return $tag_html;

} // function sitka_carousel_shortcode

