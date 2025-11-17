<?php
add_shortcode('contact_task_form', function() {
    // Get spam protection settings
    $honeypot_enabled = ctf_is_honeypot_enabled();
    $captcha_enabled = ctf_is_captcha_enabled();
    $captcha_type = ctf_get_captcha_type();
    $recaptcha_site_key = ctf_get_recaptcha_site_key();
    
    ob_start(); ?>
    <form id="ctfForm" class="contact-task-form">
        <?php wp_nonce_field('ctf_form_nonce', 'security'); ?>
        <input type="text" name="name" placeholder="Name *" required>
        <input type="text" name="company_name" placeholder="Company Name">
        <input type="text" name="person_designation" placeholder="Designation">
        <div class="two-col">
            <input type="text" name="phone" placeholder="Phone Number *" required>
            <input type="email" name="email" placeholder="Email Address *" required>
        </div>
        <input type="text" name="nature_of_trustee" placeholder="Nature of Trustee">
        <textarea name="message" placeholder="Enquiry *" required></textarea>
        
        <?php if ($honeypot_enabled) : ?>
            <!-- Honeypot field - should remain empty -->
            <input type="text" name="website_url" id="ctf_website_url" style="position: absolute; left: -9999px; width: 1px; height: 1px; opacity: 0; pointer-events: none;" tabindex="-1" autocomplete="off" aria-hidden="true" />
        <?php endif; ?>
        
        <?php if ($captcha_enabled && $captcha_type === 'recaptcha_v2' && !empty($recaptcha_site_key)) : ?>
            <div class="ctf-recaptcha-container">
                <div class="g-recaptcha" data-sitekey="<?php echo esc_attr($recaptcha_site_key); ?>"></div>
            </div>
        <?php endif; ?>
        
        <?php if ($captcha_enabled && $captcha_type === 'recaptcha_v3' && !empty($recaptcha_site_key)) : ?>
            <input type="hidden" name="ctf_recaptcha_v3_token" id="ctf_recaptcha_v3_token" />
            <script>
            // reCAPTCHA v3 will be executed on form submit via JavaScript
            </script>
        <?php endif; ?>
        
        <button type="submit">Submit</button>
        <div class="ctf-response"></div>
    </form>
    <?php return ob_get_clean();
});
