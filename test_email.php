<?php
require_once 'config/email_helper.php';

$result = sendEmail(
    'aqlannaqib6@gmail.com',   // change to your email
    'Test User',
    'Test Email — UKHSA Portal',
    '<h1 style="color:#1D70B8;">It works!</h1><p>PHPMailer is configured correctly.</p>'
);

if ($result) {
    echo '<p style="color:green; font-weight:bold;">✓ Email sent successfully!</p>';
} else {
    echo '<p style="color:red; font-weight:bold;">✗ Email failed — check your error log.</p>';
}
?>