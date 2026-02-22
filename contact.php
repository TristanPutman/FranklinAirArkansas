<?php
// Contact form handler - sends email via Resend API

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Load config
$config = require __DIR__ . '/config.php';

// Honeypot check (spam bot trap)
if (!empty($_POST['website'])) {
    // Bot filled in the hidden field - pretend success
    echo json_encode(['success' => true, 'message' => 'Thank you! We\'ll be in touch soon.']);
    exit;
}

// Rate limiting via session
session_start();
$now = time();
$cooldown = 60; // seconds between submissions
if (isset($_SESSION['last_submit']) && ($now - $_SESSION['last_submit']) < $cooldown) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Please wait a moment before submitting again.']);
    exit;
}

// Sanitize inputs
$name    = trim(strip_tags($_POST['name'] ?? ''));
$email   = trim(strip_tags($_POST['email'] ?? ''));
$phone   = trim(strip_tags($_POST['phone'] ?? ''));
$service = trim(strip_tags($_POST['service'] ?? ''));
$message = trim(strip_tags($_POST['message'] ?? ''));

// Validate required fields
$errors = [];
if (empty($name))  $errors[] = 'Name is required.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
if (empty($message)) $errors[] = 'Message is required.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Build email HTML
$timestamp = date('M j, Y \a\t g:i A');

// Conditional rows — only shown if data provided
$phoneRow = '';
if (!empty($phone)) {
    $phoneRow = <<<ROW
                            <tr>
                                <td style="padding:10px 16px 10px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;letter-spacing:0.5px;vertical-align:top;width:90px;">Phone</td>
                                <td style="padding:10px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2C2420;"><a href="tel:{$phone}" style="color:#C45A2D;text-decoration:none;font-weight:bold;">{$phone}</a></td>
                            </tr>
ROW;
}

$serviceRow = '';
if (!empty($service)) {
    $serviceRow = <<<ROW
                            <tr>
                                <td style="padding:10px 16px 10px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;letter-spacing:0.5px;vertical-align:top;width:90px;">Service</td>
                                <td style="padding:10px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2C2420;">
                                    <span style="display:inline-block;background:#FFF5EB;color:#C45A2D;padding:4px 12px;font-size:13px;font-weight:bold;letter-spacing:0.3px;">{$service}</span>
                                </td>
                            </tr>
ROW;
}

// Escape message for safe HTML display, preserve line breaks
$messageHtml = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

$html = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>New Lead — Franklin Air</title>
</head>
<body style="margin:0;padding:0;background-color:#f2ede8;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">

<!-- Outer wrapper -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f2ede8;">
    <tr>
        <td align="center" style="padding:32px 16px;">

            <!-- Email container -->
            <table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;width:100%;">

                <!-- HEADER: Navy bar with branding -->
                <tr>
                    <td bgcolor="#1B2A4A" style="background-color:#1B2A4A;padding:28px 36px;">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td style="font-family:Georgia,'Times New Roman',serif;font-size:22px;color:#FFF5EB;line-height:1.2;">
                                    Franklin Air Arkansas
                                </td>
                            </tr>
                            <tr>
                                <td style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:rgba(255,245,235,0.5);letter-spacing:1.5px;text-transform:uppercase;padding-top:6px;">
                                    New Website Lead
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- COPPER ACCENT LINE -->
                <tr>
                    <td bgcolor="#C45A2D" style="background-color:#C45A2D;height:3px;font-size:0;line-height:0;">&nbsp;</td>
                </tr>

                <!-- BODY: White content area -->
                <tr>
                    <td bgcolor="#ffffff" style="background-color:#ffffff;padding:36px 36px 28px;">

                        <!-- Lead name as headline -->
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td style="font-family:Georgia,'Times New Roman',serif;font-size:26px;color:#1B2A4A;line-height:1.25;padding-bottom:4px;">
                                    {$name}
                                </td>
                            </tr>
                            <tr>
                                <td style="font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#8a8580;padding-bottom:24px;">
                                    Submitted {$timestamp}
                                </td>
                            </tr>
                        </table>

                        <!-- Divider -->
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td style="border-bottom:1px solid #ece7e1;font-size:0;line-height:0;height:1px;">&nbsp;</td>
                            </tr>
                        </table>

                        <!-- Contact details table -->
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top:20px;">
                            <tr>
                                <td style="padding:10px 16px 10px 0;font-family:Arial,Helvetica,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;letter-spacing:0.5px;vertical-align:top;width:90px;">Email</td>
                                <td style="padding:10px 0;font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2C2420;"><a href="mailto:{$email}" style="color:#C45A2D;text-decoration:none;font-weight:bold;">{$email}</a></td>
                            </tr>
                            {$phoneRow}
                            {$serviceRow}
                        </table>

                    </td>
                </tr>

                <!-- MESSAGE SECTION -->
                <tr>
                    <td bgcolor="#ffffff" style="background-color:#ffffff;padding:0 36px 36px;">

                        <!-- Message box with copper left border -->
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td bgcolor="#FFFAF5" style="background-color:#FFFAF5;border-left:4px solid #C45A2D;padding:20px 24px;">
                                    <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                        <tr>
                                            <td style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#8a8580;text-transform:uppercase;letter-spacing:1px;padding-bottom:10px;">
                                                Message
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#2C2420;line-height:1.65;">
                                                {$messageHtml}
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                <!-- ACTION BUTTON -->
                <tr>
                    <td bgcolor="#ffffff" style="background-color:#ffffff;padding:0 36px 36px;">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td align="center">
                                    <table border="0" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td bgcolor="#C45A2D" style="background-color:#C45A2D;padding:14px 36px;">
                                                <a href="mailto:{$email}?subject=Re:%20Your%20Franklin%20Air%20Inquiry&body=Hi%20{$name},%0A%0AThank%20you%20for%20reaching%20out%20to%20Franklin%20Air%20Arkansas.%0A%0A" style="font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#ffffff;text-decoration:none;font-weight:bold;letter-spacing:0.5px;display:inline-block;">Reply to {$name} &rarr;</a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- FOOTER -->
                <tr>
                    <td bgcolor="#1B2A4A" style="background-color:#1B2A4A;padding:24px 36px;">
                        <table border="0" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td style="font-family:Arial,Helvetica,sans-serif;font-size:12px;color:rgba(255,245,235,0.45);line-height:1.6;">
                                    This lead was submitted via the contact form on<br/>
                                    <a href="https://franklinairarkansas.com" style="color:rgba(255,245,235,0.65);text-decoration:none;">franklinairarkansas.com</a>
                                </td>
                                <td align="right" valign="top" style="font-family:Georgia,'Times New Roman',serif;font-size:14px;color:rgba(255,245,235,0.3);">
                                    Franklin Air
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

            </table>
            <!-- /Email container -->

        </td>
    </tr>
</table>
<!-- /Outer wrapper -->

</body>
</html>
HTML;

// Send via Resend API
$payload = json_encode([
    'from'    => "{$config['from_name']} <{$config['from_email']}>",
    'to'      => ["{$config['to_name']} <{$config['to_email']}>"],
    'subject' => "New Lead: {$name} — Franklin Air Website",
    'html'    => $html,
    'reply_to' => $email,
]);

$ch = curl_init('https://api.resend.com/emails');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $config['resend_api_key'],
        'Content-Type: application/json',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    $_SESSION['last_submit'] = $now;
    echo json_encode(['success' => true, 'message' => 'Thank you! Thomas will be in touch soon.']);
} else {
    http_response_code(500);
    error_log("Resend API error (HTTP {$httpCode}): {$response} | cURL: {$curlError}");
    echo json_encode(['success' => false, 'message' => 'Something went wrong. Please call us at (479) 207-2454.']);
}
