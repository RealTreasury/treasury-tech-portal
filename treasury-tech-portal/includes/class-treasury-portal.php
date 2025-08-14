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
        require_once dirname(__FILE__) . '/class-ttp-data.php';
        require_once dirname(__FILE__) . '/class-ttp-rest.php';
        require_once dirname(__FILE__) . '/class-ttp-admin.php';

        add_action('init', ['TTP_Rest', 'init']);
        add_action('init', ['TTP_Admin', 'init']);

        add_shortcode('treasury_portal', array($this, 'shortcode_handler'));
    }

    public function enqueue_assets() {
        $plugin_url = TTP_PLUGIN_URL;

        $css_file = TTP_PLUGIN_DIR . 'assets/css/treasury-portal.css';
        $css_ver  = file_exists($css_file) ? filemtime($css_file) : '1.0';
        wp_enqueue_style(
            'treasury-tech-portal-css',
            $plugin_url . 'assets/css/treasury-portal.css',
            array(),
            $css_ver
        );

        $js_filename = 'treasury-portal.js';
        $js_file = TTP_PLUGIN_DIR . 'assets/js/' . $js_filename;
        $js_ver  = file_exists($js_file) ? filemtime($js_file) : '1.0';
        wp_enqueue_script(
            'treasury-tech-portal-js',
            $plugin_url . 'assets/js/' . $js_filename,
            array(),
            $js_ver,
            true
        );

        wp_localize_script(
            'treasury-tech-portal-js',
            'TTP_DATA',
            [
                'rest_url'  => esc_url_raw(rest_url('ttp/v1/tools')),
                'plugin_url' => esc_url_raw($plugin_url)
            ]
        );
    }

    public function shortcode_handler($atts = array(), $content = null) {
        $this->enqueue_assets();
        ob_start();
        include plugin_dir_path(__FILE__) . 'shortcode.php';
        return ob_get_clean();
    }
}
