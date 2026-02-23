<?php
// Session-based authentication helpers

require_once __DIR__ . '/db.php';

function authStart() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function authLogin($email, $password) {
    $db = getDB();
    $stmt = $db->prepare('SELECT id, email, password_hash, name, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    authStart();
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];

    return $user;
}

function authRegister($name, $email, $password, $phone = '', $company = '') {
    $db = getDB();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare(
        'INSERT INTO users (name, email, password_hash, phone, company) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $email, $hash, $phone, $company]);

    return $db->lastInsertId();
}

function authGetUser() {
    authStart();
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'],
    ];
}

function authRequire($role = null) {
    $user = authGetUser();
    if (!$user) {
        header('Location: /portal/login.php');
        exit;
    }
    if ($role && $user['role'] !== $role) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
    return $user;
}

function authRequireAdmin() {
    $user = authGetUser();
    if (!$user) {
        header('Location: /admin/login.php');
        exit;
    }
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }
    return $user;
}

function authLogout() {
    authStart();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function authUserExists($email) {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    return $stmt->fetch() ? true : false;
}

function authCreatePasswordReset($email) {
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        return false;
    }

    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $db->prepare(
        'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
    );
    $stmt->execute([$user['id'], $token, $expires]);

    return $token;
}

function authResetPassword($token, $newPassword) {
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0'
    );
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        return false;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
       ->execute([$hash, $reset['user_id']]);

    $db->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')
       ->execute([$token]);

    return true;
}
