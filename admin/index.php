<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pricing.php';
require_once __DIR__ . '/../includes/db.php';

$admin = authRequireAdmin();
$db = getDB();

// Handle logout
if (isset($_GET['logout'])) {
    authLogout();
    header('Location: /admin/login.php');
    exit;
}

// Status filter
$statusFilter = sanitize($_GET['status'] ?? '');
$validStatuses = ['pending', 'in_progress', 'revision_requested', 'completed', 'cancelled'];
if ($statusFilter !== '' && !in_array($statusFilter, $validStatuses)) {
    $statusFilter = '';
}

// Summary stats
$statsStmt = $db->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
    FROM orders");
$stats = $statsStmt->fetch();

// Fetch orders
$sql = "SELECT o.*, u.name AS customer_name, u.email AS customer_email
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id";
$params = [];

if ($statusFilter !== '') {
    $sql .= " WHERE o.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY o.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Franklin Air Arkansas</title>
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
        <h1 class="admin-page-title">Dashboard</h1>
        <p class="admin-page-subtitle">Manage HVAC design orders</p>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card-admin">
                <div class="stat-number"><?= e($stats['total']) ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card-admin stat-pending">
                <div class="stat-number"><?= e($stats['pending']) ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card-admin stat-progress">
                <div class="stat-number"><?= e($stats['in_progress']) ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card-admin stat-completed">
                <div class="stat-number"><?= e($stats['completed']) ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <span class="filter-label">Filter:</span>
            <a href="/admin/index.php" class="filter-btn <?= $statusFilter === '' ? 'active' : '' ?>">All</a>
            <a href="/admin/index.php?status=pending" class="filter-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="/admin/index.php?status=in_progress" class="filter-btn <?= $statusFilter === 'in_progress' ? 'active' : '' ?>">In Progress</a>
            <a href="/admin/index.php?status=revision_requested" class="filter-btn <?= $statusFilter === 'revision_requested' ? 'active' : '' ?>">Revision Requested</a>
            <a href="/admin/index.php?status=completed" class="filter-btn <?= $statusFilter === 'completed' ? 'active' : '' ?>">Completed</a>
            <a href="/admin/index.php?status=cancelled" class="filter-btn <?= $statusFilter === 'cancelled' ? 'active' : '' ?>">Cancelled</a>
        </div>

        <!-- Orders Table -->
        <div class="orders-table-wrap">
            <?php if (empty($orders)): ?>
                <div class="empty-state">
                    <p>No orders found.</p>
                    <span class="empty-hint">
                        <?= $statusFilter !== '' ? 'Try a different filter or view all orders.' : 'Orders will appear here when customers place them.' ?>
                    </span>
                </div>
            <?php else: ?>
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Price</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <a href="/admin/order.php?id=<?= (int)$order['id'] ?>" class="order-link">
                                        <?= e($order['order_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="customer-name"><?= e($order['customer_name'] ?? 'Unknown') ?></div>
                                    <div class="customer-email"><?= e($order['customer_email'] ?? '') ?></div>
                                </td>
                                <td><?= e(getServiceLabel($order['service_type'])) ?></td>
                                <td>
                                    <span class="badge <?= e(statusBadgeClass($order['status'])) ?>">
                                        <?= e(formatStatus($order['status'])) ?>
                                    </span>
                                </td>
                                <td><?= e(formatPrice($order['price_cents'])) ?></td>
                                <td><?= e(timeAgo($order['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
