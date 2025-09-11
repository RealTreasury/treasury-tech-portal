<?php
/**
 * Plugin Name: Treasury Tech Portal
 * Plugin URI: https://realtreasury.com
 * Description: Embed the Treasury Tech Portal tool using the [treasury_portal] shortcode. A comprehensive platform for discovering and comparing treasury technology solutions.
 * Version: 1.0.2
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Real Treasury
 * Author URI: https://realtreasury.com
 * Text Domain: treasury-tech-portal
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin version.
define( 'TTP_VERSION', '1.0.2' );

define( 'TTP_FILE', __FILE__ );
define( 'TTP_BASENAME', plugin_basename( TTP_FILE ) );
define( 'TTP_URL', plugin_dir_url( TTP_FILE ) );
define( 'TTP_DIR', plugin_dir_path( TTP_FILE ) );

define( 'TTP_IS_WPCOM', defined( 'IS_WPCOM' ) && IS_WPCOM );

require_once TTP_DIR . 'includes/class-treasury-portal.php';

if ( ! TTP_IS_WPCOM && function_exists( 'register_activation_hook' ) ) {
    register_activation_hook( TTP_FILE, [ 'Treasury_Tech_Portal', 'instance' ] );
}

Treasury_Tech_Portal::instance();

/**
 * Flush cached Airtable product-field ID\u2192name maps and vendor caches.
 */
function rt_airtable_flush_maps() {
    global $wpdb;
    if ( ! isset( $wpdb ) ) {
        return;
    }
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_rt_airtable_map_%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_rt_airtable_map_%'" );
    delete_transient( TTP_Data::CACHE_KEY );
    delete_transient( TTP_Data::VENDOR_CACHE_KEY . '_v' . TTP_Data::VENDOR_CACHE_VERSION );
    delete_transient( 'ttp_airbase_schema' );
}
add_action( 'rt_refresh_vendors', 'rt_airtable_flush_maps' );
