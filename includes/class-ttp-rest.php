<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TTP_Rest {
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('ttp/v1', '/tools', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_vendors'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('ttp/v1', '/vendors', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_vendors'],
            'permission_callback' => '__return_true'
        ]);
    }

    public static function get_vendors($request) {
        $vendors = TTP_Data::get_all_vendors();
        return rest_ensure_response($vendors);
    }
}
