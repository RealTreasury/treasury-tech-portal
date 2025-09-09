<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TTP_Airbase {
    const OPTION_TOKEN = 'ttp_airbase_token';
    const API_URL      = 'https://api.airbase.com/v2/vendors';

    /**
     * Retrieve vendors from Airbase API.
     *
     * @return array|WP_Error Parsed vendor data or WP_Error on failure.
     */
    public static function get_vendors() {
        $token = get_option(self::OPTION_TOKEN);
        if (empty($token)) {
            return new WP_Error('missing_token', __('Airbase API token not configured.', 'treasury-tech-portal'));
        }

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'timeout' => 20,
        ];

        $response = wp_remote_get(self::API_URL, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if (200 !== $code) {
            return new WP_Error('api_error', sprintf('Airbase API returned status %d', $code));
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('invalid_json', __('Unable to parse Airbase API response.', 'treasury-tech-portal'));
        }

        return $data;
    }
}
