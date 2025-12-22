<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/functions.php';
require_once __DIR__ . '/../src/db.php';

requireLogin();



// Get year filter
$yearId = $_GET['year'] ?? '';
$yearFilter = '';
$yearName = 'Tutti';

if ($yearId) {
    $yearFilter = "AND m.social_year_id = " . (int)$yearId;
    $stmt = $pdo->prepare("SELECT name FROM " . table('social_years') . " WHERE id = ?");
    $stmt->execute([$yearId]);
    $year = $stmt->fetch();
    if ($year) {
        $yearName = $year['name'];
    }
}

// Get all movements
$stmt = $pdo->query("
    SELECT m.*, 
           CASE 
               WHEN m.type = 'income' THEN ic.name
               WHEN m.type = 'expense' THEN ec.name
           END as category_name,
           sy.name as year_name,
           mem.first_name, mem.last_name, mem.tax_code
    FROM movements m
    LEFT JOIN " . table('income_categories') . " ic ON m.type = 'income' AND m.category_id = ic.id
    LEFT JOIN " . table('expense_categories') . " ec ON m.type = 'expense' AND m.category_id = ec.id
    LEFT JOIN " . table('social_years') . " sy ON m.social_year_id = sy.id
    LEFT JOIN " . table('members') . " mem ON m.member_id = mem.id
    WHERE 1=1 $yearFilter
    ORDER BY m.paid_at DESC, m.id DESC
");
$movements = $stmt->fetchAll();

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="movimenti_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Start output buffering
ob_start();

// Output BOM for UTF-8
echo "\xEF\xBB\xBF";

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            text-align: left;
        }
        th {
            background-color: #4472C4;
            color: white;
            font-weight: bold;
        }
        .income {
            background-color: #C6E0B4;
        }
        .expense {
            background-color: #F4B084;
        }
        .amount {
            text-align: right;
        }
        .total {
            background-color: #FFD966;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>Esportazione Movimenti - Anno: <?php echo htmlspecialchars($yearName); ?></h2>
    <p>Generato il: <?php echo date('d/m/Y H:i:s'); ?></p>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Data</th>
                <th>Tipo</th>
                <th>Categoria</th>
                <th>Descrizione</th>
                <th>Importo €</th>
                <th>Anno Sociale</th>
                <th>Socio</th>
                <th>CF Socio</th>
                <th>Metodo Pagamento</th>
                <th>N. Ricevuta</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalIncome = 0;
            $totalExpense = 0;
            
            foreach ($movements as $mov): 
                $rowClass = $mov['type'] === 'income' ? 'income' : 'expense';
                
                if ($mov['type'] === 'income') {
                    $totalIncome += $mov['amount'];
                } else {
                    $totalExpense += $mov['amount'];
                }
            ?>
            <tr class="<?php echo $rowClass; ?>">
                <td><?php echo $mov['id']; ?></td>
                <td><?php echo date('d/m/Y', strtotime($mov['paid_at'])); ?></td>
                <td><?php echo $mov['type'] === 'income' ? 'Entrata' : 'Uscita'; ?></td>
                <td><?php echo htmlspecialchars($mov['category_name']); ?></td>
                <td><?php echo htmlspecialchars($mov['description']); ?></td>
                <td class="amount"><?php echo number_format($mov['amount'], 2, ',', '.'); ?></td>
                <td><?php echo htmlspecialchars($mov['year_name'] ?? ''); ?></td>
                <td>
                    <?php 
                    if ($mov['member_id']) {
                        echo htmlspecialchars($mov['first_name'] . ' ' . $mov['last_name']);
                    }
                    ?>
                </td>
                <td><?php echo htmlspecialchars($mov['tax_code'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mov['payment_method'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mov['receipt_number'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($mov['notes'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            
            <!-- Totals -->
            <tr class="total">
                <td colspan="5"><strong>TOTALE ENTRATE</strong></td>
                <td class="amount"><strong><?php echo number_format($totalIncome, 2, ',', '.'); ?></strong></td>
                <td colspan="6"></td>
            </tr>
            <tr class="total">
                <td colspan="5"><strong>TOTALE USCITE</strong></td>
                <td class="amount"><strong><?php echo number_format($totalExpense, 2, ',', '.'); ?></strong></td>
                <td colspan="6"></td>
            </tr>
            <tr class="total">
                <td colspan="5"><strong>SALDO (Entrate - Uscite)</strong></td>
                <td class="amount"><strong><?php echo number_format($totalIncome - $totalExpense, 2, ',', '.'); ?></strong></td>
                <td colspan="6"></td>
            </tr>
        </tbody>
    </table>
    
    <br>
    <p><strong>Riepilogo:</strong></p>
    <ul>
        <li>Totale movimenti: <?php echo count($movements); ?></li>
        <li>Totale entrate: € <?php echo number_format($totalIncome, 2, ',', '.'); ?></li>
        <li>Totale uscite: € <?php echo number_format($totalExpense, 2, ',', '.'); ?></li>
        <li>Saldo: € <?php echo number_format($totalIncome - $totalExpense, 2, ',', '.'); ?></li>
    </ul>
</body>
</html>
<?php
// Flush output
ob_end_flush();
exit;
