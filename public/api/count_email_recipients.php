<?php
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/../../src/db.php';

header('Content-Type: application/json');

requireLogin();
requireAdmin();

$input = json_decode(file_get_contents('php://input'), true);

$filterType = $input['filter_type'] ?? 'all';
$eventId = $input['event_id'] ?? null;

$params = [];
if ($filterType === 'event_registered' && $eventId) {
    $params['event_id'] = $eventId;
}

$count = countMassEmailRecipients($filterType, $params);

echo json_encode(['count' => $count]);
