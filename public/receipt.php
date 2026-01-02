<?php
/**
 * Receipt Generation and Display
 * Genera e mostra ricevute per pagamenti quote
 */

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/pdf.php';

requireLogin();

$feeId = $_GET['fee_id'] ?? null;
$format = $_GET['format'] ?? 'html';

if (!$feeId || !is_numeric($feeId)) {
    die('ID quota non valido');
}

// Recupera quota con dati socio
$stmt = $pdo->prepare("
    SELECT mf.*, m.first_name, m.last_name
    FROM " . table('member_fees') . " mf
    JOIN " . table('members') . " m ON mf.member_id = m.id
    WHERE mf.id = ?
");
$stmt->execute([$feeId]);
$fee = $stmt->fetch();

if (!$fee) {
    die('Quota non trovata');
}

// Verifica permessi: admin/operatore possono vedere tutto, 
// ma se implementassimo login soci, controllare che il socio veda solo le proprie
if (!isAdmin() && !isLoggedIn()) {
    die('Accesso negato');
}

// Verifica che la quota sia stata pagata
if ($fee['status'] !== 'paid') {
    die('La ricevuta può essere generata solo per quote pagate');
}

// Genera HTML ricevuta
$html = generateReceiptHTML($feeId);

if (!$html) {
    die('Errore generazione ricevuta');
}

// Output in base al formato richiesto
if ($format === 'pdf') {
    // Per ora il PDF è uguale all'HTML (stampabile)
    // In futuro si potrebbe usare una libreria per vero PDF
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
} else {
    // HTML
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}
