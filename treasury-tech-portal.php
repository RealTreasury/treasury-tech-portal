<?php
/**
 * Plugin Name: Treasury Tech Portal
 * Namespace: TreasuryTechPortal
 * Version: 1.0.1
 */

namespace TreasuryTechPortal;

if (!defined('ABSPATH')) {
    exit;
}

// Include autoloader
require_once __DIR__ . '/includes/autoloader.php';
Autoloader::register();

// Plugin initialization
final class Plugin {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    private function define_constants() {
        define('TTP_VERSION', '1.0.1');
        define('TTP_FILE', __FILE__);
        define('TTP_DIR', plugin_dir_path(TTP_FILE));
        define('TTP_URL', plugin_dir_url(TTP_FILE));
        define('TTP_BASENAME', plugin_basename(TTP_FILE));
    }

    private function init_hooks() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(TTP_FILE, [$this, 'activate']);
        register_deactivation_hook(TTP_FILE, [$this, 'deactivate']);
    }

    public function init() {
        Treasury_Tech_Portal::instance();
    }

    public function activate() {
        // Activation logic
        flush_rewrite_rules();
    }

    public function deactivate() {
        // Cleanup logic
        flush_rewrite_rules();
    }
}

Plugin::instance();
