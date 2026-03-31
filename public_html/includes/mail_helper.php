<?php
/**
 * includes/mail_helper.php
 * Handles email notifications for the Vingo Menu platform.
 * 
 * Note: Local XAMPP environments usually require a tool like PHPMailer + SMTP 
 * to send emails. When you move this to Hostinger, the built-in mail() function 
 * will work automatically.
 */

function sendSetupEmail($to_email, $username) {
    // The "From" address MUST be one associated with your domain (e.g., sales@thevingo.com)
    $from = "sales@thevingo.com";
    $subject = "Account Provisioned | Vingo Master Console";
    
    // Email Template
    $message = "
    <html>
    <head>
        <title>Vingo Account Created</title>
    </head>
    <body style='font-family: sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 10px; padding: 30px;'>
            <h2 style='color: #6366f1;'>Vingo Menu Manager</h2>
            <p>Hello <strong>$username</strong>,</p>
            <p>Your access account has been successfully created by the Platform Superadmin.</p>
            <p style='background: #f8fafc; padding: 15px; border-radius: 8px;'>
                <strong>Your Login Username:</strong> $username<br>
                <em>Please contact your administrator for your initial security key.</em>
            </p>
            <p>For security reasons, once you log in, we recommend <strong>changing your password</strong> immediately from your profile settings.</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 25px 0;'>
            <p style='font-size: 0.8rem; color: #64748b; text-align: center;'>
                &copy; " . date('Y') . " Vingo.com | System Notification
            </p>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Vingo System <$from>" . "\r\n";

    // On local XAMPP, this will likely return false unless you have an SMTP server configured.
    // On Hostinger, this will return true.
    return @mail($to_email, $subject, $message, $headers);
}
