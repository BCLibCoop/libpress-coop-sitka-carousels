<?php defined('ABSPATH') || die(-1);

/**
 * Sitka Carousels
 *
 * New book carousel generator from Sitka/Evergreen catalogue; provides shortcode for carousels
 *
 * PHP Version 7
 *
 * @package           BCLibCoop\SitkaCarousels
 * @author            Ben Holt <ben.holt@bc.libraries.coop>
 * @author            Jonathan Schatz <jonathan.schatz@bc.libraries.coop>
 * @author            Sam Edwards <sam.edwards@bc.libraries.coop>
 * @copyright         2019-2021 BC Libraries Cooperative
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Sitka Carousels
 * Description:       New book carousel generator from Sitka/Evergreen catalogue; provides shortcode for carousels
 * Version:           1.2.0
 * Network:           true
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * Author:            BC Libraries Cooperative
 * Author URI:        https://bc.libraries.coop
 * Text Domain:       coop-sitka-carousels
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Include constants definitions
include 'inc/coop-sitka-carousels-constants.php';

global $sitka_carousels_db_version;
$sitka_carousels_db_version = '0.1.0';

// Hook called when plugin is activated, called function checks for
// network activation on a multisite install
register_activation_hook(__FILE__, 'sitka_carousels_activate');

// Register sitka_carousel shortcode
add_shortcode('sitka_carousel', 'sitka_carousels_shortcode');

// Enqueue scripts and styles
function coop_sitka_carousels_enqueue_dependencies()
{
    // Add slick javascript to <head> - https://kenwheeler.github.io/slick/
    wp_enqueue_script(
        'coop-sitka-carousels-slick-js',
        plugins_url('slick/slick.min.js', __FILE__),
        ['jquery'],
        false,
        true
    );

    // Add CSS for slick javascript library
    wp_enqueue_style('coop-sitka-carousels-slick-css', plugins_url('slick/slick.css', __FILE__), false);
    wp_enqueue_style('coop-sitka-carousels-slick-theme-css', plugins_url('slick/slick-theme.css', __FILE__), false);

    // Add CSS for carousel customization
    wp_enqueue_style('coop-sitka-carousels-css', plugins_url('css/coop-sitka-carousels.css', __FILE__), false);
}
add_action('wp_enqueue_scripts', 'coop_sitka_carousels_enqueue_dependencies');

// Register add_meta_box to provide instructions on how to add a carousel to a Highlight post
function coop_sitka_carousels_meta_box_add()
{
    add_meta_box(
        'coop_sitka_carousels',
        'Sitka Carousel Placement',
        'sitka_carousels_inner_custom_box',
        'highlight',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'coop_sitka_carousels_meta_box_add', 10, 2);

// Add submenu page for managing the Sitka libraries, their library code, catalogue links, etc.
function coop_sitka_carousels_network_admin_menu()
{
    add_submenu_page(
        'sites.php',
        'Sitka Libraries',
        'Sitka Libraries',
        'manage_network',
        'sitka-libraries',
        'coop_sitka_carousels_sitka_libraries_page'
    );
}
add_action('network_admin_menu', 'coop_sitka_carousels_network_admin_menu');

function coop_sitka_carousels_controls_admin()
{
    add_submenu_page(
        'site-manager',
        'Sitka Carousel Controls',
        'Sitka Carousel Controls',
        'manage_options',
        'sitka-carousel-controls',
        'coop_sitka_carousels_controls_form'
    );
}
add_action('admin_menu', 'coop_sitka_carousels_controls_admin', 20);

function coop_sitka_carousels_controls_form()
{
    // Display in form:
    // Last checked option date
    // Radio buttons: Last month, 3 months, 6 months
    // Submit button -> AJAX show output of runner
    $out = [];
    $run_message = '';
    $last_checked = get_option('_coop_sitka_carousels_date_last_checked');
    $site_name = get_option('blogname');
    $shortname = get_option('_coop_sitka_lib_shortname');

    // Only show controls when a shortname is set and non-default.
    if ($shortname && $shortname !== 'NA') {
        $out[] = "<h3>Sitka Carousel Controls - {$site_name}</h3>";
        $out[] = "<p>Last full run: ";
        $out[] = "<input type='text' id='last_checked' name='last_checked' disabled value={$last_checked}>";
        $out[] = "</p>";
        $out[] = '<div class="sitka-carousel-controls">
                    <form>
                        <h4>Set re-check period:</h4>
                        <div class="sitka-carousel-radios">
                            <input type="radio" id="last_one" name="recheck_period" value="1">Last month<br>
                            <input type="radio" id="last_two" name="recheck_period" value="2">2 months ago<br>
                            <input type="radio" id="last_four" name="recheck_period" value="4">4 months ago<br>
                </div><br />';

        $out[] = get_submit_button('Select a period.', 'primary large', 'controls-submit', false) . '</form>';

        if ($transient = get_transient('_coop_sitka_carousels_new_items_by_list')) {
            $run_message = "<br />The following new items were retrieved last run:<br /><pre>"
                            . json_encode($transient, JSON_PRETTY_PRINT) . "</pre>";
        }

        $out[] = "<p id='run-messages'>{$run_message}</p></div>";

        $out[] = "<h2>Carousel Search Links</h2>";
        $out[] = "<p>Use these links to perform a similar search to the automated
        checker for each type of carousel to see if an item should be showing in the carousel or not.</p>";
        $out[] = "<ul>";

        $option_last_checked = get_option(
            '_coop_sitka_carousels_date_last_checked',
            date('Y-m-d', mktime(0, 0, 0, date('m') - 4, date('d'), date('Y')))
        );

        try {
            $date = date_create($option_last_checked);
            $date->sub(new DateInterval('P1M'));
            $date_checked = $date->format('Y-m-d');
        } catch (Exception $e) {
            error_log("Something went wrong with date rechecking: " . $e->getMessage());
        }

        foreach (CAROUSEL_TYPE as $carousel_type) {
            $carousel_search = urlencode(CAROUSEL_SEARCH[$carousel_type] . " create_date($date_checked)");
            $link = "https://catalogue.libraries.coop/opac/extras/opensearch/1.1/$shortname/html/?searchTerms=";
            $link .= "$carousel_search&searchSort=create_date&count=25";
            $out[] = "<li><a href=\"$link\" target=\"_blank\">$carousel_type</a></li>";
        }

        $out[] = "</ul>";

        echo implode("\n", $out);
    } else { // no $shortname
        echo sprintf(
            '<h3>No Sitka Carousels shortname set for this site.</h3>
            <p>Set a shortname <a href="%s">here</a> to allow carousel runs.</p>',
            network_admin_url('sites.php?page=sitka-libraries')
        );
    }
}

/*
 * Callback function for site activation - checks for network activation
 */
function sitka_carousels_activate($network_wide)
{
    if (is_multisite() && $network_wide) {
        $blogs = get_sites([
            'public' => 1,
            'archived' => 0,
            'deleted' => 0,
        ]);

        foreach ($blogs as $blog) {
            switch_to_blog($blog->blog_id);
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
function sitka_carousels_install()
{
    global $wpdb;
    global $sitka_carousels_db_version;

    // Name of the database table used by this plugin
    $table_name = $wpdb->prefix . 'sitka_carousels';

    // Check to see if the table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        // Table doesn't exist so create it
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE `$table_name` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `carousel_type` ENUM(
                'adult_fiction',
                'adult_nonfiction',
                'adult_largeprint',
                'adult_dvds',
                'adult_music',
                'teen_fiction',
                'teen_nonfiction',
                'juvenile_fiction',
                'juvenile_nonfiction',
                'juvenile_dvdscds'
            ) NULL,
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
            INDEX bibkey_index (`bibkey`)
        );";

        dbDelta($sql);

        add_option('sitka_carousels_db_version', $sitka_carousels_db_version);
    }
}


/*
 * Callback function for when a new blog is added to a network install
 */
function sitka_carousels_new_blog($blog_id, $user_id, $domain, $path, $site_id, $meta)
{
    if (is_plugin_active_for_network('coop-sitka-carousels/coop-sitka-carousels.php')) {
        switch_to_blog($blog_id);
        sitka_carousels_install();
        restore_current_blog();
    }
}
add_action('wpmu_new_blog', 'sitka_carousels_new_blog', 10, 6);


/*
 * Callback function for generating sitka_carousel shortag
 */
function sitka_carousels_shortcode($attr)
{
    global $wpdb;

    // Variable created to ensure each carousel on a page has a unique class, variable is static so that it
    // is available across multiple calls to this function
    static $carousel_class = [];
    $carousel_class[] = 'sitka-carousel-' . count($carousel_class);

    // Set carousel type
    $type = (!empty($attr['type']) && in_array($attr['type'], CAROUSEL_TYPE)) ? $attr['type'] : CAROUSEL_TYPE[0];

    // Set transition type
    $transition = (!empty($attr['transition']) && in_array($attr['transition'], CAROUSEL_TRANSITION)) ?
        $attr['transition'] : CAROUSEL_TRANSITION[0];

    $table_name = "{$wpdb->prefix}sitka_carousels";

    // Get the number of new items in the last month, will be used to decide whether to show items from last month or
    // up to CAROUSEL_MIN items, which would include items older than 1 month
    $new_items = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*)
            FROM $table_name
            WHERE carousel_type = %s
            AND date_active > (NOW() - INTERVAL 1 MONTH)",
            $type
        )
    );

    if ($new_items >= CAROUSEL_MIN) {
        // Use items added within last month
        $sql = $wpdb->prepare("SELECT bibkey AS bibkey,
                                  catalogue_url AS catalogue_url,
                                  title AS title,
                                  author AS author,
                                  description AS description
                             FROM $table_name
                            WHERE carousel_type = %s
                              AND date_active > (NOW() - INTERVAL 1 MONTH)
                         ORDER BY date_active DESC", $type);
    } else {
        // Use most recent CAROUSEL_MIN items
        $sql = $wpdb->prepare("SELECT bibkey AS bibkey,
                                  catalogue_url AS catalogue_url,
                                  title AS title,
                                  author AS author,
                                  description AS description
                             FROM $table_name
                            WHERE carousel_type = %s
                              AND date_active IS NOT NULL
                         ORDER BY date_active DESC
                            LIMIT %d", [$type, CAROUSEL_MIN]);
    }

    $results = $wpdb->get_results($sql, ARRAY_A);

    // Get the library's catalogue link
    $current_domain = $GLOBALS['current_blog']->domain;
    // Assume that our main/network blog will always have the subdomain 'libpress'
    $network_domain = preg_replace('/^libpress\./', '', $GLOBALS['current_site']->domain);

    if (!empty(get_option('_coop_sitka_lib_cat_link'))) {
        $catalogue_prefix = 'https://' . trim(get_option('_coop_sitka_lib_cat_link')) . CAROUSEL_CATALOGUE_SUFFIX;
    } elseif (count(explode('.', $current_domain)) >= 4 && strpos($current_domain, $network_domain) !== false) {
        $catalogue_prefix = 'https://' . str_replace('.' . $network_domain, '', $current_domain)
            . CAROUSEL_CATALOGUE_SUFFIX;
    }

    $tag_html = "<div class='sitka-carousel-container'><div class='" . end($carousel_class)  . "' >";

    foreach ($results as $row) {
        // Check if the catalogue link is stored with or without the prefix and prepend it if not
        $catalogue_url = $row['catalogue_url'];

        if (!(strpos($catalogue_url, 'http') === 0)) {
            $catalogue_url = $catalogue_prefix . $catalogue_url;
        }

        // Build cover URL here so we can change size in the future if needed
        $cover_url = CAROUSEL_EG_URL . 'opac/extras/ac/jacket/medium/r/' . $row['bibkey'];

        // Build the HTML to return for the short tag
        $tag_html .= "<div class='sikta-item'>" .
            "<a href='" . $catalogue_url . "'>" .
            "<img alt='' src='" . $cover_url . "' class='sitka-carousel-image'>" .
            "<img alt='' src='" . plugins_url('img/nocover.jpg', __FILE__)
                . "' class='sitka-carousel-image sitka-carousel-image-default'>" .
            "<div class='sitka-info'>" .
            "<span class='sitka-title'>" . $row['title'] . "</span><br />" .
            "<span class='sitka-author'>" . $row['author'] . "</span>" .
            "</div>" .
            "</a>" .
            "<div class='sitka-description'>" . $row['description'] . "</div>" .
            "</div>";
    } // foreach

    $tag_html .= "</div></div>";

    $tag_html .= "<script>
    jQuery(document).ready(function(){
      jQuery('." . end($carousel_class) . "').slick({
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
}


/*
 * Custom Meta Box on Highlights admin page to provide instructions on how to add
 * a Sitka Carousel shortcode.
 */
function sitka_carousels_inner_custom_box($post)
{
    $out = '<p>Sitka Carousels can be added to this Highlight by inserting one or more shortcodes ' .
        'into the Highlight text. Shortcodes take the following format:' .
        '<br /> <strong>[sitka_carousel type="adult_fiction" transition="fade"]</strong></p>' .
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
function coop_sitka_carousels_sitka_libraries_page()
{
    if (! is_super_admin()) {
        // User is not a network admin
        wp_die('Sorry, you do not have permission to access this page');
    }

    // Get all active public blogs
    $blogs = get_sites([
        'public' => 1,
        'archived' => 0,
        'deleted' => 0,
    ]);

    $out = '<div class="wrap">' .
        '  <div id="icon-options-general" class="icon32">' .
        '  <br>' .
        '</div>' .

        '<h2>Sitka Libraries</h2>' .
        '<p>&nbsp;</p>' .
        '<form method="post" action="' . admin_url('admin-post.php') . '">' .

        '<table class="sitka-lists-admin-table">' .
        '  <tr><th>WP site id</th><th>Domain name</th><th>Sitka Shortcode</th><th>Sitka Locale</th>' .
            '<th>Catalogue domain</th></tr>';

    // Loop through each blog lookup options and output form
    foreach ($blogs as $blog) {
        switch_to_blog($blog->blog_id);

        $lib_shortcode = get_option('_coop_sitka_lib_shortname', '');

        // If no value for locg exists, set it to 1 (parent container for Sitka)
        $lib_locg = get_option('_coop_sitka_lib_locg', 1);

        // Must be blank by default. Same shortname stem used when it agrees with Sitka catalogue subdomain.
        // Blogs with custom domains are the only ones targeted here.
        $lib_cat_link = get_option('_coop_sitka_lib_cat_link', '');

        // Output form
        $out .= sprintf(
            '<tr>' .
            '<td>%d</td><td>%s</td>' .
            '<td><input type="text" name="shortcode_%d" class="shortcode widefat" value="%s"></td>' .
            '<td><input type="text" name="locg_%d" class="shortcode widefat" value="%d"></td>' .
            '<td><input type="text" name="cat_link_%d" class=shortcode widefat" value="%s"></td>' .
            '</tr>',
            $blog->blog_id,
            $blog->domain,
            $blog->blog_id,
            $lib_shortcode,
            $blog->blog_id,
            $lib_locg,
            $blog->blog_id,
            $lib_cat_link
        );

        // Switch back to previous blog (main network blog)
        restore_current_blog();
    }

    $out .= '<tr><td>&nbsp;</td><td></td><td></td></tr>' .
        '<tr><td><button class="button button-primary sitka-libraries-save-btn">Save changes</button>' .
        '</td><td></td></tr>' .
        '</table>' .
        wp_nonce_field('admin_post', 'coop_sitka_carousels_nonce') .
        '<input type="hidden" name="action" value="sitka_carousels">' .
        '</form>' .
        '</div><!-- .wrap -->';

    echo $out;
}

/*
 * Callback to handle the network admin form submission
 */
function coop_sitka_carousels_save_admin_callback()
{
    // Check the nonce field, if it doesn't verify report error and stop
    if (
        empty($_POST['coop_sitka_carousels_nonce'])
        || !wp_verify_nonce($_POST['coop_sitka_carousels_nonce'], 'admin_post')
    ) {
        wp_die('Sorry, there was an error handling your form submission.');
    }

    if (! is_super_admin()) {
        // User is not a network admin
        wp_die('Sorry, you do not have permission to access this page');
    }

    // Get all active public blogs
    $blogs = get_sites([
        'public' => 1,
        'archived' => 0,
        'deleted' => 0,
    ]);

    foreach ($blogs as $blog) {
        // Loop through each blog and update
        switch_to_blog($blog->blog_id);

        // Collect and sanitize values for this site
        $shortname = strtoupper(sanitize_text_field($_POST['shortcode_' . $blog->blog_id]));
        $locg = (int) sanitize_text_field($_POST['locg_' . $blog->blog_id]);
        $cat_link = sanitize_text_field($_POST['cat_link_' . $blog->blog_id]);

        // Note: The previous carousel plugin appears to have put NA in as a placeholder for unset shortcodes so
        //       we test for it here
        if (!empty($shortname) && $shortname !== "NA") {
            update_option('_coop_sitka_lib_shortname', $shortname);
        }

        // Sitka Locale (locg)
        if (is_numeric($locg)) {
            update_option('_coop_sitka_lib_locg', $locg);
        }

        // Catalogue Link
        if (!empty($cat_link)) {
            update_option('_coop_sitka_lib_cat_link', $cat_link);
        }

        restore_current_blog();
    }

    // Return to the form page
    wp_redirect(network_admin_url('sites.php?page=sitka-libraries'));
}
add_action('admin_post_sitka_carousels', 'coop_sitka_carousels_save_admin_callback');

function coop_sitka_carousels_control_js()
{
    $ajax_nonce = wp_create_nonce('coop-sitka-carousels-limit-run');
    $ajax_url = admin_url('admin-ajax.php');
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#controls-submit').addClass('disabled');

            // default
            $('input:radio[name=recheck_period]').on('click', function(event) {
                // reset
                $('#run-messages').html('');
                let period_checked = $('input:radio[name=recheck_period]:checked');
                console.log("PerCheckOption: " + period_checked.val());
                if ($(period_checked).val() !== undefined) { // just in case
                    $('#controls-submit').removeClass('disabled').val('Ready to run.');
                }
            });

            $('#controls-submit').on('click', function(event) {
                event.preventDefault();
                let period_checked = $('input:radio[name=recheck_period]').filter(':checked');
                console.log("PerCheckSubmit: " + period_checked.val());
                // $period_checked = $($period_checked.selector);

                let data = {
                    action: 'coop_sitka_carousels_control_callback',
                    mode: 'single',
                    recheck_period: period_checked.val(),
                    security: '<?php echo $ajax_nonce; ?>',
                };

                // Give user cue not to click again
                $('#controls-submit').addClass('disabled').val('Working...');
                // Provide status message
                $('#run-messages').html('This can take a few minutes for ' +
                    'the average library. Please wait...');

                // $('#controls-submit').removeClass('disabled').val('Ready to run.');

                $.post('<?php echo $ajax_url; ?>', data, function(response) {
                    if (response.success == true) {
                        console.log('Carousel run has been scheduled in a few ' +
                            'minutes. Check again for next cron run for results.');
                    }
                });
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'coop_sitka_carousels_control_js');

// Action callback for single or groups runs called by AJAX
function coop_sitka_carousels_control_callback()
{
    $data = $_POST;

    if (check_ajax_referer('coop-sitka-carousels-limit-run', 'security', false) == false) {
        wp_send_json_error();
    }

    $recheck_period = (int) sanitize_text_field($data['recheck_period']);

    // mode is always 'single' when triggered by this button
    $blog = get_current_blog_id();

    // Schedule the run wrapper in cron
    wp_schedule_single_event(
        time() + 60,
        'coop_sitka_carousels_trigger',
        [[$blog], $recheck_period],
        false
    );

    wp_send_json_success(null, 200);
}
add_action('wp_ajax_coop_sitka_carousels_control_callback', 'coop_sitka_carousels_control_callback');

//Custom CLI commands
function coop_sitka_carousels_register_cli_cmd()
{
    WP_CLI::add_command('sitka-carousel-runner', 'coop_sitka_carousels_limited_run_cmd');
}
add_action('cli_init', 'coop_sitka_carousels_register_cli_cmd');

/**
 * WP-CLI Command wrapper for coop_sitka_carousels_limited_run
 * @param array $args
 * @param array $assoc_args
 */
function coop_sitka_carousels_limited_run_cmd($args, $assoc_args)
{
    // Get arguments - no positional $args
    $parsed_args = wp_parse_args(
        $assoc_args,
        [
            'targets' => [],
            'period' => 1,
            'skip-search' => false,
        ]
    );

    // Explode into array, no double quotes.
    if (!empty($parsed_args['targets'])) {
        $parsed_args['targets'] = explode(',', str_replace('"', "", $parsed_args['targets']));
    } else {
        $parsed_args['targets'] = [];
    }

    WP_CLI::debug("ARGS " . print_r($parsed_args, true));

    try {
        $CarouselRunner = coop_sitka_carousels_limited_run($parsed_args['targets'], $parsed_args['period'], $parsed_args['skip-search']);

        if ($newItems = $CarouselRunner->getNewListItems()) {
            WP_CLI::success("The following new items were retrieved: " . json_encode($newItems, JSON_PRETTY_PRINT));
        } else {
            WP_CLI::error("Failed to populate any new items.");
        }
    } catch (\Exception $error) {
        WP_CLI::error("Failed to create CarouselRunner: {$error->getMessage()}.");
    }
}

/**
 * Wrapper for constructing a CarouselRunner
 * @param array $targets
 * @param int $period
 * @return \SitkaCarouselRunner
 */
function coop_sitka_carousels_limited_run($targets = [], $period = 1, $skip_search = false)
{
    require_once 'inc/coop-sitka-carousels-update.php';

    return new \SitkaCarouselRunner($targets, $period, $skip_search);
}
add_action('coop_sitka_carousels_trigger', 'coop_sitka_carousels_limited_run', 10, 3);
