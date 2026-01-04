<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/inc/auth.php';

$config = require __DIR__ . '/../../src/config.php';
$basePath = $config['app']['base_path'];

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Logout
portalLogout();

// Redirect to login
header('Location: ' . $basePath . 'portal/login.php');
exit;
