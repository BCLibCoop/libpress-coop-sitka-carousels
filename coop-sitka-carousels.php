<?php defined('ABSPATH') || die(-1);
/*
 * @package Sitka Carousels
 * @copyright BC Libraries Coop 2019
 *
 **/
/**
 * Plugin Name: Sitka Carousels
 * Description: New book carousel generator from Sitka/Evergreen catalogue; provides shortcode for carousels. Install as Network Activated.
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

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', 'coop_sitka_carousels_enqueue_dependencies');

function coop_sitka_carousels_enqueue_dependencies() {
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
}

// Register add_meta_box to provide instructions on how to add a carousel to a Highlight post
add_action( 'add_meta_boxes', 'coop_sitka_carousels_meta_box_add', 10, 2 );

function coop_sitka_carousels_meta_box_add() {
  add_meta_box('coop_sitka_carousels', 'Sitka Carousel Placement', 'sitka_carousels_inner_custom_box', 'highlight', 'side', 'high');
}


// Add submenu page for managing the Sitka libraries, their library code, catalogue links, etc. 
add_action('network_admin_menu', 'coop_sitka_carousels_network_admin_menu');

function coop_sitka_carousels_network_admin_menu() {
  add_submenu_page( 'sites.php', 'Sitka Libraries', 'Sitka Libraries', 'manage_network', 'sitka-libraries', 'coop_sitka_carousels_sitka_libraries_page');
}

add_action('admin_menu', 'coop_sitka_carousels_controls_admin', 20);

function coop_sitka_carousels_controls_admin() {
    add_submenu_page( 'site-manager', 'Sitka Carousel Controls',
      'Sitka Carousel Controls', 'manage_options', 'sitka-carousel-controls',
      'coop_sitka_carousels_controls_form');
}

function coop_sitka_carousels_controls_form() {
    //Display in form:
    //Last checked option date
    //Radio buttons: Last month, 3 months, 6 months
    //Submit button -> AJAX show output of runner
    $out = [];
    $run_message = '';
    $last_checked = get_option('_coop_sitka_carousels_date_last_checked');
    $site_name = get_option('blogname');
    $shortname = get_option('_coop_sitka_lib_shortname');

    //Only show controls when a shortname is set and non-default.
    if ( $shortname && $shortname != 'NA' ) {
        $out[] = "<h3>Sitka Carousel Controls - {$site_name}</h3>";
        $out[] = "<p>Last full run: <input type='text' id='last_checked' name='last_checked' disabled value={$last_checked}></p>";
        $out[] = '<div class="sitka-carousel-controls">
        <form>
            <h4>Set re-check period:</h4>
                <div class="sitka-carousel-radios">
                <input type="radio" id="last_one" name="recheck_period" value="1">Last month<br>
                <input type="radio" id="last_two" name="recheck_period" value="2">2 months ago<br>
                <input type="radio" id="last_four" name="recheck_period" value="4">4 months ago<br>
                </div><br />';

        $out[] = get_submit_button('Select a period.', 'primary large', 'controls-submit',
        FALSE) . '</form>';
        if ($transient = get_transient('_coop_sitka_carousels_new_items_by_list'))
            $run_message = "The following new items were retrieved last run:
<br /><pre>". json_encode($transient, JSON_PRETTY_PRINT) . "</pre>";
        $out[] = "<p id='run-messages'>{$run_message}</p></div>";
        echo implode("\n", $out);
    } else {
        echo sprintf('<h3>No Sitka Carousels shortname set for this site.</h3>
        <p>Set a shortname <a href="%swp-admin/network/sites.php?page=sitka-libraries">here</a> to allow carousel runs.</p>',
          network_site_url());
    }
}

// Add callback to handle the admin form submission
add_action( 'admin_post', 'coop_sitka_carousels_save_admin_callback' );


/*
 * Callback function for site activation - checks for network activation
 */
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


/*
 * Callback function for single site install
 */
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


/*
 * Callback function for when a new blog is added to a network install
 */
function sitka_carousels_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta) {

    //replace with your base plugin path E.g. dirname/filename.php
    if ( is_plugin_active_for_network( 'coop-sitka-carousels/coop-sitka-carousels.php' ) ) {
        switch_to_blog($blog_id);
        sitka_carousels_install();
        restore_current_blog();
    }

}


/*
 * Callback function for generating sitka_carousel shortag
 */
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
                     "<img src='" . plugins_url( 'img/nocover.jpg', __FILE__ ) ."' class='sitka-carousel-image sitka-carousel-image-default'>" .
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

  echo $out;
}



/*
 * Network Admin configuration page for setting each library's Sitka Shortcode, Sitka Locale, and Catalogue Domain.
 */
function coop_sitka_carousels_sitka_libraries_page() {

  if (! is_super_admin() ) {
    // User is not a network admin
    die('Sorry, you do not have permission to access this page');
  }

  global $wpdb;

  // Get the blog_id of each blog
  $blogs = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id AS blog_id,
                                                       domain AS domain
                                                  FROM wp_blogs
                                                 WHERE public = %d
                                              ORDER BY blog_id ASC", 1), ARRAY_A);


  $out = '<div class="wrap">' . 
         '  <div id="icon-options-general" class="icon32">' . 
         '  <br>' .
         '</div>' .

         '<h2>Sitka Libraries</h2>' .
         '<p>&nbsp;</p>' .
         '<form method="post" action="' . esc_url(admin_url('admin-post.php')) .'">' .

         '<table class="sitka-lists-admin-table">' .
         '  <tr><th>WP site id</th><th>Domain name</th><th>Sitka Shortcode</th><th>Sitka Locale</th><th>Catalogue domain</th></tr>';

  // Loop through each blog lookup options and output form
  foreach ($blogs as $blog) {

    switch_to_blog($blog['blog_id']);

    $lib_shortcode = get_option('_coop_sitka_lib_shortname');

    //If no value for locg exists, set it to 1 (parent container for Sitka)
    $lib_locg = (!get_option('_coop_sitka_lib_locg')) ? update_option('_coop_sitka_lib_locg', '1') : get_option('_coop_sitka_lib_locg');

    //Must be blank by default. Same shortname stem used when it agrees with Sitka catalogue subdomain. Blogs with custom domains are the only ones targetted here.
    $lib_cat_link = get_option('_coop_sitka_lib_cat_link');

    // Output form
    $out .= sprintf('<tr><td>%d</td><td>%s</td><td><input type="text" name="shortcode_%d" class="shortcode widefat" value="%s"></td>' . 
                    '<td><input type="text" name="locg_%d" class="shortcode widefat" value="%d"></td><td><input type="text" name="cat_link_%d" class=shortcode widefat" value="%s"></td></tr>',
                    $blog['blog_id'], $blog['domain'], $blog['blog_id'], $lib_shortcode, $blog['blog_id'], $lib_locg, $blog['blog_id'], $lib_cat_link);

    // Switch back to previous blog (main network blog)
    restore_current_blog();

  }

  $out .= '<tr><td>&nbsp;</td><td></td><td></td></tr>' .
          '<tr><td><button class="button button-primary sitka-libraries-save-btn">Save changes</button></td><td></td></tr>' .
          '</table>' . 
          wp_nonce_field("admin_post", "coop_sitka_carousels_nonce") . 
          '</form>' .
          '</div><!-- .wrap -->';

  echo $out;

}

/*
 * Callback to handle the network admin form submission
 */
function coop_sitka_carousels_save_admin_callback() {
  // Check the nonce field, if it doesn't verify report error and stop
  if (! isset( $_POST['coop_sitka_carousels_nonce']) || ! wp_verify_nonce( $_POST['coop_sitka_carousels_nonce'], 'admin_post')) {
    die('Sorry, there was an error handling your form submission.');
  }

  if (! is_super_admin() ) {
    // User is not a network admin
    die('Sorry, you do not have permission to access this page');
  }

  global $wpdb;

  // Query DB to get all of the blogs
  $blogs = $wpdb->get_results( $wpdb->prepare( "SELECT blog_id AS blog_id
                                                  FROM wp_blogs
                                                 WHERE public = %d", 1), ARRAY_A);
  foreach( $blogs AS $blog) {
    // Loop through each blog and update
    switch_to_blog($blog['blog_id']);

    $shortname = strtoupper( sanitize_text_field($_POST['shortcode_' . $blog['blog_id']]) );
    $locg = sanitize_text_field($_POST['locg_' . $blog['blog_id']]);
    $cat_link = sanitize_text_field($_POST['cat_link_' . $blog['blog_id']]);

    // Shortcode
    // Note: The previous carousel plugin appears to have put NA in as a placeholder for unset shortcodes so
    //       we test for it here
    if ( ( isset($shortname) ) && ( $shortname != "NA" ) ) {
      update_option('_coop_sitka_lib_shortname', $shortname );
    }

    // Sitka Locale (locg)
    if ( is_numeric($locg) ) {
      update_option('_coop_sitka_lib_locg', $locg);
    }

    // Catalogue Link
    if ( isset($cat_link) ) {
      update_option('_coop_sitka_lib_cat_link', $cat_link);
    }

    restore_current_blog();
  }

  // Return to the form page
  wp_redirect(network_admin_url('sites.php?page=sitka-libraries'));

}

add_action( 'admin_footer', 'coop_sitka_carousels_control_js' );
add_action( 'wp_ajax_coop_sitka_carousels_control_callback', 'coop_sitka_carousels_control_callback' );

function coop_sitka_carousels_control_js() {
  $ajax_nonce = wp_create_nonce( "coop-sitka-carousels-limit-run" );
  $ajax_url = admin_url( 'admin-ajax.php' );
  ?>
  <script type="text/javascript" >
  jQuery(document).ready(function($) {

      //@todo issue request for the relevant transient if it was set.

    $('#controls-submit').addClass('disabled');
    //default
      let $period_options = $('input:radio[name=recheck_period]');
      let $period_checked = $('input:radio[name=recheck_period]:checked');

      $period_options.click(function(event) {
          $period_checked = $($period_checked.selector);
        if ($period_checked.val() !== undefined) { //just in case
            $('#controls-submit').removeClass('disabled').val('Ready to run.');
        }
    });

    $('#controls-submit').click( function (event) {
      event.preventDefault();
      $period_checked = $($period_checked.selector);

      let data = {
        action: 'coop_sitka_carousels_control_callback',
        mode: 'single',
        recheck_period: $period_checked.val(),
        security: '<?php echo $ajax_nonce; ?>',
      };

      // Give user cue not to click again
      $('#controls-submit').addClass('disabled').val('Working...');
      // Provide status message
      $('#run-messages').append('This can take about 2 minutes for ' +
            'the average library. Please wait...');

      // $('#controls-submit').removeClass('disabled').val('Ready to run.');

      $.post('<?php echo $ajax_url; ?>', data, function(response) {
          if ( response.success == true ) {
              console.log('Run has been initiated with WP-CLI. Check this ' +
                  'page again in about 5 minutes for results.');
          }
      });
    });
  });
  </script> <?php
}

//Action callback for single or groups runs called by AJAX
function coop_sitka_carousels_control_callback() {
  $data = $_POST;

  if ( check_ajax_referer('coop-sitka-carousels-limit-run', 'security', FALSE)
    == FALSE ) {
    wp_send_json_error();
  }

  $mode = sanitize_text_field($data['mode']);
  $recheck_period = (int) sanitize_text_field($data['recheck_period']);

  // expects array of IDs
  // mode is always single when triggered by this button
  $blog = get_current_blog_id();

  //Prepare command
  $ini_path = '/app/overrides/php.ini'; //localized for lando.
  $ini_path = '/etc/php/7.0/php.ini'; //generalized for ubuntu-server.
  $executable = "/usr/local/bin/php -c $ini_path -d error_reporting='E_ALL & ~E_NOTICE' /usr/local/bin/wp ";
  $command = sprintf(" --path=%s sitka-carousel-runner --mode=%s --target=%s --recheck=%d",
    ABSPATH, $mode, $blog, $recheck_period
  );
  $output = [];
  $suffix = ' 2>&1 &'; //redirect stderr and run in background
  exec($executable . $command . $suffix, $output);

  //Complete the request with OK
  wp_send_json_success(array(), 200);
  wp_die();
}

//Custom CLI commands
add_action( 'cli_init', 'coop_sitka_carousels_register_cli_cmd' );
function coop_sitka_carousels_register_cli_cmd() {
  WP_CLI::add_command( 'sitka-carousel-runner', 'coop_sitka_carousels_limited_run' );
}

function coop_sitka_carousels_limited_run( $args = array(), $assoc_args =
array
('mode' => 'single',
  'target' => array() ) ) {

  // Get arguments.
  $parsed_args = wp_parse_args(
    $args,
    $assoc_args
  );

  if (!empty($parsed_args['target'])) {
    //WP_CLI::debug("Checking for new items for blog ID
    // {$parsed_args['target'][0]}...");
    //@todo reset
    WP_CLI::debug("ARGS " . print_r($parsed_args, true));

    require_once WP_PLUGIN_DIR . '/coop-sitka-carousels/inc/coop-sitka-carousels-update.php';
    $CarouselRunner = new \SitkaCarouselRunner(
        $parsed_args['mode'],
        array( (int) $parsed_args['target'] )
    );
    //@todo It can run without populating transient, so remove this. Wrap
    // above in try/catch?

    if( $newItems = $CarouselRunner::getNewListItems()) {
      WP_CLI::success("The following new items were retrieved: <pre>". json_encode($newItems, JSON_PRETTY_PRINT) . "</pre>");
    } else {
      WP_CLI::error( "Failed to populate any new items.");
    }
  }
}
