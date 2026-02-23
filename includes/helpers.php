<?php
// Utility helpers — order numbers, file handling

require_once __DIR__ . '/db.php';

function generateOrderNumber() {
    $db = getDB();
    $datePrefix = 'FA-' . date('ymd') . '-';

    // Get the next sequence number for today
    $stmt = $db->prepare("SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$datePrefix . '%']);
    $last = $stmt->fetchColumn();

    if ($last) {
        $seq = intval(substr($last, -4)) + 1;
    } else {
        $seq = 1;
    }

    return $datePrefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function handleFileUpload($file, $orderId) {
    $uploadDir = __DIR__ . '/../uploads/' . $orderId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = $uploadDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return false;
    }

    return [
        'file_name' => $file['name'],
        'file_path' => 'uploads/' . $orderId . '/' . $safeName,
        'file_size' => $file['size'],
    ];
}

function logStatusChange($orderId, $oldStatus, $newStatus, $changedBy = null, $note = '') {
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO order_status_log (order_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$orderId, $oldStatus, $newStatus, $changedBy, $note]);
}

function generatePassword($length = 12) {
    $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789!@#$';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function formatPrice($cents) {
    return '$' . number_format($cents / 100, 2);
}

function formatStatus($status) {
    $labels = [
        'pending'            => 'Pending',
        'in_progress'        => 'In Progress',
        'revision_requested' => 'Revision Requested',
        'completed'          => 'Completed',
        'cancelled'          => 'Cancelled',
    ];
    return $labels[$status] ?? ucfirst($status);
}

function statusBadgeClass($status) {
    $classes = [
        'pending'            => 'badge-pending',
        'in_progress'        => 'badge-progress',
        'revision_requested' => 'badge-revision',
        'completed'          => 'badge-completed',
        'cancelled'          => 'badge-cancelled',
    ];
    return $classes[$status] ?? 'badge-pending';
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', $time);
}

function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
