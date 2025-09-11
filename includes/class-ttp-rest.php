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
            'callback' => [__CLASS__, 'get_tools'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('ttp/v1', '/vendors', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_vendors'],
            'permission_callback' => '__return_true'
        ]);
    }

    public static function get_tools($request) {
        $args = [
            'category'        => $request->get_param('category'),
            'search'          => $request->get_param('search'),
            'has_video'       => $request->get_param('has_video'),
            'per_page'        => $request->get_param('per_page'),
            'page'            => $request->get_param('page'),
            'region'          => $request->get_param('region'),
            'parent_category' => $request->get_param('parent_category'),
            'sub_category'    => $request->get_param('sub_category'),
        ];

        $tools = TTP_Data::get_tools($args);
        return rest_ensure_response($tools);
    }

    public static function get_vendors($request) {
        $vendors = TTP_Data::get_all_vendors();
        return rest_ensure_response($vendors);
    }
}
