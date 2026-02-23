<?php
// Resend API email wrapper — reuses pattern from contact.php

function sendEmail($to, $subject, $html, $replyTo = null) {
    $config = require __DIR__ . '/../config.php';

    $payload = [
        'from'    => "{$config['from_name']} <{$config['from_email']}>",
        'to'      => is_array($to) ? $to : [$to],
        'subject' => $subject,
        'html'    => $html,
    ];
    if ($replyTo) {
        $payload['reply_to'] = $replyTo;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
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
        return true;
    }

    error_log("Resend API error (HTTP {$httpCode}): {$response} | cURL: {$curlError}");
    return false;
}

function sendOrderNotificationToAdmin($order, $customer) {
    $config = require __DIR__ . '/../config.php';
    $timestamp = date('M j, Y \a\t g:i A');
    $priceFormatted = '$' . number_format($order['price_cents'] / 100, 2);
    $rushLabel = $order['rush'] ? '<span style="display:inline-block;background:#FBE9E7;color:#C62828;padding:2px 10px;font-size:12px;font-weight:bold;border-radius:3px;">RUSH</span>' : '';

    $projectData = is_string($order['project_data']) ? json_decode($order['project_data'], true) : ($order['project_data'] ?? []);
    $projectRows = '';
    if ($projectData) {
        foreach ($projectData as $key => $value) {
            if ($value === '' || $value === null) continue;
            $label = htmlspecialchars(ucwords(str_replace('_', ' ', $key)));
            $val = htmlspecialchars(is_array($value) ? implode(', ', $value) : $value);
            $projectRows .= "<tr><td style=\"padding:6px 16px 6px 0;font-family:Arial,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;letter-spacing:0.5px;vertical-align:top;width:140px;\">{$label}</td><td style=\"padding:6px 0;font-family:Arial,sans-serif;font-size:14px;color:#2C2420;\">{$val}</td></tr>";
        }
    }

    $notesHtml = '';
    if (!empty($order['notes'])) {
        $notesHtml = '<tr><td colspan="2" style="padding-top:16px;"><div style="background:#FFFAF5;border-left:4px solid #C45A2D;padding:16px 20px;"><div style="font-family:Arial,sans-serif;font-size:11px;color:#8a8580;text-transform:uppercase;letter-spacing:1px;padding-bottom:8px;">Notes</div><div style="font-family:Arial,sans-serif;font-size:14px;color:#2C2420;line-height:1.6;">' . nl2br(htmlspecialchars($order['notes'])) . '</div></div></td></tr>';
    }

    $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>New Order</title></head>
<body style="margin:0;padding:0;background-color:#f2ede8;">
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f2ede8;"><tr><td align="center" style="padding:32px 16px;">
<table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;width:100%;">
<tr><td bgcolor="#1B2A4A" style="padding:28px 36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr><td style="font-family:Georgia,serif;font-size:22px;color:#FFF5EB;">Franklin Air Arkansas</td></tr>
        <tr><td style="font-family:Arial,sans-serif;font-size:11px;color:rgba(255,245,235,0.5);letter-spacing:1.5px;text-transform:uppercase;padding-top:6px;">New Design Order</td></tr>
    </table>
</td></tr>
<tr><td bgcolor="#C45A2D" style="height:3px;font-size:0;">&nbsp;</td></tr>
<tr><td bgcolor="#ffffff" style="padding:36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr><td style="font-family:Georgia,serif;font-size:24px;color:#1B2A4A;padding-bottom:4px;">{$customer['name']}</td></tr>
        <tr><td style="font-family:Arial,sans-serif;font-size:13px;color:#8a8580;padding-bottom:8px;">Order {$order['order_number']} &mdash; {$timestamp}</td></tr>
        <tr><td style="padding-bottom:20px;">
            <span style="display:inline-block;background:#FFF5EB;color:#C45A2D;padding:4px 14px;font-size:13px;font-weight:bold;border-radius:3px;">{$order['service_type']}</span>
            {$rushLabel}
        </td></tr>
        <tr><td style="border-bottom:1px solid #ece7e1;">&nbsp;</td></tr>
    </table>
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top:16px;">
        <tr><td style="padding:6px 16px 6px 0;font-family:Arial,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;width:140px;">Email</td><td style="padding:6px 0;font-family:Arial,sans-serif;font-size:14px;"><a href="mailto:{$customer['email']}" style="color:#C45A2D;text-decoration:none;font-weight:bold;">{$customer['email']}</a></td></tr>
        <tr><td style="padding:6px 16px 6px 0;font-family:Arial,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;width:140px;">Phone</td><td style="padding:6px 0;font-family:Arial,sans-serif;font-size:14px;"><a href="tel:{$customer['phone']}" style="color:#C45A2D;text-decoration:none;font-weight:bold;">{$customer['phone']}</a></td></tr>
        <tr><td style="padding:6px 16px 6px 0;font-family:Arial,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;width:140px;">Systems</td><td style="padding:6px 0;font-family:Arial,sans-serif;font-size:14px;color:#2C2420;">{$order['num_systems']}</td></tr>
        <tr><td style="padding:6px 16px 6px 0;font-family:Arial,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;width:140px;">Total</td><td style="padding:6px 0;font-family:Georgia,serif;font-size:20px;color:#1B2A4A;font-weight:bold;">{$priceFormatted}</td></tr>
        {$projectRows}
        {$notesHtml}
    </table>
</td></tr>
<tr><td bgcolor="#ffffff" style="padding:0 36px 36px;" align="center">
    <table border="0" cellpadding="0" cellspacing="0"><tr>
        <td bgcolor="#C45A2D" style="padding:14px 36px;"><a href="mailto:{$customer['email']}?subject=Re:%20Order%20{$order['order_number']}" style="font-family:Arial,sans-serif;font-size:14px;color:#ffffff;text-decoration:none;font-weight:bold;">Reply to {$customer['name']} &rarr;</a></td>
    </tr></table>
</td></tr>
<tr><td bgcolor="#1B2A4A" style="padding:24px 36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%"><tr>
        <td style="font-family:Arial,sans-serif;font-size:12px;color:rgba(255,245,235,0.45);line-height:1.6;">New order via <a href="https://franklinairarkansas.com" style="color:rgba(255,245,235,0.65);text-decoration:none;">franklinairarkansas.com</a></td>
        <td align="right" style="font-family:Georgia,serif;font-size:14px;color:rgba(255,245,235,0.3);">Franklin Air</td>
    </tr></table>
</td></tr>
</table>
</td></tr></table>
</body></html>
HTML;

    $toAddr = "{$config['to_name']} <{$config['to_email']}>";
    return sendEmail($toAddr, "New Order: {$order['order_number']} — {$customer['name']}", $html, $customer['email']);
}

function sendOrderConfirmationToCustomer($order, $customer) {
    $priceFormatted = '$' . number_format($order['price_cents'] / 100, 2);
    $rushLabel = $order['rush'] ? ' (Rush)' : '';

    $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Order Confirmation</title></head>
<body style="margin:0;padding:0;background-color:#f2ede8;">
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f2ede8;"><tr><td align="center" style="padding:32px 16px;">
<table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;width:100%;">
<tr><td bgcolor="#1B2A4A" style="padding:28px 36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr><td style="font-family:Georgia,serif;font-size:22px;color:#FFF5EB;">Franklin Air Arkansas</td></tr>
        <tr><td style="font-family:Arial,sans-serif;font-size:11px;color:rgba(255,245,235,0.5);letter-spacing:1.5px;text-transform:uppercase;padding-top:6px;">Order Confirmation</td></tr>
    </table>
</td></tr>
<tr><td bgcolor="#C45A2D" style="height:3px;font-size:0;">&nbsp;</td></tr>
<tr><td bgcolor="#ffffff" style="padding:36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr><td style="font-family:Georgia,serif;font-size:24px;color:#1B2A4A;padding-bottom:8px;">Thank you, {$customer['name']}!</td></tr>
        <tr><td style="font-family:Arial,sans-serif;font-size:15px;color:#6B5E54;line-height:1.7;padding-bottom:24px;">We've received your order and will begin working on it shortly. Here's your order summary:</td></tr>
        <tr><td style="border-bottom:1px solid #ece7e1;">&nbsp;</td></tr>
    </table>
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top:16px;">
        <tr><td style="padding:8px 16px 8px 0;font-family:Arial,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;width:130px;">Order Number</td><td style="padding:8px 0;font-family:Arial,sans-serif;font-size:15px;color:#2C2420;font-weight:bold;">{$order['order_number']}</td></tr>
        <tr><td style="padding:8px 16px 8px 0;font-family:Arial,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;width:130px;">Service</td><td style="padding:8px 0;font-family:Arial,sans-serif;font-size:15px;color:#2C2420;">{$order['service_type']}{$rushLabel}</td></tr>
        <tr><td style="padding:8px 16px 8px 0;font-family:Arial,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;width:130px;">Systems</td><td style="padding:8px 0;font-family:Arial,sans-serif;font-size:15px;color:#2C2420;">{$order['num_systems']}</td></tr>
        <tr><td style="padding:8px 16px 8px 0;font-family:Arial,sans-serif;font-size:12px;color:#8a8580;text-transform:uppercase;width:130px;">Total</td><td style="padding:8px 0;font-family:Georgia,serif;font-size:22px;color:#1B2A4A;font-weight:bold;">{$priceFormatted}</td></tr>
    </table>
</td></tr>
<tr><td bgcolor="#ffffff" style="padding:0 36px 16px;">
    <div style="background:#FFFAF5;border-left:4px solid #C45A2D;padding:16px 20px;font-family:Arial,sans-serif;font-size:14px;color:#2C2420;line-height:1.6;">
        <strong>What's next?</strong><br>
        Thomas will review your order and project details. Standard turnaround is 5&ndash;7 business days. If you selected rush service, we'll prioritize your order.
    </div>
</td></tr>
<tr><td bgcolor="#ffffff" style="padding:0 36px 36px;" align="center">
    <table border="0" cellpadding="0" cellspacing="0"><tr>
        <td bgcolor="#C45A2D" style="padding:14px 36px;"><a href="https://franklinairarkansas.com/portal/dashboard.php" style="font-family:Arial,sans-serif;font-size:14px;color:#ffffff;text-decoration:none;font-weight:bold;">View Your Order &rarr;</a></td>
    </tr></table>
</td></tr>
<tr><td bgcolor="#ffffff" style="padding:0 36px 36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td style="font-family:Arial,sans-serif;font-size:13px;color:#8a8580;line-height:1.6;">
        Questions? Call Thomas at <a href="tel:+14792072454" style="color:#C45A2D;text-decoration:none;font-weight:bold;">(479) 207-2454</a> or reply to this email.
    </td></tr></table>
</td></tr>
<tr><td bgcolor="#1B2A4A" style="padding:24px 36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%"><tr>
        <td style="font-family:Arial,sans-serif;font-size:12px;color:rgba(255,245,235,0.45);line-height:1.6;"><a href="https://franklinairarkansas.com" style="color:rgba(255,245,235,0.65);text-decoration:none;">franklinairarkansas.com</a></td>
        <td align="right" style="font-family:Georgia,serif;font-size:14px;color:rgba(255,245,235,0.3);">Franklin Air</td>
    </tr></table>
</td></tr>
</table>
</td></tr></table>
</body></html>
HTML;

    return sendEmail($customer['email'], "Order Confirmed: {$order['order_number']} — Franklin Air Arkansas", $html);
}

function sendStatusUpdateEmail($order, $customer, $newStatus) {
    $statusLabels = [
        'in_progress'        => 'In Progress',
        'completed'          => 'Completed',
        'revision_requested' => 'Revision Requested',
        'cancelled'          => 'Cancelled',
    ];
    $label = $statusLabels[$newStatus] ?? ucfirst($newStatus);
    $priceFormatted = '$' . number_format($order['price_cents'] / 100, 2);

    $statusMessage = '';
    switch ($newStatus) {
        case 'in_progress':
            $statusMessage = 'Thomas has started working on your HVAC design report. You\'ll receive another email when it\'s complete.';
            break;
        case 'completed':
            $statusMessage = 'Your HVAC design report is ready! Log in to your portal to download it.';
            break;
        case 'revision_requested':
            $statusMessage = 'Your revision request has been received. Thomas will review it and update your report.';
            break;
        default:
            $statusMessage = "Your order status has been updated to: {$label}.";
    }

    $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Order Update</title></head>
<body style="margin:0;padding:0;background-color:#f2ede8;">
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f2ede8;"><tr><td align="center" style="padding:32px 16px;">
<table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;width:100%;">
<tr><td bgcolor="#1B2A4A" style="padding:28px 36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr><td style="font-family:Georgia,serif;font-size:22px;color:#FFF5EB;">Franklin Air Arkansas</td></tr>
        <tr><td style="font-family:Arial,sans-serif;font-size:11px;color:rgba(255,245,235,0.5);letter-spacing:1.5px;text-transform:uppercase;padding-top:6px;">Order Update</td></tr>
    </table>
</td></tr>
<tr><td bgcolor="#C45A2D" style="height:3px;font-size:0;">&nbsp;</td></tr>
<tr><td bgcolor="#ffffff" style="padding:36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr><td style="font-family:Georgia,serif;font-size:22px;color:#1B2A4A;padding-bottom:8px;">Order {$order['order_number']}</td></tr>
        <tr><td style="padding-bottom:16px;"><span style="display:inline-block;background:#FFF5EB;color:#C45A2D;padding:4px 14px;font-size:13px;font-weight:bold;border-radius:3px;">{$label}</span></td></tr>
        <tr><td style="font-family:Arial,sans-serif;font-size:15px;color:#6B5E54;line-height:1.7;padding-bottom:24px;">{$statusMessage}</td></tr>
    </table>
</td></tr>
<tr><td bgcolor="#ffffff" style="padding:0 36px 36px;" align="center">
    <table border="0" cellpadding="0" cellspacing="0"><tr>
        <td bgcolor="#C45A2D" style="padding:14px 36px;"><a href="https://franklinairarkansas.com/portal/order.php?id={$order['id']}" style="font-family:Arial,sans-serif;font-size:14px;color:#ffffff;text-decoration:none;font-weight:bold;">View Order Details &rarr;</a></td>
    </tr></table>
</td></tr>
<tr><td bgcolor="#1B2A4A" style="padding:24px 36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%"><tr>
        <td style="font-family:Arial,sans-serif;font-size:12px;color:rgba(255,245,235,0.45);"><a href="https://franklinairarkansas.com" style="color:rgba(255,245,235,0.65);text-decoration:none;">franklinairarkansas.com</a></td>
        <td align="right" style="font-family:Georgia,serif;font-size:14px;color:rgba(255,245,235,0.3);">Franklin Air</td>
    </tr></table>
</td></tr>
</table>
</td></tr></table>
</body></html>
HTML;

    return sendEmail($customer['email'], "Order Update: {$order['order_number']} — {$label}", $html);
}
