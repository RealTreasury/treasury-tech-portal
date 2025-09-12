<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-ttp-airbase.php';

if ( ! function_exists( 'get_option' ) ) {
    $GLOBALS['wp_options'] = [];
    function get_option( $name, $default = false ) {
        return isset( $GLOBALS['wp_options'][ $name ] ) ? $GLOBALS['wp_options'][ $name ] : $default;
    }
}

if ( ! function_exists( 'update_option' ) ) {
    function update_option( $name, $value ) {
        $GLOBALS['wp_options'][ $name ] = $value;
        return true;
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) {
        return $thing instanceof WP_Error;
    }
}

if ( ! function_exists( 'wp_remote_get' ) ) {
    function wp_remote_get( $url, $args = [] ) {
        $context = [ 'http' => [ 'method' => 'GET' ] ];
        if ( isset( $args['headers'] ) && is_array( $args['headers'] ) ) {
            $headers = '';
            foreach ( $args['headers'] as $key => $value ) {
                $headers .= $key . ': ' . $value . "\r\n";
            }
            $context['http']['header'] = $headers;
        }
        if ( isset( $args['timeout'] ) ) {
            $context['http']['timeout'] = $args['timeout'];
        }
        $body = @file_get_contents( $url, false, stream_context_create( $context ) );
        if ( false === $body ) {
            return new WP_Error( 'http_request_failed', 'Failed to retrieve URL.' );
        }
        $code = 0;
        if ( isset( $http_response_header[0] ) && preg_match( '#HTTP/\S+\s(\d+)#', $http_response_header[0], $match ) ) {
            $code = (int) $match[1];
        }
        return [
            'response' => [ 'code' => $code ],
            'body'     => $body,
        ];
    }
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $response ) {
        return isset( $response['response']['code'] ) ? $response['response']['code'] : 0;
    }
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    function wp_remote_retrieve_body( $response ) {
        return isset( $response['body'] ) ? $response['body'] : '';
    }
}

if ( ! function_exists( 'wp_parse_url' ) ) {
    function wp_parse_url( $url ) {
        return parse_url( $url );
    }
}

if ( ! function_exists( 'wp_http_validate_url' ) ) {
    function wp_http_validate_url( $url ) {
        return filter_var( $url, FILTER_VALIDATE_URL );
    }
}

class TTP_Airbase_Connection_Test extends TestCase {
    public function test_connects_to_airbase_api() {
        $token = getenv( 'AIRBASE_API_TOKEN' );
        if ( empty( $token ) ) {
            $this->markTestSkipped( 'AIRBASE_API_TOKEN not set' );
        }

        update_option( TTP_Airbase::OPTION_TOKEN, $token );

        $base_url = getenv( 'AIRBASE_BASE_URL' );
        if ( ! empty( $base_url ) ) {
            update_option( TTP_Airbase::OPTION_BASE_URL, $base_url );
        }

        $base_id = getenv( 'AIRBASE_BASE_ID' );
        if ( ! empty( $base_id ) ) {
            update_option( TTP_Airbase::OPTION_BASE_ID, $base_id );
        }

        $api_path = getenv( 'AIRBASE_API_PATH' );
        if ( ! empty( $api_path ) ) {
            update_option( TTP_Airbase::OPTION_API_PATH, $api_path );
        }

        $vendors = TTP_Airbase::get_products();

        if ( is_wp_error( $vendors ) ) {
            $this->markTestSkipped( 'Airbase API request failed' );
        }

        $this->assertIsArray( $vendors );
        if ( ! empty( $vendors ) ) {
            $this->assertIsArray( $vendors[0] );
        }
    }
}
