<?php

function ctf_build_email_html($title, $intro_html, $content_rows = [], $cta = []) {
    // $content_rows: array of ['label' => 'Email', 'value' => 'example@...']
    // $cta: ['text' => 'View Enquiry', 'url' => 'https://...']
    $primary = '#0ea5e9'; // sky-500
    $accent  = '#f97316'; // orange-500
    $border  = '#e5e7eb'; // gray-200
    $text    = '#111827'; // gray-900
    $muted   = '#6b7280'; // gray-500
    $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    $year = date('Y');

    ob_start(); ?>
    <div style="background:#f8fafc;padding:24px 0;">
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="center">
                    <table role="presentation" cellpadding="0" cellspacing="0" width="640" style="max-width:640px;background:#ffffff;border:1px solid <?php echo esc_attr($border); ?>;border-radius:12px;overflow:hidden">
                        <tr>
                            <td style="padding:24px 24px 0 24px;background:linear-gradient(90deg, <?php echo esc_attr($primary); ?>, <?php echo esc_attr($accent); ?>);color:#fff;">
                                <h1 style="margin:0;font-size:20px;font-weight:700;"><?php echo esc_html($site_name); ?></h1>
                                <p style="margin:6px 0 0 0;font-size:14px;opacity:.9;"><?php echo esc_html($title); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px;color:<?php echo esc_attr($text); ?>;">
                                <div style="font-size:14px;line-height:1.6;"><?php echo $intro_html; // already escaped by caller ?></div>
                                <?php if (!empty($content_rows)) : ?>
                                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin-top:16px;border-collapse:separate;border-spacing:0 8px;">
                                    <?php foreach ($content_rows as $row) : ?>
                                        <tr>
                                            <td style="width:180px;color:<?php echo esc_attr($muted); ?>;font-size:12px;text-transform:uppercase;letter-spacing:.04em;"><?php echo esc_html($row['label']); ?></td>
                                            <td style="font-size:14px;color:<?php echo esc_attr($text); ?>;"><?php echo wp_kses_post($row['value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                                <?php endif; ?>

                                <?php if (!empty($cta['url']) && !empty($cta['text'])) : ?>
                                <div style="margin-top:24px;">
                                    <a href="<?php echo esc_url($cta['url']); ?>" style="display:inline-block;background:<?php echo esc_attr($accent); ?>;color:#fff;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:600;">
                                        <?php echo esc_html($cta['text']); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:16px 24px;background:#f9fafb;color:#6b7280;font-size:12px;text-align:center;border-top:1px solid <?php echo esc_attr($border); ?>;">© <?php echo esc_html($year); ?> <?php echo esc_html($site_name); ?>. All rights reserved.</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    <?php return ob_get_clean();
}

function ctf_send_admin_email($post_id, $name, $email, $message) {
    $admin_email = get_option('admin_email');
    $subject = "New Enquiry from $name";
    $phone = get_post_meta($post_id, 'phone', true);
    $company_name = get_post_meta($post_id, 'company_name', true);
    $person_designation = get_post_meta($post_id, 'person_designation', true);
    $nature_of_trustee = get_post_meta($post_id, 'nature_of_trustee', true);
    $dashboard_url = admin_url("post.php?post=$post_id&action=edit");

    $intro = wp_kses_post('<p>You have received a new enquiry. Details are below.</p>');
    $rows = [
        ['label' => 'Name', 'value' => esc_html($name)],
        ['label' => 'Company Name', 'value' => esc_html($company_name ?: 'N/A')],
        ['label' => 'Designation', 'value' => esc_html($person_designation ?: 'N/A')],
        ['label' => 'Email', 'value' => '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>'],
        ['label' => 'Phone', 'value' => esc_html($phone ?: 'N/A')],
        ['label' => 'Nature of Trustee', 'value' => esc_html($nature_of_trustee ?: 'N/A')],
        ['label' => 'Message', 'value' => nl2br(esc_html($message ?: '—'))],
    ];
    $html = ctf_build_email_html('New Enquiry', $intro, $rows, [
        'text' => 'View in Dashboard',
        'url'  => $dashboard_url,
    ]);

    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
    wp_mail($admin_email, $subject, $html, $headers);
}

function ctf_send_user_email($email, $name) {
    $subject = 'We received your enquiry!';
    $contact_url = 'https://iposamadhan.com/contact-us/';
    $intro = wp_kses_post('<p>Hi ' . esc_html($name) . ',</p><p>Thank you for contacting us. Our team will respond shortly. If you need to share more details, you can reply to this email or use our contact page.</p>');
    $html = ctf_build_email_html('Thank you for reaching out', $intro, [], [
        'text' => 'Visit Contact Page',
        'url'  => $contact_url,
    ]);
    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
    wp_mail($email, $subject, $html, $headers);
}

function ctf_send_comment_alert($post_id, $comment_text, $author_id) {
    $assignee_id = get_post_meta($post_id, 'assigned_to', true);
    $assignee_email = $assignee_id ? (get_userdata($assignee_id)->user_email ?? '') : '';
    $admin_email = get_option('admin_email');
    $post = get_post($post_id);
    $author = get_userdata($author_id);
    $author_name = $author ? $author->display_name : 'System';
    $subject = 'New internal comment on Enquiry: ' . $post->post_title;
    $dashboard_url = admin_url("post.php?post=$post_id&action=edit");

    $intro = wp_kses_post('<p><strong>' . esc_html($author_name) . '</strong> added a new internal comment.</p>');
    $rows = [
        ['label' => 'Enquiry', 'value' => esc_html($post->post_title)],
        ['label' => 'Comment', 'value' => nl2br(esc_html($comment_text))],
    ];
    $html = ctf_build_email_html('Internal Comment Added', $intro, $rows, [
        'text' => 'Open Enquiry',
        'url'  => $dashboard_url,
    ]);
    $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

    // Send to assignee if available, else only admin
    $recipients = [];
    if (!empty($assignee_email)) { $recipients[] = $assignee_email; }
    if ($admin_email && (!empty($assignee_email) ? strtolower($admin_email) !== strtolower($assignee_email) : true)) {
        $recipients[] = $admin_email;
    }
    foreach ($recipients as $to) {
        wp_mail($to, $subject, $html, $headers);
    }
}
