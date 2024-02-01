<?php

namespace BCLibCoop\SitkaCarousel;

use BCLibCoop\CoopHighlights\CoopHighlights;

use function TenUp\AsyncTransients\get_async_transient;
use function TenUp\AsyncTransients\set_async_transient;

class SitkaCarousel
{
    /**
     * Transient key used to cache response
     */
    private $transient_key = 'sitka_carousel';

    /**
     * Initialize the plugin
     */
    public function __construct()
    {
        // Register sitka_carousel shortcode
        add_shortcode('sitka_carousel', [$this, 'shortcode']);

        add_action('wp_enqueue_scripts', [$this, 'frontendDeps']);
        add_action('add_meta_boxes', [$this, 'metabox'], 10, 2);
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function frontendDeps()
    {
        global $post;

        $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

        /**
         * All Coop plugins will include their own copy of flickity, but
         * only the first one actually enqued should be needed/registered.
         * Assuming we keep versions in sync, this shouldn't be an issue.
         */

        if (
            (!empty($post) && has_shortcode($post->post_content, 'sitka_carousel'))
            || (is_front_page() && has_shortcode(CoopHighlights::allHighlightsContent(), 'sitka_carousel'))
        ) {
            /* flickity */
            wp_enqueue_script(
                'flickity',
                plugins_url('/assets/js/flickity.pkgd' . $suffix . '.js', COOP_SITKA_CAROUSEL_PLUGINFILE),
                [
                    'jquery',
                ],
                '2.3.0-accessible',
                true
            );

            wp_enqueue_script(
                'flickity-fade',
                plugins_url('/assets/js/flickity-fade.js', COOP_SITKA_CAROUSEL_PLUGINFILE),
                [
                    'flickity',
                ],
                '1.0.0',
                true
            );

            wp_enqueue_style(
                'flickity',
                plugins_url('/assets/css/flickity' . $suffix . '.css', COOP_SITKA_CAROUSEL_PLUGINFILE),
                [],
                '2.3.0-accessible'
            );

            if (empty($GLOBALS['flickity_fade_enqueued'])) {
                wp_add_inline_style(
                    'flickity',
                    file_get_contents(dirname(COOP_SLIDESHOW_PLUGIN) . '/assets/css/flickity-fade.css')
                );
                $GLOBALS['flickity_fade_enqueued'] = true;
            }

            // Add CSS for carousel customization
            wp_add_inline_style(
                'flickity',
                file_get_contents(dirname(COOP_SITKA_CAROUSEL_PLUGINFILE) . '/assets/css/coop-sitka-carousels.css')
            );
        }
    }

    /**
     * Register add_meta_box to provide instructions on how to add a carousel
     * to a Highlight post
     */
    public function metabox()
    {
        add_meta_box(
            'coop_sitka_carousels',
            'Sitka Carousel Placement',
            [$this, 'showMetabox'],
            'highlight',
            'side',
            'high'
        );
    }

    /**
     * Get the proper catalogue endpoint for the current library
     */
    public static function getCatalogueUrl()
    {
        $catalogue_url = Constants::EG_URL;

        $lib_cat_url = get_option('_coop_sitka_lib_cat_link');
        $cat_suffix = array_filter(explode('.', $lib_cat_url));
        $cat_suffix = end($cat_suffix);

        if (!empty($cat_suffix) && !in_array($cat_suffix, Constants::PROD_LIBS)) {
            $catalogue_url = 'https://' . $cat_suffix . Constants::CATALOGUE_SUFFIX;
        }

        return $catalogue_url;
    }

    /**
     * Callback function for generating sitka_carousel shortag
     */
    public function shortcode($attr = [])
    {
        $attr = shortcode_atts(
            [
                'transition' => Constants::TRANSITION[0],
                'type' => Constants::TYPE[0],
                'carousel_id' => false,
            ],
            array_change_key_case((array) $attr, CASE_LOWER),
            'sitka_carousel'
        );

        // Validate atts
        $attr = filter_var_array((array) $attr, [
            'transition' => [
                'filter' => FILTER_VALIDATE_REGEXP,
                'options' => [
                    'default' => Constants::TRANSITION[0],
                    'flags'   => FILTER_REQUIRE_SCALAR,
                    'regexp' => '/^(' . implode('|', Constants::TRANSITION) . ')$/'
                ],
            ],
            'type' => [
                'filter' => FILTER_VALIDATE_REGEXP,
                'options' => [
                    'default' => Constants::TYPE[0],
                    'flags'   => FILTER_REQUIRE_SCALAR,
                    'regexp' => '/^(' . implode('|', Constants::TYPE) . ')$/'
                ],
            ],
            'carousel_id' => FILTER_VALIDATE_INT,
        ]);

        // Not allowing these to be set from the shortcode for now
        $attr['size'] = 'medium';
        $attr['no_cover'] = plugins_url('assets/img/nocover.jpg', COOP_SITKA_CAROUSEL_PLUGINFILE);

        // Get the library's catalogue link
        $current_domain = $GLOBALS['current_blog']->domain;
        // Assume that our main/network blog will always have the subdomain 'libpress'
        $network_domain = preg_replace('/^libpress\./', '', $GLOBALS['current_site']->domain);

        $lib_locg = get_option('_coop_sitka_lib_locg', 1);
        $cat_link = trim(get_option('_coop_sitka_lib_cat_link'));

        $catalogue_prefix = Constants::EG_URL;

        if (!empty($cat_link)) {
            $catalogue_prefix = 'https://' . $cat_link . Constants::CATALOGUE_SUFFIX;
        } elseif (count(explode('.', $current_domain)) >= 4 && strpos($current_domain, $network_domain) !== false) {
            $catalogue_prefix = 'https://' . str_replace('.' . $network_domain, '', $current_domain)
                . Constants::CATALOGUE_SUFFIX;
        }

        // If we have a carousel ID, make the call to Sitka, otherwise, use info
        // from the local DB
        $results = empty($attr['carousel_id']) ? $this->getFromDb($attr) : $this->getFromOSRF($attr);

        /**
         * Prep some data for output, format URLs, etc
         */
        foreach ($results as &$row_format) {
            // Build catalogue URL
            $row_format['catalogue_url'] = sprintf(
                "%s/eg/opac/record/%d?locg=%d",
                $catalogue_prefix,
                $row_format['bibkey'],
                $lib_locg,
            );

            // Build cover URL here so we can change size in the future if needed
            $row_format['cover_url'] = sprintf(
                '%s/opac/extras/ac/jacket/%s/r/%d',
                $catalogue_prefix,
                $attr['size'],
                $row_format['bibkey']
            );

            // No crazy-long titles
            $row_format['title'] = wp_trim_words($row_format['title'], 6);
        }

        $flickity_options = [
            'autoPlay' => 4000,
            'wrapAround' => true,
            'pageDots' => false,
            'fade' => ($attr['transition'] !== 'swipe'),
        ];
        $flickity_options = json_encode($flickity_options);

        ob_start();

        include dirname(COOP_SITKA_CAROUSEL_PLUGINFILE) . '/inc/views/shortcode.php';

        return ob_get_clean();
    }

    /**
     * Retrieve titles from the database that have been previously searched for and
     * stored
     */
    public function getFromDb($attr)
    {
        global $wpdb;

        $table_name = "{$wpdb->prefix}sitka_carousels";

        /**
         * Since we're no longer checking/updating/creating the database tables,
         * ignore any error
         */
        $wpdb->suppress_errors(true);

        // Get the number of new items in the last month, will be used to decide whether to show items from last month
        // or up to Constants::MIN items, which would include items older than 1 month
        $new_items = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM $table_name
                WHERE carousel_type = %s
                AND date_active > (NOW() - INTERVAL 1 MONTH)",
                $attr['type']
            )
        ) ?? 0;

        $query = "SELECT bibkey,
                        title,
                        author
                    FROM $table_name
                    WHERE carousel_type = %s ";

        if ($new_items >= Constants::MIN) {
            // Use items added within last month
            $query .= "AND date_active > (NOW() - INTERVAL 1 MONTH)
                ORDER BY date_active DESC";
            $sql = $wpdb->prepare($query, $attr['type']);
        } else {
            // Use most recent Constants::MIN items
            $query .= "AND date_active IS NOT NULL
                ORDER BY date_active DESC
                LIMIT %d";
            $sql = $wpdb->prepare($query, [$attr['type'], Constants::MIN]);
        }

        $results = $wpdb->get_results($sql, ARRAY_A) ?? [];
        $wpdb->suppress_errors(false);

        return $results;
    }

    /**
     * Retrieve a specific carousel ID from Sitka, caching in a transient for a
     * reasonable amount of time
     *
     * Checks an "async transient" that will return stale data, and then return
     * to the webserver while PHP-FPM runs the update if needed
     */
    public function getFromOSRF($attr)
    {
        $catalogue_url = self::getCatalogueUrl();
        $carousel_key = md5("{$catalogue_url}_{$attr['carousel_id']}");
        $transient_key = "{$this->transient_key}_{$carousel_key}";

        $bibs = get_async_transient(
            $transient_key,
            [$this, 'realGetFromOSRF'],
            [$transient_key, $attr, $catalogue_url]
        );

        return array_filter((array) $bibs);
    }

    /**
     * The real function to return carousel data
     */
    public function realGetFromOSRF($transient_key, $attr, $catalogue_url)
    {
        // Check for a lock for the update of this carousel data and bail if set
        if (get_transient("{$transient_key}_lock")) {
            return;
        }

        // Set a lock. 5 minutes max
        set_transient("{$transient_key}_lock", true, MINUTE_IN_SECONDS * 5);

        $bibs = [];
        // Default to only persisting for 1 minute
        $transient_time = MINUTE_IN_SECONDS;

        $carousel = (new OSRFQuery([
                'service' => 'open-ils.actor',
                'method' => 'open-ils.actor.carousel.get_contents',
                'params' => [
                    $attr['carousel_id']
                ],
            ], $catalogue_url))->getResult();

        if (!empty($carousel) && !empty($carousel->bibs)) {
            foreach ($carousel->bibs as $bib) {
                $bibs[] = [
                    'bibkey' => $bib->id,
                    'title' => $bib->title ?? '',
                    'author' => $bib->author ?? '',
                ];
            }

            // If we get results, persist for 15 minutes
            $transient_time = MINUTE_IN_SECONDS * 15;
        }

        // Cache the results
        set_async_transient(
            $transient_key,
            $bibs,
            $transient_time
        );

        // Delete the lock once we have finished running
        delete_transient("{$transient_key}_lock");
    }

    /**
     * Custom Meta Box on Highlights admin page to provide instructions on how
     * to add a Sitka Carousel shortcode.
     */
    public function showMetabox()
    {
        $sitka_carousels = $this->getOrgCarousels();

        include dirname(COOP_SITKA_CAROUSEL_PLUGINFILE) . '/inc/views/metabox.php';
    }

    /**
     * Get a list of the current carousels avaliable for the site's EG org
     *
     * @return array
     */
    private function getOrgCarousels()
    {
        $sitka_carousels = [];

        $shortname = trim(get_option('_coop_sitka_lib_shortname'));

        if (!empty($shortname) && $shortname !== 'NA') {
            $catalogur_url = self::getCatalogueUrl();
            $carousel_key = md5("{$catalogur_url }_{$shortname}");
            $transient_key = "{$this->transient_key}_{$carousel_key}";

            $carousels = get_transient($transient_key);

            if ($carousels !== false) {
                $sitka_carousels = $carousels;
            } else {
                $map_transient = 'coop_sitka_shortname_map';
                $mapping = get_site_transient($map_transient) ?: [];

                if (!empty($mapping[$shortname])) {
                    $sitka_id = (int) $mapping[$shortname];
                } else {
                    // Retrieve this library's meta data from EG
                    $lib_meta = (new OSRFQuery([
                        'service' => 'open-ils.actor',
                        'method' => 'open-ils.actor.org_unit.retrieve_by_shortname',
                        'params' => [
                            $shortname,
                        ],
                    ], $catalogur_url))->getResult();

                    $sitka_id = (int) $lib_meta[3] ?? 0;
                    $mapping[$shortname] = $sitka_id;
                    set_site_transient($map_transient, $mapping, MONTH_IN_SECONDS);
                }

                if ($sitka_id > 0) {
                    $carousels = (new OSRFQuery([
                        'service' => 'open-ils.actor',
                        'method' => 'open-ils.actor.carousel.retrieve_by_org',
                        'params' => [
                            $sitka_id
                        ],
                    ], $catalogur_url))->getResult();

                    $sitka_carousels = array_map(function ($carousel) {
                        return [
                            'carousel_id' => $carousel->carousel,
                            'name' => $carousel->override_name ?? $carousel->name ?? '<em>No Name</em>',
                        ];
                    }, $carousels);

                    // Always set transient, as we still want to cache an empty result
                    set_transient($transient_key, $sitka_carousels, MINUTE_IN_SECONDS);
                }
            }
        }

        return $sitka_carousels;
    }
}
