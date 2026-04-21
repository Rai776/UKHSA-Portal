<?php
require_once __DIR__ . '/email_config.php';

require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to_email, $to_name, $subject, $html_body)
{
    if (!MAIL_ENABLED) {
        error_log('[Email] Email is disabled in config.');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // Sender
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);

        // Recipient
        $mail->addAddress($to_email, $to_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_body;
        $mail->AltBody = strip_tags($html_body);

        $mail->send();
        error_log('[Email] Sent successfully to ' . $to_email . ' — ' . $subject);
        return true;
    } catch (Exception $e) {
        error_log('[Email] Failed to send to ' . $to_email . ' — ' . $mail->ErrorInfo);
        return false;
    }
}


function emailLayout($content, $border_color = '#1D70B8')
{
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="margin:0; padding:0; background-color:#f3f2f1; font-family: Arial, Helvetica, sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f2f1; padding: 30px 0;">
            <tr>
                <td align="center">
                    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%;">

                        <!-- HEADER -->
                        <tr>
                            <td style="background-color:#1D70B8; padding: 20px 30px;">
                                <h1 style="color:#ffffff; margin:0; font-size:1.1rem; font-weight:700;">
                                    UKHSA Data Governance Portal
                                </h1>
                            </td>
                        </tr>

                        <!-- CONTENT -->
                        <tr>
                            <td style="background-color:#ffffff; border-left: 5px solid ' . $border_color . '; padding: 30px;">
                                ' . $content . '
                            </td>
                        </tr>

                        <!-- FOOTER -->
                        <tr>
                            <td style="background-color:#f3f2f1; padding: 20px 30px; border-top: 1px solid #b1b4b6;">
                                <p style="margin:0; font-size:0.75rem; color:#505a5f;">
                                    This is an automated message from the UKHSA Data Governance Portal.<br>
                                    Please do not reply to this email.
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}

function sendApprovalEmail($to_email, $to_name, $dataset_name, $expiry_date)
{
    $subject = '[UKHSA Portal] Access Approved — ' . $dataset_name;

    $content = '
        <h2 style="color:#00703c; margin-top:0;">✓ Access Request Approved</h2>
        <p style="color:#0b0c0c;">Dear <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
        <p style="color:#0b0c0c;">
            Your request to access the dataset 
            <strong>' . htmlspecialchars($dataset_name) . '</strong> 
            has been <strong style="color:#00703c;">approved</strong>.
        </p>

        <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
            <tr style="background-color:#f3f2f1;">
                <td style="padding:12px; font-weight:bold; width:40%; border-bottom:1px solid #b1b4b6;">Dataset</td>
                <td style="padding:12px; border-bottom:1px solid #b1b4b6;">' . htmlspecialchars($dataset_name) . '</td>
            </tr>
            <tr>
                <td style="padding:12px; font-weight:bold; border-bottom:1px solid #b1b4b6;">Status</td>
                <td style="padding:12px; border-bottom:1px solid #b1b4b6; color:#00703c; font-weight:bold;">Approved</td>
            </tr>
            <tr style="background-color:#f3f2f1;">
                <td style="padding:12px; font-weight:bold;">Access Expires</td>
                <td style="padding:12px;">' . date('d M Y', strtotime($expiry_date)) . '</td>
            </tr>
        </table>

        <p style="color:#0b0c0c;">You can now access this dataset through the portal.</p>
        <p style="color:#d4351c; font-size:0.85rem;">
            ⚠ Please ensure you handle this data in accordance with UKHSA data governance policies.
        </p>

        <a href="' . PORTAL_URL . '/user/my_requests.php" 
           style="display:inline-block; padding:12px 24px; background-color:#1D70B8; color:#ffffff; text-decoration:none; font-weight:bold; margin-top:10px;">
            View My Requests
        </a>
    ';

    return sendEmail($to_email, $to_name, $subject, emailLayout($content, '#00703c'));
}

function sendRejectionEmail($to_email, $to_name, $dataset_name, $reason)
{
    $subject = '[UKHSA Portal] Access Request Rejected — ' . $dataset_name;

    $content = '
        <h2 style="color:#d4351c; margin-top:0;">✗ Access Request Rejected</h2>
        <p style="color:#0b0c0c;">Dear <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
        <p style="color:#0b0c0c;">
            Your request to access the dataset 
            <strong>' . htmlspecialchars($dataset_name) . '</strong> 
            has been <strong style="color:#d4351c;">rejected</strong>.
        </p>

        <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
            <tr style="background-color:#f3f2f1;">
                <td style="padding:12px; font-weight:bold; width:40%; border-bottom:1px solid #b1b4b6;">Dataset</td>
                <td style="padding:12px; border-bottom:1px solid #b1b4b6;">' . htmlspecialchars($dataset_name) . '</td>
            </tr>
            <tr>
                <td style="padding:12px; font-weight:bold; border-bottom:1px solid #b1b4b6;">Status</td>
                <td style="padding:12px; border-bottom:1px solid #b1b4b6; color:#d4351c; font-weight:bold;">Rejected</td>
            </tr>
            <tr style="background-color:#f3f2f1;">
                <td style="padding:12px; font-weight:bold;">Reason</td>
                <td style="padding:12px;">' . htmlspecialchars($reason) . '</td>
            </tr>
        </table>

        <p style="color:#0b0c0c;">
            If you believe this decision is incorrect, you may submit a new request with additional justification.
        </p>

        <a href="' . PORTAL_URL . '/user/datasets.php" 
           style="display:inline-block; padding:12px 24px; background-color:#1D70B8; color:#ffffff; text-decoration:none; font-weight:bold; margin-top:10px;">
            Browse Datasets
        </a>
    ';

    return sendEmail($to_email, $to_name, $subject, emailLayout($content, '#d4351c'));
}

function sendAccessExpiryEmail($to_email, $to_name, $dataset_name, $expiry_date, $days_left)
{
    $subject       = '[UKHSA Portal] Access Expiring in ' . $days_left . ' Day' . ($days_left > 1 ? 's' : '') . ' — ' . $dataset_name;
    $urgency_color = $days_left <= 1 ? '#d4351c' : '#f47738';

    $content = '
        <h2 style="color:' . $urgency_color . '; margin-top:0;">
            ⚠ Access Expiring in ' . $days_left . ' Day' . ($days_left > 1 ? 's' : '') . '
        </h2>
        <p style="color:#0b0c0c;">Dear <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
        <p style="color:#0b0c0c;">
            Your access to <strong>' . htmlspecialchars($dataset_name) . '</strong> 
            will expire in <strong style="color:' . $urgency_color . ';">' . $days_left . ' day' . ($days_left > 1 ? 's' : '') . '</strong>.
        </p>

        <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
            <tr style="background-color:#f3f2f1;">
                <td style="padding:12px; font-weight:bold; width:40%; border-bottom:1px solid #b1b4b6;">Dataset</td>
                <td style="padding:12px; border-bottom:1px solid #b1b4b6;">' . htmlspecialchars($dataset_name) . '</td>
            </tr>
            <tr>
                <td style="padding:12px; font-weight:bold; border-bottom:1px solid #b1b4b6;">Expiry Date</td>
                <td style="padding:12px; border-bottom:1px solid #b1b4b6; color:' . $urgency_color . '; font-weight:bold;">' . date('d M Y', strtotime($expiry_date)) . '</td>
            </tr>
            <tr style="background-color:#f3f2f1;">
                <td style="padding:12px; font-weight:bold;">Days Remaining</td>
                <td style="padding:12px; color:' . $urgency_color . '; font-weight:bold;">' . $days_left . ' day' . ($days_left > 1 ? 's' : '') . '</td>
            </tr>
        </table>

        <p style="color:#0b0c0c;">
            Please submit a new access request before the expiry date to maintain uninterrupted access.
        </p>

        <a href="' . PORTAL_URL . '/user/datasets.php" 
           style="display:inline-block; padding:12px 24px; background-color:#1D70B8; color:#ffffff; text-decoration:none; font-weight:bold; margin-top:10px;">
            Renew Access
        </a>
    ';

    return sendEmail($to_email, $to_name, $subject, emailLayout($content, $urgency_color));
}


function sendTrainingExpiryEmail($to_email, $to_name, $expiry_date, $days_left)
{
    $subject       = '[UKHSA Portal] Training Certification Expiring in ' . $days_left . ' Day' . ($days_left > 1 ? 's' : '');
    $urgency_color = $days_left <= 3 ? '#d4351c' : '#f47738';

    $content = '
        <h2 style="color:' . $urgency_color . '; margin-top:0;">
            ⚠ Training Certification Expiring in ' . $days_left . ' Day' . ($days_left > 1 ? 's' : '') . '
        </h2>
        <p style="color:#0b0c0c;">Dear <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
        <p style="color:#0b0c0c;">
            Your data governance training certification will expire in 
            <strong style="color:' . $urgency_color . ';">' . $days_left . ' day' . ($days_left > 1 ? 's' : '') . '</strong> 
            on <strong>' . date('d M Y', strtotime($expiry_date)) . '</strong>.
        </p>

        <div style="background-color:#fff4e6; border-left:4px solid #f47738; padding:15px; margin:20px 0;">
            <p style="margin:0; color:#6e3b00; font-size:0.9rem;">
                <strong>Important:</strong> Once your training expires, you will not be able to submit 
                new access requests for sensitive datasets until you renew your certification.
            </p>
        </div>

        <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 
                0; border-collapse: collapse;">
            <tr style="background-color:#f3f2f1;">
                <td style="padding:12px; font-weight:bold; width:40%; border-bottom:1px solid #b1b4b6;">Certification</td>
                <td style="padding:12px; border-bottom:1px solid #b1b4b6;">Data Governance Training</td>
            </tr>
            <tr>
                <td style="padding:12px; font-weight:bold; border-bottom:1px solid #b1b4b6;">Expiry Date</td>
                <td style="padding:12px; border-bottom:1px solid #b1b4b6; color:' . $urgency_color . '; font-weight:bold;">' . date('d M Y', strtotime($expiry_date)) . '</td>
            </tr>
            <tr style="background-color:#f3f2f1;">
                <td style="padding:12px; font-weight:bold;">Days Remaining</td>
                <td style="padding:12px; color:' . $urgency_color . '; font-weight:bold;">' . $days_left . ' day' . ($days_left > 1 ? 's' : '') . '</td>
            </tr>
        </table>

        <p style="color:#0b0c0c;">
            Please complete your training renewal as soon as possible to avoid disruption to your access.
        </p>

        <a href="' . PORTAL_URL . '/user/training.php"
           style="display:inline-block; padding:12px 24px; background-color:#1D70B8; color:#ffffff; text-decoration:none; font-weight:bold; margin-top:10px;">
            Renew Training
        </a>
    ';

    return sendEmail($to_email, $to_name, $subject, emailLayout($content, $urgency_color));
}

function sendRevokeEmail($to_email, $to_name, $dataset_name, $revoked_by)
{
    $subject = '[UKHSA Portal] Access Revoked — ' . $dataset_name;

    $content = '
        <h2 style="color:#d4351c; margin-top:0;">✗ Dataset Access Revoked</h2>
        <p style="color:#0b0c0c;">Dear <strong>' . htmlspecialchars($to_name) . '</strong>,</p>
        <p style="color:#0b0c0c;">
            Your access to the dataset 
            <strong>' . htmlspecialchars($dataset_name) . '</strong> 
            has been <strong style="color:#d4351c;">revoked</strong>.
        </p>

        <table width="100%" cellpadding="0" cellspacing="0" style="margin: 20px 0; border-collapse: collapse;">
            <tr style="background-color:#f3f2f1;">
                <td style="padding:12px; font-weight:bold; width:40%; border-bottom:1px solid #b1b4b6;">Dataset</td>
                <td style="padding:12px; border-bottom:1px solid #b1b4b6;">' . htmlspecialchars($dataset_name) . '</td>
            </tr>
            <tr>
                <td style="padding:12px; font-weight:bold; border-bottom:1px solid #b1b4b6;">Status</td>
                <td style="padding:12px; border-bottom:1px solid #b1b4b6; color:#d4351c; font-weight:bold;">Revoked</td>
            </tr>
            <tr style="background-color:#f3f2f1;">
                <td style="padding:12px; font-weight:bold; border-bottom:1px solid #b1b4b6;">Revoked By</td>
                <td style="padding:12px; border-bottom:1px solid #b1b4b6;">' . htmlspecialchars($revoked_by) . '</td>
            </tr>
            <tr>
                <td style="padding:12px; font-weight:bold;">Date</td>
                <td style="padding:12px;">' . date('d M Y, H:i') . '</td>
            </tr>
        </table>

        <div style="background-color:#fff4e6; border-left:4px solid #f47738; padding:15px; margin:20px 0;">
            <p style="margin:0; color:#6e3b00; font-size:0.9rem;">
                <strong>Note:</strong> If you believe this is an error or wish to request access again, 
                please submit a new access request through the portal with a detailed justification.
            </p>
        </div>

        <p style="color:#0b0c0c;">
            You will no longer be able to access this dataset until a new request has been approved.
        </p>

        <a href="' . PORTAL_URL . '/user/datasets.php" 
           style="display:inline-block; padding:12px 24px; background-color:#1D70B8; color:#ffffff; text-decoration:none; font-weight:bold; margin-top:10px;">
            Request Access Again
        </a>
    ';

    return sendEmail($to_email, $to_name, $subject, emailLayout($content, '#d4351c'));
}
