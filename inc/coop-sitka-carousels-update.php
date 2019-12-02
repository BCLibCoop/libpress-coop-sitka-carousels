<?php
/*
 * Script to be run via cron with `wp eval-script`
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
 * without an active_date will be queried, including ones previously added
 * to the db.
 */

print("At the start\n");

// Yesterday's date - We use yesterday's date so that in the off-chance a new
// item gets added during the update process it will be captured in the next update
$date_checked = date('Y-m-d', mktime(0,0,0, date('m'), date('d')-1, date('Y')));

require_once('/home/ben/coop/libpress/web/wp-content/plugins/coop-sitka-carousels/inc/coop-sitka-carousels-constants.php');

// Get all of the active libraries

$sql = "SELECT blog_id AS blog_id,
               domain AS domain
          FROM {$wpdb->blogs}
         WHERE public = 1";
#$libraries = $wpdb->get_results( $sql, ARRAY_A );





  //////////////
 //TEMP CODE //
//////////////
$libraries = array( array('blog_id' => 55,
                          'domain' => 'smithers.bc'));
define('CAROUSEL_TYPE', array('adult_fiction'));

print("About to loop libraries\n");




// Loop through each library updating its carousel data
foreach($libraries AS $library) {

  print("Top of library loop\n");


  // Switch to the current library's WP instance
  switch_to_blog($library['blog_id']);


  // Get the library's short name - if not set skip this library
  if (get_option('_coop_sitka_lib_shortname', '') != '') {
    $library['short_name'] = get_option('_coop_sitka_lib_shortname');
  }
  else {
    continue;
  }

  // Get the library's catalogue link
  if ( get_option('_coop_sitka_lib_cat_link') != '' ) {
    $library['catalogue_link'] = get_option('_coop_sitka_lib_cat_link') . CAROUSEL_CATALOGUE_SUFFIX;
  }
  else {
    $library['catalogue_link'] = preg_filter(CAROUSEL_DOMAIN_SUFFIX, '', $library['domain']) . CAROUSEL_CATALOGUE_SUFFIX;
  }

  print("set catalogue_link\n");
  // Retrieve this library's meta data from EG
  $eg_query_result = wp_remote_post( 'https://catalogue.libraries.coop/osrf-http-translator',
                                     array('headers' => 'X-OpenSRF-service:open-ils.actor',
                                           'body' => 'osrf-msg=[{"__c":"osrfMessage","__p":{"threadTrace":"0","locale":"en-US","type":"REQUEST","payload":{"__c":"osrfMethod","__p":{'
                                                   . '"method":"open-ils.actor.org_unit.retrieve_by_shortname","params":["' . $library['short_name'] . '"]}}}}]',
                                           'timeout' => CAROUSEL_QUERY_TIMEOUT));

  $lib_meta = json_decode(wp_remote_retrieve_body($eg_query_result))[0]->__p->payload->__p->content->__p;

  $library['locg'] = $lib_meta[3];
  $library['parent_locg'] = $lib_meta[8];

  // Get the date of the last carousel update, if no update use 4 months ago
  $date_last_checked = get_option('_coop_sitka_carousels_date_last_checked', date('Y-m-d', mktime(0,0,0, date('m')-4, date('d'), date('Y'))));


  /*
   * Query Evergreen for new items for each carousel type and add them to
   * the database
   */
  foreach(CAROUSEL_TYPE AS $carousel_type) {
    print("Top of foreach carousel type loop\n");

    $finished = FALSE;
    $index = 1;
    $count = 25; // 25 is the max number of results Evergreen will return

    // The query may return multiple pages of results. Increment the $index by $count
    // and retrieve the next page until no links are found
    while (! $finished) {
      print("Top of while ! finished loop\n");

      $eg_query_result = wp_remote_get(CAROUSEL_EG_URL . '/opac/extras/opensearch/1.1/' . $library['short_name'] . '/html-full/?searchTerms=' 
                                     . CAROUSEL_SEARCH[$carousel_type] . '%20create_date('
                                     . $date_last_checked . ')&searchSort=create_date&startIndex=' . $index . '&count=' . $count,
                                       array('timeout' => CAROUSEL_QUERY_TIMEOUT));
      $result_html = wp_remote_retrieve_body($eg_query_result);

      print($result_html . "\n");

      $doc = @DOMDocument::loadHTML($result_html);
      $xpath = new DOMXpath($doc);

      // Get the links to each returned item from the HTML page
      $link_urls = $xpath->query('//html/body/dl/dt/a/@href');

      print("link_url count is " . count($link_urls) . "\n");

      if ($link_urls->length > 0) {

        print("Link URLs > 0\n");

        foreach($link_urls AS $link_url) {

          print("Top of foreach link_urls as link_url\n");

          $link_parts = explode('/', $link_url->nodeValue);
print("Before pregmatch test\n");
print("link_url nodeValue: " . $link_url->nodeValue . "\n");
          // Test to make sure the link URL includes the expected text
          if (preg_match('/biblio-record_entry/', $link_parts[5])) {
print("pregmatch tested ok\n");
            $bibkey = $link_parts[6];
print("bibkey is " . $bibkey ."\n");

            // Check that the bibkey doesn't already exist in the database
#            $sql = "SELECT bibkey AS bibkey
#                      FROM {$wpdb->sitka_carousels}
#                     WHERE bibkey = " . $bibkey;
#            $query_results = $wpdb->get_results( $sql, ARRAY_A );
#
#            if (count($query_results) == 0) {
#              $wpdb->insert({$wpdb->sitka_carousels}, array(
#                'carousel_type' => $carousel_type,
#                'date_created' => $date_checked,
#                'bibkey' => $bibkey));
#            }

print("pregmatch matched\n");
          }

        } 

        // All links on this page processed, update index and then get next page
        $index = $index = $count;

      }
#      else {

        // No link URLs found, have gone through all result pages
        $finished = TRUE;

#      }

    }
    print("After while ! finished loop\n");
#
#    // All new bibkeys have been added to the db, now pull all
#    // items without a date_active and check 
#    $sql = "SELECT id AS id,
#                   bibkey AS bibkey
#              FROM {$wpdb->sitka_carousels}
#             WHERE carousel_type = '" . $carousel_type . "'
#               AND date_active IS NULL";
#
#    $db_bibkeys = $wpdb->get_results( $sql, ARRAY_A );
#
#    foreach($db_items AS $item) {
#
#      // Query Evergreen to get the copy id and active date
#      $eg_query_result = wp_remote_post( 'https://catalogue.libraries.coop/osrf-http-translator',
#                                         array('headers' => 'X-OpenSRF-service:open-ils.cat',
#                                               'body' => 'osrf-msg=[{"__c":"osrfMessage","__p":{"threadTrace":"0","locale":"en-US","type":"REQUEST","payload":{"__c":"osrfMethod","__p":{"method":"open-ils.cat.asset.copy_tree.retrieve","params":["", "' . $item['bibkey'] . '","' . $library['locg'] . '"]}}}}]',
#                                               'timeout' => CAROUSEL_QUERY_TIMEOUT));
#
#      $item_copy_data = json_decode(wp_remote_retrieve_body($eg_query_result))[0]->__p->payload->__p->content[0]->__p[0][0]->__p;
#
#      if (isset($item_copy_data[10])) {
#        // The current bibkey has a date_active
#        $item['date_active'] = $item_copy_data[10];
#        $item['copy_id'] = $item_copy_data[22];
#
#        $eg_query_result = wp_remote_post( 'https://catalogue.libraries.coop/osrf-http-translator',
#                                           array('headers' => 'X-OpenSRF-service:open-ils.search',
#                                                 'body' => 'osrf-msg=[{"__c":"osrfMessage","__p":{"threadTrace":"0","locale":"en-US","type":"REQUEST","payload":{"__c":"osrfMethod","__p":{"method":"open-ils.search.biblio.mods_from_copy","params":["' . $item['copy_id'] . '"]}}}}]',
#                                                 'timeout' => CAROUSEL_QUERY_TIMEOUT));
#        $item_copy_data = json_decode(wp_remote_retrieve_body($eg_query_result))[0]->__p->payload->__p->content->__p;
#
#        $item['title'] = $item_copy_data[0];
#        $item['author'] = $item_copy_data[1];
#        $item['description'] = $item_copy_data[13];
#        $item['catalogue_url'] = $library['catalogue_link'] . '/eg/opac/record/' . $item['bibkey'] . '?locg=' . $library['locg'];
#        $item['cover_url'] = CAROUSEL_EG_URL . 'opac/extras/ac/jacket/medium/r/' . $item['bibkey'];
#
#        // Update the database to add our new information
#        $wpdb->update( $wpdb->sitka_carousels,
#                       array('carousel_type' => $carousel_type,
#                             'date_active' => $item['date_active'],
#                             'catalogue_url' => $item['catalogue_url'],
#                             'cover_url' => $item['cover_url'],
#                             'title' => $item['title'],
#                             'author' => $item['author'],
#                             'description' => $item['description']),
#                       array('bibkey' => $item['bibkey']));
#
#      } // if isset($item_copy_data[10] (date_active)
#
#    } // for each $item (bibkey without a date_active)
#
  } // foreach carousel_type
#
#  // Set this library's _coop_sitka_carousels_date_last_checked to yesterday's date
#  // This is done in the off chance a new item has been added while we have been updating the carousel
#  update_option('_coop_sitka_carousels_date_last_checked', date('Y-m-d', mktime(0,0,0, date('m'), date('d')-1, date('Y'))));
#
}
print("After library loop");
#// Switch back to main network intance of WP
#restore_current_blog();
#
