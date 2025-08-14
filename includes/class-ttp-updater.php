<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TTP_Updater {
    private $plugin_slug;
    private $version;
    private $plugin_path;
    private $plugin_file;
    private $github_username;
    private $github_repo;
    private $github_token;

    public function __construct($plugin_file, $github_username, $github_repo, $github_token = null) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = TTP_VERSION;
        $this->plugin_path = plugin_basename(dirname($plugin_file));
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;
        $this->github_token = $github_token;

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_pre_download', array($this, 'download_package'), 10, 3);
    }

    private function get_repository_info() {
        $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest', 
                              $this->github_username, $this->github_repo);

        $args = array();
        if ($this->github_token) {
            $args['headers'] = array(
                'Authorization' => 'token ' . $this->github_token
            );
        }

        $response = wp_remote_get($request_uri, $args);

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            return json_decode(wp_remote_retrieve_body($response), true);
        }

        return false;
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote_version = $this->get_remote_version();
        
        if (version_compare($this->version, $remote_version, '<')) {
            $transient->response[$this->plugin_slug] = (object) array(
                'slug' => $this->plugin_path,
                'new_version' => $remote_version,
                'url' => sprintf('https://github.com/%s/%s', $this->github_username, $this->github_repo),
                'package' => $this->get_download_url(),
                'tested' => '6.4',
                'requires_php' => '7.4',
                'compatibility' => new stdClass(),
            );
        }

        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return false;
        }

        if (!empty($args->slug) && $args->slug === $this->plugin_path) {
            $remote_version = $this->get_remote_version();
            
            return (object) array(
                'name' => 'Treasury Tech Portal',
                'slug' => $this->plugin_path,
                'version' => $remote_version,
                'author' => 'Real Treasury',
                'author_profile' => 'https://realtreasury.com',
                'homepage' => sprintf('https://github.com/%s/%s', $this->github_username, $this->github_repo),
                'short_description' => 'A comprehensive platform for discovering and comparing treasury technology solutions.',
                'sections' => array(
                    'description' => 'The Treasury Tech Portal provides an interactive interface for exploring Cash Tools, TMS-Lite solutions, and Enterprise TRMS platforms.',
                    'installation' => 'Upload and activate the plugin, then use the [treasury_portal] shortcode.',
                    'changelog' => 'See GitHub releases for changelog.'
                ),
                'download_link' => $this->get_download_url(),
                'tested' => '6.4',
                'requires_php' => '7.4',
                'last_updated' => date('Y-m-d'),
            );
        }

        return $result;
    }

    public function download_package($reply, $package, $upgrader) {
        if ($package === $this->get_download_url()) {
            $args = array();
            if ($this->github_token) {
                $args['headers'] = array(
                    'Authorization' => 'token ' . $this->github_token
                );
            }

            $response = wp_remote_get($package, $args);
            
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $temp_file = download_url($package);
                return $temp_file;
            }
        }

        return $reply;
    }

    private function get_remote_version() {
        $repo_info = $this->get_repository_info();
        
        if ($repo_info && isset($repo_info['tag_name'])) {
            return ltrim($repo_info['tag_name'], 'v');
        }

        return $this->version;
    }

    private function get_download_url() {
        $repo_info = $this->get_repository_info();
        
        if ($repo_info && isset($repo_info['zipball_url'])) {
            return $repo_info['zipball_url'];
        }

        return sprintf('https://github.com/%s/%s/archive/main.zip', 
                      $this->github_username, $this->github_repo);
    }
}
