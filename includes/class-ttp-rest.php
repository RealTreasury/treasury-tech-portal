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

        register_rest_route('ttp/v1', '/refresh', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'refresh_data'],
            'permission_callback' => '__return_true'
        ]);
    }

    public static function get_tools($request) {
        $args = array();

        $category = $request->get_param('category');
        if (!empty($category)) {
            $args['category'] = array_map('sanitize_text_field', (array) $category);
        }

        $search = $request->get_param('search');
        if (is_string($search) && $search !== '') {
            $args['search'] = sanitize_text_field($search);
        }

        if ($request->get_param('has_video')) {
            $args['has_video'] = true;
        }

        $per_page = $request->get_param('per_page');
        if ($per_page !== null && $per_page !== '') {
            $args['per_page'] = absint($per_page);
        }

        $page = $request->get_param('page');
        if ($page !== null && $page !== '') {
            $args['page'] = absint($page);
        }

        $region = $request->get_param('region');
        if (!empty($region)) {
            $args['region'] = array_map('sanitize_text_field', (array) $region);
        }

        $sub_category = $request->get_param('sub_category');
        if (!empty($sub_category)) {
            $args['sub_category'] = array_map('sanitize_text_field', (array) $sub_category);
        }

        $tools = TTP_Data::get_tools($args);

        return rest_ensure_response($tools);
    }

    public static function get_vendors($request) {
        $vendors = TTP_Data::get_all_vendors();
        $needs_refresh = false;

        $vendors = (array) $vendors;
        foreach ( $vendors as &$vendor ) {
            $vendor = (array) $vendor;
            $vendor_needs_resolution = false;
            foreach ( $vendor as $key => $value ) {
                if ( is_array( $value ) ) {
                    $filtered = array_values( array_filter( (array) $value, function ( $item ) use ( &$needs_refresh, &$vendor_needs_resolution ) {
                        if ( TTP_Record_Utils::contains_record_ids( $item ) ) {
                            $needs_refresh        = true;
                            $vendor_needs_resolution = true;
                            return false;
                        }
                        return true;
                    } ) );

                    if ( empty( $filtered ) ) {
                        unset( $vendor[ $key ] );
                    } else {
                        $vendor[ $key ] = $filtered;
                    }
                } else {
                    if ( TTP_Record_Utils::contains_record_ids( $value ) ) {
                        $needs_refresh        = true;
                        $vendor_needs_resolution = true;
                        unset( $vendor[ $key ] );
                    }
                }
            }

            if ( $vendor_needs_resolution ) {
                $vendor['incomplete'] = true;
            }
        }
        unset( $vendor );

        if ( $needs_refresh ) {
            TTP_Data::refresh_vendor_cache();
        }

        return rest_ensure_response( $vendors );
    }

    public static function refresh_data( $request ) {
        TTP_Data::refresh_vendor_cache();
        return rest_ensure_response( array( 'status' => 'refreshed' ) );
    }

}
