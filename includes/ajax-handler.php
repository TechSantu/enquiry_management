<?php
add_action('wp_ajax_ctf_submit_form', 'ctf_handle_ajax');
add_action('wp_ajax_nopriv_ctf_submit_form', 'ctf_handle_ajax');

function ctf_handle_ajax() {
    check_ajax_referer('ctf_form_nonce', 'security');

    // Spam protection checks
    $honeypot_enabled = ctf_is_honeypot_enabled();
    $captcha_enabled = ctf_is_captcha_enabled();
    $captcha_type = ctf_get_captcha_type();
    
    // Honeypot check
    if ($honeypot_enabled) {
        $honeypot_value = isset($_POST['website_url']) ? trim(wp_unslash($_POST['website_url'])) : '';
        if (!empty($honeypot_value)) {
            // Honeypot was filled - likely spam
            wp_send_json_error(['message' => 'Spam detected. Please try again.']);
        }
    }
    
    // CAPTCHA validation
    if ($captcha_enabled) {
        $recaptcha_secret = ctf_get_recaptcha_secret_key();
        
        if (empty($recaptcha_secret)) {
            wp_send_json_error(['message' => 'CAPTCHA configuration error. Please contact the administrator.']);
        }
        
        if ($captcha_type === 'recaptcha_v2') {
            $recaptcha_response = isset($_POST['g-recaptcha-response']) ? sanitize_text_field(wp_unslash($_POST['g-recaptcha-response'])) : '';
            
            if (empty($recaptcha_response)) {
                wp_send_json_error(['message' => 'Please complete the CAPTCHA verification.']);
            }
            
            // Verify reCAPTCHA v2 with Google
            $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
            $verify_response = wp_remote_post($verify_url, [
                'body' => [
                    'secret' => $recaptcha_secret,
                    'response' => $recaptcha_response,
                    'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : ''
                ]
            ]);
            
            if (is_wp_error($verify_response)) {
                wp_send_json_error(['message' => 'CAPTCHA verification failed. Please try again.']);
            }
            
            $verify_result = json_decode(wp_remote_retrieve_body($verify_response), true);
            if (!isset($verify_result['success']) || !$verify_result['success']) {
                wp_send_json_error(['message' => 'CAPTCHA verification failed. Please try again.']);
            }
        } elseif ($captcha_type === 'recaptcha_v3') {
            $recaptcha_token = isset($_POST['ctf_recaptcha_v3_token']) ? sanitize_text_field(wp_unslash($_POST['ctf_recaptcha_v3_token'])) : '';
            
            if (empty($recaptcha_token)) {
                wp_send_json_error(['message' => 'CAPTCHA verification failed. Please refresh the page and try again.']);
            }
            
            // Verify reCAPTCHA v3 with Google
            $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
            $verify_response = wp_remote_post($verify_url, [
                'body' => [
                    'secret' => $recaptcha_secret,
                    'response' => $recaptcha_token,
                    'remoteip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : ''
                ]
            ]);
            
            if (is_wp_error($verify_response)) {
                wp_send_json_error(['message' => 'CAPTCHA verification failed. Please try again.']);
            }
            
            $verify_result = json_decode(wp_remote_retrieve_body($verify_response), true);
            if (!isset($verify_result['success']) || !$verify_result['success']) {
                wp_send_json_error(['message' => 'CAPTCHA verification failed. Please try again.']);
            }
            
            // Check score for v3 (0.0 = bot, 1.0 = human)
            $min_score = ctf_get_recaptcha_v3_score();
            $score = isset($verify_result['score']) ? floatval($verify_result['score']) : 0;
            
            if ($score < $min_score) {
                wp_send_json_error(['message' => 'CAPTCHA verification failed. Your request appears to be automated.']);
            }
        }
    }

    // Safely read and sanitize inputs
    $raw_name    = isset($_POST['name']) ? wp_unslash($_POST['name']) : '';
    $raw_company_name = isset($_POST['company_name']) ? wp_unslash($_POST['company_name']) : '';
    $raw_person_designation = isset($_POST['person_designation']) ? wp_unslash($_POST['person_designation']) : '';
    $raw_phone   = isset($_POST['phone']) ? wp_unslash($_POST['phone']) : '';
    $raw_email   = isset($_POST['email']) ? wp_unslash($_POST['email']) : '';
    $raw_nature_of_trustee = isset($_POST['nature_of_trustee']) ? wp_unslash($_POST['nature_of_trustee']) : '';
    $raw_message = isset($_POST['message']) ? wp_unslash($_POST['message']) : '';

    $name    = sanitize_text_field($raw_name);
    $company_name = sanitize_text_field($raw_company_name);
    $person_designation = sanitize_text_field($raw_person_designation);
    $phone   = sanitize_text_field($raw_phone);
    $email   = sanitize_email($raw_email);
    $nature_of_trustee = sanitize_text_field($raw_nature_of_trustee);
    $message = sanitize_textarea_field($raw_message);

    // Basic validation
    if ($name === '' || $email === '') {
        wp_send_json_error(['message' => 'Please fill in your name and email.']);
    }
    if (!is_email($email)) {
        wp_send_json_error(['message' => 'Please enter a valid email address.']);
    }
    // Field length validations
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        wp_send_json_error(['message' => 'Name must be between 2 and 100 characters.']);
    }
    if ($company_name !== '' && (mb_strlen($company_name) < 2 || mb_strlen($company_name) > 200)) {
        wp_send_json_error(['message' => 'Company name must be between 2 and 200 characters.']);
    }
    if ($person_designation !== '' && (mb_strlen($person_designation) < 2 || mb_strlen($person_designation) > 100)) {
        wp_send_json_error(['message' => 'Person designation must be between 2 and 100 characters.']);
    }
    if ($nature_of_trustee !== '' && (mb_strlen($nature_of_trustee) < 2 || mb_strlen($nature_of_trustee) > 100)) {
        wp_send_json_error(['message' => 'Nature of trustee must be between 2 and 100 characters.']);
    }
    if ($message !== '' && mb_strlen($message) > 2000) {
        wp_send_json_error(['message' => 'Message must be 2000 characters or fewer.']);
    }
    // Phone: allow digits, spaces, +, -, () and require 7-15 digits if provided
    $digits_only = preg_replace('/\D+/', '', $phone);
    if ($phone !== '' && (strlen($digits_only) < 7 || strlen($digits_only) > 15)) {
        wp_send_json_error(['message' => 'Please enter a valid phone number or leave it empty.']);
    }

    // Simple IP-based rate limit: 1 submission per 30 seconds
    $client_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    if ($client_ip) {
        $rate_key = 'ctf_rate_' . md5($client_ip);
        if (get_transient($rate_key)) {
            wp_send_json_error(['message' => 'Please wait a few seconds before submitting again.']);
        }
    }

    $post_id = wp_insert_post([
        'post_title'   => $name,
        'post_content' => $message,
        'post_type'    => 'contact_enquiry',
        'post_status'  => 'publish'
    ]);

    if ($post_id && !is_wp_error($post_id)) {
        // Store all form data
        update_post_meta($post_id, 'name', $name);
        update_post_meta($post_id, 'email', $email);
        update_post_meta($post_id, 'phone', $phone);
        update_post_meta($post_id, 'company_name', $company_name);
        update_post_meta($post_id, 'person_designation', $person_designation);
        update_post_meta($post_id, 'nature_of_trustee', $nature_of_trustee);
        update_post_meta($post_id, 'message', $message);
        // Set default status
        $default_statuses = ctf_get_status_names();
        $default_status = !empty($default_statuses) ? $default_statuses[0] : 'New';
        update_post_meta($post_id, 'status', $default_status);
        // Store extra request context
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $ref = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        update_post_meta($post_id, 'submit_ip', $ip);
        update_post_meta($post_id, 'submit_user_agent', $ua);
        update_post_meta($post_id, 'submit_referrer', $ref);

        // Initialize audit log
        $log = [
            [
                'time' => current_time('mysql'),
                'user_id' => 0,
                'action' => 'created',
                'details' => sprintf('Submission by %s <%s> from %s', $name, $email, $ip ?: 'unknown IP'),
            ]
        ];
        add_post_meta($post_id, 'ctf_change_log', $log, true);

        ctf_send_admin_email($post_id, $name, $email, $message);
        ctf_send_user_email($email, $name);

        // Set rate limit after successful submit
        if (!empty($client_ip)) {
            set_transient($rate_key, 1, 30);
        }

        wp_send_json_success(['message' => 'Thank you! We’ll get back to you soon.']);
    } else {
        $error_msg = is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown error occurred.';
        wp_send_json_error(['message' => 'Something went wrong. Try again. ' . $error_msg]);
    }
}
