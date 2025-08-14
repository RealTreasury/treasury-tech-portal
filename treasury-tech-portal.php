<?php
/*
Plugin Name: Treasury Tech Portal
Description: Embed the Treasury Tech Portal tool using the [treasury_portal] shortcode. A comprehensive platform for discovering and comparing treasury technology solutions.
Version: 1.0.0
Author: Real Treasury
Author URI: https://realtreasury.com
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: treasury-tech-portal
Domain Path: /languages
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Network: false
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin version
define('TTP_VERSION', '1.0.0');
define('TTP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TTP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TTP_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once plugin_dir_path(__FILE__) . 'includes/class-treasury-portal.php';

// Initialize updater if not in WordPress.org directory
if (!function_exists('get_plugin_updates') || !array_key_exists(TTP_PLUGIN_BASENAME, get_plugin_updates())) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-ttp-updater.php';

    // Initialize the updater
    new TTP_Updater(
        __FILE__,
        'realtreasury', // Replace with your GitHub username
        'treasury-tech-portal',       // Replace with your repository name
        get_option('ttp_github_token', '') // Optional: GitHub token for private repos
    );
}

Treasury_Tech_Portal::instance();
