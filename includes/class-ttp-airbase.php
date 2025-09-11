<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TTP_Airbase {
    const OPTION_TOKEN     = 'ttp_airbase_token';
    const OPTION_BASE_URL  = 'ttp_airbase_base_url';
    const OPTION_BASE_ID   = 'ttp_airbase_base_id';
    const OPTION_API_PATH  = 'ttp_airbase_api_path';

    const DEFAULT_BASE_URL = 'https://api.airtable.com/v0';
    const DEFAULT_BASE_ID  = '';
    const DEFAULT_API_PATH = '';

    /**
     * Maximum number of linked record IDs per API request.
     *
     * Airtable has practical URL and formula size limits, so IDs are
     * requested in batches of this size and merged.
     */
    const RECORD_BATCH_SIZE = 50;

    /**
     * In-memory cache of resolved linked record values.
     *
     * Structured as [ table_id ][ record_id ] => value. A null value indicates
     * that the record was requested but no value was returned from the API.
     * Cache persists only for the duration of the request.
     *
     * @var array
     */
    private static $linked_record_cache = array();

    /**
     * Perform an HTTP request with basic exponential backoff on rate limits.
     *
     * Retries the request when a 429 status code is encountered, waiting
     * progressively longer between attempts (1s, 2s, 4s). The last response is
     * returned even if it is not successful so that callers can handle errors
     * consistently.
     *
     * @param string $url          Request URL.
     * @param array  $args         Arguments passed to wp_remote_get().
     * @param int    $max_attempts Maximum number of attempts.
     *
     * @return array|WP_Error Response array or WP_Error from wp_remote_get().
     */
    private static function request_with_backoff( $url, $args, $max_attempts = 3 ) {
        $delay = 1;

        for ( $attempt = 0; $attempt < $max_attempts; $attempt++ ) {
            $response = wp_remote_get( $url, $args );
            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( 429 === $code && $attempt < ( $max_attempts - 1 ) ) {
                sleep( $delay );
                $delay *= 2;
                continue;
            }

            return $response;
        }

        return $response;
    }

    /**
     * Retrieve vendors from Airbase API.
     *
     * @param array $fields Optional list of field IDs or names to request.
     *
     * @return array|WP_Error Array of vendor records or WP_Error on failure.
     */
    public static function get_vendors( $fields = array() ) {
        $token = get_option( self::OPTION_TOKEN );
        if ( empty( $token ) ) {
            return new WP_Error( 'missing_token', __( 'Airbase API token not configured.', 'treasury-tech-portal' ) );
        }

        $base_url = get_option( self::OPTION_BASE_URL, self::DEFAULT_BASE_URL );
        if ( empty( $base_url ) ) {
            $base_url = self::DEFAULT_BASE_URL;
        }

        $parts = wp_parse_url( $base_url );
        if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return new WP_Error( 'invalid_base_url', __( 'Invalid Airbase base URL.', 'treasury-tech-portal' ) );
        }

        if ( empty( $parts['path'] ) || '/' === $parts['path'] ) {
            $base_url = rtrim( $base_url, '/' ) . '/v0';
        }

        $base_id = get_option( self::OPTION_BASE_ID, self::DEFAULT_BASE_ID );
        if ( empty( $base_id ) ) {
            return new WP_Error( 'missing_base_id', __( 'Airbase base ID not configured.', 'treasury-tech-portal' ) );
        }

        $api_path = get_option( self::OPTION_API_PATH, self::DEFAULT_API_PATH );
        if ( empty( $api_path ) ) {
            return new WP_Error( 'missing_api_path', __( 'Airbase API path not configured.', 'treasury-tech-portal' ) );
        }

        $base_endpoint = rtrim( $base_url, '/' ) . '/' . trim( $base_id, '/' ) . '/' . ltrim( $api_path, '/' );

        if ( ! wp_http_validate_url( $base_endpoint ) ) {
            return new WP_Error( 'invalid_api_url', __( 'Invalid Airbase API URL.', 'treasury-tech-portal' ) );
        }

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ),
            'timeout' => 20,
        );

        $fields   = array_filter( (array) $fields );
        $records  = array();
        $offset   = '';

        if ( ! empty( $fields ) ) {
            $schema         = self::get_table_schema();
            $name_to_id_map = array();
            $id_to_name_map = array();

            if ( ! is_wp_error( $schema ) && is_array( $schema ) ) {
                $name_to_id_map = $schema;
                $id_to_name_map = array_flip( $schema );
            }

            $normalized = array();
            foreach ( $fields as $field ) {
                $field = sanitize_text_field( $field );
                if ( isset( $name_to_id_map[ $field ] ) ) {
                    $normalized[] = $name_to_id_map[ $field ];
                } elseif ( isset( $id_to_name_map[ $field ] ) ) {
                    $normalized[] = $field; // Already an ID.
                } else {
                    $normalized[] = $field;
                    if ( function_exists( 'error_log' ) ) {
                        error_log( 'TTP_Airbase: Unknown requested field ' . $field );
                    }
                }
            }

            $fields = $normalized;
        }

        do {
            $url   = $base_endpoint;
            $query = array(
                'pageSize=100',
                'returnFieldsByFieldId=false',
                'userLocale=en-US',
                'timeZone=UTC',
            );

            // Ensure returned linked records contain human-readable values.
            if ( ! in_array( 'cellFormat=string', $query, true ) ) {
                array_splice( $query, 1, 0, 'cellFormat=string' );
            }

            if ( $offset ) {
                $query[] = 'offset=' . rawurlencode( $offset );
            }

            if ( ! empty( $fields ) ) {
                foreach ( $fields as $field ) {
                    $query[] = 'fields[]=' . rawurlencode( $field );
                }
            }

            if ( ! empty( $query ) ) {
                $url .= '?' . implode( '&', $query );
            }

            $response = self::request_with_backoff( $url, $args );
            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== $code ) {
                return new WP_Error( 'api_error', sprintf( 'Airbase API returned status %d', $code ) );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            if ( JSON_ERROR_NONE !== json_last_error() ) {
                return new WP_Error( 'invalid_json', __( 'Unable to parse Airbase API response.', 'treasury-tech-portal' ) );
            }

            if ( isset( $data['records'] ) && is_array( $data['records'] ) ) {
                $records = array_merge( $records, $data['records'] );
            }

            $offset = isset( $data['offset'] ) ? $data['offset'] : '';
        } while ( $offset );

        if ( ! empty( $fields ) ) {
            $present = array();
            foreach ( $records as $record ) {
                if ( isset( $record['fields'] ) && is_array( $record['fields'] ) ) {
                    $present = array_unique( array_merge( $present, array_keys( $record['fields'] ) ) );
                }
            }

            $missing = array_diff( $fields, $present );
            if ( ! empty( $missing ) && function_exists( 'error_log' ) ) {
                error_log( 'TTP_Airbase: Missing expected fields: ' . implode( ', ', $missing ) );
            }
        }

        return $records;
    }

    /**
     * Map field names to their schema IDs.
     *
     * Returns both a mapping of the provided field names to their Airtable
     * field IDs and a list of the IDs (falling back to the original name when
     * a field is not found). This helper centralises the logic used by multiple
     * callers when preparing field requests.
     *
     * @param array $field_names Field names to map.
     * @return array {
     *     @type array $schema_map Mapping of field names to IDs.
     *     @type array $field_ids List of field IDs or names when unmapped.
     * }
     */
    public static function map_field_names( $field_names ) {
        $field_names = array_values( array_filter( (array) $field_names ) );
        $schema_map  = array();
        $field_ids   = array();

        if ( empty( $field_names ) ) {
            return array(
                'schema_map' => array(),
                'field_ids'  => array(),
            );
        }

        $schema = self::get_table_schema();
        if ( ! is_wp_error( $schema ) && is_array( $schema ) ) {
            foreach ( $field_names as $name ) {
                $id                = isset( $schema[ $name ] ) ? $schema[ $name ] : $name;
                $schema_map[ $name ] = $id;
                $field_ids[]         = $id;
            }
        } else {
            $schema_map = array_combine( $field_names, $field_names );
            $field_ids  = $field_names;
        }

        return array(
            'schema_map' => $schema_map,
            'field_ids'  => $field_ids,
        );
    }

    /**
     * Retrieve field schema for the vendor table via the Airtable metadata API.
     *
     * Returns a mapping of field names to their internal Airtable IDs so that
     * requests can use the correct identifiers without hard-coding them in the
     * codebase.
     *
     * @param string $table_id Optional table ID. Defaults to configured API path.
     *
     * @return array|WP_Error Array mapping field names to IDs or WP_Error on failure.
     */
    public static function get_table_schema( $table_id = '' ) {
        $table_id = $table_id ? $table_id : get_option( self::OPTION_API_PATH, self::DEFAULT_API_PATH );
        if ( empty( $table_id ) ) {
            return new WP_Error( 'missing_api_path', __( 'Airbase API path not configured.', 'treasury-tech-portal' ) );
        }

        $cached = get_transient( 'ttp_airbase_schema' );
        if ( is_array( $cached ) && isset( $cached[ $table_id ]['fields'] ) ) {
            return $cached[ $table_id ]['fields'];
        }

        $token = get_option( self::OPTION_TOKEN );
        if ( empty( $token ) ) {
            return new WP_Error( 'missing_token', __( 'Airbase API token not configured.', 'treasury-tech-portal' ) );
        }

        $base_url = get_option( self::OPTION_BASE_URL, self::DEFAULT_BASE_URL );
        if ( empty( $base_url ) ) {
            $base_url = self::DEFAULT_BASE_URL;
        }

        $parts = wp_parse_url( $base_url );
        if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return new WP_Error( 'invalid_base_url', __( 'Invalid Airbase base URL.', 'treasury-tech-portal' ) );
        }

        if ( empty( $parts['path'] ) || '/' === $parts['path'] ) {
            $base_url = rtrim( $base_url, '/' ) . '/v0';
        }

        $base_id = get_option( self::OPTION_BASE_ID, self::DEFAULT_BASE_ID );
        if ( empty( $base_id ) ) {
            return new WP_Error( 'missing_base_id', __( 'Airbase base ID not configured.', 'treasury-tech-portal' ) );
        }

        $endpoint = rtrim( $base_url, '/' ) . '/meta/bases/' . trim( $base_id, '/' ) . '/tables';

        if ( ! wp_http_validate_url( $endpoint ) ) {
            return new WP_Error( 'invalid_api_url', __( 'Invalid Airbase API URL.', 'treasury-tech-portal' ) );
        }

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ),
            'timeout' => 20,
        );

        $response = self::request_with_backoff( $endpoint, $args );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            return new WP_Error( 'api_error', sprintf( 'Airbase API returned status %d', $code ) );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error( 'invalid_json', __( 'Unable to parse Airbase API response.', 'treasury-tech-portal' ) );
        }

        if ( ! isset( $data['tables'] ) || ! is_array( $data['tables'] ) ) {
            return new WP_Error( 'missing_tables', __( 'Airbase schema response missing tables.', 'treasury-tech-portal' ) );
        }

        $schemas = array();
        foreach ( $data['tables'] as $table ) {
            $map          = array();
            $types        = array();
            $primary_id   = isset( $table['primaryFieldId'] ) ? $table['primaryFieldId'] : '';
            $primary_name = '';
            $primary_type = '';

            if ( isset( $table['fields'] ) && is_array( $table['fields'] ) ) {
                foreach ( $table['fields'] as $field ) {
                    if ( isset( $field['name'], $field['id'] ) ) {
                        $map[ $field['name'] ] = $field['id'];
                        if ( isset( $field['type'] ) ) {
                            $types[ $field['name'] ] = $field['type'];
                        }
                        if ( $field['id'] === $primary_id ) {
                            $primary_name = $field['name'];
                            $primary_type = isset( $field['type'] ) ? $field['type'] : '';
                        }
                    }
                }
            }

            $entry = array(
                'fields'  => $map,
                'types'   => $types,
                'primary' => array(
                    'id'   => $primary_id,
                    'name' => $primary_name,
                    'type' => $primary_type,
                ),
            );

            if ( isset( $table['id'] ) ) {
                $schemas[ $table['id'] ] = $entry;
            }
            if ( isset( $table['name'] ) ) {
                $schemas[ $table['name'] ] = $entry;
            }
        }

        set_transient( 'ttp_airbase_schema', $schemas, DAY_IN_SECONDS );

        if ( isset( $schemas[ $table_id ]['fields'] ) ) {
            return $schemas[ $table_id ]['fields'];
        }

        return new WP_Error( 'table_not_found', __( 'Specified Airbase table not found in schema.', 'treasury-tech-portal' ) );
    }

    /**
     * Retrieve the primary field details for a given table.
     *
     * Uses cached schema data when available to avoid additional network
     * requests. The returned array contains both the field ID and current
     * name. Results are cached in a static property for subsequent lookups
     * within the same request.
     *
     * @param string $table_id Table name or ID.
     *
     * @return array|WP_Error Array with 'id' and 'name' keys or WP_Error on failure.
     */
    public static function get_primary_field( $table_id ) {
        static $cache = array();

        if ( isset( $cache[ $table_id ] ) ) {
            return $cache[ $table_id ];
        }

        $schema = get_transient( 'ttp_airbase_schema' );
        if ( is_array( $schema ) && isset( $schema[ $table_id ]['primary'] ) ) {
            $cache[ $table_id ] = $schema[ $table_id ]['primary'];
            return $cache[ $table_id ];
        }

        $result = self::get_table_schema( $table_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $schema = get_transient( 'ttp_airbase_schema' );
        if ( is_array( $schema ) && isset( $schema[ $table_id ]['primary'] ) ) {
            $cache[ $table_id ] = $schema[ $table_id ]['primary'];
            return $cache[ $table_id ];
        }

        return new WP_Error( 'primary_field_not_found', __( 'Primary field not found for table.', 'treasury-tech-portal' ) );
    }

    /**
     * Resolve linked record IDs to their name values.
     *
     * IDs are queried in batches of {@see self::RECORD_BATCH_SIZE} to avoid
     * exceeding Airtable's URL and formula limits. Results from each batch are
     * merged and returned as a flat array.
     *
     * @param string $table_id      Table name or ID to query.
     * @param array  $ids           Record IDs to resolve.
     * @param string $primary_field Primary field to return. Defaults to "Name".
     *
     * @return array|WP_Error Array of record field values or WP_Error on failure.
     */
    public static function resolve_linked_records( $table_id, $ids, $primary_field = '' ) {
        $ids = array_filter( (array) $ids );
        if ( empty( $ids ) ) {
            return array();
        }

        $table_id = (string) $table_id;
        $missing  = array();

        // Determine which IDs are not already cached.
        foreach ( $ids as $id ) {
            $id = (string) $id;
            if ( ! isset( self::$linked_record_cache[ $table_id ][ $id ] ) ) {
                $missing[] = $id;
            }
        }

        // If all IDs are cached, return them in the original order.
        if ( empty( $missing ) ) {
            $ordered = array();
            foreach ( $ids as $id ) {
                $val = self::$linked_record_cache[ $table_id ][ $id ];
                if ( null !== $val ) {
                    $ordered[] = $val;
                }
            }
            return $ordered;
        }

        $token = get_option( self::OPTION_TOKEN );
        if ( empty( $token ) ) {
            return new WP_Error( 'missing_token', __( 'Airbase API token not configured.', 'treasury-tech-portal' ) );
        }

        $base_url = get_option( self::OPTION_BASE_URL, self::DEFAULT_BASE_URL );
        if ( empty( $base_url ) ) {
            $base_url = self::DEFAULT_BASE_URL;
        }

        $parts = wp_parse_url( $base_url );
        if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
            return new WP_Error( 'invalid_base_url', __( 'Invalid Airbase base URL.', 'treasury-tech-portal' ) );
        }

        if ( empty( $parts['path'] ) || '/' === $parts['path'] ) {
            $base_url = rtrim( $base_url, '/' ) . '/v0';
        }

        $base_id = get_option( self::OPTION_BASE_ID, self::DEFAULT_BASE_ID );
        if ( empty( $base_id ) ) {
            return new WP_Error( 'missing_base_id', __( 'Airbase base ID not configured.', 'treasury-tech-portal' ) );
        }

        $endpoint = rtrim( $base_url, '/' ) . '/' . trim( $base_id, '/' ) . '/' . ltrim( $table_id, '/' );

        if ( ! wp_http_validate_url( $endpoint ) ) {
            return new WP_Error( 'invalid_api_url', __( 'Invalid Airbase API URL.', 'treasury-tech-portal' ) );
        }

        if ( empty( $primary_field ) ) {
            $primary = self::get_primary_field( $table_id );
            if ( is_wp_error( $primary ) ) {
                return $primary;
            }

            $query_field  = sanitize_text_field( $primary['id'] ? $primary['id'] : $primary['name'] );
            $result_field = $primary['name'] ? $primary['name'] : $primary['id'];
        } else {
            $query_field  = sanitize_text_field( $primary_field );
            $result_field = $query_field;
        }

        $field_type = '';
        if ( function_exists( 'get_transient' ) ) {
            $schema = get_transient( 'ttp_airbase_schema' );
            if ( isset( $schema[ $table_id ]['types'][ $result_field ] ) ) {
                $field_type = $schema[ $table_id ]['types'][ $result_field ];
            } elseif ( isset( $schema[ $table_id ]['types'][ $query_field ] ) ) {
                $field_type = $schema[ $table_id ]['types'][ $query_field ];
            }
        }

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ),
            'timeout' => 20,
        );

        $chunks = array_chunk( $missing, self::RECORD_BATCH_SIZE );

        foreach ( $chunks as $chunk ) {
            $filter_parts = array();
            foreach ( $chunk as $id ) {
                $filter_parts[] = "RECORD_ID()='" . str_replace( "'", "\\'", $id ) . "'";
            }
            $filter = 'OR(' . implode( ',', $filter_parts ) . ')';

            $url      = $endpoint . '?pageSize=100&cellFormat=string&returnFieldsByFieldId=false&fields[]=' . rawurlencode( $query_field ) . '&filterByFormula=' . rawurlencode( $filter ) . '&userLocale=en-US&timeZone=UTC';
            $response = self::request_with_backoff( $url, $args );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( 200 !== $code ) {
                return new WP_Error( 'api_error', sprintf( 'Airbase API returned status %d', $code ) );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            if ( JSON_ERROR_NONE !== json_last_error() ) {
                return new WP_Error( 'invalid_json', __( 'Unable to parse Airbase API response.', 'treasury-tech-portal' ) );
            }

            if ( isset( $data['records'] ) && is_array( $data['records'] ) ) {
                foreach ( $data['records'] as $record ) {
                    $record_id = isset( $record['id'] ) ? (string) $record['id'] : '';
                    $value     = null;
                    if ( isset( $record['fields'][ $result_field ] ) ) {
                        $value = $record['fields'][ $result_field ];
                    } elseif ( isset( $record['fields'][ $query_field ] ) ) {
                        $value = $record['fields'][ $query_field ];
                    }

                    if ( is_array( $value ) ) {
                        if ( isset( $value['name'] ) ) {
                            $value = sanitize_text_field( $value['name'] );
                        } elseif ( isset( $value['text'] ) ) {
                            $value = sanitize_text_field( $value['text'] );
                        } elseif ( isset( $value['id'] ) ) {
                            $value = sanitize_text_field( $value['id'] );
                        } else {
                            $value = null;
                        }
                    } elseif ( null !== $value ) {
                        $type = strtolower( (string) $field_type );
                        if ( is_numeric( $value ) && in_array( $type, array( 'number', 'count', 'float', 'integer', 'decimal', 'currency', 'percent', 'rating', 'duration' ), true ) ) {
                            $value = 0 + $value;
                        } else {
                            $value = sanitize_text_field( $value );
                        }
                    }

                    if ( $record_id ) {
                        self::$linked_record_cache[ $table_id ][ $record_id ] = $value;
                    }
                }
            }

            // Mark any IDs not returned as null to avoid repeated lookups.
            foreach ( $chunk as $id ) {
                if ( ! isset( self::$linked_record_cache[ $table_id ][ $id ] ) ) {
                    self::$linked_record_cache[ $table_id ][ $id ] = null;
                }
            }
        }

        $ordered = array();
        foreach ( $ids as $id ) {
            if ( array_key_exists( $id, self::$linked_record_cache[ $table_id ] ) ) {
                $val = self::$linked_record_cache[ $table_id ][ $id ];
                if ( null !== $val ) {
                    $ordered[] = $val;
                }
            }
        }

        return $ordered;
    }
}

/**
 * Legacy wrapper retained for backward compatibility.
 *
 * @deprecated 1.0.3 Use TTP_Data::resolve_linked_field() or
 *             TTP_Airbase::resolve_linked_records().
 *
 * @param array  $records         Airtable records.
 * @param array  $field_to_linked Map of field name => linked table name.
 * @param string $base_id         Airtable base ID.
 * @param string $token           API token.
 *
 * @return array Unmodified records.
 */
function rt_airtable_map_ids_to_names( array $records, array $field_to_linked, $base_id, $token ) {
    if ( function_exists( '_deprecated_function' ) ) {
        _deprecated_function( __FUNCTION__, '1.0.3', 'TTP_Data::resolve_linked_field or TTP_Airbase::resolve_linked_records' );
    }

    return $records;
}
