<?php
namespace TreasuryTechPortal;

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
        add_action('init', [TTP_Rest::class, 'init']);
        add_action('init', [TTP_Admin::class, 'init']);
        add_action('init', [$this, 'load_textdomain']);

        add_shortcode('treasury_portal', [$this, 'shortcode_handler']);
    }

    public function enqueue_assets() {
        $plugin_url = TTP_URL;
        $version = TTP_VERSION;

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $css_file = TTP_DIR . 'assets/css/treasury-portal.css';
            $js_file  = TTP_DIR . 'assets/js/treasury-portal.js';
            $css_ver  = file_exists($css_file) ? filemtime($css_file) : $version;
            $js_ver   = file_exists($js_file) ? filemtime($js_file) : $version;
        } else {
            $css_ver = $js_ver = $version;
        }

        wp_enqueue_style(
            'treasury-tech-portal-css',
            $plugin_url . 'assets/css/treasury-portal.css',
            [],
            $css_ver
        );

        wp_enqueue_script(
            'treasury-tech-portal-js',
            $plugin_url . 'assets/js/treasury-portal.js',
            [],
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

    public function shortcode_handler($atts = [], $content = null) {
        $this->enqueue_assets();
        ob_start();
        include plugin_dir_path(__FILE__) . 'shortcode.php';
        return ob_get_clean();
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'treasury-tech-portal',
            false,
            dirname(TTP_BASENAME) . '/languages'
        );
    }
}
