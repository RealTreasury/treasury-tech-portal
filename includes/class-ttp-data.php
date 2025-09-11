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
        // Ensure legacy caches with semicolon-delimited values are normalised
        // before attempting to read from the transient cache. This migration is
        // lightweight and will no-op once data is updated.
        self::migrate_semicolon_cache();

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
     * Migration: normalise semicolon-delimited values stored in the vendor cache.
     *
     * Older caches may contain strings with semicolon separators where arrays of
     * values were expected. Scan the cached vendors and split any such strings
     * into arrays so downstream logic receives the canonical format.
     */
    private static function migrate_semicolon_cache() {
        $vendors = get_option( self::VENDOR_OPTION_KEY, array() );
        $updated = false;

        foreach ( $vendors as &$vendor ) {
            $fields = array( 'regions', 'hosted_type', 'domain', 'categories', 'sub_categories', 'capabilities' );

            foreach ( $fields as $field ) {
                if ( ! isset( $vendor[ $field ] ) ) {
                    continue;
                }

                $original   = $vendor[ $field ];
                $values     = is_array( $original ) ? $original : array( $original );
                $normalized = array();

                foreach ( $values as $value ) {
                    if ( is_string( $value ) && strpos( $value, ';' ) !== false ) {
                        $normalized = array_merge( $normalized, self::parse_record_ids( $value ) );
                    } else {
                        $normalized[] = $value;
                    }
                }

                $normalized = array_values( array_filter( array_map( 'trim', $normalized ) ) );

                if ( $normalized !== $values ) {
                    $vendor[ $field ] = $normalized;
                    $updated          = true;
                }
            }

            if ( isset( $vendor['categories'] ) || isset( $vendor['sub_categories'] ) ) {
                $categories     = isset( $vendor['categories'] ) ? (array) $vendor['categories'] : array();
                $sub_categories = isset( $vendor['sub_categories'] ) ? (array) $vendor['sub_categories'] : array();

                $category       = $categories ? reset( $categories ) : '';
                $category_names = array_filter( array_merge( $categories, $sub_categories ) );

                if ( ! isset( $vendor['category'] ) || $vendor['category'] !== $category ) {
                    $vendor['category'] = $category;
                    $updated            = true;
                }

                if ( ! isset( $vendor['category_names'] ) || $vendor['category_names'] !== $category_names ) {
                    $vendor['category_names'] = $category_names;
                    $updated                  = true;
                }
            }
        }
        unset( $vendor );

        if ( $updated ) {
            update_option( self::VENDOR_OPTION_KEY, $vendors );
            delete_transient( self::get_vendor_cache_key() );
            set_transient( self::get_vendor_cache_key(), $vendors, self::CACHE_TTL );
        }
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
     * Normalize a field key to lowercase snake_case.
     *
     * Converts camelCase, spaces and hyphens to underscores and collapses
     * consecutive separators so keys can be compared case-insensitively.
     *
     * @param string $key Raw field key.
     * @return string Normalized key.
     */
    private static function normalize_key( $key ) {
        $key = (string) $key;
        $key = preg_replace( '/([a-z0-9])([A-Z])/', '$1_$2', $key );
        $key = strtolower( str_replace( array( ' ', '-' ), '_', $key ) );
        $key = preg_replace( '/_+/', '_', $key );
        return $key;
    }

    /**
     * Recursively normalize array keys to lowercase snake_case.
     *
     * @param array $data Array to normalize.
     * @return array Normalized array.
     */
    private static function normalize_keys( $data ) {
        $normalized = array();
        foreach ( (array) $data as $key => $value ) {
            $new_key = is_int( $key ) ? $key : self::normalize_key( $key );
            if ( is_array( $value ) ) {
                $value = self::normalize_keys( $value );
            }
            $normalized[ $new_key ] = $value;
        }
        return $normalized;
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

        $fields = array( 'domain', 'regions', 'sub_categories', 'capabilities', 'hosted_type', 'vendor', 'categories', 'category' );

        $vendors = array_map( array( __CLASS__, 'normalize_keys' ), (array) $vendors );

        $walker = function ( $data ) use ( &$walker, $aliases, $fields ) {
            foreach ( (array) $data as $key => $value ) {
                $normalized_key = preg_replace( '/_ids?$/', '', $key );
                if ( isset( $aliases[ $normalized_key ] ) ) {
                    $normalized_key = $aliases[ $normalized_key ];
                }

                if ( in_array( $normalized_key, $fields, true ) ) {
                    if ( ! empty( $value ) && self::contains_record_ids( (array) $value ) ) {
                        return true;
                    }
                }

                if ( is_array( $value ) || is_object( $value ) ) {
                    if ( $walker( $value ) ) {
                        return true;
                    }
                }
            }
            return false;
        };

        foreach ( (array) $vendors as $vendor ) {
            if ( $walker( $vendor ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Refresh vendor cache from Airbase.
     */
    public static function refresh_vendor_cache() {
        do_action( 'rt_refresh_vendors' );

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

        $normalized_field_names = array();
        $normalized_to_label    = array();
        foreach ( $field_names as $name ) {
            $normalized_field_names[]          = self::normalize_key( $name );
            $normalized_to_label[ self::normalize_key( $name ) ] = $name;
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

        foreach ( $linked_fields as $label => &$info ) {
            $info['field'] = self::normalize_key( $label );
        }
        unset( $info );

        $mapping    = TTP_Airbase::map_field_names( $field_names );
        $schema_map = $mapping['schema_map'];
        $field_ids  = $mapping['field_ids'];

        $missing_linked = array();
        foreach ( $linked_fields as $label => $info ) {
            if ( ! isset( $schema_map[ $label ] ) || $schema_map[ $label ] === $label ) {
                if ( function_exists( 'error_log' ) ) {
                    error_log( sprintf( 'TTP_Data: Field %s missing from schema; skipping resolution', $label ) );
                }
                $missing_linked[ $label ] = $info;
                unset( $linked_fields[ $label ] );
                $field_names             = array_diff( $field_names, array( $label ) );
                $normalized_field_names  = array_diff( $normalized_field_names, array( $info['field'] ) );
                unset( $normalized_to_label[ $info['field'] ] );
            }
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
                    $mapped_name = isset( $id_to_name[ $key ] ) ? $id_to_name[ $key ] : $key;
                    $mapped[ $mapped_name ] = $value;
                }
                $record['fields'] = self::normalize_keys( $mapped );
            } else {
                $record = self::normalize_keys( $record );
            }
        }
        unset( $record );

        $field_to_linked = array(
            'regions'        => 'Regions',
            'category'       => 'Categories',
            'sub_categories' => 'Sub Categories',
            'capabilities'   => 'Capabilities',
        );
        $needs_map       = false;
        foreach ( $records as $r ) {
            foreach ( array_keys( $field_to_linked ) as $fname ) {
                $v = $r['fields'][ $fname ] ?? null;
                if ( ( is_string( $v ) && preg_match( '/^rec[a-zA-Z0-9]{10,}$/', $v ) ) || ( is_array( $v ) && array_filter( $v, function ( $x ) {
                    return is_string( $x ) && preg_match( '/^rec[a-zA-Z0-9]{10,}$/', $x );
                } ) ) ) {
                    $needs_map = true;
                    break 2;
                }
            }
        }
        if ( $needs_map ) {
            $base_id = get_option( TTP_Airbase::OPTION_BASE_ID, TTP_Airbase::DEFAULT_BASE_ID );
            $token   = get_option( TTP_Airbase::OPTION_TOKEN );
            $records = rt_airtable_map_ids_to_names( $records, $field_to_linked, $base_id, $token );
        }

        $present_keys = array();
        foreach ( $records as $record ) {
            $fields_arr  = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : ( is_array( $record ) ? $record : array() );
            $present_keys = array_unique( array_merge( $present_keys, array_keys( $fields_arr ) ) );
        }

        $missing_normalized = array_diff( $normalized_field_names, $present_keys );
        if ( ! empty( $missing_normalized ) ) {
            $missing = array();
            foreach ( $missing_normalized as $key ) {
                $missing[] = $normalized_to_label[ $key ] ?? $key;
            }
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

        $vendors = array();
        foreach ( $records as $record ) {
            $fields   = isset( $record['fields'] ) && is_array( $record['fields'] ) ? $record['fields'] : $record;
            $resolved = array();

            foreach ( $linked_fields as $label => $info ) {
                $values = self::resolve_linked_field( $fields, $info['field'], $info['table'], $info['primary_field'] );
                if ( ! empty( $info['single'] ) ) {
                    $resolved[ $info['key'] ] = $values ? reset( $values ) : '';
                } else {
                    $resolved[ $info['key'] ] = $values;
                }
            }

            foreach ( $missing_linked as $label => $info ) {
                $field_key  = $info['field'];
                $raw_values = self::parse_record_ids( $fields[ $field_key ] ?? array() );
                $raw_values = array_map( 'sanitize_text_field', $raw_values );

                $ids_to_log = array();
                foreach ( $raw_values as $val ) {
                    if ( self::contains_record_ids( array( $val ) ) ) {
                        $ids_to_log[] = preg_replace( '/[^A-Za-z0-9]/', '', (string) $val );
                    }
                }

                if ( ! empty( $ids_to_log ) ) {
                    self::log_unresolved_field( $label, $ids_to_log );
                }

                if ( ! empty( $info['single'] ) ) {
                    $resolved[ $info['key'] ] = $raw_values ? reset( $raw_values ) : '';
                } else {
                    $resolved[ $info['key'] ] = $raw_values;
                }
            }

            $categories     = $resolved['categories'];
            $sub_categories = $resolved['sub_categories'];
            $category       = $categories ? reset( $categories ) : '';
            $category_names = array_filter( array_merge( $categories, $sub_categories ) );

            $vendors[] = array(
                'id'              => sanitize_text_field( $record['id'] ?? '' ),
                'name'            => $fields['product_name'] ?? '',
                'vendor'          => $resolved['vendor'],
                'website'         => self::normalize_url( $fields['product_website'] ?? '' ),
                'video_url'       => self::normalize_url( $fields['product_video'] ?? '' ),
                'status'          => $fields['status'] ?? '',
                'hosted_type'     => $resolved['hosted_type'],
                'domain'          => $resolved['domain'],
                'regions'         => $resolved['regions'],
                'categories'      => $categories,
                'sub_categories'  => $sub_categories,
                'category'        => $category,
                'category_names'  => $category_names,
                'capabilities'    => $resolved['capabilities'],
                'logo_url'        => self::normalize_url( $fields['logo_url'] ?? '' ),
                'hq_location'     => $resolved['hq_location'],
                'founded_year'    => $fields['founded_year'] ?? '',
                'founders'        => $fields['founders'] ?? '',
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
     * Recursively searches strings and arrays for `name` or `id` keys at any
     * depth. Strings are treated as JSON when possible or split on common
     * delimiters. When both `name` and `id` exist in the same structure the
     * `name` is preferred.
     *
     * Example:
     * `parse_record_ids( '{"wrapper":{"item":{"name":"A"}}}' )` returns
     * `array( 'A' )`.
     *
     * Limitations:
     * - Returned values are not deduplicated.
     * - Only simple string delimiters (comma, semicolon, newline) are parsed.
     *
     * @param mixed $value Raw value to parse.
     * @return array Array of trimmed values.
     */
    private static function parse_record_ids( $value ) {
        if ( is_string( $value ) ) {
            $maybe_json = json_decode( $value, true );
            if ( json_last_error() === JSON_ERROR_NONE && ( is_array( $maybe_json ) || is_object( $maybe_json ) ) ) {
                return self::parse_record_ids( $maybe_json );
            }

            $lines  = preg_split( '/\r\n|\n|\r/', $value );
            $parsed = array();

            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( $line === '' ) {
                    continue;
                }

                // Replace semicolons with commas when outside of quoted sections so
                // both delimiters are treated equally while preserving literal
                // semicolons within quoted names.
                $normalized = '';
                $in_quotes  = false;
                $length     = strlen( $line );
                for ( $i = 0; $i < $length; $i++ ) {
                    $char = $line[ $i ];
                    if ( '"' === $char ) {
                        $in_quotes = ! $in_quotes;
                    }
                    if ( ! $in_quotes && ';' === $char ) {
                        $char = ',';
                    }
                    $normalized .= $char;
                }

                $fields = str_getcsv( $normalized );
                $parsed = array_merge( $parsed, $fields );
            }

            return array_map( 'trim', $parsed );
        }

        $results = array();

        foreach ( (array) $value as $item ) {
            if ( is_array( $item ) ) {
                if ( isset( $item['name'] ) ) {
                    $results[] = trim( $item['name'] );
                } elseif ( isset( $item['text'] ) ) {
                    $results[] = trim( $item['text'] );
                } elseif ( isset( $item['value'] ) ) {
                    $results[] = trim( $item['value'] );
                } elseif ( isset( $item['id'] ) ) {
                    $results[] = trim( $item['id'] );
                }

                $nested = array_diff_key( $item, array_flip( array( 'name', 'id', 'text', 'value' ) ) );
                if ( ! empty( $nested ) ) {
                    $results = array_merge( $results, self::parse_record_ids( $nested ) );
                }
            } elseif ( is_string( $item ) ) {
                $results = array_merge( $results, self::parse_record_ids( $item ) );
            } elseif ( is_numeric( $item ) ) {
                $results[] = $item + 0;
            } else {
                $results[] = trim( (string) $item );
            }
        }

        return array_filter(
            $results,
            function ( $v ) {
                return '' !== $v && null !== $v;
            }
        );
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
            if ( is_array( $value ) || is_object( $value ) ) {
                if ( self::contains_record_ids( (array) $value ) ) {
                    return true;
                }
                continue;
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
                    '/^(?:r(?:ec|es|cs|cx)|sel|opt)[0-9a-z]*\d[0-9a-z]*$/i',
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
     * Records unresolved IDs grouped by field in the `ttp_unresolved_report`
     * option and always writes to `error_log` when that function exists.
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
            $ids  = array_map( 'sanitize_text_field', $ids );
            $field = sanitize_text_field( $field );
        }

        $log_id = uniqid( 'ttp_', true );

        if ( function_exists( 'error_log' ) ) {
            error_log( sprintf( 'TTP_Data [%s]: Unresolved %s IDs: %s', $log_id, $field, implode( ', ', $ids ) ) );
        }

        if ( function_exists( 'get_option' ) && function_exists( 'update_option' ) ) {
            $report = (array) get_option( 'ttp_unresolved_report', array() );
            if ( ! isset( $report[ $field ] ) ) {
                $report[ $field ] = array();
            }
            $report[ $field ] = array_values( array_unique( array_merge( $report[ $field ], $ids ) ) );
            update_option( 'ttp_unresolved_report', $report );
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
            if ( is_string( $item ) && self::contains_record_ids( array( $item ) ) ) {
                $clean               = preg_replace( '/[^A-Za-z0-9]/', '', $item );
                $placeholders[ $idx ] = $clean;
                $ids[]               = $clean;
            } else {
                if ( is_string( $item ) ) {
                    $values[ $idx ] = sanitize_text_field( $item );
                } elseif ( is_numeric( $item ) ) {
                    $values[ $idx ] = $item + 0;
                }
            }
        }

        if ( ! empty( $ids ) ) {
            /**
             * Swap placeholder IDs with their readable names using
             * TTP_Airbase::resolve_linked_records(). See self::resolve_linked_field()
             * for the full placeholder-replacement strategy.
             */
            $attempt       = 0;
            $max_attempts  = 3;
            $use_field_ids = empty( $primary_field );

            do {
                $resolved = TTP_Airbase::resolve_linked_records( $table, $ids, $primary_field, $use_field_ids );
                $attempt++;
                if ( ! is_wp_error( $resolved ) ) {
                    break;
                }
            } while ( $attempt < $max_attempts );

            if ( is_wp_error( $resolved ) ) {
                if ( function_exists( 'error_log' ) ) {
                    $ids_str = implode( ', ', array_map( 'sanitize_text_field', $ids ) );
                    error_log( sprintf( 'TTP_Data: Failed resolving %s in %s for record IDs %s: %s', $field, $table, $ids_str, $resolved->get_error_message() ) );
                }
                self::log_unresolved_field( $field, $ids );
                foreach ( $placeholders as $idx => $id ) {
                    unset( $values[ $idx ] );
                }
            } else {
                $sanitized = array();
                foreach ( (array) $resolved as $val ) {
                    if ( is_string( $val ) ) {
                        $sanitized[] = sanitize_text_field( $val );
                    } elseif ( is_numeric( $val ) ) {
                        $sanitized[] = $val + 0;
                    }
                }

                if ( empty( $sanitized ) ) {
                    self::log_unresolved_field( $field, $ids );
                    foreach ( $placeholders as $idx => $id ) {
                        unset( $values[ $idx ] );
                    }
                } else {
                    if ( count( $sanitized ) < count( $ids ) ) {
                        $missing = array_slice( $ids, count( $sanitized ) );
                        self::log_unresolved_field( $field, $missing );
                    }

                    $i = 0;
                    foreach ( $placeholders as $idx => $id ) {
                        if ( isset( $sanitized[ $i ] ) ) {
                            $values[ $idx ] = $sanitized[ $i ];
                        } else {
                            unset( $values[ $idx ] );
                        }
                        $i++;
                    }
                }
            }
        }

        $values = array_values(
            array_filter(
                $values,
                function ( $v ) {
                    return $v !== null && $v !== '' && $v !== false;
                }
            )
        );

        if ( self::contains_record_ids( $values ) ) {
            $remaining = array();
            foreach ( $values as $idx => $item ) {
                if ( is_string( $item ) && self::contains_record_ids( array( $item ) ) ) {
                    $remaining[ $idx ] = preg_replace( '/[^A-Za-z0-9]/', '', $item );
                }
            }

            if ( ! empty( $remaining ) ) {
                $fallback = TTP_Airbase::resolve_linked_records( $table, array_values( $remaining ), '', true );

                if ( is_wp_error( $fallback ) || empty( $fallback ) ) {
                    self::log_unresolved_field( $field, array_values( $remaining ) );
                    foreach ( array_keys( $remaining ) as $idx ) {
                        unset( $values[ $idx ] );
                    }
                } else {
                    $sanitized = array();
                    foreach ( (array) $fallback as $val ) {
                        if ( is_string( $val ) ) {
                            $sanitized[] = sanitize_text_field( $val );
                        } elseif ( is_numeric( $val ) ) {
                            $sanitized[] = $val + 0;
                        }
                    }

                    $i = 0;
                    foreach ( $remaining as $idx => $id ) {
                        if ( isset( $sanitized[ $i ] ) ) {
                            $values[ $idx ] = $sanitized[ $i ];
                        } else {
                            unset( $values[ $idx ] );
                        }
                        $i++;
                    }

                    $values = array_values(
                        array_filter(
                            $values,
                            function ( $v ) {
                                return $v !== null && $v !== '' && $v !== false;
                            }
                        )
                    );
                }
            }
        }

        return $values;
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
