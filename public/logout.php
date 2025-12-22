<?php
require_once __DIR__ . '/../src/auth.php';

$config = require __DIR__ . '/../src/config.php';

logoutUser();

$basePath = $config['app']['base_path'] ?? '/';
header('Location: ' . $basePath . 'login.php');
exit;
