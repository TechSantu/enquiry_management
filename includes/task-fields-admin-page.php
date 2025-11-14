<?php
/**
 * Task Fields Management Admin Page
 */

// Add admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=contact_enquiry',
        'Manage Additional Task Fields',
        'Manage Additional Task Fields',
        'manage_options',
        'ctf-manage-task-fields',
        'ctf_task_fields_management_page'
    );
});

// Task fields management page
function ctf_task_fields_management_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Handle form submissions
    if (isset($_POST['ctf_action']) && check_admin_referer('ctf_task_fields_management')) {
        $action = sanitize_text_field(wp_unslash($_POST['ctf_action']));
        
        if ($action === 'add') {
            $label = isset($_POST['ctf_field_label']) ? sanitize_text_field(wp_unslash($_POST['ctf_field_label'])) : '';
            $field_type = isset($_POST['ctf_field_type']) ? sanitize_text_field(wp_unslash($_POST['ctf_field_type'])) : 'text';
            $meta_key = isset($_POST['ctf_field_meta_key']) ? sanitize_text_field(wp_unslash($_POST['ctf_field_meta_key'])) : '';
            $result = ctf_add_task_field($label, $field_type, $meta_key);
            echo '<div class="notice notice-' . ($result['success'] ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        }
        
        if ($action === 'update') {
            $old_meta_key = isset($_POST['ctf_field_old_meta_key']) ? sanitize_text_field(wp_unslash($_POST['ctf_field_old_meta_key'])) : '';
            $label = isset($_POST['ctf_field_label']) ? sanitize_text_field(wp_unslash($_POST['ctf_field_label'])) : '';
            $field_type = isset($_POST['ctf_field_type']) ? sanitize_text_field(wp_unslash($_POST['ctf_field_type'])) : 'text';
            $meta_key = isset($_POST['ctf_field_meta_key']) ? sanitize_text_field(wp_unslash($_POST['ctf_field_meta_key'])) : '';
            $result = ctf_update_task_field($old_meta_key, $label, $field_type, $meta_key);
            echo '<div class="notice notice-' . ($result['success'] ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        }
        
        if ($action === 'delete') {
            $meta_key = isset($_POST['ctf_field_meta_key']) ? sanitize_text_field(wp_unslash($_POST['ctf_field_meta_key'])) : '';
            $result = ctf_delete_task_field($meta_key);
            echo '<div class="notice notice-' . ($result['success'] ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }
    
    $all_fields = ctf_get_task_fields();
    // Filter out protected fields (used in form)
    $task_fields = array_filter($all_fields, function($field) {
        return !ctf_is_field_used_in_form($field['meta_key']);
    });
    $task_fields = array_values($task_fields);
    ?>
    <div class="wrap">
        <h1>Manage Additional Task Fields</h1>
        <p class="description">Manage custom fields for the "Additional Task Details" section. Fields used in the contact form are protected and not shown here.</p>
        
        <div class="ctf-task-fields-management" style="margin-top: 20px;">
            <!-- Add New Field Form -->
            <div class="postbox" style="margin-bottom: 20px;">
                <div class="postbox-header">
                    <h2 class="hndle">Add New Field</h2>
                </div>
                <div class="inside" style="padding: 20px;">
                    <form method="post" action="">
                        <?php wp_nonce_field('ctf_task_fields_management'); ?>
                        <input type="hidden" name="ctf_action" value="add">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="ctf_field_label">Field Label</label></th>
                                <td>
                                    <input type="text" id="ctf_field_label" name="ctf_field_label" class="regular-text" required />
                                    <p class="description">The label that will be displayed in the Additional Task Details section.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ctf_field_type">Field Type</label></th>
                                <td>
                                    <select id="ctf_field_type" name="ctf_field_type">
                                        <option value="text">Text</option>
                                        <option value="email">Email</option>
                                        <option value="textarea">Textarea</option>
                                        <option value="number">Number</option>
                                        <option value="url">URL</option>
                                        <option value="tel">Phone</option>
                                    </select>
                                    <p class="description">The type of input field.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ctf_field_meta_key">Meta Key (optional)</label></th>
                                <td>
                                    <input type="text" id="ctf_field_meta_key" name="ctf_field_meta_key" class="regular-text" />
                                    <p class="description">Leave empty to auto-generate from the field label.</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <input type="submit" class="button button-primary" value="Add Field" />
                        </p>
                    </form>
                </div>
            </div>
            
            <!-- Current Fields List -->
            <div class="postbox">
                <div class="postbox-header">
                    <h2 class="hndle">Current Custom Fields</h2>
                </div>
                <div class="inside" style="padding: 20px;">
                    <?php if (empty($task_fields)) : ?>
                        <p>No custom fields defined yet. Add your first field above.</p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Label</th>
                                    <th>Type</th>
                                    <th>Meta Key</th>
                                    <th>Usage</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($task_fields as $field) : 
                                    $usage_count = ctf_get_task_field_usage_count($field['meta_key']);
                                    $can_delete = $usage_count === 0;
                                ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($field['label']); ?></strong></td>
                                        <td><?php echo esc_html(ucfirst($field['field_type'])); ?></td>
                                        <td><code><?php echo esc_html($field['meta_key']); ?></code></td>
                                        <td>
                                            <?php if ($usage_count > 0) : ?>
                                                <span style="color: #dc2626;"><?php echo esc_html($usage_count); ?> enquiry(ies)</span>
                                            <?php else : ?>
                                                <span style="color: #10b981;">Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="post" action="" class="ctf-edit-field-form" style="display: none;">
                                                <?php wp_nonce_field('ctf_task_fields_management'); ?>
                                                <input type="hidden" name="ctf_action" value="update">
                                                <input type="hidden" name="ctf_field_old_meta_key" value="<?php echo esc_attr($field['meta_key']); ?>">
                                                <input type="text" name="ctf_field_label" value="<?php echo esc_attr($field['label']); ?>" style="width: 150px; margin-right: 5px;" />
                                                <select name="ctf_field_type" style="width: 100px; margin-right: 5px;">
                                                    <option value="text" <?php selected($field['field_type'], 'text'); ?>>Text</option>
                                                    <option value="email" <?php selected($field['field_type'], 'email'); ?>>Email</option>
                                                    <option value="textarea" <?php selected($field['field_type'], 'textarea'); ?>>Textarea</option>
                                                    <option value="number" <?php selected($field['field_type'], 'number'); ?>>Number</option>
                                                    <option value="url" <?php selected($field['field_type'], 'url'); ?>>URL</option>
                                                    <option value="tel" <?php selected($field['field_type'], 'tel'); ?>>Phone</option>
                                                </select>
                                                <input type="hidden" name="ctf_field_meta_key" value="<?php echo esc_attr($field['meta_key']); ?>">
                                                <button type="submit" class="button button-small button-primary">Save</button>
                                                <button type="button" class="button button-small ctf-cancel-edit">Cancel</button>
                                            </form>
                                            <div class="ctf-field-actions">
                                                <button type="button" class="button button-small ctf-edit-btn" data-meta-key="<?php echo esc_attr($field['meta_key']); ?>">Edit</button>
                                                <form method="post" action="" style="display: inline;">
                                                    <?php wp_nonce_field('ctf_task_fields_management'); ?>
                                                    <input type="hidden" name="ctf_action" value="delete">
                                                    <input type="hidden" name="ctf_field_meta_key" value="<?php echo esc_attr($field['meta_key']); ?>">
                                                    <button type="submit" class="button button-small button-link-delete" 
                                                            <?php echo $can_delete ? '' : 'disabled title="Cannot delete: Has data in ' . $usage_count . ' enquiry(ies)"'; ?>
                                                            onclick="return confirm('Are you sure you want to delete this field? This will remove it from the Additional Task Details section but will not delete existing data.');">
                                                        Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Edit button click
        $('.ctf-edit-btn').on('click', function() {
            var $row = $(this).closest('tr');
            var $actions = $row.find('.ctf-field-actions');
            var $form = $row.find('.ctf-edit-field-form');
            
            // Hide actions, show form
            $actions.hide();
            $form.show();
        });
        
        // Cancel edit
        $('.ctf-cancel-edit').on('click', function() {
            var $row = $(this).closest('tr');
            var $actions = $row.find('.ctf-field-actions');
            var $form = $row.find('.ctf-edit-field-form');
            
            // Hide form, show actions
            $form.hide();
            $actions.show();
        });
    });
    </script>
    <style>
    .ctf-edit-field-form {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .ctf-field-actions {
        display: flex;
        gap: 5px;
    }
    </style>
    <?php
}

