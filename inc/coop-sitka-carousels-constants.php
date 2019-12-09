<?php

// Constant defining all of the possible carousel types - first is default
define('CAROUSEL_TYPE', array('adult_fiction',
                              'adult_nonfiction',
                              'adult_largeprint',
                              'adult_dvds',
                              'adult_music',
                              'teen_fiction',
                              'teen_nonfiction',
                              'juvenile_fiction',
                              'juvenile_nonfiction',
                              'juvenile_dvdscds'));

// Constant defining all of the possible transition types - first is default
define('CAROUSEL_TRANSITION', array('fade',
                                    'swipe'));

// EG Search Terms
define('CAROUSEL_MARC_AUDIENCE', array('adult' => 'audience(e,g)',
                                       'teen' => 'audience(d)',
                                       'juvenile' => 'audience(a,b,c,j)'));

define('CAROUSEL_LITFORM', array('fiction' => 'lit_form(1,f,j)',
                                 'nonfiction' => 'lit_form(0,e,i,2)'));

define('CAROUSEL_FORMAT', array('print' => 'search_format(physicalbooks)',
                                'large_print' => 'item_type(a,t)%20item_form(d)',
                                'dvd' => 'item_type(g)',
                                'cd' => 'item_type(j)',
                                'dvdscds' => 'item_type(g,j)'));

// Image to show if there is no cover image - NEEDS TO BE CHANGED!!!!!
define('CAROUSEL_NOCOVER', '/wp-content/mu-plugins/coop-sitka-lists/img/nocover.jpg');

// The Evergreen URL
define('CAROUSEL_EG_URL', 'https://catalogue.libraries.coop/');

// The part of a library's domain to remove when creating a EG catalogue link, formatted as a pattern for preg_filter
define('CAROUSEL_DOMAIN_SUFFIX', '/\.libraries\.coop/');

// The suffix to form a library specific link to the EG catalogue
define('CAROUSEL_CATALOGUE_SUFFIX', '.catalogue.libraries.coop');

// Minimum number of items to return for a carousel
define('CAROUSEL_MIN', 8);

// How long to wait for query results from Evergreen
define('CAROUSEL_QUERY_TIMEOUT', 30);

// Search terms for each carousel type
define('CAROUSEL_SEARCH', array('adult_fiction' => CAROUSEL_MARC_AUDIENCE['adult'] . ' ' . CAROUSEL_FORMAT['print'] . ' ' . CAROUSEL_LITFORM['fiction'],
                                'adult_nonfiction' => CAROUSEL_MARC_AUDIENCE['adult'] . ' ' . CAROUSEL_FORMAT['print'] . ' ' . CAROUSEL_LITFORM['nonfiction'],
                                'adult_largeprint' => CAROUSEL_MARC_AUDIENCE['adult'] . ' ' . CAROUSEL_FORMAT['large_print'],
                                'adult_dvds' => CAROUSEL_MARC_AUDIENCE['adult'] . ' ' . CAROUSEL_FORMAT['dvd'],
                                'adult_music' => CAROUSEL_MARC_AUDIENCE['adult'] . ' ' . CAROUSEL_FORMAT['cd'],
                                'teen_fiction' => CAROUSEL_MARC_AUDIENCE['teen'] . ' ' . CAROUSEL_FORMAT['print'] . ' ' . CAROUSEL_LITFORM['fiction'],
                                'teen_nonfiction' => CAROUSEL_MARC_AUDIENCE['teen'] . ' ' . CAROUSEL_FORMAT['print'] . ' ' . CAROUSEL_LITFORM['nonfiction'],
                                'juvenile_fiction' => CAROUSEL_MARC_AUDIENCE['juvenile'] . ' ' . CAROUSEL_FORMAT['print'] . ' ' . CAROUSEL_LITFORM['fiction'],
                                'juvenile_nonfiction' => CAROUSEL_MARC_AUDIENCE['juvenile'] . ' ' . CAROUSEL_FORMAT['print'] . ' ' . CAROUSEL_LITFORM['nonfiction'],
                                'juvenile_dvdscds' => CAROUSEL_MARC_AUDIENCE['juvenile'] . ' ' . CAROUSEL_FORMAT['dvdscds']));
