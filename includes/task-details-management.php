<?php
/**
 * Task Details Management Functions
 */

// AJAX handlers for task details management
add_action('wp_ajax_ctf_add_task_field', function() {
    check_ajax_referer('ctf_task_details_ajax', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $label = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
    $field_type = isset($_POST['field_type']) ? sanitize_text_field(wp_unslash($_POST['field_type'])) : 'text';
    $meta_key = isset($_POST['meta_key']) ? sanitize_text_field(wp_unslash($_POST['meta_key'])) : '';
    $order = isset($_POST['order']) ? intval($_POST['order']) : null;
    
    $result = ctf_add_task_field($label, $field_type, $meta_key, $order);
    
    if ($result['success']) {
        wp_send_json_success(['message' => $result['message']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
});

add_action('wp_ajax_ctf_update_task_field', function() {
    check_ajax_referer('ctf_task_details_ajax', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $old_meta_key = isset($_POST['old_meta_key']) ? sanitize_text_field(wp_unslash($_POST['old_meta_key'])) : '';
    $label = isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '';
    $field_type = isset($_POST['field_type']) ? sanitize_text_field(wp_unslash($_POST['field_type'])) : 'text';
    $meta_key = isset($_POST['meta_key']) ? sanitize_text_field(wp_unslash($_POST['meta_key'])) : '';
    $order = isset($_POST['order']) ? intval($_POST['order']) : null;
    
    $result = ctf_update_task_field($old_meta_key, $label, $field_type, $meta_key, $order);
    
    if ($result['success']) {
        wp_send_json_success(['message' => $result['message']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
});

add_action('wp_ajax_ctf_delete_task_field', function() {
    check_ajax_referer('ctf_task_details_ajax', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $meta_key = isset($_POST['meta_key']) ? sanitize_text_field(wp_unslash($_POST['meta_key'])) : '';
    
    $result = ctf_delete_task_field($meta_key);
    
    if ($result['success']) {
        wp_send_json_success(['message' => $result['message']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
});

// Get all task detail fields
function ctf_get_task_fields() {
    $fields = get_option('ctf_task_fields', []);
    // If no fields exist, initialize with defaults
    if (empty($fields)) {
        $default_fields = [
            ['label' => 'Company Name', 'field_type' => 'text', 'meta_key' => 'company_name', 'order' => 1],
            ['label' => 'Person Designation', 'field_type' => 'text', 'meta_key' => 'person_designation', 'order' => 2],
            ['label' => 'Contact Email', 'field_type' => 'email', 'meta_key' => 'email', 'order' => 3],
            ['label' => 'Phone Number', 'field_type' => 'text', 'meta_key' => 'phone', 'order' => 4],
            ['label' => 'Nature of Trustee', 'field_type' => 'text', 'meta_key' => 'nature_of_trustee', 'order' => 5],
        ];
        update_option('ctf_task_fields', $default_fields);
        return $default_fields;
    }
    // Sort by order
    usort($fields, function($a, $b) {
        return ($a['order'] ?? 999) - ($b['order'] ?? 999);
    });
    return $fields;
}

// Get field by meta key
function ctf_get_task_field($meta_key) {
    $fields = ctf_get_task_fields();
    foreach ($fields as $field) {
        if ($field['meta_key'] === $meta_key) {
            return $field;
        }
    }
    return null;
}

// Generate meta key from label
function ctf_generate_meta_key($label) {
    $key = strtolower($label);
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    $key = trim($key, '_');
    return $key;
}

// Add new task field
function ctf_add_task_field($label, $field_type = 'text', $meta_key = '', $order = null) {
    if (empty($label)) {
        return ['success' => false, 'message' => 'Field label is required.'];
    }
    
    // Generate meta_key if not provided
    if (empty($meta_key)) {
        $meta_key = ctf_generate_meta_key($label);
    } else {
        $meta_key = sanitize_key($meta_key);
    }
    
    // Get fields directly from option to avoid initialization issues
    $fields = get_option('ctf_task_fields', []);
    
    // If empty, initialize with defaults first
    if (empty($fields)) {
        $fields = [
            ['label' => 'Company Name', 'field_type' => 'text', 'meta_key' => 'company_name', 'order' => 1],
            ['label' => 'Person Designation', 'field_type' => 'text', 'meta_key' => 'person_designation', 'order' => 2],
            ['label' => 'Contact Email', 'field_type' => 'email', 'meta_key' => 'email', 'order' => 3],
            ['label' => 'Phone Number', 'field_type' => 'text', 'meta_key' => 'phone', 'order' => 4],
            ['label' => 'Nature of Trustee', 'field_type' => 'text', 'meta_key' => 'nature_of_trustee', 'order' => 5],
        ];
    }
    
    // Check if meta_key already exists
    foreach ($fields as $field) {
        if (isset($field['meta_key']) && $field['meta_key'] === $meta_key) {
            return ['success' => false, 'message' => 'A field with this key already exists.'];
        }
    }
    
    // Determine order
    if ($order === null) {
        $max_order = 0;
        foreach ($fields as $field) {
            $max_order = max($max_order, isset($field['order']) ? intval($field['order']) : 0);
        }
        $order = $max_order + 1;
    }
    
    $new_field = [
        'label' => sanitize_text_field($label),
        'field_type' => in_array($field_type, ['text', 'email', 'textarea', 'number', 'url', 'tel']) ? $field_type : 'text',
        'meta_key' => $meta_key,
        'order' => intval($order),
    ];
    
    $fields[] = $new_field;
    
    // Save the option and clear cache to ensure fresh data on next load
    $saved = update_option('ctf_task_fields', $fields, false);
    wp_cache_delete('ctf_task_fields', 'options');
    delete_transient('ctf_task_fields');
    
    if (!$saved && get_option('ctf_task_fields') === false) {
        add_option('ctf_task_fields', $fields);
    }
    
    return ['success' => true, 'message' => 'Field added successfully.'];
}

// Update task field
function ctf_update_task_field($old_meta_key, $label, $field_type = 'text', $meta_key = '', $order = null) {
    if (empty($old_meta_key) || empty($label)) {
        return ['success' => false, 'message' => 'Field label and meta key are required.'];
    }
    
    // Generate meta_key if not provided
    if (empty($meta_key)) {
        $meta_key = ctf_generate_meta_key($label);
    } else {
        $meta_key = sanitize_key($meta_key);
    }
    
    $fields = ctf_get_task_fields();
    $found = false;
    
    foreach ($fields as &$field) {
        if ($field['meta_key'] === $old_meta_key) {
            $found = true;
            $field['label'] = sanitize_text_field($label);
            $field['field_type'] = in_array($field_type, ['text', 'email', 'textarea', 'number', 'url', 'tel']) ? $field_type : 'text';
            
            // If meta_key changed, update all posts
            if ($old_meta_key !== $meta_key) {
                // Check if new meta_key already exists
                $key_exists = false;
                foreach ($fields as $f) {
                    if ($f['meta_key'] === $meta_key && $f['meta_key'] !== $old_meta_key) {
                        $key_exists = true;
                        break;
                    }
                }
                
                if ($key_exists) {
                    return ['success' => false, 'message' => 'A field with this key already exists.'];
                }
                
                // Migrate meta values
                $posts = get_posts([
                    'post_type' => 'contact_enquiry',
                    'posts_per_page' => -1,
                    'meta_query' => [
                        [
                            'key' => $old_meta_key,
                            'compare' => 'EXISTS'
                        ]
                    ]
                ]);
                
                foreach ($posts as $post) {
                    $value = get_post_meta($post->ID, $old_meta_key, true);
                    update_post_meta($post->ID, $meta_key, $value);
                    delete_post_meta($post->ID, $old_meta_key);
                }
            }
            
            $field['meta_key'] = $meta_key;
            if ($order !== null) {
                $field['order'] = intval($order);
            }
            break;
        }
    }
    
    if (!$found) {
        return ['success' => false, 'message' => 'Field not found.'];
    }
    
    update_option('ctf_task_fields', $fields);
    wp_cache_delete('ctf_task_fields', 'options');
    
    return ['success' => true, 'message' => 'Field updated successfully.'];
}

// Get field usage count
function ctf_get_task_field_usage_count($meta_key) {
    $posts = get_posts([
        'post_type' => 'contact_enquiry',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => $meta_key,
                'compare' => 'EXISTS'
            ]
        ],
        'fields' => 'ids'
    ]);
    return count($posts);
}

// Check if field is used in form
function ctf_is_field_used_in_form($meta_key) {
    // These are the default fields that are hardcoded in the form
    $form_fields = ['name', 'company_name', 'person_designation', 'email', 'phone', 'nature_of_trustee', 'message'];
    return in_array($meta_key, $form_fields, true);
}

// Delete task field
function ctf_delete_task_field($meta_key) {
    if (empty($meta_key)) {
        return ['success' => false, 'message' => 'Meta key is required.'];
    }
    
    // Check if field exists
    $field = ctf_get_task_field($meta_key);
    if (!$field) {
        return ['success' => false, 'message' => 'Field not found.'];
    }
    
    // Check if field is used in the form (hardcoded fields)
    if (ctf_is_field_used_in_form($meta_key)) {
        return ['success' => false, 'message' => 'Cannot delete field that is used in the contact form. Please remove it from the form first.'];
    }
    
    // Check if field has data in existing enquiries
    $usage_count = ctf_get_task_field_usage_count($meta_key);
    if ($usage_count > 0) {
        return ['success' => false, 'message' => sprintf('Cannot delete field that is used by %d enquiry(ies). Please migrate or clear the data first.', $usage_count)];
    }
    
    // Remove field from list
    $fields = ctf_get_task_fields();
    $fields = array_filter($fields, function($field) use ($meta_key) {
        return $field['meta_key'] !== $meta_key;
    });
    update_option('ctf_task_fields', array_values($fields));
    wp_cache_delete('ctf_task_fields', 'options');
    
    return ['success' => true, 'message' => 'Field deleted successfully.'];
}

