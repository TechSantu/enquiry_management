<?php
/**
 * Spam Protection Settings Admin Page
 */

// Add admin menu
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=contact_enquiry',
        'Spam Protection Settings',
        'Spam Protection',
        'manage_options',
        'ctf-spam-protection',
        'ctf_spam_protection_settings_page'
    );
});

// Spam protection settings page
function ctf_spam_protection_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    // Handle form submission
    if (isset($_POST['ctf_save_spam_settings']) && check_admin_referer('ctf_spam_protection_settings')) {
        // Honeypot settings
        $honeypot_enabled = isset($_POST['ctf_honeypot_enabled']) ? 1 : 0;
        update_option('ctf_honeypot_enabled', $honeypot_enabled);
        
        // CAPTCHA settings
        $captcha_enabled = isset($_POST['ctf_captcha_enabled']) ? 1 : 0;
        $captcha_type = isset($_POST['ctf_captcha_type']) ? sanitize_text_field(wp_unslash($_POST['ctf_captcha_type'])) : 'recaptcha_v2';
        $recaptcha_site_key = isset($_POST['ctf_recaptcha_site_key']) ? sanitize_text_field(wp_unslash($_POST['ctf_recaptcha_site_key'])) : '';
        $recaptcha_secret_key = isset($_POST['ctf_recaptcha_secret_key']) ? sanitize_text_field(wp_unslash($_POST['ctf_recaptcha_secret_key'])) : '';
        $recaptcha_v3_score = isset($_POST['ctf_recaptcha_v3_score']) ? floatval($_POST['ctf_recaptcha_v3_score']) : 0.5;
        
        update_option('ctf_captcha_enabled', $captcha_enabled);
        update_option('ctf_captcha_type', $captcha_type);
        update_option('ctf_recaptcha_site_key', $recaptcha_site_key);
        update_option('ctf_recaptcha_secret_key', $recaptcha_secret_key);
        update_option('ctf_recaptcha_v3_score', $recaptcha_v3_score);
        
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully.</p></div>';
    }
    
    // Get current settings
    $honeypot_enabled = get_option('ctf_honeypot_enabled', 1);
    $captcha_enabled = get_option('ctf_captcha_enabled', 0);
    $captcha_type = get_option('ctf_captcha_type', 'recaptcha_v2');
    $recaptcha_site_key = get_option('ctf_recaptcha_site_key', '');
    $recaptcha_secret_key = get_option('ctf_recaptcha_secret_key', '');
    $recaptcha_v3_score = get_option('ctf_recaptcha_v3_score', 0.5);
    
    ?>
    <div class="wrap">
        <h1>Spam Protection Settings</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('ctf_spam_protection_settings'); ?>
            <input type="hidden" name="ctf_save_spam_settings" value="1">
            
            <table class="form-table">
                <tr>
                    <th scope="row">Honeypot Protection</th>
                    <td>
                        <label>
                            <input type="checkbox" name="ctf_honeypot_enabled" value="1" <?php checked($honeypot_enabled, 1); ?> />
                            Enable honeypot field
                        </label>
                        <p class="description">A hidden field that should remain empty. Bots often fill it, allowing you to detect spam.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">CAPTCHA Protection</th>
                    <td>
                        <label>
                            <input type="checkbox" name="ctf_captcha_enabled" value="1" id="ctf_captcha_enabled" <?php checked($captcha_enabled, 1); ?> />
                            Enable CAPTCHA
                        </label>
                        <p class="description">Add CAPTCHA verification to prevent automated spam submissions.</p>
                    </td>
                </tr>
                
                <tr id="ctf_captcha_type_row" style="<?php echo $captcha_enabled ? '' : 'display:none;'; ?>">
                    <th scope="row"><label for="ctf_captcha_type">CAPTCHA Type</label></th>
                    <td>
                        <select name="ctf_captcha_type" id="ctf_captcha_type">
                            <option value="recaptcha_v2" <?php selected($captcha_type, 'recaptcha_v2'); ?>>Google reCAPTCHA v2 (Checkbox)</option>
                            <option value="recaptcha_v3" <?php selected($captcha_type, 'recaptcha_v3'); ?>>Google reCAPTCHA v3 (Invisible)</option>
                        </select>
                        <p class="description">Choose the type of CAPTCHA to use. v2 shows a checkbox, v3 is invisible and runs automatically.</p>
                    </td>
                </tr>
                
                <tr id="ctf_recaptcha_settings_row" style="<?php echo ($captcha_enabled && in_array($captcha_type, ['recaptcha_v2', 'recaptcha_v3'])) ? '' : 'display:none;'; ?>">
                    <th scope="row" colspan="2">
                        <h2 style="margin-top: 20px;">Google reCAPTCHA Settings</h2>
                        <p class="description">Get your API keys from <a href="https://www.google.com/recaptcha/admin" target="_blank">Google reCAPTCHA Admin</a></p>
                        <p class="description"><strong>Note:</strong> v2 and v3 use different keys. Make sure to register separate sites for each version.</p>
                    </th>
                </tr>
                
                <tr id="ctf_recaptcha_site_key_row" style="<?php echo ($captcha_enabled && in_array($captcha_type, ['recaptcha_v2', 'recaptcha_v3'])) ? '' : 'display:none;'; ?>">
                    <th scope="row"><label for="ctf_recaptcha_site_key">Site Key</label></th>
                    <td>
                        <input type="text" name="ctf_recaptcha_site_key" id="ctf_recaptcha_site_key" value="<?php echo esc_attr($recaptcha_site_key); ?>" class="regular-text" />
                        <p class="description">Your reCAPTCHA site key (public key).</p>
                    </td>
                </tr>
                
                <tr id="ctf_recaptcha_secret_key_row" style="<?php echo ($captcha_enabled && in_array($captcha_type, ['recaptcha_v2', 'recaptcha_v3'])) ? '' : 'display:none;'; ?>">
                    <th scope="row"><label for="ctf_recaptcha_secret_key">Secret Key</label></th>
                    <td>
                        <input type="text" name="ctf_recaptcha_secret_key" id="ctf_recaptcha_secret_key" value="<?php echo esc_attr($recaptcha_secret_key); ?>" class="regular-text" />
                        <p class="description">Your reCAPTCHA secret key (private key). Keep this secure.</p>
                    </td>
                </tr>
                
                <tr id="ctf_recaptcha_v3_score_row" style="<?php echo ($captcha_enabled && $captcha_type === 'recaptcha_v3') ? '' : 'display:none;'; ?>">
                    <th scope="row"><label for="ctf_recaptcha_v3_score">Minimum Score</label></th>
                    <td>
                        <input type="number" name="ctf_recaptcha_v3_score" id="ctf_recaptcha_v3_score" value="<?php echo esc_attr(get_option('ctf_recaptcha_v3_score', 0.5)); ?>" min="0" max="1" step="0.1" class="small-text" />
                        <p class="description">reCAPTCHA v3 returns a score from 0.0 (bot) to 1.0 (human). Submissions below this score will be rejected. Recommended: 0.5</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="Save Settings" />
            </p>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#ctf_captcha_enabled').on('change', function() {
            var enabled = $(this).is(':checked');
            $('#ctf_captcha_type_row').toggle(enabled);
            updateCaptchaTypeRows();
        });
        
        $('#ctf_captcha_type').on('change', function() {
            updateCaptchaTypeRows();
        });
        
        function updateCaptchaTypeRows() {
            var enabled = $('#ctf_captcha_enabled').is(':checked');
            var type = $('#ctf_captcha_type').val();
            
            if (enabled && (type === 'recaptcha_v2' || type === 'recaptcha_v3')) {
                $('#ctf_recaptcha_settings_row, #ctf_recaptcha_site_key_row, #ctf_recaptcha_secret_key_row').show();
                if (type === 'recaptcha_v3') {
                    $('#ctf_recaptcha_v3_score_row').show();
                } else {
                    $('#ctf_recaptcha_v3_score_row').hide();
                }
            } else {
                $('#ctf_recaptcha_settings_row, #ctf_recaptcha_site_key_row, #ctf_recaptcha_secret_key_row, #ctf_recaptcha_v3_score_row').hide();
            }
        }
        
        // Initialize on page load
        updateCaptchaTypeRows();
    });
    </script>
    <?php
}

// Helper functions to get settings
function ctf_is_honeypot_enabled() {
    return (bool) get_option('ctf_honeypot_enabled', 1);
}

function ctf_is_captcha_enabled() {
    return (bool) get_option('ctf_captcha_enabled', 0);
}

function ctf_get_captcha_type() {
    return get_option('ctf_captcha_type', 'recaptcha_v2');
}

function ctf_get_recaptcha_site_key() {
    return get_option('ctf_recaptcha_site_key', '');
}

function ctf_get_recaptcha_secret_key() {
    return get_option('ctf_recaptcha_secret_key', '');
}

function ctf_get_recaptcha_v3_score() {
    return floatval(get_option('ctf_recaptcha_v3_score', 0.5));
}

