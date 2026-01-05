<?php
require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/db.php';
require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/../../src/auth.php';

requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

$lastName = $input['last_name'] ?? '';
$firstName = $input['first_name'] ?? '';
$birthDate = $input['birth_date'] ?? '';
$birthPlace = $input['birth_place'] ?? '';
$gender = $input['gender'] ?? '';

if (empty($lastName) || empty($firstName) || empty($birthDate) || empty($gender)) {
    echo json_encode(['success' => false, 'error' => 'Dati mancanti']);
    exit;
}

$fiscalCode = calculateItalianFiscalCode($lastName, $firstName, $birthDate, $birthPlace, $gender);

echo json_encode([
    'success' => true,
    'fiscal_code' => $fiscalCode
]);
