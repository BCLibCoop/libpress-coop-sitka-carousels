<?php

defined('ABSPATH') || die(-1);

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
 * @copyright         2019-2022 BC Libraries Cooperative
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Sitka Carousels
 * Description:       New book carousel generator from Sitka/Evergreen catalogue; provides shortcode for carousels
 * Version:           1.5.4
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
        plugins_url('assets/slick/slick.min.js', __FILE__),
        ['jquery'],
        get_plugin_data(__FILE__, false, false)['Version'],
        true
    );

    // Add CSS for slick javascript library
    wp_enqueue_style(
        'coop-sitka-carousels-slick-css',
        plugins_url('assets/slick/slick.css', __FILE__),
        [],
        get_plugin_data(__FILE__, false, false)['Version']
    );
    wp_enqueue_style(
        'coop-sitka-carousels-slick-theme-css',
        plugins_url('assets/slick/slick-theme.css', __FILE__),
        [],
        get_plugin_data(__FILE__, false, false)['Version']
    );

    // Add CSS for carousel customization
    wp_enqueue_style(
        'coop-sitka-carousels-css',
        plugins_url('assets/css/coop-sitka-carousels.css', __FILE__),
        [],
        get_plugin_data(__FILE__, false, false)['Version']
    );
}
add_action('wp_enqueue_scripts', 'coop_sitka_carousels_enqueue_dependencies');

function coop_sitka_carousels_enqueue_admin_dependencies($hook)
{
    if ($hook === 'site-manager_page_sitka-carousel-controls') {
        wp_enqueue_script(
            'coop-sitka-carousels-admin-js',
            plugins_url('assets/js/admin.js', __FILE__),
            ['jquery'],
            get_plugin_data(__FILE__, false, false)['Version'],
            true
        );

        $ajax_nonce = wp_create_nonce('coop-sitka-carousels-limit-run');
        wp_localize_script('coop-sitka-carousels-admin-js', 'coop_sitka_carousels', ['nonce' => $ajax_nonce]);
    }
}
add_action('admin_enqueue_scripts', 'coop_sitka_carousels_enqueue_admin_dependencies');

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

    $run_message = '';
    $last_checked = get_option('_coop_sitka_carousels_date_last_checked');
    $shortname = get_option('_coop_sitka_lib_shortname');
    $lib_cat_url = get_option('_coop_sitka_lib_cat_link');

    if ($transient = get_transient('_coop_sitka_carousels_new_items_by_list')) {
        $run_message = "<br />The following new items were retrieved last run:<br /><pre>" . json_encode($transient, JSON_PRETTY_PRINT) . "</pre>";
    }

    $option_last_checked = get_option(
        '_coop_sitka_carousels_date_last_checked',
        date('Y-m-d', mktime(0, 0, 0, date('m') - 4, date('d'), date('Y')))
    );

    try {
        $date = date_create($option_last_checked);
        $date->sub(new DateInterval('P1M'));
        $date_checked = $date->format('Y-m-d');
    } catch (Exception $e) {
        // error_log("Something went wrong with date rechecking: " . $e->getMessage());
        $date_checked = '';
    }

    $opensearch_url = CAROUSEL_EG_URL;
    $cat_suffix = array_filter(explode('.', $lib_cat_url));
    $cat_suffix = end($cat_suffix);

    if (!empty($cat_suffix) && !in_array($cat_suffix, CAROUSEL_PROD_LIBS)) {
        $opensearch_url = 'https://' . $cat_suffix . CAROUSEL_CATALOGUE_SUFFIX;
    }

    include dirname(__FILE__) . '/inc/views/admin.php';
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
function sitka_carousels_shortcode($attr = [])
{
    // Variable created to ensure each carousel on a page has a unique class, variable is static so that it
    // is available across multiple calls to this function
    static $carousel_class = [];

    $carousel_class[] = 'sitka-carousel-' . count($carousel_class);

    // Set transition type
    $transition = (!empty($attr['transition']) && in_array($attr['transition'], CAROUSEL_TRANSITION)) ?
        $attr['transition'] : CAROUSEL_TRANSITION[0];

    // Get the library's catalogue link
    $current_domain = $GLOBALS['current_blog']->domain;
    // Assume that our main/network blog will always have the subdomain 'libpress'
    $network_domain = preg_replace('/^libpress\./', '', $GLOBALS['current_site']->domain);

    $lib_locg = get_option('_coop_sitka_lib_locg', 1);

    if (!empty(get_option('_coop_sitka_lib_cat_link'))) {
        $catalogue_prefix = 'https://' . trim(get_option('_coop_sitka_lib_cat_link')) . CAROUSEL_CATALOGUE_SUFFIX;
    } elseif (count(explode('.', $current_domain)) >= 4 && strpos($current_domain, $network_domain) !== false) {
        $catalogue_prefix = 'https://' . str_replace('.' . $network_domain, '', $current_domain)
            . CAROUSEL_CATALOGUE_SUFFIX;
    }

    $no_cover = plugins_url('assets/img/nocover.jpg', __FILE__);

    // In the future, this will look for a "carousel ID" type attribute in the
    // shortcode and switch retrieval methods
    if (true) {
        $results = sitka_database_carousel($attr);
    } else {
        $results = sitka_osrf_carousel($attr);
    }

    ob_start();

    include dirname(__FILE__) . '/inc/views/shortcode.php';

    return ob_get_clean();
}

/**
 * Retrieve titles from the database that have been previously searched for and
 * stored
 */
function sitka_database_carousel($attr = [])
{
    global $wpdb;

    // Set carousel type
    $type = (!empty($attr['type']) && in_array($attr['type'], CAROUSEL_TYPE)) ?
        $attr['type'] : CAROUSEL_TYPE[0];

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

    $query = "SELECT bibkey,
                    catalogue_url,
                    title,
                    author,
                    `description`
                    FROM $table_name
                    WHERE carousel_type = %s ";

    if ($new_items >= CAROUSEL_MIN) {
        // Use items added within last month
        $sql = $wpdb->prepare($query .
                             "AND date_active > (NOW() - INTERVAL 1 MONTH)
                         ORDER BY date_active DESC", $type);
    } else {
        // Use most recent CAROUSEL_MIN items
        $sql = $wpdb->prepare($query .
                             "AND date_active IS NOT NULL
                         ORDER BY date_active DESC
                            LIMIT %d", [$type, CAROUSEL_MIN]);
    }

    return $wpdb->get_results($sql, ARRAY_A);
}

/**
 * Retrieve a specific carousel ID from Sitka, caching in a transient for a
 * reasonable amount of time
 */
function sitka_osrf_carousel($attr = [])
{
    return [];
}

/*
 * Custom Meta Box on Highlights admin page to provide instructions on how to add
 * a Sitka Carousel shortcode.
 */
function sitka_carousels_inner_custom_box()
{
    include dirname(__FILE__) . '/inc/views/metabox.php';
}

// Action callback for single or groups runs called by AJAX
function coop_sitka_carousels_control_callback()
{
    if (check_ajax_referer('coop-sitka-carousels-limit-run', false, false) === false) {
        wp_send_json_error();
    }

    $recheck_period = (int) sanitize_text_field($_POST['recheck_period']);

    // mode is always 'single' when triggered by this button
    $blog = get_current_blog_id();

    // Schedule the run wrapper in cron
    $event = wp_schedule_single_event(
        time() + 60,
        'coop_sitka_carousels_trigger',
        [[$blog], $recheck_period],
        false
    );

    if ($event && !is_wp_error($event)) {
        wp_send_json_success();
    }

    wp_send_json_error();
}
add_action('wp_ajax_coop_sitka_carousels_control_callback', 'coop_sitka_carousels_control_callback');

// Custom CLI commands
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
        $CarouselRunner = coop_sitka_carousels_limited_run(
            $parsed_args['targets'],
            $parsed_args['period'],
            $parsed_args['skip-search']
        );

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
