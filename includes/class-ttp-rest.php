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

        register_rest_route('ttp/v1', '/products', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_products'],
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
        } else {
            $args['category'] = (array) get_option( TTP_Admin::OPTION_ENABLED_CATEGORIES, array_keys( TTP_Data::get_categories() ) );
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

        $response = array(
            'tools'          => $tools,
            'enabled_domains' => (array) get_option( TTP_Admin::OPTION_ENABLED_DOMAINS, array_keys( TTP_Data::get_domains() ) ),
        );

        return rest_ensure_response( $response );
    }

    public static function get_products($request) {
        $products = TTP_Data::get_all_products();
        $enabled_categories = (array) get_option( TTP_Admin::OPTION_ENABLED_CATEGORIES, array_keys( TTP_Data::get_categories() ) );
        $enabled_domains    = (array) get_option( TTP_Admin::OPTION_ENABLED_DOMAINS, array_keys( TTP_Data::get_domains() ) );
        $products = array_filter( (array) $products, function ( $product ) use ( $enabled_categories, $enabled_domains ) {
            $product = (array) $product;
            $cat    = $product['category'] ?? ( $product['categories'][0] ?? '' );
            $domains = (array) ( $product['domain'] ?? array() );
            if ( ! in_array( $cat, $enabled_categories, true ) ) {
                return false;
            }
            if ( ! empty( $domains ) && empty( array_intersect( $domains, $enabled_domains ) ) ) {
                return false;
            }
            return true;
        } );
        $needs_refresh = false;

        $products = array_values( $products );
        foreach ( $products as &$product ) {
            $product = (array) $product;
            $product_needs_resolution = false;
            foreach ( $product as $key => $value ) {
                if ( is_array( $value ) ) {
                    $filtered = array_values( array_filter( (array) $value, function ( $item ) use ( &$needs_refresh, &$product_needs_resolution ) {
                        if ( TTP_Record_Utils::contains_record_ids( $item ) ) {
                            $needs_refresh         = true;
                            $product_needs_resolution = true;
                            return false;
                        }
                        return true;
                    } ) );

                    if ( empty( $filtered ) ) {
                        unset( $product[ $key ] );
                    } else {
                        $product[ $key ] = $filtered;
                    }
                } else {
                    if ( TTP_Record_Utils::contains_record_ids( $value ) ) {
                        $needs_refresh         = true;
                        $product_needs_resolution = true;
                        unset( $product[ $key ] );
                    }
                }
            }

            if ( $product_needs_resolution ) {
                $product['incomplete'] = true;
            }
        }
        unset( $product );

        if ( $needs_refresh ) {
            TTP_Data::refresh_product_cache();
        }

        return rest_ensure_response(
            array(
                'products'        => $products,
                'enabled_domains' => $enabled_domains,
            )
        );
    }

    public static function refresh_data( $request ) {
        TTP_Data::refresh_product_cache();
        return rest_ensure_response( array( 'status' => 'refreshed' ) );
    }

}
