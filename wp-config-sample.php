<?php
/**
 * WordPress.com deployment configuration sample
 * This file should be renamed to wp-config.php for local development
 */

// WordPress.com environment detection
if (!defined('WPCOM_IS_VIP_ENV')) {
    define('WPCOM_IS_VIP_ENV', false);
}

// Plugin-specific constants
define('TTP_GITHUB_REPO', 'RealTreasury/treasury-tech-portal');
define('TTP_DEPLOYMENT_METHOD', 'wordpress_com');

// Development vs Production
if (isset($_ENV['WORDPRESS_COM_ENV']) && $_ENV['WORDPRESS_COM_ENV'] === 'production') {
    define('WP_DEBUG', false);
    define('TTP_DEBUG', false);
} else {
    define('WP_DEBUG', true);
    define('TTP_DEBUG', true);
}
