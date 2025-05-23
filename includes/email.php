<?php
/**
 * Email Helper Functions
 * File: includes/email.php
 */

// Correct paths to PHPMailer files in MAMP's root directory
require_once '/Applications/MAMP/htdocs/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once '/Applications/MAMP/htdocs/vendor/phpmailer/phpmailer/src/SMTP.php';
require_once '/Applications/MAMP/htdocs/vendor/phpmailer/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email using PHPMailer
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML)
 * @param array $attachments Optional array of file paths to attach
 * @return bool True if email was sent successfully, false otherwise
 */
function sendEmail($to, $subject, $body, $attachments = []) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port = MAIL_PORT;

        // Recipients
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($to);

        // Add attachments if any
        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body); // Plain text version for non-HTML mail clients

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send a notification email
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Notification message
 * @param string $actionUrl Optional URL for action button
 * @param string $actionText Optional text for action button
 * @return bool True if email was sent successfully, false otherwise
 */
function sendNotificationEmail($to, $subject, $message, $actionUrl = '', $actionText = '') {
    // Create HTML template
    $html = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #333;">' . htmlspecialchars($subject) . '</h2>
        <p style="color: #666; line-height: 1.6;">' . nl2br(htmlspecialchars($message)) . '</p>';
    
    if ($actionUrl && $actionText) {
        $html .= '
        <div style="margin: 30px 0;">
            <a href="' . htmlspecialchars($actionUrl) . '" 
               style="background-color: #4CAF50; color: white; padding: 12px 24px; 
                      text-decoration: none; border-radius: 4px; display: inline-block;">
                ' . htmlspecialchars($actionText) . '
            </a>
        </div>';
    }
    
    $html .= '
        <p style="color: #999; font-size: 12px; margin-top: 30px;">
            This is an automated message from ' . htmlspecialchars(APP_NAME) . '. 
            Please do not reply to this email.
        </p>
    </div>';

    return sendEmail($to, $subject, $html);
} 