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
    }

    public static function get_tools($request) {
        $args = [
            'category'  => sanitize_text_field($request->get_param('category')),
            'search'    => sanitize_text_field($request->get_param('search')),
            'has_video' => $request->get_param('has_video') ? true : false,
            'per_page'  => absint($request->get_param('per_page')),
            'page'      => absint($request->get_param('page')),
        ];
        $tools = TTP_Data::get_tools($args);

        return rest_ensure_response($tools);
    }
}
