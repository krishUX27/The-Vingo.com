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

    // Headers to help avoid Spam folder
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Vingo System <$from>" . "\r\n";
    $headers .= "Reply-To: $from" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 1 (Highest)" . "\r\n";
    $headers .= "Importance: High" . "\r\n";

    // Email Template (with Bulletproof button for mobile)
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Account Setup</title>
    </head>
    <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8fafc;'>
        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #f8fafc; padding: 20px 0;'>
            <tr>
                <td align='center'>
                    <div style='max-width: 600px; background-color: #ffffff; border-radius: 16px; padding: 40px; border: 1px solid #edf2f7; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);'>
                        <div style='text-align: center; margin-bottom: 30px;'>
                           <h2 style='color: #4f46e5; margin: 0;'>Vingo Menu</h2>
                           <p style='color: #718096; font-size: 14px; margin-top: 5px;'>Secure Platform Invitation</p>
                        </div>
                        
                        <p style='color: #2d3748; font-size: 16px; line-height: 24px;'>Hello <strong>$username</strong>,</p>
                        <p style='color: #4a5568; font-size: 16px; line-height: 24px; margin-bottom: 30px;'>
                            Your master access account has been successfully provisioned. To protect your dashboard, please click the secure link below to set your account password.
                        </p>
                        
                        <!-- Bulletproof Button -->
                        <table border='0' cellpadding='0' cellspacing='0' style='margin: 30px auto;'>
                            <tr>
                                <td align='center' style='border-radius: 12px;' bgcolor='#f59e0b'>
                                    <a href='$link' target='_blank' style='font-size: 16px; font-weight: bold; color: #000000; text-decoration: none; padding: 18px 45px; border-radius: 12px; border: 1px solid #f59e0b; display: block; background-color: #f59e0b; letter-spacing: 0.5px;'>
                                        SET PASSWORD NOW
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <p style='font-size: 12px; color: #a0aec0; text-align: center; margin-top: 30px;'>
                            This security link expires in <strong>24 hours</strong>. If you did not expect this invitation, please ignore this email.
                        </p>
                        
                        <hr style='border: 0; border-top: 1px solid #edf2f7; margin: 30px 0;'>
                        
                        <p style='font-size: 12px; color: #a0aec0; text-align: center; margin: 0;'>
                            &copy; " . date('Y') . " Vingo.com | Automated Delivery System
                        </p>
                    </div>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";

    // Send Mail
    $sent = mail($to_email, $subject, $message, $headers);
    
    if (!$sent && function_exists('platform_log')) {
        platform_log("Email Delivery Failed", "Target: $to_email", "ERROR");
    }
    
    return $sent;
}

/**
 * Sends a notification email when an account is placed on hold.
 */
function sendHoldEmail($to_email, $username) {
    $from = "sales@thevingo.com";
    $subject = "Vingo Service Temporarily On Hold";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Vingo System <$from>" . "\r\n";
    $headers .= "Reply-To: $from" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Service On Hold</title>
    </head>
    <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8fafc;'>
        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #f8fafc; padding: 40px 0;'>
            <tr>
                <td align='center'>
                    <div style='max-width: 600px; background-color: #ffffff; border-radius: 16px; padding: 40px; border: 1px solid #edf2f7; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);'>
                        <div style='text-align: center; margin-bottom: 30px;'>
                           <h2 style='color: #dc2626; margin: 0;'>Vingo Menu</h2>
                           <p style='color: #718096; font-size: 14px; margin-top: 5px;'>Service Notification</p>
                        </div>
                        
                        <p style='color: #2d3748; font-size: 16px; line-height: 24px;'>Hello <strong>$username</strong>,</p>
                        <p style='color: #4a5568; font-size: 16px; line-height: 24px; margin-bottom: 20px;'>
                            Your Vingo service has been temporarily placed on hold because the payment has not been completed.
                        </p>
                        <p style='color: #4a5568; font-size: 16px; line-height: 24px; margin-bottom: 20px;'>
                            Please complete the payment to restore access to your account and continue using the Vingo platform.
                        </p>
                        <p style='color: #718096; font-size: 14px; line-height: 22px;'>
                            If you believe this is a mistake, please contact support.
                        </p>
                        
                        <hr style='border: 0; border-top: 1px solid #edf2f7; margin: 30px 0;'>
                        
                        <p style='font-size: 12px; color: #a0aec0; text-align: center; margin: 0;'>
                            &copy; " . date('Y') . " Vingo.com | Support Team
                        </p>
                    </div>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";

    return mail($to_email, $subject, $message, $headers);
}

/**
 * Sends a 6-digit OTP for password reset.
 */
function sendOTPEmail($to_email, $username, $otp) {
    $from = "sales@thevingo.com";
    $subject = "🔑 Your Password Reset OTP: $otp";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Vingo Security <$from>" . "\r\n";
    $headers .= "Reply-To: $from" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";

    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Password Reset OTP</title>
    </head>
    <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f8fafc;'>
        <table border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #f8fafc; padding: 40px 0;'>
            <tr>
                <td align='center'>
                    <div style='max-width: 500px; background-color: #ffffff; border-radius: 16px; padding: 40px; border: 1px solid #edf2f7; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);'>
                        <div style='text-align: center; margin-bottom: 30px;'>
                           <h2 style='color: #4f46e5; margin: 0;'>Vingo Menu</h2>
                           <p style='color: #718096; font-size: 14px; margin-top: 5px;'>Security Verification</p>
                        </div>
                        
                        <p style='color: #2d3748; font-size: 16px; line-height: 24px;'>Hello <strong>$username</strong>,</p>
                        <p style='color: #4a5568; font-size: 16px; line-height: 24px; margin-bottom: 25px;'>
                            You requested a password reset. Use the verification code below to proceed:
                        </p>
                        
                        <div style='background-color: #f1f5f9; border-radius: 12px; padding: 25px; text-align: center; margin-bottom: 25px;'>
                            <span style='font-size: 32px; font-weight: 800; letter-spacing: 10px; color: #1e293b;'>$otp</span>
                        </div>
                        
                        <p style='color: #ef4444; font-size: 13px; font-weight: 600; text-align: center; margin-bottom: 30px;'>
                            ⚠️ This code is valid for 5 minutes only.
                        </p>
                        
                        <p style='color: #718096; font-size: 13px; line-height: 20px; text-align: center;'>
                            If you didn't request this, you can safely ignore this email. Your password will remain unchanged.
                        </p>
                        
                        <hr style='border: 0; border-top: 1px solid #edf2f7; margin: 30px 0;'>
                        
                        <p style='font-size: 11px; color: #a0aec0; text-align: center; margin: 0;'>
                            &copy; " . date('Y') . " Vingo.com | Security Team
                        </p>
                    </div>
                </td>
            </tr>
        </table>
    </body>
    </html>
    ";

    return mail($to_email, $subject, $message, $headers);
}

