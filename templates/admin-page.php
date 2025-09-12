<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap treasury-portal-admin">
    <h1><?php esc_html_e('Products', 'treasury-tech-portal'); ?></h1>
    <p><?php esc_html_e('Use the button below to manually refresh the product cache after changing Airbase settings or when product data appears outdated.', 'treasury-tech-portal'); ?></p>
    <p><?php esc_html_e('Linked field IDs such as regions or categories are automatically converted to names for easier reading.', 'treasury-tech-portal'); ?></p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ttp_refresh_products', 'ttp_refresh_products_nonce'); ?>
        <input type="hidden" name="action" value="ttp_refresh_products" />
        <?php submit_button(__('Refresh Products', 'treasury-tech-portal'), 'primary', 'refresh-products'); ?>
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
            foreach ( $categories as $key => $label ) :
                $checked = in_array( $key, $enabled_categories, true );
                ?>
                <label><input type="checkbox" name="enabled_categories[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $checked ); ?> /> <?php echo esc_html( $label ); ?></label>
            <?php endforeach; ?>
        </fieldset>
        <?php submit_button(__('Save Categories', 'treasury-tech-portal'), 'secondary', 'save-categories'); ?>
    </form>
    <?php if (isset($_GET['cats_updated'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Categories updated.', 'treasury-tech-portal'); ?></p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('ttp_save_domains', 'ttp_save_domains_nonce'); ?>
        <input type="hidden" name="action" value="ttp_save_domains" />
        <fieldset>
            <legend class="screen-reader-text"><?php esc_html_e('Enable Domains', 'treasury-tech-portal'); ?></legend>
            <?php foreach ( $domains as $key => $label ) :
                $checked = in_array( $key, $enabled_domains, true );
                ?>
                <label><input type="checkbox" name="enabled_domains[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $checked ); ?> /> <?php echo esc_html( $label ); ?></label>
            <?php endforeach; ?>
        </fieldset>
        <?php submit_button(__('Save Domains', 'treasury-tech-portal'), 'secondary', 'save-domains'); ?>
    </form>
    <?php if (isset($_GET['domains_updated'])) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Domains updated.', 'treasury-tech-portal'); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($vendors)) : ?>
        <?php
        $statuses = array_unique(
            array_filter(
                array_map(
                    function ($vendor) {
                        return sanitize_text_field($vendor['status'] ?? '');
                    },
                    $vendors
                )
            )
        );
        sort($statuses);
        ?>
        <div class="treasury-portal-admin-search">
            <label for="treasury-portal-admin-search-input" class="screen-reader-text"><?php esc_html_e('Search vendors', 'treasury-tech-portal'); ?></label>
            <input type="search" id="treasury-portal-admin-search-input" placeholder="<?php esc_attr_e('Search vendors...', 'treasury-tech-portal'); ?>" />
        </div>
        <button type="button" class="button tp-filter-toggle"><?php esc_html_e('Filter', 'treasury-tech-portal'); ?></button>
        <div class="tp-filter-panel"><div class="tp-filter-panel-content"></div></div>
        <div class="treasury-portal-admin-table-wrapper">
            <table class="widefat fixed striped treasury-portal-admin-table">
                <thead>
                    <tr>
                        <th data-sort-key="name"><div class="tp-header-cell"><?php esc_html_e('Name', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th data-sort-key="category_names"><div class="tp-header-cell"><?php esc_html_e('Category Names', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th data-sort-key="vendor"><div class="tp-header-cell"><?php esc_html_e('Vendor', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th data-sort-key="website"><div class="tp-header-cell"><?php esc_html_e('Website', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th data-sort-key="video_url"><div class="tp-header-cell"><?php esc_html_e('Video URL', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th data-sort-key="status"><div class="tp-header-cell"><?php esc_html_e('Status', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th class="is-mobile-hidden" data-sort-key="hosted_type"><div class="tp-header-cell"><?php esc_html_e('Hosted Type', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th class="is-mobile-hidden" data-sort-key="domain"><div class="tp-header-cell"><?php esc_html_e('Domain', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th class="is-mobile-hidden" data-sort-key="regions"><div class="tp-header-cell"><?php esc_html_e('Regions', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th class="is-mobile-hidden" data-sort-key="sub_categories"><div class="tp-header-cell"><?php esc_html_e('Sub Categories', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th class="is-mobile-hidden" data-sort-key="category"><div class="tp-header-cell"><?php esc_html_e('Category', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th class="is-mobile-hidden" data-sort-key="capabilities"><div class="tp-header-cell"><?php esc_html_e('Additional Capabilities', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th class="is-mobile-hidden" data-sort-key="logo_url"><div class="tp-header-cell"><?php esc_html_e('Logo URL', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th class="is-mobile-hidden" data-sort-key="hq_location"><div class="tp-header-cell"><?php esc_html_e('HQ Location', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th class="is-mobile-hidden" data-sort-key="founded_year"><div class="tp-header-cell"><?php esc_html_e('Founded Year', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                        <th class="is-mobile-hidden" data-sort-key="founders"><div class="tp-header-cell"><?php esc_html_e('Founders', 'treasury-tech-portal'); ?><span class="tp-resizer"></span></div></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="tp-filter-row">
                        <td data-label="<?php echo esc_attr__('Name', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-name"><?php esc_html_e('Filter by name', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-name" class="tp-filter-control" data-filter-key="name" placeholder="<?php esc_attr_e('Filter name', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td data-label="<?php echo esc_attr__('Category Names', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-category-names"><?php esc_html_e('Filter by category names', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-category-names" class="tp-filter-control" data-filter-key="category_names" placeholder="<?php esc_attr_e('Filter categories', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td data-label="<?php echo esc_attr__('Vendor', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-vendor"><?php esc_html_e('Filter by vendor', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-vendor" class="tp-filter-control" data-filter-key="vendor" placeholder="<?php esc_attr_e('Filter vendor', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td data-label="<?php echo esc_attr__('Website', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-website"><?php esc_html_e('Filter by website', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-website" class="tp-filter-control" data-filter-key="website" placeholder="<?php esc_attr_e('Filter website', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td data-label="<?php echo esc_attr__('Video URL', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-video-url"><?php esc_html_e('Filter by video URL', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-video-url" class="tp-filter-control" data-filter-key="video_url" placeholder="<?php esc_attr_e('Filter video URL', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td data-label="<?php echo esc_attr__('Status', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-status"><?php esc_html_e('Filter by status', 'treasury-tech-portal'); ?></label>
                            <select id="tp-filter-status" class="tp-filter-control" data-filter-key="status" data-match="exact">
                                <option value=""><?php esc_html_e('All', 'treasury-tech-portal'); ?></option>
                                <?php foreach ($statuses as $status) : ?>
                                    <option value="<?php echo esc_attr(strtolower($status)); ?>"><?php echo esc_html($status); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Hosted Type', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-hosted-type"><?php esc_html_e('Filter by hosted type', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-hosted-type" class="tp-filter-control" data-filter-key="hosted_type" placeholder="<?php esc_attr_e('Filter hosted type', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Domain', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-domain"><?php esc_html_e('Filter by domain', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-domain" class="tp-filter-control" data-filter-key="domain" placeholder="<?php esc_attr_e('Filter domain', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Regions', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-regions"><?php esc_html_e('Filter by regions', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-regions" class="tp-filter-control" data-filter-key="regions" placeholder="<?php esc_attr_e('Filter regions', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Sub Categories', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-sub-categories"><?php esc_html_e('Filter by sub categories', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-sub-categories" class="tp-filter-control" data-filter-key="sub_categories" placeholder="<?php esc_attr_e('Filter sub categories', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Category', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-category"><?php esc_html_e('Filter by category', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-category" class="tp-filter-control" data-filter-key="category" placeholder="<?php esc_attr_e('Filter category', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Additional Capabilities', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-capabilities"><?php esc_html_e('Filter by additional capabilities', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-capabilities" class="tp-filter-control" data-filter-key="capabilities" placeholder="<?php esc_attr_e('Filter additional capabilities', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Logo URL', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-logo-url"><?php esc_html_e('Filter by logo URL', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-logo-url" class="tp-filter-control" data-filter-key="logo_url" placeholder="<?php esc_attr_e('Filter logo URL', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('HQ Location', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-hq-location"><?php esc_html_e('Filter by HQ location', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-hq-location" class="tp-filter-control" data-filter-key="hq_location" placeholder="<?php esc_attr_e('Filter HQ location', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Founded Year', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-founded-year"><?php esc_html_e('Filter by founded year', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-founded-year" class="tp-filter-control" data-filter-key="founded_year" placeholder="<?php esc_attr_e('Filter founded year', 'treasury-tech-portal'); ?>" />
                        </td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Founders', 'treasury-tech-portal'); ?>">
                            <label class="screen-reader-text" for="tp-filter-founders"><?php esc_html_e('Filter by founders', 'treasury-tech-portal'); ?></label>
                            <input type="text" id="tp-filter-founders" class="tp-filter-control" data-filter-key="founders" placeholder="<?php esc_attr_e('Filter founders', 'treasury-tech-portal'); ?>" />
                        </td>
                    </tr>
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
                        <td data-label="<?php echo esc_attr__('Website', 'treasury-tech-portal'); ?>" data-filter-value="<?php echo esc_url($website); ?>">
                            <?php if (!empty($website_href)) : ?>
                                <a href="<?php echo $website_href; ?>" target="_blank" rel="noopener noreferrer"><?php echo $website_text; ?></a>
                            <?php endif; ?>
                        </td>
                        <td data-label="<?php echo esc_attr__('Video URL', 'treasury-tech-portal'); ?>" data-filter-value="<?php echo esc_url($video_url); ?>">
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
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Additional Capabilities', 'treasury-tech-portal'); ?>"><?php echo esc_html($capabilities); ?></td>
                        <td class="is-mobile-hidden" data-label="<?php echo esc_attr__('Logo URL', 'treasury-tech-portal'); ?>" data-filter-value="<?php echo esc_url($logo_url); ?>">
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
</div>
