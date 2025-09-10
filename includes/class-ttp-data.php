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
    const FIELD_PRODUCT_NAME = 'fld2hocSMtPQYWfPa';

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

        $tools = get_option(self::OPTION_KEY);
        if (empty($tools)) {
            $tools = self::load_default_tools();
        }

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
        $data = TTP_Airbase::get_vendors();
        if (is_wp_error($data)) {
            return;
        }
        $vendors = self::normalize_vendor_response($data);
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
                return self::map_vendor_records($data[$key]);
            }
        }

        if (isset($data['data']) && is_array($data['data'])) {
            return self::normalize_vendor_response($data['data']);
        }

        return self::map_vendor_records($data);
    }

    /**
     * Map Airtable record structures to a vendor array.
     *
     * @param array $records
     * @return array
     */
    private static function map_vendor_records($records) {
        if (!is_array($records)) {
            return [];
        }

        $vendors = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $id   = $record['id'] ?? '';
            $name = '';

            if (isset($record['fields']) && is_array($record['fields'])) {
                $fields = $record['fields'];
                $name   = $fields[self::FIELD_PRODUCT_NAME] ?? ($fields['Product Name'] ?? '');
            } else {
                $name = $record['name'] ?? '';
            }

            $vendors[] = [
                'id'   => $id,
                'name' => $name,
            ];
        }

        return $vendors;
    }

/**
     * Load default tools from bundled JSON file.
     *
     * @return array
     */
    private static function load_default_tools() {
        $file = dirname(__DIR__) . '/data/tools.json';
        if (!file_exists($file)) {
            return [];
        }
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

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

        $page     = max(1, intval($args['page'] ?? 1));
        $per_page = max(1, intval($args['per_page'] ?? count($tools)));
        $offset   = ($page - 1) * $per_page;

        $tools = array_slice(array_values($tools), $offset, $per_page);

        return $tools;
    }
}
