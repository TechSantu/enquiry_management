jQuery(document).ready(function($){
    function isValidEmail(email){
        // Simple RFC 5322-inspired pattern
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    }
    function inRangeLen(str, min, max){
        var len = (str || '').length;
        return len >= min && len <= max;
    }
    function validPhone(phone){
        if(!phone) return true;
        var digits = (phone.match(/\d/g) || []).length;
        return digits >= 7 && digits <= 15;
    }

    $('#ctfForm').on('submit', function(e){
        e.preventDefault();
        var form = $(this);
        var $btn = form.find('button[type="submit"]');
        var $resp = form.find('.ctf-response');

        // Basic client-side validation
        var name = $.trim(form.find('input[name="name"]').val());
        var companyName = $.trim(form.find('input[name="company_name"]').val());
        var personDesignation = $.trim(form.find('input[name="person_designation"]').val());
        var email = $.trim(form.find('input[name="email"]').val());
        var phone = $.trim(form.find('input[name="phone"]').val());
        var natureOfTrustee = $.trim(form.find('input[name="nature_of_trustee"]').val());
        var message = $.trim(form.find('textarea[name="message"]').val());
        if(!name || !email){
            $resp.html('<p class="error-msg">Please fill in your name and email.</p>');
            return;
        }
        if(!inRangeLen(name, 2, 100)){
            $resp.html('<p class="error-msg">Name must be between 2 and 100 characters.</p>');
            return;
        }
        if(companyName && !inRangeLen(companyName, 2, 200)){
            $resp.html('<p class="error-msg">Company name must be between 2 and 200 characters.</p>');
            return;
        }
        if(personDesignation && !inRangeLen(personDesignation, 2, 100)){
            $resp.html('<p class="error-msg">Person designation must be between 2 and 100 characters.</p>');
            return;
        }
        if(natureOfTrustee && !inRangeLen(natureOfTrustee, 2, 100)){
            $resp.html('<p class="error-msg">Nature of trustee must be between 2 and 100 characters.</p>');
            return;
        }
        if(!isValidEmail(email)){
            $resp.html('<p class="error-msg">Please enter a valid email address.</p>');
            return;
        }
        if(message && message.length > 2000){
            $resp.html('<p class="error-msg">Message must be 2000 characters or fewer.</p>');
            return;
        }
        if(!validPhone(phone)){
            $resp.html('<p class="error-msg">Please enter a valid phone number or leave it empty.</p>');
            return;
        }
        
        // Check reCAPTCHA v2 if present
        var recaptchaResponse = form.find('textarea[name="g-recaptcha-response"]').val() || form.find('[name="g-recaptcha-response"]').val();
        if (form.find('.g-recaptcha').length > 0 && (!recaptchaResponse || recaptchaResponse === '')) {
            $resp.html('<p class="error-msg">Please complete the CAPTCHA verification.</p>');
            return;
        }
        
        // Handle reCAPTCHA v3
        var recaptchaV3Token = form.find('#ctf_recaptcha_v3_token');
        var recaptchaV3SiteKey = '';
        if (recaptchaV3Token.length > 0) {
            // Get site key from script tag
            var recaptchaScript = $('script[src*="recaptcha/api.js?render="]');
            if (recaptchaScript.length > 0) {
                var scriptSrc = recaptchaScript.attr('src');
                var match = scriptSrc.match(/render=([^&]+)/);
                if (match && match[1]) {
                    recaptchaV3SiteKey = match[1];
                }
            }
        }

        var originalText = $btn.text();
        $btn.prop('disabled', true).addClass('is-loading').text('Sending...');
        $resp.html('');

        // Execute reCAPTCHA v3 before submitting
        if (recaptchaV3Token.length > 0 && recaptchaV3SiteKey && typeof grecaptcha !== 'undefined') {
            grecaptcha.ready(function() {
                grecaptcha.execute(recaptchaV3SiteKey, {action: 'submit'}).then(function(token) {
                    recaptchaV3Token.val(token);
                    submitForm();
                });
            });
            return; // Don't submit yet, wait for token
        }
        
        submitForm();
        
        function submitForm() {
            var data = form.serialize() + '&action=ctf_submit_form';
        if (typeof ctf_ajax !== 'undefined' && ctf_ajax.nonce) {
            // If nonce field wasn't present in the form (e.g., custom renders), include it
            if (form.find('input[name="security"]').length === 0) {
                data += '&security=' + encodeURIComponent(ctf_ajax.nonce);
            }
        }

            $.post(ctf_ajax.ajax_url, data)
                .done(function(response){
                    if(response && response.success){
                        $resp.html('<p class="success-msg">'+response.data.message+'</p>');
                        form[0].reset();
                        // Reset reCAPTCHA v3 token
                        if (recaptchaV3Token.length > 0) {
                            recaptchaV3Token.val('');
                        }
                    } else {
                        var msg = (response && response.data && response.data.message) ? response.data.message : 'Something went wrong. Try again.';
                        $resp.html('<p class="error-msg">'+msg+'</p>');
                    }
                    window.scrollTo({ top: form.offset().top - 40, behavior: 'smooth' });
                })
                .fail(function(){
                    $resp.html('<p class="error-msg">Network error. Please try again.</p>');
                })
                .always(function(){
                    $btn.prop('disabled', false).removeClass('is-loading').text(originalText);
                });
        }
    });
});
