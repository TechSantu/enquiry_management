<?php
/**
 * Plugin Name: Enquiry Management
 * Description: Contact form with task assignment, admin dashboard, and email notifications.
 * Version: 2.0
 * Author: Santu Maity
 */

if (!defined('ABSPATH')) exit;

foreach (glob(plugin_dir_path(__FILE__) . 'includes/*.php') as $file) {
    require_once $file;
}

// Enqueue styles & scripts
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('ctf-style', plugin_dir_url(__FILE__) . 'assets/style.css');
    wp_enqueue_script('ctf-script', plugin_dir_url(__FILE__) . 'assets/script.js', ['jquery'], false, true);
    wp_localize_script('ctf-script', 'ctf_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('ctf_form_nonce'),
    ]);
    
    // Enqueue reCAPTCHA script if enabled
    if (function_exists('ctf_is_captcha_enabled') && ctf_is_captcha_enabled()) {
        $captcha_type = function_exists('ctf_get_captcha_type') ? ctf_get_captcha_type() : '';
        $recaptcha_site_key = function_exists('ctf_get_recaptcha_site_key') ? ctf_get_recaptcha_site_key() : '';
        if (!empty($recaptcha_site_key)) {
            if ($captcha_type === 'recaptcha_v2') {
                wp_enqueue_script('google-recaptcha', 'https://www.google.com/recaptcha/api.js', [], null, true);
            } elseif ($captcha_type === 'recaptcha_v3') {
                wp_enqueue_script('google-recaptcha-v3', 'https://www.google.com/recaptcha/api.js?render=' . esc_attr($recaptcha_site_key), [], null, true);
            }
        }
    }
});

// Admin assets
add_action('admin_enqueue_scripts', function(){
    wp_enqueue_style('ctf-admin', plugin_dir_url(__FILE__) . 'assets/admin.css');
});

// Add dynamic status color styles
add_action('admin_head', function() {
    if (!function_exists('ctf_get_statuses')) {
        return;
    }
    $statuses = ctf_get_statuses();
    if (empty($statuses)) {
        return;
    }
    echo '<style id="ctf-dynamic-status-styles">';
    foreach ($statuses as $status) {
        $slug = ctf_get_status_slug($status['name']);
        echo '.ctf-status-' . esc_attr($slug) . ' { background-color: ' . esc_attr($status['color']) . ' !important; }';
    }
    echo '</style>';
});

// Role/Capability setup
register_activation_hook(__FILE__, function(){
    // Create/ensure Task Manager role with scoped capabilities only for contact_enquiry
    remove_role('task_manager');
    add_role('task_manager', 'Task Manager', [
        'read' => true,
        'read_contact_enquiry' => true,
        'read_private_contact_enquiries' => true,
        'edit_contact_enquiry' => true,
        'edit_contact_enquiries' => true,
        'edit_published_contact_enquiries' => true,
        'edit_others_contact_enquiries' => true,
    ]);
    // Ensure administrators can assign tasks
    if ($admin = get_role('administrator')) {
        $admin->add_cap('assign_contact_tasks');
        // Grant full capabilities on CPT to admins
        foreach ([
            'read_contact_enquiry','read_private_contact_enquiries','edit_contact_enquiry','edit_contact_enquiries',
            'edit_others_contact_enquiries','edit_published_contact_enquiries','publish_contact_enquiries','delete_contact_enquiry',
            'delete_others_contact_enquiries','delete_published_contact_enquiries'
        ] as $cap) { $admin->add_cap($cap); }
    }
});

// Defensive: ensure admin always retains assign capability (in case plugin was updated without re-activation)
add_action('init', function(){
    // Ensure the custom role exists and has scoped caps even if activation hook didn't run
    if (!get_role('task_manager')) {
        add_role('task_manager', 'Task Manager', [ 'read' => true ]);
    }
    if ($tm = get_role('task_manager')) {
        // Remove generic post caps
        foreach (['edit_posts','edit_others_posts','publish_posts','delete_posts'] as $cap) { if ($tm->has_cap($cap)) { $tm->remove_cap($cap); } }
        // Add CPT-specific caps
        foreach ([
            'read_contact_enquiry','read_private_contact_enquiries','edit_contact_enquiry','edit_contact_enquiries','edit_published_contact_enquiries','edit_others_contact_enquiries'
        ] as $cap) { if (!$tm->has_cap($cap)) { $tm->add_cap($cap); } }
    }
    if ($admin = get_role('administrator')) {
        if (!$admin->has_cap('assign_contact_tasks')) {
            $admin->add_cap('assign_contact_tasks');
        }
        foreach ([
            'read_contact_enquiry','read_private_contact_enquiries','edit_contact_enquiry','edit_contact_enquiries',
            'edit_others_contact_enquiries','edit_published_contact_enquiries','publish_contact_enquiries','delete_contact_enquiry',
            'delete_others_contact_enquiries','delete_published_contact_enquiries'
        ] as $cap) { if (!$admin->has_cap($cap)) { $admin->add_cap($cap); } }
    }
    // Revoke assign capability from task_manager if present
    if ($tm = get_role('task_manager')) {
        if ($tm->has_cap('assign_contact_tasks')) {
            $tm->remove_cap('assign_contact_tasks');
        }
    }
});

// Hide most admin menus for Task Managers; leave Enquiries and Profile
add_action('admin_menu', function(){
    $user = wp_get_current_user();
    if (!$user || !in_array('task_manager', (array)$user->roles, true)) return;
    // Remove common menus
    remove_menu_page('edit.php'); // Posts
    remove_menu_page('upload.php'); // Media
    remove_menu_page('edit.php?post_type=page'); // Pages
    remove_menu_page('edit-comments.php'); // Comments
    remove_menu_page('themes.php'); // Appearance
    remove_menu_page('plugins.php'); // Plugins
    remove_menu_page('users.php'); // Users
    remove_menu_page('tools.php'); // Tools
    remove_menu_page('options-general.php'); // Settings
    // Many builders add Templates under themes.php or custom; optionally hide Elementor templates
    remove_menu_page('edit.php?post_type=elementor_library');
});

// Disable the post locked "take over" dialog for admins (still applies to others)
add_filter('show_post_locked_dialog', function($show, $post, $user){
    if (!is_admin()) return $show;
    if (!current_user_can('manage_options')) return $show;
    if ($post && isset($post->post_type) && $post->post_type === 'contact_enquiry') {
        return false;
    }
    return $show;
}, 10, 3);
