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
    const VENDOR_CACHE_VERSION = 1;

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
    private static function get_vendor_cache_key() {
        return self::VENDOR_CACHE_KEY . '_v' . self::VENDOR_CACHE_VERSION;
    }

    public static function get_all_vendors() {
        $vendors = get_transient( self::get_vendor_cache_key() );
        if ( $vendors !== false && ! self::vendors_need_resolution( $vendors ) ) {
            return $vendors;
        }

        $vendors = get_option( self::VENDOR_OPTION_KEY, array() );
        if ( self::vendors_need_resolution( $vendors ) ) {
            self::refresh_vendor_cache();
            $vendors = get_option( self::VENDOR_OPTION_KEY, array() );
        }

        set_transient( self::get_vendor_cache_key(), $vendors, self::CACHE_TTL );
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
        delete_transient( self::get_vendor_cache_key() );

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

    public static function register_cli_commands() {
        if ( ! class_exists( 'WP_CLI' ) ) {
            return;
        }

        \WP_CLI::add_command( 'ttp refresh-cache', array( __CLASS__, 'cli_refresh_cache' ) );
    }

    public static function cli_refresh_cache() {
        self::refresh_vendor_cache();
        if ( class_exists( 'WP_CLI' ) ) {
            \WP_CLI::success( 'Vendor cache refreshed.' );
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
            'region'        => 'regions',
            'sub_category'  => 'sub_categories',
            'capability'    => 'capabilities',
            'hosted_types'  => 'hosted_type',
            'domains'       => 'domain',
            'linked_vendor' => 'vendor',
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

            $fields = array( 'domain', 'regions', 'sub_categories', 'capabilities', 'hosted_type', 'vendor', 'categories', 'category' );
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
            'Category',
            'Sub Categories',
            'Capabilities',
            'HQ Location',
            'Founded Year',
            'Founders',
        );

        $schema     = TTP_Airbase::get_table_schema();
        $schema_map = array();
        $field_ids  = array();

        if ( ! is_wp_error( $schema ) && is_array( $schema ) ) {
            foreach ( $field_names as $name ) {
                $id               = isset( $schema[ $name ] ) ? $schema[ $name ] : $name;
                $schema_map[ $name ] = $id;
                $field_ids[]        = $id;
            }
        } else {
            $schema_map = array_combine( $field_names, $field_names );
            $field_ids  = $field_names;
        }

        $id_to_name = array_flip( $schema_map );

        $data = TTP_Airbase::get_vendors( $field_ids, true );
        if ( is_wp_error( $data ) ) {
            return;
        }

        $records = self::normalize_vendor_response( $data );

        foreach ( $records as &$record ) {
            if ( isset( $record['fields'] ) && is_array( $record['fields'] ) ) {
                $mapped = array();
                foreach ( $record['fields'] as $key => $value ) {
                    $mapped[ isset( $id_to_name[ $key ] ) ? $id_to_name[ $key ] : $key ] = $value;
                }
                $record['fields'] = $mapped;
            }
        }
        unset( $record );

        $present_keys = array();
        foreach ( $records as $record ) {
            if ( isset( $record['fields'] ) && is_array( $record['fields'] ) ) {
                $present_keys = array_unique( array_merge( $present_keys, array_keys( $record['fields'] ) ) );
            }
        }

        $missing = array_diff( $field_names, $present_keys );
        if ( ! empty( $missing ) ) {
            $field_map   = $schema_map;
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

            return new WP_Error(
                'ttp_missing_fields',
                'Missing expected vendor fields.',
                array(
                    'fields' => $missing,
                    'ids'    => $missing_ids,
                )
            );
        }

        $linked_fields = array(
            'Regions'        => array( 'key' => 'regions',        'table' => 'Regions',        'primary_field' => 'Name' ),
            'Linked Vendor'  => array( 'key' => 'vendor',         'table' => 'Vendors',        'primary_field' => 'Name',   'single' => true ),
            'Hosted Type'    => array( 'key' => 'hosted_type',    'table' => 'Hosted Type',    'primary_field' => 'Name' ),
            'Domain'         => array( 'key' => 'domain',         'table' => 'Domain',         'primary_field' => 'Domain' ),
            'Category'       => array( 'key' => 'categories',     'table' => 'Category',       'primary_field' => 'Name' ),
            'Sub Categories' => array( 'key' => 'sub_categories', 'table' => 'Sub Categories', 'primary_field' => 'Name' ),
            'Capabilities'   => array( 'key' => 'capabilities',   'table' => 'Capabilities',   'primary_field' => 'Name' ),
            'HQ Location'    => array( 'key' => 'hq_location',    'table' => 'HQ Location',    'primary_field' => 'Name',   'single' => true ),
        );

        $vendors = array();
        foreach ( $records as $record ) {
            $fields   = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : $record;
            $resolved = array();

            foreach ( $linked_fields as $label => $info ) {
                $values = self::resolve_linked_field( $fields, $label, $info['table'], $info['primary_field'] );
                if ( ! empty( $info['single'] ) ) {
                    $resolved[ $info['key'] ] = $values ? reset( $values ) : '';
                } else {
                    $resolved[ $info['key'] ] = $values;
                }
            }

            $categories     = $resolved['categories'];
            $sub_categories = $resolved['sub_categories'];
            $category       = $categories ? reset( $categories ) : '';
            $category_names = array_filter( array_merge( $categories, $sub_categories ) );

            $vendors[] = array(
                'id'              => sanitize_text_field( $record['id'] ?? '' ),
                'name'            => $fields['Product Name'] ?? '',
                'vendor'          => $resolved['vendor'],
                'website'         => self::normalize_url( $fields['Product Website'] ?? '' ),
                'video_url'       => self::normalize_url( $fields['Product Video'] ?? '' ),
                'status'          => $fields['Status'] ?? '',
                'hosted_type'     => $resolved['hosted_type'],
                'domain'          => $resolved['domain'],
                'regions'         => $resolved['regions'],
                'categories'      => $categories,
                'sub_categories'  => $sub_categories,
                'category'        => $category,
                'category_names'  => $category_names,
                'capabilities'    => $resolved['capabilities'],
                'logo_url'        => self::normalize_url( $fields['Logo URL'] ?? '' ),
                'hq_location'     => $resolved['hq_location'],
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
                $lines  = preg_split( '/\r\n|\n|\r/', $value );
                $parsed = array();

                foreach ( $lines as $line ) {
                    $line = trim( $line );
                    if ( $line === '' ) {
                        continue;
                    }

                    $fields = str_getcsv( $line );
                    if ( count( $fields ) <= 1 && strpos( $line, ';' ) !== false ) {
                        $fields = str_getcsv( $line, ';' );
                    }

                    $parsed = array_merge( $parsed, $fields );
                }

                $value = $parsed;
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
     * or mixed casing are detected. Numeric-only strings are also treated as
     * unresolved record IDs as some linked records may appear as plain numbers
     * in the source data.
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

            if ( $candidate === '' ) {
                continue;
            }

            if ( ctype_digit( $candidate ) ) {
                return true;
            }

            if (
                preg_match(
                    '/^r(?:ec|es|cs|cx)[0-9a-z]*\d[0-9a-z]*$/i',
                    $candidate
                )
            ) {
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

        $log_id = uniqid( 'ttp_', true );

        if ( function_exists( 'error_log' ) ) {
            error_log( sprintf( 'TTP_Data [%s]: Unresolved %s IDs: %s', $log_id, $field, implode( ', ', $ids ) ) );
        }

        if ( function_exists( 'get_option' ) && function_exists( 'update_option' ) ) {
            $existing   = (array) get_option( 'ttp_unresolved_fields', array() );
            $existing[] = array(
                'id'      => $log_id,
                'message' => sprintf( '%s unresolved IDs: %s', $field, implode( ', ', $ids ) ),
            );
            update_option( 'ttp_unresolved_fields', $existing );
        }
    }

    /**
     * Resolve linked record IDs to their corresponding names.
     *
     * Parses the raw value, replaces any Airtable record IDs with the
     * primary-field names retrieved via the API, and sanitizes the final
     * values. Unresolved IDs are logged and removed from the result.
     *
     * @param array  $record        Source record.
     * @param string $field         Field label for logging and lookup.
     * @param string $table         Airtable table name.
     * @param string $primary_field Primary field name in linked table.
     * @return array Sanitized values with IDs replaced by names.
     */
    private static function resolve_linked_field( $record, $field, $table, $primary_field ) {
        $value        = $record[ $field ] ?? array();
        $values       = self::parse_record_ids( $value );
        $placeholders = array();
        $ids          = array();

        foreach ( $values as $idx => $item ) {
            $item = (string) $item;
            if ( self::contains_record_ids( array( $item ) ) ) {
                $clean               = preg_replace( '/[^A-Za-z0-9]/', '', $item );
                $placeholders[ $idx ] = $clean;
                $ids[]               = $clean;
            } else {
                $values[ $idx ] = sanitize_text_field( $item );
            }
        }

        if ( ! empty( $ids ) ) {
            $resolved = TTP_Airbase::resolve_linked_records( $table, $ids, $primary_field );
            if ( is_wp_error( $resolved ) ) {
                if ( function_exists( 'error_log' ) ) {
                    $ids_str = implode( ', ', array_map( 'sanitize_text_field', $ids ) );
                    error_log( sprintf( 'TTP_Data: Failed resolving %s for record IDs %s: %s', $field, $ids_str, $resolved->get_error_message() ) );
                }
                self::log_unresolved_field( $field, $ids );
                foreach ( $placeholders as $idx => $id ) {
                    unset( $values[ $idx ] );
                }
            } else {
                $resolved = array_map( 'sanitize_text_field', (array) $resolved );
                if ( count( $resolved ) < count( $ids ) ) {
                    $missing = array_slice( $ids, count( $resolved ) );
                    self::log_unresolved_field( $field, $missing );
                }
                $i = 0;
                foreach ( $placeholders as $idx => $id ) {
                    $values[ $idx ] = $resolved[ $i ] ?? '';
                    $i++;
                }
            }
        }

        return array_values( array_filter( $values ) );
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

        if (!empty($args['category'])) {
            $parents = (array) $args['category'];
            $tools   = array_filter($tools, function ($tool) use ($parents) {
                return isset($tool['category']) && in_array($tool['category'], $parents, true);
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
