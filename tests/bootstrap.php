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
