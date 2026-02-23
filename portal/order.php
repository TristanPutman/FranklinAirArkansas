<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pricing.php';

$user = authRequire();
$db   = getDB();

// Validate order ID
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    header('Location: /portal/dashboard.php');
    exit;
}

// Fetch order and verify ownership
$stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: /portal/dashboard.php');
    exit;
}

// Handle new note submission
$noteError   = '';
$noteSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrfVerify($token)) {
        $noteError = 'Invalid form submission. Please try again.';
    } else {
        $message = sanitize($_POST['message'] ?? '');
        if ($message === '') {
            $noteError = 'Please enter a message.';
        } else {
            $stmt = $db->prepare('INSERT INTO order_notes (order_id, user_id, message) VALUES (?, ?, ?)');
            $stmt->execute([$orderId, $user['id'], $message]);
            $noteSuccess = 'Your message has been sent.';
        }
    }
}

// Fetch files
$stmt = $db->prepare('SELECT * FROM order_files WHERE order_id = ? ORDER BY created_at ASC');
$stmt->execute([$orderId]);
$files = $stmt->fetchAll();

// Fetch notes with user names
$stmt = $db->prepare(
    'SELECT n.*, u.name AS author_name, u.role AS author_role
     FROM order_notes n
     JOIN users u ON n.user_id = u.id
     WHERE n.order_id = ?
     ORDER BY n.created_at ASC'
);
$stmt->execute([$orderId]);
$notes = $stmt->fetchAll();

// Decode project data
$projectData = [];
if (!empty($order['project_data'])) {
    $decoded = json_decode($order['project_data'], true);
    if (is_array($decoded)) {
        $projectData = $decoded;
    }
}

// Human-readable labels for common project data keys
$projectLabels = [
    'address'           => 'Project Address',
    'city'              => 'City',
    'state'             => 'State',
    'zip'               => 'Zip Code',
    'sqft'              => 'Square Footage',
    'square_footage'    => 'Square Footage',
    'stories'           => 'Stories',
    'year_built'        => 'Year Built',
    'bedrooms'          => 'Bedrooms',
    'bathrooms'         => 'Bathrooms',
    'foundation_type'   => 'Foundation Type',
    'heating_type'      => 'Heating Type',
    'cooling_type'      => 'Cooling Type',
    'duct_location'     => 'Duct Location',
    'insulation'        => 'Insulation',
    'windows'           => 'Window Type',
    'property_type'     => 'Property Type',
    'construction_type' => 'Construction Type',
    'notes'             => 'Additional Notes',
    'customer_notes'    => 'Customer Notes',
    'name'              => 'Project Name',
    'project_name'      => 'Project Name',
];

function formatProjectKey($key) {
    global $projectLabels;
    if (isset($projectLabels[$key])) {
        return $projectLabels[$key];
    }
    return ucwords(str_replace(['_', '-'], ' ', $key));
}

function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?= e($order['order_number']) ?> | Franklin Air Arkansas</title>

    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Nunito+Sans:ital,opsz,wght@0,6..12,300..1000;1,6..12,300..1000&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/portal.css">
</head>
<body>

<div class="portal-wrap">
    <header class="portal-header">
        <a href="/" class="portal-logo">
            Franklin Air Arkansas
            <span class="portal-logo-tag">Portal</span>
        </a>
        <div class="portal-header-actions">
            <span class="portal-header-user"><?= e($user['name']) ?></span>
            <a href="/portal/logout.php" class="portal-header-link">Sign Out</a>
        </div>
    </header>

    <main class="portal-main">
        <a href="/portal/dashboard.php" class="order-detail-back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Orders
        </a>

        <div class="order-detail-header">
            <div>
                <h1 class="order-detail-title">Order <?= e($order['order_number']) ?></h1>
                <span class="badge <?= e(statusBadgeClass($order['status'])) ?>"><?= e(formatStatus($order['status'])) ?></span>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="detail-section">
            <h2 class="detail-section-title">Order Summary</h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Service</span>
                    <span class="detail-value"><?= e(getServiceLabel($order['service_type'])) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Price</span>
                    <span class="detail-value detail-value-lg"><?= e(formatPrice($order['price_cents'])) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Systems</span>
                    <span class="detail-value"><?= (int)$order['num_systems'] ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Rush Order</span>
                    <span class="detail-value"><?= $order['rush'] ? 'Yes' : 'No' ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Submitted</span>
                    <span class="detail-value"><?= e(date('M j, Y g:i A', strtotime($order['created_at']))) ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Last Updated</span>
                    <span class="detail-value"><?= e(timeAgo($order['updated_at'])) ?></span>
                </div>
            </div>
        </div>

        <!-- Order Notes (initial notes field from order submission) -->
        <?php if (!empty($order['notes'])): ?>
        <div class="detail-section">
            <h2 class="detail-section-title">Order Notes</h2>
            <div class="order-notes-text"><?= e($order['notes']) ?></div>
        </div>
        <?php endif; ?>

        <!-- Project Details -->
        <?php if (!empty($projectData)): ?>
        <div class="detail-section">
            <h2 class="detail-section-title">Project Details</h2>
            <?php foreach ($projectData as $key => $value): ?>
                <?php
                    if (is_array($value) || is_object($value)) {
                        $displayValue = json_encode($value);
                    } else {
                        $displayValue = (string)$value;
                    }
                    if ($displayValue === '') continue;
                ?>
                <div class="project-detail-item">
                    <span class="project-detail-key"><?= e(formatProjectKey($key)) ?></span>
                    <span class="project-detail-val"><?= e($displayValue) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Files -->
        <div class="detail-section">
            <h2 class="detail-section-title">Files</h2>
            <?php if (empty($files)): ?>
                <div class="files-empty">No files have been uploaded yet.</div>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <div class="file-item">
                        <div class="file-info">
                            <div class="file-name"><?= e($file['file_name']) ?></div>
                            <div class="file-meta">
                                <?= e(ucwords(str_replace('_', ' ', $file['file_type']))) ?>
                                &middot; <?= e(formatFileSize($file['file_size'])) ?>
                                &middot; <?= e(timeAgo($file['created_at'])) ?>
                            </div>
                        </div>
                        <a href="/api/download-file.php?id=<?= (int)$file['id'] ?>" class="file-download">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Download
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Messages Thread -->
        <div class="detail-section">
            <h2 class="detail-section-title">Messages</h2>

            <?php if ($noteSuccess): ?>
                <div class="portal-alert portal-alert-success"><?= e($noteSuccess) ?></div>
            <?php endif; ?>
            <?php if ($noteError): ?>
                <div class="portal-alert portal-alert-error"><?= e($noteError) ?></div>
            <?php endif; ?>

            <?php if (empty($notes)): ?>
                <div class="notes-empty">No messages yet. Send a message below.</div>
            <?php else: ?>
                <?php foreach ($notes as $note): ?>
                    <div class="note-item">
                        <div class="note-header">
                            <span class="note-author <?= $note['author_role'] === 'admin' ? 'note-author-admin' : '' ?>">
                                <?= e($note['author_name']) ?>
                                <?php if ($note['author_role'] === 'admin'): ?>
                                    <span style="font-size: 0.72rem; font-weight: 600; opacity: 0.7;">(Staff)</span>
                                <?php endif; ?>
                            </span>
                            <span class="note-time"><?= e(timeAgo($note['created_at'])) ?></span>
                        </div>
                        <div class="note-message"><?= e($note['message']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="POST" action="" class="portal-form note-form">
                <?= csrfField() ?>
                <div class="form-group">
                    <label for="message">Send a Message</label>
                    <textarea
                        id="message"
                        name="message"
                        placeholder="Type your message here..."
                        rows="3"
                        required
                    ></textarea>
                </div>
                <div class="note-form-actions">
                    <button type="submit" class="portal-btn portal-btn-primary portal-btn-sm">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        Send Message
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

</body>
</html>
