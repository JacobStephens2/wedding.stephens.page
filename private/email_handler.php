<?php
/**
 * Email handler using PHPMailer with Mandrill SMTP
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendEmail($to, $subject, $body, $replyTo = null) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $_ENV['MANDRILL_SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['MANDRILL_SMTP_USER'];
        $mail->Password = $_ENV['MANDRILL_SMTP_PASS'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = intval($_ENV['MANDRILL_SMTP_PORT'] ?? 587);
        
        // Recipients
        // Use FROM_EMAIL if set, otherwise use RSVP_EMAIL, otherwise use a default
        $fromEmail = $_ENV['FROM_EMAIL'] ?? $_ENV['RSVP_EMAIL'] ?? 'noreply@wedding.stephens.page';
        $mail->setFrom($fromEmail, 'Wedding Website');
        $mail->addAddress($to);
        
        if ($replyTo) {
            $mail->addReplyTo($replyTo);
        }
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log both the exception message and PHPMailer error info if available
        $errorMsg = $e->getMessage();
        if (isset($mail) && !empty($mail->ErrorInfo)) {
            $errorMsg .= " | PHPMailer Error: " . $mail->ErrorInfo;
        }
        error_log("Email sending failed: " . $errorMsg);
        return false;
    }
}

