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
            if ( is_string( $regions_field ) ) {
                $regions_field = self::explode_record_ids( $regions_field );
            } else {
                $regions_field = self::parse_record_ids( $regions_field );
            }
            $regions = array();
            if ( self::contains_record_ids( $regions_field ) ) {
                $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Regions'], $regions_field );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Data: Failed resolving Regions: ' . $resolved->get_error_message() );
                    }
                    $regions = array();
                } else {
                    $regions       = array_map( 'sanitize_text_field', (array) $resolved );
                    $regions_field = $regions;
                }
            } else {
                $regions = array_map( 'sanitize_text_field', $regions_field );
            }

            $vendor_field = $fields['Linked Vendor'] ?? array();
            if ( is_string( $vendor_field ) ) {
                $vendor_field = self::explode_record_ids( $vendor_field );
            } else {
                $vendor_field = self::parse_record_ids( $vendor_field );
            }
            $vendor_name = '';
            if ( self::contains_record_ids( $vendor_field ) ) {
                $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Linked Vendor'], $vendor_field );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Data: Failed resolving Linked Vendor: ' . $resolved->get_error_message() );
                    }
                } elseif ( ! empty( $resolved ) ) {
                    $vendor_field = array_map( 'sanitize_text_field', (array) $resolved );
                    $vendor_name  = $vendor_field ? reset( $vendor_field ) : '';
                }
            } elseif ( ! empty( $vendor_field ) ) {
                $vendor_field = array_map( 'sanitize_text_field', $vendor_field );
                $vendor_name  = $vendor_field ? reset( $vendor_field ) : '';
            }

            $hosted_field = $fields['Hosted Type'] ?? array();
            if ( is_string( $hosted_field ) ) {
                $hosted_field = self::explode_record_ids( $hosted_field );
            } else {
                $hosted_field = self::parse_record_ids( $hosted_field );
            }
            $hosted_type = array();
            if ( self::contains_record_ids( $hosted_field ) ) {
                $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Hosted Type'], $hosted_field );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Data: Failed resolving Hosted Type: ' . $resolved->get_error_message() );
                    }
                } else {
                    $hosted_type  = array_map( 'sanitize_text_field', (array) $resolved );
                    $hosted_field = $hosted_type;
                }
            } else {
                $hosted_type = array_map( 'sanitize_text_field', $hosted_field );
            }

            $domain_field = $fields['Domain'] ?? array();
            $domain       = array();
            if ( is_array( $domain_field ) && ! empty( $domain_field ) ) {
                $first = reset( $domain_field );
                if ( is_array( $first ) ) {
                    $ids = array();
                    foreach ( $domain_field as $item ) {
                        if ( is_array( $item ) ) {
                            if ( isset( $item['name'] ) ) {
                                $domain[] = sanitize_text_field( $item['name'] );
                            } elseif ( isset( $item['Name'] ) ) {
                                $domain[] = sanitize_text_field( $item['Name'] );
                            } elseif ( isset( $item['id'] ) ) {
                                $ids[] = $item['id'];
                            }
                        } elseif ( is_string( $item ) ) {
                            foreach ( self::explode_record_ids( $item ) as $maybe ) {
                                if ( self::contains_record_ids( array( $maybe ) ) ) {
                                    $ids[] = $maybe;
                                } else {
                                    $domain[] = sanitize_text_field( $maybe );
                                }
                            }
                        }
                    }
                    if ( ! empty( $ids ) ) {
                        $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Domain'], $ids );
                        if ( is_wp_error( $resolved ) ) {
                            if ( function_exists( 'error_log' ) ) {
                                error_log( 'TTP_Data: Failed resolving Domain: ' . $resolved->get_error_message() );
                            }
                        } else {
                            $resolved      = array_map( 'sanitize_text_field', (array) $resolved );
                            $domain        = array_merge( $domain, $resolved );
                            $domain_field  = array_merge( $domain_field, $resolved );
                        }
                    }
                } else {
                    $domain_values = self::parse_record_ids( $domain_field );
                    if ( self::contains_record_ids( $domain_values ) ) {
                        $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Domain'], $domain_values );
                        if ( is_wp_error( $resolved ) ) {
                            if ( function_exists( 'error_log' ) ) {
                                error_log( 'TTP_Data: Failed resolving Domain: ' . $resolved->get_error_message() );
                            }
                            $domain = array();
                        } else {
                            $domain       = array_map( 'sanitize_text_field', (array) $resolved );
                            $domain_field = $domain;
                        }
                    } else {
                        $domain = array_map( 'sanitize_text_field', $domain_values );
                    }
                }
            } else {
                $domain_values = is_string( $domain_field ) ? self::explode_record_ids( $domain_field ) : self::parse_record_ids( $domain_field );
                if ( self::contains_record_ids( $domain_values ) ) {
                    $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Domain'], $domain_values );
                    if ( is_wp_error( $resolved ) ) {
                        if ( function_exists( 'error_log' ) ) {
                            error_log( 'TTP_Data: Failed resolving Domain: ' . $resolved->get_error_message() );
                        }
                        $domain = array();
                    } else {
                        $domain       = array_map( 'sanitize_text_field', (array) $resolved );
                        $domain_field = $domain;
                    }
                } else {
                    $domain = $domain_values;
                }
            }

            $sub_field = $fields['Sub Categories'] ?? array();
            if ( is_string( $sub_field ) ) {
                $sub_field = self::explode_record_ids( $sub_field );
            } else {
                $sub_field = self::parse_record_ids( $sub_field );
            }
            $sub_categories = array();
            if ( self::contains_record_ids( $sub_field ) ) {
                $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Sub Categories'], $sub_field );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Data: Failed resolving Sub Categories: ' . $resolved->get_error_message() );
                    }
                    $sub_categories = array();
                } else {
                    $sub_categories = array_map( 'sanitize_text_field', (array) $resolved );
                    $sub_field      = $sub_categories;
                }
            } else {
                $sub_categories = array_map( 'sanitize_text_field', $sub_field );
            }

            $cap_field = $fields['Capabilities'] ?? array();
            if ( is_string( $cap_field ) ) {
                $cap_field = self::explode_record_ids( $cap_field );
            } else {
                $cap_field = self::parse_record_ids( $cap_field );
            }
            $capabilities = array();
            if ( self::contains_record_ids( $cap_field ) ) {
                $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Capabilities'], $cap_field );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Data: Failed resolving Capabilities: ' . $resolved->get_error_message() );
                    }
                    $capabilities = array();
                } else {
                    $capabilities = array_map( 'sanitize_text_field', (array) $resolved );
                    $cap_field    = $capabilities;
                }
            } else {
                $capabilities = array_map( 'sanitize_text_field', $cap_field );
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
     * Explode a comma-separated list of record IDs.
     *
     * @param string $value Raw string of IDs.
     * @return array Array of trimmed IDs.
     */
    private static function explode_record_ids( string $value ) {
        $value = trim( $value );
        if ( '' === $value ) {
            return array();
        }

        $parts = array_map( 'trim', explode( ',', $value ) );

        return array_filter(
            $parts,
            function ( $part ) {
                return $part !== '';
            }
        );
    }

    /**
     * Parse a raw field value into an array of IDs.
     *
     * Accepts a string of comma-separated values or an array and trims
     * whitespace from each entry.
     *
     * @param mixed $value Raw value to parse.
     * @return array Array of trimmed values.
     */
    private static function parse_record_ids( $value ) {
        if ( is_string( $value ) ) {
            $value = explode( ',', $value );
        }
        return array_filter( array_map( 'trim', (array) $value ) );
    }

    /**
     * Check if an array contains Airtable record IDs.
     *
     * @param array $values Values to inspect.
     * @return bool
     */
    private static function contains_record_ids( $values ) {
        foreach ( (array) $values as $value ) {
            if ( is_string( $value ) && strpos( ltrim( $value ), 'rec' ) === 0 ) {
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
