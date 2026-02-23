<?php
// File upload endpoint — add files to existing orders (portal/admin use)
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/helpers.php';

// Require authentication
authStart();
$user = authGetUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Validate order_id
$orderId = isset($_POST['order_id']) ? (int) sanitize($_POST['order_id']) : 0;
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid order_id is required']);
    exit;
}

// Validate file_type
$allowedFileTypes = ['floor_plan', 'report', 'revision', 'other'];
$fileType = isset($_POST['file_type']) ? sanitize($_POST['file_type']) : 'other';
if (!in_array($fileType, $allowedFileTypes)) {
    $fileType = 'other';
}

try {
    $db = getDB();

    // Verify order exists and user has access
    $stmt = $db->prepare('SELECT id, user_id, order_number FROM orders WHERE id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
        exit;
    }

    if ($user['role'] !== 'admin' && (int) $order['user_id'] !== (int) $user['id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
        exit;
    }

    // Validate file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No file was uploaded']);
        exit;
    }

    // Validate file contents and type
    $validation = validateFileUpload($_FILES['file']);
    if ($validation !== true) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $validation]);
        exit;
    }

    // If admin uploading a report, set file_type to 'report'
    if ($user['role'] === 'admin' && $fileType === 'report') {
        $fileType = 'report';
    }

    // Handle the file upload (move to storage)
    $uploadResult = handleFileUpload($_FILES['file'], $orderId);
    if (!$uploadResult) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
        exit;
    }

    // Insert record into order_files
    $stmt = $db->prepare(
        'INSERT INTO order_files (order_id, uploaded_by, file_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $orderId,
        $user['id'],
        $uploadResult['file_name'],
        $uploadResult['file_path'],
        $fileType,
        $uploadResult['file_size'],
    ]);

    $fileId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'file'    => [
            'id'        => (int) $fileId,
            'file_name' => $uploadResult['file_name'],
            'file_type' => $fileType,
            'file_size' => $uploadResult['file_size'],
        ],
    ]);

} catch (Exception $e) {
    error_log('upload-file.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
}
