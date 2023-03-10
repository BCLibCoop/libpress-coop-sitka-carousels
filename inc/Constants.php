<?php

/**
 * Constants used by the plugin
 */

namespace BCLibCoop\SitkaCarousel;

/**
 * Constants used by the plugin
 */
class Constants
{
    /**
     * The Evergreen URL
     */
    public const EG_URL = 'https://catalogue.libraries.coop';

    /**
     * The suffix to form a library specific link to the EG catalogue
     */
    public const CATALOGUE_SUFFIX = '.catalogue.libraries.coop';

    /**
     * Library domains that are on the prod cluster. Everything else will be
     * parsed out to go to the catologue in the domain
     */
    public const PROD_LIBS = ['bc', 'mb'];

    /**
     * Minimum number of items to return for a carousel
     */
    public const MIN = 8;

    /**
     * How long to wait for query results from Evergreen
     */
    public const QUERY_TIMEOUT = 30;

    /**
     * Constant defining all of the possible carousel types - first is default
     */
    public const TYPE = [
        'adult_fiction',
        'adult_nonfiction',
        'adult_largeprint',
        'adult_dvds',
        'adult_music',
        'teen_fiction',
        'teen_nonfiction',
        'juvenile_fiction',
        'juvenile_nonfiction',
        'juvenile_dvdscds',
    ];

    /**
     * Constant defining all of the possible transition types - first is default
     */
    public const TRANSITION = [
        'fade',
        'swipe',
    ];

    /**
     * Predefined autiences
     */
    public const MARC_AUDIENCE = [
        'adult' => 'audience(e,g)',
        'teen' => 'audience(d)',
        'juvenile' => 'audience(a,b,c,j)',
    ];

    /**
     * Predefined literary forms
     */
    public const LITFORM = [
        'fiction' => 'lit_form(1,f,j)',
        'nonfiction' => 'lit_form(0,e,i,2)',
    ];

    /**
     * Predefined formats
     */
    public const FORMAT = [
        'print' => 'search_format(physicalbooks)',
        'large_print' => 'item_type(a,t) item_form(d)',
        'dvd' => 'item_type(g)',
        'cd' => 'item_type(j)',
        'dvdscds' => 'item_type(g,j)',
    ];

    /**
     * Search terms for each carousel type
     */
    public const SEARCH = [
        'adult_fiction' =>
            self::MARC_AUDIENCE['adult'] . ' ' . self::FORMAT['print'] . ' ' . self::LITFORM['fiction'],
        'adult_nonfiction' =>
            self::MARC_AUDIENCE['adult'] . ' ' . self::FORMAT['print'] . ' ' . self::LITFORM['nonfiction'],
        'adult_largeprint' =>
            self::MARC_AUDIENCE['adult'] . ' ' . self::FORMAT['large_print'],
        'adult_dvds' =>
            self::MARC_AUDIENCE['adult'] . ' ' . self::FORMAT['dvd'],
        'adult_music' =>
            self::MARC_AUDIENCE['adult'] . ' ' . self::FORMAT['cd'],
        'teen_fiction' =>
            self::MARC_AUDIENCE['teen'] . ' ' . self::FORMAT['print'] . ' ' . self::LITFORM['fiction'],
        'teen_nonfiction' =>
            self::MARC_AUDIENCE['teen'] . ' ' . self::FORMAT['print'] . ' ' . self::LITFORM['nonfiction'],
        'juvenile_fiction' =>
            self::MARC_AUDIENCE['juvenile'] . ' ' . self::FORMAT['print'] . ' ' . self::LITFORM['fiction'],
        'juvenile_nonfiction' =>
            self::MARC_AUDIENCE['juvenile'] . ' ' . self::FORMAT['print'] . ' ' . self::LITFORM['nonfiction'],
        'juvenile_dvdscds' =>
            self::MARC_AUDIENCE['juvenile'] . ' ' . self::FORMAT['dvdscds'],
    ];
}
