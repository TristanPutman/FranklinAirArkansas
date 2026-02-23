<?php
require_once __DIR__ . '/../includes/auth.php';

authLogout();

header('Location: /portal/login.php');
exit;
