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
        <ul>
            <?php foreach ($vendors as $vendor) : ?>
                <li>
                    <?php echo esc_html($vendor['name'] ?? ''); ?>
                    <?php if (!empty($vendor['id'])) : ?>
                        (<?php echo esc_html__('ID:', 'treasury-tech-portal'); ?> <?php echo esc_html($vendor['id']); ?>)
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else : ?>
        <p><?php esc_html_e('No vendors found.', 'treasury-tech-portal'); ?></p>
    <?php endif; ?>
</div>
