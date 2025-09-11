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
        if ($hook !== 'toplevel_page_treasury-tools' && $hook !== 'treasury-tools_page_treasury-airbase-settings') {
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

        $js_file = TTP_DIR . 'assets/js/treasury-portal-admin.js';
        $js_ver  = file_exists($js_file) ? filemtime($js_file) : '1.0';
        wp_enqueue_script(
            'treasury-tech-portal-admin-js',
            TTP_URL . 'assets/js/treasury-portal-admin.js',
            array(),
            $js_ver,
            true
        );
    }

    public static function render_airbase_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('ttp_airbase_settings', 'ttp_airbase_settings_nonce');
            update_option(TTP_Airbase::OPTION_TOKEN, sanitize_text_field($_POST[TTP_Airbase::OPTION_TOKEN] ?? ''));
            update_option(TTP_Airbase::OPTION_BASE_URL, esc_url_raw($_POST[TTP_Airbase::OPTION_BASE_URL] ?? ''));
            update_option(TTP_Airbase::OPTION_BASE_ID, sanitize_text_field($_POST[TTP_Airbase::OPTION_BASE_ID] ?? ''));
            update_option(TTP_Airbase::OPTION_API_PATH, sanitize_text_field($_POST[TTP_Airbase::OPTION_API_PATH] ?? ''));
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'treasury-tech-portal') . '</p></div>';
        }

        if (isset($_GET['test_success'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Airbase vendor fetch successful.', 'treasury-tech-portal') . '</p></div>';
        } elseif (isset($_GET['test_error'])) {
            $message = sanitize_text_field(wp_unslash($_GET['test_error']));
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(sprintf(__('Airbase vendor fetch failed: %s', 'treasury-tech-portal'), $message)) . '</p></div>';
        }

        $missing_fields = array();
        if ( function_exists( 'get_option' ) ) {
            $missing_fields = (array) get_option( 'ttp_missing_fields', array() );
            if ( ! empty( $missing_fields ) && function_exists( 'delete_option' ) ) {
                delete_option( 'ttp_missing_fields' );
            }
        }

        if ( ! empty( $missing_fields ) ) {
            $names = array_map( 'sanitize_text_field', (array) ( $missing_fields['fields'] ?? array() ) );
            $ids   = array_map( 'sanitize_text_field', (array) ( $missing_fields['ids'] ?? array() ) );
            $message = sprintf( __( 'Missing expected fields from Airtable: %s', 'treasury-tech-portal' ), implode( ', ', $names ) );
            if ( ! empty( $ids ) ) {
                $message .= ' ' . sprintf( __( '(Requested field IDs: %s)', 'treasury-tech-portal' ), implode( ', ', $ids ) );
            }
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        }

        $api_token = get_option(TTP_Airbase::OPTION_TOKEN, '');
        $base_url  = get_option(TTP_Airbase::OPTION_BASE_URL, '');
        $base_id   = get_option(TTP_Airbase::OPTION_BASE_ID, '');
        $api_path  = get_option(TTP_Airbase::OPTION_API_PATH, '');

        if (empty($base_id)) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Base ID is required to fetch Airbase data.', 'treasury-tech-portal') . '</p></div>';
        }
        if (empty($api_path)) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Products table ID is required to fetch Airbase data.', 'treasury-tech-portal') . '</p></div>';
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Airbase Settings', 'treasury-tech-portal'); ?></h1>
            <form method="post">
                <?php wp_nonce_field('ttp_airbase_settings', 'ttp_airbase_settings_nonce'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(TTP_Airbase::OPTION_TOKEN); ?>"><?php esc_html_e('API Token', 'treasury-tech-portal'); ?></label></th>
                        <td>
                            <input name="<?php echo esc_attr(TTP_Airbase::OPTION_TOKEN); ?>" type="password" id="<?php echo esc_attr(TTP_Airbase::OPTION_TOKEN); ?>" value="<?php echo esc_attr($api_token); ?>" class="regular-text" autocomplete="current-password" />
                            <button type="button" id="<?php echo esc_attr(TTP_Airbase::OPTION_TOKEN); ?>_toggle" class="button"><?php esc_html_e('Reveal', 'treasury-tech-portal'); ?></button>
                        </td>
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
                        <td>
                            <input name="<?php echo esc_attr(TTP_Airbase::OPTION_BASE_ID); ?>" type="text" id="<?php echo esc_attr(TTP_Airbase::OPTION_BASE_ID); ?>" value="<?php echo esc_attr($base_id); ?>" class="regular-text" />
                            <?php if (empty($base_id)) : ?>
                                <p class="description" style="color:#b32d2e;">
                                    <?php esc_html_e('Please enter a base ID.', 'treasury-tech-portal'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="<?php echo esc_attr(TTP_Airbase::OPTION_API_PATH); ?>"><?php esc_html_e('API Path', 'treasury-tech-portal'); ?></label></th>
                        <td>
                            <input name="<?php echo esc_attr(TTP_Airbase::OPTION_API_PATH); ?>" type="text" id="<?php echo esc_attr(TTP_Airbase::OPTION_API_PATH); ?>" value="<?php echo esc_attr($api_path); ?>" class="regular-text" />
                            <?php if (empty($api_path)) : ?>
                                <p class="description" style="color:#b32d2e;">
                                    <?php esc_html_e('Please enter a products table ID.', 'treasury-tech-portal'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Changes', 'treasury-tech-portal'), 'primary', 'save-changes'); ?>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ttp_test_airbase', 'ttp_test_airbase_nonce'); ?>
                <input type="hidden" name="action" value="ttp_test_airbase" />
                <?php submit_button(__('Test Connection', 'treasury-tech-portal'), 'secondary', 'test-connection'); ?>
            </form>
        </div>
        <?php
    }

    public static function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $unresolved_fields = array();
        if ( function_exists( 'get_option' ) ) {
            $unresolved_fields = (array) get_option( 'ttp_unresolved_fields', array() );
            if ( ! empty( $unresolved_fields ) && function_exists( 'delete_option' ) ) {
                delete_option( 'ttp_unresolved_fields' );
            }
        }

        $vendors = TTP_Data::get_all_vendors();
        include dirname(__DIR__) . '/templates/admin-page.php';
    }

    public static function refresh_vendors() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ttp_refresh_vendors', 'ttp_refresh_vendors_nonce');
        TTP_Data::refresh_vendor_cache();
        wp_redirect(admin_url('admin.php?page=treasury-tools&refreshed=1'));
        exit;
    }

    public static function test_airbase_connection() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('ttp_test_airbase', 'ttp_test_airbase_nonce');

        $field_names = array(
            'Product Name',
            'Linked Vendor',
            'Product Website',
            'Product Video',
            'Logo URL',
            'Status',
            'Hosted Type',
            'Domain',
            'Regions',
            'Category',
            'Sub Categories',
            'Capabilities',
            'HQ Location',
            'Founded Year',
            'Founders',
        );

        $mapping    = TTP_Airbase::map_field_names( $field_names );
        $schema_map = $mapping['schema_map'];
        $field_ids  = $mapping['field_ids'];

        $id_to_name = array_flip( $schema_map );

        $result = TTP_Airbase::get_vendors( $field_ids, true );
        $url    = admin_url('admin.php?page=treasury-airbase-settings');
        if ( is_wp_error( $result ) ) {
            $url = add_query_arg( 'test_error', rawurlencode( $result->get_error_message() ), $url );
        } else {
            $data = $result;
            if ( is_array( $data ) ) {
                $known = array( 'products', 'records', 'vendors' );
                foreach ( $known as $key ) {
                    if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
                        $data = $data[ $key ];
                        break;
                    }
                }
                if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
                    $data = $data['data'];
                }
            } else {
                $data = array();
            }

            foreach ( $data as &$record ) {
                if ( isset( $record['fields'] ) && is_array( $record['fields'] ) ) {
                    $mapped = array();
                    foreach ( $record['fields'] as $key => $value ) {
                        $mapped[ isset( $id_to_name[ $key ] ) ? $id_to_name[ $key ] : $key ] = $value;
                    }
                    $record['fields'] = $mapped;
                }
            }
            unset( $record );

            $present = array();
            foreach ( $data as $record ) {
                if ( isset( $record['fields'] ) && is_array( $record['fields'] ) ) {
                    $present = array_unique( array_merge( $present, array_keys( $record['fields'] ) ) );
                }
            }

            $missing = array_diff( $field_names, $present );
            if ( ! empty( $missing ) ) {
                $url = add_query_arg( 'test_error', rawurlencode( 'Missing fields: ' . implode( ', ', $missing ) ), $url );
            } else {
                $url = add_query_arg( 'test_success', 1, $url );
            }
        }

        wp_redirect( $url );
        exit;
    }
}
