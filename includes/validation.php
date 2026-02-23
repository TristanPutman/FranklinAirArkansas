<?php
// Input validation and sanitization helpers

function sanitize($value) {
    if (is_array($value)) {
        return array_map('sanitize', $value);
    }
    return trim(strip_tags($value));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validateRequired($fields, $data) {
    $errors = [];
    foreach ($fields as $field => $label) {
        if (empty($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            $errors[] = "{$label} is required.";
        }
    }
    return $errors;
}

function validatePhone($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    return strlen($digits) >= 7 && strlen($digits) <= 15;
}

function validateFileUpload($file, $maxSize = 10485760) {
    $allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];
    $allowedExts = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server error: missing temp folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server error: failed to write file.',
        ];
        return $errorMessages[$file['error']] ?? 'Upload error.';
    }

    if ($file['size'] > $maxSize) {
        return 'File is too large. Maximum size is ' . round($maxSize / 1048576) . 'MB.';
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        return 'File type not allowed. Please upload PDF, JPG, or PNG files.';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes)) {
        return 'File type not allowed.';
    }

    return true;
}

function csrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function csrfVerify($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
