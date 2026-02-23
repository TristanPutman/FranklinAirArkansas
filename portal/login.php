<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/helpers.php';

authStart();

// If already logged in, redirect to dashboard
$user = authGetUser();
if ($user) {
    header('Location: /portal/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrfVerify($token)) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($email === '' || $password === '') {
            $error = 'Please enter both email and password.';
        } elseif (!validateEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            $result = authLogin($email, $password);
            if ($result) {
                header('Location: /portal/dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | Franklin Air Arkansas</title>

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

<div class="login-wrap">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-brand-name">Franklin Air Arkansas</div>
            <div class="login-brand-tag">HVAC Design Portal</div>
        </div>

        <h1 class="login-title">Sign In to Your Account</h1>

        <?php if ($error): ?>
            <div class="portal-alert portal-alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="" class="portal-form" novalidate>
            <?= csrfField() ?>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="you@example.com"
                    value="<?= e($_POST['email'] ?? '') ?>"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="portal-btn portal-btn-primary portal-btn-full">
                Sign In
            </button>
        </form>

        <div class="login-links">
            <a href="/portal/reset-password.php">Forgot your password?</a>
        </div>
    </div>
</div>

</body>
</html>
