<?php
namespace TreasuryTechPortal;

if (!defined('ABSPATH')) {
    exit;
}

class Autoloader {
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    public static function autoload($class) {
        if (strpos($class, 'TreasuryTechPortal\\') !== 0) {
            return;
        }

        $class = substr($class, 18);
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = TTP_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}
