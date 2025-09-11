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
     * @param array $fields              Optional list of field IDs or names to request.
     * @param bool  $return_fields_by_id Whether to request and return fields by ID.
     *
     * @return array|WP_Error Array of vendor records or WP_Error on failure.
     */
    public static function get_vendors( $fields = array(), $return_fields_by_id = false ) {
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

        // Normalize fields to names or IDs based on returnFieldsByFieldId.
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
                if ( $return_fields_by_id ) {
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
                } else {
                    if ( isset( $id_to_name_map[ $field ] ) ) {
                        $normalized[] = $id_to_name_map[ $field ];
                    } else {
                        $normalized[] = $field;
                        if ( ! isset( $name_to_id_map[ $field ] ) && function_exists( 'error_log' ) ) {
                            error_log( 'TTP_Airbase: Unknown requested field ' . $field );
                        }
                    }
                }
            }

            $fields = $normalized;
        }

        do {
            $url   = $base_endpoint;
            $query = array( 'cellFormat=json' );

            if ( $offset ) {
                $query[] = 'offset=' . rawurlencode( $offset );
            }

            if ( ! empty( $fields ) ) {
                foreach ( $fields as $field ) {
                    $query[] = 'fields[]=' . rawurlencode( $field );
                }
                if ( $return_fields_by_id ) {
                    $query[] = 'returnFieldsByFieldId=true';
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
        if ( is_array( $cached ) && isset( $cached[ $table_id ] ) ) {
            return $cached[ $table_id ];
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
            $map = array();
            if ( isset( $table['fields'] ) && is_array( $table['fields'] ) ) {
                foreach ( $table['fields'] as $field ) {
                    if ( isset( $field['name'], $field['id'] ) ) {
                        $map[ $field['name'] ] = $field['id'];
                    }
                }
            }
            if ( isset( $table['id'] ) ) {
                $schemas[ $table['id'] ] = $map;
            }
            if ( isset( $table['name'] ) ) {
                $schemas[ $table['name'] ] = $map;
            }
        }

        set_transient( 'ttp_airbase_schema', $schemas, DAY_IN_SECONDS );

        if ( isset( $schemas[ $table_id ] ) ) {
            return $schemas[ $table_id ];
        }

        return new WP_Error( 'table_not_found', __( 'Specified Airbase table not found in schema.', 'treasury-tech-portal' ) );
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
     * @param bool   $use_field_ids Whether the primary field is provided as a field ID.
     *
     * @return array|WP_Error Array of record field values or WP_Error on failure.
     */
    public static function resolve_linked_records( $table_id, $ids, $primary_field = 'Name', $use_field_ids = false ) {
        $token = get_option( self::OPTION_TOKEN );
        if ( empty( $token ) ) {
            return new WP_Error( 'missing_token', __( 'Airbase API token not configured.', 'treasury-tech-portal' ) );
        }

        $ids = array_filter( (array) $ids );
        if ( empty( $ids ) ) {
            return array();
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

        $primary_field = sanitize_text_field( $primary_field );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ),
            'timeout' => 20,
        );

        $values = array();
        $chunks = array_chunk( $ids, self::RECORD_BATCH_SIZE );

        foreach ( $chunks as $chunk ) {
            $filter_parts = array();
            foreach ( $chunk as $id ) {
                $filter_parts[] = "RECORD_ID()='" . str_replace( "'", "\\'", $id ) . "'";
            }
            $filter = 'OR(' . implode( ',', $filter_parts ) . ')';

            $url = $endpoint . '?fields[]=' . rawurlencode( $primary_field ) . '&filterByFormula=' . rawurlencode( $filter );
            if ( $use_field_ids ) {
                $url .= '&returnFieldsByFieldId=true';
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
                foreach ( $data['records'] as $record ) {
                    if ( isset( $record['fields'][ $primary_field ] ) ) {
                        $values[] = sanitize_text_field( $record['fields'][ $primary_field ] );
                    }
                }
            }
        }

        return $values;
    }
}
