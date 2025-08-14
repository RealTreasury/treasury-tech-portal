<?php
namespace TreasuryTechPortal;

if (!defined('ABSPATH')) exit;

use function \sanitize_text_field;
use function \selected;
use function \wp_nonce_field;
use function \admin_url;
use function \wp_nonce_url;
use function \esc_html;
use function \submit_button;
use function \wp_json_encode;
?>
<div class="wrap">
    <h1><?php _e('Treasury Tools', 'treasury-tech-portal'); ?></h1>
    <?php $current_sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : ''; ?>
    <form method="get" style="margin-bottom:10px;">
        <input type="hidden" name="page" value="treasury-tools">
        <label for="ttp-sort"><?php _e('Sort By:', 'treasury-tech-portal'); ?></label>
        <select name="sort" id="ttp-sort" onchange="this.form.submit()">
            <option value="" <?php selected($current_sort, ''); ?>><?php _e('Default', 'treasury-tech-portal'); ?></option>
            <option value="name" <?php selected($current_sort, 'name'); ?>><?php _e('Name', 'treasury-tech-portal'); ?></option>
            <option value="category" <?php selected($current_sort, 'category'); ?>><?php _e('Category', 'treasury-tech-portal'); ?></option>
        </select>
    </form>
    <?php if (!empty($_GET['updated'])): ?>
        <div class="updated notice"><p><?php _e('Tool saved.', 'treasury-tech-portal'); ?></p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?>
        <div class="updated notice"><p><?php _e('Tool deleted.', 'treasury-tech-portal'); ?></p></div>
    <?php endif; ?>

    <table class="widefat">
        <thead>
            <tr>
                <th><?php _e('Name', 'treasury-tech-portal'); ?></th>
                <th><?php _e('Category', 'treasury-tech-portal'); ?></th>
                <th><?php _e('Actions', 'treasury-tech-portal'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tools as $i => $tool): ?>
                <tr>
                    <td><?php echo esc_html($tool['name']); ?></td>
                    <td><?php echo esc_html($tool['category']); ?></td>
                    <td>
                        <a href="#" class="edit-tool" data-index="<?php echo $i; ?>"><?php _e('Edit', 'treasury-tech-portal'); ?></a> |
                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=ttp_delete_tool&index=' . $i), 'ttp_delete_tool'); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this tool?', 'treasury-tech-portal')); ?>');"><?php _e('Delete', 'treasury-tech-portal'); ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2 id="add-new-tool"><?php _e('Add / Edit Tool', 'treasury-tech-portal'); ?></h2>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('ttp_save_tool'); ?>
        <input type="hidden" name="action" value="ttp_save_tool">
        <input type="hidden" name="index" id="tool-index">
        <table class="form-table">
            <tr>
                <th><label for="tool-name"><?php _e('Name', 'treasury-tech-portal'); ?></label></th>
                <td><input name="name" id="tool-name" type="text" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="tool-category"><?php _e('Category', 'treasury-tech-portal'); ?></label></th>
                <td><input name="category" id="tool-category" type="text" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="tool-desc"><?php _e('Description', 'treasury-tech-portal'); ?></label></th>
                <td><textarea name="desc" id="tool-desc" class="large-text"></textarea></td>
            </tr>
            <tr>
                <th><label for="tool-features"><?php _e('Features (one per line)', 'treasury-tech-portal'); ?></label></th>
                <td><textarea name="features" id="tool-features" class="large-text"></textarea></td>
            </tr>
            <tr>
                <th><label for="tool-target"><?php _e('Target', 'treasury-tech-portal'); ?></label></th>
                <td><input name="target" id="tool-target" type="text" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tool-video"><?php _e('Video URL', 'treasury-tech-portal'); ?></label></th>
                <td><input name="videoUrl" id="tool-video" type="url" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tool-website"><?php _e('Website URL', 'treasury-tech-portal'); ?></label></th>
                <td><input name="websiteUrl" id="tool-website" type="url" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tool-logo"><?php _e('Logo URL', 'treasury-tech-portal'); ?></label></th>
                <td><input name="logoUrl" id="tool-logo" type="url" class="regular-text"></td>
            </tr>
        </table>
        <?php submit_button(__('Save Tool', 'treasury-tech-portal')); ?>
    </form>
</div>
<script>
(function(){
    const tools = <?php echo wp_json_encode($tools); ?>;
    document.querySelectorAll('.edit-tool').forEach(link => {
        link.addEventListener('click', function(e){
            e.preventDefault();
            const index = parseInt(this.dataset.index);
            const tool = tools[index];
            document.getElementById('tool-index').value = index;
            document.getElementById('tool-name').value = tool.name;
            document.getElementById('tool-category').value = tool.category;
            document.getElementById('tool-desc').value = tool.desc;
            document.getElementById('tool-features').value = (tool.features || []).join('\n');
            document.getElementById('tool-target').value = tool.target || '';
            document.getElementById('tool-video').value = tool.videoUrl || '';
            document.getElementById('tool-website').value = tool.websiteUrl || '';
            document.getElementById('tool-logo').value = tool.logoUrl || '';
            location.hash = 'add-new-tool';
        });
    });
})();
</script>
