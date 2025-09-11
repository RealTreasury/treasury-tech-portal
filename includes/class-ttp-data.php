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

        $vendors = array();
        foreach ($records as $record) {
            $fields = isset($record['fields']) && is_array($record['fields']) ? $record['fields'] : $record;

            $id = isset($record['id']) ? $record['id'] : '';
            if (function_exists('sanitize_text_field')) {
                $id = sanitize_text_field($id);
            }

            $vendors[] = array(
                'id'              => $id,
                'name'            => $fields['Product Name'] ?? '',
                'vendor'          => $fields['Linked Vendor'] ?? '',
                'website'         => self::normalize_url($fields['Product Website'] ?? ''),
                'video_url'       => self::normalize_url($fields['Product Video'] ?? ''),
                'status'          => $fields['Status'] ?? '',
                'hosted_type'     => $fields['Hosted Type'] ?? array(),
                'domain'          => $fields['Domain'] ?? array(),
                'regions'         => $fields['Regions'] ?? array(),
                'sub_categories'  => $fields['Sub Categories'] ?? array(),
                'parent_category' => $fields['Parent Category'] ?? '',
                'capabilities'    => $fields['Capabilities'] ?? array(),
                'logo_url'        => self::normalize_url($fields['Logo URL'] ?? ''),
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
