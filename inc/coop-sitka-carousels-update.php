<?php

/*
 * Script to be run via cron with `wp eval-script`
 * wp --url=libpress.libraries.coop --path=/path/to/wp/web/root eval-file /path/to/coop-sitka-carousels-update.php
 *
 * Query Evergreen and add any items that have been added since the
 * last time the script was run.  Items are added to the sitka_carousels
 * database table.
 *
 * The first time the script is run it queries Evergreen for any items
 * created within the last 4 months.  This is done to ensure that there
 * will be enough items for each carousel type.
 *
 * Evergreen allows us to search on items based on their 'create_date'
 * however the date of interest to us is the item's 'active_date', which we
 * cannot search on. This requires a two step process where we first search
 * on create_date and then again on the particular copy to get its active_date.
 * It is possible that an item may be created, but not active so when
 * displaying carousels only items with an active_date will be shown. Any items
 * without an active_date will not be queried, including ones previously added
 * to the db.
 */

if (!class_exists('SitkaCarouselRunner')) :
    class SitkaCarouselRunner
    {
        /**
         * @property array getNewListItems
         */
        private $newListItems;

        /**
         * SitkaCarouselRunner constructor.
         * @param array $targets
         * @param int $period
         */
        public function __construct($targets = [], $period = 1)
        {
            global $wpdb;

            // Initialize default mode switch
            $sweep = true; // TRUE: sweep/all, FALSE: single or subset

            if (count($targets) >= 1) {
                $sweep = false;
            }

            // Variable for populated targets
            $libraries = [];

            if ($sweep) {
                print("Starting check for new items (network-wide)...");

                // Yesterday's date - We use yesterday's date so that in the off-chance a new
                // item gets added during the update process it will be captured in the next update
                $date_checked = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')));

                // Get all of the active libraries
                $libraries = get_sites([
                    'public' => 1,
                    'archived' => 0,
                    'deleted' => 0,
                ]);
            } else { // Single or small group triggered by ajax
                // Load the IDs as full WP_Site objects
                foreach ($targets as $target) {
                    $libraries[] = get_blog_details($target);
                    // var_dump($libraries);
                }
            }

            // Loop through each library updating its carousel data
            foreach ($libraries as $library) {
                // Switch to the current library's WP instance
                switch_to_blog($library->blog_id);

                // Initialize attribute containing list status
                // $this->newListItems = array_fill_keys(CAROUSEL_TYPE, []);

                // Get the library's short name - if not set, skip
                if (get_option('_coop_sitka_lib_shortname')) {
                    $library->short_name = get_option('_coop_sitka_lib_shortname');
                } else {
                    continue;
                }

                // Get the library's catalogue link
                if (get_option('_coop_sitka_lib_cat_link') != '') {
                    $library->catalogue_link = 'https://' . get_option('_coop_sitka_lib_cat_link') . CAROUSEL_CATALOGUE_SUFFIX;
                } else {
                    $library->catalogue_link = 'https://' . preg_filter(CAROUSEL_DOMAIN_SUFFIX, '', $library->domain) . CAROUSEL_CATALOGUE_SUFFIX;
                }

                // Retrieve this library's meta data from EG
                $eg_query_result = wp_remote_post(
                    'https://catalogue.libraries.coop/osrf-http-translator',
                    [
                        'headers' => 'X-OpenSRF-service:open-ils.actor',
                        'body' => 'osrf-msg=[{"__c":"osrfMessage","__p":{"threadTrace":"0","locale":"en-US","type":"REQUEST","payload":{"__c":"osrfMethod","__p":{'
                            . '"method":"open-ils.actor.org_unit.retrieve_by_shortname","params":["' . $library->short_name . '"]}}}}]',
                        'timeout' => CAROUSEL_QUERY_TIMEOUT,
                    ]
                );

                $lib_meta = json_decode(wp_remote_retrieve_body($eg_query_result))[0]->__p->payload->__p->content->__p;

                $library->locg = $lib_meta[3];
                $library->parent_locg = $lib_meta[8];

                // Get the date of the last carousel update, if no update use 4 months ago
                $option_last_checked = get_option(
                    '_coop_sitka_carousels_date_last_checked',
                    date('Y-m-d', mktime(0, 0, 0, date('m') - 4, date('d'), date('Y')))
                );

                // Default: last month ($period == 1)
                $recheck_period = "P{$period}M";

                try {
                    $date = date_create($option_last_checked);
                    $date->sub(new DateInterval($recheck_period));
                    $date_checked = $date->format('Y-m-d');
                } catch (Exception $e) {
                    error_log("Something went wrong with date rechecking: " . $e->getMessage());
                }

                // Query Evergreen for new items for each carousel type and add them to
                // the database
                foreach (CAROUSEL_TYPE as $carousel_type) {
                    $finished = false;
                    $index = 1;
                    $count = 25; // 25 is the max number of results Evergreen will return

                    // The query may return multiple pages of results. Increment the $index by $count
                    // and retrieve the next page until no links are found
                    while (!$finished) {
                        $eg_query_result = wp_remote_get(
                            CAROUSEL_EG_URL . 'opac/extras/opensearch/1.1/' . $library->short_name . '/html-full/?searchTerms='
                                . CAROUSEL_SEARCH[$carousel_type] . '%20create_date('
                                . $date_checked . ')&searchSort=create_date&startIndex=' . $index . '&count=' . $count,
                            ['timeout' => CAROUSEL_QUERY_TIMEOUT]
                        );
                        $result_html = wp_remote_retrieve_body($eg_query_result);

                        libxml_use_internal_errors(true);
                        $doc = new DOMDocument();
                        $doc->loadHTML($result_html);
                        $xpath = new DOMXpath($doc);

                        // Get the links to each returned item from the HTML page
                        $link_urls = $xpath->query('//html/body/dl/dt/a/@href');

                        if ($link_urls->length > 0) {
                            foreach ($link_urls as $link_url) {
                                $link_parts = explode('/', $link_url->nodeValue);

                                // Test to make sure the link URL includes the expected text
                                if (preg_match('/biblio-record_entry/', $link_parts[5])) {
                                    $bibkey = $link_parts[6];

                                    // Check that the bibkey doesn't already exist in the database
                                    $query_results = $wpdb->get_results($wpdb->prepare("SELECT bibkey AS bibkey
                                                                         FROM " . $wpdb->prefix . "sitka_carousels
                                                                        WHERE bibkey = %d", $bibkey), ARRAY_A);

                                    if (count($query_results) == 0) {
                                        $wpdb->insert(
                                            $wpdb->prefix . 'sitka_carousels',
                                            [
                                                'carousel_type' => $carousel_type,
                                                'date_created' => $date_checked,
                                                'bibkey' => $bibkey,
                                            ]
                                        );
                                    }
                                }
                            }

                            // All links on this page processed, update index and then get next page
                            $index = $index + $count;
                        } else {
                            // No link URLs found, have gone through all result pages
                            $finished = true;
                        }
                    }

                    // All new bibkeys have been added to the db, now pull all
                    // items without a date_active and check
                    $inactive_items = $wpdb->get_results($wpdb->prepare("SELECT id AS id,
                                                                       bibkey AS bibkey
                                                                  FROM " . $wpdb->prefix . "sitka_carousels
                                                                 WHERE carousel_type = %s
                                                                   AND date_active IS NULL", $carousel_type), ARRAY_A);

                    foreach ($inactive_items as $item) {
                        // Query Evergreen to get the copy id and active date
                        $eg_query_result = wp_remote_post(
                            CAROUSEL_EG_URL . 'osrf-http-translator',
                            [
                                'headers' => 'X-OpenSRF-service:open-ils.cat',
                                'body' => 'osrf-msg=[{"__c":"osrfMessage","__p":{"threadTrace":"0","locale":"en-US","type":"REQUEST","payload":{"__c":"osrfMethod","__p":{"method":"open-ils.cat.asset.copy_tree.retrieve","params":["", "' . $item['bibkey'] . '","' . $library->locg . '"]}}}}]',
                                'timeout' => CAROUSEL_QUERY_TIMEOUT,
                            ]
                        );

                        $item_copy_data = json_decode(wp_remote_retrieve_body($eg_query_result))[0]->__p->payload->__p->content[0]->__p[0][0]->__p;

                        if (isset($item_copy_data[10])) {
                            // The current bibkey has a date_active, gather the necessary info

                            // The active date comes in YYYY-MM-DDTHH:MM:SS-TZ, we need to convert to YYYY-MM-DD
                            $item_copy_date_parts = explode('T', $item_copy_data[10]);
                            $item['date_active'] = $item_copy_date_parts[0];

                            $item['copy_id'] = $item_copy_data[22];

                            // With the copy_id we can now query EG for the remaining info
                            $eg_query_result = wp_remote_post(
                                CAROUSEL_EG_URL . 'osrf-http-translator',
                                [
                                    'headers' => 'X-OpenSRF-service:open-ils.search',
                                    'body' => 'osrf-msg=[{"__c":"osrfMessage","__p":{"threadTrace":"0","locale":"en-US","type":"REQUEST","payload":{"__c":"osrfMethod","__p":{"method":"open-ils.search.biblio.mods_from_copy","params":["' . $item['copy_id'] . '"]}}}}]',
                                    'timeout' => CAROUSEL_QUERY_TIMEOUT,
                                ]
                            );
                            $item_copy_data = json_decode(wp_remote_retrieve_body($eg_query_result))[0]->__p->payload->__p->content->__p;

                            $item['title'] = $item_copy_data[0];
                            $item['author'] = $item_copy_data[1];
                            $item['description'] = $item_copy_data[13];
                            $item['catalogue_url'] = $library->catalogue_link . '/eg/opac/record/' . $item['bibkey'] . '?locg=' . $library->locg;
                            $item['cover_url'] = CAROUSEL_EG_URL . 'opac/extras/ac/jacket/medium/r/' . $item['bibkey'];

                            // Update the database to add our new information
                            $wpdb->update(
                                $wpdb->prefix . 'sitka_carousels',
                                [
                                    'date_active' => $item['date_active'],
                                    'catalogue_url' => $item['catalogue_url'],
                                    'cover_url' => $item['cover_url'],
                                    'title' => $item['title'],
                                    'author' => $item['author'],
                                    'description' => $item['description'],
                                ],
                                ['bibkey' => $item['bibkey']],
                                ['%s', '%s', '%s', '%s', '%s', '%s'],
                                ['%d']
                            );

                            // Save relevant metadata for user list
                            $this->newListItems[$library->blog_id][$carousel_type][] =
                                [
                                    'bibkey' => $item['bibkey'],
                                    'title' => $item['title'],
                                    'date_active' => $item['date_active'],
                                    'catalogue_url' => $item['catalogue_url'],
                                ];
                            // if isset($item_copy_data[10] (date_active)
                        } else {
                            // Remove from table or keep in case item starts circulating?
                        }
                    } // for each $inactive_item (bibkey without a date_active)
                } // foreach carousel_type

                // Set this library's _coop_sitka_carousels_date_last_checked to yesterday's date
                // This is done in the off chance a new item has been added while we have been updating the carousel
                update_option(
                    '_coop_sitka_carousels_date_last_checked',
                    date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')))
                );

                // Set a transient for half-hour to update the admin page with results
                set_transient(
                    '_coop_sitka_carousels_new_items_by_list',
                    $this->newListItems,
                    1800
                );

                // Set current blog back to the previous one, which is our main network blog
                print "Completed run for Blog ID {$library->blog_id}.\r\n";
                restore_current_blog();
            }
        }

        public static function getNewListItems()
        {
            return get_transient('_coop_sitka_carousels_new_items_by_list');
        }
    }
endif;
