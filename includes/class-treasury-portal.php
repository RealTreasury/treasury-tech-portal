<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Treasury_Tech_Portal {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        require_once dirname(__FILE__) . '/class-ttp-record-utils.php';
        require_once dirname(__FILE__) . '/class-ttp-data.php';
        require_once dirname(__FILE__) . '/class-ttp-rest.php';
        require_once dirname(__FILE__) . '/class-ttp-admin.php';
        require_once dirname(__FILE__) . '/class-ttp-airbase.php';

        add_action('init', ['TTP_Rest', 'init']);
        add_action('init', ['TTP_Admin', 'init']);

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            add_action( 'cli_init', array( 'TTP_Data', 'register_cli_commands' ) );
        }

        add_action('ttp_refresh_vendor_cache', ['TTP_Data', 'refresh_vendor_cache']);
        if (!wp_next_scheduled('ttp_refresh_vendor_cache')) {
            wp_schedule_event(time(), 'twicedaily', 'ttp_refresh_vendor_cache');
        }

        add_filter('rt_portal_get_vendors', array($this, 'provide_vendors_to_bcb'));
        add_filter('rt_portal_get_sectors', array($this, 'provide_sectors_to_bcb'));
        add_filter('rt_portal_get_vendor_notes', array($this, 'provide_vendor_notes_to_bcb'));
        add_action('rt_portal_new_lead', array($this, 'handle_bcb_lead'));
        add_action('rt_portal_data_changed', array($this, 'notify_data_change'));

        add_shortcode('treasury_portal', array($this, 'shortcode_handler'));
    }

    public function enqueue_assets() {
        $plugin_url = TTP_URL;

        $css_file = TTP_DIR . 'assets/css/treasury-portal.css';
        $css_ver  = file_exists($css_file) ? filemtime($css_file) : '1.0';

        wp_enqueue_style(
            'treasury-tech-portal-css',
            $plugin_url . 'assets/css/treasury-portal.css',
            array(),
            $css_ver
        );

        $js_filename = 'treasury-portal.js';
        $js_file     = TTP_DIR . 'assets/js/' . $js_filename;
        $js_ver      = file_exists($js_file) ? filemtime($js_file) : '1.0';
        wp_enqueue_script(
            'treasury-tech-portal-js',
            $plugin_url . 'assets/js/' . $js_filename,
            array(),
            $js_ver,
            true
        );

        $categories = TTP_Data::get_categories();
        wp_localize_script(
            'treasury-tech-portal-js',
            'TTP_DATA',
            [
                'rest_url'            => esc_url_raw( rest_url( 'ttp/v1/vendors' ) ),
                'plugin_url'          => esc_url_raw( $plugin_url ),
                'enabled_categories'  => (array) get_option( TTP_Admin::OPTION_ENABLED_CATEGORIES, array_keys( $categories ) ),
                'available_categories' => array_keys( $categories ),
                'category_labels'     => $categories,
                'category_icons'      => TTP_Data::get_category_icons(),
            ]
        );
    }

    public function shortcode_handler($atts = array(), $content = null) {
        $this->enqueue_assets();
        ob_start();
        include plugin_dir_path(__FILE__) . 'shortcode.php';
        return ob_get_clean();
    }

    public function provide_vendors_to_bcb($vendors = array()) {
        $tools       = TTP_Data::get_all_tools();
        $bcb_vendors = array();

        foreach ($tools as $tool) {
            $bcb_vendors[] = array(
                'vendor_id'      => sanitize_title($tool['name']),
                'name'           => $tool['name'],
                'category'       => $this->map_category_to_bcb($tool['category']),
                'target_segment' => $this->extract_target_segments($tool['target'] ?? ''),
                'features'       => $tool['features'] ?? array(),
                'integrations'   => $this->extract_integrations($tool),
                'deployment'     => 'SaaS',
                'pros'           => $this->extract_pros($tool),
                'cons'           => $this->extract_cons($tool),
                'sources'        => array(
                    array(
                        'doc' => 'portal',
                        'loc' => 'treasury-portal',
                    ),
                ),
            );
        }

        return $bcb_vendors;
    }

    private function map_category_to_bcb($portal_category) {
        $mapping = array_fill_keys( array_keys( TTP_Data::get_categories() ), 'TMS' );

        return $mapping[ $portal_category ] ?? 'TMS';
    }

    public function provide_sectors_to_bcb($sectors = array()) {
        $tools       = TTP_Data::get_all_tools();
        $bcb_sectors = array();

        foreach ($tools as $tool) {
            $sector                 = $this->map_category_to_bcb($tool['category']);
            $bcb_sectors[$sector] = $sector;
        }

        return array_values($bcb_sectors);
    }

    public function provide_vendor_notes_to_bcb($notes = array()) {
        $tools      = TTP_Data::get_all_tools();
        $bcb_notes  = array();

        foreach ($tools as $tool) {
            $bcb_notes[sanitize_title($tool['name'])] = $tool['desc'] ?? '';
        }

        return $bcb_notes;
    }

    public function handle_bcb_lead($lead_data) {
        $name    = sanitize_text_field($lead_data['name'] ?? '');
        $email   = sanitize_email($lead_data['email'] ?? '');
        $message = sanitize_textarea_field($lead_data['message'] ?? '');

        if (empty($email)) {
            return;
        }

        $subject = 'New BCB Lead';
        $body    = "Name: {$name}\nEmail: {$email}\nMessage: {$message}";

        wp_mail(get_option('admin_email'), $subject, $body);
    }

    public function notify_data_change() {
        delete_transient(TTP_Data::CACHE_KEY);
    }

    private function extract_target_segments($target) {
        if (empty($target)) {
            return array();
        }

        $segments = preg_split('/[,\/]| and | & /', $target);
        $segments = array_map('trim', $segments);
        $segments = array_filter($segments, function ($seg) {
            return !empty($seg);
        });

        return array_map('sanitize_text_field', $segments);
    }

    private function extract_integrations($tool) {
        if (empty($tool['integrations']) || !is_array($tool['integrations'])) {
            return array();
        }

        return array_map('sanitize_text_field', $tool['integrations']);
    }

    private function extract_pros($tool) {
        if (empty($tool['pros']) || !is_array($tool['pros'])) {
            return array();
        }

        return array_map('sanitize_text_field', $tool['pros']);
    }

    private function extract_cons($tool) {
        if (empty($tool['cons']) || !is_array($tool['cons'])) {
            return array();
        }

        return array_map('sanitize_text_field', $tool['cons']);
    }
}
