<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TTP_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_post_ttp_refresh_vendors', [__CLASS__, 'refresh_vendors']);
        add_action('admin_post_ttp_test_airbase', [__CLASS__, 'test_airbase_connection']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_menu() {
        add_menu_page(
            'Treasury Vendors',
            'Treasury Vendors',
            'manage_options',
            'treasury-tools',
            [__CLASS__, 'render_page'],
            'dashicons-hammer',
            56
        );

        add_submenu_page(
            'treasury-tools',
            'Airbase Settings',
            'Airbase Settings',
            'manage_options',
            'treasury-airbase-settings',
            [__CLASS__, 'render_airbase_settings']
        );
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_treasury-tools') {
            return;
        }
        $css_file = TTP_DIR . 'assets/css/treasury-portal.css';
        $css_ver  = file_exists($css_file) ? filemtime($css_file) : '1.0';
        wp_enqueue_style(
            'treasury-tech-portal-admin-css',
            TTP_URL . 'assets/css/treasury-portal.css',
            array(),
            $css_ver
        );
    }

    public static function render_airbase_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('ttp_airbase_settings');
            update_option(TTP_Airbase::OPTION_TOKEN, sanitize_text_field($_POST[TTP_Airbase::OPTION_TOKEN] ?? ''));
            update_option(TTP_Airbase::OPTION_BASE_URL, esc_url_raw($_POST[TTP_Airbase::OPTION_BASE_URL] ?? ''));
            update_option(TTP_Airbase::OPTION_BASE_ID, sanitize_text_field($_POST[TTP_Airbase::OPTION_BASE_ID] ?? ''));
            update_option(TTP_Airbase::OPTION_API_PATH, sanitize_text_field($_POST[TTP_Airbase::OPTION_API_PATH] ?? ''));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'treasury-tech-portal') . '</p></div>';
        }

        if (isset($_GET['test_success'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Airbase connection successful.', 'treasury-tech-portal') . '</p></div>';
        } elseif (isset($_GET['test_error'])) {
            $message = sanitize_text_field(wp_unslash($_GET['test_error']));
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(sprintf(__('Airbase connection failed: %s', 'treasury-tech-portal'), $message)) . '</p></div>';
        }

        $api_token = get_option(TTP_Airbase::OPTION_TOKEN, '');
        $base_url  = get_option(TTP_Airbase::OPTION_BASE_URL, '');
        $base_id   = get_option(TTP_Airbase::OPTION_BASE_ID, '');
        $api_path  = get_option(TTP_Airbase::OPTION_API_PATH, '');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Airbase Settings', 'treasury-tech-portal'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('ttp_airbase_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(TTP_Airbase::OPTION_TOKEN); ?>"><?php esc_html_e('API Token', 'treasury-tech-portal'); ?></label></th>
                        <td><input name="<?php echo esc_attr(TTP_Airbase::OPTION_TOKEN); ?>" type="text" id="<?php echo esc_attr(TTP_Airbase::OPTION_TOKEN); ?>" value="<?php echo esc_attr($api_token); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(TTP_Airbase::OPTION_BASE_URL); ?>"><?php esc_html_e('Base URL', 'treasury-tech-portal'); ?></label></th>
                        <td>
                            <input name="<?php echo esc_attr(TTP_Airbase::OPTION_BASE_URL); ?>" type="text" id="<?php echo esc_attr(TTP_Airbase::OPTION_BASE_URL); ?>" value="<?php echo esc_attr($base_url); ?>" class="regular-text" placeholder="<?php echo esc_attr(TTP_Airbase::DEFAULT_BASE_URL); ?>" />
                            <?php if (empty($base_url) || !wp_http_validate_url($base_url)) : ?>
                                <p class="description" style="color:#b32d2e;"><?php esc_html_e('Please enter a valid base URL, e.g., https://api.airtable.com/v0.', 'treasury-tech-portal'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(TTP_Airbase::OPTION_BASE_ID); ?>"><?php esc_html_e('Base ID', 'treasury-tech-portal'); ?></label></th>
                        <td><input name="<?php echo esc_attr(TTP_Airbase::OPTION_BASE_ID); ?>" type="text" id="<?php echo esc_attr(TTP_Airbase::OPTION_BASE_ID); ?>" value="<?php echo esc_attr($base_id); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(TTP_Airbase::OPTION_API_PATH); ?>"><?php esc_html_e('API Path', 'treasury-tech-portal'); ?></label></th>
                        <td><input name="<?php echo esc_attr(TTP_Airbase::OPTION_API_PATH); ?>" type="text" id="<?php echo esc_attr(TTP_Airbase::OPTION_API_PATH); ?>" value="<?php echo esc_attr($api_path); ?>" class="regular-text" /></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ttp_test_airbase'); ?>
                <input type="hidden" name="action" value="ttp_test_airbase" />
                <?php submit_button(__('Test Connection', 'treasury-tech-portal'), 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $vendors = TTP_Data::get_all_vendors();
        include dirname(__DIR__) . '/templates/admin-page.php';
    }

    public static function refresh_vendors() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ttp_refresh_vendors');
        TTP_Data::refresh_vendor_cache();
        wp_redirect(admin_url('admin.php?page=treasury-tools&refreshed=1'));
        exit;
    }

    public static function test_airbase_connection() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ttp_test_airbase');
        $result = TTP_Airbase::get_vendors();
        $url    = admin_url('admin.php?page=treasury-airbase-settings');
        if (is_wp_error($result)) {
            $url = add_query_arg('test_error', rawurlencode($result->get_error_message()), $url);
        } else {
            $url = add_query_arg('test_success', 1, $url);
        }
        wp_redirect($url);
        exit;
    }
}
