<?php
add_action('init', function(){
    register_post_type('contact_enquiry', [
        'label' => 'Enquiries',
        'public' => false,
        'show_ui' => true,
        'supports' => ['title', 'editor'],
        'menu_icon' => 'dashicons-email-alt',
        'capability_type' => ['contact_enquiry','contact_enquiries'],
        'map_meta_cap' => true,
    ]);
});

add_action('add_meta_boxes', function() {
    add_meta_box('ctf_task_box', 'Task Details', 'ctf_task_box_html', 'contact_enquiry', 'side');
    add_meta_box('ctf_additional_task_details_box', 'Additional Task Details', 'ctf_additional_task_details_box_html', 'contact_enquiry', 'side');
    add_meta_box('ctf_status_box', 'Status', 'ctf_status_box_html', 'contact_enquiry', 'side', 'high');
    add_meta_box('ctf_assign_box', 'Assign to', 'ctf_assign_box_html', 'contact_enquiry', 'side', 'high');
    if (current_user_can('manage_options')) {
        add_meta_box('ctf_manage_task_fields_box', 'Manage Additional Task Fields', 'ctf_manage_task_fields_box_html', 'contact_enquiry', 'side');
    }
    add_meta_box('ctf_change_log_box', 'Change Log', 'ctf_change_log_box_html', 'contact_enquiry', 'normal');
    add_meta_box('ctf_internal_comments_box', 'Internal Comments', 'ctf_internal_comments_box_html', 'contact_enquiry', 'normal');
});

// Helper function to render field input
function ctf_render_task_field_input($field, $value = '') {
    $meta_key = $field['meta_key'];
    $label = $field['label'];
    $field_type = $field['field_type'] ?? 'text';
    $name = 'ctf_task_' . $meta_key;
    
    echo '<p><strong>' . esc_html($label) . ':</strong></p>';
    
    switch ($field_type) {
        case 'textarea':
            echo '<textarea name="' . esc_attr($name) . '" style="width:100%;min-height:60px;" placeholder="' . esc_attr($label) . '">' . esc_textarea($value) . '</textarea>';
            break;
        case 'email':
            echo '<input type="email" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" style="width:100%" placeholder="' . esc_attr($label) . '" />';
            break;
        case 'number':
            echo '<input type="number" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" style="width:100%" placeholder="' . esc_attr($label) . '" />';
            break;
        case 'url':
            echo '<input type="url" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" style="width:100%" placeholder="' . esc_attr($label) . '" />';
            break;
        case 'tel':
            echo '<input type="tel" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" style="width:100%" placeholder="' . esc_attr($label) . '" />';
            break;
        default: // text
            echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" style="width:100%" placeholder="' . esc_attr($label) . '" />';
            break;
    }
}

function ctf_task_box_html($post) {
    // Show only protected fields (used in form)
    $all_task_fields = ctf_get_task_fields();
    $protected_fields = array_filter($all_task_fields, function($field) {
        return ctf_is_field_used_in_form($field['meta_key']);
    });
    ?>
    <input type="hidden" id="ctf_task_box_nonce" name="ctf_task_box_nonce" value="<?php echo wp_create_nonce('ctf_task_box_nonce_action'); ?>" />
    
    <?php foreach ($protected_fields as $field) : 
        $value = get_post_meta($post->ID, $field['meta_key'], true);
        ctf_render_task_field_input($field, $value);
    endforeach; ?>
    <?php
}

// Additional Task Details meta box (for custom fields only)
function ctf_additional_task_details_box_html($post) {
    // Show only custom fields (not used in form)
    $all_task_fields = ctf_get_task_fields();
    $custom_fields = array_filter($all_task_fields, function($field) {
        return !ctf_is_field_used_in_form($field['meta_key']);
    });
    // Re-index array after filtering
    $custom_fields = array_values($custom_fields);
    ?>
    <input type="hidden" id="ctf_additional_task_details_nonce" name="ctf_task_box_nonce" value="<?php echo wp_create_nonce('ctf_task_box_nonce_action'); ?>" />
    
    <?php if (empty($custom_fields)) : ?>
        <p class="description">No additional fields defined yet. Use "Manage Additional Task Fields" to add custom fields.</p>
    <?php else : ?>
        <?php foreach ($custom_fields as $field) : 
            $value = get_post_meta($post->ID, $field['meta_key'], true);
            ctf_render_task_field_input($field, $value);
        endforeach; ?>
    <?php endif; ?>
    <?php
}

// Manage Task Fields meta box (separate, for admins only)
function ctf_manage_task_fields_box_html($post) {
    $all_task_fields = ctf_get_task_fields();
    // Filter out protected fields (used in form)
    $task_fields = array_filter($all_task_fields, function($field) {
        return !ctf_is_field_used_in_form($field['meta_key']);
    });
    // Re-index array after filtering
    $task_fields = array_values($task_fields);
    ?>
    <p class="description">Manage custom fields for the "Additional Task Details" section. Fields added here will appear in a separate meta box below the main Task Details. Fields used in the contact form are protected and not shown here.</p>
    <p style="margin-top: 10px;">
        <a href="<?php echo esc_url(admin_url('edit.php?post_type=contact_enquiry&page=ctf-manage-task-fields')); ?>" class="button">
            Open Full Management Page
        </a>
    </p>
    
    <!-- Add New Field -->
    <div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
        <h4 style="margin-top: 0; margin-bottom: 10px;">Add New Field</h4>
        <div style="display: grid; gap: 8px;">
            <div>
                <label style="display: block; margin-bottom: 4px; font-size: 12px; font-weight: 600;">Field Label</label>
                <input type="text" id="ctf_new_field_label" placeholder="Enter field label" style="width: 100%;" />
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                <div>
                    <label style="display: block; margin-bottom: 4px; font-size: 12px; font-weight: 600;">Field Type</label>
                    <select id="ctf_new_field_type" style="width: 100%;">
                        <option value="text">Text</option>
                        <option value="email">Email</option>
                        <option value="textarea">Textarea</option>
                        <option value="number">Number</option>
                        <option value="url">URL</option>
                        <option value="tel">Phone</option>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 4px; font-size: 12px; font-weight: 600;">Meta Key (optional)</label>
                    <input type="text" id="ctf_new_field_meta_key" placeholder="Auto-generated" style="width: 100%;" />
                </div>
            </div>
            <div>
                <button type="button" class="button button-primary" id="ctf-add-field-btn">Add Field</button>
            </div>
        </div>
    </div>
    
    <!-- Fields List -->
    <div style="margin-bottom: 15px;">
        <h4 style="margin-bottom: 10px;">Custom Fields</h4>
        <div id="ctf-task-fields-list-container">
            <?php if (empty($task_fields)) : ?>
                <p class="description">No custom fields defined yet. Add your first field above.</p>
            <?php else : ?>
                <?php foreach ($task_fields as $field) : 
                    $usage_count = ctf_get_task_field_usage_count($field['meta_key']);
                    $can_delete = $usage_count === 0;
                ?>
                    <div class="ctf-field-item" data-meta-key="<?php echo esc_attr($field['meta_key']); ?>" 
                         style="display: grid; grid-template-columns: 2fr 1fr auto auto; gap: 8px; align-items: center; padding: 8px; background: #fff; margin-bottom: 6px; border: 1px solid #ddd; border-radius: 3px;">
                        <input type="text" class="ctf-edit-field-label" value="<?php echo esc_attr($field['label']); ?>" 
                               data-original="<?php echo esc_attr($field['label']); ?>" 
                               placeholder="Field Label" style="min-width: 120px;" />
                        <select class="ctf-edit-field-type" data-original="<?php echo esc_attr($field['field_type']); ?>">
                            <option value="text" <?php selected($field['field_type'], 'text'); ?>>Text</option>
                            <option value="email" <?php selected($field['field_type'], 'email'); ?>>Email</option>
                            <option value="textarea" <?php selected($field['field_type'], 'textarea'); ?>>Textarea</option>
                            <option value="number" <?php selected($field['field_type'], 'number'); ?>>Number</option>
                            <option value="url" <?php selected($field['field_type'], 'url'); ?>>URL</option>
                            <option value="tel" <?php selected($field['field_type'], 'tel'); ?>>Phone</option>
                        </select>
                        <span style="font-size: 11px; color: #666; font-family: monospace;"><?php echo esc_html($field['meta_key']); ?></span>
                        <?php if ($usage_count > 0) : ?>
                            <span style="font-size: 11px; color: #dc2626;" title="Used in enquiries">(<?php echo esc_html($usage_count); ?>)</span>
                        <?php else : ?>
                            <span style="font-size: 11px; color: #10b981;">Available</span>
                        <?php endif; ?>
                        <div style="display: flex; gap: 4px;">
                            <button type="button" class="button button-small ctf-update-field-btn" 
                                    data-meta-key="<?php echo esc_attr($field['meta_key']); ?>"
                                    style="display: none;">Update</button>
                            <button type="button" class="button button-small button-link-delete ctf-delete-field-btn" 
                                    data-meta-key="<?php echo esc_attr($field['meta_key']); ?>"
                                    data-usage="<?php echo esc_attr($usage_count); ?>"
                                    <?php echo $can_delete ? '' : 'disabled title="Cannot delete: Has data in ' . $usage_count . ' enquiry(ies)"'; ?>>Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Assign to meta box (separate, positioned in right sidebar)
function ctf_assign_box_html($post) {
    $assigned = get_post_meta($post->ID, 'assigned_to', true);
    $users = get_users(['role__in' => ['administrator', 'editor', 'task_manager']]);
    ?>
    <input type="hidden" id="ctf_assign_box_nonce" name="ctf_task_box_nonce" value="<?php echo wp_create_nonce('ctf_task_box_nonce_action'); ?>" />
    <p><strong>Assign to:</strong></p>
    <?php $can_assign = current_user_can('assign_contact_tasks'); ?>
    <select name="ctf_assigned_to" id="ctf_assigned_to" style="width: 100%;" <?php echo $can_assign ? '' : 'disabled'; ?>>
        <option value="">-- Select User --</option>
        <?php foreach ($users as $user) : ?>
            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($assigned, $user->ID); ?>>
                <?php echo esc_html($user->display_name); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if (!$can_assign && $assigned) : ?>
        <input type="hidden" name="ctf_assigned_to" value="<?php echo esc_attr($assigned); ?>" />
        <?php if ($assigned) : 
            $assigned_user = get_userdata($assigned);
        ?>
            <p class="description" style="margin-top: 8px;">
                Currently assigned to: <strong><?php echo esc_html($assigned_user ? $assigned_user->display_name : 'User #' . $assigned); ?></strong>
            </p>
        <?php endif; ?>
    <?php endif; ?>
    <?php
}

// Status meta box (separate, positioned in right sidebar)
function ctf_status_box_html($post) {
    $status = get_post_meta($post->ID, 'status', true);
    $all_statuses = ctf_get_statuses();
    ?>
    <input type="hidden" id="ctf_status_box_nonce" name="ctf_task_box_nonce" value="<?php echo wp_create_nonce('ctf_task_box_nonce_action'); ?>" />
    <p><strong>Enquiry Status:</strong></p>
    <div style="display: flex; gap: 8px; align-items: center; margin-bottom: 8px;">
        <select name="ctf_status" id="ctf_status_select" style="width: 100%;">
            <?php 
            foreach ($all_statuses as $status_option) : 
                $selected = selected($status, $status_option['name'], false);
            ?>
                <option value="<?php echo esc_attr($status_option['name']); ?>" <?php echo $selected; ?>>
                    <?php echo esc_html($status_option['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="ctf-status-legend" style="margin-bottom: 10px; word-break: break-word;">
        <?php foreach ($all_statuses as $status_option) : 
            $slug = ctf_get_status_slug($status_option['name']);
        ?>
            <span class="ctf-badge ctf-status-<?php echo esc_attr($slug); ?>" 
                  style="background-color: <?php echo esc_attr($status_option['color']); ?>; margin-right: 4px; margin-bottom: 4px; display: inline-block;">
                <?php echo esc_html($status_option['name']); ?>
            </span>
        <?php endforeach; ?>
    </div>
    
    <?php if (current_user_can('manage_options')) : ?>
        <p style="margin-bottom: 8px;">
            <button type="button" class="button button-small" id="ctf-manage-statuses-btn" style="width: 100%;">
                Manage Statuses
            </button>
        </p>
    <!-- Status Management Panel (hidden by default) -->
    <div id="ctf-status-management-panel" style="display: none; margin-top: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
        <h4 style="margin-top: 0;">Manage Statuses</h4>
        
        <!-- Add New Status -->
        <div style="margin-bottom: 15px;">
            <h5 style="margin-bottom: 8px;">Add New Status</h5>
            <div style="display: flex; gap: 8px; align-items: flex-end;">
                <div style="flex: 1;">
                    <label style="display: block; margin-bottom: 4px; font-size: 12px;">Status Name</label>
                    <input type="text" id="ctf_new_status_name" placeholder="Enter status name" style="width: 100%;" />
                </div>
                <div>
                    <label style="display: block; margin-bottom: 4px; font-size: 12px;">Color</label>
                    <input type="color" id="ctf_new_status_color" value="#6b7280" />
                </div>
                <div>
                    <button type="button" class="button button-primary" id="ctf-add-status-btn">Add</button>
                </div>
            </div>
        </div>
        
        <!-- Status List -->
        <div style="margin-bottom: 15px;">
            <h5 style="margin-bottom: 8px;">Current Statuses</h5>
            <div id="ctf-status-list-container">
                <?php foreach ($all_statuses as $status_option) : 
                    $slug = ctf_get_status_slug($status_option['name']);
                    $usage_count = ctf_get_status_usage_count($status_option['name']);
                ?>
                    <div class="ctf-status-item" data-status-name="<?php echo esc_attr($status_option['name']); ?>" 
                         style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 8px; background: #fff; margin-bottom: 6px; border: 1px solid #ddd; border-radius: 3px;">
                        <input type="text" class="ctf-edit-status-name" value="<?php echo esc_attr($status_option['name']); ?>" 
                               data-original="<?php echo esc_attr($status_option['name']); ?>" 
                               style="flex: 1; min-width: 120px;" />
                        <input type="color" class="ctf-edit-status-color" value="<?php echo esc_attr($status_option['color']); ?>" 
                               data-original="<?php echo esc_attr($status_option['color']); ?>" />
                        <span class="ctf-badge ctf-status-<?php echo esc_attr($slug); ?>" 
                              style="background-color: <?php echo esc_attr($status_option['color']); ?>; min-width: 80px; text-align: center;">
                            Preview
                        </span>
                        <?php if ($usage_count > 0) : ?>
                            <span style="font-size: 11px; color: #666;">(<?php echo esc_html($usage_count); ?>)</span>
                        <?php endif; ?>
                        <button type="button" class="button button-small ctf-update-status-btn" 
                                data-status-name="<?php echo esc_attr($status_option['name']); ?>"
                                style="display: none;">Update</button>
                        <button type="button" class="button button-small ctf-delete-status-btn" 
                                data-status-name="<?php echo esc_attr($status_option['name']); ?>"
                                data-usage="<?php echo esc_attr($usage_count); ?>">Delete</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div style="text-align: right; margin-top: 10px;">
            <button type="button" class="button" id="ctf-close-status-panel">Close</button>
            <a href="<?php echo esc_url(admin_url('edit.php?post_type=contact_enquiry&page=ctf-manage-statuses')); ?>" 
               class="button button-secondary" target="_blank" style="margin-left: 5px;">Full Page</a>
        </div>
    </div>
    <?php endif; ?>
    <?php
}

function ctf_change_log_box_html($post) {
    $log = get_post_meta($post->ID, 'ctf_change_log', true);
    if (!is_array($log) || empty($log)) {
        echo '<p>No changes recorded yet.</p>';
        return;
    }
    echo '<div class="ctf-change-log">';
    echo '<table class="widefat fixed striped">';
    echo '<thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr></thead><tbody>';
    foreach (array_reverse($log) as $entry) {
        $time = isset($entry['time']) ? esc_html($entry['time']) : '';
        $user_id = isset($entry['user_id']) ? intval($entry['user_id']) : 0;
        $user_name = $user_id ? (get_userdata($user_id)->display_name ?? ('User #' . $user_id)) : 'System';
        $action = isset($entry['action']) ? esc_html($entry['action']) : '';
        $details = isset($entry['details']) ? esc_html($entry['details']) : '';
        echo '<tr>';
        echo '<td>' . $time . '</td>';
        echo '<td>' . esc_html($user_name) . '</td>';
        echo '<td>' . $action . '</td>';
        echo '<td>' . $details . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';

    // Show submission context
    $ip = get_post_meta($post->ID, 'submit_ip', true);
    $ua = get_post_meta($post->ID, 'submit_user_agent', true);
    $ref = get_post_meta($post->ID, 'submit_referrer', true);
    $phone = get_post_meta($post->ID, 'phone', true);
    $company_name = get_post_meta($post->ID, 'company_name', true);
    $person_designation = get_post_meta($post->ID, 'person_designation', true);
    $nature_of_trustee = get_post_meta($post->ID, 'nature_of_trustee', true);
    echo '<h4>Submission Context</h4>';
    echo '<p><strong>IP:</strong> ' . esc_html($ip) . '</p>';
    echo '<p><strong>User Agent:</strong> ' . esc_html($ua) . '</p>';
    echo '<p><strong>Phone:</strong> ' . esc_html($phone) . '</p>';
    echo '<p><strong>Company Name:</strong> ' . esc_html($company_name) . '</p>';
    echo '<p><strong>Person Designation:</strong> ' . esc_html($person_designation) . '</p>';
    echo '<p><strong>Nature of Trustee:</strong> ' . esc_html($nature_of_trustee) . '</p>';
    if ($ref) {
        echo '<p><strong>Referrer:</strong> <a href="' . esc_url($ref) . '" target="_blank" rel="noopener noreferrer">' . esc_html($ref) . '</a></p>';
    }
    echo '</div>';
}

function ctf_internal_comments_box_html($post) {
    // Admin-only visibility (wp-admin context), comments never shown on front-end
    $comments = get_post_meta($post->ID, 'ctf_internal_comments', true);
    if (!is_array($comments)) { $comments = []; }
    // Nonce is already included in other meta boxes, no need to duplicate
    echo '<div class="ctf-internal-comments">';
    if (empty($comments)) {
        echo '<p>No internal comments yet.</p>';
    } else {
        echo '<ul class="ctf-comments-list" style="margin:0;padding-left:18px;">';
        foreach ($comments as $c) {
            $time = isset($c['time']) ? esc_html($c['time']) : '';
            $uid  = isset($c['user_id']) ? intval($c['user_id']) : 0;
            $user = $uid ? (get_userdata($uid)->display_name ?? ('User #'.$uid)) : 'System';
            $text = isset($c['text']) ? esc_html($c['text']) : '';
            echo '<li style="margin-bottom:8px;"><strong>' . esc_html($user) . '</strong> <span style="color:#6b7280;">' . $time . '</span><br/>' . nl2br($text) . '</li>';
        }
        echo '</ul>';
    }
    echo '<p><textarea name="ctf_new_comment" rows="4" style="width:100%;" placeholder="Add an internal comment (only visible to admins/team)"></textarea></p>';
    echo '</div>';
}

add_action('save_post', function($post_id){
    // Only run for our post type
    if (get_post_type($post_id) !== 'contact_enquiry') {
        return;
    }
    // Autosave/bulk edits should not trigger
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    // Verify nonce
    if (!isset($_POST['ctf_task_box_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ctf_task_box_nonce'])), 'ctf_task_box_nonce_action')) {
        return;
    }
    // Capability check
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save dynamic task fields
    $task_fields = ctf_get_task_fields();
    foreach ($task_fields as $field) {
        $meta_key = $field['meta_key'];
        $input_name = 'ctf_task_' . $meta_key;
        
        if (isset($_POST[$input_name])) {
            $old_value = get_post_meta($post_id, $meta_key, true);
            $field_type = $field['field_type'] ?? 'text';
            
            // Sanitize based on field type
            switch ($field_type) {
                case 'email':
                    $new_value = sanitize_email(wp_unslash($_POST[$input_name]));
                    break;
                case 'number':
                    $new_value = floatval($_POST[$input_name]);
                    break;
                case 'url':
                    $new_value = esc_url_raw(wp_unslash($_POST[$input_name]));
                    break;
                case 'textarea':
                    $new_value = sanitize_textarea_field(wp_unslash($_POST[$input_name]));
                    break;
                default: // text, tel
                    $new_value = sanitize_text_field(wp_unslash($_POST[$input_name]));
                    break;
            }
            
            if ($new_value !== $old_value) {
                update_post_meta($post_id, $meta_key, $new_value);
                
                // Log change
                $log = get_post_meta($post_id, 'ctf_change_log', true);
                if (!is_array($log)) { $log = []; }
                $log[] = [
                    'time' => current_time('mysql'),
                    'user_id' => get_current_user_id(),
                    'action' => 'field_updated',
                    'details' => sprintf('%s changed from %s to %s', $field['label'], $old_value ?: 'N/A', $new_value ?: 'N/A'),
                ];
                update_post_meta($post_id, 'ctf_change_log', $log);
            }
        }
    }

    // Backward compatibility: Save old field names if they exist
    if (isset($_POST['ctf_company_name'])) {
        $old_value = get_post_meta($post_id, 'company_name', true);
        $new_value = sanitize_text_field(wp_unslash($_POST['ctf_company_name']));
        if ($new_value !== $old_value) {
            update_post_meta($post_id, 'company_name', $new_value);
        }
    }
    if (isset($_POST['ctf_person_designation'])) {
        $old_value = get_post_meta($post_id, 'person_designation', true);
        $new_value = sanitize_text_field(wp_unslash($_POST['ctf_person_designation']));
        if ($new_value !== $old_value) {
            update_post_meta($post_id, 'person_designation', $new_value);
        }
    }
    if (isset($_POST['ctf_email'])) {
        $old_value = get_post_meta($post_id, 'email', true);
        $new_value = sanitize_email(wp_unslash($_POST['ctf_email']));
        if ($new_value !== $old_value) {
            update_post_meta($post_id, 'email', $new_value);
        }
    }
    if (isset($_POST['ctf_phone'])) {
        $old_value = get_post_meta($post_id, 'phone', true);
        $new_value = sanitize_text_field(wp_unslash($_POST['ctf_phone']));
        if ($new_value !== $old_value) {
            update_post_meta($post_id, 'phone', $new_value);
        }
    }
    if (isset($_POST['ctf_nature_of_trustee'])) {
        $old_value = get_post_meta($post_id, 'nature_of_trustee', true);
        $new_value = sanitize_text_field(wp_unslash($_POST['ctf_nature_of_trustee']));
        if ($new_value !== $old_value) {
            update_post_meta($post_id, 'nature_of_trustee', $new_value);
        }
    }

    if (isset($_POST['ctf_assigned_to'])) {
        // Only admins can change assignment
        if (!current_user_can('assign_contact_tasks')) {
            // If not allowed, do not alter the value
        } else {
        $old_user = get_post_meta($post_id, 'assigned_to', true);
        $new_user = sanitize_text_field(wp_unslash($_POST['ctf_assigned_to']));
        update_post_meta($post_id, 'assigned_to', $new_user);

        // Notify assigned user (if new)
        if ($new_user && $new_user != $old_user) {
            $user = get_userdata($new_user);
            if ($user && !empty($user->user_email)) {
                wp_mail($user->user_email, 'New Task Assigned', 'You have been assigned a new enquiry. Check the dashboard.');
            }
        }

            // Log change
            if ($new_user !== $old_user) {
                $log = get_post_meta($post_id, 'ctf_change_log', true);
                if (!is_array($log)) { $log = []; }
                $current_user_id = get_current_user_id();
                $old_name = $old_user ? (get_userdata($old_user)->display_name ?? ('User #' . $old_user)) : 'Unassigned';
                $new_name = $new_user ? (get_userdata($new_user)->display_name ?? ('User #' . $new_user)) : 'Unassigned';
                $log[] = [
                    'time' => current_time('mysql'),
                    'user_id' => $current_user_id,
                    'action' => 'assignment_changed',
                    'details' => sprintf('Assigned changed from %s to %s', $old_name, $new_name),
                ];
                update_post_meta($post_id, 'ctf_change_log', $log);
            }
        }
    }
    // Email/Phone updates with validation
    if (isset($_POST['ctf_email'])) {
        $old_email = get_post_meta($post_id, 'email', true);
        $new_email_raw = wp_unslash($_POST['ctf_email']);
        $new_email = sanitize_email($new_email_raw);
        if ($new_email && is_email($new_email)) {
            if ($new_email !== $old_email) {
                update_post_meta($post_id, 'email', $new_email);
                $log = get_post_meta($post_id, 'ctf_change_log', true);
                if (!is_array($log)) { $log = []; }
                $log[] = [
                    'time' => current_time('mysql'),
                    'user_id' => get_current_user_id(),
                    'action' => 'email_changed',
                    'details' => sprintf('Email changed from %s to %s', $old_email ?: 'N/A', $new_email),
                ];
                update_post_meta($post_id, 'ctf_change_log', $log);
            }
        }
    }
    if (isset($_POST['ctf_phone'])) {
        $old_phone = get_post_meta($post_id, 'phone', true);
        $new_phone_raw = sanitize_text_field(wp_unslash($_POST['ctf_phone']));
        $digits_only = preg_replace('/\D+/', '', $new_phone_raw);
        if ($new_phone_raw === '' || (strlen($digits_only) >= 7 && strlen($digits_only) <= 15)) {
            if ($new_phone_raw !== $old_phone) {
                update_post_meta($post_id, 'phone', $new_phone_raw);
                $log = get_post_meta($post_id, 'ctf_change_log', true);
                if (!is_array($log)) { $log = []; }
                $log[] = [
                    'time' => current_time('mysql'),
                    'user_id' => get_current_user_id(),
                    'action' => 'phone_changed',
                    'details' => sprintf('Phone changed from %s to %s', $old_phone ?: 'N/A', $new_phone_raw ?: 'N/A'),
                ];
                update_post_meta($post_id, 'ctf_change_log', $log);
            }
        }
    }
    if (isset($_POST['ctf_company_name'])) {
        $old_company_name = get_post_meta($post_id, 'company_name', true);
        $new_company_name = sanitize_text_field(wp_unslash($_POST['ctf_company_name']));
        if ($new_company_name !== $old_company_name) {
            update_post_meta($post_id, 'company_name', $new_company_name);
            $log = get_post_meta($post_id, 'ctf_change_log', true);
            if (!is_array($log)) { $log = []; }
            $log[] = [
                'time' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'action' => 'company_name_changed',
                'details' => sprintf('Company name changed from %s to %s', $old_company_name ?: 'N/A', $new_company_name ?: 'N/A'),
            ];
            update_post_meta($post_id, 'ctf_change_log', $log);
        }
    }
    if (isset($_POST['ctf_person_designation'])) {
        $old_person_designation = get_post_meta($post_id, 'person_designation', true);
        $new_person_designation = sanitize_text_field(wp_unslash($_POST['ctf_person_designation']));
        if ($new_person_designation !== $old_person_designation) {
            update_post_meta($post_id, 'person_designation', $new_person_designation);
            $log = get_post_meta($post_id, 'ctf_change_log', true);
            if (!is_array($log)) { $log = []; }
            $log[] = [
                'time' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'action' => 'person_designation_changed',
                'details' => sprintf('Person designation changed from %s to %s', $old_person_designation ?: 'N/A', $new_person_designation ?: 'N/A'),
            ];
            update_post_meta($post_id, 'ctf_change_log', $log);
        }
    }
    if (isset($_POST['ctf_nature_of_trustee'])) {
        $old_nature_of_trustee = get_post_meta($post_id, 'nature_of_trustee', true);
        $new_nature_of_trustee = sanitize_text_field(wp_unslash($_POST['ctf_nature_of_trustee']));
        if ($new_nature_of_trustee !== $old_nature_of_trustee) {
            update_post_meta($post_id, 'nature_of_trustee', $new_nature_of_trustee);
            $log = get_post_meta($post_id, 'ctf_change_log', true);
            if (!is_array($log)) { $log = []; }
            $log[] = [
                'time' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'action' => 'nature_of_trustee_changed',
                'details' => sprintf('Nature of trustee changed from %s to %s', $old_nature_of_trustee ?: 'N/A', $new_nature_of_trustee ?: 'N/A'),
            ];
            update_post_meta($post_id, 'ctf_change_log', $log);
        }
    }
    if (isset($_POST['ctf_status'])) {
        $old_status = get_post_meta($post_id, 'status', true);
        $new_status = sanitize_text_field(wp_unslash($_POST['ctf_status']));
        if ($new_status !== $old_status) {
            update_post_meta($post_id, 'status', $new_status);
            // Log status change
            $log = get_post_meta($post_id, 'ctf_change_log', true);
            if (!is_array($log)) { $log = []; }
            $default_statuses = ctf_get_status_names();
            $default_status = !empty($default_statuses) ? $default_statuses[0] : 'New';
            $log[] = [
                'time' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'action' => 'status_changed',
                'details' => sprintf('Status changed from %s to %s', $old_status ?: $default_status, $new_status),
            ];
            update_post_meta($post_id, 'ctf_change_log', $log);
        }
    }

    // Handle new internal comment
    if (isset($_POST['ctf_new_comment'])) {
        $raw = wp_unslash($_POST['ctf_new_comment']);
        $comment_text = trim(wp_strip_all_tags($raw));
        if ($comment_text !== '') {
            $comments = get_post_meta($post_id, 'ctf_internal_comments', true);
            if (!is_array($comments)) { $comments = []; }
            $entry = [
                'time' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'text' => $comment_text,
            ];
            $comments[] = $entry;
            update_post_meta($post_id, 'ctf_internal_comments', $comments);

            // Log comment activity
            $log = get_post_meta($post_id, 'ctf_change_log', true);
            if (!is_array($log)) { $log = []; }
            $log[] = [
                'time' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'action' => 'comment_added',
                'details' => mb_substr($comment_text, 0, 140) . (mb_strlen($comment_text) > 140 ? '…' : ''),
            ];
            update_post_meta($post_id, 'ctf_change_log', $log);

            // Auto-mark In Progress if currently New
            $current_status = get_post_meta($post_id, 'status', true);
            $default_statuses = ctf_get_status_names();
            $first_status = !empty($default_statuses) ? $default_statuses[0] : 'New';
            $in_progress_status = count($default_statuses) > 1 ? $default_statuses[1] : 'In Progress';
            
            if ($current_status === $first_status) {
                update_post_meta($post_id, 'status', $in_progress_status);
                $log[] = [
                    'time' => current_time('mysql'),
                    'user_id' => get_current_user_id(),
                    'action' => 'status_changed',
                    'details' => sprintf('Status changed from %s to %s (comment added)', $first_status, $in_progress_status),
                ];
                update_post_meta($post_id, 'ctf_change_log', $log);
            }

            // Email alerts to assignee and admin
            if (!function_exists('ctf_send_comment_alert')) {
                // no-op; function declared in email-functions
            } else {
                ctf_send_comment_alert($post_id, $comment_text, get_current_user_id());
            }
        }
    }
});

// Block Task Manager from reading/editing enquiries not assigned to them
add_filter('map_meta_cap', function($caps, $cap, $user_id, $args){
    $restricted_caps = ['edit_post', 'read_post', 'delete_post'];
    if (!in_array($cap, $restricted_caps, true)) {
        return $caps;
    }
    $post_id = isset($args[0]) ? intval($args[0]) : 0;
    if (!$post_id) return $caps;
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'contact_enquiry') return $caps;

    $user = get_userdata($user_id);
    if (!$user || !in_array('task_manager', (array) $user->roles, true)) return $caps;

    $assigned_to = get_post_meta($post_id, 'assigned_to', true);
    if ((string) $assigned_to !== (string) $user_id) {
        return ['do_not_allow'];
    }

    // If assigned, map to the minimal CPT-specific caps the role has
    if ($cap === 'edit_post') {
        // Require basic edit cap; include published edit cap to satisfy checks on published posts
        return ['edit_contact_enquiry','edit_published_contact_enquiries'];
    }
    if ($cap === 'read_post') {
        return ['read_contact_enquiry'];
    }
    if ($cap === 'delete_post') {
        // Task managers cannot delete
        return ['do_not_allow'];
    }
    return $caps;
}, 10, 4);

// Status management panel script (shared)
add_action('admin_footer', function() {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'contact_enquiry' || !current_user_can('manage_options')) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        var $panel = $('#ctf-status-management-panel');
        var $btn = $('#ctf-manage-statuses-btn');
        
        if ($panel.length === 0 || $btn.length === 0) {
            return;
        }
        
        // Toggle panel
        $btn.on('click', function() {
            $panel.slideToggle();
        });
        
        $('#ctf-close-status-panel').on('click', function() {
            $panel.slideUp();
        });
        
        // Add new status
        $('#ctf-add-status-btn').on('click', function() {
            var name = $('#ctf_new_status_name').val().trim();
            var color = $('#ctf_new_status_color').val();
            
            if (!name) {
                alert('Please enter a status name.');
                return;
            }
            
            $.post(ajaxurl, {
                action: 'ctf_add_status',
                name: name,
                color: color,
                nonce: '<?php echo wp_create_nonce('ctf_status_ajax'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error adding status.');
                }
            });
        });
        
        // Track changes for update button
        $('.ctf-edit-status-name, .ctf-edit-status-color').on('input change', function() {
            var $item = $(this).closest('.ctf-status-item');
            var originalName = $item.find('.ctf-edit-status-name').data('original');
            var originalColor = $item.find('.ctf-edit-status-color').data('original');
            var currentName = $item.find('.ctf-edit-status-name').val();
            var currentColor = $item.find('.ctf-edit-status-color').val();
            
            if (originalName !== currentName || originalColor !== currentColor) {
                $item.find('.ctf-update-status-btn').show();
            } else {
                $item.find('.ctf-update-status-btn').hide();
            }
            
            // Update preview
            var slug = currentName.toLowerCase().replace(/\s+/g, '-');
            var $badge = $item.find('.ctf-badge');
            $badge.removeClass().addClass('ctf-badge ctf-status-' + slug);
            $badge.css('background-color', currentColor);
        });
        
        // Update status
        $('.ctf-update-status-btn').on('click', function() {
            var $item = $(this).closest('.ctf-status-item');
            var oldName = $item.find('.ctf-edit-status-name').data('original');
            var newName = $item.find('.ctf-edit-status-name').val().trim();
            var color = $item.find('.ctf-edit-status-color').val();
            
            if (!newName) {
                alert('Status name cannot be empty.');
                return;
            }
            
            $.post(ajaxurl, {
                action: 'ctf_update_status',
                old_name: oldName,
                new_name: newName,
                color: color,
                nonce: '<?php echo wp_create_nonce('ctf_status_ajax'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error updating status.');
                }
            });
        });
        
        // Delete status
        $('.ctf-delete-status-btn').on('click', function() {
            var statusName = $(this).data('status-name');
            var usage = parseInt($(this).data('usage')) || 0;
            
            if (usage > 0) {
                var migrateTo = prompt('This status is used by ' + usage + ' enquiry(ies). Enter the status name to migrate them to:');
                if (!migrateTo) return;
            } else {
                if (!confirm('Are you sure you want to delete "' + statusName + '"?')) return;
                var migrateTo = '';
            }
            
            $.post(ajaxurl, {
                action: 'ctf_delete_status',
                name: statusName,
                migrate_to: migrateTo || '',
                nonce: '<?php echo wp_create_nonce('ctf_status_ajax'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error deleting status.');
                }
            });
        });
        
        // Task Fields Management
        // Add new field
        $('#ctf-add-field-btn').on('click', function() {
            var label = $('#ctf_new_field_label').val().trim();
            var fieldType = $('#ctf_new_field_type').val();
            var metaKey = $('#ctf_new_field_meta_key').val().trim();
            
            if (!label) {
                alert('Please enter a field label.');
                return;
            }
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('Adding...');
            
            $.post(ajaxurl, {
                action: 'ctf_add_task_field',
                label: label,
                field_type: fieldType,
                meta_key: metaKey || '',
                nonce: '<?php echo wp_create_nonce('ctf_task_details_ajax'); ?>'
            }, function(response) {
                $btn.prop('disabled', false).text('Add Field');
                
                if (response.success) {
                    // Clear the form
                    $('#ctf_new_field_label').val('');
                    $('#ctf_new_field_meta_key').val('');
                    // Reload page to show new field
                    location.reload();
                } else {
                    alert(response.data.message || 'Error adding field.');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Add Field');
                alert('Network error. Please try again.');
            });
        });
        
        // Track changes for update button (use event delegation for dynamically loaded content)
        $(document).on('input change', '.ctf-edit-field-label, .ctf-edit-field-type', function() {
            var $item = $(this).closest('.ctf-field-item');
            var originalLabel = $item.find('.ctf-edit-field-label').data('original');
            var originalType = $item.find('.ctf-edit-field-type').data('original');
            var currentLabel = $item.find('.ctf-edit-field-label').val();
            var currentType = $item.find('.ctf-edit-field-type').val();
            
            if (originalLabel !== currentLabel || originalType !== currentType) {
                $item.find('.ctf-update-field-btn').show();
            } else {
                $item.find('.ctf-update-field-btn').hide();
            }
        });
        
        // Update field (use event delegation)
        $(document).on('click', '.ctf-update-field-btn', function() {
            var $item = $(this).closest('.ctf-field-item');
            var oldMetaKey = $item.data('meta-key');
            var newLabel = $item.find('.ctf-edit-field-label').val().trim();
            var fieldType = $item.find('.ctf-edit-field-type').val();
            
            if (!newLabel) {
                alert('Field label cannot be empty.');
                return;
            }
            
            $.post(ajaxurl, {
                action: 'ctf_update_task_field',
                old_meta_key: oldMetaKey,
                label: newLabel,
                field_type: fieldType,
                meta_key: oldMetaKey,
                nonce: '<?php echo wp_create_nonce('ctf_task_details_ajax'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error updating field.');
                }
            });
        });
        
        // Delete field (use event delegation)
        $(document).on('click', '.ctf-delete-field-btn', function() {
            var $btn = $(this);
            if ($btn.prop('disabled')) {
                return;
            }
            
            var metaKey = $btn.data('meta-key');
            var usage = parseInt($btn.data('usage')) || 0;
            
            if (usage > 0) {
                alert('This field is used by ' + usage + ' enquiry(ies) and cannot be deleted. Please clear or migrate the data first.');
                return;
            }
            
            if (!confirm('Are you sure you want to delete this field? This will remove it from the Task Details section but will not delete existing data.')) {
                return;
            }
            
            $.post(ajaxurl, {
                action: 'ctf_delete_task_field',
                meta_key: metaKey,
                nonce: '<?php echo wp_create_nonce('ctf_task_details_ajax'); ?>'
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || 'Error deleting field.');
                }
            });
        });
    });
    </script>
    <?php
});
