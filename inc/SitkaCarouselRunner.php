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

namespace BCLibCoop\SitkaCarousel;

class SitkaCarouselRunner
{
    /**
     * @property array getNewListItems
     */
    private $newListItems;

    /**
     * Current library being processed
     * @var \WP_Post
     */
    private $library;

    /**
     * SitkaCarouselRunner constructor.
     * @param array $targets
     * @param int $period
     */
    public function __construct($targets = [], $period = 1, $skip_search = false)
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
            }
        }

        // Loop through each library updating its carousel data
        foreach ($libraries as $library) {
            $this->library = $library;

            echo "Starting run for Blog ID {$this->library->blog_id}.\n";

            // Switch to the current library's WP instance
            switch_to_blog($this->library->blog_id);

            // Initialize attribute containing list status
            // $this->newListItems = array_fill_keys(Constants::TYPE, []);

            // Get the library's short name - if not set, skip
            $shortname = get_option('_coop_sitka_lib_shortname');
            if ($shortname && $shortname !== 'NA') {
                $this->library->short_name = $shortname;
            } else {
                $this->library = null;
                continue;
            }

            $this->library->cat_url = get_option('_coop_sitka_lib_cat_link');
            $this->library->branches = [];

            // Retrieve this library's meta data from EG
            $lib_meta = $this->osrfHttpQuery([
                'service' => 'open-ils.actor',
                'method' => 'open-ils.actor.org_unit.retrieve_by_shortname',
                'params' => [
                    $this->library->short_name,
                ],
            ]);

            if (!$lib_meta) {
                continue;
            }

            $this->library->sitka_id = (int) $lib_meta[3];
            $this->library->branches[] = $this->library->sitka_id;

            // If this is a library system/network, collect all children
            // so that we can look for copies at all branches later
            $lib_tree = $this->osrfHttpQuery([
                'service' => 'open-ils.actor',
                'method' => 'open-ils.actor.org_tree.descendants.retrieve',
                'params' => [
                    $this->library->sitka_id,
                ],
            ]);

            if (!empty($lib_tree) && !empty($lib_tree[0])) {
                foreach ($lib_tree[0] as $lib_child) {
                    $this->library->branches[] = (int) $lib_child->__p[3];
                }
            }

            // Get the date of the last carousel update, if no update use 4 months ago
            $option_last_checked = get_option(
                '_coop_sitka_carousels_date_last_checked',
                date('Y-m-d', mktime(0, 0, 0, date('m') - 4, date('d'), date('Y')))
            );

            // Default: last month ($period == 1)
            $recheck_period = "P{$period}M";

            try {
                $date = date_create($option_last_checked);
                $date->sub(new \DateInterval($recheck_period));
                $date_checked = $date->format('Y-m-d');
            } catch (\Exception $e) {
                error_log("Something went wrong with date rechecking: " . $e->getMessage());
            }

            $new_bibkeys = [];

            if (!$skip_search) {
                // Query Evergreen for new items for each carousel type and add them to the database
                foreach (Constants::TYPE as $carousel_type) {
                    $finished = false;
                    $offset = 0;
                    $count = 50;

                    // The query may return multiple pages of results. Increment the $offset by $count
                    // and retrieve the next page until no links are found
                    while (! $finished) {
                        $carousel_results = $this->osrfHttpQuery([
                            'service' => 'open-ils.search',
                            'method' => 'open-ils.search.biblio.multiclass.query',
                            'params' => [
                                [
                                    'offset' => $offset,
                                    'limit' => $count,
                                    'searchSort' => 'create_date',
                                ],
                                'site(' . $this->library->short_name . ') create_date(' . $date_checked . ') '
                                . Constants::SEARCH[$carousel_type],
                                0
                            ],
                        ]);

                        // Get the ID keys if we got any results, else an empty array to fall through
                        $bibkeys = $carousel_results ? array_column($carousel_results->ids, 0) : [];

                        if (!empty($bibkeys)) {
                            foreach ($bibkeys as $bibkey) {
                                // Check that the bibkey doesn't already exist in the database
                                $query_results = $wpdb->get_results(
                                    $wpdb->prepare(
                                        "SELECT bibkey FROM {$wpdb->prefix}sitka_carousels WHERE bibkey = %d",
                                        $bibkey
                                    ),
                                    ARRAY_A
                                );

                                if (count($query_results) === 0) {
                                    $new_bibkeys[] = $bibkey;
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

                            // If we got all the results in one request, don't bother making another
                            if (count($bibkeys) >= $carousel_results->count) {
                                $finished = true;
                            } else {
                                // Processed these IDs, continue to the next page
                                $offset += $count;
                            }
                        } else {
                            // Error or no results, continue on
                            $finished = true;
                        }
                    }

                    // Free some memory
                    unset($carousel_results);
                } // foreach carousel_type
            } else {
                echo "skipping search for new items as requested\n";
            }

            echo "found " . count($new_bibkeys) . " NEW bibkeys to check\n";

            // All new bibkeys have been added to the db, now pull all
            // items without a date_active and check
            $inactive_items = $wpdb->get_results("SELECT bibkey, carousel_type
                                                FROM {$wpdb->prefix}sitka_carousels
                                                WHERE date_active IS NULL", ARRAY_A);

            echo "found " . count($inactive_items) . " total INACTIVE bibkeys to check\n";
            echo "search at location ids: " . implode(", ", $this->library->branches) . "\n";

            foreach ($inactive_items as $item) {
                // Query Evergreen to get the copy id and active date
                $item_copy_data = $this->osrfHttpQuery([
                    'service' => 'open-ils.cat',
                    'method' => 'open-ils.cat.asset.copy_tree.retrieve',
                    'params' => [
                        '',
                        $item['bibkey'],
                        $this->library->branches,
                    ],
                ]);

                // Drill down to the copy data if we got a response
                $copies = $item_copy_data ? $item_copy_data[0]->__p[0] : [];

                // Check all copies
                if (!empty($copies)) {
                    foreach ($copies as $copy) {
                        $copy_data = $copy->__p;

                        if (!empty($copy_data[10])) {
                            $item['copy_id'] = $copy_data[22];

                            // The active date comes in YYYY-MM-DDTHH:MM:SS-TZ, we need to convert to YYYY-MM-DD
                            $item['date_active'] = date('Y-m-d', strtotime($copy_data[10]));

                            // Break if we got an active date from this copy
                            break;
                        }
                    }

                    // The current bibkey has a date_active, gather the necessary info
                    if (!empty($item['date_active'])) {
                        // With the copy_id we can now query EG for the remaining info
                        $item_data = $this->osrfHttpQuery([
                            'service' => 'open-ils.search',
                            'method' => 'open-ils.search.biblio.mods_from_copy',
                            'params' => [
                                $item['copy_id'],
                            ],
                        ]);

                        if (!empty($item_data)) {
                            $item['title'] = $item_data[0];
                            $item['author'] = $item_data[1];
                            $item['description'] = $item_data[13];

                            // Update the database to add our new information
                            $wpdb->update(
                                $wpdb->prefix . 'sitka_carousels',
                                [
                                    'date_active' => $item['date_active'],
                                    'title' => $item['title'],
                                    'author' => $item['author'],
                                    'description' => $item['description'],
                                ],
                                ['bibkey' => $item['bibkey']],
                                ['%s', '%s', '%s', '%s', '%s'],
                                ['%d']
                            );

                            // Save relevant metadata for user list
                            $this->newListItems[$this->library->blog_id][$item['carousel_type']][] = [
                                'bibkey' => $item['bibkey'],
                                'title' => $item['title'],
                                'date_active' => $item['date_active'],
                            ];
                        }

                        // Free some memory
                        unset($item_data);
                    } else {
                        echo "no active date for bibkey {$item['bibkey']}\n";
                    }
                } else {
                    echo "no copy data for bibkey {$item['bibkey']}\n";
                }

                // Free some memory
                unset($item_copy_data);
            } // for each $inactive_item (bibkey without a date_active)

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
            echo "Completed run for Blog ID {$this->library->blog_id}.\n";
            $this->library = null;
            restore_current_blog();
        }
    }

    public function getNewListItems()
    {
        return $this->newListItems;
    }

    /**
     * Helper function to generate the WP_Http::request() args for an OSRF
     * request
     *
     * @param array $query_data
     * @return array
     */
    private function osrfHttpQueryBuilder($request_data)
    {
        $request = [
            'timeout' => Constants::QUERY_TIMEOUT,
            'headers' => ['X-OpenSRF-service' => $request_data['service']],
        ];

        $osrf_msg = [];

        $osrf_msg[] = [
            '__c' => 'osrfMessage',
            '__p' => [
                'threadTrace' => '0',
                'locale' => 'en-US',
                'type' => 'REQUEST',
                'payload' => [
                    '__c' => 'osrfMethod',
                    '__p' => [
                        'method' => $request_data['method'],
                        'params' => $request_data['params'],
                    ],
                ],
            ],
        ];

        $request['body'] = 'osrf-msg=' . json_encode($osrf_msg);

        return $request;
    }

    private function osrfHttpQuery($request_data)
    {
        // Build the request
        $query = $this->osrfHttpQueryBuilder($request_data);

        $catalogue_url = Constants::EG_URL;

        $cat_suffix = array_filter(explode('.', $this->library->cat_url));
        $cat_suffix = end($cat_suffix);

        if (!empty($cat_suffix) && !in_array($cat_suffix, Constants::PROD_LIBS)) {
            $catalogue_url = 'https://' . $cat_suffix . Constants::CATALOGUE_SUFFIX;
        }

        // Post to the translator service
        $eg_query_result = wp_remote_post(
            $catalogue_url . '/osrf-http-translator',
            $query
        );

        // If the request completely errored, return null
        if (wp_remote_retrieve_response_code($eg_query_result) !== 200) {
            return null;
        }

        /**
         * Do some best-effort checking of the returned response. The translator
         * service seems to not be great about returning error status codes when then
         * are no results or otherwise an error, and the nexting level of the data we
         * want is inconsistent, so we do our best to check for errors and return data
         * at a soemwhat useful point
         */
        if ($json_result = json_decode(wp_remote_retrieve_body($eg_query_result))) {
            // Check for status message
            foreach ($json_result as $osrf_message) {
                if (isset($osrf_message->__p) && $osrf_message->__p->type === 'STATUS') {
                    $status_code = $osrf_message->__p->payload->__p->statusCode;

                    // If the internal status code isn't in the 1xx or 2xx range, return null
                    if ($status_code >= 300) {
                        return null;
                    }

                    break;
                }
            }

            // Check for results message
            foreach ($json_result as $osrf_message) {
                if (isset($osrf_message->__p) && $osrf_message->__p->type === 'RESULT') {
                    $content = $osrf_message->__p->payload->__p->content;

                    // Status codes don't seem to indicate a true bad response,
                    // stacktrace seems to be a somewhat reliable way of finding an error
                    if (isset($content->stacktrace)) {
                        return null;
                    }

                    // Sometimes nested one more level, sometimes not.
                    return $content->__p ?? $content;
                }
            }
        }
    }
}
