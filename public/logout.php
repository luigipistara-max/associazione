<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/audit.php';

$config = require __DIR__ . '/../src/config.php';

// Log logout before destroying session
if (isLoggedIn()) {
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    logLogout($userId, $username);
}

logoutUser();

$basePath = $config['app']['base_path'] ?? '/';
header('Location: ' . $basePath . 'login.php');
exit;
