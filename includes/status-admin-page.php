<?php
/**
 * Status Management Admin Page
 */

// Add admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=contact_enquiry',
        'Manage Statuses',
        'Manage Statuses',
        'manage_options',
        'ctf-manage-statuses',
        'ctf_status_management_page'
    );
});

// Status management page
function ctf_status_management_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Handle form submissions
    if (isset($_POST['ctf_action']) && check_admin_referer('ctf_status_management')) {
        $action = sanitize_text_field(wp_unslash($_POST['ctf_action']));
        
        if ($action === 'add') {
            $name = isset($_POST['ctf_status_name']) ? sanitize_text_field(wp_unslash($_POST['ctf_status_name'])) : '';
            $color = isset($_POST['ctf_status_color']) ? sanitize_text_field(wp_unslash($_POST['ctf_status_color'])) : '#6b7280';
            $result = ctf_add_status($name, $color);
            echo '<div class="notice notice-' . ($result['success'] ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        }
        
        if ($action === 'update') {
            $old_name = isset($_POST['ctf_status_old_name']) ? sanitize_text_field(wp_unslash($_POST['ctf_status_old_name'])) : '';
            $new_name = isset($_POST['ctf_status_new_name']) ? sanitize_text_field(wp_unslash($_POST['ctf_status_new_name'])) : '';
            $color = isset($_POST['ctf_status_color']) ? sanitize_text_field(wp_unslash($_POST['ctf_status_color'])) : null;
            $order = isset($_POST['ctf_status_order']) ? intval($_POST['ctf_status_order']) : null;
            $result = ctf_update_status($old_name, $new_name, $color, $order);
            echo '<div class="notice notice-' . ($result['success'] ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        }
        
        if ($action === 'delete') {
            $name = isset($_POST['ctf_status_name']) ? sanitize_text_field(wp_unslash($_POST['ctf_status_name'])) : '';
            $migrate_to = isset($_POST['ctf_migrate_to']) ? sanitize_text_field(wp_unslash($_POST['ctf_migrate_to'])) : '';
            $result = ctf_delete_status($name, $migrate_to ?: null);
            echo '<div class="notice notice-' . ($result['success'] ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html($result['message']) . '</p></div>';
        }
    }
    
    $statuses = ctf_get_statuses();
    ?>
    <div class="wrap">
        <h1>Manage Statuses</h1>
        
        <div class="ctf-status-management">
            <div class="ctf-status-list">
                <h2>Current Statuses</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Color</th>
                            <th>Order</th>
                            <th>Preview</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($statuses)) : ?>
                            <tr>
                                <td colspan="5">No statuses found.</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($statuses as $status) : 
                                $slug = ctf_get_status_slug($status['name']);
                                $usage_count = ctf_get_status_usage_count($status['name']);
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($status['name']); ?></strong></td>
                                    <td>
                                        <input type="color" value="<?php echo esc_attr($status['color']); ?>" 
                                               data-status="<?php echo esc_attr($status['name']); ?>" 
                                               class="ctf-status-color-picker" />
                                    </td>
                                    <td>
                                        <input type="number" value="<?php echo esc_attr($status['order']); ?>" 
                                               data-status="<?php echo esc_attr($status['name']); ?>" 
                                               class="ctf-status-order" min="1" style="width:60px;" />
                                    </td>
                                    <td>
                                        <span class="ctf-badge ctf-status-<?php echo esc_attr($slug); ?>" 
                                              style="background-color: <?php echo esc_attr($status['color']); ?>;">
                                            <?php echo esc_html($status['name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="button ctf-edit-status" 
                                                data-name="<?php echo esc_attr($status['name']); ?>"
                                                data-color="<?php echo esc_attr($status['color']); ?>"
                                                data-order="<?php echo esc_attr($status['order']); ?>">
                                            Edit
                                        </button>
                                        <?php if ($usage_count > 0) : ?>
                                            <span class="description">(<?php echo esc_html($usage_count); ?> enquiries)</span>
                                        <?php endif; ?>
                                        <button type="button" class="button button-link-delete ctf-delete-status" 
                                                data-name="<?php echo esc_attr($status['name']); ?>"
                                                data-usage="<?php echo esc_attr($usage_count); ?>">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="ctf-status-forms" style="margin-top: 30px;">
                <h2>Add New Status</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('ctf_status_management'); ?>
                    <input type="hidden" name="ctf_action" value="add" />
                    <table class="form-table">
                        <tr>
                            <th><label for="ctf_status_name">Status Name</label></th>
                            <td>
                                <input type="text" id="ctf_status_name" name="ctf_status_name" required class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ctf_status_color">Color</label></th>
                            <td>
                                <input type="color" id="ctf_status_color" name="ctf_status_color" value="#6b7280" />
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Add Status" />
                    </p>
                </form>
            </div>
            
            <!-- Edit Form (hidden by default) -->
            <div class="ctf-status-edit-form" style="margin-top: 30px; display: none;">
                <h2>Edit Status</h2>
                <form method="post" action="" id="ctf-edit-status-form">
                    <?php wp_nonce_field('ctf_status_management'); ?>
                    <input type="hidden" name="ctf_action" value="update" />
                    <input type="hidden" name="ctf_status_old_name" id="ctf_status_old_name" />
                    <table class="form-table">
                        <tr>
                            <th><label for="ctf_status_new_name">Status Name</label></th>
                            <td>
                                <input type="text" id="ctf_status_new_name" name="ctf_status_new_name" required class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ctf_status_edit_color">Color</label></th>
                            <td>
                                <input type="color" id="ctf_status_edit_color" name="ctf_status_color" value="#6b7280" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="ctf_status_edit_order">Order</label></th>
                            <td>
                                <input type="number" id="ctf_status_edit_order" name="ctf_status_order" min="1" value="1" />
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Update Status" />
                        <button type="button" class="button ctf-cancel-edit">Cancel</button>
                    </p>
                </form>
            </div>
            
            <!-- Delete Form (hidden by default) -->
            <div class="ctf-status-delete-form" style="margin-top: 30px; display: none;">
                <h2>Delete Status</h2>
                <form method="post" action="" id="ctf-delete-status-form">
                    <?php wp_nonce_field('ctf_status_management'); ?>
                    <input type="hidden" name="ctf_action" value="delete" />
                    <input type="hidden" name="ctf_status_name" id="ctf_delete_status_name" />
                    <table class="form-table">
                        <tr>
                            <th><label>Status to Delete</label></th>
                            <td>
                                <strong id="ctf_delete_status_display"></strong>
                                <p class="description" id="ctf_delete_status_usage"></p>
                            </td>
                        </tr>
                        <tr id="ctf_migrate_row" style="display: none;">
                            <th><label for="ctf_migrate_to">Migrate Enquiries To</label></th>
                            <td>
                                <select name="ctf_migrate_to" id="ctf_migrate_to" required>
                                    <option value="">-- Select Status --</option>
                                    <?php foreach ($statuses as $status) : ?>
                                        <option value="<?php echo esc_attr($status['name']); ?>">
                                            <?php echo esc_html($status['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">All enquiries with this status will be migrated to the selected status.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <input type="submit" class="button button-primary" value="Delete Status" />
                        <button type="button" class="button ctf-cancel-delete">Cancel</button>
                    </p>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Edit status
        $('.ctf-edit-status').on('click', function() {
            var name = $(this).data('name');
            var color = $(this).data('color');
            var order = $(this).data('order');
            
            $('#ctf_status_old_name').val(name);
            $('#ctf_status_new_name').val(name);
            $('#ctf_status_edit_color').val(color);
            $('#ctf_status_edit_order').val(order);
            
            $('.ctf-status-edit-form').show();
            $('.ctf-status-delete-form').hide();
            $('html, body').animate({ scrollTop: $('.ctf-status-edit-form').offset().top - 50 }, 500);
        });
        
        // Cancel edit
        $('.ctf-cancel-edit').on('click', function() {
            $('.ctf-status-edit-form').hide();
        });
        
        // Delete status
        $('.ctf-delete-status').on('click', function() {
            var name = $(this).data('name');
            var usage = parseInt($(this).data('usage')) || 0;
            
            $('#ctf_delete_status_name').val(name);
            $('#ctf_delete_status_display').text(name);
            
            if (usage > 0) {
                $('#ctf_delete_status_usage').text('This status is used by ' + usage + ' enquiry(ies). You must migrate them to another status.');
                $('#ctf_migrate_row').show();
                $('#ctf_migrate_to').prop('required', true);
            } else {
                $('#ctf_delete_status_usage').text('This status is not in use. You can safely delete it.');
                $('#ctf_migrate_row').hide();
                $('#ctf_migrate_to').prop('required', false);
            }
            
            $('.ctf-status-delete-form').show();
            $('.ctf-status-edit-form').hide();
            $('html, body').animate({ scrollTop: $('.ctf-status-delete-form').offset().top - 50 }, 500);
        });
        
        // Cancel delete
        $('.ctf-cancel-delete').on('click', function() {
            $('.ctf-status-delete-form').hide();
        });
        
        // Update color on change
        $('.ctf-status-color-picker').on('change', function() {
            var color = $(this).val();
            var statusName = $(this).data('status');
            var form = $('<form method="post">').append(
                $('<input>').attr({type: 'hidden', name: 'ctf_action', value: 'update'}),
                $('<input>').attr({type: 'hidden', name: 'ctf_status_old_name', value: statusName}),
                $('<input>').attr({type: 'hidden', name: 'ctf_status_new_name', value: statusName}),
                $('<input>').attr({type: 'hidden', name: 'ctf_status_color', value: color}),
                $('<?php echo wp_nonce_field('ctf_status_management', '_wpnonce', true, false); ?>')
            );
            form.appendTo('body').submit();
        });
        
        // Update order on change
        $('.ctf-status-order').on('change', function() {
            var order = $(this).val();
            var statusName = $(this).data('status');
            var form = $('<form method="post">').append(
                $('<input>').attr({type: 'hidden', name: 'ctf_action', value: 'update'}),
                $('<input>').attr({type: 'hidden', name: 'ctf_status_old_name', value: statusName}),
                $('<input>').attr({type: 'hidden', name: 'ctf_status_new_name', value: statusName}),
                $('<input>').attr({type: 'hidden', name: 'ctf_status_order', value: order}),
                $('<?php echo wp_nonce_field('ctf_status_management', '_wpnonce', true, false); ?>')
            );
            form.appendTo('body').submit();
        });
    });
    </script>
    <?php
}

// Helper function to get usage count
function ctf_get_status_usage_count($status_name) {
    $posts = get_posts([
        'post_type' => 'contact_enquiry',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'status',
                'value' => $status_name,
                'compare' => '='
            ]
        ],
        'fields' => 'ids'
    ]);
    return count($posts);
}

