<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pricing.php';
require_once __DIR__ . '/../includes/email.php';
require_once __DIR__ . '/../includes/db.php';

$admin = authRequireAdmin();
$db = getDB();

// Validate order ID
$orderId = (int)($_GET['id'] ?? 0);
if ($orderId <= 0) {
    header('Location: /admin/index.php');
    exit;
}

// Load order
$stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: /admin/index.php');
    exit;
}

// Load customer
$stmt = $db->prepare("SELECT id, name, email, phone, company FROM users WHERE id = ?");
$stmt->execute([$order['user_id']]);
$customer = $stmt->fetch();

$success = '';
$error = '';

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // STATUS UPDATE
        if ($action === 'update_status') {
            $newStatus = sanitize($_POST['new_status'] ?? '');
            $statusNote = sanitize($_POST['status_note'] ?? '');
            $validStatuses = ['pending', 'in_progress', 'revision_requested', 'completed', 'cancelled'];

            if (!in_array($newStatus, $validStatuses)) {
                $error = 'Invalid status selected.';
            } elseif ($newStatus === $order['status']) {
                $error = 'Order is already in that status.';
            } else {
                $oldStatus = $order['status'];

                $updateStmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$newStatus, $orderId]);

                logStatusChange($orderId, $oldStatus, $newStatus, $admin['id'], $statusNote);

                if ($customer) {
                    $order['status'] = $newStatus;
                    sendStatusUpdateEmail($order, $customer, $newStatus);
                }

                $success = 'Status updated to ' . formatStatus($newStatus) . '.';

                // Reload order data
                $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt->execute([$orderId]);
                $order = $stmt->fetch();
            }
        }

        // FILE UPLOAD
        elseif ($action === 'upload_file') {
            if (empty($_FILES['report_file']) || $_FILES['report_file']['error'] === UPLOAD_ERR_NO_FILE) {
                $error = 'Please select a file to upload.';
            } else {
                $validation = validateFileUpload($_FILES['report_file']);
                if ($validation !== true) {
                    $error = $validation;
                } else {
                    $result = handleFileUpload($_FILES['report_file'], $orderId);
                    if (!$result) {
                        $error = 'Failed to upload file. Please try again.';
                    } else {
                        $insertStmt = $db->prepare(
                            "INSERT INTO order_files (order_id, uploaded_by, file_name, file_path, file_type, file_size, created_at)
                             VALUES (?, ?, ?, ?, 'report', ?, NOW())"
                        );
                        $insertStmt->execute([
                            $orderId,
                            $admin['id'],
                            $result['file_name'],
                            $result['file_path'],
                            $result['file_size'],
                        ]);
                        $success = 'File uploaded successfully.';
                    }
                }
            }
        }

        // ADD NOTE
        elseif ($action === 'add_note') {
            $message = sanitize($_POST['message'] ?? '');
            if ($message === '') {
                $error = 'Please enter a message.';
            } else {
                $insertStmt = $db->prepare(
                    "INSERT INTO order_notes (order_id, user_id, message, created_at) VALUES (?, ?, ?, NOW())"
                );
                $insertStmt->execute([$orderId, $admin['id'], $message]);
                $success = 'Note added.';
            }
        }
    }
}

// Load files
$filesStmt = $db->prepare(
    "SELECT f.*, u.name AS uploader_name
     FROM order_files f
     LEFT JOIN users u ON f.uploaded_by = u.id
     WHERE f.order_id = ?
     ORDER BY f.created_at DESC"
);
$filesStmt->execute([$orderId]);
$files = $filesStmt->fetchAll();

// Load notes
$notesStmt = $db->prepare(
    "SELECT n.*, u.name AS author_name, u.role AS author_role
     FROM order_notes n
     LEFT JOIN users u ON n.user_id = u.id
     WHERE n.order_id = ?
     ORDER BY n.created_at ASC"
);
$notesStmt->execute([$orderId]);
$notes = $notesStmt->fetchAll();

// Decode project data
$projectData = [];
if (!empty($order['project_data'])) {
    $projectData = json_decode($order['project_data'], true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order <?= e($order['order_number']) ?> - Admin - Franklin Air Arkansas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Nunito+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
</head>
<body>
    <header class="admin-header">
        <a href="/admin/index.php" class="admin-header-brand">
            <span class="brand-name">Franklin Air Arkansas</span>
            <span class="brand-tag">Admin</span>
        </a>
        <nav class="admin-header-nav">
            <span class="admin-user"><?= e($admin['name']) ?></span>
            <a href="/admin/index.php?logout=1" class="btn-logout">Sign Out</a>
        </nav>
    </header>

    <main class="admin-content">
        <a href="/admin/index.php" class="back-link">&larr; Back to Dashboard</a>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= e($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <!-- Order Header -->
        <div class="order-header">
            <div class="order-header-left">
                <h1>Order <?= e($order['order_number']) ?></h1>
                <div class="order-meta">
                    Created <?= e(date('M j, Y \a\t g:i A', strtotime($order['created_at']))) ?>
                    <?php if ($order['rush']): ?>
                        &nbsp;&mdash;&nbsp;<span class="badge badge-revision">RUSH</span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <span class="badge <?= e(statusBadgeClass($order['status'])) ?>" style="font-size: 0.88rem; padding: 6px 18px;">
                    <?= e(formatStatus($order['status'])) ?>
                </span>
            </div>
        </div>

        <div class="order-grid">
            <!-- Left Column: Details -->
            <div>
                <!-- Customer Info -->
                <div class="detail-card">
                    <div class="detail-card-header">Customer Information</div>
                    <div class="detail-card-body">
                        <div class="detail-row">
                            <div class="detail-label">Name</div>
                            <div class="detail-value"><?= e($customer['name'] ?? 'Unknown') ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Email</div>
                            <div class="detail-value">
                                <a href="mailto:<?= e($customer['email'] ?? '') ?>"><?= e($customer['email'] ?? 'N/A') ?></a>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Phone</div>
                            <div class="detail-value">
                                <?php if (!empty($customer['phone'])): ?>
                                    <a href="tel:<?= e($customer['phone']) ?>"><?= e($customer['phone']) ?></a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Company</div>
                            <div class="detail-value"><?= e($customer['company'] ?? 'N/A') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Order Details -->
                <div class="detail-card">
                    <div class="detail-card-header">Order Details</div>
                    <div class="detail-card-body">
                        <div class="detail-row">
                            <div class="detail-label">Service</div>
                            <div class="detail-value"><?= e(getServiceLabel($order['service_type'])) ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Systems</div>
                            <div class="detail-value"><?= (int)$order['num_systems'] ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Rush</div>
                            <div class="detail-value"><?= $order['rush'] ? 'Yes (+$75)' : 'No' ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Price</div>
                            <div class="detail-value price-display"><?= e(formatPrice($order['price_cents'])) ?></div>
                        </div>
                        <?php if (!empty($order['notes'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Order Notes</div>
                                <div class="detail-value"><?= nl2br(e($order['notes'])) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Project Data -->
                <?php if (!empty($projectData)): ?>
                    <div class="detail-card">
                        <div class="detail-card-header">Project Data</div>
                        <div class="detail-card-body">
                            <?php foreach ($projectData as $key => $value): ?>
                                <?php
                                    if ($value === '' || $value === null) continue;
                                    $label = ucwords(str_replace(['_', '-'], ' ', $key));
                                    $displayValue = is_array($value) ? implode(', ', $value) : (string)$value;
                                ?>
                                <div class="detail-row">
                                    <div class="detail-label"><?= e($label) ?></div>
                                    <div class="detail-value"><?= nl2br(e($displayValue)) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Files -->
                <div class="detail-card">
                    <div class="detail-card-header">Files (<?= count($files) ?>)</div>
                    <div class="detail-card-body">
                        <?php if (empty($files)): ?>
                            <p style="color: var(--warm-gray); font-size: 0.92rem;">No files uploaded yet.</p>
                        <?php else: ?>
                            <ul class="file-list">
                                <?php foreach ($files as $file): ?>
                                    <li class="file-item">
                                        <div class="file-info">
                                            <span class="file-name"><?= e($file['file_name']) ?></span>
                                            <span class="file-meta">
                                                <?= e(ucfirst(str_replace('_', ' ', $file['file_type']))) ?>
                                                &middot; <?= e(number_format($file['file_size'] / 1024, 1)) ?> KB
                                                &middot; <?= e($file['uploader_name'] ?? 'Unknown') ?>
                                                &middot; <?= e(timeAgo($file['created_at'])) ?>
                                            </span>
                                        </div>
                                        <a href="/api/download-file.php?id=<?= (int)$file['id'] ?>" class="file-download">Download</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Notes Thread -->
                <div class="detail-card">
                    <div class="detail-card-header">Notes &amp; Messages (<?= count($notes) ?>)</div>
                    <div class="detail-card-body">
                        <?php if (!empty($notes)): ?>
                            <div class="notes-thread">
                                <?php foreach ($notes as $note): ?>
                                    <div class="note-item <?= ($note['author_role'] ?? '') === 'admin' ? 'note-admin' : '' ?>">
                                        <div class="note-header">
                                            <span class="note-author"><?= e($note['author_name'] ?? 'Unknown') ?></span>
                                            <span class="note-time"><?= e(date('M j, Y g:i A', strtotime($note['created_at']))) ?></span>
                                        </div>
                                        <div class="note-body"><?= e($note['message']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--warm-gray); font-size: 0.92rem;">No notes yet.</p>
                        <?php endif; ?>

                        <!-- Add Note Form -->
                        <form method="POST" action="" class="admin-form" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(44,36,32,0.06);">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="add_note">
                            <div class="form-group">
                                <label for="message">Add a Note</label>
                                <textarea id="message" name="message" rows="3" placeholder="Type your message..." required></textarea>
                            </div>
                            <button type="submit" class="btn-submit btn-secondary">Add Note</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Actions -->
            <div>
                <!-- Status Update -->
                <div class="detail-card">
                    <div class="detail-card-header">Update Status</div>
                    <div class="detail-card-body">
                        <form method="POST" action="" class="admin-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">

                            <div class="form-group">
                                <label for="new_status">New Status</label>
                                <select id="new_status" name="new_status" required>
                                    <option value="">Select status...</option>
                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="in_progress" <?= $order['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="revision_requested" <?= $order['status'] === 'revision_requested' ? 'selected' : '' ?>>Revision Requested</option>
                                    <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="status_note">Note (optional)</label>
                                <textarea id="status_note" name="status_note" rows="2" placeholder="Reason for status change..."></textarea>
                            </div>

                            <button type="submit" class="btn-submit">Update Status</button>
                        </form>
                    </div>
                </div>

                <!-- File Upload -->
                <div class="detail-card">
                    <div class="detail-card-header">Upload Report</div>
                    <div class="detail-card-body">
                        <form method="POST" action="" class="admin-form" enctype="multipart/form-data">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="upload_file">

                            <div class="form-group">
                                <label for="report_file">Select File</label>
                                <input type="file" id="report_file" name="report_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                <small style="display: block; margin-top: 6px; color: var(--warm-gray-light); font-size: 0.78rem;">
                                    PDF, JPG, or PNG. Max 10MB.
                                </small>
                            </div>

                            <button type="submit" class="btn-submit">Upload File</button>
                        </form>
                    </div>
                </div>

                <!-- Status History -->
                <?php
                    $logStmt = $db->prepare(
                        "SELECT l.*, u.name AS changed_by_name
                         FROM order_status_log l
                         LEFT JOIN users u ON l.changed_by = u.id
                         WHERE l.order_id = ?
                         ORDER BY l.created_at DESC
                         LIMIT 10"
                    );
                    $logStmt->execute([$orderId]);
                    $statusLog = $logStmt->fetchAll();
                ?>
                <?php if (!empty($statusLog)): ?>
                    <div class="detail-card">
                        <div class="detail-card-header">Status History</div>
                        <div class="detail-card-body">
                            <?php foreach ($statusLog as $log): ?>
                                <div style="padding: 10px 0; border-bottom: 1px solid rgba(44,36,32,0.04); font-size: 0.88rem;">
                                    <div>
                                        <span class="badge <?= e(statusBadgeClass($log['old_status'])) ?>" style="font-size: 0.72rem;"><?= e(formatStatus($log['old_status'])) ?></span>
                                        &rarr;
                                        <span class="badge <?= e(statusBadgeClass($log['new_status'])) ?>" style="font-size: 0.72rem;"><?= e(formatStatus($log['new_status'])) ?></span>
                                    </div>
                                    <div style="margin-top: 4px; color: var(--warm-gray-light); font-size: 0.78rem;">
                                        <?= e($log['changed_by_name'] ?? 'System') ?>
                                        &middot; <?= e(timeAgo($log['created_at'])) ?>
                                    </div>
                                    <?php if (!empty($log['note'])): ?>
                                        <div style="margin-top: 4px; color: var(--warm-gray); font-size: 0.82rem; font-style: italic;">
                                            <?= e($log['note']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
