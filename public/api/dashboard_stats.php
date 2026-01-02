<?php
/**
 * API Statistiche Dashboard
 * Restituisce dati JSON per grafici Chart.js
 */

require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';

// Solo utenti loggati
requireLogin();

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'all';

$response = [];

// Andamento finanziario ultimi 12 mesi
if ($type === 'all' || $type === 'financial_trend') {
    $response['financial_trend'] = getFinancialTrend(12);
}

// Entrate per categoria
if ($type === 'all' || $type === 'income_by_category') {
    $response['income_by_category'] = getIncomeByCategory();
}

// Soci per stato
if ($type === 'all' || $type === 'members_by_status') {
    $response['members_by_status'] = getMembersByStatus();
}

// Quote anno corrente
if ($type === 'all' || $type === 'fees_status') {
    $response['fees_status'] = getFeesStatus();
}

echo json_encode($response);
