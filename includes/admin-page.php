<?php
// Columns in admin
add_filter('manage_contact_enquiry_posts_columns', function($cols){
    $cols['phone'] = 'Phone';
    $cols['email'] = 'Email';
    $cols['assigned_to'] = 'Assigned To';
    $cols['status'] = 'Status';
    return $cols;
});

add_action('manage_contact_enquiry_posts_custom_column', function($col, $post_id){
    if ($col == 'phone') echo esc_html(get_post_meta($post_id, 'phone', true));
    if ($col == 'email') echo esc_html(get_post_meta($post_id, 'email', true));
    if ($col == 'assigned_to') {
        $uid = get_post_meta($post_id, 'assigned_to', true);
        if ($uid) echo esc_html(get_userdata($uid)->display_name);
    }
    if ($col == 'status') {
        $status = get_post_meta($post_id, 'status', true);
        $default_statuses = ctf_get_status_names();
        $default_status = !empty($default_statuses) ? $default_statuses[0] : 'New';
        $status = $status ?: $default_status;
        $slug = ctf_get_status_slug($status);
        $color = ctf_get_status_color($status);
        echo '<span class="ctf-badge ctf-status-' . esc_attr($slug) . '" style="background-color: ' . esc_attr($color) . ';">' . esc_html($status) . '</span>';
    }
}, 10, 2);

// Apply filters to list table query
add_action('pre_get_posts', function($query){
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'contact_enquiry') return;

    // If Task Manager, limit to their assigned enquiries only
    $current = wp_get_current_user();
    $is_task_manager = $current && is_array($current->roles) && in_array('task_manager', $current->roles, true);

    $status = isset($_GET['ctf_status']) ? sanitize_text_field(wp_unslash($_GET['ctf_status'])) : '';
    $assigned_to = isset($_GET['ctf_assigned_to']) ? sanitize_text_field(wp_unslash($_GET['ctf_assigned_to'])) : '';
    $email_like = isset($_GET['ctf_email']) ? sanitize_text_field(wp_unslash($_GET['ctf_email'])) : '';
    $phone_like = isset($_GET['ctf_phone']) ? sanitize_text_field(wp_unslash($_GET['ctf_phone'])) : '';
    $only_unassigned = !empty($_GET['ctf_unassigned']);
    $date_from = isset($_GET['ctf_date_from']) ? sanitize_text_field(wp_unslash($_GET['ctf_date_from'])) : '';
    $date_to   = isset($_GET['ctf_date_to']) ? sanitize_text_field(wp_unslash($_GET['ctf_date_to'])) : '';

    $meta_query = [];
    if ($status !== '') {
        $meta_query[] = [
            'key' => 'status',
            'value' => $status,
            'compare' => '='
        ];
    }
    if ($assigned_to !== '') {
        $meta_query[] = [
            'key' => 'assigned_to',
            'value' => $assigned_to,
            'compare' => '='
        ];
    }
    if ($is_task_manager) {
        $meta_query[] = [
            'key' => 'assigned_to',
            'value' => (string) get_current_user_id(),
            'compare' => '='
        ];
    }
    if ($only_unassigned) {
        $meta_query[] = [
            'key' => 'assigned_to',
            'compare' => 'NOT EXISTS'
        ];
    }
    if ($email_like !== '') {
        $meta_query[] = [
            'key' => 'email',
            'value' => $email_like,
            'compare' => 'LIKE'
        ];
    }
    if ($phone_like !== '') {
        $meta_query[] = [
            'key' => 'phone',
            'value' => $phone_like,
            'compare' => 'LIKE'
        ];
    }
    if (!empty($meta_query)) {
        $query->set('meta_query', $meta_query);
    }

    if ($date_from || $date_to) {
        $date_query = [ 'inclusive' => true ];
        if ($date_from) { $date_query['after'] = $date_from; }
        if ($date_to)   { $date_query['before'] = $date_to; }
        $query->set('date_query', [ $date_query ]);
    }
});

// Dashboard widget: Recent Enquiries & Activity
add_action('wp_dashboard_setup', function(){
    wp_add_dashboard_widget('ctf_recent_activity', 'Enquiries – Recent Comments & Activity', function(){
        // Latest 5 enquiries
        $recent = get_posts([
            'post_type' => 'contact_enquiry',
            'posts_per_page' => 5,
            'post_status' => ['publish'],
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        echo '<h3>Recent Enquiries</h3>';
        if (!$recent) {
            echo '<p>No enquiries yet.</p>';
        } else {
            echo '<ul class="ctf-recent-enquiries">';
            foreach ($recent as $p) {
                $status = get_post_meta($p->ID, 'status', true);
                $default_statuses = ctf_get_status_names();
                $default_status = !empty($default_statuses) ? $default_statuses[0] : 'New';
                $status = $status ?: $default_status;
                $email  = get_post_meta($p->ID, 'email', true);
                $assigned = get_post_meta($p->ID, 'assigned_to', true);
                $assignee = $assigned ? (get_userdata($assigned)->display_name ?? 'User #'.$assigned) : 'Unassigned';
                echo '<li>' . esc_html($p->post_title) . ' – ' . esc_html($status) . ' – ' . esc_html($assignee) . ' – ' . esc_html($email) . ' – ' . esc_html(get_the_date('', $p)) . '</li>';
            }
            echo '</ul>';
        }

        // Recent activity (last changes from logs)
        echo '<h3>Recent Activity</h3>';
        $activity_rows = [];
        $activity_posts = get_posts([
            'post_type' => 'contact_enquiry',
            'posts_per_page' => 20,
            'post_status' => ['publish'],
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        foreach ($activity_posts as $p) {
            $log = get_post_meta($p->ID, 'ctf_change_log', true);
            if (is_array($log)) {
                foreach ($log as $entry) {
                    $activity_rows[] = [
                        'time' => isset($entry['time']) ? $entry['time'] : '',
                        'title' => $p->post_title,
                        'action' => isset($entry['action']) ? $entry['action'] : '',
                        'details' => isset($entry['details']) ? $entry['details'] : '',
                    ];
                }
            }
        }
        if (!$activity_rows) {
            echo '<p>No activity recorded yet.</p>';
        } else {
            // Sort by time desc and show top 10
            usort($activity_rows, function($a,$b){ return strcmp($b['time'], $a['time']); });
            $activity_rows = array_slice($activity_rows, 0, 10);
            echo '<ul class="ctf-recent-activity">';
            foreach ($activity_rows as $row) {
                echo '<li>' . esc_html($row['time']) . ' – ' . esc_html($row['title']) . ' – ' . esc_html($row['action']) . ' – ' . esc_html($row['details']) . '</li>';
            }
            echo '</ul>';
        }
        echo '<p><a class="button" href="' . esc_url(admin_url('edit.php?post_type=contact_enquiry')) . '">View all Enquiries</a></p>';
    });
    // Dedicated Recent Activity widget (top priority for admins)
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget('ctf_recent_activity_only', 'Enquiries – Recent Activity', function(){
            $activity_rows = [];
            $activity_posts = get_posts([
                'post_type' => 'contact_enquiry',
                'posts_per_page' => 50,
                'post_status' => ['publish'],
                'orderby' => 'date',
                'order' => 'DESC'
            ]);
            foreach ($activity_posts as $p) {
                $log = get_post_meta($p->ID, 'ctf_change_log', true);
                if (is_array($log)) {
                    foreach ($log as $entry) {
                        $activity_rows[] = [
                            'time' => isset($entry['time']) ? $entry['time'] : '',
                            'title' => $p->post_title,
                            'post_id' => $p->ID,
                            'action' => isset($entry['action']) ? $entry['action'] : '',
                            'details' => isset($entry['details']) ? $entry['details'] : '',
                        ];
                    }
                }
            }
            if (!$activity_rows) {
                echo '<p>No recent activity.</p>';
                return;
            }
            usort($activity_rows, function($a,$b){ return strcmp($b['time'], $a['time']); });
            $activity_rows = array_slice($activity_rows, 0, 10);
            echo '<ul class="ctf-recent-activity" style="margin:0;padding-left:18px;">';
            foreach ($activity_rows as $row) {
                $url = admin_url('post.php?post=' . intval($row['post_id']) . '&action=edit');
                echo '<li><span style="color:#6b7280;">' . esc_html($row['time']) . '</span> – <a href="' . esc_url($url) . '"><strong>' . esc_html($row['title']) . '</strong></a> – ' . esc_html($row['action']) . ' – ' . esc_html($row['details']) . '</li>';
            }
            echo '</ul>';
        });
    }
});

// CSV Export button and handler
add_action('restrict_manage_posts', function($post_type){
    if ($post_type !== 'contact_enquiry') return;
    // Allow admins or users with CPT edit capability (Task Managers)
    if (!current_user_can('manage_options') && !current_user_can('edit_contact_enquiries')) return;

    $current_status = isset($_GET['ctf_status']) ? sanitize_text_field(wp_unslash($_GET['ctf_status'])) : '';
    $current_user   = isset($_GET['ctf_assigned_to']) ? sanitize_text_field(wp_unslash($_GET['ctf_assigned_to'])) : '';
    $email_like     = isset($_GET['ctf_email']) ? sanitize_text_field(wp_unslash($_GET['ctf_email'])) : '';
    $phone_like     = isset($_GET['ctf_phone']) ? sanitize_text_field(wp_unslash($_GET['ctf_phone'])) : '';
    $only_unassigned= isset($_GET['ctf_unassigned']) ? (bool) $_GET['ctf_unassigned'] : false;
    $date_from      = isset($_GET['ctf_date_from']) ? sanitize_text_field(wp_unslash($_GET['ctf_date_from'])) : '';
    $date_to        = isset($_GET['ctf_date_to']) ? sanitize_text_field(wp_unslash($_GET['ctf_date_to'])) : '';

    // Detect Task Manager and constrain UI accordingly
    $cu = wp_get_current_user();
    $is_task_manager = $cu && is_array($cu->roles) && in_array('task_manager', $cu->roles, true);
    if ($is_task_manager) {
        $current_user = (string) get_current_user_id();
        // Force assigned_to in query via hidden input
        echo '<input type="hidden" name="ctf_assigned_to" value="' . esc_attr($current_user) . '" />';
        // Do not show unassigned toggle to TMs
        $only_unassigned = false;
    }

    echo '<span class="ctf-filter-inline">';
    // Status filter
    echo '<label for="filter-by-ctf-status" class="screen-reader-text">Filter by status</label>';
    echo '<select name="ctf_status" id="filter-by-ctf-status">';
    echo '<option value="">All statuses</option>';
    $statuses = ctf_get_status_names();
    foreach ($statuses as $st) {
        $sel = selected($current_status, $st, false);
        echo '<option value="' . esc_attr($st) . '" ' . $sel . '>' . esc_html($st) . '</option>';
    }
    echo '</select>';

    // Assignee filter (hidden for Task Managers)
    if (!$is_task_manager) {
        echo '<label for="filter-by-ctf-assignee" class="screen-reader-text">Filter by assignee</label>';
        echo '<select name="ctf_assigned_to" id="filter-by-ctf-assignee">';
        echo '<option value="">All assignees</option>';
        $users = get_users(['role__in' => ['administrator','editor','task_manager'], 'orderby' => 'display_name', 'order' => 'ASC']);
        foreach ($users as $user) {
            $sel = selected($current_user, (string)$user->ID, false);
            echo '<option value="' . esc_attr($user->ID) . '" ' . $sel . '>' . esc_html($user->display_name) . '</option>';
        }
        echo '</select>';
    }
    // Email contains
    echo '<label for="filter-by-ctf-email" class="screen-reader-text">Filter by email</label>';
    echo '<input type="text" name="ctf_email" id="filter-by-ctf-email" placeholder="Email contains" value="' . esc_attr($email_like) . '" />';

    // Phone contains
    echo '<label for="filter-by-ctf-phone" class="screen-reader-text">Filter by phone</label>';
    echo '<input type="text" name="ctf_phone" id="filter-by-ctf-phone" placeholder="Phone contains" value="' . esc_attr($phone_like) . '" />';

    // Unassigned only
    if (!$is_task_manager) {
        echo '<label for="filter-by-ctf-unassigned" class="screen-reader-text">Unassigned only</label>';
        echo '<input type="checkbox" name="ctf_unassigned" id="filter-by-ctf-unassigned" value="1" ' . checked($only_unassigned, true, false) . ' /> Unassigned';
    }

    // Date range
    echo '<label for="filter-by-ctf-date-from" class="screen-reader-text">From date</label>';
    echo '<input type="date" name="ctf_date_from" id="filter-by-ctf-date-from" value="' . esc_attr($date_from) . '" />';
    echo '<label for="filter-by-ctf-date-to" class="screen-reader-text">To date</label>';
    echo '<input type="date" name="ctf_date_to" id="filter-by-ctf-date-to" value="' . esc_attr($date_to) . '" />';

    echo '</span>';

    // Export button respecting current filters
    $export_url = add_query_arg([
        'action' => 'ctf_export_csv',
        '_wpnonce' => wp_create_nonce('ctf_export_csv'),
        'ctf_status' => $current_status,
        'ctf_assigned_to' => $current_user,
        'ctf_email' => $email_like,
        'ctf_phone' => $phone_like,
        'ctf_unassigned' => $only_unassigned ? '1' : '',
        'ctf_date_from' => $date_from,
        'ctf_date_to' => $date_to,
    ], admin_url('admin-post.php'));
    echo ' <a href="' . esc_url($export_url) . '" class="button">Export CSV</a>';
});

add_action('admin_post_ctf_export_csv', function(){
    if (!current_user_can('manage_options') && !current_user_can('edit_contact_enquiries')) {
        wp_die('Unauthorized');
    }
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'ctf_export_csv')) {
        wp_die('Invalid nonce');
    }
    // Respect filters
    $status = isset($_GET['ctf_status']) ? sanitize_text_field(wp_unslash($_GET['ctf_status'])) : '';
    $assigned_to = isset($_GET['ctf_assigned_to']) ? sanitize_text_field(wp_unslash($_GET['ctf_assigned_to'])) : '';
    $email_like = isset($_GET['ctf_email']) ? sanitize_text_field(wp_unslash($_GET['ctf_email'])) : '';
    $phone_like = isset($_GET['ctf_phone']) ? sanitize_text_field(wp_unslash($_GET['ctf_phone'])) : '';
    $only_unassigned = !empty($_GET['ctf_unassigned']);
    $date_from = isset($_GET['ctf_date_from']) ? sanitize_text_field(wp_unslash($_GET['ctf_date_from'])) : '';
    $date_to   = isset($_GET['ctf_date_to']) ? sanitize_text_field(wp_unslash($_GET['ctf_date_to'])) : '';

    $args = [
        'post_type' => 'contact_enquiry',
        'posts_per_page' => -1,
        'post_status' => ['publish'],
        'orderby' => 'date',
        'order' => 'DESC'
    ];
    $meta_query = [];
    if ($status !== '') {
        $meta_query[] = [
            'key' => 'status',
            'value' => $status,
            'compare' => '='
        ];
    }
    if ($assigned_to !== '') {
        $meta_query[] = [
            'key' => 'assigned_to',
            'value' => $assigned_to,
            'compare' => '='
        ];
    }
    if ($only_unassigned) {
        $meta_query[] = [
            'key' => 'assigned_to',
            'compare' => 'NOT EXISTS'
        ];
    }
    if ($email_like !== '') {
        $meta_query[] = [
            'key' => 'email',
            'value' => $email_like,
            'compare' => 'LIKE'
        ];
    }
    if ($phone_like !== '') {
        $meta_query[] = [
            'key' => 'phone',
            'value' => $phone_like,
            'compare' => 'LIKE'
        ];
    }
    if (!empty($meta_query)) {
        $args['meta_query'] = $meta_query;
    }

    if ($date_from || $date_to) {
        $date_query = [ 'inclusive' => true ];
        if ($date_from) { $date_query['after'] = $date_from; }
        if ($date_to)   { $date_query['before'] = $date_to; }
        $args['date_query'] = [ $date_query ];
    }

    $posts = get_posts($args);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=enquiries-' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID','Name','Email','Phone','Status','Assigned To','Date','Message']);
    foreach ($posts as $p) {
        $email = get_post_meta($p->ID, 'email', true);
        $phone = get_post_meta($p->ID, 'phone', true);
        $status = get_post_meta($p->ID, 'status', true);
        $assigned = get_post_meta($p->ID, 'assigned_to', true);
        $assignee = $assigned ? (get_userdata($assigned)->display_name ?? 'User #'.$assigned) : '';
        fputcsv($output, [
            $p->ID,
            $p->post_title,
            $email,
            $phone,
            $status,
            $assignee,
            get_the_date('Y-m-d H:i:s', $p),
            wp_strip_all_tags($p->post_content)
        ]);
    }
    fclose($output);
    exit;
});
