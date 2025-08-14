<?php
namespace TreasuryTechPortal;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TTP_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_post_ttp_save_tool', [__CLASS__, 'save_tool']);
        add_action('admin_post_ttp_delete_tool', [__CLASS__, 'delete_tool']);
    }

    public static function register_menu() {
        add_menu_page(
            __('Treasury Tools', 'treasury-tech-portal'),
            __('Treasury Tools', 'treasury-tech-portal'),
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
            wp_die(__('Unauthorized', 'treasury-tech-portal'), __('Unauthorized', 'treasury-tech-portal'), ['response' => 403]);
        }

        check_admin_referer('ttp_save_tool');

        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) {
            wp_die(__('Tool name is required', 'treasury-tech-portal'));
        }

        $category = sanitize_text_field($_POST['category'] ?? '');
        $allowed_categories = ['CASH', 'LITE', 'TRMS'];
        if (!in_array($category, $allowed_categories, true)) {
            wp_die(__('Invalid category', 'treasury-tech-portal'));
        }

        $video_url = '';
        if (!empty($_POST['videoUrl'])) {
            $video_url = esc_url_raw($_POST['videoUrl']);
            if (!wp_http_validate_url($video_url)) {
                wp_die(__('Invalid video URL', 'treasury-tech-portal'));
            }
        }

        $tools = TTP_Data::get_all_tools();
        $index = isset($_POST['index']) ? absint($_POST['index']) : null;

        $tool = [
            'name'       => $name,
            'category'   => $category,
            'desc'       => sanitize_textarea_field($_POST['desc'] ?? ''),
            'features'   => array_filter(array_map('sanitize_text_field', explode("\n", $_POST['features'] ?? ''))),
            'target'     => sanitize_text_field($_POST['target'] ?? ''),
            'videoUrl'   => $video_url,
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
            wp_die(__('Unauthorized', 'treasury-tech-portal'));
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
