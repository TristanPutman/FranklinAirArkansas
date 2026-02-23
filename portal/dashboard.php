<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/pricing.php';

$user = authRequire();

$db = getDB();
$stmt = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders | Franklin Air Arkansas</title>

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
        <div class="dashboard-header">
            <div class="dashboard-welcome">
                <h1>Welcome, <?= e($user['name']) ?></h1>
                <p>Manage your HVAC design orders below.</p>
            </div>
            <a href="/order.html" class="portal-btn portal-btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Order
            </a>
        </div>

        <?php if (empty($orders)): ?>
            <div class="orders-empty">
                <div class="orders-empty-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--warm-gray-light)"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <h2>No Orders Yet</h2>
                <p>Ready to get started? Place your first HVAC design order.</p>
                <a href="/order.html" class="portal-btn portal-btn-primary">Place Your First Order</a>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <a href="/portal/order.php?id=<?= (int)$order['id'] ?>" class="order-card">
                    <div class="order-card-top">
                        <span class="order-card-number"><?= e($order['order_number']) ?></span>
                        <span class="badge <?= e(statusBadgeClass($order['status'])) ?>"><?= e(formatStatus($order['status'])) ?></span>
                    </div>
                    <div class="order-card-service"><?= e(getServiceLabel($order['service_type'])) ?></div>
                    <div class="order-card-meta">
                        <span><?= e(formatPrice($order['price_cents'])) ?></span>
                        <span><?= e($order['num_systems']) ?> system<?= $order['num_systems'] != 1 ? 's' : '' ?></span>
                        <?php if ($order['rush']): ?>
                            <span style="color: var(--copper); font-weight: 700;">Rush</span>
                        <?php endif; ?>
                        <span><?= e(timeAgo($order['created_at'])) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>

</body>
</html>
