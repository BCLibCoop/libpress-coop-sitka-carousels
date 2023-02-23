<?php

/*
 * Script to be run via cron with via wp-cli
 * wp sitka-carousel-runner --period=1
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

    private $libraries;

    private $dateChecked;

    private $skipSearch;

    private $period;

    /**
     * SitkaCarouselRunner constructor.
     * @param array $targets
     * @param int $period
     */
    public function __construct($targets = [], $period = 1, $skipSearch = false)
    {
        $this->skipSearch = $skipSearch;
        $this->period = $period;

        // Initialize attribute containing list status
        // $this->newListItems = array_fill_keys(Constants::TYPE, []);
        $this->newListItems = [];

        // Variable for populated targets
        $this->libraries = [];

        // Yesterday's date - We use yesterday's date so that in the off-chance a new
        // item gets added during the update process it will be captured in the next update
        $this->dateChecked = date('Y-m-d', mktime(0, 0, 0, date('m'), date('d') - 1, date('Y')));

        if (empty($targets)) {
            print("Starting check for new items (network-wide)...");

            // Get all of the active libraries
            $this->libraries = get_sites([
                'public' => 1,
                'archived' => 0,
                'deleted' => 0,
            ]);
        } else { // Single or small group triggered by ajax
            // Load the IDs as full WP_Site objects
            foreach ($targets as $target) {
                $this->libraries[] = get_blog_details($target);
            }
        }
    }

    public function getNewListItems()
    {
        global $wpdb;

        // Loop through each library updating its carousel data
        foreach ($this->libraries as $library) {
            $this->library = $library;

            echo "Starting run for Blog ID {$this->library->blog_id}.\n";

            // Switch to the current library's WP instance
            switch_to_blog($this->library->blog_id);

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
            $lib_meta = (new OSRFQuery([
                'service' => 'open-ils.actor',
                'method' => 'open-ils.actor.org_unit.retrieve_by_shortname',
                'params' => [
                    $this->library->short_name,
                ],
            ], $this->library->cat_url))->getResult();

            if (!$lib_meta) {
                continue;
            }

            $this->library->sitka_id = (int) $lib_meta[3];
            $this->library->branches[] = $this->library->sitka_id;

            // If this is a library system/network, collect all children
            // so that we can look for copies at all branches later
            $lib_tree = (new OSRFQuery([
                'service' => 'open-ils.actor',
                'method' => 'open-ils.actor.org_tree.descendants.retrieve',
                'params' => [
                    $this->library->sitka_id,
                ],
            ], $this->library->cat_url))->getResult();

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
            $recheck_period = "P{$this->period}M";

            try {
                $date = date_create($option_last_checked);
                $date->sub(new \DateInterval($recheck_period));
                $this->dateChecked = $date->format('Y-m-d');
            } catch (\Exception $e) {
                error_log("Something went wrong with date rechecking: " . $e->getMessage());
            }

            $new_bibkeys = [];

            if (!$this->skipSearch) {
                // Query Evergreen for new items for each carousel type and add them to the database
                foreach (Constants::TYPE as $carousel_type) {
                    $finished = false;
                    $offset = 0;
                    $count = 50;

                    // The query may return multiple pages of results. Increment the $offset by $count
                    // and retrieve the next page until no links are found
                    while (!$finished) {
                        $carousel_results = (new OSRFQuery([
                            'service' => 'open-ils.search',
                            'method' => 'open-ils.search.biblio.multiclass.query',
                            'params' => [
                                [
                                    'offset' => $offset,
                                    'limit' => $count,
                                    'searchSort' => 'create_date',
                                ],
                                'site(' . $this->library->short_name . ') create_date(' . $this->dateChecked . ') '
                                    . Constants::SEARCH[$carousel_type],
                                0
                            ],
                        ], $this->library->cat_url))->getResult();

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
                                            'date_created' => $this->dateChecked,
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
                $item_copy_data = (new OSRFQuery([
                    'service' => 'open-ils.cat',
                    'method' => 'open-ils.cat.asset.copy_tree.retrieve',
                    'params' => [
                        '',
                        $item['bibkey'],
                        $this->library->branches,
                    ],
                ], $this->library->cat_url))->getResult();

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
                        $item_data = (new OSRFQuery([
                            'service' => 'open-ils.search',
                            'method' => 'open-ils.search.biblio.mods_from_copy',
                            'params' => [
                                $item['copy_id'],
                            ],
                        ], $this->library->cat_url))->getResult();

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

        return $this->newListItems;
    }
}
