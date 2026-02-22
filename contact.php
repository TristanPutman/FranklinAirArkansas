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
$serviceLabel = !empty($service) ? "<tr><td style='padding:6px 12px;color:#666;'>Service:</td><td style='padding:6px 12px;'>{$service}</td></tr>" : '';
$phoneRow     = !empty($phone) ? "<tr><td style='padding:6px 12px;color:#666;'>Phone:</td><td style='padding:6px 12px;'><a href='tel:{$phone}'>{$phone}</a></td></tr>" : '';

$html = <<<HTML
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;">
    <div style="background:#1B2A4A;padding:24px;border-radius:8px 8px 0 0;">
        <h2 style="color:#FFF5EB;margin:0;font-size:20px;">New Lead from Franklin Air Website</h2>
    </div>
    <div style="background:#fff;padding:24px;border:1px solid #e5e5e5;">
        <table style="width:100%;border-collapse:collapse;">
            <tr><td style="padding:6px 12px;color:#666;">Name:</td><td style="padding:6px 12px;font-weight:bold;">{$name}</td></tr>
            <tr><td style="padding:6px 12px;color:#666;">Email:</td><td style="padding:6px 12px;"><a href="mailto:{$email}">{$email}</a></td></tr>
            {$phoneRow}
            {$serviceLabel}
        </table>
        <div style="margin-top:16px;padding:16px;background:#FFFAF5;border-radius:6px;border-left:3px solid #C45A2D;">
            <p style="margin:0 0 4px;color:#666;font-size:13px;">Message:</p>
            <p style="margin:0;color:#333;">{$message}</p>
        </div>
    </div>
    <div style="background:#f9f9f9;padding:16px;border-radius:0 0 8px 8px;border:1px solid #e5e5e5;border-top:none;text-align:center;">
        <p style="margin:0;color:#999;font-size:12px;">Sent from franklinairarkansas.com contact form</p>
    </div>
</div>
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
