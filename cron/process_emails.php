<?php
/**
 * Endpoint per cron job esterno
 * 
 * Configurazione cron esterno (es. cron-job.org):
 * URL: https://tuosito.altervista.org/gest/cron/process_emails.php?token=TUO_TOKEN
 * Frequenza: ogni 5 minuti
 */

// Disabilita output buffering per log immediato
ob_implicit_flush(true);

// Carica configurazione
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/email.php';

// Verifica token di sicurezza
$token = $_GET['token'] ?? '';
$validToken = getSetting('cron_token', '');

if (empty($validToken)) {
    http_response_code(500);
    die(json_encode(['error' => 'Cron token not configured. Set it in Settings > API']));
}

if (!hash_equals($validToken, $token)) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid token']));
}

// Processa coda email
$limit = (int) ($_GET['limit'] ?? 20);
$stats = processEmailQueue($limit);

// Output JSON per logging
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'processed' => $stats['processed'],
    'sent' => $stats['sent'],
    'failed' => $stats['failed'],
    'errors' => $stats['errors'],
    'timestamp' => date('Y-m-d H:i:s')
]);
