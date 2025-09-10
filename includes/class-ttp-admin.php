<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TTP_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_post_ttp_save_tool', [__CLASS__, 'save_tool']);
        add_action('admin_post_ttp_delete_tool', [__CLASS__, 'delete_tool']);
        add_action('admin_post_ttp_refresh_vendors', [__CLASS__, 'refresh_vendors']);
        add_action('admin_post_ttp_test_airbase', [__CLASS__, 'test_airbase_connection']);
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

        add_submenu_page(
            'treasury-tools',
            'Airbase Settings',
            'Airbase Settings',
            'manage_options',
            'treasury-airbase-settings',
            [__CLASS__, 'render_airbase_settings']
        );

        add_submenu_page(
            'treasury-tools',
            'Vendors',
            'Vendors',
            'manage_options',
            'treasury-vendors',
            [__CLASS__, 'render_vendors_page']
        );
    }

    public static function render_airbase_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('ttp_airbase_settings');
            update_option(TTP_Airbase::OPTION_TOKEN, sanitize_text_field($_POST[TTP_Airbase::OPTION_TOKEN] ?? ''));
            update_option('ttp_airbase_base_url', esc_url_raw($_POST['ttp_airbase_base_url'] ?? ''));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'treasury-tech-portal') . '</p></div>';
        }

        if (isset($_GET['test_success'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Airbase connection successful.', 'treasury-tech-portal') . '</p></div>';
        } elseif (isset($_GET['test_error'])) {
            $message = sanitize_text_field(wp_unslash($_GET['test_error']));
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(sprintf(__('Airbase connection failed: %s', 'treasury-tech-portal'), $message)) . '</p></div>';
        }

        $api_token = get_option(TTP_Airbase::OPTION_TOKEN, '');
        $base_url = get_option('ttp_airbase_base_url', '');
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
                        <th scope="row"><label for="ttp_airbase_base_url"><?php esc_html_e('Base URL', 'treasury-tech-portal'); ?></label></th>
                        <td><input name="ttp_airbase_base_url" type="text" id="ttp_airbase_base_url" value="<?php echo esc_attr($base_url); ?>" class="regular-text" /></td>
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

    public static function render_vendors_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['refreshed'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Vendor cache refreshed.', 'treasury-tech-portal') . '</p></div>';
        }

        $vendors = TTP_Data::get_all_vendors();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Vendors', 'treasury-tech-portal'); ?></h1>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ttp_refresh_vendors'); ?>
                <input type="hidden" name="action" value="ttp_refresh_vendors" />
                <?php submit_button(__('Refresh Vendors', 'treasury-tech-portal')); ?>
            </form>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'treasury-tech-portal'); ?></th>
                        <th><?php esc_html_e('Name', 'treasury-tech-portal'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($vendors)) : ?>
                        <?php foreach ($vendors as $vendor) : ?>
                            <tr>
                                <td><?php echo esc_html($vendor['id'] ?? ''); ?></td>
                                <td><?php echo esc_html($vendor['name'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="2"><?php esc_html_e('No vendors found.', 'treasury-tech-portal'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
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

    public static function refresh_vendors() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ttp_refresh_vendors');
        TTP_Data::refresh_vendor_cache();
        wp_redirect(admin_url('admin.php?page=treasury-vendors&refreshed=1'));
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
