<?php
/*
Plugin Name: Treasury Tech Portal
Plugin URI: https://realtreasury.com
Description: Embed the Treasury Tech Portal tool using the [treasury_portal] shortcode.
Version: 1.0.0
Author: Real Treasury
Author URI: https://realtreasury.com
License: GPLv2 or later
*/

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define("TTP_PLUGIN_URL", plugin_dir_url(__FILE__));
define("TTP_PLUGIN_DIR", plugin_dir_path(__FILE__));
require_once plugin_dir_path(__FILE__) . 'includes/class-treasury-portal.php';

Treasury_Tech_Portal::instance();
