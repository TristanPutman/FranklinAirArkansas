<?php
// ONE-TIME SETUP SCRIPT — Creates the admin user for Thomas
// DELETE THIS FILE AFTER RUNNING IT
//
// Usage: Visit https://franklinairarkansas.com/setup-admin.php in your browser
// It will create the admin account and display the result.
// Then IMMEDIATELY delete this file from the server.

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=utf-8');

$adminEmail    = 'Tlfranklinhvac@gmail.com';
$adminName     = 'Thomas Franklin';
$adminPhone    = '4792072454';
$adminPassword = 'FranklinAir2026!'; // Change this after first login

echo '<h1>Franklin Air Arkansas — Admin Setup</h1>';

try {
    $db = getDB();

    // Check if admin already exists
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$adminEmail]);
    if ($stmt->fetch()) {
        echo '<p style="color:orange;"><strong>Admin user already exists.</strong> No changes made.</p>';
        echo '<p>You can log in at <a href="/admin/login.php">/admin/login.php</a></p>';
        echo '<p style="color:red;"><strong>DELETE THIS FILE NOW.</strong></p>';
        exit;
    }

    // Create admin user
    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare(
        'INSERT INTO users (email, password_hash, name, phone, role) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$adminEmail, $hash, $adminName, $adminPhone, 'admin']);

    echo '<p style="color:green;"><strong>Admin user created successfully!</strong></p>';
    echo '<ul>';
    echo '<li><strong>Email:</strong> ' . htmlspecialchars($adminEmail) . '</li>';
    echo '<li><strong>Password:</strong> ' . htmlspecialchars($adminPassword) . '</li>';
    echo '<li><strong>Name:</strong> ' . htmlspecialchars($adminName) . '</li>';
    echo '</ul>';
    echo '<p>Log in at <a href="/admin/login.php">/admin/login.php</a></p>';
    echo '<hr>';
    echo '<p style="color:red;font-size:1.2em;"><strong>IMPORTANT: DELETE THIS FILE FROM THE SERVER IMMEDIATELY.</strong></p>';
    echo '<p>This file contains a hardcoded password and should not remain on the server.</p>';

} catch (Exception $e) {
    echo '<p style="color:red;"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Make sure you have:</p>';
    echo '<ol>';
    echo '<li>Created the MySQL database</li>';
    echo '<li>Run schema.sql in phpMyAdmin</li>';
    echo '<li>Updated config.php with your database password</li>';
    echo '</ol>';
}
