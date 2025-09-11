<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap treasury-portal-admin">
    <h1><?php esc_html_e('Vendors', 'treasury-tech-portal'); ?></h1>
    <?php if (!empty($unresolved_fields)) : ?>
        <div class="notice notice-warning">
            <p><?php esc_html_e('Some product fields could not be resolved.', 'treasury-tech-portal'); ?></p>
            <ul>
                <?php foreach ($unresolved_fields as $field => $ids) : ?>
                    <li><?php echo esc_html(sprintf('%s unresolved IDs: %s', $field, implode(', ', $ids))); ?></li>
                <?php endforeach; ?>
            </ul>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=treasury-unresolved-report')); ?>"><?php esc_html_e('View full report', 'treasury-tech-portal'); ?></a></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('ttp_retry_resolution', 'ttp_retry_resolution_nonce'); ?>
                <input type="hidden" name="action" value="ttp_retry_resolution" />
                <?php submit_button(__('Retry Resolution', 'treasury-tech-portal'), 'secondary', 'retry-resolution', false); ?>
            </form>
        </div>
    <?php endif; ?>
    <p><?php esc_html_e('Use the button below to manually refresh the product cache after changing Airbase settings or when product data appears outdated.', 'treasury-tech-portal'); ?></p>
    <p><?php esc_html_e('Linked field IDs such as regions or categories are automatically converted to names for easier reading.', 'treasury-tech-portal'); ?></p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ttp_refresh_vendors', 'ttp_refresh_vendors_nonce'); ?>
        <input type="hidden" name="action" value="ttp_refresh_vendors" />
        <?php submit_button(__('Refresh Products', 'treasury-tech-portal'), 'primary', 'refresh-vendors'); ?>
    </form>
    <?php if (isset($_GET['refreshed'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Product cache refreshed.', 'treasury-tech-portal'); ?></p></div>
    <?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ttp_save_categories', 'ttp_save_categories_nonce'); ?>
        <input type="hidden" name="action" value="ttp_save_categories" />
        <fieldset>
            <legend class="screen-reader-text"><?php esc_html_e('Enable Categories', 'treasury-tech-portal'); ?></legend>
            <?php
            foreach ( TTP_CATEGORIES as $key => $label ) :
                $checked = in_array($key, $enabled_categories, true);
                ?>
                <label><input type="checkbox" name="enabled_categories[]" value="<?php echo esc_attr($key); ?>" <?php checked($checked); ?> /> <?php echo esc_html( $label ); ?></label><br />
            <?php endforeach; ?>
        </fieldset>
        <?php submit_button(__('Save Categories', 'treasury-tech-portal'), 'secondary', 'save-categories'); ?>
    </form>
    <?php if (isset($_GET['cats_updated'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Categories updated.', 'treasury-tech-portal'); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($vendors)) : ?>
        <div class="treasury-portal-admin-search">
            <label for="treasury-portal-admin-search-input" class="screen-reader-text"><?php esc_html_e('Search vendors', 'treasury-tech-portal'); ?></label>
            <input type="search" id="treasury-portal-admin-search-input" placeholder="<?php esc_attr_e('Search vendors...', 'treasury-tech-portal'); ?>" />
        </div>
        <div class="treasury-portal-admin-table-wrapper">
            <table class="widefat fixed striped treasury-portal-admin-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name', 'treasury-tech-portal'); ?></th>
                        <th><?php esc_html_e('Category Names', 'treasury-tech-portal'); ?></th>
                        <th><?php esc_html_e('Vendor', 'treasury-tech-portal'); ?></th>
                    <th><?php esc_html_e('Website', 'treasury-tech-portal'); ?></th>
                    <th><?php esc_html_e('Video URL', 'treasury-tech-portal'); ?></th>
                    <th><?php esc_html_e('Status', 'treasury-tech-portal'); ?></th>
                    <th class="is-mobile-hidden"><?php esc_html_e('Hosted Type', 'treasury-tech-portal'); ?></th>
                    <th class="is-mobile-hidden"><?php esc_html_e('Domain', 'treasury-tech-portal'); ?></th>
                    <th class="is-mobile-hidden"><?php esc_html_e('Regions', 'treasury-tech-portal'); ?></th>
                    <th class="is-mobile-hidden"><?php esc_html_e('Sub Categories', 'treasury-tech-portal'); ?></th>
                    <th class="is-mobile-hidden"><?php esc_html_e('Category', 'treasury-tech-portal'); ?></th>
                    <th class="is-mobile-hidden"><?php esc_html_e('Capabilities', 'treasury-tech-portal'); ?></th>
                    <th class="is-mobile-hidden"><?php esc_html_e('Logo URL', 'treasury-tech-portal'); ?></th>
                    <th class="is-mobile-hidden"><?php esc_html_e('HQ Location', 'treasury-tech-portal'); ?></th>
                    <th class="is-mobile-hidden"><?php esc_html_e('Founded Year', 'treasury-tech-portal'); ?></th>
                    <th class="is-mobile-hidden"><?php esc_html_e('Founders', 'treasury-tech-portal'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($vendors as $vendor) : ?>
                    <?php
                    $cats         = implode(', ', array_map('sanitize_text_field', (array) ($vendor['category_names'] ?? array())));
                    $hosted       = implode(', ', array_map('sanitize_text_field', (array) ($vendor['hosted_type'] ?? array())));
                    $domain       = implode(', ', array_map('sanitize_text_field', (array) ($vendor['domain'] ?? array())));
                    $regions      = implode(', ', array_map('sanitize_text_field', (array) ($vendor['regions'] ?? array())));
                    $subs         = implode(', ', array_map('sanitize_text_field', (array) ($vendor['sub_categories'] ?? array())));
                    $capabilities = implode(', ', array_map('sanitize_text_field', (array) ($vendor['capabilities'] ?? array())));

                    $website   = $vendor['website'] ?? '';
                    $video_url = $vendor['video_url'] ?? '';
                    $logo_url  = $vendor['logo_url'] ?? '';

                    $website_href   = esc_url($website);
                    $video_href     = esc_url($video_url);
                    $logo_href      = esc_url($logo_url);

                    $website_text   = strlen($website) > 30 ? esc_html__('Visit', 'treasury-tech-portal') : esc_html($website);
                    $video_text     = strlen($video_url) > 30 ? esc_html__('Visit', 'treasury-tech-portal') : esc_html($video_url);
                    $logo_text      = strlen($logo_url) > 30 ? esc_html__('Visit', 'treasury-tech-portal') : esc_html($logo_url);
                    ?>
                    <tr>
                        <td data-label="<?php echo esc_attr__('Name', 'treasury-tech-portal'); ?>">
                            <?php
                            echo esc_html($vendor['name'] ?? '');
                            if (!empty($vendor['id'])) {
                                echo ' (' . esc_html($vendor['id']) . ')';
                            }
                            ?>
                        </td>
                        <td data-label="<?php echo esc_attr__('Category Names', 'treasury-tech-portal'); ?>"><?php echo esc_html($cats); ?></td>
                        <td data-label="<?php echo esc_attr__('Vendor', 'treasury-tech-portal'); ?>"><?php echo esc_html($vendor['vendor'] ?? ''); ?></td>
                        <td data-label="<?php echo esc_attr__('Website', 'treasury-tech-portal'); ?>">
                            <?php if (!empty($website_href)) : ?>
                                <a href="<?php echo $website_href; ?>" target="_blank" rel="noopener noreferrer"><?php echo $website_text; ?></a>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php echo esc_attr__('Video URL', 'treasury-tech-portal'); ?>">
                            <?php if (!empty($video_href)) : ?>
                                <a href="<?php echo $video_href; ?>" target="_blank" rel="noopener noreferrer"><?php echo $video_text; ?></a>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php echo esc_attr__('Status', 'treasury-tech-portal'); ?>"><?php echo esc_html($vendor['status'] ?? ''); ?></td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Hosted Type', 'treasury-tech-portal'); ?>"><?php echo esc_html($hosted); ?></td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Domain', 'treasury-tech-portal'); ?>"><?php echo esc_html($domain); ?></td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Regions', 'treasury-tech-portal'); ?>"><?php echo esc_html($regions); ?></td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Sub Categories', 'treasury-tech-portal'); ?>"><?php echo esc_html($subs); ?></td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Category', 'treasury-tech-portal'); ?>"><?php echo esc_html($vendor['category'] ?? ($vendor['categories'][0] ?? '')); ?></td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Capabilities', 'treasury-tech-portal'); ?>"><?php echo esc_html($capabilities); ?></td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Logo URL', 'treasury-tech-portal'); ?>">
                            <?php if (!empty($logo_href)) : ?>
                                <a href="<?php echo $logo_href; ?>" target="_blank" rel="noopener noreferrer"><?php echo $logo_text; ?></a>
                            <?php endif; ?>
                        </td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('HQ Location', 'treasury-tech-portal'); ?>"><?php echo esc_html($vendor['hq_location'] ?? ''); ?></td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Founded Year', 'treasury-tech-portal'); ?>"><?php echo esc_html($vendor['founded_year'] ?? ''); ?></td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Founders', 'treasury-tech-portal'); ?>"><?php echo esc_html($vendor['founders'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <p><?php esc_html_e('No vendors found.', 'treasury-tech-portal'); ?></p>
    <?php endif; ?>
    <?php if (!empty($unresolved_fields)) : ?>
        <div class="treasury-portal-admin-unresolved">
            <h2><?php esc_html_e('Unresolved IDs', 'treasury-tech-portal'); ?></h2>
            <?php foreach ($unresolved_fields as $field => $ids) : ?>
                <h3><?php echo esc_html($field); ?></h3>
                <ul>
                    <?php foreach ($ids as $id) : ?>
                        <li><code><?php echo esc_html($id); ?></code></li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
