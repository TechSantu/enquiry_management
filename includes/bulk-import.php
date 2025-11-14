<?php
/**
 * Bulk Import Functionality
 */

// Helper function to convert PHP ini size to bytes
function ctf_convert_to_bytes($val) {
    $val = trim($val);
    if (empty($val)) return 0;
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// Add admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=contact_enquiry',
        'Bulk Import',
        'Bulk Import',
        'manage_options',
        'ctf-bulk-import',
        'ctf_bulk_import_page'
    );
});

// Handle file upload
add_action('admin_post_ctf_upload_import_file', function() {
    // Start output buffering to prevent any accidental output
    ob_start();
    
    if (!current_user_can('manage_options')) {
        ob_end_clean();
        wp_die('Unauthorized');
    }
    
    check_admin_referer('ctf_upload_import_file');
    
    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        ob_end_clean();
        $redirect_url = add_query_arg(['page' => 'ctf-bulk-import', 'error' => 'file_upload'], admin_url('edit.php?post_type=contact_enquiry'));
        if (headers_sent()) {
            echo '<script type="text/javascript">window.location.href = "' . esc_js($redirect_url) . '";</script>';
            exit;
        }
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    $file = $_FILES['import_file'];
    $file_type = wp_check_filetype($file['name']);
    
    if (!in_array($file_type['ext'], ['csv'])) {
        ob_end_clean();
        $redirect_url = add_query_arg(['page' => 'ctf-bulk-import', 'error' => 'invalid_file'], admin_url('edit.php?post_type=contact_enquiry'));
        if (headers_sent()) {
            echo '<script type="text/javascript">window.location.href = "' . esc_js($redirect_url) . '";</script>';
            exit;
        }
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    // Save uploaded file temporarily
    $upload_dir = wp_upload_dir();
    $import_dir = $upload_dir['basedir'] . '/ctf-imports';
    if (!file_exists($import_dir)) {
        wp_mkdir_p($import_dir);
    }
    
    $file_name = 'import_' . time() . '_' . sanitize_file_name($file['name']);
    $file_path = $import_dir . '/' . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        // Store file path in transient
        set_transient('ctf_import_file_' . get_current_user_id(), $file_path, 3600);
        ob_end_clean();
        $redirect_url = add_query_arg(['page' => 'ctf-bulk-import', 'step' => 'map'], admin_url('edit.php?post_type=contact_enquiry'));
        if (headers_sent()) {
            echo '<script type="text/javascript">window.location.href = "' . esc_js($redirect_url) . '";</script>';
            exit;
        }
        wp_safe_redirect($redirect_url);
        exit;
    } else {
        ob_end_clean();
        $redirect_url = add_query_arg(['page' => 'ctf-bulk-import', 'error' => 'upload_failed'], admin_url('edit.php?post_type=contact_enquiry'));
        if (headers_sent()) {
            echo '<script type="text/javascript">window.location.href = "' . esc_js($redirect_url) . '";</script>';
            exit;
        }
        wp_safe_redirect($redirect_url);
        exit;
    }
});

// Handle field mapping
add_action('admin_post_ctf_map_import_fields', function() {
    // Start output buffering to prevent any accidental output
    ob_start();
    
    if (!current_user_can('manage_options')) {
        ob_end_clean();
        wp_die('Unauthorized');
    }
    
    check_admin_referer('ctf_map_import_fields');
    
    $user_id = get_current_user_id();
    $file_path = get_transient('ctf_import_file_' . $user_id);
    
    if (!$file_path || !file_exists($file_path)) {
        ob_end_clean();
        $redirect_url = add_query_arg(['page' => 'ctf-bulk-import', 'error' => 'file_not_found'], admin_url('edit.php?post_type=contact_enquiry'));
        if (headers_sent()) {
            echo '<script type="text/javascript">window.location.href = "' . esc_js($redirect_url) . '";</script>';
            exit;
        }
        wp_safe_redirect($redirect_url);
        exit;
    }
    
    $mappings = isset($_POST['field_mapping']) ? array_map('sanitize_text_field', $_POST['field_mapping']) : [];
    $field_types = isset($_POST['field_type']) ? array_map('sanitize_text_field', $_POST['field_type']) : [];
    $new_field_labels = isset($_POST['new_field_label']) ? array_map('sanitize_text_field', $_POST['new_field_label']) : [];
    $new_field_meta_keys = isset($_POST['new_field_meta_key']) ? array_map('sanitize_key', $_POST['new_field_meta_key']) : [];
    
    // Process new fields
    foreach ($mappings as $csv_index => $field_key) {
        if ($field_key === '__new__' && !empty($new_field_labels[$csv_index])) {
            $label = $new_field_labels[$csv_index];
            $field_type = !empty($field_types[$csv_index]) ? $field_types[$csv_index] : 'text';
            $meta_key = !empty($new_field_meta_keys[$csv_index]) ? $new_field_meta_keys[$csv_index] : '';
            
            $result = ctf_add_task_field($label, $field_type, $meta_key);
            if ($result['success']) {
                // Update mapping to use the new field's meta key
                $new_field = ctf_get_task_field($meta_key ?: ctf_generate_meta_key($label));
                if ($new_field) {
                    $mappings[$csv_index] = $new_field['meta_key'];
                }
            }
        }
    }
    
    // Clean mappings - remove __new__ entries and empty values
    foreach ($mappings as $key => $value) {
        if ($value === '__new__' || empty($value)) {
            unset($mappings[$key]);
        }
    }
    
    // Ensure mappings are stored with integer keys
    $clean_mappings = [];
    foreach ($mappings as $key => $value) {
        $clean_mappings[(int)$key] = $value;
    }
    
    // Store mappings in transient
    set_transient('ctf_import_mappings_' . $user_id, $clean_mappings, 3600);
    
    ob_end_clean();
    $redirect_url = add_query_arg(['page' => 'ctf-bulk-import', 'step' => 'preview'], admin_url('edit.php?post_type=contact_enquiry'));
    if (headers_sent()) {
        echo '<script type="text/javascript">window.location.href = "' . esc_js($redirect_url) . '";</script>';
        exit;
    }
    wp_safe_redirect($redirect_url);
    exit;
});

// Handle import processing
add_action('admin_post_ctf_do_import', function() {
    // Start output buffering to prevent any accidental output
    ob_start();
    
    if (!current_user_can('manage_options')) {
        ob_end_clean();
        wp_die('Unauthorized');
    }
    
    check_admin_referer('ctf_do_import');
    
    $user_id = get_current_user_id();
    $file_path = get_transient('ctf_import_file_' . $user_id);
    $mappings = get_transient('ctf_import_mappings_' . $user_id);
    
    if (empty($mappings)) {
        ob_end_clean();
        wp_safe_redirect(add_query_arg(['page' => 'ctf-bulk-import', 'step' => 'map', 'error' => 'no_mappings'], admin_url('edit.php?post_type=contact_enquiry')));
        exit;
    }
    
    if (!$file_path || !file_exists($file_path)) {
        ob_end_clean();
        wp_safe_redirect(add_query_arg(['page' => 'ctf-bulk-import', 'error' => 'file_not_found'], admin_url('edit.php?post_type=contact_enquiry')));
        exit;
    }
    
    // Debug output (only to error log, not browser)
    @error_log('CTF Import: Starting import process');
    @error_log('CTF Import: File path - ' . $file_path);
    @error_log('CTF Import: File exists - ' . (file_exists($file_path) ? 'Yes' : 'No'));
    @error_log('CTF Import: Mappings count - ' . count($mappings));
    
    $result = ctf_process_import($file_path, $mappings);
    $imported = $result['imported'] ?? 0;
    $skipped = $result['skipped'] ?? 0;
    $errors = $result['errors'] ?? [];
    
    @error_log('CTF Import: Result - Imported: ' . $imported . ', Skipped: ' . $skipped);
    
    // Clean up
    delete_transient('ctf_import_file_' . $user_id);
    delete_transient('ctf_import_mappings_' . $user_id);
    @unlink($file_path);
    
    // Redirect with results
    $redirect_args = [
        'page' => 'ctf-bulk-import',
        'imported' => $imported,
        'skipped' => $skipped
    ];
    
    if (!empty($errors)) {
        $redirect_args['errors'] = urlencode(implode('|', $errors));
    }
    
    $redirect_url = add_query_arg($redirect_args, admin_url('edit.php?post_type=contact_enquiry'));
    
    // Clear any output and redirect
    ob_end_clean();
    
    // Check if headers already sent, use JavaScript redirect as fallback
    if (headers_sent()) {
        echo '<script type="text/javascript">';
        echo 'window.location.href = "' . esc_js($redirect_url) . '";';
        echo '</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url($redirect_url) . '"></noscript>';
        exit;
    }
    
    wp_safe_redirect($redirect_url);
    exit;
});

// Bulk import page
function ctf_bulk_import_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'upload';
    $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : '';
    $imported = isset($_GET['imported']) ? (int)$_GET['imported'] : 0;
    $skipped = isset($_GET['skipped']) ? (int)$_GET['skipped'] : 0;
    $errors = isset($_GET['errors']) ? explode('|', urldecode($_GET['errors'])) : [];
    
    ?>
    <div class="wrap">
        <h1>Bulk Import Enquiries</h1>
        
        <?php if ($imported > 0 || $skipped > 0) : ?>
            <div class="notice notice-<?php echo $imported > 0 ? 'success' : 'warning'; ?>">
                <p>
                    <strong>Import Complete:</strong>
                    <?php if ($imported > 0) : ?>
                        Successfully imported <strong><?php echo $imported; ?></strong> enquiry(ies).
                    <?php endif; ?>
                    <?php if ($skipped > 0) : ?>
                        <?php echo $imported > 0 ? ' ' : ''; ?>Skipped <strong><?php echo $skipped; ?></strong> invalid row(s).
                    <?php endif; ?>
                </p>
                <?php if (!empty($errors)) : ?>
                    <ul style="margin: 10px 0 0 20px;">
                        <?php foreach ($errors as $err) : ?>
                            <li><?php echo esc_html($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error) : ?>
            <div class="notice notice-error">
                <p><?php
                    switch ($error) {
                        case 'file_upload':
                            echo 'Error uploading file. Please try again.';
                            break;
                        case 'invalid_file':
                            echo 'Invalid file type. Please upload a CSV file.';
                            break;
                        case 'upload_failed':
                            echo 'Failed to save uploaded file.';
                            break;
                        case 'file_not_found':
                            echo 'Import file not found. Please upload again.';
                            break;
                        case 'no_mappings':
                            echo 'No field mappings found. Please map your CSV columns.';
                            break;
                        default:
                            echo 'An error occurred.';
                    }
                ?></p>
            </div>
        <?php endif; ?>
        
        <?php if ($step === 'upload') : ?>
            <?php ctf_import_upload_step(); ?>
        <?php elseif ($step === 'map') : ?>
            <?php ctf_import_map_step(); ?>
        <?php elseif ($step === 'preview') : ?>
            <?php ctf_import_preview_step(); ?>
        <?php endif; ?>
    </div>
    <?php
}

// Upload step
function ctf_import_upload_step() {
    ?>
    <div class="postbox" style="max-width: 800px; margin-top: 20px;">
        <div class="postbox-header">
            <h2 class="hndle">Step 1: Upload CSV File</h2>
        </div>
        <div class="inside" style="padding: 20px;">
            <p>Upload a CSV file containing enquiry data. The first row should contain column headers.</p>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('ctf_upload_import_file'); ?>
                <input type="hidden" name="action" value="ctf_upload_import_file">
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="import_file">CSV File</label>
                        </th>
                        <td>
                            <input type="file" name="import_file" id="import_file" accept=".csv" required />
                            <p class="description">
                                Select a CSV file to import.
                                <?php
                                $upload_max = ini_get('upload_max_filesize');
                                $post_max = ini_get('post_max_size');
                                $wp_max = wp_max_upload_size();
                                
                                $upload_max_bytes = ctf_convert_to_bytes($upload_max);
                                $post_max_bytes = ctf_convert_to_bytes($post_max);
                                
                                // Use PHP upload_max_filesize as the maximum
                                $effective_max = $upload_max_bytes;
                                
                                echo '<br><strong>Maximum file size:</strong> ' . esc_html($upload_max);
                                echo '<br><small style="color: #666;">PHP upload_max_filesize: ' . esc_html($upload_max) . ' | post_max_size: ' . esc_html($post_max) . ' | WordPress limit: ' . size_format($wp_max) . '</small>';
                                if ($post_max_bytes < $upload_max_bytes) {
                                    echo '<br><small style="color: #d63638;"><strong>Note:</strong> post_max_size (' . esc_html($post_max) . ') is smaller than upload_max_filesize. You may need to increase post_max_size to upload files up to ' . esc_html($upload_max) . '.</small>';
                                }
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Upload and Continue" />
                </p>
            </form>
            
            <div style="margin-top: 30px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h3 style="margin-top: 0;">CSV Format Requirements:</h3>
                <ul>
                    <li>First row must contain column headers</li>
                    <li>Required columns: <strong>Name</strong>, <strong>Email</strong></li>
                    <li>Optional columns: <strong>Phone</strong>, <strong>Company Name</strong>, <strong>Person Designation</strong>, <strong>Nature of Trustee</strong>, <strong>Message</strong></li>
                    <li>You can include additional columns that will be mapped to custom fields</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
}

// Map step
function ctf_import_map_step() {
    $user_id = get_current_user_id();
    $file_path = get_transient('ctf_import_file_' . $user_id);
    
    if (!$file_path || !file_exists($file_path)) {
        echo '<div class="notice notice-error"><p>Import file not found. Please upload again.</p></div>';
        echo '<p><a href="' . admin_url('edit.php?post_type=contact_enquiry&page=ctf-bulk-import') . '" class="button">Start Over</a></p>';
        return;
    }
    
    // Parse CSV
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        echo '<div class="notice notice-error"><p>Error reading file.</p></div>';
        return;
    }
    
    // Detect CSV delimiter
    $first_line = fgets($handle);
    rewind($handle);
    $delimiter = ',';
    if (strpos($first_line, ';') !== false && substr_count($first_line, ';') > substr_count($first_line, ',')) {
        $delimiter = ';';
    } elseif (strpos($first_line, "\t") !== false) {
        $delimiter = "\t";
    }
    
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        echo '<div class="notice notice-error"><p>Invalid CSV file format.</p></div>';
        fclose($handle);
        return;
    }
    
    // Get sample rows (first 3 data rows)
    $sample_rows = [];
    $row_count = 0;
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $row_count < 3) {
        $sample_rows[] = $row;
        $row_count++;
    }
    fclose($handle);
    
    // Get existing task fields
    $task_fields = ctf_get_task_fields();
    $protected_fields = ['name', 'email', 'phone', 'company_name', 'person_designation', 'nature_of_trustee', 'message'];
    
    ?>
    <div class="postbox" style="max-width: 1200px; margin-top: 20px;">
        <div class="postbox-header">
            <h2 class="hndle">Step 2: Map CSV Columns to Fields</h2>
        </div>
        <div class="inside" style="padding: 20px;">
            <p>Map each CSV column to a corresponding field. Required fields: <strong>Name</strong> and <strong>Email</strong>.</p>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('ctf_map_import_fields'); ?>
                <input type="hidden" name="action" value="ctf_map_import_fields">
                
                <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                    <thead>
                        <tr>
                            <th style="width: 200px;">CSV Column</th>
                            <th style="width: 200px;">Sample Data</th>
                            <th style="width: 250px;">Map to Field</th>
                            <th>Field Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($headers as $index => $header) : 
                            $header = trim($header);
                            $sample = isset($sample_rows[0][$index]) ? esc_html($sample_rows[0][$index]) : '';
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($header); ?></strong></td>
                                <td><code style="font-size: 11px;"><?php echo mb_substr($sample, 0, 50) . (mb_strlen($sample) > 50 ? '...' : ''); ?></code></td>
                                <td>
                                    <select name="field_mapping[<?php echo esc_attr($index); ?>]" class="ctf-field-mapping" style="width: 100%;">
                                        <option value="">-- Skip Column --</option>
                                        <optgroup label="Required Fields">
                                            <option value="name" <?php selected(strtolower($header), 'name'); ?>>Name</option>
                                            <option value="email" <?php selected(strtolower($header), 'email'); ?>>Email</option>
                                        </optgroup>
                                        <optgroup label="Standard Fields">
                                            <option value="phone" <?php selected(strtolower($header), 'phone'); ?>>Phone</option>
                                            <option value="company_name" <?php selected(strtolower($header), 'company name'); ?>>Company Name</option>
                                            <option value="person_designation" <?php selected(strtolower($header), 'person designation'); ?>>Person Designation</option>
                                            <option value="nature_of_trustee" <?php selected(strtolower($header), 'nature of trustee'); ?>>Nature of Trustee</option>
                                            <option value="message" <?php selected(strtolower($header), 'message'); ?>>Message</option>
                                        </optgroup>
                                        <?php if (!empty($task_fields)) : ?>
                                            <optgroup label="Custom Fields">
                                                <?php foreach ($task_fields as $field) : 
                                                    if (!in_array($field['meta_key'], $protected_fields)) :
                                                ?>
                                                    <option value="<?php echo esc_attr($field['meta_key']); ?>">
                                                        <?php echo esc_html($field['label']); ?>
                                                    </option>
                                                <?php 
                                                    endif;
                                                endforeach; ?>
                                            </optgroup>
                                        <?php endif; ?>
                                        <optgroup label="Create New Field">
                                            <option value="__new__">+ Create New Field</option>
                                        </optgroup>
                                    </select>
                                </td>
                                <td>
                                    <select name="field_type[<?php echo esc_attr($index); ?>]" class="ctf-field-type" style="width: 100%; display: none;">
                                        <option value="text">Text</option>
                                        <option value="email">Email</option>
                                        <option value="textarea">Textarea</option>
                                        <option value="number">Number</option>
                                        <option value="url">URL</option>
                                        <option value="tel">Phone</option>
                                    </select>
                                    <input type="text" name="new_field_label[<?php echo esc_attr($index); ?>]" 
                                           placeholder="New Field Label" class="ctf-new-field-label" style="width: 100%; display: none;" />
                                    <input type="text" name="new_field_meta_key[<?php echo esc_attr($index); ?>]" 
                                           placeholder="Meta Key (optional)" class="ctf-new-field-meta-key" style="width: 100%; display: none; margin-top: 5px;" />
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div id="ctf-new-fields-container" style="margin-top: 20px; display: none;">
                    <h3>New Fields to Create</h3>
                    <div id="ctf-new-fields-list"></div>
                </div>
                
                <p class="submit" style="margin-top: 20px;">
                    <input type="submit" class="button button-primary" value="Continue to Preview" />
                    <a href="<?php echo admin_url('edit.php?post_type=contact_enquiry&page=ctf-bulk-import'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('.ctf-field-mapping').on('change', function() {
            var $row = $(this).closest('tr');
            var $typeSelect = $row.find('.ctf-field-type');
            var $labelInput = $row.find('.ctf-new-field-label');
            var $metaKeyInput = $row.find('.ctf-new-field-meta-key');
            var $typeCell = $row.find('td:last-child');
            
            if ($(this).val() === '__new__') {
                $typeSelect.show().attr('required', true);
                $labelInput.show().attr('required', true);
                $metaKeyInput.show();
                $typeCell.css('background-color', '#fff3cd');
            } else {
                $typeSelect.hide().removeAttr('required');
                $labelInput.hide().removeAttr('required');
                $metaKeyInput.hide();
                $typeCell.css('background-color', '');
            }
        });
        
        // Auto-generate meta key from label
        $('.ctf-new-field-label').on('blur', function() {
            var $row = $(this).closest('tr');
            var $metaKeyInput = $row.find('.ctf-new-field-meta-key');
            if (!$metaKeyInput.val()) {
                var label = $(this).val().toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
                $metaKeyInput.val(label);
            }
        });
        
        $('form').on('submit', function(e) {
            var hasName = false, hasEmail = false;
            var missingNewFields = [];
            
            $('.ctf-field-mapping').each(function() {
                var val = $(this).val();
                if (val === 'name') hasName = true;
                if (val === 'email') hasEmail = true;
                
                if (val === '__new__') {
                    var $row = $(this).closest('tr');
                    var label = $row.find('.ctf-new-field-label').val();
                    if (!label || !label.trim()) {
                        var columnName = $row.find('td:first-child strong').text();
                        missingNewFields.push(columnName);
                    }
                }
            });
            
            if (!hasName || !hasEmail) {
                e.preventDefault();
                alert('Please map at least "Name" and "Email" columns.');
                return false;
            }
            
            if (missingNewFields.length > 0) {
                e.preventDefault();
                alert('Please enter field labels for new fields in columns: ' + missingNewFields.join(', '));
                return false;
            }
        });
    });
    </script>
    <style>
    .ctf-field-type, .ctf-new-field-label, .ctf-new-field-meta-key {
        margin-top: 5px;
    }
    </style>
    <?php
}

// Preview step
function ctf_import_preview_step() {
    $user_id = get_current_user_id();
    $file_path = get_transient('ctf_import_file_' . $user_id);
    $mappings = get_transient('ctf_import_mappings_' . $user_id);
    
    if (!$file_path || !file_exists($file_path) || !$mappings) {
        echo '<div class="notice notice-error"><p>Import data not found. Please start over.</p></div>';
        echo '<p><a href="' . admin_url('edit.php?post_type=contact_enquiry&page=ctf-bulk-import') . '" class="button">Start Over</a></p>';
        return;
    }
    
    // Parse CSV and preview
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        echo '<div class="notice notice-error"><p>Error reading file.</p></div>';
        return;
    }
    
    // Detect CSV delimiter
    $first_line = fgets($handle);
    rewind($handle);
    $delimiter = ',';
    if (strpos($first_line, ';') !== false && substr_count($first_line, ';') > substr_count($first_line, ',')) {
        $delimiter = ';';
    } elseif (strpos($first_line, "\t") !== false) {
        $delimiter = "\t";
    }
    
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        echo '<div class="notice notice-error"><p>Invalid CSV file format.</p></div>';
        fclose($handle);
        return;
    }
    
    // Count total rows first
    $total_rows = 0;
    $temp_handle = fopen($file_path, 'r');
    fgetcsv($temp_handle, 0, $delimiter); // Skip header
    while (fgetcsv($temp_handle, 0, $delimiter) !== false) {
        $total_rows++;
    }
    fclose($temp_handle);
    
    // Reset file pointer for preview
    rewind($handle);
    fgetcsv($handle, 0, $delimiter); // Skip header again
    
    $preview_rows = [];
    $row_count = 0;
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false && $row_count < 10) {
        $mapped_row = [];
        foreach ($mappings as $csv_index => $field_key) {
            $csv_index = (int)$csv_index; // Ensure integer index
            if (!empty($field_key) && isset($row[$csv_index])) {
                $mapped_row[$field_key] = trim($row[$csv_index]);
            }
        }
        if (!empty($mapped_row)) {
            $preview_rows[] = $mapped_row;
            $row_count++;
        }
    }
    fclose($handle);
    
    ?>
    <div class="postbox" style="max-width: 1200px; margin-top: 20px;">
        <div class="postbox-header">
            <h2 class="hndle">Step 3: Preview and Import</h2>
        </div>
        <div class="inside" style="padding: 20px;">
            <p><strong>Total rows to import:</strong> <?php echo $total_rows; ?> (excluding header)</p>
            <?php if (empty($mappings)) : ?>
                <div class="notice notice-error inline">
                    <p><strong>Error:</strong> No field mappings found. Please go back to the mapping step.</p>
                </div>
            <?php else : ?>
                <details style="margin: 15px 0;">
                    <summary style="cursor: pointer; font-weight: 600;">View Field Mappings (Debug)</summary>
                    <table class="widefat" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th>CSV Column Index</th>
                                <th>Mapped Field</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mappings as $index => $field) : ?>
                                <tr>
                                    <td><?php echo esc_html($index); ?></td>
                                    <td><code><?php echo esc_html($field); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
            <?php endif; ?>
            <p>Preview of first <?php echo count($preview_rows); ?> rows:</p>
            
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <?php if (!empty($preview_rows)) : ?>
                            <?php foreach (array_keys($preview_rows[0]) as $field) : ?>
                                <th><?php echo esc_html($field); ?></th>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview_rows as $row) : ?>
                        <tr>
                            <?php foreach ($row as $value) : ?>
                                <td><?php echo esc_html(mb_substr($value, 0, 50)) . (mb_strlen($value) > 50 ? '...' : ''); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="margin-top: 20px;">
                <?php wp_nonce_field('ctf_do_import'); ?>
                <input type="hidden" name="action" value="ctf_do_import">
                <p class="submit">
                    <input type="submit" class="button button-primary" value="Import All Rows" onclick="return confirm('Are you sure you want to import <?php echo $total_rows; ?> enquiries?');" />
                    <a href="<?php echo admin_url('edit.php?post_type=contact_enquiry&page=ctf-bulk-import'); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
    </div>
    <?php
}

// Process import
function ctf_process_import($file_path, $mappings) {
    $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];
    
    if (!file_exists($file_path)) {
        $result['errors'][] = 'Import file not found.';
        return $result;
    }
    
    if (empty($mappings)) {
        $result['errors'][] = 'No field mappings provided.';
        return $result;
    }
    
    $handle = fopen($file_path, 'r');
    if (!$handle) {
        $result['errors'][] = 'Could not open file for reading.';
        return $result;
    }
    
    // Detect CSV delimiter
    $first_line = fgets($handle);
    rewind($handle);
    $delimiter = ',';
    if (strpos($first_line, ';') !== false && substr_count($first_line, ';') > substr_count($first_line, ',')) {
        $delimiter = ';';
    } elseif (strpos($first_line, "\t") !== false) {
        $delimiter = "\t";
    }
    
    // Set locale for CSV parsing (try to set, but don't fail if not available)
    @setlocale(LC_ALL, 'en_US.UTF-8', 'en_US', 'C');
    
    $headers = fgetcsv($handle, 0, $delimiter); // Skip header
    if (!$headers) {
        fclose($handle);
        $result['errors'][] = 'Invalid CSV file format - no headers found.';
        return $result;
    }
    
    $imported = 0;
    $skipped = 0;
    $default_statuses = ctf_get_status_names();
    $default_status = !empty($default_statuses) ? $default_statuses[0] : 'New';
    $row_number = 1; // Start at 1 (after header)
    
    // Debug: Log mappings
    error_log('CTF Import: Mappings - ' . print_r($mappings, true));
    
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $row_number++;
        
        // Skip empty rows
        if (empty(array_filter($row, function($val) { return trim($val) !== ''; }))) {
            continue;
        }
        
        $data = [];
        foreach ($mappings as $csv_index => $field_key) {
            $csv_index = (int)$csv_index; // Ensure integer index
            if (!empty($field_key) && isset($row[$csv_index])) {
                $value = trim($row[$csv_index]);
                if ($value !== '') {
                    $data[$field_key] = $value;
                }
            }
        }
        
        // Debug first row
        if ($row_number === 2) {
            error_log('CTF Import Row 2 - Raw row: ' . print_r($row, true));
            error_log('CTF Import Row 2 - Mapped data: ' . print_r($data, true));
        }
        
        // Validate required fields
        if (empty($data['name']) || empty($data['email'])) {
            $skipped++;
            if ($row_number <= 3) {
                error_log("CTF Import Row {$row_number} skipped - Missing name or email. Data: " . print_r($data, true));
            }
            continue; // Skip invalid rows
        }
        
        if (!is_email($data['email'])) {
            $skipped++;
            if ($row_number <= 3) {
                error_log("CTF Import Row {$row_number} skipped - Invalid email: " . $data['email']);
            }
            continue; // Skip invalid email
        }
        
        // Create post
        $post_data = [
            'post_title'   => sanitize_text_field($data['name']),
            'post_content' => isset($data['message']) ? sanitize_textarea_field($data['message']) : '',
            'post_type'    => 'contact_enquiry',
            'post_status'  => 'publish'
        ];
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            $skipped++;
            $error_msg = $post_id->get_error_message();
            error_log('CTF Import Error Row ' . $row_number . ': ' . $error_msg);
            $result['errors'][] = "Row {$row_number}: " . $error_msg;
            continue;
        }
        
        if (!$post_id || $post_id <= 0) {
            $skipped++;
            error_log('CTF Import Error Row ' . $row_number . ': Post creation returned invalid ID');
            continue;
        }
        
        // Debug first successful import
        if ($imported === 0) {
            error_log('CTF Import: First post created successfully - ID: ' . $post_id);
            error_log('CTF Import: Post data - ' . print_r($post_data, true));
        }
        
        if ($post_id && $post_id > 0) {
            // Save all mapped fields
            foreach ($data as $key => $value) {
                if ($key === 'message') continue; // Already in post content
                
                switch ($key) {
                    case 'name':
                        update_post_meta($post_id, 'name', sanitize_text_field($value));
                        break;
                    case 'email':
                        update_post_meta($post_id, 'email', sanitize_email($value));
                        break;
                    case 'phone':
                        update_post_meta($post_id, 'phone', sanitize_text_field($value));
                        break;
                    case 'company_name':
                        update_post_meta($post_id, 'company_name', sanitize_text_field($value));
                        break;
                    case 'person_designation':
                        update_post_meta($post_id, 'person_designation', sanitize_text_field($value));
                        break;
                    case 'nature_of_trustee':
                        update_post_meta($post_id, 'nature_of_trustee', sanitize_text_field($value));
                        break;
                    default:
                        // Custom fields
                        $field = ctf_get_task_field($key);
                        if ($field) {
                            $field_type = $field['field_type'] ?? 'text';
                            switch ($field_type) {
                                case 'email':
                                    update_post_meta($post_id, $key, sanitize_email($value));
                                    break;
                                case 'number':
                                    update_post_meta($post_id, $key, floatval($value));
                                    break;
                                case 'url':
                                    update_post_meta($post_id, $key, esc_url_raw($value));
                                    break;
                                case 'textarea':
                                    update_post_meta($post_id, $key, sanitize_textarea_field($value));
                                    break;
                                default:
                                    update_post_meta($post_id, $key, sanitize_text_field($value));
                                    break;
                            }
                        }
                        break;
                }
            }
            
            // Set default status
            update_post_meta($post_id, 'status', $default_status);
            
            // Initialize audit log
            $log = [[
                'time' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'action' => 'imported',
                'details' => 'Imported via bulk import',
            ]];
            add_post_meta($post_id, 'ctf_change_log', $log, true);
            
            $imported++;
        }
    }
    
    fclose($handle);
    
    $result['imported'] = $imported;
    $result['skipped'] = $skipped;
    
    // Log import results for debugging
    error_log("CTF Bulk Import: Imported {$imported} enquiries, skipped {$skipped} invalid rows.");
    
    if ($imported === 0 && $skipped === 0) {
        $result['errors'][] = 'No data rows found in CSV file.';
    }
    
    return $result;
}

