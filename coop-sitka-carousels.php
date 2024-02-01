<?php

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
 * Version:           3.0.2
 * Network:           true
 * Requires at least: 5.2
 * Requires PHP:      7.0
 * Author:            BC Libraries Cooperative
 * Author URI:        https://bc.libraries.coop
 * Text Domain:       coop-sitka-carousels
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace BCLibCoop\SitkaCarousel;

defined('ABSPATH') || die(-1);

define('COOP_SITKA_CAROUSEL_PLUGINFILE', __FILE__);

/**
 * Require Composer autoloader if installed on it's own
 */
if (file_exists($composer = __DIR__ . '/vendor/autoload.php')) {
    require_once $composer;
}

add_action('plugins_loaded', function () {
    new SitkaCarousel();
});
