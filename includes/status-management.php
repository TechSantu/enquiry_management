<?php
/**
 * Status Management Functions
 */

// AJAX handlers for status management from edit page
add_action('wp_ajax_ctf_add_status', function() {
    check_ajax_referer('ctf_status_ajax', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $color = isset($_POST['color']) ? sanitize_text_field(wp_unslash($_POST['color'])) : '#6b7280';
    
    $result = ctf_add_status($name, $color);
    
    if ($result['success']) {
        wp_send_json_success(['message' => $result['message']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
});

add_action('wp_ajax_ctf_update_status', function() {
    check_ajax_referer('ctf_status_ajax', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $old_name = isset($_POST['old_name']) ? sanitize_text_field(wp_unslash($_POST['old_name'])) : '';
    $new_name = isset($_POST['new_name']) ? sanitize_text_field(wp_unslash($_POST['new_name'])) : '';
    $color = isset($_POST['color']) ? sanitize_text_field(wp_unslash($_POST['color'])) : null;
    
    $result = ctf_update_status($old_name, $new_name, $color);
    
    if ($result['success']) {
        wp_send_json_success(['message' => $result['message']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
});

add_action('wp_ajax_ctf_delete_status', function() {
    check_ajax_referer('ctf_status_ajax', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $migrate_to = isset($_POST['migrate_to']) ? sanitize_text_field(wp_unslash($_POST['migrate_to'])) : '';
    
    $result = ctf_delete_status($name, $migrate_to ?: null);
    
    if ($result['success']) {
        wp_send_json_success(['message' => $result['message']]);
    } else {
        wp_send_json_error(['message' => $result['message']]);
    }
});

// Get all statuses
function ctf_get_statuses() {
    $statuses = get_option('ctf_statuses', []);
    // If no statuses exist, initialize with defaults
    if (empty($statuses)) {
        $default_statuses = [
            ['name' => 'New', 'color' => '#6366f1', 'order' => 1],
            ['name' => 'In Progress', 'color' => '#f59e0b', 'order' => 2],
            ['name' => 'Resolved', 'color' => '#10b981', 'order' => 3],
            ['name' => 'Closed', 'color' => '#6b7280', 'order' => 4],
        ];
        update_option('ctf_statuses', $default_statuses);
        return $default_statuses;
    }
    // Sort by order
    usort($statuses, function($a, $b) {
        return ($a['order'] ?? 999) - ($b['order'] ?? 999);
    });
    return $statuses;
}

// Get status names only (for backward compatibility)
function ctf_get_status_names() {
    $statuses = ctf_get_statuses();
    return array_column($statuses, 'name');
}

// Get status by name
function ctf_get_status($name) {
    $statuses = ctf_get_statuses();
    foreach ($statuses as $status) {
        if ($status['name'] === $name) {
            return $status;
        }
    }
    return null;
}

// Add new status
function ctf_add_status($name, $color = '#6b7280', $order = null) {
    if (empty($name)) {
        return ['success' => false, 'message' => 'Status name is required.'];
    }
    
    $statuses = ctf_get_statuses();
    
    // Check if status already exists
    foreach ($statuses as $status) {
        if ($status['name'] === $name) {
            return ['success' => false, 'message' => 'Status already exists.'];
        }
    }
    
    // Determine order
    if ($order === null) {
        $max_order = 0;
        foreach ($statuses as $status) {
            $max_order = max($max_order, $status['order'] ?? 0);
        }
        $order = $max_order + 1;
    }
    
    $new_status = [
        'name' => sanitize_text_field($name),
        'color' => sanitize_hex_color($color) ?: '#6b7280',
        'order' => intval($order),
    ];
    
    $statuses[] = $new_status;
    update_option('ctf_statuses', $statuses);
    
    return ['success' => true, 'message' => 'Status added successfully.'];
}

// Update status
function ctf_update_status($old_name, $new_name, $color = null, $order = null) {
    if (empty($old_name) || empty($new_name)) {
        return ['success' => false, 'message' => 'Status name is required.'];
    }
    
    $statuses = ctf_get_statuses();
    $found = false;
    
    foreach ($statuses as &$status) {
        if ($status['name'] === $old_name) {
            $found = true;
            $status['name'] = sanitize_text_field($new_name);
            if ($color !== null) {
                $status['color'] = sanitize_hex_color($color) ?: $status['color'];
            }
            if ($order !== null) {
                $status['order'] = intval($order);
            }
            break;
        }
    }
    
    if (!$found) {
        return ['success' => false, 'message' => 'Status not found.'];
    }
    
    // If name changed, update all enquiries with old status
    if ($old_name !== $new_name) {
        $posts = get_posts([
            'post_type' => 'contact_enquiry',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'status',
                    'value' => $old_name,
                    'compare' => '='
                ]
            ]
        ]);
        
        foreach ($posts as $post) {
            update_post_meta($post->ID, 'status', $new_name);
        }
    }
    
    update_option('ctf_statuses', $statuses);
    
    return ['success' => true, 'message' => 'Status updated successfully.'];
}

// Delete status
function ctf_delete_status($name, $migrate_to = null) {
    if (empty($name)) {
        return ['success' => false, 'message' => 'Status name is required.'];
    }
    
    // Check if status exists
    $status = ctf_get_status($name);
    if (!$status) {
        return ['success' => false, 'message' => 'Status not found.'];
    }
    
    // Check if status is in use
    $posts = get_posts([
        'post_type' => 'contact_enquiry',
        'posts_per_page' => 1,
        'meta_query' => [
            [
                'key' => 'status',
                'value' => $name,
                'compare' => '='
            ]
        ]
    ]);
    
    $in_use = !empty($posts);
    
    // If in use and no migration status provided, prevent deletion
    if ($in_use && empty($migrate_to)) {
        return ['success' => false, 'message' => 'Cannot delete status that is in use. Please migrate enquiries first.'];
    }
    
    // Migrate enquiries if needed
    if ($in_use && !empty($migrate_to)) {
        $migrate_status = ctf_get_status($migrate_to);
        if (!$migrate_status) {
            return ['success' => false, 'message' => 'Migration status not found.'];
        }
        
        $all_posts = get_posts([
            'post_type' => 'contact_enquiry',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'status',
                    'value' => $name,
                    'compare' => '='
                ]
            ]
        ]);
        
        foreach ($all_posts as $post) {
            update_post_meta($post->ID, 'status', $migrate_to);
            
            // Log the migration
            $log = get_post_meta($post->ID, 'ctf_change_log', true);
            if (!is_array($log)) { $log = []; }
            $log[] = [
                'time' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'action' => 'status_changed',
                'details' => sprintf('Status migrated from %s to %s (status deleted)', $name, $migrate_to),
            ];
            update_post_meta($post->ID, 'ctf_change_log', $log);
        }
    }
    
    // Remove status from list
    $statuses = ctf_get_statuses();
    $statuses = array_filter($statuses, function($status) use ($name) {
        return $status['name'] !== $name;
    });
    update_option('ctf_statuses', array_values($statuses));
    
    return ['success' => true, 'message' => 'Status deleted successfully.'];
}

// Get status color
function ctf_get_status_color($name) {
    $status = ctf_get_status($name);
    return $status ? ($status['color'] ?? '#6b7280') : '#6b7280';
}

// Get status slug for CSS class
function ctf_get_status_slug($name) {
    return strtolower(str_replace(' ', '-', $name));
}

