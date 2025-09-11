<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TTP_Data {
    const OPTION_KEY = 'ttp_tools';
    const CACHE_KEY  = 'ttp_tools_cache';
    const CACHE_TTL  = HOUR_IN_SECONDS;
    const VENDOR_OPTION_KEY = 'ttp_vendors';
    const VENDOR_CACHE_KEY  = 'ttp_vendors_cache';

    /**
     * Retrieve all tools with caching.
     *
     * @return array
     */
    public static function get_all_tools() {
        $tools = get_transient(self::CACHE_KEY);
        if ($tools !== false) {
            return $tools;
        }

        $tools = self::get_all_vendors();

        set_transient(self::CACHE_KEY, $tools, self::CACHE_TTL);
        return $tools;
    }

    /**
     * Save the given tools and clear cache.
     *
     * @param array $tools
     */
    public static function save_tools($tools) {
        update_option(self::OPTION_KEY, $tools);
        delete_transient(self::CACHE_KEY);
        // Clear all caches
        wp_cache_flush();
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        delete_transient('ttp_tools_cache');
    }

    
    /**
     * Retrieve all vendors with caching.
     *
     * @return array
     */
    public static function get_all_vendors() {
        $vendors = get_transient(self::VENDOR_CACHE_KEY);
        if ($vendors !== false) {
            return $vendors;
        }

        $vendors = get_option(self::VENDOR_OPTION_KEY, []);
        set_transient(self::VENDOR_CACHE_KEY, $vendors, self::CACHE_TTL);
        return $vendors;
    }

    /**
     * Save the given vendors and clear plugin cache when data changes.
     *
     * @param array $vendors
     */
    public static function save_vendors($vendors) {
        $current = get_option(self::VENDOR_OPTION_KEY, []);

        if (wp_json_encode($vendors) === wp_json_encode($current)) {
            return;
        }

        update_option(self::VENDOR_OPTION_KEY, $vendors);
        delete_transient(self::VENDOR_CACHE_KEY);
    }

    /**
     * Refresh vendor cache from Airbase.
     */
    public static function refresh_vendor_cache() {
        $field_map = array(
            'Product Name'    => 'fld2hocSMtPQYWfPa',
            'Linked Vendor'   => 'fldsrlwpO9AfkmjcH',
            'Product Website' => 'fldznljEJpn4lv79r',
            'Product Video'   => 'fld9Kd3xN2hPQYF7W', // placeholder ID
            'Logo URL'        => 'fldfZPuRMjQKCv3U6',
            'Status'          => 'fldFsaznNFvfh3x7k',
            'Hosted Type'     => 'fldGyZDaIUFFidaXA',
            'Domain'          => 'fldU53MVlWgkPbPDw',
            'Regions'         => 'fldE8buvdk7TDG1ex',
            'Sub Categories'  => 'fldl2g5bYDq9TibuF',
            'Parent Category' => 'fldXqnpKe8ioYOYhP',
            'Capabilities'    => 'fldvvv8jnCKoJSI7x',
            'HQ Location'     => 'fldTIplvUIwNH7C4X',
            'Founded Year'    => 'fldwsUY6nSqxBk62J',
            'Founders'        => 'fldoTMkJIl1i8oo0r',
        );

        $data = TTP_Airbase::get_vendors(array_values($field_map));
        if (is_wp_error($data)) {
            return;
        }

        $records = self::normalize_vendor_response($data);

        $present_keys = array();
        foreach ($records as $record) {
            if (isset($record['fields']) && is_array($record['fields'])) {
                $present_keys = array_unique(array_merge($present_keys, array_keys($record['fields'])));
            }
        }

        $missing = array_diff(array_keys($field_map), $present_keys);
        if (!empty($missing) && function_exists('error_log')) {
            error_log('TTP_Data: Missing expected fields: ' . implode(', ', $missing));
        }

        $linked_tables = array(
            'Regions'        => 'Regions',
            'Linked Vendor'  => 'Vendors',
            'Hosted Type'    => 'Hosted Type',
            'Domain'         => 'Domain',
            'Sub Categories' => 'Sub Categories',
            'Capabilities'   => 'Capabilities',
        );

        $vendors = array();
        foreach ($records as $record) {
            $fields = isset($record['fields']) && is_array($record['fields']) ? $record['fields'] : $record;

            $regions_field = $fields['Regions'] ?? array();
            $regions       = array();
            if ( is_array( $regions_field ) && ! empty( $regions_field ) ) {
                if ( self::contains_record_ids( $regions_field ) ) {
                    $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Regions'], $regions_field );
                    if ( is_wp_error( $resolved ) ) {
                        if ( function_exists( 'error_log' ) ) {
                            error_log( 'TTP_Data: Failed resolving Regions: ' . $resolved->get_error_message() );
                        }
                        $regions = array();
                    } else {
                        $regions = array_map( 'sanitize_text_field', (array) $resolved );
                    }
                } else {
                    $regions = array_map( 'sanitize_text_field', $regions_field );
                }
            } elseif ( is_string( $regions_field ) ) {
                $regions = array_filter( array_map( 'trim', explode( ',', $regions_field ) ) );
            }

            $vendor_field = $fields['Linked Vendor'] ?? array();
            $vendor_name  = '';
            if ( is_array( $vendor_field ) && ! empty( $vendor_field ) ) {
                if ( self::contains_record_ids( $vendor_field ) ) {
                    $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Linked Vendor'], $vendor_field );
                    if ( is_wp_error( $resolved ) ) {
                        if ( function_exists( 'error_log' ) ) {
                            error_log( 'TTP_Data: Failed resolving Linked Vendor: ' . $resolved->get_error_message() );
                        }
                        $vendor_name = '';
                    } elseif ( ! empty( $resolved ) ) {
                        $vendor_name = sanitize_text_field( reset( $resolved ) );
                    }
                } else {
                    $vendor_name = sanitize_text_field( reset( $vendor_field ) );
                }
            } elseif ( ! empty( $vendor_field ) ) {
                $vendor_name = sanitize_text_field( $vendor_field );
            }

            $hosted_field = $fields['Hosted Type'] ?? array();
            $hosted_type  = array();
            if ( is_array( $hosted_field ) && ! empty( $hosted_field ) ) {
                if ( self::contains_record_ids( $hosted_field ) ) {
                    $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Hosted Type'], $hosted_field );
                    if ( is_wp_error( $resolved ) ) {
                        if ( function_exists( 'error_log' ) ) {
                            error_log( 'TTP_Data: Failed resolving Hosted Type: ' . $resolved->get_error_message() );
                        }
                        $hosted_type = array();
                    } else {
                        $hosted_type = array_map( 'sanitize_text_field', (array) $resolved );
                    }
                } else {
                    $hosted_type = array_map( 'sanitize_text_field', $hosted_field );
                }
            } elseif ( is_string( $hosted_field ) ) {
                $hosted_type = array_filter( array_map( 'trim', explode( ',', $hosted_field ) ) );
            }

            $domain_field = $fields['Domain'] ?? array();
            $domain       = array();
            if ( is_array( $domain_field ) && ! empty( $domain_field ) ) {
                if ( self::contains_record_ids( $domain_field ) ) {
                    $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Domain'], $domain_field );
                    if ( is_wp_error( $resolved ) ) {
                        if ( function_exists( 'error_log' ) ) {
                            error_log( 'TTP_Data: Failed resolving Domain: ' . $resolved->get_error_message() );
                        }
                        $domain = array();
                    } else {
                        $domain = array_map( 'sanitize_text_field', (array) $resolved );
                    }
                } else {
                    $domain = array_map( 'sanitize_text_field', $domain_field );
                }
            } elseif ( is_string( $domain_field ) ) {
                $domain = array_filter( array_map( 'trim', explode( ',', $domain_field ) ) );
            }

            $sub_field     = $fields['Sub Categories'] ?? array();
            $sub_categories = array();
            if ( is_array( $sub_field ) && ! empty( $sub_field ) ) {
                if ( self::contains_record_ids( $sub_field ) ) {
                    $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Sub Categories'], $sub_field );
                    if ( is_wp_error( $resolved ) ) {
                        if ( function_exists( 'error_log' ) ) {
                            error_log( 'TTP_Data: Failed resolving Sub Categories: ' . $resolved->get_error_message() );
                        }
                        $sub_categories = array();
                    } else {
                        $sub_categories = array_map( 'sanitize_text_field', (array) $resolved );
                    }
                } else {
                    $sub_categories = array_map( 'sanitize_text_field', $sub_field );
                }
            } elseif ( is_string( $sub_field ) ) {
                $sub_categories = array_filter( array_map( 'trim', explode( ',', $sub_field ) ) );
            }

            $cap_field   = $fields['Capabilities'] ?? array();
            $capabilities = array();
            if ( is_array( $cap_field ) && ! empty( $cap_field ) ) {
                if ( self::contains_record_ids( $cap_field ) ) {
                    $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Capabilities'], $cap_field );
                    if ( is_wp_error( $resolved ) ) {
                        if ( function_exists( 'error_log' ) ) {
                            error_log( 'TTP_Data: Failed resolving Capabilities: ' . $resolved->get_error_message() );
                        }
                        $capabilities = array();
                    } else {
                        $capabilities = array_map( 'sanitize_text_field', (array) $resolved );
                    }
                } else {
                    $capabilities = array_map( 'sanitize_text_field', $cap_field );
                }
            } elseif ( is_string( $cap_field ) ) {
                $capabilities = array_filter( array_map( 'trim', explode( ',', $cap_field ) ) );
            }

            $parent_category = sanitize_text_field( $fields['Parent Category'] ?? '' );
            $category_names  = array_filter( array_merge( $parent_category ? array( $parent_category ) : array(), $sub_categories ) );

            $vendors[] = array(
                'id'              => sanitize_text_field( $record['id'] ?? '' ),
                'name'            => $fields['Product Name'] ?? '',
                'vendor'          => $vendor_name,
                'website'         => self::normalize_url( $fields['Product Website'] ?? '' ),
                'video_url'       => self::normalize_url( $fields['Product Video'] ?? '' ),
                'status'          => $fields['Status'] ?? '',
                'hosted_type'     => $hosted_type,
                'domain'          => $domain,
                'regions'         => $regions,
                'sub_categories'  => $sub_categories,
                'parent_category' => $parent_category,
                'category_names'  => $category_names,
                'capabilities'    => $capabilities,
                'logo_url'        => self::normalize_url( $fields['Logo URL'] ?? '' ),
                'hq_location'     => $fields['HQ Location'] ?? '',
                'founded_year'    => $fields['Founded Year'] ?? '',
                'founders'        => $fields['Founders'] ?? '',
            );
        }

        self::save_vendors($vendors);
    }

    /**
     * Normalize Airbase responses into a vendor array.
     *
     * @param mixed $data Raw API response.
     * @return array
     */
    private static function normalize_vendor_response($data) {
        if (!is_array($data)) {
            return [];
        }

        $known_keys = ['products', 'records', 'vendors'];

        foreach ($known_keys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return self::normalize_vendor_response($data['data']);
        }

        return $data;
    }

    /**
     * Normalize and sanitize a URL value.
     *
     * Ensures a scheme is present and validates via WordPress utilities when
     * available. Returns an empty string for invalid URLs.
     *
     * @param string $url Raw URL value.
     * @return string Normalized URL or empty string if invalid.
     */
    private static function normalize_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }

        if (strpos($url, '://') === false) {
            $url = 'https://' . ltrim($url, '/');
        }

        if (function_exists('esc_url_raw')) {
            $url = esc_url_raw($url);
        }

        if (function_exists('wp_http_validate_url') && !wp_http_validate_url($url)) {
            return '';
        }

        return $url;
    }

    /**
     * Check if an array contains Airtable record IDs.
     *
     * @param array $values Values to inspect.
     * @return bool
     */
    private static function contains_record_ids( $values ) {
        foreach ( (array) $values as $value ) {
            if ( is_string( $value ) && strpos( $value, 'rec' ) === 0 ) {
                return true;
            }
        }
        return false;
    }

/**
     * Load default tools from bundled JSON file.
     *
     * @return array
     */
    /**
     * Filter and search tools server-side.
     *
     * @param array $args
     * @return array
     */
    public static function get_tools($args = []) {
        $tools = self::get_all_tools();

        if (!empty($args['category']) && $args['category'] !== 'ALL') {
            $tools = array_filter($tools, function ($tool) use ($args) {
                return isset($tool['category']) && $tool['category'] === $args['category'];
            });
        }

        if (!empty($args['search'])) {
            $search = strtolower($args['search']);
            $tools = array_filter($tools, function ($tool) use ($search) {
                $haystack = strtolower($tool['name'] . ' ' . ($tool['desc'] ?? '') . ' ' . implode(' ', $tool['features'] ?? []));
                return strpos($haystack, $search) !== false;
            });
        }

        if (!empty($args['has_video'])) {
            $tools = array_filter($tools, function ($tool) {
                return !empty($tool['videoUrl']);
            });
        }

        if (!empty($args['region'])) {
            $regions = (array) $args['region'];
            $tools   = array_filter($tools, function ($tool) use ($regions) {
                $tool_regions = $tool['regions'] ?? array();
                return !empty(array_intersect($regions, $tool_regions));
            });
        }

        if (!empty($args['parent_category'])) {
            $parents = (array) $args['parent_category'];
            $tools   = array_filter($tools, function ($tool) use ($parents) {
                return isset($tool['parent_category']) && in_array($tool['parent_category'], $parents, true);
            });
        }

        if (!empty($args['sub_category'])) {
            $subs  = (array) $args['sub_category'];
            $tools = array_filter($tools, function ($tool) use ($subs) {
                $tool_subs = $tool['sub_categories'] ?? array();
                return !empty(array_intersect($subs, $tool_subs));
            });
        }

        $page     = max(1, intval($args['page'] ?? 1));
        $per_page = max(1, intval($args['per_page'] ?? count($tools)));
        $offset   = ($page - 1) * $per_page;

        $tools = array_slice(array_values($tools), $offset, $per_page);

        return $tools;
    }
}
