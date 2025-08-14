<?php
/**
 * Plugin Installation Helper
 * This file helps with automated installation from GitHub
 */

class TTP_Installer {
    
    public static function install_from_github($username, $repo, $branch = 'main') {
        $plugin_dir = WP_PLUGIN_DIR . '/treasury-tech-portal/';
        $temp_file = download_url("https://github.com/{$username}/{$repo}/archive/{$branch}.zip");
        
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        $result = unzip_file($temp_file, $plugin_dir);
        unlink($temp_file);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Activate the plugin
        activate_plugin('treasury-tech-portal/treasury-tech-portal.php');
        
        return true;
    }
    
    public static function check_requirements() {
        $errors = array();
        
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            $errors[] = 'PHP 7.4 or higher is required.';
        }
        
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            $errors[] = 'WordPress 5.0 or higher is required.';
        }
        
        return $errors;
    }
}
