<?php
/**
 * Database Configuration
 * This file is created during installation
 */

// Check if configuration exists (created by install.php)
if (!defined('DB_HOST')) {
    if (file_exists(__DIR__ . '/config_local.php')) {
        require_once __DIR__ . '/config_local.php';
    } else {
        // Default configuration (will be overwritten during installation)
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'associazione');
        define('DB_USER', 'root');
        define('DB_PASS', '');
        define('DB_CHARSET', 'utf8mb4');
    }
}

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}
