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
    const DEFAULT_BASE_ID  = 'appJdxdz3310aJ3Fd';
    const DEFAULT_API_PATH = 'tblOJ6yL9Jw5ZTdRc';

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

        $base_url = get_option(self::OPTION_BASE_URL, self::DEFAULT_BASE_URL);
        if (empty($base_url)) {
            $base_url = self::DEFAULT_BASE_URL;
        }

        $base_id = get_option(self::OPTION_BASE_ID, self::DEFAULT_BASE_ID);
        if (empty($base_id)) {
            $base_id = self::DEFAULT_BASE_ID;
        }

        $api_path = get_option(self::OPTION_API_PATH, self::DEFAULT_API_PATH);
        if (empty($api_path)) {
            $api_path = self::DEFAULT_API_PATH;
        }

        $url = rtrim($base_url, '/') . '/' . trim($base_id, '/') . '/' . ltrim($api_path, '/');

        $args = [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
            ],
            'timeout' => 20,
        ];

        $response = wp_remote_get($url, $args);
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
