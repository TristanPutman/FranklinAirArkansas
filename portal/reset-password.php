<?php
// Password reset flow — request reset link or set new password
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/email.php';

authStart();

$error = '';
$success = '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$mode = $token ? 'reset' : 'request';

// --- Handle POST submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!csrfVerify($csrfToken)) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        if ($mode === 'request') {
            // Mode 1: User submitted their email to request a reset link
            $email = trim($_POST['email'] ?? '');

            if (empty($email)) {
                $error = 'Please enter your email address.';
            } elseif (!validateEmail($email)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Attempt to create a password reset token
                $resetToken = authCreatePasswordReset($email);

                if ($resetToken) {
                    // Build the reset link
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'] ?? 'franklinairarkansas.com';
                    $resetLink = "{$protocol}://{$host}/portal/reset-password.php?token=" . urlencode($resetToken);

                    // Send the reset email
                    $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Password Reset</title></head>
<body style="margin:0;padding:0;background-color:#f2ede8;">
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f2ede8;"><tr><td align="center" style="padding:32px 16px;">
<table border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;width:100%;">
<tr><td bgcolor="#1B2A4A" style="padding:28px 36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr><td style="font-family:Georgia,serif;font-size:22px;color:#FFF5EB;">Franklin Air Arkansas</td></tr>
        <tr><td style="font-family:Arial,sans-serif;font-size:11px;color:rgba(255,245,235,0.5);letter-spacing:1.5px;text-transform:uppercase;padding-top:6px;">Password Reset</td></tr>
    </table>
</td></tr>
<tr><td bgcolor="#C45A2D" style="height:3px;font-size:0;">&nbsp;</td></tr>
<tr><td bgcolor="#ffffff" style="padding:36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr><td style="font-family:Georgia,serif;font-size:22px;color:#1B2A4A;padding-bottom:12px;">Reset Your Password</td></tr>
        <tr><td style="font-family:Arial,sans-serif;font-size:15px;color:#6B5E54;line-height:1.7;padding-bottom:24px;">
            We received a request to reset your password. Click the button below to choose a new password. This link will expire in 1 hour.
        </td></tr>
    </table>
</td></tr>
<tr><td bgcolor="#ffffff" style="padding:0 36px 36px;" align="center">
    <table border="0" cellpadding="0" cellspacing="0"><tr>
        <td bgcolor="#C45A2D" style="padding:14px 36px;border-radius:6px;"><a href="{$resetLink}" style="font-family:Arial,sans-serif;font-size:14px;color:#ffffff;text-decoration:none;font-weight:bold;">Reset Password &rarr;</a></td>
    </tr></table>
</td></tr>
<tr><td bgcolor="#ffffff" style="padding:0 36px 36px;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td style="font-family:Arial,sans-serif;font-size:13px;color:#8a8580;line-height:1.6;">
        If you didn't request this, you can safely ignore this email. Your password will remain unchanged.
    </td></tr></table>
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

                    sendEmail($email, 'Password Reset — Franklin Air Arkansas', $html);
                }

                // Always show success message (don't reveal if email exists)
                $success = 'If an account with that email exists, we\'ve sent a password reset link. Please check your inbox.';
            }
        } elseif ($mode === 'reset') {
            // Mode 2: User submitted a new password with a valid token
            $token = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            if (empty($password)) {
                $error = 'Please enter a new password.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($password !== $passwordConfirm) {
                $error = 'Passwords do not match.';
            } else {
                $result = authResetPassword($token, $password);

                if ($result) {
                    $success = 'Your password has been reset successfully. You can now log in with your new password.';
                    $mode = 'done';
                } else {
                    $error = 'This reset link is invalid or has expired. Please request a new one.';
                }
            }
        }
    }
}

// Generate CSRF token for the form
$csrf = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Franklin Air Arkansas</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/portal.css">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
</head>
<body>

<div class="login-wrap">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-brand-name">Franklin Air Arkansas</div>
            <div class="login-brand-tag">HVAC Design Portal</div>
        </div>

        <?php if ($mode === 'done'): ?>
            <!-- Password reset successful -->
            <?php if ($success): ?>
                <div class="portal-alert portal-alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <div class="login-links" style="margin-top: 24px;">
                <a href="/portal/login.php">Back to Login</a>
            </div>

        <?php elseif ($mode === 'request'): ?>
            <!-- Request reset link form -->
            <h1 class="login-title">Reset Your Password</h1>

            <?php if ($error): ?>
                <div class="portal-alert portal-alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="portal-alert portal-alert-success"><?= htmlspecialchars($success) ?></div>
            <?php else: ?>
                <p style="text-align:center;color:var(--warm-gray);font-size:0.92rem;margin-bottom:24px;line-height:1.6;">
                    Enter your email address and we'll send you a link to reset your password.
                </p>

                <form method="POST" action="/portal/reset-password.php" class="portal-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required autocomplete="email"
                               placeholder="you@example.com"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <button type="submit" class="portal-btn portal-btn-primary portal-btn-full">
                        Send Reset Link
                    </button>
                </form>
            <?php endif; ?>

            <div class="login-links">
                <a href="/portal/login.php">Back to Login</a>
            </div>

        <?php elseif ($mode === 'reset'): ?>
            <!-- New password form -->
            <h1 class="login-title">Choose a New Password</h1>

            <?php if ($error): ?>
                <div class="portal-alert portal-alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="/portal/reset-password.php?token=<?= htmlspecialchars(urlencode($token)) ?>" class="portal-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required
                           autocomplete="new-password"
                           placeholder="Minimum 8 characters">
                </div>

                <div class="form-group">
                    <label for="password_confirm">Confirm New Password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required
                           autocomplete="new-password"
                           placeholder="Re-enter your new password">
                </div>

                <button type="submit" class="portal-btn portal-btn-primary portal-btn-full">
                    Reset Password
                </button>
            </form>

            <div class="login-links">
                <a href="/portal/login.php">Back to Login</a>
            </div>

        <?php endif; ?>
    </div>
</div>

</body>
</html>
