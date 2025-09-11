<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'TTP_CATEGORIES' ) ) {
    require_once __DIR__ . '/categories.php';
}

class TTP_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_post_ttp_save_tool', [__CLASS__, 'save_tool']);
        add_action('admin_post_ttp_delete_tool', [__CLASS__, 'delete_tool']);
    }

    public static function register_menu() {
        add_menu_page(
            'Treasury Tools',
            'Treasury Tools',
            'manage_options',
            'treasury-tools',
            [__CLASS__, 'render_page'],
            'dashicons-hammer',
            56
        );
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $tools = TTP_Data::get_all_tools();

        $sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : '';
        if ($sort === 'name') {
            usort($tools, function($a, $b) {
                return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
            });
        } elseif ($sort === 'category') {
            usort($tools, function($a, $b) {
                return strcasecmp($a['category'] ?? '', $b['category'] ?? '');
            });
        }

        include dirname(__DIR__) . '/templates/admin-page.php';
    }

    public static function save_tool() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ttp_save_tool');

        $tools = TTP_Data::get_all_tools();
        $index = isset($_POST['index']) ? absint($_POST['index']) : null;

        $tool = [
            'name'       => sanitize_text_field($_POST['name'] ?? ''),
            'category'   => sanitize_text_field($_POST['category'] ?? ''),
            'desc'       => sanitize_textarea_field($_POST['desc'] ?? ''),
            'features'   => array_filter(array_map('sanitize_text_field', explode("\n", $_POST['features'] ?? ''))),
            'target'     => sanitize_text_field($_POST['target'] ?? ''),
            'videoUrl'   => esc_url_raw($_POST['videoUrl'] ?? ''),
            'websiteUrl' => esc_url_raw($_POST['websiteUrl'] ?? ''),
            'logoUrl'    => esc_url_raw($_POST['logoUrl'] ?? ''),
        ];

        if ($index === null) {
            $tools[] = $tool;
        } else {
            $tools[$index] = $tool;
        }

        TTP_Data::save_tools($tools);
        wp_redirect(admin_url('admin.php?page=treasury-tools&updated=1'));
        exit;
    }

    public static function delete_tool() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ttp_delete_tool');
        $index = absint($_GET['index']);
        $tools = TTP_Data::get_all_tools();
        if (isset($tools[$index])) {
            unset($tools[$index]);
            TTP_Data::save_tools(array_values($tools));
        }
        wp_redirect(admin_url('admin.php?page=treasury-tools&deleted=1'));
        exit;
    }
}
