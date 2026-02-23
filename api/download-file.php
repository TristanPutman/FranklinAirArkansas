<?php
// Secure file download endpoint — serves files with access control

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Require authentication
authStart();
$user = authGetUser();

if (!$user) {
    http_response_code(401);
    echo 'Authentication required.';
    exit;
}

// Validate file ID parameter
$fileId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($fileId <= 0) {
    http_response_code(400);
    echo 'Invalid file ID.';
    exit;
}

try {
    $db = getDB();

    // Query the file record
    $stmt = $db->prepare(
        'SELECT f.id, f.order_id, f.file_name, f.file_path, f.file_size, o.user_id
         FROM order_files f
         JOIN orders o ON f.order_id = o.id
         WHERE f.id = ?'
    );
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();

    // File not found in database
    if (!$file) {
        http_response_code(404);
        echo 'File not found.';
        exit;
    }

    // Access control: admins can download any file; customers can only download their own
    if ($user['role'] !== 'admin') {
        if ((int) $file['user_id'] !== (int) $user['id']) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }
    }

    // Resolve the absolute path to the file on disk
    $absolutePath = realpath(__DIR__ . '/../' . $file['file_path']);

    // Verify the file exists on disk and is within the uploads directory
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if (!$absolutePath || !is_file($absolutePath) || strpos($absolutePath, $uploadsDir) !== 0) {
        http_response_code(404);
        echo 'File not found on disk.';
        exit;
    }

    // Determine MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($absolutePath);
    if (!$mimeType) {
        $mimeType = 'application/octet-stream';
    }

    // Sanitize the original filename for Content-Disposition
    $downloadName = basename($file['file_name']);
    $downloadName = preg_replace('/[^\w\-. ]/', '_', $downloadName);
    if (empty($downloadName)) {
        $downloadName = 'download';
    }

    // Send download headers
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . filesize($absolutePath));
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Flush output buffers to avoid memory issues with large files
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Stream the file to the client
    readfile($absolutePath);
    exit;

} catch (Exception $e) {
    error_log('download-file.php error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Server error. Please try again.';
    exit;
}
