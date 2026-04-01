<?php
/**
 * includes/mail_helper.php
 * Handles email notifications for the Vingo Menu platform.
 * 
 * Note: Local XAMPP environments usually require a tool like PHPMailer + SMTP 
 * to send emails. When you move this to Hostinger, the built-in mail() function 
 * will work automatically.
 */

function sendSetupEmail($to_email, $username, $token) {
    // The "From" address MUST be one associated with your domain
    $from = "sales@thevingo.com";
    $subject = "🔒 Secure Password Setup | Vingo Platform";
    
    // Auto-Detect Protocol and Host
    $proto = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host  = $_SERVER['HTTP_HOST'];
    $dir   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    
    // Link to setup-password.php at root
    $link = "$proto://$host/setup-password.php?token=$token";

    // Email Template
    $message = "
    <html>
    <head>
        <title>Vingo Account Setup</title>
    </head>
    <body style='font-family: sans-serif; line-height: 1.6; color: #333;'>
        <div style='max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);'>
            <div style='text-align: center; margin-bottom: 25px;'>
               <h2 style='color: #6366f1; margin-bottom: 5px;'>Vingo Menu Manager</h2>
               <p style='color: #64748b; font-size: 0.85rem;'>Master Console Notification</p>
            </div>
            
            <p>Hello <strong>$username</strong>,</p>
            <p>Your access account has been successfully provisioned. To ensure your account is secure, you must set a private password before you can access the dashboard.</p>
            
            <div style='background: #fdfdfd; padding: 35px; border-radius: 16px; margin: 25px 0; text-align: center; border: 1px solid #f1f5f9; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);'>
                <p style='margin-bottom: 20px; font-weight: 700; color: #0f172a; font-size: 1.1rem;'>Set Your Master Security Key</p>
                <div style='margin-bottom: 20px;'>
                    <a href='$link' style='display: inline-block; background-color: #f59e0b; color: #0f172a; padding: 16px 40px; border-radius: 12px; text-decoration: none; font-weight: 800; font-size: 1rem; border: none; letter-spacing: 0.5px; box-shadow: 0 10px 15px -3px rgba(245, 158, 11, 0.4);'>
                        🔓 SETUP ACCOUNT PASSWORD
                    </a>
                </div>
                <p style='font-size: 0.75rem; color: #94a3b8;'>Secure invitation valid for exactly <strong>24 hours</strong>.</p>
            </div>

            <p style='font-size:0.8rem; color:#64748b;'>If you did not expect this invitation, please ignore this email.</p>
            <hr style='border: 0; border-top: 1px solid #eee; margin: 25px 0;'>
            <p style='font-size: 0.75rem; color: #94a3b8; text-align: center;'>
                &copy; " . date('Y') . " Vingo.com | Automated Security System
            </p>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Vingo System <$from>" . "\r\n";

    // Send Mail and capture result
    $sent = mail($to_email, $subject, $message, $headers);
    
    // Log failure for debugging if logger is available
    if (!$sent && function_exists('platform_log')) {
        platform_log("Email Delivery Failed", "Target: $to_email", "ERROR");
    }
    
    return $sent;
}
