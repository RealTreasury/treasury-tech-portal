<?php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $code;
        public $message;
        public $data;
        public function __construct($code = '', $message = '', $data = null) {
            $this->code    = $code;
            $this->message = $message;
            $this->data    = $data;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_code() {
            return $this->code;
        }
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

// Minimal TTP_Admin stub for constant references in tests.
if (!class_exists('TTP_Admin')) {
    class TTP_Admin {
        const OPTION_ENABLED_CATEGORIES = 'ttp_enabled_categories';
    }
}
