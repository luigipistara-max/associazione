<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/../../src/auth.php';

requireLogin();

header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'number' => getNextMembershipNumber()
]);
