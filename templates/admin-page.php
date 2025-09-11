<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap treasury-portal-admin">
    <h1><?php esc_html_e('Vendors', 'treasury-tech-portal'); ?></h1>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ttp_refresh_vendors'); ?>
        <input type="hidden" name="action" value="ttp_refresh_vendors" />
        <?php submit_button(__('Refresh Vendors', 'treasury-tech-portal')); ?>
    </form>
    <?php if (isset($_GET['refreshed'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Vendor cache refreshed.', 'treasury-tech-portal'); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($vendors)) : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'treasury-tech-portal'); ?></th>
                    <th><?php esc_html_e('Parent Category', 'treasury-tech-portal'); ?></th>
                    <th><?php esc_html_e('Subcategories', 'treasury-tech-portal'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $vendor) : ?>
                    <?php
                    $parent = $vendor['parent_category'] ?? '';
                    if (is_array($parent)) {
                        $parent = implode(', ', array_map('sanitize_text_field', $parent));
                    } else {
                        $parent = sanitize_text_field($parent);
                    }
                    $sub_cats = implode(', ', array_map('sanitize_text_field', (array) ($vendor['sub_categories'] ?? array())));
                    ?>
                    <tr>
                        <td><?php echo esc_html($vendor['name'] ?? ''); ?></td>
                        <td><?php echo esc_html($parent); ?></td>
                        <td><?php echo esc_html($sub_cats); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php esc_html_e('No vendors found.', 'treasury-tech-portal'); ?></p>
    <?php endif; ?>
</div>
