<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/functions.php';

requireLogin();

// Get year parameter
$yearId = isset($_GET['year']) ? (int)$_GET['year'] : null;

if (!$yearId) {
    die("Anno sociale non specificato");
}

try {
    // Get year info
    $stmt = $pdo->prepare("SELECT * FROM " . table('social_years') . " WHERE id = ?");
    $stmt->execute([$yearId]);
    $year = $stmt->fetch();
    
    if (!$year) {
        die("Anno sociale non trovato");
    }
    
    // Get income
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as category_name, m.first_name, m.last_name, m.fiscal_code
        FROM " . table('income') . " i
        LEFT JOIN " . table('income_categories') . " c ON i.category_id = c.id
        LEFT JOIN " . table('members') . " m ON i.member_id = m.id
        WHERE i.social_year_id = ?
        ORDER BY i.transaction_date, i.created_at
    ");
    $stmt->execute([$yearId]);
    $income = $stmt->fetchAll();
    
    // Get expenses
    $stmt = $pdo->prepare("
        SELECT e.*, c.name as category_name
        FROM " . table('expenses') . " e
        LEFT JOIN " . table('expense_categories') . " c ON e.category_id = c.id
        WHERE e.social_year_id = ?
        ORDER BY e.transaction_date, e.created_at
    ");
    $stmt->execute([$yearId]);
    $expenses = $stmt->fetchAll();
    
    // Get summary
    $stmt = $pdo->prepare("
        SELECT c.name, COALESCE(SUM(i.amount), 0) as total
        FROM " . table('income_categories') . " c
        LEFT JOIN " . table('income') . " i ON c.id = i.category_id AND i.social_year_id = ?
        WHERE c.is_active = 1
        GROUP BY c.id, c.name, c.sort_order
        ORDER BY c.sort_order, c.name
    ");
    $stmt->execute([$yearId]);
    $incomeSummary = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT c.name, COALESCE(SUM(e.amount), 0) as total
        FROM " . table('expense_categories') . " c
        LEFT JOIN " . table('expenses') . " e ON c.id = e.category_id AND e.social_year_id = ?
        WHERE c.is_active = 1
        GROUP BY c.id, c.name, c.sort_order
        ORDER BY c.sort_order, c.name
    ");
    $stmt->execute([$yearId]);
    $expenseSummary = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Errore database: " . htmlspecialchars($e->getMessage()));
}

// Calculate totals
$totalIncome = array_sum(array_column($income, 'amount'));
$totalExpense = array_sum(array_column($expenses, 'amount'));
$balance = $totalIncome - $totalExpense;

// Generate CSV (Excel-compatible)
$filename = 'rendiconto_' . preg_replace('/[^a-zA-Z0-9]/', '_', $year['name']) . '_' . date('Ymd') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write header info
fputcsv($output, ['RENDICONTO FINANZIARIO'], ';');
fputcsv($output, ['Anno Sociale', $year['name']], ';');
fputcsv($output, ['Periodo', formatDate($year['start_date']) . ' - ' . formatDate($year['end_date'])], ';');
fputcsv($output, ['Data Esportazione', date('d/m/Y H:i')], ';');
fputcsv($output, [], ';');

// INCOME SECTION
fputcsv($output, ['ENTRATE'], ';');
fputcsv($output, ['Data', 'Categoria', 'Socio', 'CF Socio', 'Metodo', 'Ricevuta', 'Importo', 'Note'], ';');

foreach ($income as $row) {
    $socio = $row['member_id'] ? ($row['last_name'] . ' ' . $row['first_name']) : '';
    fputcsv($output, [
        formatDate($row['transaction_date']),
        $row['category_name'],
        $socio,
        $row['fiscal_code'] ?? '',
        $row['payment_method'],
        $row['receipt_number'],
        number_format($row['amount'], 2, ',', ''),
        $row['notes']
    ], ';');
}

fputcsv($output, ['', '', '', '', '', 'TOTALE ENTRATE', number_format($totalIncome, 2, ',', '')], ';');
fputcsv($output, [], ';');

// INCOME SUMMARY
fputcsv($output, ['RIEPILOGO ENTRATE PER CATEGORIA'], ';');
fputcsv($output, ['Categoria', 'Importo'], ';');

foreach ($incomeSummary as $row) {
    if ($row['total'] > 0) {
        fputcsv($output, [
            $row['name'],
            number_format($row['total'], 2, ',', '')
        ], ';');
    }
}

fputcsv($output, ['TOTALE', number_format($totalIncome, 2, ',', '')], ';');
fputcsv($output, [], ';');
fputcsv($output, [], ';');

// EXPENSE SECTION
fputcsv($output, ['USCITE'], ';');
fputcsv($output, ['Data', 'Categoria', 'Metodo', 'Ricevuta', 'Importo', 'Note'], ';');

foreach ($expenses as $row) {
    fputcsv($output, [
        formatDate($row['transaction_date']),
        $row['category_name'],
        $row['payment_method'],
        $row['receipt_number'],
        number_format($row['amount'], 2, ',', ''),
        $row['notes']
    ], ';');
}

fputcsv($output, ['', '', '', 'TOTALE USCITE', number_format($totalExpense, 2, ',', '')], ';');
fputcsv($output, [], ';');

// EXPENSE SUMMARY
fputcsv($output, ['RIEPILOGO USCITE PER CATEGORIA'], ';');
fputcsv($output, ['Categoria', 'Importo'], ';');

foreach ($expenseSummary as $row) {
    if ($row['total'] > 0) {
        fputcsv($output, [
            $row['name'],
            number_format($row['total'], 2, ',', '')
        ], ';');
    }
}

fputcsv($output, ['TOTALE', number_format($totalExpense, 2, ',', '')], ';');
fputcsv($output, [], ';');
fputcsv($output, [], ';');

// FINAL BALANCE
fputcsv($output, ['RIEPILOGO FINALE'], ';');
fputcsv($output, ['Totale Entrate', number_format($totalIncome, 2, ',', '')], ';');
fputcsv($output, ['Totale Uscite', number_format($totalExpense, 2, ',', '')], ';');
fputcsv($output, ['RISULTATO D\'ESERCIZIO', number_format($balance, 2, ',', '')], ';');
fputcsv($output, [], ';');
fputcsv($output, [], ';');
fputcsv($output, ['Powered with AssoLife by Luigi Pistarà'], ';');

fclose($output);
exit;
