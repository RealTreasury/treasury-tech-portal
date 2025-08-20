<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap treasury-portal-admin">
    <style>
        .treasury-portal-admin__table-wrapper {
            overflow-x: auto;
        }

        .treasury-portal-admin__table {
            width: 100%;
        }

        @media (max-width: 768px) {
            .treasury-portal-admin__table thead {
                display: none;
            }

            .treasury-portal-admin__table tr {
                display: block;
                border-bottom: 1px solid #ccc;
                margin-bottom: 10px;
            }

            .treasury-portal-admin__table td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px;
            }

            .treasury-portal-admin__table td::before {
                content: attr(data-label);
                font-weight: 600;
                margin-right: 10px;
            }
        }
    </style>
    <h1>Treasury Tools</h1>
    <?php $current_sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : ''; ?>
    <form method="get" style="margin-bottom:10px;">
        <input type="hidden" name="page" value="treasury-tools">
        <label for="ttp-sort">Sort By:</label>
        <select name="sort" id="ttp-sort" onchange="this.form.submit()">
            <option value="" <?php selected($current_sort, ''); ?>>Default</option>
            <option value="name" <?php selected($current_sort, 'name'); ?>>Name</option>
            <option value="category" <?php selected($current_sort, 'category'); ?>>Category</option>
        </select>
    </form>
    <?php if (!empty($_GET['updated'])): ?>
        <div class="updated notice"><p>Tool saved.</p></div>
    <?php endif; ?>
    <?php if (!empty($_GET['deleted'])): ?>
        <div class="updated notice"><p>Tool deleted.</p></div>
    <?php endif; ?>

    <div class="treasury-portal-admin__table-wrapper">
        <table class="widefat treasury-portal-admin__table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tools as $i => $tool): ?>
                    <tr>
                        <td data-label="Name"><?php echo esc_html($tool['name']); ?></td>
                        <td data-label="Category"><?php echo esc_html($tool['category']); ?></td>
                        <td data-label="Actions">
                            <a href="#" class="edit-tool" data-index="<?php echo $i; ?>">Edit</a> |
                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=ttp_delete_tool&index=' . $i), 'ttp_delete_tool'); ?>" onclick="return confirm('Delete this tool?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 id="add-new-tool">Add / Edit Tool</h2>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <?php wp_nonce_field('ttp_save_tool'); ?>
        <input type="hidden" name="action" value="ttp_save_tool">
        <input type="hidden" name="index" id="tool-index">
        <table class="form-table">
            <tr>
                <th><label for="tool-name">Name</label></th>
                <td><input name="name" id="tool-name" type="text" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="tool-category">Category</label></th>
                <td><input name="category" id="tool-category" type="text" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="tool-desc">Description</label></th>
                <td><textarea name="desc" id="tool-desc" class="large-text"></textarea></td>
            </tr>
            <tr>
                <th><label for="tool-features">Features (one per line)</label></th>
                <td><textarea name="features" id="tool-features" class="large-text"></textarea></td>
            </tr>
            <tr>
                <th><label for="tool-target">Target</label></th>
                <td><input name="target" id="tool-target" type="text" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tool-video">Video URL</label></th>
                <td><input name="videoUrl" id="tool-video" type="url" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tool-website">Website URL</label></th>
                <td><input name="websiteUrl" id="tool-website" type="url" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="tool-logo">Logo URL</label></th>
                <td><input name="logoUrl" id="tool-logo" type="url" class="regular-text"></td>
            </tr>
        </table>
        <?php submit_button('Save Tool'); ?>
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
