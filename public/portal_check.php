<?php
/**
 * Portal Installation Checker
 * Verifica che tutte le componenti del portale siano installate correttamente
 */

require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

$config = require __DIR__ . '/../src/config.php';

header('Content-Type: text/html; charset=utf-8');

$checks = [];
$allPassed = true;

// Check database columns
function checkDatabaseColumn($table, $column, $description) {
    global $pdo, $checks, $allPassed;
    
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM " . table($table) . " LIKE '$column'");
        $exists = $stmt->rowCount() > 0;
        
        $checks[] = [
            'name' => $description,
            'passed' => $exists,
            'message' => $exists ? 'OK' : 'Colonna mancante: ' . $table . '.' . $column
        ];
        
        if (!$exists) $allPassed = false;
        return $exists;
    } catch (PDOException $e) {
        $checks[] = [
            'name' => $description,
            'passed' => false,
            'message' => 'Errore: ' . $e->getMessage()
        ];
        $allPassed = false;
        return false;
    }
}

// Check table exists
function checkDatabaseTable($table, $description) {
    global $pdo, $checks, $allPassed;
    
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '" . table($table) . "'");
        $exists = $stmt->rowCount() > 0;
        
        $checks[] = [
            'name' => $description,
            'passed' => $exists,
            'message' => $exists ? 'OK' : 'Tabella mancante: ' . $table
        ];
        
        if (!$exists) $allPassed = false;
        return $exists;
    } catch (PDOException $e) {
        $checks[] = [
            'name' => $description,
            'passed' => false,
            'message' => 'Errore: ' . $e->getMessage()
        ];
        $allPassed = false;
        return false;
    }
}

// Check file exists
function checkFile($path, $description) {
    global $checks, $allPassed;
    
    $fullPath = __DIR__ . '/../' . $path;
    $exists = file_exists($fullPath);
    
    $checks[] = [
        'name' => $description,
        'passed' => $exists,
        'message' => $exists ? 'OK' : 'File mancante: ' . $path
    ];
    
    if (!$exists) $allPassed = false;
    return $exists;
}

// Check setting exists
function checkSetting($key, $description) {
    global $checks, $allPassed;
    
    $value = getSetting($key);
    $exists = !empty($value);
    
    $checks[] = [
        'name' => $description,
        'passed' => $exists,
        'message' => $exists ? 'Configurato' : 'Non configurato (opzionale)'
    ];
    
    // Settings are optional, don't fail
    return $exists;
}

echo '<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Installation Check</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0"><i class="bi bi-clipboard-check"></i> Portal Installation Check</h3>
            </div>
            <div class="card-body">';

// Run checks
echo '<h5>Database - Tabella Members</h5>';
checkDatabaseColumn('members', 'portal_password', 'Colonna portal_password');
checkDatabaseColumn('members', 'portal_token', 'Colonna portal_token');
checkDatabaseColumn('members', 'portal_token_expires', 'Colonna portal_token_expires');
checkDatabaseColumn('members', 'photo_url', 'Colonna photo_url');
checkDatabaseColumn('members', 'last_portal_login', 'Colonna last_portal_login');

echo '<h5 class="mt-4">Database - Tabella Member Groups</h5>';
checkDatabaseColumn('member_groups', 'is_hidden', 'Colonna is_hidden');
checkDatabaseColumn('member_groups', 'is_restricted', 'Colonna is_restricted');

echo '<h5 class="mt-4">Database - Tabella Member Fees</h5>';
checkDatabaseColumn('member_fees', 'payment_pending', 'Colonna payment_pending');
checkDatabaseColumn('member_fees', 'payment_reference', 'Colonna payment_reference');
checkDatabaseColumn('member_fees', 'paypal_transaction_id', 'Colonna paypal_transaction_id');
checkDatabaseColumn('member_fees', 'payment_confirmed_by', 'Colonna payment_confirmed_by');
checkDatabaseColumn('member_fees', 'payment_confirmed_at', 'Colonna payment_confirmed_at');

echo '<h5 class="mt-4">Database - Nuove Tabelle</h5>';
checkDatabaseTable('member_group_requests', 'Tabella member_group_requests');

echo '<h5 class="mt-4">File Portale</h5>';
checkFile('public/portal/inc/auth.php', 'File autenticazione');
checkFile('public/portal/inc/header.php', 'Header portale');
checkFile('public/portal/inc/footer.php', 'Footer portale');
checkFile('public/portal/login.php', 'Pagina login');
checkFile('public/portal/logout.php', 'Pagina logout');
checkFile('public/portal/register.php', 'Pagina registrazione');
checkFile('public/portal/forgot_password.php', 'Recupero password');
checkFile('public/portal/reset_password.php', 'Reset password');
checkFile('public/portal/index.php', 'Dashboard');
checkFile('public/portal/profile.php', 'Profilo');
checkFile('public/portal/photo.php', 'Upload foto');
checkFile('public/portal/card.php', 'Tesserino');

echo '<h5 class="mt-4">Impostazioni (Opzionali)</h5>';
checkSetting('imgbb_api_key', 'API Key ImgBB');
checkSetting('paypal_mode', 'Modalità PayPal');

echo '<hr>';
echo '<h4>Riepilogo</h4>';
echo '<table class="table">';
foreach ($checks as $check) {
    $icon = $check['passed'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>';
    echo '<tr>';
    echo '<td width="40">' . $icon . '</td>';
    echo '<td>' . htmlspecialchars($check['name']) . '</td>';
    echo '<td class="text-end"><small class="text-muted">' . htmlspecialchars($check['message']) . '</small></td>';
    echo '</tr>';
}
echo '</table>';

if ($allPassed) {
    echo '<div class="alert alert-success">';
    echo '<i class="bi bi-check-circle-fill"></i> <strong>Tutti i controlli obbligatori sono passati!</strong><br>';
    echo 'Il portale è installato correttamente e pronto per l\'uso.';
    echo '</div>';
    echo '<a href="' . htmlspecialchars($config['app']['base_path']) . 'portal/login.php" class="btn btn-primary">';
    echo '<i class="bi bi-door-open"></i> Vai al Portale Soci';
    echo '</a>';
} else {
    echo '<div class="alert alert-danger">';
    echo '<i class="bi bi-exclamation-triangle-fill"></i> <strong>Alcuni controlli hanno fallito.</strong><br>';
    echo 'Verifica i messaggi di errore sopra e correggi i problemi.<br>';
    echo 'Potrebbe essere necessario eseguire la migration SQL.';
    echo '</div>';
    echo '<a href="' . htmlspecialchars($config['app']['base_path']) . '" class="btn btn-secondary">';
    echo '<i class="bi bi-arrow-left"></i> Torna al Gestionale';
    echo '</a>';
}

echo '            </div>
        </div>
        <div class="text-center mt-3 text-muted small">
            <p>Portal Soci - Parte 1 | AssoLife</p>
        </div>
    </div>
</body>
</html>';
