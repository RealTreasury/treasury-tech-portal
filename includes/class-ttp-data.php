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
     * Indicates when the vendor cache is actively being refreshed.
     * Prevents recursive refresh calls when saving inside the refresh
     * routine itself.
     *
     * @var bool
     */
    private static $refreshing_vendors = false;

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
        $vendors = get_transient( self::VENDOR_CACHE_KEY );
        if ( $vendors !== false && ! self::vendors_need_resolution( $vendors ) ) {
            return $vendors;
        }

        $vendors = get_option( self::VENDOR_OPTION_KEY, array() );
        if ( self::vendors_need_resolution( $vendors ) ) {
            self::refresh_vendor_cache();
            $vendors = get_option( self::VENDOR_OPTION_KEY, array() );
        }

        set_transient( self::VENDOR_CACHE_KEY, $vendors, self::CACHE_TTL );
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

        // Immediately refresh the vendor cache so stored data is normalised
        // before any subsequent access. Use a guard to avoid recursive calls
        // when this method is invoked from within the refresh routine itself.
        if ( ! self::$refreshing_vendors ) {
            self::$refreshing_vendors = true;

            // Trigger the refresh through the existing action hook to ensure
            // consistent behaviour with scheduled events.
            do_action( 'ttp_refresh_vendor_cache' );

            self::$refreshing_vendors = false;
        }
    }

    /**
     * Determine if vendor data contains unresolved record IDs.
     *
     * @param array $vendors Vendor records to inspect.
     * @return bool
     */
    private static function vendors_need_resolution( $vendors ) {
        $aliases = array(
            'region'            => 'regions',
            'sub_category'      => 'sub_categories',
            'capability'        => 'capabilities',
            'hosted_types'      => 'hosted_type',
            'domains'           => 'domain',
            'parent_categories' => 'parent_category',
        );

        foreach ( (array) $vendors as $vendor ) {
            $normalized = array();

            foreach ( (array) $vendor as $key => $value ) {
                $normalized_key = strtolower( str_replace( ' ', '_', $key ) );
                $normalized_key = preg_replace( '/_ids?$/', '', $normalized_key );
                if ( isset( $aliases[ $normalized_key ] ) ) {
                    $normalized_key = $aliases[ $normalized_key ];
                }
                $normalized[ $normalized_key ] = $value;
            }

            $fields = array( 'domain', 'regions', 'sub_categories', 'capabilities', 'hosted_type', 'parent_category' );
            foreach ( $fields as $field ) {
                if ( ! empty( $normalized[ $field ] ) && self::contains_record_ids( (array) $normalized[ $field ] ) ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Refresh vendor cache from Airbase.
     */
    public static function refresh_vendor_cache() {
        $field_names = array(
            'Product Name',
            'Linked Vendor',
            'Product Website',
            'Product Video',
            'Logo URL',
            'Status',
            'Hosted Type',
            'Domain',
            'Regions',
            'Sub Categories',
            'Parent Category',
            'Capabilities',
            'HQ Location',
            'Founded Year',
            'Founders',
        );

        $schema    = TTP_Airbase::get_table_schema();
        $field_ids = array();

        if ( ! is_wp_error( $schema ) && is_array( $schema ) ) {
            foreach ( $field_names as $name ) {
                $field_ids[] = isset( $schema[ $name ] ) ? $schema[ $name ] : $name;
            }
        } else {
            $field_ids = $field_names;
        }

        $data = TTP_Airbase::get_vendors( $field_ids );
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

        $missing = array_diff( $field_names, $present_keys );
        if ( ! empty( $missing ) ) {
            $field_map   = array_combine( $field_names, $field_ids );
            $missing_ids = array();
            foreach ( $missing as $field ) {
                if ( isset( $field_map[ $field ] ) ) {
                    $missing_ids[] = $field_map[ $field ];
                }
            }
            if ( function_exists( 'error_log' ) ) {
                $message = 'TTP_Data: Missing expected fields: ' . implode( ', ', $missing );
                if ( ! empty( $missing_ids ) ) {
                    $message .= ' (requested IDs: ' . implode( ', ', $missing_ids ) . ')';
                }
                error_log( $message );
            }
            if ( function_exists( 'sanitize_text_field' ) ) {
                $missing     = array_map( 'sanitize_text_field', $missing );
                $missing_ids = array_map( 'sanitize_text_field', $missing_ids );
            }
            if ( function_exists( 'update_option' ) ) {
                update_option( 'ttp_missing_fields', array(
                    'fields' => $missing,
                    'ids'    => $missing_ids,
                ) );
            }
        }

        $linked_tables = array(
            'Regions'        => array( 'table' => 'Regions',        'primary_field' => 'Name' ),
            'Linked Vendor'  => array( 'table' => 'Vendors',        'primary_field' => 'Name' ),
            'Hosted Type'    => array( 'table' => 'Hosted Type',    'primary_field' => 'Name' ),
            'Domain'         => array( 'table' => 'Domain',         'primary_field' => 'Domain' ),
            'Sub Categories' => array( 'table' => 'Sub Categories', 'primary_field' => 'Name' ),
            'Parent Category' => array( 'table' => 'Category',      'primary_field' => 'Name' ),
            'Capabilities'   => array( 'table' => 'Capabilities',   'primary_field' => 'Name' ),
        );

        $vendors = array();
        foreach ($records as $record) {
            $fields = isset($record['fields']) && is_array($record['fields']) ? $record['fields'] : $record;

            $regions_field = self::parse_record_ids( $fields['Regions'] ?? array() );
            $regions       = array();
            $region_ids    = array();

            foreach ( (array) $regions_field as $item ) {
                $item = (string) $item;
                if ( self::contains_record_ids( array( $item ) ) ) {
                    $clean       = preg_replace( '/[^A-Za-z0-9]/', '', $item );
                    $region_ids[] = $clean;
                    $regions[]    = $clean; // placeholder for order
                } else {
                    $regions[] = sanitize_text_field( $item );
                }
            }

            if ( ! empty( $region_ids ) ) {
                $resolved = TTP_Airbase::resolve_linked_records( $linked_tables['Regions']['table'], $region_ids, $linked_tables['Regions']['primary_field'] );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        $ids = implode( ', ', array_map( 'sanitize_text_field', $region_ids ) );
                        error_log( sprintf( 'TTP_Data: Failed resolving Regions for record IDs %s: %s', $ids, $resolved->get_error_message() ) );
                    }
                    self::log_unresolved_field( 'Regions', $region_ids );
                    // remove placeholders for IDs on error
                    $regions = array_filter( $regions, function ( $val ) use ( $region_ids ) {
                        return ! in_array( $val, $region_ids, true );
                    } );
                } else {
                    $resolved = array_map( 'sanitize_text_field', (array) $resolved );
                    if ( count( $resolved ) < count( $region_ids ) ) {
                        $missing = array_slice( $region_ids, count( $resolved ) );
                        self::log_unresolved_field( 'Regions', $missing );
                    }
                    $i = 0;
                    foreach ( $regions as $idx => $val ) {
                        if ( in_array( $val, $region_ids, true ) ) {
                            $regions[ $idx ] = $resolved[ $i ] ?? '';
                            $i++;
                        }
                    }
                }
            }

            $regions = array_values( array_filter( $regions ) );

            $vendor_field = self::parse_record_ids( $fields['Linked Vendor'] ?? array() );
            $vendor_name  = '';
            if ( self::contains_record_ids( $vendor_field ) ) {
                $original_vendor_ids = $vendor_field;
                $resolved             = TTP_Airbase::resolve_linked_records( $linked_tables['Linked Vendor']['table'], $vendor_field, $linked_tables['Linked Vendor']['primary_field'] );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Data: Failed resolving Linked Vendor: ' . $resolved->get_error_message() );
                    }
                    self::log_unresolved_field( 'Linked Vendor', $original_vendor_ids );
                } elseif ( ! empty( $resolved ) ) {
                    if ( count( (array) $resolved ) < count( (array) $original_vendor_ids ) ) {
                        $missing = array_slice( (array) $original_vendor_ids, count( (array) $resolved ) );
                        self::log_unresolved_field( 'Linked Vendor', $missing );
                    }
                    $vendor_field = array_map( 'sanitize_text_field', (array) $resolved );
                    $vendor_name  = $vendor_field ? reset( $vendor_field ) : '';
                }
            } elseif ( ! empty( $vendor_field ) ) {
                $vendor_field = array_map( 'sanitize_text_field', $vendor_field );
                $vendor_name  = $vendor_field ? reset( $vendor_field ) : '';
            }

            $hosted_field = self::parse_record_ids( $fields['Hosted Type'] ?? array() );
            $hosted_type  = array();
            if ( self::contains_record_ids( $hosted_field ) ) {
                $original_hosted_ids = $hosted_field;
                $resolved            = TTP_Airbase::resolve_linked_records( $linked_tables['Hosted Type']['table'], $hosted_field, $linked_tables['Hosted Type']['primary_field'] );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Data: Failed resolving Hosted Type: ' . $resolved->get_error_message() );
                    }
                    self::log_unresolved_field( 'Hosted Type', $original_hosted_ids );
                } else {
                    if ( count( (array) $resolved ) < count( (array) $original_hosted_ids ) ) {
                        $missing = array_slice( (array) $original_hosted_ids, count( (array) $resolved ) );
                        self::log_unresolved_field( 'Hosted Type', $missing );
                    }
                    $hosted_type  = array_map( 'sanitize_text_field', (array) $resolved );
                    $hosted_field = $hosted_type;
                }
            } else {
                $hosted_type = array_map( 'sanitize_text_field', $hosted_field );
            }

            $domain_field = self::parse_record_ids( $fields['Domain'] ?? array() );
            $domain       = array();
            if ( self::contains_record_ids( $domain_field ) ) {
                $original_domain_ids = $domain_field;
                $resolved            = TTP_Airbase::resolve_linked_records( $linked_tables['Domain']['table'], $domain_field, $linked_tables['Domain']['primary_field'] );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Data: Failed resolving Domain: ' . $resolved->get_error_message() );
                    }
                    self::log_unresolved_field( 'Domain', $original_domain_ids );
                    $domain = array();
                } else {
                    if ( count( (array) $resolved ) < count( (array) $original_domain_ids ) ) {
                        $missing = array_slice( (array) $original_domain_ids, count( (array) $resolved ) );
                        self::log_unresolved_field( 'Domain', $missing );
                    }
                    $domain       = array_map( 'sanitize_text_field', (array) $resolved );
                    $domain_field = $domain;
                }
            } else {
                $domain = array_map( 'sanitize_text_field', $domain_field );
            }

            $sub_field      = self::parse_record_ids( $fields['Sub Categories'] ?? array() );
            $sub_categories = array();
            if ( self::contains_record_ids( $sub_field ) ) {
                $original_sub_ids = $sub_field;
                $resolved         = TTP_Airbase::resolve_linked_records( $linked_tables['Sub Categories']['table'], $sub_field, $linked_tables['Sub Categories']['primary_field'] );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Data: Failed resolving Sub Categories: ' . $resolved->get_error_message() );
                    }
                    self::log_unresolved_field( 'Sub Categories', $original_sub_ids );
                    $sub_categories = array();
                } else {
                    if ( count( (array) $resolved ) < count( (array) $original_sub_ids ) ) {
                        $missing = array_slice( (array) $original_sub_ids, count( (array) $resolved ) );
                        self::log_unresolved_field( 'Sub Categories', $missing );
                    }
                    $sub_categories = array_map( 'sanitize_text_field', (array) $resolved );
                    $sub_field      = $sub_categories;
                }
            } else {
                $sub_categories = array_map( 'sanitize_text_field', $sub_field );
            }

            $cap_field    = self::parse_record_ids( $fields['Capabilities'] ?? array() );
            $capabilities = array();
            if ( self::contains_record_ids( $cap_field ) ) {
                $original_cap_ids = $cap_field;
                $resolved         = TTP_Airbase::resolve_linked_records( $linked_tables['Capabilities']['table'], $cap_field, $linked_tables['Capabilities']['primary_field'] );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Data: Failed resolving Capabilities: ' . $resolved->get_error_message() );
                    }
                    self::log_unresolved_field( 'Capabilities', $original_cap_ids );
                    $capabilities = array();
                } else {
                    if ( count( (array) $resolved ) < count( (array) $original_cap_ids ) ) {
                        $missing = array_slice( (array) $original_cap_ids, count( (array) $resolved ) );
                        self::log_unresolved_field( 'Capabilities', $missing );
                    }
                    $capabilities = array_map( 'sanitize_text_field', (array) $resolved );
                    $cap_field    = $capabilities;
                }
            } else {
                $capabilities = array_map( 'sanitize_text_field', $cap_field );
            }

            $parent_field    = self::parse_record_ids( $fields['Parent Category'] ?? array() );
            $parent_category = '';
            if ( self::contains_record_ids( $parent_field ) ) {
                $original_parent_ids = $parent_field;
                $resolved             = TTP_Airbase::resolve_linked_records( $linked_tables['Parent Category']['table'], $parent_field, $linked_tables['Parent Category']['primary_field'] );
                if ( is_wp_error( $resolved ) ) {
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Data: Failed resolving Parent Category: ' . $resolved->get_error_message() );
                    }
                    self::log_unresolved_field( 'Parent Category', $original_parent_ids );
                } else {
                    if ( count( (array) $resolved ) < count( (array) $original_parent_ids ) ) {
                        $missing = array_slice( (array) $original_parent_ids, count( (array) $resolved ) );
                        self::log_unresolved_field( 'Parent Category', $missing );
                    }
                    $parent_field    = array_map( 'sanitize_text_field', (array) $resolved );
                    $parent_category = $parent_field ? reset( $parent_field ) : '';
                }
            } else {
                $parent_field    = array_map( 'sanitize_text_field', (array) $parent_field );
                $parent_category = $parent_field ? reset( $parent_field ) : '';
            }

            $category_names = array_filter( array_merge( $parent_category ? array( $parent_category ) : array(), $sub_categories ) );

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
     * Parse a raw field value into an array of IDs or names.
     *
     * Handles comma-separated strings, JSON strings and nested arrays. When
     * encountering array items, extracts the `id` or `name` properties when
     * present.
     *
     * @param mixed $value Raw value to parse.
     * @return array Array of trimmed values.
     */
    private static function parse_record_ids( $value ) {
        if ( is_string( $value ) ) {
            $maybe_json = json_decode( $value, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $value = $maybe_json;
            } else {
                $value = preg_split( '/[\\n;,]+/', $value );
            }
        }

        $results = array();

        foreach ( (array) $value as $item ) {
            if ( is_array( $item ) ) {
                if ( isset( $item['name'] ) ) {
                    $results[] = trim( $item['name'] );
                } elseif ( isset( $item['id'] ) ) {
                    $results[] = trim( $item['id'] );
                } else {
                    $results = array_merge( $results, self::parse_record_ids( $item ) );
                }
            } else {
                $results[] = trim( (string) $item );
            }
        }

        return array_filter( $results );
    }

    /**
     * Check if an array contains Airtable record IDs.
     *
     * Strips non-alphanumeric characters and performs a case-insensitive search
     * for the `rec` prefix anywhere in the value so IDs wrapped in extra text
     * or mixed casing are detected.
     *
     * @param array $values Values to inspect.
     * @return bool
     */
    private static function contains_record_ids( $values ) {
        foreach ( (array) $values as $value ) {
            if ( is_array( $value ) && self::contains_record_ids( $value ) ) {
                return true;
            }

            $candidate = preg_replace( '/[^A-Za-z0-9]/', '', (string) $value );

            if ( preg_match( '/rec[0-9a-z]{3,}/i', $candidate ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Log unresolved Airtable record IDs for visibility in logs and admin.
     *
     * Stores a transient notice via the options API when available so admin
     * users can be alerted, and always writes to `error_log` when that
     * function exists.
     *
     * @param string $field Field label.
     * @param array  $ids   IDs that failed to resolve.
     */
    private static function log_unresolved_field( $field, $ids ) {
        $ids = array_filter( (array) $ids );
        if ( empty( $ids ) ) {
            return;
        }

        if ( function_exists( 'sanitize_text_field' ) ) {
            $ids = array_map( 'sanitize_text_field', $ids );
        }

        if ( function_exists( 'error_log' ) ) {
            error_log( sprintf( 'TTP_Data: Unresolved %s IDs: %s', $field, implode( ', ', $ids ) ) );
        }

        if ( function_exists( 'get_option' ) && function_exists( 'update_option' ) ) {
            $existing   = (array) get_option( 'ttp_unresolved_fields', array() );
            $existing[] = sprintf( '%s unresolved IDs: %s', $field, implode( ', ', $ids ) );
            update_option( 'ttp_unresolved_fields', $existing );
        }
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
